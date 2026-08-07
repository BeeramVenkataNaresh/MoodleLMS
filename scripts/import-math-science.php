<?php

define('CLI_SCRIPT', true);

require '/var/www/moodle/config.php';
require_once $CFG->dirroot . '/course/lib.php';

global $DB;

echo PHP_EOL;
echo "Nexus EPS — Ontario Mathematics and Science Import" . PHP_EOL;
echo "===================================================" . PHP_EOL;

/**
 * Create or retrieve a Moodle category.
 */
function nexus_category(
    string $name,
    string $idnumber,
    int $parent = 0
): core_course_category {
    global $DB;

    $id = $DB->get_field(
        'course_categories',
        'id',
        ['idnumber' => $idnumber]
    );

    if ($id) {
        return core_course_category::get((int) $id);
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
 * Create or update one course shell.
 */
function nexus_course(
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
                s($definition['code']) .
            '</p>

            <p><strong>Grade:</strong> ' .
                s((string) $definition['grade']) .
            '</p>

            <p><strong>Course type:</strong> ' .
                s($definition['type']) .
            '</p>

            <p><strong>Credit value:</strong> 1.0</p>

            <p><strong>Prerequisite:</strong> ' .
                s($definition['prerequisite']) .
            '</p>

            <h4>Course description</h4>

            <p>' . s($definition['description']) . '</p>

            <p>
                <strong>Curriculum source:</strong>
                Ontario Ministry of Education
            </p>
        </div>
    ';

    $data = [
        'category' => $category->id,
        'fullname' => $definition['title'] . ' | ' . $definition['code'],
        'shortname' => $definition['code'],
        'idnumber' => 'NEXUS-' . $definition['code'],
        'summary' => $summary,
        'summaryformat' => FORMAT_HTML,
        'format' => 'topics',
        'numsections' => count($definition['sections']) - 1,
        'enablecompletion' => 1,
        'visible' => 0,
        'showgrades' => 1,
        'newsitems' => 5,
        'lang' => 'en',
    ];

    if ($existing) {
        $data['id'] = $existing->id;
        update_course((object) $data);
        $course = get_course($existing->id);

        echo "UPDATED: {$definition['code']}" . PHP_EOL;
    } else {
        $course = create_course((object) $data);

        echo "CREATED: {$definition['code']}" . PHP_EOL;
    }

    $sections = $DB->get_records(
        'course_sections',
        ['course' => $course->id],
        'section ASC'
    );

    foreach ($sections as $section) {
        $number = (int) $section->section;

        if (!array_key_exists($number, $definition['sections'])) {
            continue;
        }

        $DB->set_field(
            'course_sections',
            'name',
            $definition['sections'][$number],
            ['id' => $section->id]
        );
    }

    rebuild_course_cache($course->id, true);
}

$secondary = nexus_category(
    'Ontario Secondary School Courses',
    'ONTARIO-SECONDARY'
);

$mathCategory = nexus_category(
    'Mathematics',
    'ONTARIO-MATHEMATICS',
    $secondary->id
);

$scienceCategory = nexus_category(
    'Science',
    'ONTARIO-SCIENCE',
    $secondary->id
);

/*
|--------------------------------------------------------------------------
| Mathematics courses
|--------------------------------------------------------------------------
*/

$mathCourses = [
    [
        'code' => 'MTH1W',
        'title' => 'Mathematics',
        'grade' => 9,
        'type' => 'De-streamed',
        'prerequisite' => 'None',
        'description' =>
            'Students develop mathematical thinking through number sense, algebra, ' .
            'data, geometry, measurement, financial literacy, coding, modelling, ' .
            'and real-world problem solving.',
        'sections' => [
            0 => 'Course Information and Announcements',
            1 => 'Mathematical Thinking and Making Connections',
            2 => 'Number',
            3 => 'Algebra and Coding',
            4 => 'Data',
            5 => 'Geometry and Measurement',
            6 => 'Financial Literacy',
            7 => 'Final Evaluation',
        ],
    ],
    [
        'code' => 'MPM2D',
        'title' => 'Principles of Mathematics',
        'grade' => 10,
        'type' => 'Academic',
        'prerequisite' => 'MTH1W',
        'description' =>
            'Students extend algebraic, analytic geometry, trigonometry, quadratic ' .
            'relations, and problem-solving skills in preparation for senior-level mathematics.',
        'sections' => [
            0 => 'Course Information and Announcements',
            1 => 'Linear Systems',
            2 => 'Analytic Geometry',
            3 => 'Quadratic Relations',
            4 => 'Trigonometry',
            5 => 'Final Evaluation',
        ],
    ],
    [
        'code' => 'MFM2P',
        'title' => 'Foundations of Mathematics',
        'grade' => 10,
        'type' => 'Applied',
        'prerequisite' => 'MTH1W',
        'description' =>
            'Students strengthen practical mathematical skills involving linear relations, ' .
            'measurement, geometry, trigonometry, and applications in everyday contexts.',
        'sections' => [
            0 => 'Course Information and Announcements',
            1 => 'Measurement and Similarity',
            2 => 'Linear Relations',
            3 => 'Graphing and Equations',
            4 => 'Trigonometry',
            5 => 'Final Evaluation',
        ],
    ],
    [
        'code' => 'MCR3U',
        'title' => 'Functions',
        'grade' => 11,
        'type' => 'University Preparation',
        'prerequisite' => 'MPM2D',
        'description' =>
            'Students study functions and their representations, including polynomial, ' .
            'rational, exponential, and trigonometric functions, with an emphasis on modelling.',
        'sections' => [
            0 => 'Course Information and Announcements',
            1 => 'Characteristics of Functions',
            2 => 'Quadratic Functions',
            3 => 'Polynomial and Rational Functions',
            4 => 'Exponential Functions',
            5 => 'Trigonometric Functions',
            6 => 'Final Evaluation',
        ],
    ],
    [
        'code' => 'MCF3M',
        'title' => 'Functions and Applications',
        'grade' => 11,
        'type' => 'University/College Preparation',
        'prerequisite' => 'MPM2D or MFM2P',
        'description' =>
            'Students investigate quadratic, exponential, and trigonometric functions ' .
            'and apply mathematical models to practical and technological situations.',
        'sections' => [
            0 => 'Course Information and Announcements',
            1 => 'Functions and Representations',
            2 => 'Quadratic Functions',
            3 => 'Exponential Functions',
            4 => 'Trigonometry',
            5 => 'Applications and Modelling',
            6 => 'Final Evaluation',
        ],
    ],
    [
        'code' => 'MBF3C',
        'title' => 'Foundations for College Mathematics',
        'grade' => 11,
        'type' => 'College Preparation',
        'prerequisite' => 'MPM2D or MFM2P',
        'description' =>
            'Students develop mathematical skills involving personal finance, measurement, ' .
            'geometry, data management, probability, and practical applications.',
        'sections' => [
            0 => 'Course Information and Announcements',
            1 => 'Mathematical Models',
            2 => 'Personal Finance',
            3 => 'Geometry and Measurement',
            4 => 'Data Management',
            5 => 'Probability',
            6 => 'Final Evaluation',
        ],
    ],
    [
        'code' => 'MEL3E',
        'title' => 'Mathematics for Work and Everyday Life',
        'grade' => 11,
        'type' => 'Workplace Preparation',
        'prerequisite' => 'MTH1W or an approved Grade 9 locally developed mathematics course',
        'description' =>
            'Students develop practical mathematics skills for employment, personal finance, ' .
            'measurement, transportation, budgeting, and independent living.',
        'sections' => [
            0 => 'Course Information and Announcements',
            1 => 'Earning and Purchasing',
            2 => 'Saving and Borrowing',
            3 => 'Transportation and Travel',
            4 => 'Measurement and Design',
            5 => 'Final Evaluation',
        ],
    ],
    [
        'code' => 'MHF4U',
        'title' => 'Advanced Functions',
        'grade' => 12,
        'type' => 'University Preparation',
        'prerequisite' => 'MCR3U or MCT4C',
        'description' =>
            'Students extend their understanding of polynomial, rational, logarithmic, ' .
            'exponential, and trigonometric functions and prepare for calculus.',
        'sections' => [
            0 => 'Course Information and Announcements',
            1 => 'Polynomial Functions',
            2 => 'Rational Functions',
            3 => 'Exponential and Logarithmic Functions',
            4 => 'Trigonometric Functions',
            5 => 'Rates of Change and Function Characteristics',
            6 => 'Final Evaluation',
        ],
    ],
    [
        'code' => 'MCV4U',
        'title' => 'Calculus and Vectors',
        'grade' => 12,
        'type' => 'University Preparation',
        'prerequisite' =>
            'MHF4U must be completed before or taken concurrently with this course',
        'description' =>
            'Students study limits, derivatives, rates of change, curve analysis, ' .
            'geometric and algebraic vectors, lines, planes, and applications.',
        'sections' => [
            0 => 'Course Information and Announcements',
            1 => 'Rates of Change and Limits',
            2 => 'Derivatives',
            3 => 'Applications of Derivatives',
            4 => 'Geometric Vectors',
            5 => 'Algebraic Vectors',
            6 => 'Lines and Planes',
            7 => 'Final Evaluation',
        ],
    ],
    [
        'code' => 'MDM4U',
        'title' => 'Mathematics of Data Management',
        'grade' => 12,
        'type' => 'University Preparation',
        'prerequisite' => 'MCR3U or MCF3M',
        'description' =>
            'Students study probability, counting techniques, probability distributions, ' .
            'statistics, data analysis, and the design and completion of a data-management project.',
        'sections' => [
            0 => 'Course Information and Announcements',
            1 => 'Counting and Probability',
            2 => 'Probability Distributions',
            3 => 'Organization of Data',
            4 => 'Statistical Analysis',
            5 => 'Data Management Investigation',
            6 => 'Final Evaluation',
        ],
    ],
    [
        'code' => 'MCT4C',
        'title' => 'Mathematics for College Technology',
        'grade' => 12,
        'type' => 'College Preparation',
        'prerequisite' => 'MCF3M or MCR3U',
        'description' =>
            'Students develop algebraic, trigonometric, exponential, and measurement skills ' .
            'for college technology programs and technical applications.',
        'sections' => [
            0 => 'Course Information and Announcements',
            1 => 'Polynomial and Rational Expressions',
            2 => 'Exponential Functions',
            3 => 'Trigonometry',
            4 => 'Measurement and Geometry',
            5 => 'Technical Applications',
            6 => 'Final Evaluation',
        ],
    ],
    [
        'code' => 'MAP4C',
        'title' => 'Foundations for College Mathematics',
        'grade' => 12,
        'type' => 'College Preparation',
        'prerequisite' => 'MBF3C or MCF3M',
        'description' =>
            'Students broaden their understanding of real-world mathematics through ' .
            'finance, geometry, measurement, data management, probability, and modelling.',
        'sections' => [
            0 => 'Course Information and Announcements',
            1 => 'Mathematical Models',
            2 => 'Personal Finance',
            3 => 'Geometry and Measurement',
            4 => 'Data and Probability',
            5 => 'Applications',
            6 => 'Final Evaluation',
        ],
    ],
    [
        'code' => 'MEL4E',
        'title' => 'Mathematics for Work and Everyday Life',
        'grade' => 12,
        'type' => 'Workplace Preparation',
        'prerequisite' => 'MEL3E',
        'description' =>
            'Students consolidate practical mathematics skills related to employment, ' .
            'housing, budgeting, transportation, taxes, measurement, and independent living.',
        'sections' => [
            0 => 'Course Information and Announcements',
            1 => 'Employment and Payroll',
            2 => 'Government and Personal Finance',
            3 => 'Housing and Transportation',
            4 => 'Measurement and Design',
            5 => 'Final Evaluation',
        ],
    ],
];

/*
|--------------------------------------------------------------------------
| Science courses
|--------------------------------------------------------------------------
*/

$scienceCourses = [
    [
        'code' => 'SNC1W',
        'title' => 'Science',
        'grade' => 9,
        'type' => 'De-streamed',
        'prerequisite' => 'None',
        'description' =>
            'Students investigate biology, chemistry, physics, Earth and space science, ' .
            'and the relationships between science, technology, society, and the environment.',
        'sections' => [
            0 => 'Course Information and Announcements',
            1 => 'STEM Skills and Connections',
            2 => 'Biology',
            3 => 'Chemistry',
            4 => 'Physics',
            5 => 'Earth and Space Science',
            6 => 'Final Evaluation',
        ],
    ],
    [
        'code' => 'SNC2D',
        'title' => 'Science',
        'grade' => 10,
        'type' => 'Academic',
        'prerequisite' => 'SNC1W',
        'description' =>
            'Students deepen their understanding of biology, chemistry, climate science, ' .
            'light, scientific investigation, and science-related environmental issues.',
        'sections' => [
            0 => 'Course Information and Announcements',
            1 => 'Scientific Investigation Skills',
            2 => 'Biology: Tissues, Organs, and Systems',
            3 => 'Chemistry: Chemical Reactions',
            4 => 'Earth and Space Science: Climate Change',
            5 => 'Physics: Light and Geometric Optics',
            6 => 'Final Evaluation',
        ],
    ],
    [
        'code' => 'SNC2P',
        'title' => 'Science',
        'grade' => 10,
        'type' => 'Applied',
        'prerequisite' => 'SNC1W',
        'description' =>
            'Students explore practical concepts in biology, chemistry, Earth science, ' .
            'and physics through investigations and everyday applications.',
        'sections' => [
            0 => 'Course Information and Announcements',
            1 => 'Scientific Investigation Skills',
            2 => 'Biology: Living Systems',
            3 => 'Chemistry: Chemical Reactions',
            4 => 'Earth and Space Science',
            5 => 'Physics: Light and Applications',
            6 => 'Final Evaluation',
        ],
    ],
    [
        'code' => 'SBI3U',
        'title' => 'Biology',
        'grade' => 11,
        'type' => 'University Preparation',
        'prerequisite' => 'SNC2D',
        'description' =>
            'Students study biodiversity, evolution, genetics, animals, plants, and the ' .
            'relationships between biological systems, technology, society, and the environment.',
        'sections' => [
            0 => 'Course Information and Announcements',
            1 => 'Scientific Investigation Skills',
            2 => 'Diversity of Living Things',
            3 => 'Evolution',
            4 => 'Genetic Processes',
            5 => 'Animals: Structure and Function',
            6 => 'Plants: Anatomy, Growth, and Function',
            7 => 'Final Evaluation',
        ],
    ],
    [
        'code' => 'SBI3C',
        'title' => 'Biology',
        'grade' => 11,
        'type' => 'College Preparation',
        'prerequisite' => 'SNC2D or SNC2P',
        'description' =>
            'Students explore cellular biology, microbiology, genetics, anatomy, ' .
            'plants, and environmental applications relevant to health and biotechnology.',
        'sections' => [
            0 => 'Course Information and Announcements',
            1 => 'Scientific Investigation Skills',
            2 => 'Cellular Biology',
            3 => 'Microbiology',
            4 => 'Genetics',
            5 => 'Anatomy of Mammals',
            6 => 'Plants and the Natural Environment',
            7 => 'Final Evaluation',
        ],
    ],
    [
        'code' => 'SCH3U',
        'title' => 'Chemistry',
        'grade' => 11,
        'type' => 'University Preparation',
        'prerequisite' => 'SNC2D',
        'description' =>
            'Students study matter, chemical bonding, reactions, quantitative relationships, ' .
            'solutions, solubility, gases, and atmospheric chemistry.',
        'sections' => [
            0 => 'Course Information and Announcements',
            1 => 'Scientific Investigation Skills',
            2 => 'Matter, Trends, and Chemical Bonding',
            3 => 'Chemical Reactions',
            4 => 'Quantities in Chemical Reactions',
            5 => 'Solutions and Solubility',
            6 => 'Gases and Atmospheric Chemistry',
            7 => 'Final Evaluation',
        ],
    ],
    [
        'code' => 'SPH3U',
        'title' => 'Physics',
        'grade' => 11,
        'type' => 'University Preparation',
        'prerequisite' => 'SNC2D',
        'description' =>
            'Students study motion, forces, energy, waves, sound, electricity, magnetism, ' .
            'and their technological and environmental applications.',
        'sections' => [
            0 => 'Course Information and Announcements',
            1 => 'Scientific Investigation Skills',
            2 => 'Kinematics',
            3 => 'Forces',
            4 => 'Energy and Society',
            5 => 'Waves and Sound',
            6 => 'Electricity and Magnetism',
            7 => 'Final Evaluation',
        ],
    ],
    [
        'code' => 'SVN3M',
        'title' => 'Environmental Science',
        'grade' => 11,
        'type' => 'University/College Preparation',
        'prerequisite' => 'SNC2D or SNC2P',
        'description' =>
            'Students investigate environmental challenges, ecosystems, human health, ' .
            'energy, conservation, sustainable agriculture, and waste management.',
        'sections' => [
            0 => 'Course Information and Announcements',
            1 => 'Scientific Investigation Skills',
            2 => 'Scientific Solutions to Contemporary Issues',
            3 => 'Human Health and the Environment',
            4 => 'Sustainable Agriculture and Forestry',
            5 => 'Reducing and Managing Waste',
            6 => 'Conservation of Energy',
            7 => 'Final Evaluation',
        ],
    ],
    [
        'code' => 'SNC4M',
        'title' => 'Science',
        'grade' => 12,
        'type' => 'University/College Preparation',
        'prerequisite' => 'SNC2D or SNC2P',
        'description' =>
            'Students examine medical technologies, disease, nutrition, public health, ' .
            'biotechnology, and contemporary health-related scientific issues.',
        'sections' => [
            0 => 'Course Information and Announcements',
            1 => 'Scientific Investigation Skills',
            2 => 'Medical Technologies',
            3 => 'Pathogens and Disease',
            4 => 'Nutritional Science',
            5 => 'Public Health Issues',
            6 => 'Biotechnology',
            7 => 'Final Evaluation',
        ],
    ],
    [
        'code' => 'SBI4U',
        'title' => 'Biology',
        'grade' => 12,
        'type' => 'University Preparation',
        'prerequisite' => 'SBI3U',
        'description' =>
            'Students study biochemistry, metabolic processes, molecular genetics, ' .
            'homeostasis, and population dynamics.',
        'sections' => [
            0 => 'Course Information and Announcements',
            1 => 'Scientific Investigation Skills',
            2 => 'Biochemistry',
            3 => 'Metabolic Processes',
            4 => 'Molecular Genetics',
            5 => 'Homeostasis',
            6 => 'Population Dynamics',
            7 => 'Final Evaluation',
        ],
    ],
    [
        'code' => 'SCH4U',
        'title' => 'Chemistry',
        'grade' => 12,
        'type' => 'University Preparation',
        'prerequisite' => 'SCH3U',
        'description' =>
            'Students study organic chemistry, atomic structure, energy changes, ' .
            'reaction rates, equilibrium, acids and bases, and electrochemistry.',
        'sections' => [
            0 => 'Course Information and Announcements',
            1 => 'Scientific Investigation Skills',
            2 => 'Organic Chemistry',
            3 => 'Structure and Properties of Matter',
            4 => 'Energy Changes and Rates of Reaction',
            5 => 'Chemical Systems and Equilibrium',
            6 => 'Electrochemistry',
            7 => 'Final Evaluation',
        ],
    ],
    [
        'code' => 'SCH4C',
        'title' => 'Chemistry',
        'grade' => 12,
        'type' => 'College Preparation',
        'prerequisite' => 'SNC2D or SNC2P',
        'description' =>
            'Students develop applied chemistry knowledge through matter, qualitative ' .
            'analysis, organic chemistry, electrochemistry, calculations, and environmental chemistry.',
        'sections' => [
            0 => 'Course Information and Announcements',
            1 => 'Scientific Investigation Skills',
            2 => 'Matter and Qualitative Analysis',
            3 => 'Organic Chemistry',
            4 => 'Electrochemistry',
            5 => 'Chemical Calculations',
            6 => 'Chemistry in the Environment',
            7 => 'Final Evaluation',
        ],
    ],
    [
        'code' => 'SPH4U',
        'title' => 'Physics',
        'grade' => 12,
        'type' => 'University Preparation',
        'prerequisite' => 'SPH3U',
        'description' =>
            'Students study dynamics, energy and momentum, gravitational and electric fields, ' .
            'waves, light, quantum mechanics, and special relativity.',
        'sections' => [
            0 => 'Course Information and Announcements',
            1 => 'Scientific Investigation Skills',
            2 => 'Dynamics',
            3 => 'Energy and Momentum',
            4 => 'Gravitational, Electric, and Magnetic Fields',
            5 => 'The Wave Nature of Light',
            6 => 'Revolutions in Modern Physics',
            7 => 'Final Evaluation',
        ],
    ],
    [
        'code' => 'SES4U',
        'title' => 'Earth and Space Science',
        'grade' => 12,
        'type' => 'University Preparation',
        'prerequisite' => 'SNC2D',
        'description' =>
            'Students study astronomy, the solar system, Earth materials, geological processes, ' .
            'Earth history, natural hazards, and interactions among Earth systems.',
        'sections' => [
            0 => 'Course Information and Announcements',
            1 => 'Scientific Investigation Skills',
            2 => 'Astronomy',
            3 => 'Planetary Science',
            4 => 'Earth Materials',
            5 => 'Geological Processes',
            6 => 'Earth History and Natural Hazards',
            7 => 'Final Evaluation',
        ],
    ],
    [
        'code' => 'SNC4E',
        'title' => 'Science',
        'grade' => 12,
        'type' => 'Workplace Preparation',
        'prerequisite' =>
            'SNC2P or an approved Grade 10 locally developed compulsory science course',
        'description' =>
            'Students apply science to workplace hazards, consumer products, disease prevention, ' .
            'electricity, nutrition, and everyday decision-making.',
        'sections' => [
            0 => 'Course Information and Announcements',
            1 => 'Scientific Investigation Skills',
            2 => 'Hazards in the Workplace',
            3 => 'Chemicals in Consumer Products',
            4 => 'Disease and Its Prevention',
            5 => 'Electricity at Home and Work',
            6 => 'Nutritional Science',
            7 => 'Final Evaluation',
        ],
    ],
];

foreach ($mathCourses as $course) {
    nexus_course($mathCategory, $course);
}

foreach ($scienceCourses as $course) {
    nexus_course($scienceCategory, $course);
}

echo PHP_EOL;
echo count($mathCourses) . " Mathematics courses processed." . PHP_EOL;
echo count($scienceCourses) . " Science courses processed." . PHP_EOL;
echo "All courses remain hidden pending Nexus approval." . PHP_EOL;
echo PHP_EOL;
