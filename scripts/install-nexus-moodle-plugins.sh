#!/usr/bin/env bash

set -Eeuo pipefail

ROOT="$(pwd)"
MOODLE="$ROOT/moodle"

CONTAINER="moodle"
CONTAINER_ROOT="/var/www/moodle"

echo
echo "=========================================================="
echo " Nexus EPS Moodle 4.5 - Production Plugin Installer"
echo "=========================================================="
echo


# ----------------------------------------------------------
# BASIC CHECKS
# ----------------------------------------------------------

if [ ! -f "$ROOT/docker-compose.yml" ]; then
    echo "ERROR: Run this from the nexus-moodle-lms repository root."
    exit 1
fi

if ! docker compose exec -T "$CONTAINER" \
    test -f "$CONTAINER_ROOT/version.php"
then
    echo "ERROR: Moodle core is not available inside Docker."
    exit 1
fi

echo "Moodle Docker installation detected."

docker compose ps >/dev/null

echo "Docker OK."


# ----------------------------------------------------------
# FOLDERS
# ----------------------------------------------------------

mkdir -p \
    "$MOODLE/course/format" \
    "$MOODLE/mod" \
    "$MOODLE/blocks" \
    "$MOODLE/local"


# ----------------------------------------------------------
# HELPERS
# ----------------------------------------------------------

clone_git() {

    local NAME="$1"
    local URL="$2"
    local BRANCH="$3"
    local DEST="$4"

    echo
    echo "----------------------------------------------------------"
    echo " Installing: $NAME"
    echo "----------------------------------------------------------"

    if [ -d "$DEST/.git" ]; then

        echo "Existing plugin checkout detected."

        git -C "$DEST" fetch --all --tags

        if git -C "$DEST" show-ref \
            --verify \
            --quiet \
            "refs/remotes/origin/$BRANCH"
        then
            git -C "$DEST" checkout "$BRANCH"
            git -C "$DEST" pull --ff-only origin "$BRANCH"
        else
            echo "Branch $BRANCH not found."
            exit 1
        fi

    else

        rm -rf "$DEST"

        git clone \
            --depth 1 \
            --branch "$BRANCH" \
            "$URL" \
            "$DEST"

    fi

    if [ ! -f "$DEST/version.php" ]; then
        echo "ERROR: $NAME has no version.php"
        exit 1
    fi

    echo "OK: $NAME"
}


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
# 1. TILES FORMAT
#
# Official repository is Bitbucket, NOT GitHub.
# Moodle 4.5 release exists and Moove compatibility is listed.
# ==========================================================

echo
echo "Installing Tiles from official Bitbucket repository..."

rm -rf "$MOODLE/course/format/tiles"

git clone \
    --depth 1 \
    --branch moodle45 \
    https://bitbucket.org/dw8/moodle-format_tiles.git \
    "$MOODLE/course/format/tiles"

test -f \
    "$MOODLE/course/format/tiles/version.php"

echo "OK: Tiles"


# ==========================================================
# 2. ATTENDANCE
# Moodle 4.5 supported.
# ==========================================================

clone_git \
    "Attendance" \
    "https://github.com/danmarsden/moodle-mod_attendance.git" \
    "MOODLE_405_STABLE" \
    "$MOODLE/mod/attendance"


# ==========================================================
# 3. CUSTOM CERTIFICATE
#
# Official plugin supports Moodle 4.4 + 4.5.
# ==========================================================

clone_git \
    "Custom Certificate" \
    "https://github.com/mdjnelson/moodle-mod_customcert.git" \
    "MOODLE_404_STABLE" \
    "$MOODLE/mod/customcert"


# ==========================================================
# 4. COMPLETION PROGRESS
# ==========================================================

echo
echo "----------------------------------------------------------"
echo " Installing: Completion Progress"
echo "----------------------------------------------------------"

rm -rf "$MOODLE/blocks/completion_progress"

git clone \
    --depth 1 \
    "https://github.com/deraadt/moodle-block_completion_progress.git" \
    "$MOODLE/blocks/completion_progress"

test -f "$MOODLE/blocks/completion_progress/version.php"

echo "OK: Completion Progress"


# ==========================================================
# 5. QUESTIONNAIRE
#
# Correct upstream repository from Moodle plugin directory.
# ==========================================================

echo
echo "----------------------------------------------------------"
echo " Installing: Questionnaire"
echo "----------------------------------------------------------"

rm -rf "$MOODLE/mod/questionnaire"

git clone \
    --depth 1 \
    --branch MOODLE_404_STABLE \
    "https://github.com/remotelearner/moodle-mod_questionnaire.git" \
    "$MOODLE/mod/questionnaire"

test -f "$MOODLE/mod/questionnaire/version.php"

echo "OK: Questionnaire"


# ==========================================================
# 6. DOWNLOAD CENTER
#
# Official Moodle 4.5 release: v4.5.1
# ==========================================================

