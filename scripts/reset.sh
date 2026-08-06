#!/usr/bin/env bash
set -Eeuo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "${ROOT_DIR}"

if [[ "${1:-}" != "--yes" ]]; then
    echo "This permanently removes the Nexus Moodle application, moodledata, MariaDB, and Redis local volumes."
    echo "Create a backup first with ./scripts/backup.sh."
    read -r -p "Type RESET to continue: " confirmation
    [[ "${confirmation}" == "RESET" ]] || { echo "Reset cancelled."; exit 1; }
fi

docker compose down --volumes --remove-orphans
echo "Nexus local Moodle volumes were removed. Run ./scripts/start.sh to create a fresh installation."
