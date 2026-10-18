#!/usr/bin/env python3
"""Focused real WP/MariaDB validation; refuses any unmarked/non-loopback install.

Prepare a disposable installation as described in docs/internal-linking-phase-c.md.
Stages are separate to avoid rerunning schemas or rebuilding measured corpora.
"""
import argparse
import http.cookiejar
import json
import math
import os
from pathlib import Path
import statistics
import subprocess
import sys
import time
import urllib.error
import urllib.parse
import urllib.request

HERE = Path(__file__).resolve().parent
REPO = HERE.parent.parent


class NoRedirect(urllib.request.HTTPRedirectHandler):
    def redirect_request(self, req, fp, code, msg, headers, newurl):
        return None


def decode_result(output):
    for line in reversed(output.splitlines()):
        if line.startswith("PHASE_C_JSON "):
            return json.loads(line[len("PHASE_C_JSON "):])
    if "PHASE_C_JSON_BEGIN" in output:
        return json.loads(output.split("PHASE_C_JSON_BEGIN", 1)[1].split("PHASE_C_JSON_END", 1)[0])
    raise RuntimeError("Missing JSON result; inspect the stage log")


def distribution(values):
    values = sorted(values)
    return {"n": len(values), "min": min(values), "median": statistics.median(values),
            "p95": values[max(0, math.ceil(len(values) * .95) - 1)], "max": max(values)}


