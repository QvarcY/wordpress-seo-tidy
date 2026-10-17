#!/usr/bin/env python3
"""Standalone BM25 evaluation of synthetic fixtures. Standard library only."""
import json, math, pathlib, re, sys, unicodedata
from collections import Counter

HERE = pathlib.Path(__file__).resolve().parent
data = json.loads((HERE / "internal-linking-fixtures.json").read_text(encoding="utf-8"))
docs = {d["id"]: d for d in data["documents"]}
stop = set("the and for with from this that into where what are can how the your while and where kā kur kas vai arī bet pie par no un ar".split())

def terms(value):
    t = unicodedata.normalize("NFC", value).lower()
    return [x for x in re.findall(r"[^\\W_]+", t, re.UNICODE) if len(x) >= 3 and x not in stop]

def weighted(d):
    return Counter(terms(d["title"]))*3 + Counter(terms(d["heading"]))*2 + Counter(terms(d["body"]))

corpus = {k: weighted(d) for k,d in docs.items()}
lengths = {k: sum(v.values()) for k,v in corpus.items()}
avgdl = sum(lengths.values()) / max(len(lengths), 1)
df = Counter(t for bag in corpus.values() for t in bag)
n = len(corpus)

def bm25(src, dst, k1=1.2, b=0.75):
    query = set(terms(src["title"] + " " + src["heading"] + " " + " ".join(src["topics"])))
    bag = corpus[dst["id"]]
    score = 0.0
    for term in query:
        freq = bag[term]
        if not freq: continue
        idf = math.log(1 + (n - df[term] + .5) / (df[term] + .5))
        denom = freq + k1 * (1 - b + b * lengths[dst["id"]] / avgdl)
        score += idf * (freq * (k1 + 1)) / denom
    return score

print("Pair       BM25  Label       Overlap(topic)")
for p in data["pairs"]:
    src, dst = docs[p["source"]],docs[p["target"]]
    overlap = len(set(terms(" ".join(src["topics"]))) & set(terms(" ".join(dst["topics"]))))
    print(f'{p["source"][:10]:10} -> {p["target"][:10]:10} {bm25(src,dst):6.2f}  {p["expected"]:9}  {overlap}')
print("Scores are corpus-relative ranking signals, not accuracy percentages.")
