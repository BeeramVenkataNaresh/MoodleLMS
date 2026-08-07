<?php

define('CLI_SCRIPT', true);

require '/var/www/moodle/config.php';

use core_course\customfield\course_handler;

global $DB;

$handler = course_handler::create();

function nexus_get_select_value(
    string $shortname,
    string $wanted
): ?int {
    global $DB;

    $field = $DB->get_record(
        'customfield_field',
        ['shortname' => $shortname],
        '*',
        MUST_EXIST
    );

    $config = json_decode($field->configdata, true);

    $rawoptions = $config['options'] ?? [];

    if (is_string($rawoptions)) {
        $options = preg_split(
            '/\R/',
            $rawoptions,
            -1,
            PREG_SPLIT_NO_EMPTY
        );
    } else {
        $options = $rawoptions;
    }

    foreach (array_values($options) as $index => $option) {
        if (trim((string)$option) === trim($wanted)) {
            return $index;
        }
    }

    return null;
}

function nexus_detect_department_and_grade(
    stdClass $course
): array {
    global $DB;

    $category = $DB->get_record(
        'course_categories',
        ['id' => $course->category],
        '*',
        MUST_EXIST
    );

    $pathids = array_values(
        array_filter(
            array_map(
                'intval',
                explode('/', trim($category->path, '/'))
            )
        )
    );

    $categories = [];

    if ($pathids) {
        $records = $DB->get_records_list(
            'course_categories',
            'id',
            $pathids
        );

        foreach ($pathids as $pathid) {
            if (isset($records[$pathid])) {
                $categories[] = $records[$pathid];
            }
        }
    }

    $department = null;
    $grade = null;

    foreach ($categories as $item) {
        if (
            preg_match(
                '/^Grade\s+(9|10|11|12)$/i',
                trim($item->name),
                $matches
            )
        ) {
            $grade = 'Grade ' . $matches[1];
            continue;
        }

        if (
            str_starts_with(
                (string)$item->idnumber,
                'ONTARIO-'
            ) &&
            $item->idnumber !== 'ONTARIO-SECONDARY' &&
            !str_contains($item->idnumber, '-GRADE-')
        ) {
            $department = $item->name;
        }
    }

    // Fallback: obtain grade from the fourth character of the course code.
    if (
        !$grade &&
        preg_match(
            '/^[A-Z]{3}([1-4])[A-Z]$/',
            strtoupper($course->shortname),
            $matches
        )
    ) {
        $gradeMap = [
            '1' => 'Grade 9',
            '2' => 'Grade 10',
            '3' => 'Grade 11',
            '4' => 'Grade 12',
        ];

        $grade = $gradeMap[$matches[1]] ?? null;
    }

    return [$department, $grade];
}

$courses = $DB->get_records_select(
    'course',
    'id <> :siteid',
    ['siteid' => SITEID],
    'shortname ASC'
);

$updated = 0;
$skipped = 0;

foreach ($courses as $course) {
    [$department, $grade] =
        nexus_detect_department_and_grade($course);

    if (!$department || !$grade) {
        echo "SKIPPED: {$course->shortname}";

        if (!$department) {
            echo ' [department missing]';
        }

        if (!$grade) {
            echo ' [grade missing]';
        }

        echo PHP_EOL;

        $skipped++;
        continue;
    }

    $departmentvalue = nexus_get_select_value(
        'department',
        $department
    );

    $gradevalue = nexus_get_select_value(
        'grade',
        $grade
    );

    $combined = "{$department} — {$grade}";

    $combinedvalue = nexus_get_select_value(
        'department_grade',
        $combined
    );

    if (
        $departmentvalue === null ||
        $gradevalue === null ||
        $combinedvalue === null
    ) {
        echo "SKIPPED OPTIONS: {$course->shortname}" . PHP_EOL;
        $skipped++;
        continue;
    }

    $formdata = (object)[
        'id' => $course->id,
        'customfield_department' => $departmentvalue,
        'customfield_grade' => $gradevalue,
        'customfield_department_grade' => $combinedvalue,
    ];

    $handler->instance_form_save($formdata, true);

    echo "UPDATED: {$course->shortname}";
    echo " | {$department}";
    echo " | {$grade}" . PHP_EOL;

    $updated++;
}

echo PHP_EOL;
echo "{$updated} courses updated." . PHP_EOL;
echo "{$skipped} courses require manual review." . PHP_EOL;
