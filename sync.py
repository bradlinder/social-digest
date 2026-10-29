#!/usr/bin/env python3
"""2-Way Synchronization Engine for Social Digest WordPress Plugin.

Enables seamless two-way code synchronization between local Antigravity
and Google AI Studio via the central GitHub repository (origin/main).

Usage:
  python sync.py --push            # Stage all changes, commit with project version, and push to GitHub
  python sync.py --push "Message"  # Commit with custom message and push
  python sync.py --pull            # Pull latest changes from GitHub into local workspace
  python sync.py --status          # Check local vs remote git sync status (default)
"""

from __future__ import annotations

import argparse
import json
import re
import subprocess
import sys
from pathlib import Path

ROOT_DIR = Path(__file__).resolve().parent


def get_current_version() -> str:
    """Read version from social-digest.php, readme.txt, or package.json."""
    php_path = ROOT_DIR / "social-digest.php"
    if php_path.is_file():
        content = php_path.read_text(encoding="utf-8", errors="ignore")
        m = re.search(r"define\(\s*['\"]SOCIAL_DIGEST_VERSION['\"]\s*,\s*['\"]([^'\"]+)['\"]\s*\)", content)
        if m:
            return m.group(1).strip()
        m_hdr = re.search(r"\*\s*Version:\s*([0-9\.]+)", content)
        if m_hdr:
            return m_hdr.group(1).strip()

    pkg_path = ROOT_DIR / "package.json"
    if pkg_path.is_file():
        try:
            data = json.loads(pkg_path.read_text(encoding="utf-8"))
            if "version" in data:
                return str(data["version"]).strip()
        except Exception:
            pass

    return "update"


def run_cmd(cmd: list[str], check: bool = True) -> subprocess.CompletedProcess:
    """Run a subprocess command in the project root directory."""
    return subprocess.run(cmd, cwd=str(ROOT_DIR), check=check, text=True, capture_output=True)


def cmd_status() -> int:
    """Display current git branch status and unpushed/unpulled commits."""
    print("=== Social Digest Git Synchronization Status ===")
    res_branch = run_cmd(["git", "branch", "--show-current"], check=False)
    branch = res_branch.stdout.strip() or "main"
    print(f"Active Branch: {branch}")

    # Fetch latest remote references
    print(f"[SYNC] Fetching latest status from origin/{branch}...")
    run_cmd(["git", "fetch", "origin", branch], check=False)

    res_status = run_cmd(["git", "status", "--short"], check=False)
    dirty_lines = [line for line in res_status.stdout.splitlines() if line.strip()]

    res_ahead_behind = run_cmd(
        ["git", "rev-list", "--left-right", "--count", f"HEAD...origin/{branch}"],
        check=False
    )
    ahead, behind = 0, 0
    if res_ahead_behind.returncode == 0 and res_ahead_behind.stdout.strip():
        parts = res_ahead_behind.stdout.strip().split()
        if len(parts) >= 2:
            ahead, behind = int(parts[0]), int(parts[1])

    print(f"Sync State:    {ahead} commit(s) ahead, {behind} commit(s) behind origin/{branch}")
    if dirty_lines:
        print(f"Local Changes: {len(dirty_lines)} modified/untracked file(s):")
        for line in dirty_lines[:15]:
            print(f"  {line}")
        if len(dirty_lines) > 15:
            print(f"  ... and {len(dirty_lines) - 15} more files.")
    else:
        print("Local Changes: Working tree clean.")
    print("================================================")
    return 0


def cmd_pull() -> int:
    """Pull latest changes from GitHub into local workspace."""
    print("[SYNC] Fetching and pulling latest changes from GitHub (origin/main)...")
    res = run_cmd(["git", "pull", "--rebase", "--autostash", "origin", "main"], check=False)
    if res.returncode != 0:
        print(f"[!] Git pull failed:\n{res.stderr or res.stdout}")
        print("\nTip: If you have conflicting local changes, you can run:")
        print("  git stash")
        print("  python sync.py --pull")
        print("  git stash pop")
        return 1

    print(res.stdout.strip() or "Already up to date.")

    # Try optional verification if node and verify script exist
    verify_script = ROOT_DIR / "scripts" / "verify-all.cjs"
    if verify_script.is_file():
        try:
            ver_res = subprocess.run(["node", "scripts/verify-all.cjs"], cwd=str(ROOT_DIR), check=False, text=True, capture_output=True)
            if ver_res.returncode == 0:
                print("[SYNC] AST syntax verification passed.")
        except Exception:
            pass

    print("[SYNC] Local workspace is now up to date with GitHub.")
    return 0


def cmd_push(commit_msg: str | None = None) -> int:
    """Stage all changes, commit, and push to GitHub."""
    version = get_current_version()
    msg = commit_msg or f"release: synchronize v{version} updates"

    print("[SYNC] Staging changes...")
    run_cmd(["git", "add", "-A"])

    res_diff = run_cmd(["git", "diff", "--cached", "--quiet"], check=False)
    if res_diff.returncode == 0:
        print("[SYNC] No staged changes to commit.")
    else:
        print(f"[SYNC] Committing changes with message: '{msg}'")
        res_commit = run_cmd(["git", "commit", "-m", msg], check=False)
        if res_commit.returncode != 0:
            print(f"[!] Commit failed:\n{res_commit.stderr or res_commit.stdout}")
            return 1
        print(res_commit.stdout.strip().splitlines()[-1] if res_commit.stdout else "Committed.")

    print("[SYNC] Pushing to GitHub (origin/main)...")
    res_push = run_cmd(["git", "push", "origin", "main"], check=False)
    if res_push.returncode != 0:
        print(f"[!] Git push failed:\n{res_push.stderr or res_push.stdout}")
        print("\nIf authentication is required, make sure your GitHub credentials / token are configured:")
        print("  git remote set-url origin https://<TOKEN>@github.com/bradlinder/social-digest.git")
        return 1

    print(res_push.stderr.strip() or res_push.stdout.strip() or "Pushed.")
    print("[SYNC] Successfully pushed to https://github.com/bradlinder/social-digest (main).")
    return 0


def main() -> int:
    parser = argparse.ArgumentParser(description="2-Way Git Sync between Antigravity and AI Studio.")
    parser.add_argument("--push", nargs="?", const="", default=None, help="Commit and push changes to GitHub (optional custom commit message)")
    parser.add_argument("--pull", action="store_true", help="Pull latest changes from GitHub into local workspace")
    parser.add_argument("--status", action="store_true", help="Display local and remote synchronization status")

    args = parser.parse_args()

    if args.pull:
        return cmd_pull()
    if args.status:
        return cmd_status()
    if args.push is not None:
        commit_msg = args.push.strip() if args.push else None
        return cmd_push(commit_msg)

    # Default if no arguments: show status
    return cmd_status()


if __name__ == "__main__":
    sys.exit(main())
