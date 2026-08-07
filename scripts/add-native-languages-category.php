<?php

define('CLI_SCRIPT', true);

require '/var/www/moodle/config.php';

global $DB;

$rootid = $DB->get_field(
    'course_categories',
    'id',
    ['idnumber' => 'ONTARIO-SECONDARY'],
    MUST_EXIST
);

$idnumber = 'ONTARIO-NATIVE-LANGUAGES';

$existingid = $DB->get_field(
    'course_categories',
    'id',
    ['idnumber' => $idnumber]
);

if ($existingid) {
    $department = core_course_category::get((int)$existingid);
    echo "EXISTS: Native Languages\n";
} else {
    $department = core_course_category::create([
        'name' => 'Native Languages',
        'idnumber' => $idnumber,
        'parent' => $rootid,
        'visible' => 1,
        'description' =>
            '<p>Ontario Ministry Native Languages courses.</p>',
        'descriptionformat' => FORMAT_HTML,
    ]);

    echo "CREATED: Native Languages\n";
}

foreach ([9, 10, 11, 12] as $grade) {
    $gradeidnumber = $idnumber . '-GRADE-' . $grade;

    if ($DB->record_exists(
        'course_categories',
        ['idnumber' => $gradeidnumber]
    )) {
        echo "EXISTS: Grade {$grade}\n";
        continue;
    }

    core_course_category::create([
        'name' => "Grade {$grade}",
        'idnumber' => $gradeidnumber,
        'parent' => $department->id,
        'visible' => 1,
        'descriptionformat' => FORMAT_HTML,
    ]);

    echo "CREATED: Grade {$grade}\n";
}
