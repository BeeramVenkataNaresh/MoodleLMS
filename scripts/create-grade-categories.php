<?php

define('CLI_SCRIPT', true);

require '/var/www/moodle/config.php';

global $DB;

echo PHP_EOL;
echo "Nexus EPS — Create Grade Categories" . PHP_EOL;
echo "===================================" . PHP_EOL;

$root = $DB->get_record(
    'course_categories',
    ['idnumber' => 'ONTARIO-SECONDARY'],
    '*',
    MUST_EXIST
);

// Get only direct department categories under the Ontario root.
$departments = $DB->get_records(
    'course_categories',
    ['parent' => $root->id],
    'sortorder ASC, name ASC'
);

if (!$departments) {
    echo "No Ontario department categories were found." . PHP_EOL;
    exit(1);
}

foreach ($departments as $department) {
    echo PHP_EOL . $department->name . PHP_EOL;

    $departmentidnumber = trim((string)$department->idnumber);

    if ($departmentidnumber === '') {
        $departmentidnumber =
            'ONTARIO-' .
            strtoupper(
                preg_replace(
                    '/[^A-Za-z0-9]+/',
                    '-',
                    $department->name
                )
            );

        $departmentidnumber = trim($departmentidnumber, '-');

        $DB->set_field(
            'course_categories',
            'idnumber',
            $departmentidnumber,
            ['id' => $department->id]
        );

        echo "  ASSIGNED ID: {$departmentidnumber}" . PHP_EOL;
    }

    foreach ([9, 10, 11, 12] as $grade) {
        $gradeidnumber =
            $departmentidnumber . '-GRADE-' . $grade;

        $existing = $DB->get_record(
            'course_categories',
            ['idnumber' => $gradeidnumber]
        );

        if ($existing) {
            // Ensure an existing grade category belongs to this department.
            if ((int)$existing->parent !== (int)$department->id) {
                $DB->set_field(
                    'course_categories',
                    'parent',
                    $department->id,
                    ['id' => $existing->id]
                );

                echo "  MOVED: Grade {$grade}" . PHP_EOL;
            } else {
                echo "  EXISTS: Grade {$grade}" . PHP_EOL;
            }

            continue;
        }

        core_course_category::create([
            'name' => "Grade {$grade}",
            'idnumber' => $gradeidnumber,
            'parent' => $department->id,
            'visible' => 1,
            'description' =>
                "<p>Grade {$grade} Ontario Ministry-aligned courses " .
                "for {$department->name}.</p>",
            'descriptionformat' => FORMAT_HTML,
        ]);

        echo "  CREATED: Grade {$grade}" . PHP_EOL;
    }
}

echo PHP_EOL;
echo count($departments) .
    " departments processed successfully." . PHP_EOL;
echo PHP_EOL;
