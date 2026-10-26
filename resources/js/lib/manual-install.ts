// Only Chromium browsers (Chrome, Edge, Samsung Internet, ...) fire
// `beforeinstallprompt`, which is what powers our one-tap "Install" button.
// Everywhere else the user has to add the app from the browser's own menu,
// so these are the steps to show them instead.

export type ManualInstallGuide = {
	/** Shown above the steps, e.g. why they can't install from here. */
	intro?: string
	steps: string[]
	/** Shown under the steps, e.g. what to try if the option is missing. */
	note?: string
}

// Social apps' built-in browsers can't install anything, so the user has to
// get into a real browser first.
const IN_APP_BROWSER = /FBAN|FBAV|FB_IAB|Instagram|TikTok|musical_ly|Snapchat|Line\/|Twitter/i

function isIos(ua: string): boolean {
	// iPadOS reports itself as a Mac, but Macs don't have touch screens.
	return (
		/iPhone|iPad|iPod/.test(ua) ||
		(/Macintosh/.test(ua) && navigator.maxTouchPoints > 1)
	)
}

/**
 * Manual install steps for the current browser. Chromium browsers only get
 * steps when `includeBrowserMenu` is set: they normally offer the one-tap
 * prompt, so the steps are just a fallback for when it hasn't fired.
 */
export function getManualInstallGuide({
	includeBrowserMenu = false,
}: { includeBrowserMenu?: boolean } = {}): ManualInstallGuide | null {
	const ua = navigator.userAgent

	if (IN_APP_BROWSER.test(ua)) {
		return {
			intro: "Apps can't be installed from this in-app browser.",
			steps: [
				"Tap the menu (⋯ or ⋮) in the corner of the screen",
				'Choose "Open in browser" (or "Open in Chrome" / "Open in Safari")',
				"Then follow the install steps there",
			],
		}
	}

	if (isIos(ua)) {
		return {
			steps: [
				"Tap the Share button (the square with an arrow, at the bottom of Safari or next to the address bar in other browsers)",
				'Scroll down and tap "Add to Home Screen"',
				'Tap "Add"',
			],
			note: "Don't see the option? Open this page in Safari and try again.",
		}
	}

	if (/Android/.test(ua) && /Firefox\//.test(ua)) {
		return {
			steps: [
				"Tap the menu (⋮) next to the address bar",
				'Tap "Add to Home screen" (or "Install")',
				'Tap "Add" to confirm',
			],
		}
	}

	if (/Firefox\//.test(ua)) {
		return {
			intro: "Firefox on computers can't install apps.",
			steps: [
				"Open this page in Chrome, Edge or Safari",
				"Install Black Gallery from there",
			],
		}
	}

	if (
		/Macintosh/.test(ua) &&
		/Safari\//.test(ua) &&
		!/Chrome|Chromium|Edg|OPR/.test(ua)
	) {
		return {
			steps: [
				"In the menu bar, click File",
				'Choose "Add to Dock"',
				'Click "Add"',
			],
			note: "Needs macOS Sonoma or later.",
		}
	}

	if (!includeBrowserMenu) {
		return null
	}

	return {
		steps: [
			"Open the browser menu (⋮ or ⋯)",
			'Choose "Install app" or "Add to Home screen"',
			"Confirm to add it",
		],
	}
}
