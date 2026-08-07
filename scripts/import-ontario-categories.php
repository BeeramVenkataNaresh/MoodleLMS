<?php

define('CLI_SCRIPT', true);

require '/var/www/moodle/config.php';

global $DB;

echo PHP_EOL;
echo "Nexus EPS — Ontario Department Category Import" . PHP_EOL;
echo "===============================================" . PHP_EOL;

function nexus_category(
    string $name,
    string $idnumber,
    int $parent = 0,
    int $sortorder = 0
): core_course_category {
    global $DB;

    $existingid = $DB->get_field(
        'course_categories',
        'id',
        ['idnumber' => $idnumber]
    );

    if ($existingid) {
        $category = core_course_category::get((int)$existingid);

        echo "EXISTS: {$name}" . PHP_EOL;

        return $category;
    }

    $category = core_course_category::create([
        'name' => $name,
        'idnumber' => $idnumber,
        'parent' => $parent,
        'visible' => 1,
        'sortorder' => $sortorder,
        'description' =>
            '<p>Ontario Ministry-aligned courses offered by ' .
            'Nexus Education Private School.</p>',
        'descriptionformat' => FORMAT_HTML,
    ]);

    echo "CREATED: {$name}" . PHP_EOL;

    return $category;
}

$root = nexus_category(
    'Ontario Secondary School Courses',
    'ONTARIO-SECONDARY'
);

$departments = [
    ['English', 'ONTARIO-ENGLISH'],
    ['Mathematics', 'ONTARIO-MATHEMATICS'],
    ['Science', 'ONTARIO-SCIENCE'],
    ['The Arts', 'ONTARIO-ARTS'],
    ['Business Studies', 'ONTARIO-BUSINESS'],
    ['Canadian and World Studies', 'ONTARIO-CWS'],
    ['Computer Studies', 'ONTARIO-COMPUTER-STUDIES'],
    ['French as a Second Language', 'ONTARIO-FRENCH'],
    ['Guidance and Career Education', 'ONTARIO-GUIDANCE'],
    ['Health and Physical Education', 'ONTARIO-HPE'],
    ['Social Sciences and Humanities', 'ONTARIO-SSH'],
    ['Technological Education', 'ONTARIO-TECH'],
    ['First Nations, Métis and Inuit Studies', 'ONTARIO-FNMI'],
    ['Classical and International Languages', 'ONTARIO-LANGUAGES'],
    ['American Sign Language as a Second Language', 'ONTARIO-ASL'],
    ['Interdisciplinary Studies', 'ONTARIO-IDC'],
    ['Cooperative Education', 'ONTARIO-COOP'],
    ['English as a Second Language and English Literacy Development', 'ONTARIO-ESL-ELD'],
    ['Ontario Secondary School Literacy Course', 'ONTARIO-OSSLT'],
    ['Locally Developed Courses', 'ONTARIO-LOCALLY-DEVELOPED'],
];

$position = 1;

foreach ($departments as [$name, $idnumber]) {
    nexus_category(
        $name,
        $idnumber,
        $root->id,
        $position++
    );
}

echo PHP_EOL;
echo count($departments) . " department categories processed." . PHP_EOL;
echo PHP_EOL;
