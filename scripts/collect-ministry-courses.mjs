import { chromium } from "playwright";
import fs from "node:fs/promises";
import path from "node:path";

const departments = [
  {
    slug: "business-studies",
    name: "Business Studies",
    url: "https://www.dcp.edu.gov.on.ca/en/curriculum/business-studies/courses-list",
  },
  {
    slug: "canadian-and-world-studies",
    name: "Canadian and World Studies",
    url: "https://www.dcp.edu.gov.on.ca/en/curriculum/canadian-and-world-studies/courses-list",
  },
  {
    slug: "computer-studies",
    name: "Computer Studies",
    url: "https://www.dcp.edu.gov.on.ca/en/curriculum/computer-studies/courses-list",
  },
  {
    slug: "english",
    name: "English",
    url: "https://www.dcp.edu.gov.on.ca/en/curriculum/secondary-english/courses-list",
  },
  {
    slug: "guidance-and-career-education",
    name: "Guidance and Career Education",
    url: "https://www.dcp.edu.gov.on.ca/en/curriculum/secondary-guidance-and-career-education/courses-list",
  },
  {
    slug: "mathematics",
    name: "Mathematics",
    url: "https://www.dcp.edu.gov.on.ca/en/curriculum/secondary-mathematics/courses-list",
  },
  {
    slug: "science",
    name: "Science",
    url: "https://www.dcp.edu.gov.on.ca/en/curriculum/secondary-science/courses-list",
  },
  {
    slug: "social-sciences-and-humanities",
    name: "Social Sciences and Humanities",
    url: "https://www.dcp.edu.gov.on.ca/en/curriculum/social-sciences-humanities/courses-list",
  },
  {
    slug: "health-and-physical-education",
    name: "Health and Physical Education",
    url: "https://www.dcp.edu.gov.on.ca/en/curriculum/secondary-hpe/courses-list",
  },
  {
    name: "The Arts",
    slug: "secondary-arts",
    url: "https://www.dcp.edu.gov.on.ca/en/curriculum/secondary-arts/courses-list",
  },,
];

const requestedSlug = process.argv[2] ?? "all";
const selected =
  requestedSlug === "all"
    ? departments
    : departments.filter((item) => item.slug === requestedSlug);

if (selected.length === 0) {
  console.error(`Unknown department: ${requestedSlug}`);
  console.error(
    `Available: ${departments.map((item) => item.slug).join(", ")}`
  );
  process.exit(1);
}

const outputDirectory = path.resolve("scripts/ministry-data");
await fs.mkdir(outputDirectory, { recursive: true });

const browser = await chromium.launch({ headless: true });
const context = await browser.newContext({
  viewport: { width: 1440, height: 1200 },
});

for (const department of selected) {
  console.log(`Collecting: ${department.name}`);

  const page = await context.newPage();

  try {
    await page.goto(department.url, {
      waitUntil: "domcontentloaded",
      timeout: 120000,
    });

    await page.waitForTimeout(8000);

    // Scroll through the page so lazy-loaded course content is rendered.
    await page.evaluate(async () => {
      await new Promise((resolve) => {
        let total = 0;
        const distance = 700;

        const timer = setInterval(() => {
          window.scrollBy(0, distance);
          total += distance;

          if (total >= document.body.scrollHeight + 2000) {
            clearInterval(timer);
            resolve();
          }
        }, 250);
      });
    });

    await page.waitForTimeout(3000);

    // Expand buttons that may hide course details.
    const expandableButtons = page.locator(
      'button[aria-expanded="false"], [role="button"][aria-expanded="false"]'
    );

    const buttonCount = await expandableButtons.count();

    for (let index = 0; index < buttonCount; index += 1) {
      try {
        await expandableButtons.nth(index).click({ timeout: 1500 });
      } catch {
        // Some controls may be hidden, duplicated, or not course controls.
      }
    }

    await page.waitForTimeout(3000);

    const result = await page.evaluate(
      ({ name, slug, url }) => ({
        department: name,
        slug,
        sourceUrl: url,
        pageTitle: document.title,
        collectedAt: new Date().toISOString(),
        text: document.body.innerText,
        html: document.documentElement.outerHTML,
      }),
      department
    );

    await fs.writeFile(
      path.join(outputDirectory, `${department.slug}.json`),
      JSON.stringify(result, null, 2),
      "utf8"
    );

    await fs.writeFile(
      path.join(outputDirectory, `${department.slug}.txt`),
      result.text,
      "utf8"
    );

    console.log(`Saved: scripts/ministry-data/${department.slug}.json`);
  } catch (error) {
    console.error(`Failed: ${department.name}`);
    console.error(error instanceof Error ? error.message : error);
  } finally {
    await page.close();
  }
}

await browser.close();

console.log("Collection complete.");
