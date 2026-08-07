<?php

define('CLI_SCRIPT', true);

/*
 * This script is designed to run inside the Moodle Docker container.
 */
chdir('/var/www/moodle');
require 'config.php';

global $DB;

echo PHP_EOL;
echo "Nexus EPS — Department Course Banners" . PHP_EOL;
echo "======================================" . PHP_EOL;

if (!extension_loaded('gd')) {
    throw new RuntimeException(
        'PHP GD is not installed in the Moodle container.'
    );
}

/*
|--------------------------------------------------------------------------
| Department visual system
|--------------------------------------------------------------------------
|
| Every course belonging to one department receives the EXACT same PNG.
|
| Format:
|   Department => [start colour, end colour, accent colour]
|
*/

$themes = [
    'English' => [
        '#166534',
        '#4ADE80',
        '#DCFCE7',
    ],

    'Mathematics' => [
        '#B91C1C',
        '#F87171',
        '#FEE2E2',
    ],

    'Science' => [
        '#075985',
        '#38BDF8',
        '#E0F2FE',
    ],

    'The Arts' => [
        '#6B21A8',
        '#C084FC',
        '#F3E8FF',
    ],

    'Business Studies' => [
        '#3730A3',
        '#818CF8',
        '#E0E7FF',
    ],

    'Canadian and World Studies' => [
        '#0F766E',
        '#2DD4BF',
        '#CCFBF1',
    ],

    'Computer Studies' => [
        '#155E75',
        '#22D3EE',
        '#CFFAFE',
    ],

    'French as a Second Language' => [
        '#BE123C',
        '#FB7185',
        '#FFE4E6',
    ],

    'Guidance and Career Education' => [
        '#A16207',
        '#FACC15',
        '#FEF9C3',
    ],

    'Health and Physical Education' => [
        '#047857',
        '#34D399',
        '#D1FAE5',
    ],

    'Social Sciences and Humanities' => [
        '#9A3412',
        '#FB923C',
        '#FFEDD5',
    ],

    'Technological Education' => [
        '#C2410C',
        '#F97316',
        '#FFEDD5',
    ],

    'First Nations, Métis and Inuit Studies' => [
        '#78350F',
        '#D97706',
        '#FEF3C7',
    ],

    'Classical and International Languages' => [
        '#5B21B6',
        '#8B5CF6',
        '#EDE9FE',
    ],

    'American Sign Language as a Second Language' => [
        '#334155',
        '#64748B',
        '#E2E8F0',
    ],

    'Interdisciplinary Studies' => [
        '#1D4ED8',
        '#60A5FA',
        '#DBEAFE',
    ],

    'Cooperative Education' => [
        '#0F766E',
        '#5EEAD4',
        '#CCFBF1',
    ],

    'English as a Second Language and English Literacy Development' => [
        '#166534',
        '#86EFAC',
        '#DCFCE7',
    ],

    'Ontario Secondary School Literacy Course' => [
        '#3F6212',
        '#A3E635',
        '#ECFCCB',
    ],

    'Locally Developed Courses' => [
        '#475569',
        '#94A3B8',
        '#E2E8F0',
    ],

    'Native Languages' => [
        '#92400E',
        '#F59E0B',
        '#FEF3C7',
    ],
];


/*
|--------------------------------------------------------------------------
| Helpers
|--------------------------------------------------------------------------
*/

function nexus_hex_rgb(string $hex): array {
    $hex = ltrim($hex, '#');

    return [
        hexdec(substr($hex, 0, 2)),
        hexdec(substr($hex, 2, 2)),
        hexdec(substr($hex, 4, 2)),
    ];
}


function nexus_colour(
    GdImage $image,
    string $hex,
    int $alpha = 0
): int {
    [$r, $g, $b] = nexus_hex_rgb($hex);

    return imagecolorallocatealpha(
        $image,
        $r,
        $g,
        $b,
        $alpha
    );
}


/*
 * Creates one modern abstract banner.
 *
 * IMPORTANT:
 * This function runs only ONCE per department.
 * The exact same resulting PNG is copied to every course
 * inside that department.
 */
function nexus_create_banner(
    string $starthex,
    string $endhex,
    string $accenthex,
    string $path
): void {
    $width = 1200;
    $height = 400;

    $image = imagecreatetruecolor(
        $width,
        $height
    );

    imagealphablending($image, true);
    imagesavealpha($image, true);

    [$sr, $sg, $sb] = nexus_hex_rgb($starthex);
    [$er, $eg, $eb] = nexus_hex_rgb($endhex);

    /*
     * Smooth horizontal gradient.
     */
    for ($x = 0; $x < $width; $x++) {
        $ratio = $x / ($width - 1);

        $r = (int)round(
            $sr + (($er - $sr) * $ratio)
        );

        $g = (int)round(
            $sg + (($eg - $sg) * $ratio)
        );

        $b = (int)round(
            $sb + (($eb - $sb) * $ratio)
        );

        $linecolour = imagecolorallocate(
            $image,
            $r,
            $g,
            $b
        );

        imageline(
            $image,
            $x,
            0,
            $x,
            $height,
            $linecolour
        );
    }

    /*
     * Glass-style geometric decoration.
     *
     * Same coordinates every time so courses within
     * one department genuinely have identical banners.
     */
    $light = nexus_colour(
        $image,
        $accenthex,
        82
    );

    $lighter = nexus_colour(
        $image,
        '#FFFFFF',
        100
    );

    $dark = nexus_colour(
        $image,
        '#000000',
        116
    );

    imagefilledellipse(
        $image,
        120,
        60,
        310,
        310,
        $lighter
    );

    imagefilledellipse(
        $image,
        410,
        310,
        380,
        380,
        $light
    );

    imagefilledellipse(
        $image,
        760,
        70,
        330,
        330,
        $dark
    );

    imagefilledellipse(
        $image,
        1060,
        330,
        440,
        440,
        $lighter
    );

    /*
     * Additional subtle glass polygons.
     */
    imagefilledpolygon(
        $image,
        [
            480, 0,
            700, 0,
            585, 200,
        ],
        $light
    );

    imagefilledpolygon(
        $image,
        [
            810, 400,
            1040, 400,
            925, 200,
        ],
        $dark
    );

    if (!imagepng(
        $image,
        $path,
        7
    )) {
        imagedestroy($image);

        throw new RuntimeException(
            "Unable to generate PNG: {$path}"
        );
    }

    imagedestroy($image);
}


