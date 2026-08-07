<?php

define('CLI_SCRIPT', true);

require '/var/www/moodle/config.php';
require_once $CFG->dirroot . '/course/lib.php';

global $DB;

echo PHP_EOL;
echo "Nexus EPS — Ontario English course import" . PHP_EOL;
echo "===========================================" . PHP_EOL;

/**
 * Find or create a Moodle course category.
 */
function nexus_get_or_create_category(
    string $name,
    string $idnumber,
    int $parent = 0
): core_course_category {
    global $DB;

    $existingid = $DB->get_field(
        'course_categories',
        'id',
        ['idnumber' => $idnumber]
    );

    if ($existingid) {
        return core_course_category::get((int) $existingid);
    }

    return core_course_category::create([
        'name' => $name,
        'idnumber' => $idnumber,
        'parent' => $parent,
        'visible' => 1,
        'descriptionformat' => FORMAT_HTML,
    ]);
}

/**
 * Create or update a Moodle course shell.
 */
function nexus_import_course(
    core_course_category $category,
    array $definition
): void {
    global $DB;

    $existing = $DB->get_record(
        'course',
        ['shortname' => $definition['code']]
    );

    $summary = '
        <div class="nexus-course-profile">
            <h3>Ontario Ministry Course Information</h3>

            <p><strong>Course code:</strong> ' .
                s($definition['code']) . '</p>

            <p><strong>Grade:</strong> ' .
                s((string) $definition['grade']) . '</p>

            <p><strong>Course type:</strong> ' .
                s($definition['type']) . '</p>

            <p><strong>Credit value:</strong> 1.0</p>

            <p><strong>Prerequisite:</strong> ' .
                s($definition['prerequisite']) . '</p>

            <h4>Course description</h4>

            <p>' . s($definition['description']) . '</p>

            <p>
                <strong>Curriculum source:</strong>
                Ontario Ministry of Education
            </p>
        </div>
    ';

    $courseData = [
        'category' => $category->id,
        'fullname' => $definition['title'] . ' | ' . $definition['code'],
        'shortname' => $definition['code'],
        'idnumber' => 'NEXUS-' . $definition['code'],
        'summary' => $summary,
        'summaryformat' => FORMAT_HTML,
        'format' => 'topics',
        'numsections' => count($definition['sections']) - 1,
        'enablecompletion' => 1,

        // Keep hidden until Nexus approves the final outline.
        'visible' => 0,

        'showgrades' => 1,
        'newsitems' => 5,
        'lang' => 'en',
    ];

    if ($existing) {
        $courseData['id'] = $existing->id;
        update_course((object) $courseData);
        $course = get_course($existing->id);

        echo "UPDATED: {$definition['code']}" . PHP_EOL;
    } else {
        $course = create_course((object) $courseData);

        echo "CREATED: {$definition['code']}" . PHP_EOL;
    }

    // Moodle creates section 0 plus the requested topic sections.
    $sections = $DB->get_records(
        'course_sections',
        ['course' => $course->id],
        'section ASC'
    );

    foreach ($sections as $section) {
        $sectionNumber = (int) $section->section;

        if (!array_key_exists($sectionNumber, $definition['sections'])) {
            continue;
        }

        $DB->set_field(
            'course_sections',
            'name',
            $definition['sections'][$sectionNumber],
            ['id' => $section->id]
        );
    }

    rebuild_course_cache($course->id, true);
}

$parentCategory = nexus_get_or_create_category(
    'Ontario Secondary School Courses',
    'ONTARIO-SECONDARY'
);

$englishCategory = nexus_get_or_create_category(
    'English',
    'ONTARIO-ENGLISH',
    $parentCategory->id
);

$traditionalEnglishSections = [
    0 => 'Course Information and Announcements',
    1 => 'Oral Communication',
    2 => 'Reading and Literature Studies',
    3 => 'Writing',
    4 => 'Media Studies',
    5 => 'Final Evaluation',
];

$gradeNineSections = [
    0 => 'Course Information and Announcements',
    1 => 'Applications, Connections, and Contributions',
    2 => 'Foundations of Language',
    3 => 'Comprehension: Understanding and Responding to Texts',
    4 => 'Composition: Expressing Ideas and Creating Texts',
    5 => 'Final Evaluation',
];

