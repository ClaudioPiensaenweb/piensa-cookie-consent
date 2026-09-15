#!/usr/bin/env python3
"""Turn a Plugin Check result file into a pass or fail.

`wp plugin check` can print ERROR findings and still exit 0, so the exit code
alone is not a gate. This reads the machine-readable output and rejects errors,
empty output and malformed results alike: a check that silently produced
nothing must fail loudly rather than look like a pass.
"""
import json
import sys
import os


def fail(message):
    print("::error::%s" % message)
    raise SystemExit(1)


def main():
    if len(sys.argv) < 2:
        fail("usage: check-plugin-check.py <results.json>")

    path = sys.argv[1]

    if not os.path.exists(path):
        fail("Plugin Check wrote no result file at %s" % path)

    raw = open(path, encoding="utf-8").read().strip()

    if not raw:
        fail("Plugin Check produced an empty result file")

    # The CLI prints a per-file header before each JSON array, so the file is a
    # sequence of blocks rather than one document.
    findings = []
    current_file = ""

    for line in raw.split("\n"):
        line = line.strip()
        if not line:
            continue
        if line.startswith("FILE:"):
            current_file = line[len("FILE:"):].strip()
            continue
        if not line.startswith("["):
            continue
        try:
            block = json.loads(line)
        except ValueError as error:
            fail("could not parse Plugin Check output: %s" % error)
        for item in block:
            item["file"] = item.get("file") or current_file
            findings.append(item)

    errors = [f for f in findings if str(f.get("type", "")).upper() == "ERROR"]
    warnings = [f for f in findings if str(f.get("type", "")).upper() == "WARNING"]

    for finding in errors + warnings:
        print(
            "%s %s:%s  [%s] %s"
            % (
                finding.get("type", "?"),
                finding.get("file", "?"),
                finding.get("line", "?"),
                finding.get("code", "?"),
                finding.get("message", ""),
            )
        )

    print()
    print("Errors: %d, warnings: %d" % (len(errors), len(warnings)))

    if errors:
        fail("Plugin Check reported %d error(s); the directory requires none" % len(errors))

    print("Plugin Check passed the plugin_repo category.")


if __name__ == "__main__":
    main()
