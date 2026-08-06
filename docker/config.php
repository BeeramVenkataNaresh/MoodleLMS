<?php
// Moodle configuration for the local Nexus Education Private School stack.
// Secrets are read only from the ignored .env file passed into the containers.

unset($CFG);
global $CFG;
$CFG = new stdClass();

$required = static function (string $name): string {
    $value = getenv($name);
    if ($value === false || $value === '') {
        throw new RuntimeException("Required environment variable {$name} is not set.");
    }
    return $value;
};

$CFG->dbtype = 'mariadb';
$CFG->dblibrary = 'native';
$CFG->dbhost = 'db';
$CFG->dbname = $required('MOODLE_DB_NAME');
$CFG->dbuser = $required('MOODLE_DB_USER');
$CFG->dbpass = $required('MOODLE_DB_PASSWORD');
$CFG->prefix = 'mdl_';
$CFG->dboptions = [
    'dbpersist' => false,
    'dbport' => 3306,
    'dbsocket' => false,
    'dbcollation' => 'utf8mb4_unicode_ci',
];

$CFG->wwwroot = rtrim($required('MOODLE_URL'), '/');
$CFG->dataroot = '/var/www/moodledata';
$CFG->admin = 'admin';
$CFG->directorypermissions = 02770;

// Redis is used for both PHP/Moodle sessions and Moodle application cache mappings.
$CFG->session_handler_class = '\\core\\session\\redis';
$CFG->session_redis_host = $required('REDIS_HOST');
$CFG->session_redis_port = (int) ($required('REDIS_PORT'));
$CFG->session_redis_database = 0;
$CFG->session_redis_auth = $required('REDIS_PASSWORD');
$CFG->session_redis_prefix = 'nexuseps_sess_';
$CFG->session_redis_acquire_lock_timeout = 120;
$CFG->session_redis_lock_expire = 7200;
$CFG->session_redis_lock_retry = 100;
$CFG->session_redis_connection_timeout = 3.0;
$CFG->session_redis_read_timeout = 3.0;
$CFG->session_redis_serializer_use_igbinary = false;
$CFG->session_redis_compressor = 'none';

// Mailpit catches all outgoing development email; no external mail service is used.
$CFG->smtphosts = $required('MOODLE_SMTP_HOST') . ':' . $required('MOODLE_SMTP_PORT');
$CFG->smtpsecure = '';
$CFG->noreplyaddress = $required('MOODLE_ADMIN_EMAIL');
$CFG->supportemail = $required('MOODLE_ADMIN_EMAIL');

// Nexus deployment references, retained in configuration and backup exports.
$CFG->nexus_portal_name = $required('MOODLE_PORTAL_NAME');
$CFG->nexus_production_url = $required('MOODLE_PRODUCTION_URL');
$CFG->nexus_public_website = $required('MOODLE_PUBLIC_WEBSITE');

$CFG->dirroot = __DIR__;
$CFG->libdir = $CFG->dirroot . '/lib';

require_once(__DIR__ . '/lib/setup.php');
