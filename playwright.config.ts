import { defineConfig, devices } from "@playwright/test"

/**
 * Browser smoke tests. They drive the app that's already running locally
 * (`./vendor/bin/sail up -d` plus built assets or `npm run dev`), so start
 * it before `npm run test:e2e`.
 */
export default defineConfig({
	testDir: "tests/e2e",
	fullyParallel: true,
	forbidOnly: !!process.env.CI,
	retries: process.env.CI ? 1 : 0,
	// Sail serves the app with a single threaded `artisan serve`, so more
	// than two browsers at once queue behind each other and time out.
	workers: process.env.CI ? undefined : 2,
	reporter: process.env.CI ? "github" : "list",
	use: {
		baseURL: process.env.E2E_BASE_URL ?? "http://localhost:8020",
		trace: "retain-on-failure",
		screenshot: "only-on-failure",
		// The installed Chrome locally, so no browser download is needed.
		channel: process.env.CI ? undefined : "chrome",
	},
	projects: [
		{
			name: "setup",
			testMatch: /auth\.setup\.ts/,
		},
		{
			// The phone the apps are mostly used on: a Galaxy Z Fold6's cover
			// screen.
			name: "phone",
			dependencies: ["setup"],
			use: {
				...devices["Galaxy S9+"],
				viewport: { width: 360, height: 780 },
				storageState: "tests/e2e/.auth/user.json",
			},
		},
	],
})
