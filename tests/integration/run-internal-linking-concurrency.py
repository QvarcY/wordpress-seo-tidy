#!/usr/bin/env python3
"""Only the four targeted Phase C concurrency/small-corpus regressions.

Reuses the previously provisioned and marked local WP/MariaDB environment,
the real administrator scan callback, and the original first-INSERT barrier.
No provisioning, large seeding, benchmarks, HTTP suite or full contracts run.
"""
import importlib.util
import json
import os
from pathlib import Path
import subprocess
import sys
import time
from datetime import datetime, timezone

sys.dont_write_bytecode = True
HERE = Path(__file__).resolve().parent
REPO = HERE.parent.parent
SPEC = importlib.util.spec_from_file_location("phase_c_previous", HERE / "run-internal-linking-phase-c.py")
PREVIOUS = importlib.util.module_from_spec(SPEC)
SPEC.loader.exec_module(PREVIOUS)
PROBE = "internal-linking-phase-c-concurrency.php"
PERF = "internal-linking-phase-c-performance.php"


class Concurrency(PREVIOUS.Validation):
    def __init__(self):
        super().__init__()
        self.token = str(time.time_ns())[-12:]
        self.logs = self.logs / ("concurrency-" + self.token)
        self.logs.mkdir()
        self.environment = dict(os.environ, SEO_TIDY_PHASE_C_LOG_DIR=str(self.logs))
        self.evidence_path = REPO / "docs/evidence/internal-linking-phase-c-concurrency.json"
        self.evidence = {
            "started_utc": datetime.now(timezone.utc).isoformat(),
            "scope": "Concurrent scans, edit during scan, interrupted scan/recovery, small sequential indexing only",
            "corpus": "Existing 25 published synthetic posts; 975 draft posts are not rebuilt",
            "benchmarks_rerun": False,
            "logs_directory_relative_to_isolated_environment": self.logs.relative_to(self.env_root).as_posix(),
            "checks": [], "stages": {}, "passed": False,
        }
        self.workers = []
        self.persist()

    def persist(self):
        self.evidence_path.parent.mkdir(exist_ok=True)
        self.evidence_path.write_text(json.dumps(self.evidence, indent=2, ensure_ascii=False) + "\n", encoding="utf-8")

    def check(self, condition, description):
        self.evidence["checks"].append({"pass": bool(condition), "description": description})
        self.persist()
        if not condition:
            raise RuntimeError(description)

    def record(self, name, result):
        self.evidence["stages"][name] = result
        self.persist()
        return result

    def probe(self, label, mode="inspect", post_id=None):
        args = [mode]
        if post_id is not None:
            args.append(post_id)
        if mode != "inspect":
            args.append(self.token + "-" + label.replace("_", "-"))
        value = self.record(label, self.php_run(PROBE, args, label))
        self.check(not value["database_error"], label + ": no database error")
        if hasattr(self, "topics"):
            self.check(value["smart_topics_sha256"] == self.topics, label + ": Smart Topics preserved")
        return value

    def scan(self, label):
        value = self.record(label, self.php_run("internal-linking-phase-c.php", ["scan"], label))
        self.check(not value["database_error"] and not value["fatal"], label + ": scan has no SQL/fatal error")
        text = (self.logs / (label + ".log")).read_text(encoding="utf-8")
        self.check("WordPress database error" not in text and "Duplicate entry" not in text,
                   label + ": scan log has no database/duplicate-key error")
        return value

    def start_worker(self, kind, restart=False):
        worker = "cf-" + kind + "-" + self.token
        handle = (self.logs / (worker + ".log")).open("w", encoding="utf-8")
        args = ["race-scan", worker] + (["--restart"] if restart else [])
        process = subprocess.Popen(self.command(PERF, args), cwd=REPO,
                                   env=self.environment, stdout=handle, stderr=subprocess.STDOUT)
        item = {"name": worker, "process": process, "handle": handle}
        self.workers.append(item)
        path = self.wp_root / "phase-c-races" / (worker + ".ready.json")
        ready = self.wait_file(path, process)
        self.record(kind + "_barrier", ready)
        return item, ready

    def release_worker(self, item, label):
        directory = self.wp_root / "phase-c-races"
        (directory / (item["name"] + ".release")).write_text("release", encoding="utf-8")
        result_code = item["process"].wait(timeout=45)
        item["handle"].close()
        value = json.loads((directory / (item["name"] + ".result.json")).read_text(encoding="utf-8-sig"))
        self.record(label, value)
        self.check(result_code == 0, label + ": worker exits normally")
        self.check(not value["last_db_error"] and not value["captured_db_errors"],
                   label + ": no captured database errors")
        fatal = value["last_php_error"]
        self.check(not fatal or fatal["type"] not in (1, 4, 16, 64), label + ": no fatal PHP error")
        text = (self.logs / (item["name"] + ".log")).read_text(encoding="utf-8")
        self.check("Duplicate entry" not in text and "WordPress database error" not in text,
                   label + ": no duplicate-key/database error in log")
        return value

    @staticmethod
    def healthy(state):
        return bool(state.get("complete") and not state.get("stale") and not state.get("in_progress"))

    def assert_unavailable(self, value, label):
        self.check(not self.healthy(value["state"]), label + ": fresh public state is unusable")
        self.check(not self.healthy(value["raw_state"]), label + ": raw persisted state is unusable")
        self.check(value["corpus"]["count"] == 0 and value["candidate_ids"] == [],
                   label + ": corpus statistics and indexed candidate retrieval are gated")

    def assert_healthy(self, value, label):
        self.check(self.healthy(value["state"]) and self.healthy(value["raw_state"]),
                   label + ": fresh and persisted index state is complete and current")
        self.check(value["indexed_documents"] == 25 and value["state"]["processed"] == 25,
                   label + ": exactly 25 published documents are indexed")
        self.check(value["mismatched_vectors"] == [], label + ": all 25 vectors match current WordPress content")
        self.check(value["duplicate_primary_keys"] == [], label + ": no duplicate posting keys")

    def run(self):
        initial = self.probe("initial")
        self.topics = initial["smart_topics_sha256"]
        self.evidence["environment"] = initial["environment"]
        post_id = initial["post_id"]

        # One small batch establishes a baseline, followed by the healthy no-op path.
        self.probe("sequential_touch", "touch", post_id)
        self.scan("sequential_scan")
        self.assert_healthy(self.probe("sequential_complete"), "sequential")
        no_op = self.scan("sequential_noop")
        self.check("scan_status=complete" in (no_op["redirect"] or ""),
                   "sequential: completed scan is a no-op")

        self.probe("overlap_touch", "touch", post_id)
        a, ready = self.start_worker("overlap")
        self.check(ready["post_id"] == post_id, "overlap: barrier uses the first published article")
        before = self.probe("overlap_active_before")
        self.assert_unavailable(before, "overlap while worker A is active")
        b = self.scan("overlap_scan_b")
        self.check(a["process"].poll() is None, "overlap: worker A remains paused during worker B")
        self.check("scan_status=busy" in (b["redirect"] or ""), "overlap: worker B returns busy")
        after = self.probe("overlap_active_after")
        self.assert_unavailable(after, "overlap after competing request")
        self.check(before["raw_state"] == after["raw_state"] and before["postings"] == after["postings"],
                   "overlap: busy worker neither changes active state nor writes postings")
        self.release_worker(a, "overlap_worker_a")
        self.assert_healthy(self.probe("overlap_complete"), "overlap completion")

        self.probe("edit_touch", "touch", post_id)
        a, ready = self.start_worker("edit")
        edited = self.probe("edit_during_scan", "edit", ready["post_id"])
        self.assert_unavailable(edited, "edit while worker A is active")
        self.check(ready["snapshot_content_sha256"] != edited["body_sha256"], "edit changes the captured article body")
        self.release_worker(a, "edit_worker_a")
        stale = self.probe("edit_after_scan")
        self.assert_unavailable(stale, "edit after old worker finishes")
        self.scan("edit_recovery_scan")
        self.assert_healthy(self.probe("edit_recovered"), "edit recovery")

        # Start a forced rebuild from a healthy index, not an already-stale save.
        # Its active marker must survive termination before the first INSERT.
        a, _ = self.start_worker("interrupt", restart=True)
        self.assert_unavailable(self.probe("interrupted_active"), "interrupted before termination")
        a["process"].terminate()
        code = a["process"].wait(timeout=15)
        a["handle"].close()
        self.record("interrupted_termination", {"exit_code": code,
                    "method": "Terminate the paused PHP process without releasing its first-INSERT barrier"})
        self.check(code != 0, "interrupted: terminated worker did not complete normally")
        self.assert_unavailable(self.probe("interrupted_after_termination"), "interrupted after termination")
        recovery = self.scan("interrupted_recovery_scan")
        self.check("scan_status=busy" not in (recovery["redirect"] or ""),
                   "interrupted: disconnected owner no longer holds the database lock")
        self.assert_healthy(self.probe("interrupted_recovered"), "interrupted recovery")
        self.evidence["passed"] = True
        self.evidence["finished_utc"] = datetime.now(timezone.utc).isoformat()
        self.evidence["assertions"] = len(self.evidence["checks"])
        self.persist()
        print(f"PASS {len(self.evidence['checks'])} targeted assertions; evidence: {self.evidence_path}", flush=True)

    def close(self):
        for item in self.workers:
            if item["process"].poll() is None:
                item["process"].terminate()
                item["process"].wait(timeout=15)
            item["handle"].close()


if __name__ == "__main__":
    validation = None
    try:
        validation = Concurrency()
        validation.run()
    except Exception as error:
        if validation is not None:
            validation.evidence["failure"] = str(error)
            validation.evidence["assertions"] = len(validation.evidence["checks"])
            validation.persist()
        print(f"FAILED: {error}", file=sys.stderr)
        sys.exit(1)
    finally:
        if validation is not None:
            validation.close()
