#!/usr/bin/env bash
set -Eeuo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "${ROOT_DIR}"

if [[ ! -f .env ]]; then
    echo "Missing .env. Run ./scripts/generate-secrets.sh first."
    exit 1
fi

docker compose config -q
docker compose up --build -d

echo "Waiting for Moodle's one-time installation container..."
for _ in $(seq 1 180); do
    init_id="$(docker compose ps -aq moodle-init)"
    if [[ -n "${init_id}" ]]; then
        init_status="$(docker inspect --format '{{.State.Status}}:{{.State.ExitCode}}' "${init_id}")"
        case "${init_status}" in
            exited:0)
                break
                ;;
            exited:*)
                docker compose logs --no-color moodle-init
                echo "Moodle installation failed. Inspect the logs above."
                exit 1
                ;;
        esac
    fi
    sleep 2
done

if [[ "${init_status:-}" != "exited:0" ]]; then
    docker compose logs --no-color moodle-init
    echo "Timed out waiting for Moodle installation."
    exit 1
fi

docker compose up -d moodle moodle-cron phpmyadmin
echo "Moodle is starting. Check readiness with: docker compose ps"
echo "Moodle: http://localhost:8080"
echo "Mailpit: http://localhost:8025"
echo "phpMyAdmin: http://localhost:8081"