/*
 * Recursively collect category IDs.
 *
 * This means the script works even if later we introduce:
 *
 * Department
 *   → Grade
 *      → another subcategory
 *         → Course
 */
function nexus_category_tree_ids(
    int $categoryid
): array {
    global $DB;

    $ids = [$categoryid];

    $children = $DB->get_records(
        'course_categories',
        [
            'parent' => $categoryid,
        ],
        'id ASC',
        'id'
    );

    foreach ($children as $child) {
        $ids = array_merge(
            $ids,
            nexus_category_tree_ids(
                (int)$child->id
            )
        );
    }

    return array_values(
        array_unique($ids)
    );
}


/*
|--------------------------------------------------------------------------
| Ontario root
|--------------------------------------------------------------------------
*/

$root = $DB->get_record(
    'course_categories',
    [
        'idnumber' => 'ONTARIO-SECONDARY',
    ],
    '*',
    MUST_EXIST
);

$departments = $DB->get_records(
    'course_categories',
    [
        'parent' => $root->id,
    ],
    'name ASC'
);

$fileStorage = get_file_storage();

$tempdir = make_temp_directory(
    'nexus_department_banners'
);

$totalcourses = 0;
$totaldepartments = 0;
$skippeddepartments = 0;
$errors = 0;


/*
|--------------------------------------------------------------------------
| Process departments
|--------------------------------------------------------------------------
*/

foreach ($departments as $department) {

    $departmentname = trim(
        $department->name
    );

    echo PHP_EOL;
    echo $departmentname . PHP_EOL;
    echo str_repeat(
        '-',
        mb_strlen($departmentname)
    ) . PHP_EOL;

    if (!isset($themes[$departmentname])) {
        echo "SKIPPED: no visual theme configured."
            . PHP_EOL;

        $skippeddepartments++;
        continue;
    }

    [$start, $end, $accent] =
        $themes[$departmentname];

    /*
     * Generate ONE image for the department.
     */
    $filename =
        'nexus-department-'
        . clean_param(
            strtolower(
                preg_replace(
                    '/[^a-zA-Z0-9]+/',
                    '-',
                    $departmentname
                )
            ),
            PARAM_FILE
        )
        . '.png';

    $bannerpath =
        $tempdir
        . DIRECTORY_SEPARATOR
        . $filename;

    nexus_create_banner(
        $start,
        $end,
        $accent,
        $bannerpath
    );

    /*
     * Find every course anywhere below this department.
     */
    $categoryids =
        nexus_category_tree_ids(
            (int)$department->id
        );

    [$insql, $params] =
        $DB->get_in_or_equal(
            $categoryids,
            SQL_PARAMS_NAMED,
            'departmentcategory'
        );

    $params['siteid'] = SITEID;

    $courses = $DB->get_records_select(
        'course',
        "category {$insql}
         AND id <> :siteid",
        $params,
        'shortname ASC'
    );

    if (!$courses) {
        echo "No courses currently imported."
            . PHP_EOL;

        continue;
    }

    echo "Courses found: "
        . count($courses)
        . PHP_EOL;

    foreach ($courses as $course) {
        try {
            $context =
                context_course::instance(
                    $course->id
                );

            /*
             * Remove ALL previous overview files.
             *
             * This gets rid of:
             * - previous uploaded banners
             * - old department banner
             * - any test image
             */
            $fileStorage->delete_area_files(
                $context->id,
                'course',
                'overviewfiles',
                0
            );

            /*
             * Assign the department PNG.
             */
            $filerecord = [
                'contextid' =>
                    $context->id,

                'component' =>
                    'course',

                'filearea' =>
                    'overviewfiles',

                'itemid' =>
                    0,

                'filepath' =>
                    '/',

                'filename' =>
                    $filename,
            ];

            $fileStorage
                ->create_file_from_pathname(
                    $filerecord,
                    $bannerpath
                );

            rebuild_course_cache(
                $course->id,
                true
            );

            echo "UPDATED: "
                . $course->shortname
                . PHP_EOL;

            $totalcourses++;

        } catch (Throwable $e) {

            echo "FAILED: "
                . $course->shortname
                . " — "
                . $e->getMessage()
                . PHP_EOL;

            $errors++;
        }
    }

    $totaldepartments++;
}


/*
|--------------------------------------------------------------------------
| Cleanup
|--------------------------------------------------------------------------
*/

remove_dir($tempdir);

theme_reset_all_caches();

echo PHP_EOL;
echo "======================================" . PHP_EOL;
echo "Department banners complete" . PHP_EOL;
echo "======================================" . PHP_EOL;
echo "Departments processed: "
    . $totaldepartments
    . PHP_EOL;

echo "Courses updated: "
    . $totalcourses
    . PHP_EOL;

echo "Departments skipped: "
    . $skippeddepartments
    . PHP_EOL;

echo "Errors: "
    . $errors
    . PHP_EOL;

echo PHP_EOL;

if ($errors > 0) {
    exit(1);
}
