<?php

defined('MOODLE_INTERNAL') || die();

$observers = [
    [
        'eventname' => '\core\event\course_created',
        'callback' =>
            '\local_nexusadminenrol\observer::course_created',
    ],
];
