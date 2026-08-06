#!/usr/bin/env bash
set -Eeuo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "${ROOT_DIR}"

if [[ -e .env ]]; then
    echo ".env already exists; it was not replaced. Remove it intentionally to generate a new local configuration."
    exit 1
fi

cp .env.example .env

replace_value() {
    local key="$1"
    local value="$2"
    perl -0pi -e "s/^${key}=.*$/${key}=${value}/m" .env
}

replace_value MYSQL_ROOT_PASSWORD "$(openssl rand -hex 32)"
replace_value MOODLE_DB_PASSWORD "$(openssl rand -hex 32)"
replace_value REDIS_PASSWORD "$(openssl rand -hex 32)"
replace_value MOODLE_ADMIN_PASSWORD "NexusAdmin-$(openssl rand -hex 24)"

chmod 600 .env
echo "Created .env with unique local secrets. Keep it private and out of Git."
