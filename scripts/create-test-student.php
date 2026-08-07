<?php

define('CLI_SCRIPT', true);

chdir('/var/www/moodle');
require 'config.php';

require_once($CFG->dirroot . '/user/lib.php');
require_once($CFG->dirroot . '/lib/enrollib.php');

global $DB;

$username = 'johndoe';
$password = 'TestStudent@123';
$firstname = 'John';
$lastname = 'Doe';
$email = 'johndoe@example.com';

$coursecodes = [
    'MHF4U',
    'SPH4U',
];

echo PHP_EOL;
echo "Nexus EPS — Test Student" . PHP_EOL;
echo "========================" . PHP_EOL;

$user = $DB->get_record(
    'user',
    ['username' => $username]
);

if (!$user) {
    $userdata = (object)[
        'auth' => 'manual',
        'confirmed' => 1,
        'mnethostid' => $CFG->mnet_localhost_id,
        'username' => $username,
        'password' => $password,
        'firstname' => $firstname,
        'lastname' => $lastname,
        'email' => $email,
        'city' => 'Toronto',
        'country' => 'CA',
        'lang' => 'en',
    ];

    $userid = user_create_user(
        $userdata,
        true,
        false
    );

    $user = $DB->get_record(
        'user',
        ['id' => $userid],
        '*',
        MUST_EXIST
    );

    echo "CREATED: John Doe" . PHP_EOL;
} else {
    echo "USER EXISTS: John Doe" . PHP_EOL;
}

$studentrole = $DB->get_record(
    'role',
    ['shortname' => 'student'],
    '*',
    MUST_EXIST
);

$manualplugin = enrol_get_plugin('manual');

if (!$manualplugin) {
    throw new RuntimeException(
        'Manual enrolment plugin not found.'
    );
}

foreach ($coursecodes as $code) {
    $course = $DB->get_record(
        'course',
        ['shortname' => $code],
        '*',
        MUST_EXIST
    );

    $context = context_course::instance(
        $course->id
    );

    if (
        is_enrolled(
            $context,
            $user,
            '',
            true
        )
    ) {
        echo "ALREADY ENROLLED: {$code}" . PHP_EOL;
        continue;
    }

    $instance = $DB->get_record(
        'enrol',
        [
            'courseid' => $course->id,
            'enrol' => 'manual',
            'status' => ENROL_INSTANCE_ENABLED,
        ]
    );

    if (!$instance) {
        $instanceid = $manualplugin->add_instance(
            $course,
            [
                'status' => ENROL_INSTANCE_ENABLED,
                'roleid' => $studentrole->id,
            ]
        );

        $instance = $DB->get_record(
            'enrol',
            ['id' => $instanceid],
            '*',
            MUST_EXIST
        );
    }

    $manualplugin->enrol_user(
        $instance,
        $user->id,
        $studentrole->id
    );

    echo "ENROLLED: {$code}" . PHP_EOL;
}

echo PHP_EOL;
echo "Student ready" . PHP_EOL;
echo "Name: John Doe" . PHP_EOL;
echo "Username: johndoe" . PHP_EOL;
echo "Email: johndoe@example.com" . PHP_EOL;
echo "Password: TestStudent@123" . PHP_EOL;
echo "Courses: MHF4U, SPH4U" . PHP_EOL;
