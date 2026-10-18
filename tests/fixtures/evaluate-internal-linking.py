#!/usr/bin/env python3
"""Compatibility entry point: execute assertion tests against production PHP.

PHP (with pdo_sqlite, DOM and mbstring) must be on PATH, or supplied via PHP_BINARY.
This wrapper deliberately contains no second ranking/tokenization algorithm.
"""
import os
import pathlib
import shutil
import subprocess
import sys

php = os.environ.get("PHP_BINARY") or shutil.which("php")
if not php:
    sys.exit("PHP not found. Set PHP_BINARY or run evaluate-internal-linking.php directly.")
sys.exit(subprocess.call([php, str(pathlib.Path(__file__).with_suffix(".php")), *sys.argv[1:]]))
