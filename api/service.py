# run 'python -m flask run'

from flask       import Flask, render_template, jsonify, request
from collections import defaultdict
from nltk.corpus import stopwords, wordnet
from nltk.stem   import WordNetLemmatizer
from flask_cors  import CORS
from scipy       import spatial
from sklearn     import preprocessing
from heapq       import *


import json, io

# Wrapper funtion to simplify calls to calculate cosine similarity
def cos(a, b):
    return spatial.distance.cosine(a, b)

# Wrapper function to simplify calls to normalize vectors to the unit circle
def unit(v):
    return preprocessing.normalize([v], norm='l2')[0]

# initialize the app and enable cors
app = Flask(__name__)
cors = CORS(app, resources={r"/service/*": {"origins": "*"}})

# load in index
index = json.loads(io.open('index.json').read())

lemmatizer = WordNetLemmatizer()


## NOTE: all the above variables are loaded once and maintained in memory across sessions

@app.route('/service/search', methods = ['POST', 'GET', 'OPTIONS'])
def search():
    # find the movies containing the term and rank
    data = searchTerm(request.args.get('query'))
    # return the response data
    response = {
        'data': data
    }
    return jsonify(response)

@app.route('/service/related', methods = ['POST', 'GET', 'OPTIONS'])
def related():
    results = []
    for r in request.args.get('query').split():
	results.extend([n.name() for syn in wordnet.synsets(r) for n in syn.lemmas()])
    results = list(set([r for r in results if lemmatizer.lemmatize(lemmatizer.lemmatize(r, 'v')) in index]))
    return jsonify({ 'data': results })

# --------------------------------Helper Functions-----------------------------
def searchTerm(term):
    _stopwords = set(stopwords.words('english'))
    terms = [lemmatizer.lemmatize(lemmatizer.lemmatize(t.strip(), 'v')) for t in term.split(" ")]
    terms = tuple([t for t in [t for t in terms if t not in _stopwords] if t in index])
    if not terms: return {}
    template = { term: 0.0 for term in terms } # Will initialize scores for each term to 0, needed to have full vector of all terms for each doc
    results = defaultdict(lambda: defaultdict(lambda: 0.0, template))

    for term in terms:
        idf = index[term]['score']['idf']
        for movie, tf in index[term]['postings'].iteritems():
            results[movie][term] += tf * idf

    # Then we can proceed with the actual cosine similarity, which is pretty simple
    query_vector = unit([1.0 for term in range(len(terms))]) # Query vector, normalized
    scores = dict()

    for entry in results: # Turn vectors from dicts to arrays, and normalize and do cosine similarity
        scores[entry] = 1 - cos(query_vector, unit([x[1] for x in sorted(results[entry].items())]))
        scores[entry] *= sum([x for x in results[entry].values()])

    return dict(sorted(scores.items(), key = lambda x: -x[1])[:40])