class Validation:
    def __init__(self):
        self.env_root = Path(os.environ["SEO_TIDY_PHASE_C_ENV_ROOT"]).resolve()
        self.wp_root = Path(os.environ["SEO_TIDY_PHASE_C_WP_ROOT"]).resolve()
        marker = json.loads((self.env_root / "isolation-marker.json").read_text(encoding="utf-8-sig"))
        if (os.environ.get("SEO_TIDY_PHASE_C_ISOLATED") != "1"
                or self.wp_root != self.env_root / "wordpress"
                or not (self.wp_root / ".seo-tidy-phase-c-isolated").is_file()
                or marker.get("db_name") != "seo_tidy_phase_c"
                or marker.get("db_host") != "127.0.0.1:33929"
                or marker.get("home") != "http://127.0.0.1:8896"):
            raise RuntimeError("Disposable environment guard failed")
        self.php = os.environ.get("PHP_BINARY", "php")
        self.ini = self.env_root / "php.ini"
        self.logs = self.env_root / "logs"
        self.logs.mkdir(exist_ok=True)
        self.environment = dict(os.environ, SEO_TIDY_PHASE_C_LOG_DIR=str(self.logs))
        self.ledger_path = self.logs / "integration-results.json"
        self.ledger = json.loads(self.ledger_path.read_text(encoding="utf-8-sig")) if self.ledger_path.exists() else {}
        self.base = "http://127.0.0.1:8896"

    def save(self, key, value):
        self.ledger[key] = value
        self.ledger_path.write_text(json.dumps(self.ledger, indent=2, ensure_ascii=False), encoding="utf-8")

    def command(self, script, args):
        return [self.php, "-c", str(self.ini), str(HERE / script), *map(str, args)]

    def php_run(self, script, args, label):
        started = time.perf_counter()
        result = subprocess.run(self.command(script, args), cwd=REPO, env=self.environment,
                                capture_output=True, text=True, encoding="utf-8", errors="replace", timeout=120)
        (self.logs / (label + ".log")).write_text(result.stdout + "\n" + result.stderr, encoding="utf-8")
        if result.returncode:
            raise RuntimeError(f"{label} exit {result.returncode}; see stage log")
        value = decode_result(result.stdout)
        value["process_wall_seconds"] = time.perf_counter() - started
        return value

    def contracts(self):
        value = self.php_run("internal-linking-phase-c.php", ["contracts"], "contracts")
        self.save("contracts", value)
        print(f"Contracts: {value['checks']} assertions, {value['failures']} failures", flush=True)

    def client(self):
        jar = http.cookiejar.CookieJar()
        return (urllib.request.build_opener(urllib.request.ProxyHandler({}), urllib.request.HTTPCookieProcessor(jar)),
                urllib.request.build_opener(urllib.request.ProxyHandler({}), urllib.request.HTTPCookieProcessor(jar), NoRedirect()))

    def request(self, client, route, data=None, headers=None, redirect=True):
        encoded = urllib.parse.urlencode(data).encode() if data is not None else None
        request = urllib.request.Request(self.base + route, data=encoded, headers=headers or {})
        try:
            response = client[0 if redirect else 1].open(request, timeout=30)
        except urllib.error.HTTPError as error:
            response = error
        return response.status, response.read().decode("utf-8", "replace"), dict(response.headers)

    def http(self):
        credentials = json.loads((self.env_root / "test-users.json").read_text(encoding="utf-8-sig"))
        checks = []

        def check(ok, description, status=None):
            checks.append({"pass": bool(ok), "description": description, "http_status": status})
            print(("PASS " if ok else "FAIL ") + description, flush=True)

        anonymous = self.client()
        route = "/?rest_route=/seo-tidy/v1/internal-linking"
        status, _, _ = self.request(anonymous, route)
        check(status == 401, "Anonymous linking REST request rejected", status)
        clients = {}
        nonces = {}
        for role in ("admin", "subscriber"):
            client = self.client()
            self.request(client, "/wp-login.php")
            status, _, _ = self.request(client, "/wp-login.php", {
                "log": "phasec_admin" if role == "admin" else "phasec_subscriber",
                "pwd": credentials[role + "_password"], "wp-submit": "Log In",
                "redirect_to": self.base + "/wp-admin/", "testcookie": "1"})
            nonce_status, nonce, _ = self.request(client, "/wp-admin/admin-ajax.php?action=rest-nonce")
            check(nonce_status == 200 and len(nonce.strip()) == 10,
                  role + " authenticated cookie obtains real REST nonce", nonce_status)
            clients[role] = client
            nonces[role] = nonce.strip()
        admin, subscriber = clients["admin"], clients["subscriber"]
        admin_headers = {"X-WP-Nonce": nonces["admin"]}
        status, body, _ = self.request(subscriber, route, headers={"X-WP-Nonce": nonces["subscriber"]})
        check(status == 403, "Subscriber linking REST request rejected", status)
        status, _, _ = self.request(admin, route)
        check(status == 401, "Cookie REST request without nonce is unauthenticated", status)
        status, _, _ = self.request(admin, route, headers={"X-WP-Nonce": "invalid"})
        check(status == 403, "Invalid REST nonce rejected", status)
        status, body, _ = self.request(admin, route, headers=admin_headers)
        overview = json.loads(body)
        check(status == 200 and all(k in overview for k in ("items", "scanNonce", "ready", "processed")),
              "Administrator linking REST request returns collection and scan nonce", status)
        before = {k: overview[k] for k in ("ready", "processed", "stale")}
        status, _, _ = self.request(admin, route + "&page=0", headers=admin_headers)
        check(status == 400, "REST rejects page below minimum", status)
        status, body, _ = self.request(admin, route + "&page=2", headers=admin_headers)
        check(status == 200 and json.loads(body).get("page") == 2, "REST pagination reaches page two", status)
        status, body, _ = self.request(admin, route + "&search=WordPress", headers=admin_headers)
        check(status == 200 and json.loads(body).get("total", 0) > 0, "REST search returns matching articles", status)
        status, _, _ = self.request(admin, "/?rest_route=/seo-tidy/v1/audit", headers=admin_headers)
        check(status == 200, "Existing SEO Audit REST responds for administrator", status)
        status, _, _ = self.request(subscriber, "/wp-admin/admin.php?page=seo-tidy-linking-preview")
        check(status == 403, "Subscriber cannot open linking administration page", status)
        status, _, _ = self.request(anonymous, "/wp-admin/admin-post.php",
                                    {"action": "seo_tidy_link_scan", "_wpnonce": "invalid"}, redirect=False)
        check(status == 400, "Anonymous admin-post scan has no public handler", status)
        status, body, _ = self.request(subscriber, "/wp-admin/admin-post.php",
                                       {"action": "seo_tidy_link_scan", "_wpnonce": "invalid"}, redirect=False)
        check(status >= 400 and "Permission denied" in body, "Subscriber scan denied before mutation", status)
        status, _, _ = self.request(admin, "/wp-admin/admin-post.php",
                                    {"action": "seo_tidy_link_scan", "_wpnonce": "invalid"}, redirect=False)
        check(status == 403, "Administrator scan with invalid nonce rejected", status)
        status, body, _ = self.request(admin, route, headers=admin_headers)
        current = json.loads(body)
        check({k: current[k] for k in before} == before, "Denied scans leave index state unchanged", status)
        status, _, headers = self.request(admin, "/wp-admin/admin-post.php", {
            "action": "seo_tidy_link_scan", "_wpnonce": current["scanNonce"], "return_to": "dashboard"}, redirect=False)
        check(status == 302 and "scan_status=" in headers.get("Location", ""),
              "Authorized actual admin-post scan redirects to dashboard", status)
        result = {"checks": len(checks), "failures": sum(not item["pass"] for item in checks), "results": checks,
                  "scope": "Actual loopback HTTP, WordPress sessions, REST nonce checks and admin-post routing; no browser visual audit"}
        self.save("http", result)
        print(f"HTTP: {result['checks']} checks, {result['failures']} failures", flush=True)
        if result["failures"]:
            raise RuntimeError("HTTP contract failure; see ledger")

    def benchmark(self, count):
        perf = "internal-linking-phase-c-performance.php"
        seeded = self.php_run(perf, ["seed" + str(count)], f"seed-{count}")
        self.save(f"seed_{count}", seeded)
        print(f"Seeded {count} synthetic posts", flush=True)
        batches = []
        for number in range(1, math.ceil(count / 25) + 2):
            value = self.php_run("internal-linking-phase-c.php", ["scan"], f"scan-{count}-{number:02}")
            batches.append(value)
            state = value["state"]
            if value.get("database_error") or value.get("fatal") or state.get("stale"):
                raise RuntimeError("Scan failed; inspect batch log")
            if state.get("complete"):
                break
        if not state.get("complete") or state.get("processed") != count:
            raise RuntimeError("Incomplete or inconsistent benchmark scan")
        measured = self.php_run(perf, ["measure"], f"measure-{count}")
        result = {"seed": seeded, "batches": batches,
                  "scan_callback_ms": distribution([b["seconds"] * 1000 for b in batches]),
                  "scan_timing_scope": "After WordPress bootstrap; includes administrator/nonce setup and actual scan action",
                  "scan_callback_total_seconds": sum(b["seconds"] for b in batches),
                  "scan_process_wall_seconds": sum(b["process_wall_seconds"] for b in batches),
                  "scan_peak_bytes_max": max(b["peak_bytes"] for b in batches),
                  "scan_queries_total": sum(b["queries"] for b in batches), "measure": measured}
        self.save(f"benchmark_{count}", result)
        print(f"{count}: {len(batches)} batches; callback total {result['scan_callback_total_seconds']:.3f}s; "
              f"suggestion median {measured['suggestion_ms']['median']:.3f}ms", flush=True)

    def wait_file(self, path, process, timeout=30):
        deadline = time.monotonic() + timeout
        while not path.exists():
            if process.poll() is not None:
                raise RuntimeError(f"Race process exited before {path.name}")
            if time.monotonic() > deadline:
                raise RuntimeError(f"Race barrier timeout: {path.name}")
            time.sleep(.03)
        return json.loads(path.read_text(encoding="utf-8-sig"))

    def races(self):
        perf = "internal-linking-phase-c-performance.php"
        prepared = self.php_run(perf, ["race-prepare"], "race-prepare")
        self.save("race_prepare", prepared)
        directory = self.wp_root / "phase-c-races"
        processes = []
        files = []

        def start(worker):
            log = (self.logs / (worker + ".log")).open("w", encoding="utf-8")
            files.append(log)
            proc = subprocess.Popen(self.command(perf, ["race-scan", worker]), cwd=REPO, env=self.environment,
                                    stdout=log, stderr=subprocess.STDOUT)
            processes.append(proc)
            ready = self.wait_file(directory / (worker + ".ready.json"), proc)
            return proc, ready

        def release(worker, proc):
            (directory / (worker + ".release")).write_text("release", encoding="utf-8")
            proc.wait(timeout=45)
            return json.loads((directory / (worker + ".result.json")).read_text(encoding="utf-8-sig"))

        suffix = str(time.time_ns())
        try:
            a, ready_a = start("scan-a-" + suffix)
            b, ready_b = start("scan-b-" + suffix)
            result_a = release("scan-a-" + suffix, a)
            result_b = release("scan-b-" + suffix, b)
            report_ab = self.php_run(perf, ["race-report", ready_a["post_id"]], "race-two-scans-report")
            self.save("concurrent_scans", {"ready_a": ready_a, "ready_b": ready_b,
                                           "worker_a": result_a, "worker_b": result_b, "report": report_ab})
            print("Concurrent scan probe recorded", flush=True)
            # A competing request's cached stale value may never reach the database.
            # Start the edit probe from an explicit real save, not that assumption.
            self.php_run(perf, ["race-invalidate", ready_a["post_id"]], "race-pre-edit-invalidate")
            c, ready_c = start("scan-edit-" + suffix)
            edited = self.php_run(perf, ["race-edit", ready_c["post_id"]], "race-edit")
            result_c = release("scan-edit-" + suffix, c)
            report_c = self.php_run(perf, ["race-report", ready_c["post_id"]], "race-edit-report")
            self.save("edit_during_scan", {"ready": ready_c, "edit": edited, "worker": result_c, "report": report_c})
            print("Edit-during-scan probe recorded", flush=True)
            final_state = report_c["stats"]["state"]
            unsafe = bool(final_state.get("complete") and not final_state.get("stale")
                          and not report_c["stored_matches_current_vector"])
            self.save("race_findings", {
                "overlapping_scans_duplicate_key": "Duplicate entry" in json.dumps(result_b),
                "two_scan_failure_marks_stale": bool(report_ab["stats"]["state"].get("stale")),
                "edit_marks_stale_before_release": bool(edited["state"].get("stale")),
                "completed_ready_index_contains_obsolete_vector": unsafe,
                "concurrency_acceptance_passed": not unsafe,
            })
            if unsafe:
                raise RuntimeError("Concurrency safety failed: an in-flight scan erased edit invalidation and completed with obsolete postings")
        finally:
            for proc in processes:
                if proc.poll() is None:
                    proc.terminate()
            for log in files:
                log.close()

    def edit_race(self):
        """Resume only the edit probe after an interrupted combined race stage."""
        perf = "internal-linking-phase-c-performance.php"
        previous = self.ledger["concurrent_scans"]
        post_id = previous["ready_a"]["post_id"]
        self.php_run(perf, ["race-invalidate", post_id], "race-pre-edit-invalidate")
        worker = "edit-" + str(time.time_ns())
        directory = self.wp_root / "phase-c-races"
        with (self.logs / (worker + ".log")).open("w", encoding="utf-8") as log:
            proc = subprocess.Popen(self.command(perf, ["race-scan", worker]), cwd=REPO, env=self.environment,
                                    stdout=log, stderr=subprocess.STDOUT)
            try:
                ready = self.wait_file(directory / (worker + ".ready.json"), proc)
                edited = self.php_run(perf, ["race-edit", ready["post_id"]], "race-edit")
                (directory / (worker + ".release")).write_text("release", encoding="utf-8")
                proc.wait(timeout=45)
                result = json.loads((directory / (worker + ".result.json")).read_text(encoding="utf-8-sig"))
                report = self.php_run(perf, ["race-report", ready["post_id"]], "race-edit-report")
                self.save("edit_during_scan", {"ready": ready, "edit": edited, "worker": result, "report": report})
                state = report["stats"]["state"]
                unsafe = bool(state.get("complete") and not state.get("stale") and not report["stored_matches_current_vector"])
                self.save("race_findings", {
                    "overlapping_scans_duplicate_key": "Duplicate entry" in json.dumps(previous["worker_b"]),
                    "two_scan_failure_marks_stale": bool(previous["report"]["stats"]["state"].get("stale")),
                    "edit_marks_stale_before_release": bool(edited["state"].get("stale")),
                    "completed_ready_index_contains_obsolete_vector": unsafe,
                    "concurrency_acceptance_passed": not unsafe,
                })
                print("Edit-during-scan probe recorded", flush=True)
                if unsafe:
                    raise RuntimeError("Concurrency safety failed: in-flight scan completed with obsolete postings after a real edit")
            finally:
                if proc.poll() is None:
                    proc.terminate()


def main():
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("stage", choices=["contracts", "http", "benchmark200", "benchmark1000", "races", "edit-race"])
    args = parser.parse_args()
    validation = Validation()
    if args.stage == "contracts":
        validation.contracts()
    elif args.stage == "http":
        validation.http()
    elif args.stage.startswith("benchmark"):
        validation.benchmark(int(args.stage[len("benchmark"):]))
    elif args.stage == "races":
        validation.races()
    else:
        validation.edit_race()


if __name__ == "__main__":
    try:
        main()
    except Exception as error:
        print(f"FAILED: {error}", file=sys.stderr)
        sys.exit(1)
