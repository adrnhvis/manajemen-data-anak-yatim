#!/usr/bin/env python3
"""
K-Means untuk kinerja administrasi pengajuan per kelurahan/desa.

Kontrak:
  --input   JSON: {"features": [...], "rows": [{"village_id": "...", <fitur>: angka|null}, ...]}
  --output  JSON: hasil run (k, silhouette, centroid, assignment per wilayah)

Semua fitur bersifat "makin besar makin berat beban administrasinya"
(lebih banyak revisi, lebih sering ditolak, lebih lama merespons).
Asumsi ini dipakai untuk mengurutkan cluster dari paling lancar ke paling berat.
"""
import argparse
import json
import math
import sys

import numpy as np
from sklearn.cluster import KMeans
from sklearn.metrics import silhouette_score
from sklearn.preprocessing import StandardScaler

LOG_FEATURES = {"avg_resubmit_days", "avg_first_submit_days"}
MIN_VILLAGES = 10
RANDOM_STATE = 42

CLUSTER_NAMES = {
    2: ["Administrasi lancar", "Perlu pendampingan"],
    3: ["Administrasi lancar", "Sedang", "Perlu pendampingan"],
    4: ["Sangat lancar", "Lancar", "Sedang", "Perlu pendampingan"],
}


def fail(message: str) -> None:
    print(json.dumps({"error": message}, ensure_ascii=False), file=sys.stderr)
    sys.exit(2)


def quality_label(score: float) -> str:
    if score >= 0.5:
        return "baik"
    if score >= 0.25:
        return "lemah"
    return "tidak_bermakna"


def label_for(rank: int, k: int) -> str:
    names = CLUSTER_NAMES.get(k)
    return names[rank] if names else f"Cluster {rank + 1} dari {k}"


def load_matrix(payload: dict):
    features = payload.get("features") or []
    rows = payload.get("rows") or []
    if not features:
        fail("Daftar fitur kosong.")
    raw = np.array(
        [[np.nan if r.get(f) is None else float(r[f]) for f in features] for r in rows],
        dtype=float,
    )
    return features, rows, raw


def preprocess(features, raw):
    """Imputasi median -> log1p untuk fitur durasi -> standarisasi."""
    filled = raw.copy()
    for j in range(filled.shape[1]):
        col = filled[:, j]
        mask = np.isnan(col)
        if mask.all():
            filled[:, j] = 0.0
        elif mask.any():
            filled[mask, j] = np.nanmedian(col)
    transformed = filled.copy()
    for j, name in enumerate(features):
        if name in LOG_FEATURES:
            transformed[:, j] = np.log1p(np.clip(transformed[:, j], 0, None))
    return StandardScaler().fit_transform(transformed)


def choose_k(z, k_fixed, kmin, kmax):
    n = z.shape[0]
    min_size = max(3, math.ceil(0.05 * n))
    if k_fixed is not None:
        if not 2 <= k_fixed < n:
            fail(f"k={k_fixed} tidak valid untuk {n} wilayah.")
        candidates = [k_fixed]
    else:
        candidates = list(range(kmin, min(kmax, n - 1) + 1))

    scores, fits = {}, {}
    for k in candidates:
        model = KMeans(n_clusters=k, n_init=20, random_state=RANDOM_STATE).fit(z)
        sizes = np.bincount(model.labels_, minlength=k)
        n_labels = len(np.unique(model.labels_))
        if n_labels < 2 or n_labels >= n:
            scores[k] = -1.0
        else:
            scores[k] = float(silhouette_score(z, model.labels_))
        fits[k] = (model, int(sizes.min()))

    # Mode otomatis: abaikan k yang menghasilkan cluster sangat kecil (biasanya outlier),
    # kecuali semua kandidat memang demikian.
    eligible = [k for k in candidates if fits[k][1] >= min_size] or candidates
    best = max(eligible, key=lambda k: (round(scores[k], 6), -k))
    return best, fits[best][0], scores


def main() -> None:
    ap = argparse.ArgumentParser()
    ap.add_argument("--input", required=True)
    ap.add_argument("--output", required=True)
    ap.add_argument("--k", type=int, default=None)
    ap.add_argument("--kmin", type=int, default=2)
    ap.add_argument("--kmax", type=int, default=6)
    args = ap.parse_args()

    with open(args.input, encoding="utf-8") as fh:
        payload = json.load(fh)

    features, rows, raw = load_matrix(payload)
    n = len(rows)
    if n < MIN_VILLAGES:
        fail(f"Data terlalu sedikit untuk K-Means: {n} wilayah (minimal {MIN_VILLAGES}).")

    z = preprocess(features, raw)
    if np.unique(np.round(z, 9), axis=0).shape[0] < 3:
        fail(
            f"Hanya {np.unique(np.round(z, 9), axis=0).shape[0]} pola nilai fitur yang berbeda di {n} wilayah. "
            "K-Means tidak bermakna pada data tanpa variasi (mis. seeder dummy tanpa riwayat revisi/penolakan)."
        )
    k, model, scores = choose_k(z, args.k, args.kmin, args.kmax)

    # Urutkan cluster dari paling lancar ke paling berat (rata-rata fitur terstandarisasi).
    burden = z[:, :].mean(axis=1)
    raw_order = sorted(range(k), key=lambda c: float(burden[model.labels_ == c].mean()))
    rank_of = {old: new for new, old in enumerate(raw_order)}

    assignments = []
    for i, r in enumerate(rows):
        rank = rank_of[int(model.labels_[i])]
        assignments.append(
            {"village_id": r["village_id"], "cluster_index": rank, "cluster_label": label_for(rank, k)}
        )

    centroids = []
    for old in raw_order:
        mask = model.labels_ == old
        means = {}
        for j, name in enumerate(features):
            vals = raw[mask, j]
            means[name] = None if np.isnan(vals).all() else round(float(np.nanmean(vals)), 3)
        centroids.append(
            {
                "cluster_index": rank_of[old],
                "cluster_label": label_for(rank_of[old], k),
                "size": int(mask.sum()),
                "means": means,
            }
        )

    silhouette = scores[k]
    result = {
        "k": k,
        "silhouette": round(silhouette, 4),
        "quality": quality_label(silhouette),
        "inertia": round(float(model.inertia_), 4),
        "n_clustered": n,
        "feature_names": features,
        "k_scores": {str(kk): round(v, 4) for kk, v in scores.items()},
        "centroids": centroids,
        "assignments": assignments,
    }
    with open(args.output, "w", encoding="utf-8") as fh:
        json.dump(result, fh, ensure_ascii=False)


if __name__ == "__main__":
    main()