clone_tag \
    "Download Center" \
    "https://github.com/academic-moodle-cooperation/moodle-local_downloadcenter.git" \
    "v4.5.1" \
    "$MOODLE/local/downloadcenter"


# ----------------------------------------------------------
# VALIDATE VERSION FILES
# ----------------------------------------------------------

echo
echo
echo "=========================================================="
echo " Validating plugin files"
echo "=========================================================="

PLUGINS=(
    "$MOODLE/course/format/tiles"
    "$MOODLE/mod/attendance"
    "$MOODLE/mod/customcert"
    "$MOODLE/blocks/completion_progress"
    "$MOODLE/mod/questionnaire"
    "$MOODLE/local/downloadcenter"
)

for P in "${PLUGINS[@]}"
do

    echo
    echo "Checking:"
    echo "$P"

    test -f "$P/version.php"

    php -l "$P/version.php"

done


# ----------------------------------------------------------
# DEPLOY TO RUNNING MOODLE
# ----------------------------------------------------------

echo
echo
echo "=========================================================="
echo " Deploying plugins to Moodle container"
echo "=========================================================="

copy_plugin \
    "$MOODLE/course/format/tiles" \
    "$CONTAINER_ROOT/course/format/tiles"

copy_plugin \
    "$MOODLE/mod/attendance" \
    "$CONTAINER_ROOT/mod/attendance"

copy_plugin \
    "$MOODLE/mod/customcert" \
    "$CONTAINER_ROOT/mod/customcert"

copy_plugin \
    "$MOODLE/blocks/completion_progress" \
    "$CONTAINER_ROOT/blocks/completion_progress"

copy_plugin \
    "$MOODLE/mod/questionnaire" \
    "$CONTAINER_ROOT/mod/questionnaire"

copy_plugin \
    "$MOODLE/local/downloadcenter" \
    "$CONTAINER_ROOT/local/downloadcenter"


# ----------------------------------------------------------
# PERMISSIONS
# ----------------------------------------------------------

echo
echo "Fixing Moodle plugin permissions..."

docker compose exec -T "$CONTAINER" \
    chown -R www-data:www-data \
    "$CONTAINER_ROOT/course/format/tiles" \
    "$CONTAINER_ROOT/mod/attendance" \
    "$CONTAINER_ROOT/mod/customcert" \
    "$CONTAINER_ROOT/blocks/completion_progress" \
    "$CONTAINER_ROOT/mod/questionnaire" \
    "$CONTAINER_ROOT/local/downloadcenter"


# ----------------------------------------------------------
# MOODLE UPGRADE
# ----------------------------------------------------------

echo
echo
echo "=========================================================="
echo " Running Moodle upgrade"
echo "=========================================================="

docker compose exec -T "$CONTAINER" \
    php admin/cli/upgrade.php \
    --non-interactive


# ----------------------------------------------------------
# CACHE
# ----------------------------------------------------------

echo
echo "Purging Moodle caches..."

docker compose exec -T "$CONTAINER" \
    php admin/cli/purge_caches.php


# ----------------------------------------------------------
# RESTART
# ----------------------------------------------------------

echo
echo "Restarting Moodle..."

docker compose restart \
    moodle \
    moodle-cron


# ----------------------------------------------------------
# VERIFY PLUGIN REGISTRATION
# ----------------------------------------------------------

echo
echo
echo "=========================================================="
echo " Verifying plugin registration"
echo "=========================================================="

docker compose exec -T "$CONTAINER" php -r '

define("CLI_SCRIPT", true);

require "config.php";

$manager = core_plugin_manager::instance();

$plugins = [
    "format_tiles",
    "mod_attendance",
    "mod_customcert",
    "block_completion_progress",
    "mod_questionnaire",
    "local_downloadcenter",
];

$failed = false;

foreach ($plugins as $plugin) {

    $info =
        $manager->get_plugin_info(
            $plugin
        );

    if ($info) {

        echo "[OK]      {$plugin}";

        if (!empty($info->versiondisk)) {
            echo "  version=" . $info->versiondisk;
        }

        echo PHP_EOL;

    } else {

        echo "[MISSING] {$plugin}"
            . PHP_EOL;

        $failed = true;
    }
}

if ($failed) {
    exit(1);
}

'


echo
echo
echo "=========================================================="
echo " NEXUS EPS PLUGIN INSTALLATION COMPLETE"
echo "=========================================================="
echo

echo "Installed:"
echo
echo "  ✓ Tiles"
echo "  ✓ Attendance"
echo "  ✓ Custom Certificate"
echo "  ✓ Completion Progress"
echo "  ✓ Questionnaire"
echo "  ✓ Download Center"
echo
echo "Native Moodle H5P remains enabled."
echo
echo "Admin:"
echo "http://localhost:8080/admin/"
echo

