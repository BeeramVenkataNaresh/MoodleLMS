#!/usr/bin/env bash
set -Eeuo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "${ROOT_DIR}"

# Stops containers but intentionally keeps all Moodle, MariaDB, and Redis volumes.
docker compose down
