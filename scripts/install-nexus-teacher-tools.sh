#!/usr/bin/env bash

set -Eeuo pipefail

ROOT="$(pwd)"
MOODLE="$ROOT/moodle"

CONTAINER="moodle"
CONTAINER_ROOT="/var/www/moodle"

echo
echo "=========================================================="
echo " Nexus EPS Moodle 4.5 - Teacher Tools Installer"
echo "=========================================================="
echo


# ----------------------------------------------------------
# CHECKS
# ----------------------------------------------------------

if [ ! -f "$ROOT/docker-compose.yml" ]; then
    echo "ERROR: Run this from nexus-moodle-lms repository root."
    exit 1
fi

if ! docker compose exec -T "$CONTAINER" \
    test -f "$CONTAINER_ROOT/version.php"
then
    echo "ERROR: Moodle core is not available inside Docker."
    exit 1
fi

echo "Moodle Docker installation detected."
echo


mkdir -p \
    "$MOODLE/mod" \
    "$MOODLE/local" \
    "$MOODLE/blocks"


# ----------------------------------------------------------
# GENERIC TAG INSTALLER
# ----------------------------------------------------------

clone_tag() {

    local NAME="$1"
    local URL="$2"
    local TAG="$3"
    local DEST="$4"

    echo
    echo "----------------------------------------------------------"
    echo " Installing: $NAME"
    echo "----------------------------------------------------------"

    rm -rf "$DEST"

    git clone \
        --depth 1 \
        --branch "$TAG" \
        "$URL" \
        "$DEST"

    if [ ! -f "$DEST/version.php" ]; then
        echo "ERROR: $NAME has no version.php"
        exit 1
    fi

    php -l "$DEST/version.php" >/dev/null

    echo "OK: $NAME"
}


copy_plugin() {

    local SOURCE="$1"
    local DEST="$2"

    echo
    echo "Deploying:"
    echo "$SOURCE"
    echo "->"
    echo "$DEST"

    docker compose exec -T "$CONTAINER" \
        rm -rf "$DEST"

    docker compose exec -T "$CONTAINER" \
        mkdir -p "$(dirname "$DEST")"

    docker compose cp \
        "$SOURCE" \
        "$CONTAINER:$DEST"
}


# ==========================================================
# 1. PDF ANNOTATOR
#
# Stable v1.5.9:
# Moodle 4.5 + 5.0
# ==========================================================

clone_tag \
    "PDF Annotator" \
    "https://github.com/rwthmoodle/moodle-mod_pdfannotator.git" \
    "v1.5.9" \
    "$MOODLE/mod/pdfannotator"


# ==========================================================
# 2. AI GRADE
#
# Uses Moodle core AI subsystem.
# Moodle 4.5+
#
# Clone default branch because plugin is new and maintained.
# ==========================================================

echo
echo "----------------------------------------------------------"
echo " Installing: AI Grade"
echo "----------------------------------------------------------"

rm -rf "$MOODLE/local/aigrade"

git clone \
    --depth 1 \
    "https://github.com/brianpool/moodle-local_aigrade.git" \
    "$MOODLE/local/aigrade"

if [ ! -f "$MOODLE/local/aigrade/version.php" ]; then
    echo "ERROR: AI Grade version.php missing."
    exit 1
fi

php -l "$MOODLE/local/aigrade/version.php" >/dev/null

echo "OK: AI Grade"


# ==========================================================
# 3. AI FOR TEACHERS / PROMPT GENERATOR
#
# Course-aware prompt builder.
# Can use Ollama for locally hosted/free inference.
# ==========================================================

echo
echo "----------------------------------------------------------"
echo " Installing: AI Prompt Generator"
echo "----------------------------------------------------------"

rm -rf "$MOODLE/blocks/aipromptgen"

git clone \
    --depth 1 \
    "https://github.com/bobangajic/moodle-block_aipromptgen.git" \
    "$MOODLE/blocks/aipromptgen"

if [ ! -f "$MOODLE/blocks/aipromptgen/version.php" ]; then
    echo "ERROR: AI Prompt Generator version.php missing."
    exit 1
fi

php -l "$MOODLE/blocks/aipromptgen/version.php" >/dev/null

echo "OK: AI Prompt Generator"


# ----------------------------------------------------------
# DEPLOY
# ----------------------------------------------------------

echo
echo "=========================================================="
echo " Deploying teacher tools"
echo "=========================================================="

copy_plugin \
    "$MOODLE/mod/pdfannotator" \
    "$CONTAINER_ROOT/mod/pdfannotator"

copy_plugin \
    "$MOODLE/local/aigrade" \
    "$CONTAINER_ROOT/local/aigrade"

copy_plugin \
    "$MOODLE/blocks/aipromptgen" \
    "$CONTAINER_ROOT/blocks/aipromptgen"


# ----------------------------------------------------------
# PERMISSIONS
# ----------------------------------------------------------

docker compose exec -T "$CONTAINER" \
    chown -R www-data:www-data \
    "$CONTAINER_ROOT/mod/pdfannotator" \
    "$CONTAINER_ROOT/local/aigrade" \
    "$CONTAINER_ROOT/blocks/aipromptgen"


# ----------------------------------------------------------
# MOODLE UPGRADE
# ----------------------------------------------------------

echo
echo "=========================================================="
echo " Running Moodle upgrade"
echo "=========================================================="

docker compose exec -T "$CONTAINER" \
    php admin/cli/upgrade.php \
    --non-interactive


# ----------------------------------------------------------
# CACHE + RESTART
# ----------------------------------------------------------

docker compose exec -T "$CONTAINER" \
    php admin/cli/purge_caches.php

docker compose restart \
    moodle \
    moodle-cron


# ----------------------------------------------------------
# VERIFY
# ----------------------------------------------------------

echo
echo "=========================================================="
echo " Verifying teacher tools"
echo "=========================================================="

docker compose exec -T "$CONTAINER" php -r '

define("CLI_SCRIPT", true);
require "config.php";

$manager = core_plugin_manager::instance();

$plugins = [
    "mod_pdfannotator",
    "local_aigrade",
    "block_aipromptgen",
];

$failed = false;

foreach ($plugins as $plugin) {

    $info =
        $manager->get_plugin_info(
            $plugin
        );

    if ($info) {

        echo "[OK]      "
            . $plugin
            . PHP_EOL;

    } else {

        echo "[MISSING] "
            . $plugin
            . PHP_EOL;

        $failed = true;
    }
}

if ($failed) {
    exit(1);
}

'


echo
echo "=========================================================="
echo " COMPLETE"
echo "=========================================================="
echo
echo "Installed:"
echo " ✓ PDF Annotator"
echo " ✓ AI Grade"
echo " ✓ AI Prompt Generator"
echo
echo "Remember:"
echo " Moodle core Assignment already provides:"
echo " ✓ Rubrics"
echo " ✓ Marking guides"
echo " ✓ Feedback comments"
echo " ✓ Feedback files"
echo " ✓ PDF grading annotation"
echo " ✓ Grading workflow"
echo
echo "Admin:"
echo "http://localhost:8080/admin/"
echo

