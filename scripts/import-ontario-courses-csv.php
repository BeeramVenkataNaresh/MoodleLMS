<?php

define('CLI_SCRIPT', true);

require '/var/www/moodle/config.php';
require_once $CFG->dirroot . '/course/lib.php';

use core_course\customfield\course_handler;

global $DB;

/*
|--------------------------------------------------------------------------
| CLI arguments
|--------------------------------------------------------------------------
*/

$options = getopt('', ['file:']);

if (empty($options['file'])) {
    fwrite(
        STDERR,
        "Usage: php import-ontario-courses-csv.php "
        . "--file=/path/courses.csv\n"
    );

    exit(1);
}

$file = (string)$options['file'];

if (!is_readable($file)) {
    fwrite(STDERR, "CSV file not found: {$file}\n");
    exit(1);
}

/*
|--------------------------------------------------------------------------
| Helpers
|--------------------------------------------------------------------------
*/

function nexus_normalize(string $value): string {
    $value = trim($value);
    $value = preg_replace('/\s+/', ' ', $value);

    return $value ?? '';
}

function nexus_escape(string $value): string {
    return s(nexus_normalize($value));
}

function nexus_get_or_create_category(
    string $name,
    string $idnumber,
    int $parent = 0,
    int $sortorder = 999
): core_course_category {
    global $DB;

    $existingid = $DB->get_field(
        'course_categories',
        'id',
        ['idnumber' => $idnumber]
    );

    if ($existingid) {
        return core_course_category::get((int)$existingid);
    }

    return core_course_category::create([
        'name' => $name,
        'idnumber' => $idnumber,
        'parent' => $parent,
        'visible' => 1,
        'sortorder' => $sortorder,
        'description' => '',
        'descriptionformat' => FORMAT_HTML,
    ]);
}

/**
 * Read select options from a custom-field configuration.
 */
function nexus_customfield_options(stdClass $field): array {
    $config = json_decode((string)$field->configdata, true);

    if (!is_array($config)) {
        return [];
    }

    $options = $config['options'] ?? [];

    if (is_string($options)) {
        $options = preg_split(
            '/\R/',
            $options,
            -1,
            PREG_SPLIT_NO_EMPTY
        );
    }

    return array_values(
        array_filter(
            array_map(
                static fn($option): string =>
                    nexus_normalize((string)$option),
                (array)$options
            ),
            static fn(string $option): bool =>
                $option !== ''
        )
    );
}

/**
 * Moodle select fields normally store a one-based option index.
 */
function nexus_get_select_value(
    string $shortname,
    string $label
): ?int {
    global $DB;

    $field = $DB->get_record(
        'customfield_field',
        [
            'shortname' => $shortname,
            'type' => 'select',
        ]
    );

    if (!$field) {
        return null;
    }

    $wanted = mb_strtolower(nexus_normalize($label));

    foreach (nexus_customfield_options($field) as $index => $option) {
        if (mb_strtolower($option) === $wanted) {
            return $index + 1;
        }
    }

    return null;
}

function nexus_customfield_exists(string $shortname): bool {
    global $DB;

    return $DB->record_exists(
        'customfield_field',
        ['shortname' => $shortname]
    );
}

function nexus_build_summary(array $row): string {
    $code = nexus_escape($row['code']);
    $title = nexus_escape($row['title']);
    $department = nexus_escape($row['department']);
    $grade = (int)$row['grade'];
    $type = nexus_escape($row['type']);
    $credit = number_format((float)$row['credit'], 1);
    $prerequisite = nexus_escape($row['prerequisite']);
    $description = nexus_escape($row['description']);
    $year = nexus_escape($row['curriculum_year']);
    $sourceurl = clean_param(
        nexus_normalize($row['ministry_source_url']),
        PARAM_URL
    );

    return <<<HTML
<div class="nexus-course-profile">
    <h3>Ontario Ministry Course Information</h3>

    <p><strong>Course:</strong> {$title} | {$code}</p>
    <p><strong>Department:</strong> {$department}</p>
    <p><strong>Grade:</strong> {$grade}</p>
    <p><strong>Course type:</strong> {$type}</p>
    <p><strong>Credit value:</strong> {$credit}</p>
    <p><strong>Prerequisite:</strong> {$prerequisite}</p>
    <p><strong>Curriculum year:</strong> {$year}</p>

    <h4>Course description</h4>
    <p>{$description}</p>

    <p>
        <strong>Curriculum source:</strong>
        <a href="{$sourceurl}" target="_blank" rel="noopener noreferrer">
            Ontario Ministry of Education
        </a>
    </p>

    <p>
        <strong>Nexus offering status:</strong>
        Pending Review
    </p>
</div>
HTML;
}

