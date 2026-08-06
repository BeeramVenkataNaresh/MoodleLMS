#!/usr/bin/env bash
set -Eeuo pipefail

MOODLE_DIR="${MOODLE_DIR:-/var/www/moodle}"
MOODLE_DATA_DIR="${MOODLE_DATA_DIR:-/var/www/moodledata}"
MOODLE_SOURCE_DIR="${MOODLE_SOURCE_DIR:-/usr/src/moodle}"
CONFIG_TEMPLATE="/usr/local/share/nexus-moodle/config.php"
INSTALL_MARKER="${MOODLE_DATA_DIR}/.nexus-install-complete"

mkdir -p "${MOODLE_DIR}" "${MOODLE_DATA_DIR}"

# A named volume mounted at MOODLE_DIR starts empty and masks the image files.
# Seed it once so Moodle core, themes, and later-installed plugins survive rebuilding.
if [[ ! -f "${MOODLE_DIR}/version.php" ]]; then
    echo "Seeding the persistent Moodle application volume..."
    rsync -a --delete "${MOODLE_SOURCE_DIR}/" "${MOODLE_DIR}/"
fi

# The installer must create its temporary config.php first. Once installation succeeds,
# this environment-driven configuration is applied on every application/cron start.
if [[ -f "${INSTALL_MARKER}" ]]; then
    install -o www-data -g www-data -m 0640 "${CONFIG_TEMPLATE}" "${MOODLE_DIR}/config.php"
fi

chown -R www-data:www-data "${MOODLE_DIR}" "${MOODLE_DATA_DIR}"

exec "$@"
