<?php

define('CLI_SCRIPT', true);

require '/var/www/moodle/config.php';
require_once $CFG->dirroot . '/course/lib.php';

use core_course\customfield\course_handler;

global $DB;

echo PHP_EOL;
echo "Nexus EPS — Repair Course Categories and Metadata" . PHP_EOL;
echo "==================================================" . PHP_EOL;

/**
 * Convert the course-code grade digit to the Ontario grade number.
 */
function nexus_grade_from_code(string $code): ?int {
    $code = strtoupper(trim($code));

    if (!preg_match('/^[A-Z]{3}([1-4])[A-Z]$/', $code, $matches)) {
        return null;
    }

    return match ($matches[1]) {
        '1' => 9,
        '2' => 10,
        '3' => 11,
        '4' => 12,
        default => null,
    };
}

/**
 * Find the Ontario department from the course's category ancestry.
 */
function nexus_department_from_course(stdClass $course): ?stdClass {
    global $DB;

    $category = $DB->get_record(
        'course_categories',
        ['id' => $course->category],
        '*',
        MUST_EXIST
    );

    $pathids = array_values(array_filter(
        array_map(
            'intval',
            explode('/', trim($category->path, '/'))
        )
    ));

    if (!$pathids) {
        return null;
    }

    $records = $DB->get_records_list(
        'course_categories',
        'id',
        $pathids
    );

    foreach (array_reverse($pathids) as $categoryid) {
        if (!isset($records[$categoryid])) {
            continue;
        }

        $candidate = $records[$categoryid];
        $idnumber = trim((string)$candidate->idnumber);

        if (
            str_starts_with($idnumber, 'ONTARIO-') &&
            $idnumber !== 'ONTARIO-SECONDARY' &&
            !str_contains($idnumber, '-GRADE-')
        ) {
            return $candidate;
        }
    }

    return null;
}

/**
 * Retrieve the correct grade category under a department.
 */
function nexus_grade_category(
    stdClass $department,
    int $grade
): ?stdClass {
    global $DB;

    $idnumber = trim((string)$department->idnumber)
        . '-GRADE-' . $grade;

    return $DB->get_record(
        'course_categories',
        ['idnumber' => $idnumber]
    ) ?: null;
}

/**
 * Find the saved numeric value for one select custom-field option.
 *
 * Moodle select options are saved as one-based values:
 * first option = 1, second option = 2, etc.
 */
function nexus_select_option_value(
    string $shortname,
    string $wanted
): ?int {
    global $DB;

    $field = $DB->get_record(
        'customfield_field',
        [
            'shortname' => $shortname,
            'type' => 'select',
        ],
        '*',
        MUST_EXIST
    );

    $config = json_decode($field->configdata, true);
    $options = $config['options'] ?? [];

    if (is_string($options)) {
        $options = preg_split(
            '/\R/',
            $options,
            -1,
            PREG_SPLIT_NO_EMPTY
        );
    }

    $options = array_values($options);

    foreach ($options as $index => $option) {
        if (trim((string)$option) === trim($wanted)) {
            return $index + 1;
        }
    }

    return null;
}

$handler = course_handler::create();

$courses = $DB->get_records_select(
    'course',
    'id <> :siteid',
    ['siteid' => SITEID],
    'shortname ASC'
);

$updated = 0;
$skipped = 0;
$errors = 0;

foreach ($courses as $course) {
    $code = strtoupper(trim($course->shortname));
    $grade = nexus_grade_from_code($code);
    $department = nexus_department_from_course($course);

    if (!$grade || !$department) {
        echo "SKIPPED: {$code}";

        if (!$department) {
            echo " [department not detected]";
        }

        if (!$grade) {
            echo " [grade not detected]";
        }

        echo PHP_EOL;
        $skipped++;
        continue;
    }

    $gradecategory = nexus_grade_category($department, $grade);

    if (!$gradecategory) {
        echo "ERROR: {$code} — Grade {$grade} category missing under "
            . $department->name . PHP_EOL;

        $errors++;
        continue;
    }

    $departmentlabel = trim($department->name);
    $gradelabel = "Grade {$grade}";
    $combinedlabel = "{$departmentlabel} — {$gradelabel}";

    $departmentvalue = nexus_select_option_value(
        'department',
        $departmentlabel
    );

    $gradevalue = nexus_select_option_value(
        'grade',
        $gradelabel
    );

    $combinedvalue = nexus_select_option_value(
        'department_grade',
        $combinedlabel
    );

    if (
        $departmentvalue === null ||
        $gradevalue === null ||
        $combinedvalue === null
    ) {
        echo "ERROR OPTIONS: {$code}";

        if ($departmentvalue === null) {
            echo " [department option missing]";
        }

        if ($gradevalue === null) {
            echo " [grade option missing]";
        }

        if ($combinedvalue === null) {
            echo " [combined option missing]";
        }

        echo PHP_EOL;
        $errors++;
        continue;
    }

    try {
        /*
         * Move the course into Department → Grade.
         */
        if ((int)$course->category !== (int)$gradecategory->id) {
            $updatedcourse = (object)[
                'id' => $course->id,
                'category' => $gradecategory->id,
            ];

            update_course($updatedcourse);
        }

        /*
         * Save all three custom-field values.
         */
        $formdata = (object)[
            'id' => $course->id,
            'customfield_department' => $departmentvalue,
            'customfield_grade' => $gradevalue,
            'customfield_department_grade' => $combinedvalue,
        ];

        $handler->instance_form_save($formdata, true);

        rebuild_course_cache($course->id, true);

        echo "UPDATED: {$code}"
            . " | {$departmentlabel}"
            . " | {$gradelabel}"
            . PHP_EOL;

        $updated++;
    } catch (Throwable $exception) {
        echo "FAILED: {$code} — "
            . $exception->getMessage()
            . PHP_EOL;

        $errors++;
    }
}

echo PHP_EOL;
echo "{$updated} courses repaired." . PHP_EOL;
echo "{$skipped} courses skipped for manual review." . PHP_EOL;
echo "{$errors} errors detected." . PHP_EOL;
echo PHP_EOL;