function nexus_update_sections(int $courseid): void {
    global $DB;

    $sectionnames = [
        0 => 'Course Information and Announcements',
        1 => 'Strand A',
        2 => 'Strand B',
        3 => 'Strand C',
        4 => 'Strand D',
        5 => 'Culminating Activity',
        6 => 'Final Evaluation',
    ];

    $sections = $DB->get_records(
        'course_sections',
        ['course' => $courseid],
        'section ASC'
    );

    foreach ($sections as $section) {
        $sectionnumber = (int)$section->section;

        if (!array_key_exists($sectionnumber, $sectionnames)) {
            continue;
        }

        $DB->set_field(
            'course_sections',
            'name',
            $sectionnames[$sectionnumber],
            ['id' => $section->id]
        );
    }
}

/**
 * Save all available Nexus course custom fields.
 */
function nexus_save_custom_fields(
    int $courseid,
    array $row
): void {
    $handler = course_handler::create();

    $department = nexus_normalize($row['department']);
    $grade = 'Grade ' . (int)$row['grade'];
    $type = nexus_normalize($row['type']);
    $credit = number_format((float)$row['credit'], 1);
    $prerequisite = nexus_normalize($row['prerequisite']);
    $year = nexus_normalize($row['curriculum_year']);
    $sourceurl = nexus_normalize($row['ministry_source_url']);
    $status = 'Pending Review';

    $formdata = (object)[
        'id' => $courseid,
    ];

    if (nexus_customfield_exists('department')) {
        $value = nexus_get_select_value(
            'department',
            $department
        );

        if ($value === null) {
            throw new coding_exception(
                "Department option not found: {$department}"
            );
        }

        $formdata->customfield_department = $value;
    }

    if (nexus_customfield_exists('grade')) {
        $value = nexus_get_select_value(
            'grade',
            $grade
        );

        if ($value === null) {
            throw new coding_exception(
                "Grade option not found: {$grade}"
            );
        }

        $formdata->customfield_grade = $value;
    }

    if (nexus_customfield_exists('department_grade')) {
        $combined = "{$department} — {$grade}";

        $value = nexus_get_select_value(
            'department_grade',
            $combined
        );

        if ($value === null) {
            throw new coding_exception(
                "Department and Grade option not found: {$combined}"
            );
        }

        $formdata->customfield_department_grade = $value;
    }

    if (nexus_customfield_exists('course_type')) {
        $value = nexus_get_select_value(
            'course_type',
            $type
        );

        if ($value === null) {
            throw new coding_exception(
                "Course Type option not found: {$type}"
            );
        }

        $formdata->customfield_course_type = $value;
    }

    if (nexus_customfield_exists('credit_value')) {
        $formdata->customfield_credit_value = $credit;
    }

    if (nexus_customfield_exists('prerequisite')) {
        $formdata->customfield_prerequisite = $prerequisite;
    }

    if (nexus_customfield_exists('curriculum_year')) {
        $formdata->customfield_curriculum_year = $year;
    }

    if (nexus_customfield_exists('ministry_source_url')) {
        $formdata->customfield_ministry_source_url = $sourceurl;
    }

    if (nexus_customfield_exists('offering_status')) {
        $value = nexus_get_select_value(
            'offering_status',
            $status
        );

        if ($value === null) {
            throw new coding_exception(
                "Offering Status option not found: {$status}"
            );
        }

        $formdata->customfield_offering_status = $value;
    }

    $handler->instance_form_save($formdata, true);
}

function nexus_import_course(
    core_course_category $category,
    array $row
): string {
    global $DB;

    $code = strtoupper(nexus_normalize($row['code']));
    $title = nexus_normalize($row['title']);
    $grade = (int)$row['grade'];

    if ($code === '' || $title === '') {
        throw new coding_exception(
            'Course code or title is missing.'
        );
    }

    if (!in_array($grade, [9, 10, 11, 12], true)) {
        throw new coding_exception(
            "Invalid grade for {$code}: {$grade}"
        );
    }

    $existing = $DB->get_record(
        'course',
        ['shortname' => $code]
    );

    $coursedata = (object)[
        'category' => $category->id,
        'fullname' => "{$title} | {$code}",
        'shortname' => $code,
        'idnumber' => "NEXUS-{$code}",
        'summary' => nexus_build_summary($row),
        'summaryformat' => FORMAT_HTML,
        'format' => 'topics',
        'numsections' => 6,
        'enablecompletion' => 1,
        'visible' => 0,
        'showgrades' => 1,
        'newsitems' => 5,
        'lang' => 'en',
    ];

    if ($existing) {
        $coursedata->id = $existing->id;

        update_course($coursedata);

        $courseid = (int)$existing->id;
        $action = 'UPDATED';
    } else {
        try {
            $course = create_course($coursedata);
        } catch (Throwable $e) {
            echo PHP_EOL;
            echo "CREATE_COURSE FAILED" . PHP_EOL;
            echo "==============================" . PHP_EOL;
            echo "Course: " . ($coursedata->shortname ?? "UNKNOWN") . PHP_EOL;
            echo "Category: " . ($coursedata->category ?? "NULL") . PHP_EOL;
            echo "Fullname: " . ($coursedata->fullname ?? "NULL") . PHP_EOL;
            echo "Shortname: " . ($coursedata->shortname ?? "NULL") . PHP_EOL;
            echo "ID number: " . ($coursedata->idnumber ?? "NULL") . PHP_EOL;
            echo "Exception: " . get_class($e) . PHP_EOL;
            echo "Message: " . $e->getMessage() . PHP_EOL;

            if (isset($e->errorcode)) {
                echo "Error code: " . $e->errorcode . PHP_EOL;
            }

            if (isset($e->debuginfo)) {
                echo "DEBUG INFO:" . PHP_EOL;
                echo $e->debuginfo . PHP_EOL;
            }

            echo "==============================" . PHP_EOL;

            throw $e;
        }

        $courseid = (int)$course->id;
        $action = 'CREATED';
    }

    nexus_update_sections($courseid);
    nexus_save_custom_fields($courseid, $row);

    rebuild_course_cache($courseid, true);

    return "{$action}: {$code}";
}

