<?php

define('CLI_SCRIPT', true);

require '/var/www/moodle/config.php';

use core_customfield\api;
use core_customfield\category_controller;
use core_customfield\field_controller;
use core_course\customfield\course_handler;

global $DB;

echo PHP_EOL;
echo "Nexus EPS — Ontario Course Metadata Setup" . PHP_EOL;
echo "==========================================" . PHP_EOL;

/*
|--------------------------------------------------------------------------
| Remove the accidentally created Moodle course category
|--------------------------------------------------------------------------
*/

$wrongcategories = $DB->get_records(
    'course_categories',
    ['name' => 'Ontario Course Metadata']
);

foreach ($wrongcategories as $wrongcategory) {
    $coursecount = $DB->count_records(
        'course',
        ['category' => $wrongcategory->id]
    );

    $childcount = $DB->count_records(
        'course_categories',
        ['parent' => $wrongcategory->id]
    );

    if ($coursecount === 0 && $childcount === 0) {
        $category = core_course_category::get($wrongcategory->id);
        $category->delete_full(false);

        echo "REMOVED incorrect course category: Ontario Course Metadata" .
            PHP_EOL;
    } else {
        echo "SKIPPED incorrect category removal because it contains " .
            "courses or child categories." . PHP_EOL;
    }
}

/*
|--------------------------------------------------------------------------
| Find or create the real course custom-field category
|--------------------------------------------------------------------------
*/

$handler = course_handler::create();

$existingcategory = $DB->get_record(
    'customfield_category',
    [
        'name' => 'Ontario Course Metadata',
        'component' => 'core_course',
        'area' => 'course',
        'itemid' => 0,
    ]
);

if ($existingcategory) {
    $metadata = category_controller::create(
        (int)$existingcategory->id,
        null,
        $handler
    );

    echo "EXISTS custom-field category: Ontario Course Metadata" .
        PHP_EOL;
} else {
    $metadata = category_controller::create(
        0,
        (object)[
            'name' => 'Ontario Course Metadata',
            'description' =>
                'Ontario Ministry course classification and Nexus offering information.',
            'descriptionformat' => FORMAT_HTML,
            'component' => 'core_course',
            'area' => 'course',
            'itemid' => 0,
        ],
        $handler
    );

    api::save_category($metadata);

    echo "CREATED custom-field category: Ontario Course Metadata" .
        PHP_EOL;
}

/*
|--------------------------------------------------------------------------
| Field helper
|--------------------------------------------------------------------------
*/

function nexus_create_or_update_field(
    category_controller $category,
    string $type,
    string $name,
    string $shortname,
    array $configdata
): void {
    global $DB;

    $existing = $DB->get_record(
        'customfield_field',
        [
            'categoryid' => $category->get('id'),
            'shortname' => $shortname,
        ]
    );

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
                'categoryid' => $category->get('id'),
                'type' => $type,
            ],
            $category
        );

        $action = 'CREATED';
    }

    $formdata = (object)[
        'name' => $name,
        'shortname' => $shortname,
        'type' => $type,
        'description' => '',
        'descriptionformat' => FORMAT_HTML,
        'configdata' => $configdata,
    ];

    api::save_field_configuration($field, $formdata);

    echo "{$action}: {$name} ({$shortname})" . PHP_EOL;
}

/*
|--------------------------------------------------------------------------
| Shared settings
|--------------------------------------------------------------------------
*/

$visibletoall = course_handler::VISIBLETOALL;
$visibletoteachers = course_handler::VISIBLETOTEACHERS;

$departmentoptions = implode("\n", [
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
]);

$gradeoptions = implode("\n", [
    'Grade 9',
    'Grade 10',
    'Grade 11',
    'Grade 12',
]);

$typeoptions = implode("\n", [
    'De-streamed',
    'Academic',
    'Applied',
    'Open',
    'University Preparation',
    'College Preparation',
    'University/College Preparation',
    'Workplace Preparation',
    'Locally Developed',
]);

$statusoptions = implode("\n", [
    'Pending Review',
    'Approved to Offer',
    'Currently Offered',
    'Archived',
]);

/*
|--------------------------------------------------------------------------
| Create the metadata fields
|--------------------------------------------------------------------------
*/

nexus_create_or_update_field(
    $metadata,
    'select',
    'Department',
    'department',
    [
        'options' => $departmentoptions,
        'defaultvalue' => '',
        'visibility' => $visibletoall,
        'locked' => 0,
    ]
);

nexus_create_or_update_field(
    $metadata,
    'select',
    'Grade',
    'grade',
    [
        'options' => $gradeoptions,
        'defaultvalue' => '',
        'visibility' => $visibletoall,
        'locked' => 0,
    ]
);

nexus_create_or_update_field(
    $metadata,
    'select',
    'Course Type',
    'course_type',
    [
        'options' => $typeoptions,
        'defaultvalue' => '',
        'visibility' => $visibletoall,
        'locked' => 0,
    ]
);

nexus_create_or_update_field(
    $metadata,
    'text',
    'Credit Value',
    'credit_value',
    [
        'defaultvalue' => '1.0',
        'displaysize' => 20,
        'maxlength' => 20,
        'ispassword' => 0,
        'link' => '',
        'linktarget' => '',
        'visibility' => $visibletoall,
        'locked' => 0,
    ]
);

nexus_create_or_update_field(
    $metadata,
    'text',
    'Prerequisite',
    'prerequisite',
    [
        'defaultvalue' => '',
        'displaysize' => 80,
        'maxlength' => 500,
        'ispassword' => 0,
        'link' => '',
        'linktarget' => '',
        'visibility' => $visibletoall,
        'locked' => 0,
    ]
);

nexus_create_or_update_field(
    $metadata,
    'text',
    'Curriculum Year',
    'curriculum_year',
    [
        'defaultvalue' => '',
        'displaysize' => 20,
        'maxlength' => 20,
        'ispassword' => 0,
        'link' => '',
        'linktarget' => '',
        'visibility' => $visibletoteachers,
        'locked' => 0,
    ]
);

nexus_create_or_update_field(
    $metadata,
    'text',
    'Ministry Source URL',
    'ministry_source_url',
    [
        'defaultvalue' => '',
        'displaysize' => 100,
        'maxlength' => 1000,
        'ispassword' => 0,
        'link' => '$$',
        'linktarget' => '_blank',
        'visibility' => $visibletoteachers,
        'locked' => 0,
    ]
);

nexus_create_or_update_field(
    $metadata,
    'select',
    'Nexus Offering Status',
    'offering_status',
    [
        'options' => $statusoptions,
        'defaultvalue' => 'Pending Review',
        'visibility' => $visibletoteachers,
        'locked' => 0,
    ]
);

echo PHP_EOL;
echo "Ontario course metadata setup completed." . PHP_EOL;
echo PHP_EOL;
