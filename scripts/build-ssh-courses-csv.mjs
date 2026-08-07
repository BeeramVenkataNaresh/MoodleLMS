import fs from 'node:fs/promises';
import path from 'node:path';
import { chromium } from 'playwright';

const PROJECT_ROOT = process.cwd();

const CODE_FILE = path.join(
    PROJECT_ROOT,
    'scripts/ministry-data/ssh-missing-codes.txt'
);

const OUTPUT_FILE = path.join(
    PROJECT_ROOT,
    'scripts/ministry-data/ssh-courses.csv'
);

const BASE_URL =
    'https://www.dcp.edu.gov.on.ca/en/curriculum/' +
    'social-sciences-humanities/courses';

const DEPARTMENT = 'Social Sciences and Humanities';
const DEPARTMENT_ID = 'ONTARIO-SSH';

function clean(value = '') {
    return String(value)
        .replace(/\u00a0/g, ' ')
        .replace(/\s+/g, ' ')
        .trim();
}

function csvEscape(value) {
    const text = clean(value);

    if (
        text.includes(',') ||
        text.includes('"') ||
        text.includes('\n')
    ) {
        return `"${text.replaceAll('"', '""')}"`;
    }

    return text;
}

function courseTypeFromCode(code) {
    const suffix = code.at(-1);

    const types = {
        O: 'Open',
        D: 'Academic',
        P: 'Applied',
        U: 'University Preparation',
        C: 'College Preparation',
        M: 'University/College Preparation',
        E: 'Workplace Preparation',
        L: 'Locally Developed',
        W: 'De-streamed',
    };

    return types[suffix] ?? 'Open';
}

function gradeFromCode(code) {
    const gradeIndicator = Number(code.charAt(3));

    const grades = {
        1: 9,
        2: 10,
        3: 11,
        4: 12,
    };

    return grades[gradeIndicator] ?? 0;
}

function extractIssuedYear(text) {
    const match = text.match(/Issued:\s*(20\d{2})/i);
    return match?.[1] ?? '2013';
}

function extractPrerequisite(text) {
    const normalized = clean(text);

    const patterns = [
        /Prerequisite:\s*(.+?)(?=\s+(?:Course|Overall expectations|Strand|Resources|Downloads|Back to top|$))/i,
        /Prerequisites:\s*(.+?)(?=\s+(?:Course|Overall expectations|Strand|Resources|Downloads|Back to top|$))/i,
    ];

    for (const pattern of patterns) {
        const match = normalized.match(pattern);

        if (match?.[1]) {
            return clean(match[1]);
        }
    }

    return 'None';
}

function extractDescription(text, title) {
    const normalized = clean(text);

    const titleIndex = normalized.indexOf(title);

    if (titleIndex === -1) {
        return '';
    }

    const afterTitle = normalized.slice(
        titleIndex + title.length
    );

    const descriptionMatch = afterTitle.match(
        /(?:Issued:\s*20\d{2}\s*)?(This course .+?)(?=\s+(?:Read online|Course information|Download curriculum PDF|Prerequisite:|Overall expectations|$))/i
    );

    if (descriptionMatch?.[1]) {
        return clean(descriptionMatch[1]);
    }

    return '';
}

async function extractCourse(page, code) {
    const url = `${BASE_URL}/${code.toLowerCase()}`;

    console.log(`Collecting ${code}...`);

    await page.goto(url, {
        waitUntil: 'domcontentloaded',
        timeout: 90000,
    });

    await page.waitForTimeout(1200);

    const pageText = clean(
        await page.locator('body').innerText()
    );

    const heading = await page
        .locator('h1')
        .first()
        .textContent()
        .catch(() => '');

    let title = clean(heading);

    title = title
        .replace(new RegExp(`^${code}\\s*[-–—|:]?\\s*`, 'i'), '')
        .replace(/\s*Grade\s+(9|10|11|12).*$/i, '')
        .trim();

    if (!title || title === code) {
        const codePosition = pageText.indexOf(code);

        if (codePosition >= 0) {
            const nearbyText = pageText.slice(
                codePosition,
                codePosition + 500
            );

            const lines = nearbyText
                .split(/\n+/)
                .map(clean)
                .filter(Boolean);

            title =
                lines.find(
                    line =>
                        line !== code &&
                        !/^Grade \d+$/i.test(line) &&
                        !/^(Open|Academic|Applied|College Preparation|University Preparation|University\/College Preparation|Workplace Preparation)$/i.test(line)
                ) ?? code;
        }
    }

    const grade = gradeFromCode(code);
    const type = courseTypeFromCode(code);
    const year = extractIssuedYear(pageText);
    const prerequisite = extractPrerequisite(pageText);

    let description = extractDescription(
        pageText,
        title
    );

    if (!description) {
        const paragraphTexts = await page
            .locator('main p')
            .allTextContents()
            .catch(() => []);

        description =
            paragraphTexts
                .map(clean)
                .find(text =>
                    /^This course /i.test(text)
                ) ?? '';
    }

    if (!title || !description) {
        throw new Error(
            `Could not extract complete information for ${code}`
        );
    }

    return {
        department: DEPARTMENT,
        department_id: DEPARTMENT_ID,
        code,
        title,
        grade,
        type,
        credit: '1.0',
        prerequisite,
        description,
        curriculum_year: year,
        ministry_source_url: url,
    };
}

const codes = (
    await fs.readFile(CODE_FILE, 'utf8')
)
    .split(/\r?\n/)
    .map(clean)
    .filter(Boolean);

if (codes.length === 0) {
    throw new Error(
        'No course codes found in ssh-missing-codes.txt'
    );
}

const browser = await chromium.launch({
    headless: true,
});

const context = await browser.newContext({
    locale: 'en-CA',
});

const page = await context.newPage();

const rows = [];
const failed = [];

for (const code of codes) {
    try {
        rows.push(await extractCourse(page, code));
    } catch (error) {
        failed.push({
            code,
            message: error.message,
        });

        console.error(
            `FAILED ${code}: ${error.message}`
        );
    }
}

await browser.close();

const headers = [
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

const csvLines = [
    headers.join(','),
    ...rows.map(row =>
        headers
            .map(header => csvEscape(row[header]))
            .join(',')
    ),
];

await fs.writeFile(
    OUTPUT_FILE,
    `${csvLines.join('\n')}\n`,
    'utf8'
);

console.log('');
console.log(`Codes requested: ${codes.length}`);
console.log(`Rows created: ${rows.length}`);
console.log(`Rows failed: ${failed.length}`);
console.log(`Saved: ${OUTPUT_FILE}`);

if (failed.length > 0) {
    console.log('');
    console.log('Failed courses:');

    for (const item of failed) {
        console.log(
            `${item.code}: ${item.message}`
        );
    }

    process.exitCode = 1;
}
