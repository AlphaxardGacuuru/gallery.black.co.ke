import { existsSync } from "node:fs"
import { expect, test } from "@playwright/test"
import type { ConsoleMessage, Page } from "@playwright/test"

const PAGES = [
	"/compete",
	"/discover",
	"/leaderboard",
	"/notifications",
	"/photos/create",
	"/support",
	"/get-app",
	"/settings/profile",
	"/settings/security",
	"/settings/appearance",
	"/settings/referrals",
]

/** Pages anyone can open without signing in. */
const PUBLIC_PAGES = ["/", "/login"]

const SIGNED_OUT = { storageState: { cookies: [], origins: [] } }

/**
 * Uploaded files (avatars, photos) live in storage/app/public, which isn't in
 * git, so a local database points at files a checkout doesn't have and the
 * server answers those with a 403. That's missing local data, not a broken
 * page.
 */
function isMissingUpload(message: ConsoleMessage) {
	return message
		.text()
		.startsWith("Failed to load resource")
		&& new URL(message.location().url, "http://localhost").pathname.startsWith("/storage/")
}

/** Collects uncaught errors and console errors while a page is open. */
function watchErrors(page: Page): string[] {
	const errors: string[] = []

	page.on("pageerror", (error) => errors.push(error.message))
	page.on("console", (message) => {
		if (message.type() === "error" && !isMissingUpload(message)) {
			errors.push(message.text())
		}
	})

	return errors
}

/** The app mounted something, so the screen isn't blank. */
async function expectAppRendered(page: Page) {
	await expect(page.locator("#app > *").first()).toBeVisible({ timeout: 15_000 })
}

/** How far the page reaches past the right edge of the screen. */
function overflow(page: Page) {
	return page.evaluate(() => document.documentElement.scrollWidth - window.innerWidth)
}

for (const path of PAGES) {
	test(`${path} opens without errors and fits the phone screen`, async ({ page }) => {
		const errors = watchErrors(page)

		await page.goto(path)
		await expectAppRendered(page)
		await expect(page).toHaveURL(new RegExp(`${path}$`))
		await expect(page.getByRole("navigation").last()).toBeVisible()
		await page.waitForLoadState("networkidle")

		expect(await overflow(page), "page is wider than the screen").toBeLessThanOrEqual(0)
		expect(errors).toEqual([])
	})
}

for (const path of PUBLIC_PAGES) {
	// App bug: AppContext's useQuery(["auth"]) calls /api/auth even when there's
	// no sanctumToken in localStorage, so every signed out visit logs a 401
	// console error (two on /). It wants `enabled` tied to the token, like
	// getAuth() in middleware/auth.ts does.
	test.fixme(`${path} opens signed out without errors and fits the phone screen`, async ({ browser }) => {
		const context = await browser.newContext(SIGNED_OUT)
		const page = await context.newPage()
		const errors = watchErrors(page)

		await page.goto(path)
		await expectAppRendered(page)
		await expect(page).toHaveURL(new RegExp(`${path}$`))
		await page.waitForLoadState("networkidle")

		expect(await overflow(page), "page is wider than the screen").toBeLessThanOrEqual(0)
		expect(errors).toEqual([])

		await context.close()
	})
}

test("the bottom nav moves between pages", async ({ page }) => {
	await page.goto("/compete")
	await expectAppRendered(page)

	const bottomNav = page.locator("nav.max-w-md")

	await bottomNav
		.getByRole("link", { name: "Discover" })
		.click()
	await expect(page).toHaveURL(/\/discover$/)

	await bottomNav
		.getByRole("link", { name: "Leaderboard" })
		.click()
	await expect(page).toHaveURL(/\/leaderboard$/)

	await bottomNav
		.getByRole("link", { name: "This week" })
		.click()
	await expect(page).toHaveURL(/\/compete$/)
})

test("signed out visitors are sent to the login page", async ({ browser }) => {
	const context = await browser.newContext(SIGNED_OUT)
	const page = await context.newPage()

	await page.goto("/compete")
	await expect(page).toHaveURL(/\/login/)

	await context.close()
})

test("the app still opens offline once the service worker has it", async ({ page, context }) => {
	// The Vite dev server serves unbundled modules the service worker never
	// precaches, so this only means something against built assets.
	test.skip(existsSync("public/hot"), "needs built assets, not `npm run dev`")

	await page.goto("/compete")
	await expectAppRendered(page)
	await page.evaluate(() => navigator.serviceWorker.ready)
	// Load once under the service worker's control so it caches the page.
	await page.reload()
	await expectAppRendered(page)

	await context.setOffline(true)
	await page.reload()
	await expectAppRendered(page)
	await context.setOffline(false)
})
