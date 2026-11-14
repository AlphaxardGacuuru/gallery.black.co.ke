import { execSync } from "node:child_process"
import { expect, test as setup } from "@playwright/test"

export const E2E_EMAIL = process.env.E2E_EMAIL ?? "e2e@example.test"
export const E2E_PASSWORD = process.env.E2E_PASSWORD ?? "e2e-smoke-password"

// Locally artisan runs through Sail; CI sets E2E_ARTISAN="php artisan".
const ARTISAN = process.env.E2E_ARTISAN ?? "./vendor/bin/sail artisan"

setup("signs in the smoke test user", async ({ page }) => {
	// Everyone signs in with Google here, which a test can't drive. Google's
	// callback ends by sending the browser to /socialite-callback with a
	// Sanctum token, so the setup mints that token itself (creating the user,
	// or resetting it, so a fresh database works too) and walks the same last
	// step of the real sign-in.
	const token = execSync(
		`${ARTISAN} tinker --execute '${[
			"$user = App\\Models\\User::query()",
			`->updateOrCreate(["email" => "${E2E_EMAIL}"], [`,
			'"name" => "E2E Smoke Test",',
			`"password" => Illuminate\\Support\\Facades\\Hash::make("${E2E_PASSWORD}"),`,
			"]);",
			// Not fillable, so it's set directly.
			'$user->forceFill(["email_verified_at" => now()])',
			"->save();",
			'$user->tokens()',
			'->where("name", "e2e")',
			"->delete();",
			'echo $user->createToken("e2e")',
			"->plainTextToken;",
		].join(" ")}'`,
		{ encoding: "utf8" }
	)
		.trim()
		.split("\n")
		.pop()

	expect(token, "tinker printed no token").toMatch(/^\d+\|\w+$/)

	await page.goto(`/socialite-callback?token=${encodeURIComponent(token ?? "")}&provider=google`)

	await expect(page).toHaveURL(/\/compete$/, { timeout: 15_000 })

	await page
		.context()
		.storageState({ path: "tests/e2e/.auth/user.json" })
})