$courses = [
    [
        'code' => 'ENL1W',
        'title' => 'English',
        'grade' => 9,
        'type' => 'De-streamed',
        'prerequisite' => 'None',
        'description' =>
            'Students strengthen foundational reading, writing, oral communication, ' .
            'digital-media literacy, critical-thinking, and text-creation skills. The ' .
            'course supports students in understanding diverse texts, communicating ' .
            'effectively, and applying language skills across academic and everyday contexts.',
        'sections' => $gradeNineSections,
    ],

    [
        'code' => 'ENG2D',
        'title' => 'English',
        'grade' => 10,
        'type' => 'Academic',
        'prerequisite' => 'Grade 9 English, De-streamed',
        'description' =>
            'Students develop oral communication, reading, writing, and media-literacy ' .
            'skills through literary, informational, and graphic texts. The course places ' .
            'emphasis on analytical reading, clear communication, and preparation for ' .
            'university or college preparation English courses.',
        'sections' => $traditionalEnglishSections,
    ],

    [
        'code' => 'ENG2P',
        'title' => 'English',
        'grade' => 10,
        'type' => 'Applied',
        'prerequisite' => 'Grade 9 English, De-streamed',
        'description' =>
            'Students strengthen practical literacy and communication skills by studying ' .
            'literary, informational, and graphic texts and creating oral, written, and ' .
            'media works. The course prepares students for Grade 11 college or workplace ' .
            'preparation English.',
        'sections' => $traditionalEnglishSections,
    ],

    [
        'code' => 'ENG3U',
        'title' => 'English',
        'grade' => 11,
        'type' => 'University Preparation',
        'prerequisite' => 'ENG2D',
        'description' =>
            'Students develop advanced literacy, communication, and critical and creative ' .
            'thinking skills. They analyse challenging literary, informational, and graphic ' .
            'texts and create polished oral, written, and media texts for academic purposes.',
        'sections' => $traditionalEnglishSections,
    ],

    [
        'code' => 'ENG3C',
        'title' => 'English',
        'grade' => 11,
        'type' => 'College Preparation',
        'prerequisite' => 'ENG2P',
        'description' =>
            'Students examine the content, form, and style of literary, informational, and ' .
            'graphic texts and create communication products for practical and academic ' .
            'purposes. The course prepares students for Grade 12 college preparation English.',
        'sections' => $traditionalEnglishSections,
    ],

    [
        'code' => 'ENG3E',
        'title' => 'English',
        'grade' => 11,
        'type' => 'Workplace Preparation',
        'prerequisite' => 'ENG2P',
        'description' =>
            'Students build literacy, communication, and critical-thinking skills needed ' .
            'for the workplace and daily life. Learning focuses on contemporary texts and ' .
            'clear oral, written, visual, and media communication for practical purposes.',
        'sections' => $traditionalEnglishSections,
    ],

    [
        'code' => 'ENG4U',
        'title' => 'English',
        'grade' => 12,
        'type' => 'University Preparation',
        'prerequisite' => 'ENG3U',
        'description' =>
            'Students consolidate advanced literacy, communication, and critical and ' .
            'creative-thinking skills. They analyse challenging texts, evaluate informational ' .
            'and graphic works, and create sophisticated oral, written, and media texts in ' .
            'preparation for postsecondary study.',
        'sections' => $traditionalEnglishSections,
    ],

    [
        'code' => 'ENG4C',
        'title' => 'English',
        'grade' => 12,
        'type' => 'College Preparation',
        'prerequisite' => 'ENG3C',
        'description' =>
            'Students consolidate literacy and communication skills through literary, ' .
            'informational, and graphic texts. They produce clear oral, written, and media ' .
            'works for practical and academic purposes in preparation for college or work.',
        'sections' => $traditionalEnglishSections,
    ],

    [
        'code' => 'ENG4E',
        'title' => 'English',
        'grade' => 12,
        'type' => 'Workplace Preparation',
        'prerequisite' => 'ENG3E',
        'description' =>
            'Students consolidate practical literacy, communication, and critical-thinking ' .
            'skills needed for employment, active citizenship, and daily life. The course ' .
            'emphasizes clear, accurate, and organized workplace communication.',
        'sections' => $traditionalEnglishSections,
    ],
];

foreach ($courses as $courseDefinition) {
    nexus_import_course($englishCategory, $courseDefinition);
}

echo PHP_EOL;
echo count($courses) . " English courses processed successfully." . PHP_EOL;
echo "Courses remain hidden pending Nexus review." . PHP_EOL;
echo PHP_EOL;
