#!/usr/bin/env bash
set -Eeuo pipefail

MOODLE_DIR="${MOODLE_DIR:-/var/www/moodle}"
MOODLE_DATA_DIR="${MOODLE_DATA_DIR:-/var/www/moodledata}"
INSTALL_MARKER="${MOODLE_DATA_DIR}/.nexus-install-complete"
CONFIG_TEMPLATE="/usr/local/share/nexus-moodle/config.php"

finish_install() {
    install -o www-data -g www-data -m 0640 "${CONFIG_TEMPLATE}" "${MOODLE_DIR}/config.php"
    runuser -u www-data -- php /usr/local/bin/nexus-configure-moodle.php
    touch "${INSTALL_MARKER}"
    chown www-data:www-data "${INSTALL_MARKER}"
}

if [[ -f "${INSTALL_MARKER}" ]]; then
    echo "Moodle is already installed. Verifying local configuration."
    install -o www-data -g www-data -m 0640 "${CONFIG_TEMPLATE}" "${MOODLE_DIR}/config.php"
    exit 0
fi

# Moodle's CLI installer intentionally refuses to run when config.php exists.
# If the database is already installed, resume only the idempotent post-install
# configuration. Otherwise remove an incomplete generated config.php and retry.
if [[ -f "${MOODLE_DIR}/config.php" ]]; then
    installed_tables="$(MYSQL_PWD="${MOODLE_DB_PASSWORD}" mariadb \
        --protocol=tcp --skip-column-names --batch -h db \
        -u "${MOODLE_DB_USER}" "${MOODLE_DB_NAME}" \
        -e "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema='${MOODLE_DB_NAME}' AND table_name='mdl_config';")"
    if [[ "${installed_tables}" == "1" ]]; then
        echo "Moodle database already exists; completing local configuration."
        finish_install
        echo "Moodle installation and Nexus local configuration completed."
        exit 0
    fi

    echo "Removing incomplete-install config.php before retrying the installer."
    rm -f "${MOODLE_DIR}/config.php"
fi

mkdir -p "${MOODLE_DATA_DIR}"
chown -R www-data:www-data "${MOODLE_DIR}" "${MOODLE_DATA_DIR}"

echo "Installing Moodle 4.5 for ${MOODLE_SITE_FULLNAME}..."
runuser -u www-data -- php "${MOODLE_DIR}/admin/cli/install.php" \
    --non-interactive \
    --agree-license \
    --lang=en \
    --wwwroot="${MOODLE_URL}" \
    --dataroot="${MOODLE_DATA_DIR}" \
    --dbtype=mariadb \
    --dbhost=db \
    --dbport=3306 \
    --dbname="${MOODLE_DB_NAME}" \
    --dbuser="${MOODLE_DB_USER}" \
    --dbpass="${MOODLE_DB_PASSWORD}" \
    --fullname="${MOODLE_SITE_FULLNAME}" \
    --shortname="${MOODLE_SITE_SHORTNAME}" \
    --adminuser="${MOODLE_ADMIN_USER}" \
    --adminpass="${MOODLE_ADMIN_PASSWORD}" \
    --adminemail="${MOODLE_ADMIN_EMAIL}" \
    --supportemail="${MOODLE_ADMIN_EMAIL}"

finish_install

echo "Moodle installation and Nexus local configuration completed."