/*
|--------------------------------------------------------------------------
| Validate CSV
|--------------------------------------------------------------------------
*/

$handle = fopen($file, 'r');

if (!$handle) {
    throw new coding_exception(
        "Unable to open CSV: {$file}"
    );
}

$headers = fgetcsv($handle);

if (!$headers) {
    throw new coding_exception(
        'CSV header is missing.'
    );
}

$headers = array_map(
    static fn($header): string =>
        nexus_normalize((string)$header),
    $headers
);

$requiredcolumns = [
    'department',
    'department_id',
    'code',
    'title',
    'grade',
    'type',
    'credit',
    'prerequisite',
    'description',
    'curriculum_year',
    'ministry_source_url',
];

foreach ($requiredcolumns as $column) {
    if (!in_array($column, $headers, true)) {
        throw new coding_exception(
            "Missing CSV column: {$column}"
        );
    }
}

/*
|--------------------------------------------------------------------------
| Import
|--------------------------------------------------------------------------
*/

$root = nexus_get_or_create_category(
    'Ontario Secondary School Courses',
    'ONTARIO-SECONDARY',
    0,
    1
);

$created = 0;
$updated = 0;
$failed = 0;
$processed = 0;

echo PHP_EOL;
echo "Nexus EPS — Ontario Course Import" . PHP_EOL;
echo "==================================" . PHP_EOL;

while (($values = fgetcsv($handle)) !== false) {
    if (
        count($values) === 1 &&
        nexus_normalize((string)$values[0]) === ''
    ) {
        continue;
    }

    if (count($values) !== count($headers)) {
        echo "FAILED: Invalid column count on CSV row "
            . ($processed + 2)
            . PHP_EOL;

        $failed++;
        $processed++;
        continue;
    }

    $row = array_combine($headers, $values);

    if (!is_array($row)) {
        $failed++;
        $processed++;
        continue;
    }

    $processed++;

    try {
        $departmentname = nexus_normalize(
            $row['department']
        );

        $departmentid = nexus_normalize(
            $row['department_id']
        );

        $grade = (int)$row['grade'];

        $department = nexus_get_or_create_category(
            $departmentname,
            $departmentid,
            $root->id
        );

        $gradecategory = nexus_get_or_create_category(
            "Grade {$grade}",
            "{$departmentid}-GRADE-{$grade}",
            $department->id,
            $grade
        );

        $result = nexus_import_course(
            $gradecategory,
            $row
        );

        echo "{$result}"
            . " | {$departmentname}"
            . " | Grade {$grade}"
            . PHP_EOL;

        if (str_starts_with($result, 'CREATED:')) {
            $created++;
        } else {
            $updated++;
        }
    } catch (Throwable $exception) {
        $code = strtoupper(
            nexus_normalize($row['code'] ?? 'UNKNOWN')
        );

        echo PHP_EOL;
        echo "================ IMPORT FAILURE ================" . PHP_EOL;
        echo "COURSE: {$code}" . PHP_EOL;
        echo "CLASS: " . get_class($exception) . PHP_EOL;
        echo "MESSAGE: " . $exception->getMessage() . PHP_EOL;

        if (property_exists($exception, 'errorcode')) {
            echo "ERROR CODE: "
                . $exception->errorcode
                . PHP_EOL;
        }

        if (property_exists($exception, 'debuginfo')) {
            echo "DEBUG INFO:" . PHP_EOL;
            echo $exception->debuginfo . PHP_EOL;
        }

        echo "================================================" . PHP_EOL;
        echo PHP_EOL;

        $failed++;
    }
}

fclose($handle);

echo PHP_EOL;
echo "{$processed} CSV rows processed." . PHP_EOL;
echo "{$created} courses created." . PHP_EOL;
echo "{$updated} courses updated." . PHP_EOL;
echo "{$failed} courses failed." . PHP_EOL;
echo "Courses remain hidden pending Nexus review." . PHP_EOL;

if ($failed > 0) {
    exit(1);
}
