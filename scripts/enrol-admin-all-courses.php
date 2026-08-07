<?php

define('CLI_SCRIPT', true);

require '/var/www/moodle/config.php';
require_once $CFG->dirroot . '/enrol/locallib.php';

global $DB;

$username = 'admin';

$user = $DB->get_record(
    'user',
    ['username' => $username, 'deleted' => 0],
    '*',
    MUST_EXIST
);

$role = $DB->get_record(
    'role',
    ['shortname' => 'editingteacher'],
    '*',
    MUST_EXIST
);

$manual = enrol_get_plugin('manual');

if (!$manual) {
    throw new moodle_exception('Manual enrolment plugin is unavailable.');
}

$courses = $DB->get_records_select(
    'course',
    'id <> :siteid',
    ['siteid' => SITEID],
    'id ASC'
);

foreach ($courses as $course) {
    $instances = enrol_get_instances($course->id, true);
    $manualinstance = null;

    foreach ($instances as $instance) {
        if ($instance->enrol === 'manual') {
            $manualinstance = $instance;
            break;
        }
    }

    if (!$manualinstance) {
        $instanceid = $manual->add_instance($course);

        $manualinstance = $DB->get_record(
            'enrol',
            ['id' => $instanceid],
            '*',
            MUST_EXIST
        );
    }

    if (!$DB->record_exists('user_enrolments', [
        'enrolid' => $manualinstance->id,
        'userid' => $user->id,
    ])) {
        $manual->enrol_user(
            $manualinstance,
            $user->id,
            $role->id
        );

        echo "ENROLLED: {$course->shortname}" . PHP_EOL;
    } else {
        echo "ALREADY ENROLLED: {$course->shortname}" . PHP_EOL;
    }
}

echo PHP_EOL . 'Admin enrolment completed.' . PHP_EOL;
