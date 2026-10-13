"""Boot the built plugin on an isolated, pinned Axolotl-PM runtime."""

from __future__ import annotations

import hashlib
import json
import os
import shutil
import subprocess
import tempfile
import time
from pathlib import Path


server_phar_value = os.environ.get("SERVER_PHAR")
server_source_value = os.environ.get("SERVER_SOURCE")
if (server_phar_value is None) == (server_source_value is None):
    raise RuntimeError("Set exactly one of SERVER_PHAR or SERVER_SOURCE")
server_phar = Path(server_phar_value).resolve(strict=True) if server_phar_value is not None else None
server_source = Path(server_source_value).resolve(strict=True) if server_source_value is not None else None
plugin = Path(os.environ["PLUGIN_PHAR"]).resolve(strict=True)
server_commit = None

if server_source is not None:
    server_entrypoint = server_source / "src" / "PocketMine.php"
    if not server_entrypoint.is_file() or not (server_source / "vendor" / "autoload.php").is_file():
        raise RuntimeError("Axolotl-PM source and Composer dependencies are required")
    server_commit = subprocess.run(
        ["git", "-C", str(server_source), "rev-parse", "HEAD"],
        check=True,
        capture_output=True,
        text=True,
    ).stdout.strip()
    expected_server_commit = os.environ["EXPECTED_SERVER_COMMIT"]
    if server_commit != expected_server_commit:
        raise RuntimeError(f"Expected Axolotl-PM source {expected_server_commit}, got {server_commit}")
else:
    assert server_phar is not None
    server_entrypoint = server_phar

with tempfile.TemporaryDirectory(prefix="redskyblockx-smoke-", dir=os.environ.get("RUNNER_TEMP")) as temporary:
    root = Path(temporary)
    data = root / "data"
    plugins = root / "plugins"
    data.mkdir()
    plugins.mkdir()
    shutil.copy2(plugin, plugins / "RedSkyBlockX.phar")
    (data / "server.properties").write_text(
        "language=eng\nserver-ip=127.0.0.1\nserver-port=0\nenable-ipv6=off\n"
        "enable-query=off\nxbox-auth=off\nlevel-type=FLAT\nview-distance=2\nmax-players=1\n",
        encoding="utf-8",
    )
    (data / "pocketmine.yml").write_text(
        "settings:\n  async-workers: 2\n  enable-dev-builds: true\n  send-usage: false\n"
        "auto-report:\n  enabled: false\nauto-updater:\n  enabled: false\n"
        "network:\n  upnp-forwarding: false\n",
        encoding="utf-8",
    )

    log = root / "console.log"
    with log.open("w", encoding="utf-8") as output:
        process = subprocess.Popen(
            ["php", str(server_entrypoint), "--no-wizard", "--disable-ansi", f"--data={data}", f"--plugins={plugins}"],
            cwd=server_source if server_source is not None else root,
            stdin=subprocess.PIPE,
            stdout=output,
            stderr=subprocess.STDOUT,
            text=True,
        )
        reason = "timeout"
        try:
            deadline = time.monotonic() + 90
            while time.monotonic() < deadline and process.poll() is None:
                content = log.read_text(encoding="utf-8", errors="replace")
                if "Done (" in content:
                    reason = "ready"
                    break
                if "/CRITICAL]:" in content or "/EMERGENCY]:" in content:
                    reason = "crash"
                    break
                time.sleep(0.25)

            if reason == "ready" and process.poll() is None and process.stdin is not None:
                process.stdin.write("stop\n")
                process.stdin.flush()
                try:
                    exit_code = process.wait(timeout=30)
                except subprocess.TimeoutExpired:
                    process.kill()
                    exit_code = process.wait()
            else:
                if process.poll() is None:
                    process.kill()
                exit_code = process.wait()
        finally:
            if process.poll() is None:
                process.kill()
                process.wait()

    content = log.read_text(encoding="utf-8", errors="replace")
    errors = [
        line for line in content.splitlines()
        if any(marker in line for marker in ("/ERROR]:", "/CRITICAL]:", "/EMERGENCY]:"))
    ]
    evidence = {
        "plugin_sha256": hashlib.sha256(plugin.read_bytes()).hexdigest(),
        "server_commit": server_commit,
        "server_ready": "Done (" in content,
        "plugin_enabled": "Enabling RedSkyBlockX" in content,
        "plugin_disabled": "Disabling RedSkyBlockX" in content,
        "exit_code": exit_code,
        "errors": errors,
    }
    print(json.dumps(evidence, indent=2))
    if (
        reason != "ready"
        or not evidence["plugin_enabled"]
        or not evidence["plugin_disabled"]
        or exit_code != 0
        or errors
    ):
        print(content[-6000:])
        raise RuntimeError("Isolated RedSkyBlockX startup failed")
