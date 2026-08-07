<?php

define('CLI_SCRIPT', true);

require '/var/www/moodle/config.php';
require_once $CFG->dirroot . '/course/lib.php';

global $DB;

echo PHP_EOL;
echo "Nexus EPS — Business, Computer and Guidance Import" . PHP_EOL;
echo "===================================================" . PHP_EOL;

/**
 * Retrieve or create one Moodle category.
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
        return core_course_category::get((int)$id);
    }

    $category = core_course_category::create([
        'name' => $name,
        'idnumber' => $idnumber,
        'parent' => $parent,
        'visible' => 1,
        'description' =>
            '<p>Ontario Ministry-aligned courses offered by ' .
            'Nexus Education Private School.</p>',
        'descriptionformat' => FORMAT_HTML,
    ]);

    echo "CATEGORY CREATED: {$name}" . PHP_EOL;

    return $category;
}

/**
 * Create department Grade 9–12 categories.
 */
function nexus_grade_category(
    core_course_category $department,
    int $grade
): core_course_category {
    return nexus_category(
        "Grade {$grade}",
        $department->idnumber . "-GRADE-{$grade}",
        $department->id
    );
}

/**
 * Create or update one course.
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

    $credit = $definition['credit'] ?? 1.0;

    $summary = '
        <div class="nexus-course-profile">
            <h3>Ontario Ministry Course Information</h3>

            <p><strong>Course code:</strong> ' .
                s($definition['code']) .
            '</p>

            <p><strong>Grade:</strong> ' .
                s((string)$definition['grade']) .
            '</p>

            <p><strong>Course type:</strong> ' .
                s($definition['type']) .
            '</p>

            <p><strong>Credit value:</strong> ' .
                s(number_format((float)$credit, 1)) .
            '</p>

            <p><strong>Prerequisite:</strong> ' .
                s($definition['prerequisite']) .
            '</p>

            <h4>Course description</h4>

            <p>' . s($definition['description']) . '</p>

            <p>
                <strong>Status:</strong>
                Course shell pending Nexus academic and compliance review.
            </p>

            <p>
                <strong>Curriculum source:</strong>
                Ontario Ministry of Education
            </p>
        </div>
    ';

    $data = [
        'category' => $category->id,
        'fullname' =>
            $definition['title'] . ' | ' . $definition['code'],
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
        update_course((object)$data);
        $course = get_course($existing->id);

        echo "UPDATED: {$definition['code']}" . PHP_EOL;
    } else {
        $course = create_course((object)$data);

        echo "CREATED: {$definition['code']}" . PHP_EOL;
    }

    $sections = $DB->get_records(
        'course_sections',
        ['course' => $course->id],
        'section ASC'
    );

    foreach ($sections as $section) {
        $number = (int)$section->section;

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

$root = nexus_category(
    'Ontario Secondary School Courses',
    'ONTARIO-SECONDARY'
);

$business = nexus_category(
    'Business Studies',
    'ONTARIO-BUSINESS',
    $root->id
);

$computer = nexus_category(
    'Computer Studies',
    'ONTARIO-COMPUTER-STUDIES',
    $root->id
);

$guidance = nexus_category(
    'Guidance and Career Education',
    'ONTARIO-GUIDANCE',
    $root->id
);

$categories = [
    'business' => [],
    'computer' => [],
    'guidance' => [],
];

foreach ([9, 10, 11, 12] as $grade) {
    $categories['business'][$grade] =
        nexus_grade_category($business, $grade);

    $categories['computer'][$grade] =
        nexus_grade_category($computer, $grade);

    $categories['guidance'][$grade] =
        nexus_grade_category($guidance, $grade);
}

$businessCourses = [
    [
        'code' => 'BEM1O',
        'title' => 'Building the Entrepreneurial Mindset',
        'grade' => 9,
        'type' => 'Open',
        'prerequisite' => 'None',
        'description' =>
            'Students develop an entrepreneurial mindset by identifying ' .
            'opportunities, generating ideas, solving problems, creating value, ' .
            'and examining ethical and responsible business practices.',
        'sections' => [
            0 => 'Course Information and Announcements',
            1 => 'The Entrepreneurial Mindset',
            2 => 'Recognizing Opportunities',
            3 => 'Generating and Testing Ideas',
            4 => 'Creating Value',
            5 => 'Ethics, Responsibility, and Impact',
            6 => 'Entrepreneurial Project',
            7 => 'Final Evaluation',
        ],
    ],
    [
        'code' => 'BEP2O',
        'title' => 'Launching and Leading a Business',
        'grade' => 10,
        'type' => 'Open',
        'prerequisite' => 'None',
        'description' =>
            'Students explore how businesses are launched and managed. They ' .
            'investigate customers, operations, finance, marketing, leadership, ' .
            'innovation, and responsible business decision-making.',
        'sections' => [
            0 => 'Course Information and Announcements',
            1 => 'Business Opportunities',
            2 => 'Customers and Market Research',
            3 => 'Marketing and Communication',
            4 => 'Operations and Finance',
            5 => 'Leadership and Teamwork',
            6 => 'Business Launch Project',
            7 => 'Final Evaluation',
        ],
    ],
    [
        'code' => 'BAF3M',
        'title' => 'Financial Accounting Fundamentals',
        'grade' => 11,
        'type' => 'University/College Preparation',
        'prerequisite' => 'None',
        'description' =>
            'Students learn fundamental accounting principles, the accounting ' .
            'cycle, financial statements, internal controls, ethics, and the use ' .
            'of accounting information in business decisions.',
        'sections' => [
            0 => 'Course Information and Announcements',
            1 => 'Accounting Foundations',
            2 => 'The Accounting Cycle',
            3 => 'Journalizing and Posting',
            4 => 'Financial Statements',
            5 => 'Merchandising and Internal Control',
            6 => 'Ethics and Accounting Technology',
            7 => 'Final Evaluation',
        ],
    ],
    [
        'code' => 'BAI3E',
        'title' => 'Accounting Essentials',
        'grade' => 11,
        'type' => 'Workplace Preparation',
        'prerequisite' => 'None',
        'description' =>
            'Students develop practical accounting skills used in personal and ' .
            'small-business settings, including transactions, records, banking, ' .
            'payroll, budgeting, and financial documents.',
        'sections' => [
            0 => 'Course Information and Announcements',
            1 => 'Personal and Business Records',
            2 => 'Banking and Cash Control',
            3 => 'Sales and Purchases',
            4 => 'Payroll',
            5 => 'Budgeting',
            6 => 'Accounting Technology',
            7 => 'Final Evaluation',
        ],
    ],
    [
        'code' => 'BDI3C',
        'title' => 'Entrepreneurship: The Venture',
        'grade' => 11,
        'type' => 'College Preparation',
        'prerequisite' => 'None',
        'description' =>
            'Students investigate opportunities for a new venture, develop a ' .
            'business concept, conduct market research, plan operations and ' .
            'finances, and present a venture proposal.',
        'sections' => [
            0 => 'Course Information and Announcements',
            1 => 'Entrepreneurial Opportunities',
            2 => 'Market Research',
            3 => 'Marketing Strategy',
            4 => 'Operations and Human Resources',
            5 => 'Financial Planning',
            6 => 'Venture Plan',
            7 => 'Final Evaluation',
        ],
    ],
    [
        'code' => 'BMI3C',
        'title' => 'Marketing: Goods, Services, Events',
        'grade' => 11,
        'type' => 'College Preparation',
        'prerequisite' => 'None',
        'description' =>
            'Students examine marketing fundamentals, consumer behaviour, ' .
            'branding, product development, pricing, distribution, promotion, ' .
            'digital marketing, and event marketing.',
        'sections' => [
            0 => 'Course Information and Announcements',
            1 => 'Marketing Fundamentals',
            2 => 'Consumer Behaviour',
            3 => 'Products, Services, and Branding',
            4 => 'Pricing and Distribution',
            5 => 'Promotion and Digital Marketing',
            6 => 'Marketing Plan',
            7 => 'Final Evaluation',
        ],
    ],
    [
        'code' => 'BMX3E',
        'title' => 'Marketing: Retail and Service',
        'grade' => 11,
        'type' => 'Workplace Preparation',
        'prerequisite' => 'None',
        'description' =>
            'Students develop practical retail and service-marketing skills, ' .
            'including customer service, merchandising, promotion, sales, ' .
            'workplace communication, and responsible business practices.',
        'sections' => [
            0 => 'Course Information and Announcements',
            1 => 'Retail and Service Businesses',
            2 => 'Customer Service',
            3 => 'Merchandising',
            4 => 'Sales and Promotion',
            5 => 'Workplace Communication',
            6 => 'Retail Marketing Project',
            7 => 'Final Evaluation',
        ],
    ],
    [
        'code' => 'BAT4M',
        'title' => 'Financial Accounting Principles',
        'grade' => 12,
        'type' => 'University/College Preparation',
        'prerequisite' => 'BAF3M',
        'description' =>
            'Students study advanced accounting principles, partnerships, ' .
            'corporations, financial statement analysis, accounting standards, ' .
            'ethics, and management decision-making.',
        'sections' => [
            0 => 'Course Information and Announcements',
            1 => 'Accounting Principles and Standards',
            2 => 'Partnerships',
            3 => 'Corporations',
            4 => 'Financial Statement Analysis',
            5 => 'Management Accounting',
            6 => 'Ethics and Technology',
            7 => 'Final Evaluation',
        ],
    ],
    [
        'code' => 'BBB4M',
        'title' => 'International Business Fundamentals',
        'grade' => 12,
        'type' => 'University/College Preparation',
        'prerequisite' => 'None',
        'description' =>
            'Students examine international trade, globalization, cultural and ' .
            'political influences, international marketing, finance, logistics, ' .
            'ethics, and strategies for operating globally.',
        'sections' => [
            0 => 'Course Information and Announcements',
            1 => 'Globalization and International Trade',
            2 => 'Culture, Politics, and Economics',
            3 => 'International Marketing',
            4 => 'Finance and Currency',
            5 => 'Logistics and Supply Chains',
            6 => 'International Business Strategy',
            7 => 'Final Evaluation',
        ],
    ],
    [
        'code' => 'BDV4C',
        'title' => 'Venture Planning in an Electronic Age',
        'grade' => 12,
        'type' => 'College Preparation',
        'prerequisite' => 'None',
        'description' =>
            'Students develop and evaluate a venture plan using digital tools, ' .
            'market research, online marketing, operations planning, financial ' .
            'analysis, and electronic business strategies.',
        'sections' => [
            0 => 'Course Information and Announcements',
            1 => 'Opportunity Identification',
            2 => 'Digital Business Models',
            3 => 'Online Market Research',
            4 => 'Digital Marketing',
            5 => 'Operations and Financial Planning',
            6 => 'Electronic Venture Plan',
            7 => 'Final Evaluation',
        ],
    ],
    [
        'code' => 'BOH4M',
        'title' => 'Business Leadership: Management Fundamentals',
        'grade' => 12,
        'type' => 'University/College Preparation',
        'prerequisite' => 'None',
        'description' =>
            'Students analyse management theories, leadership styles, planning, ' .
            'organizing, communication, motivation, teamwork, ethics, and change ' .
            'management in organizations.',
        'sections' => [
            0 => 'Course Information and Announcements',
            1 => 'Management Foundations',
            2 => 'Leadership',
            3 => 'Planning and Decision-Making',
            4 => 'Organizing and Human Resources',
            5 => 'Motivation and Communication',
            6 => 'Ethics, Change, and Organizational Culture',
            7 => 'Final Evaluation',
        ],
    ],
];

$computerCourses = [
    [
        'code' => 'ICD2O',
        'title' => 'Digital Technology and Innovations in the Changing World',
        'grade' => 10,
        'type' => 'Open',
        'prerequisite' => 'None',
        'description' =>
            'Students explore digital technologies, computational thinking, ' .
            'programming, data, cybersecurity, artificial intelligence, digital ' .
            'citizenship, and the social impact of emerging technologies.',
        'sections' => [
            0 => 'Course Information and Announcements',
            1 => 'Digital Technology and Society',
            2 => 'Computational Thinking',
            3 => 'Programming Fundamentals',
            4 => 'Data and Information',
            5 => 'Cybersecurity and Digital Citizenship',
            6 => 'Emerging Technologies and Innovation',
            7 => 'Final Evaluation',
        ],
    ],
    [
        'code' => 'ICS3C',
        'title' => 'Introduction to Computer Programming',
        'grade' => 11,
        'type' => 'College Preparation',
        'prerequisite' => 'None',
        'description' =>
            'Students develop practical computer-programming skills by designing, ' .
            'writing, testing, and documenting programs and examining computer ' .
            'hardware, software, networks, and career opportunities.',
        'sections' => [
            0 => 'Course Information and Announcements',
            1 => 'Programming Concepts',
            2 => 'Selection and Repetition',
            3 => 'Functions and Modular Programming',
            4 => 'Data and File Processing',
            5 => 'Software Development and Testing',
            6 => 'Computing Environments and Careers',
            7 => 'Final Evaluation',
        ],
    ],
    [
        'code' => 'ICS3U',
        'title' => 'Introduction to Computer Science',
        'grade' => 11,
        'type' => 'University Preparation',
        'prerequisite' => 'None',
        'description' =>
            'Students design software using industry-standard programming tools, ' .
            'apply the software development life cycle, use subprograms and data ' .
            'structures, and examine computer science careers and ethical issues.',
        'sections' => [
            0 => 'Course Information and Announcements',
            1 => 'Programming Fundamentals',
            2 => 'Control Structures',
            3 => 'Functions and Modular Design',
            4 => 'Data Structures',
            5 => 'Software Development Life Cycle',
            6 => 'Computer Science, Society, and Careers',
            7 => 'Final Evaluation',
        ],
    ],
    [
        'code' => 'ICS4C',
        'title' => 'Computer Programming',
        'grade' => 12,
        'type' => 'College Preparation',
        'prerequisite' => 'ICS3C',
        'description' =>
            'Students develop advanced practical programming skills, create and ' .
            'maintain software solutions, use data structures and files, test and ' .
            'document programs, and investigate workplace practices.',
        'sections' => [
            0 => 'Course Information and Announcements',
            1 => 'Advanced Programming Concepts',
            2 => 'Data Structures and Files',
            3 => 'User Interfaces',
            4 => 'Software Design',
            5 => 'Testing and Maintenance',
            6 => 'Collaborative Development Project',
            7 => 'Final Evaluation',
        ],
    ],
    [
        'code' => 'ICS4U',
        'title' => 'Computer Science',
        'grade' => 12,
        'type' => 'University Preparation',
        'prerequisite' => 'ICS3U',
        'description' =>
            'Students analyse algorithms, use complex data structures, apply ' .
            'object-oriented programming, work in software-development teams, and ' .
            'examine ethical and environmental issues in computing.',
        'sections' => [
            0 => 'Course Information and Announcements',
            1 => 'Advanced Algorithms',
            2 => 'Object-Oriented Programming',
            3 => 'Data Structures',
            4 => 'Software Engineering',
            5 => 'Collaborative Development',
            6 => 'Ethics and Emerging Computer Science',
            7 => 'Final Evaluation',
        ],
    ],
];

$guidanceCourses = [
    [
        'code' => 'GLS1O',
        'title' => 'Learning Strategies 1: Skills for Success in Secondary School',
        'grade' => 9,
        'type' => 'Open',
        'prerequisite' => 'None',
        'description' =>
            'Students develop learning strategies, organization, time management, ' .
            'literacy, numeracy, self-advocacy, goal-setting, and transferable ' .
            'skills that support success in secondary school.',
        'sections' => [
            0 => 'Course Information and Announcements',
            1 => 'Knowing Yourself as a Learner',
            2 => 'Organization and Time Management',
            3 => 'Literacy and Numeracy Strategies',
            4 => 'Study and Assessment Strategies',
            5 => 'Self-Advocacy and Well-Being',
            6 => 'Personal Learning Plan',
            7 => 'Final Evaluation',
        ],
    ],
    [
        'code' => 'GLE1O',
        'title' => 'Learning Strategies 1: Skills for Success in Secondary School',
        'grade' => 9,
        'type' => 'Open',
        'prerequisite' =>
            'Recommendation of the principal and identified learning needs',
        'description' =>
            'Students strengthen individualized learning strategies, organization, ' .
            'literacy, numeracy, communication, assistive-technology, self-advocacy, ' .
            'and transition skills.',
        'sections' => [
            0 => 'Course Information and Announcements',
            1 => 'Individual Learning Profile',
            2 => 'Organization and Planning',
            3 => 'Literacy and Numeracy Support',
            4 => 'Assistive and Digital Tools',
            5 => 'Self-Advocacy',
            6 => 'Transition and Success Plan',
            7 => 'Final Evaluation',
        ],
    ],
    [
        'code' => 'GLC2O',
        'title' => 'Career Studies',
        'grade' => 10,
        'type' => 'Open',
        'credit' => 0.5,
        'prerequisite' => 'None',
        'description' =>
            'Students develop the knowledge, skills, and habits needed for ' .
            'education and career/life planning, transferable skills, financial ' .
            'literacy, workplace readiness, and adapting to a changing world of work.',
        'sections' => [
            0 => 'Course Information and Announcements',
            1 => 'Personal Skills and Self-Knowledge',
            2 => 'Transferable Skills',
            3 => 'Education and Career Pathways',
            4 => 'The Changing World of Work',
            5 => 'Financial Literacy and Well-Being',
            6 => 'Career and Life Plan',
            7 => 'Final Evaluation',
        ],
    ],
    [
        'code' => 'GPP3O',
        'title' => 'Leadership and Peer Support',
        'grade' => 11,
        'type' => 'Open',
        'prerequisite' => 'None',
        'description' =>
            'Students develop leadership, communication, interpersonal, conflict ' .
            'resolution, teamwork, facilitation, mentoring, and peer-support skills ' .
            'through practical school and community experiences.',
        'sections' => [
            0 => 'Course Information and Announcements',
            1 => 'Leadership Foundations',
            2 => 'Communication and Relationships',
            3 => 'Conflict Resolution',
            4 => 'Teamwork and Facilitation',
            5 => 'Peer Support and Mentoring',
            6 => 'Leadership Project',
            7 => 'Final Evaluation',
        ],
    ],
    [
        'code' => 'GWL3O',
        'title' => 'Designing Your Future',
        'grade' => 11,
        'type' => 'Open',
        'prerequisite' => 'None',
        'description' =>
            'Students explore personal strengths, education and career pathways, ' .
            'workplace trends, financial planning, decision-making, and strategies ' .
            'for managing transitions and designing their future.',
        'sections' => [
            0 => 'Course Information and Announcements',
            1 => 'Self-Knowledge',
            2 => 'Education and Training Pathways',
            3 => 'Career Exploration',
            4 => 'Workplace Trends',
            5 => 'Financial and Life Planning',
            6 => 'Personal Transition Plan',
            7 => 'Final Evaluation',
        ],
    ],
    [
        'code' => 'GLN4O',
        'title' => 'Navigating the Workplace',
        'grade' => 12,
        'type' => 'Open',
        'prerequisite' => 'None',
        'description' =>
            'Students develop workplace readiness through career planning, job ' .
            'searching, workplace communication, health and safety, employee rights, ' .
            'financial planning, and strategies for workplace success.',
        'sections' => [
            0 => 'Course Information and Announcements',
            1 => 'Workplace Expectations',
            2 => 'Job Search Skills',
            3 => 'Communication and Teamwork',
            4 => 'Rights, Responsibilities, and Safety',
            5 => 'Financial Planning',
            6 => 'Workplace Transition Portfolio',
            7 => 'Final Evaluation',
        ],
    ],
];

foreach ($businessCourses as $course) {
    nexus_import_course(
        $categories['business'][$course['grade']],
        $course
    );
}

foreach ($computerCourses as $course) {
    nexus_import_course(
        $categories['computer'][$course['grade']],
        $course
    );
}

foreach ($guidanceCourses as $course) {
    nexus_import_course(
        $categories['guidance'][$course['grade']],
        $course
    );
}

echo PHP_EOL;
echo count($businessCourses) .
    " Business Studies courses processed." . PHP_EOL;

echo count($computerCourses) .
    " Computer Studies courses processed." . PHP_EOL;

echo count($guidanceCourses) .
    " Guidance courses processed." . PHP_EOL;

echo "All courses remain hidden pending Nexus review." . PHP_EOL;
echo PHP_EOL;
