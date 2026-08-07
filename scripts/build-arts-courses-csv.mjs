import fs from 'node:fs/promises';
import path from 'node:path';
import { chromium } from 'playwright';

const ROOT = process.cwd();

const CODE_FILE = path.join(
    ROOT,
    'scripts/ministry-data/arts-missing-codes.txt'
);

const OUTPUT_FILE = path.join(
    ROOT,
    'scripts/ministry-data/arts-courses.csv'
);

const BASE_URL =
    'https://www.dcp.edu.gov.on.ca/en/curriculum/' +
    'secondary-arts/courses';

const DEPARTMENT = 'The Arts';
const DEPARTMENT_ID = 'ONTARIO-ARTS';

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

function gradeFromCode(code) {
    return {
        1: 9,
        2: 10,
        3: 11,
        4: 12,
    }[Number(code.charAt(3))] ?? 0;
}

function typeFromCode(code) {
    return {
        O: 'Open',
        U: 'University Preparation',
        C: 'College Preparation',
        M: 'University/College Preparation',
        E: 'Workplace Preparation',
        D: 'Academic',
        P: 'Applied',
        W: 'De-streamed',
    }[code.at(-1)] ?? 'Open';
}

function extractIssuedYear(text) {
    return text.match(/Issued:\s*(20\d{2})/i)?.[1] ?? '2010';
}

function extractPrerequisite(text) {
    const match = text.match(
        /Prerequisite:\s*(.+?)(?=\s+(?:Course information|Overall expectations|Resources|Downloads|Back to top|$))/i
    );

    return clean(match?.[1] || 'None');
}

function extractDescription(text) {
    const match = text.match(
        /(This course .+?)(?=\s+(?:Read online|Course information|Download curriculum PDF|Prerequisite:|More|Back to top|$))/i
    );

    return clean(match?.[1] || '');
}

function findTitle(text, code) {
    const lines = text
        .split(/\n+/)
        .map(clean)
        .filter(Boolean);

    const codeIndex = lines.findIndex(line => line === code);

    if (codeIndex === -1) {
        return '';
    }

    for (
        let index = codeIndex + 1;
        index < Math.min(lines.length, codeIndex + 8);
        index++
    ) {
        const line = lines[index];

        if (
            /^Grade (9|10|11|12)$/i.test(line) ||
            /^Issued:/i.test(line) ||
            /^(Open|University Preparation|College Preparation|University\/College Preparation|Workplace Preparation)$/i.test(line)
        ) {
            continue;
        }

        return line;
    }

    return '';
}

async function collectCourse(page, code) {
    const url = `${BASE_URL}/${code.toLowerCase()}`;

    console.log(`Collecting ${code}...`);

    await page.goto(url, {
        waitUntil: 'domcontentloaded',
        timeout: 90000,
    });

    await page.waitForTimeout(900);

    const rawText = await page.locator('body').innerText();
    const pageText = clean(rawText);

    let title = findTitle(rawText, code);

    if (!title) {
        title = clean(
            await page
                .locator('h1')
                .first()
                .textContent()
                .catch(() => '')
        );
    }

    title = title
        .replace(new RegExp(`^${code}\\s*[-–—|:]?\\s*`, 'i'), '')
        .trim();

    let description = extractDescription(pageText);

    if (!description) {
        const paragraphs = await page
            .locator('main p')
            .allTextContents()
            .catch(() => []);

        description =
            paragraphs
                .map(clean)
                .find(value => /^This course /i.test(value)) || '';
    }

    if (!title || !description) {
        throw new Error(
            `Missing title or description for ${code}`
        );
    }

    return {
        department: DEPARTMENT,
        department_id: DEPARTMENT_ID,
        code,
        title,
        grade: gradeFromCode(code),
        type: typeFromCode(code),
        credit: '1.0',
        prerequisite: extractPrerequisite(pageText),
        description,
        curriculum_year: extractIssuedYear(pageText),
        ministry_source_url: url,
    };
}

const codes = (
    await fs.readFile(CODE_FILE, 'utf8')
)
    .split(/\r?\n/)
    .map(clean)
    .filter(Boolean);

if (!codes.length) {
    throw new Error('No Arts course codes found.');
}

const browser = await chromium.launch({
    headless: true,
});

const page = await browser.newPage({
    locale: 'en-CA',
});

const rows = [];
const failed = [];

for (const code of codes) {
    try {
        rows.push(await collectCourse(page, code));
    } catch (error) {
        failed.push({
            code,
            error: error.message,
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

const csv = [
    headers.join(','),
    ...rows.map(row =>
        headers
            .map(header => csvEscape(row[header]))
            .join(',')
    ),
].join('\n');

await fs.writeFile(
    OUTPUT_FILE,
    `${csv}\n`,
    'utf8'
);

console.log('');
console.log(`Codes requested: ${codes.length}`);
console.log(`Rows created: ${rows.length}`);
console.log(`Rows failed: ${failed.length}`);
console.log(`Saved: ${OUTPUT_FILE}`);

if (failed.length) {
    console.log('');
    console.log('Failed courses:');

    for (const item of failed) {
        console.log(
            `${item.code}: ${item.error}`
        );
    }

    process.exitCode = 1;
}
