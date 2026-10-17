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
    return [x for x in re.findall(r"[^\W_]+", t, re.UNICODE) if len(x) >= 3 and x not in stop]

def weighted(d):
    result = Counter(terms(d["body"]))
    result.update({k: 3 * v for k, v in Counter(terms(d["title"])).items()})
    result.update({k: 2 * v for k, v in Counter(terms(d["heading"])).items()})
    return result

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

def decision(src, dst):
    """Conservative safety triage; not semantic intent classification."""
    if src["id"] == dst["id"]:
        return "reject", "self_link"
    if dst["id"] in src["outgoing"]:
        return "reject", "already_linked"
    if dst["status"] != "publish" or not dst["indexable"]:
        return "reject", "ineligible_target"
    shared = set(src["topics"]) & set(dst["topics"])
    if len(shared) >= 2 and src["intent"] == dst["intent"]:
        return "uncertain", "possible_same_intent"
    if not shared:
        return "uncertain", "insufficient_topic_evidence"
    return "review", "candidate_only"

print("Source -> target                       BM25   Expected   Triage")
for p in data["pairs"]:
    src, dst = docs[p["source"]],docs[p["target"]]
    triage, reason = decision(src, dst)
    print(f'{p["source"][:17]:17} -> {p["target"][:17]:17} {bm25(src,dst):6.2f}  {p["expected"]:9}  {triage}: {reason}')
print("Triage is a conservative diagnostic; 'review' is not an automatic link recommendation.")
print("Scores are corpus-relative ranking signals, not accuracy percentages.")
