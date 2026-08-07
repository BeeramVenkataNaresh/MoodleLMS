<?php

define('CLI_SCRIPT', true);

require '/var/www/moodle/config.php';

use core_customfield\api;
use core_customfield\category_controller;
use core_customfield\field_controller;
use core_course\customfield\course_handler;

global $DB;

$categoryrecord = $DB->get_record(
    'customfield_category',
    [
        'name' => 'Ontario Course Metadata',
        'component' => 'core_course',
        'area' => 'course',
    ],
    '*',
    MUST_EXIST
);

$handler = course_handler::create();

$category = category_controller::create(
    (int)$categoryrecord->id,
    null,
    $handler
);

$existing = $DB->get_record(
    'customfield_field',
    [
        'categoryid' => $categoryrecord->id,
        'shortname' => 'department_grade',
    ]
);

$options = [];

$departments = [
    'American Sign Language as a Second Language',
    'The Arts',
    'Business Studies',
    'Canadian and World Studies',
    'Classical Studies and International Languages',
    'Computer Studies',
    'Cooperative Education',
    'English',
    'English as a Second Language and English Literacy Development',
    'First Nations, Métis and Inuit Studies',
    'French as a Second Language',
    'Guidance and Career Education',
    'Health and Physical Education',
    'Interdisciplinary Studies',
    'Mathematics',
    'Native Languages',
    'Science',
    'Social Sciences and Humanities',
    'Technological Education',
];

foreach ($departments as $department) {
    foreach ([9, 10, 11, 12] as $grade) {
        $options[] = "{$department} — Grade {$grade}";
    }
}

if ($existing) {
    $field = field_controller::create(
        (int)$existing->id,
        null,
        $category
    );

    $action = 'UPDATED';
} else {
    $field = field_controller::create(
        0,
        (object)[
            'categoryid' => $categoryrecord->id,
            'type' => 'select',
        ],
        $category
    );

    $action = 'CREATED';
}

api::save_field_configuration(
    $field,
    (object)[
        'name' => 'Department and Grade',
        'shortname' => 'department_grade',
        'type' => 'select',
        'description' =>
            'Combined department and grade value used for course filtering.',
        'descriptionformat' => FORMAT_HTML,
        'configdata' => [
            'options' => implode("\n", $options),
            'defaultvalue' => '',
            'visibility' => course_handler::VISIBLETOALL,
            'locked' => 0,
        ],
    ]
);

echo "{$action}: Department and Grade\n";
