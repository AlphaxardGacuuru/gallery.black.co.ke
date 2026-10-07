import { useSyncExternalStore } from "react"
import Axios from "@/lib/axios"

type BeforeInstallPromptEvent = Event & {
	prompt: () => Promise<void>
	userChoice: Promise<{ outcome: "accepted" | "dismissed" }>
}

// `beforeinstallprompt` fires once per page load, at a moment Chrome decides
// on its own. Module-level (not per-component) state means whichever
// component happens to be mounted first — AppSidebar on every authenticated
// page, in practice — captures it, and every other usePwaInstall() caller
// (e.g. the get-app page, mounted later or never) still sees it via this
// shared store instead of missing the event entirely.
let deferredPrompt: BeforeInstallPromptEvent | null = null
let installed = false
const listeners = new Set<() => void>()

function notify(): void {
	listeners.forEach((listener) => listener())
}

// Distinct from the onboarding modal's installOnboardedAt flag (set on
// dismiss and on "nothing to prompt for" too, not just real installs);
// this only ever fires from the browser's own appinstalled event or an
// "accepted" prompt outcome, so the admin users table can trust it as a
// genuine installed signal. Guarded so a redundant appinstalled-after-
// accepted firing (common) doesn't send the request twice.
let hasRecordedInstall = false

function recordInstalled(): void {
	if (hasRecordedInstall) {
		return
	}

	hasRecordedInstall = true

	Axios.post("api/onboarding/pwa-installed").catch(() => {
		hasRecordedInstall = false
	})
}

function computeIsInstalled(): boolean {
	return (
		window.matchMedia("(display-mode: standalone)").matches ||
		("standalone" in navigator &&
			Boolean((navigator as Navigator & { standalone?: boolean }).standalone))
	)
}

if (typeof window !== "undefined") {
	installed = computeIsInstalled()

	window.addEventListener("beforeinstallprompt", (event) => {
		event.preventDefault()
		deferredPrompt = event as BeforeInstallPromptEvent
		notify()
	})

	window.addEventListener("appinstalled", () => {
		deferredPrompt = null
		installed = true
		recordInstalled()
		notify()
	})
}

function subscribe(callback: () => void): () => void {
	listeners.add(callback)
	return () => listeners.delete(callback)
}

function getPromptSnapshot(): BeforeInstallPromptEvent | null {
	return deferredPrompt
}

function getInstalledSnapshot(): boolean {
	return installed
}

function getServerSnapshot(): null {
	return null
}

function getServerInstalledSnapshot(): boolean {
	return false
}

async function install(): Promise<boolean> {
	if (!deferredPrompt) {
		return false
	}

	const prompt = deferredPrompt
	await prompt.prompt()
	const choice = await prompt.userChoice
	deferredPrompt = null

	if (choice.outcome === "accepted") {
		installed = true
		recordInstalled()
	}

	notify()

	return choice.outcome === "accepted"
}

export function usePwaInstall() {
	const installPrompt = useSyncExternalStore(
		subscribe,
		getPromptSnapshot,
		getServerSnapshot
	)
	const isInstalled = useSyncExternalStore(
		subscribe,
		getInstalledSnapshot,
		getServerInstalledSnapshot
	)

	return {
		canInstall: Boolean(installPrompt) && !isInstalled,
		install,
		isInstalled,
	}
}
