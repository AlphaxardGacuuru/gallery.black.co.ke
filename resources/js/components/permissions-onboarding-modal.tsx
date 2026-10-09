import { Bell } from "lucide-react"
import { useEffect, useRef, useState, useSyncExternalStore } from "react"
import { useQueryClient } from "@tanstack/react-query"
import { useIsInstallStepSettled } from "@/components/install-app-onboarding-modal"
import { Button } from "@/components/ui/button"
import {
	Dialog,
	DialogContent,
	DialogDescription,
	DialogFooter,
	DialogHeader,
	DialogTitle,
} from "@/components/ui/dialog"
import { Spinner } from "@/components/ui/spinner"
import { useApp } from "@/contexts/AppContext"
import { usePwaInstall } from "@/hooks/use-pwa-install"
import { usePushNotifications } from "@/hooks/use-push-notifications"
import Axios from "@/lib/axios"
import toast from "@/lib/toast"

// Persisted per tab/session so "Not now" only silences the prompt for this
// visit — the modal keeps re-asking every session until push notifications
// are actually enabled. `markComplete()` just avoids redundant API calls; it
// no longer permanently hides the modal.
const DISMISSED_KEY = "notifications-prompt-dismissed"

// sessionStorage writes don't notify the current tab, so dismissing fires
// this event to let useIsPermissionsStepSettled() subscribers (the referral
// modal) re-render and open right after "Not now".
const DISMISSED_EVENT = "notifications-prompt-dismissed"

function wasDismissedThisSession(): boolean {
	return sessionStorage.getItem(DISMISSED_KEY) === "1"
}

function subscribeToDismissal(callback: () => void): () => void {
	window.addEventListener(DISMISSED_EVENT, callback)
	return () => window.removeEventListener(DISMISSED_EVENT, callback)
}

// Mirrors useIsInstallStepSettled() — the referral onboarding modal waits on
// this so it never appears ahead of, or stacked on top of, the
// notifications prompt.
export function useIsPermissionsStepSettled(): boolean {
	const { isSupported, permission } = usePushNotifications()
	const { isInstalled } = usePwaInstall()
	const installStepSettled = useIsInstallStepSettled()
	const dismissed = useSyncExternalStore(
		subscribeToDismissal,
		wasDismissedThisSession,
	)

	if (!installStepSettled) {
		return false
	}

	if (!isInstalled || !isSupported || permission === "granted") {
		return true
	}

	return dismissed
}

export default function PermissionsOnboardingModal() {
	const { auth } = useApp()
	const queryClient = useQueryClient()
	const { isSupported, permission, subscribe } = usePushNotifications()
	const { isInstalled } = usePwaInstall()
	const installStepSettled = useIsInstallStepSettled()

	const [open, setOpen] = useState(false)
	const [processing, setProcessing] = useState(false)
	const markedRef = useRef(false)

	const onboardedAt = auth?.settings?.permissionsOnboardedAt

	function markComplete() {
		if (markedRef.current) {
			return
		}
		markedRef.current = true

		Axios.post("api/onboarding/permissions").then(() => {
			queryClient.invalidateQueries({ queryKey: ["auth"] })
		})
	}

	useEffect(() => {
		if (
			!auth ||
			wasDismissedThisSession() ||
			!installStepSettled ||
			// Only ask once the app is actually installed and running
			// standalone — never prompt for this in a regular browser tab.
			!isInstalled
		) {
			return
		}

		if (!isSupported) {
			if (!onboardedAt) {
				markComplete()
			}
			setOpen(false)
			return
		}

		if (permission === "granted") {
			if (!onboardedAt) {
				markComplete()
			}
			setOpen(false)
			return
		}

		setOpen(true)
	}, [
		auth,
		onboardedAt,
		isSupported,
		permission,
		installStepSettled,
		isInstalled,
	])

	async function handleEnable() {
		setProcessing(true)

		try {
			const enabled = await subscribe()

			if (enabled) {
				toast.success("Notifications enabled", {
					description: "You'll get a native alert for new challenge activity.",
				})
				markComplete()
				setOpen(false)
				return
			}

			if (permission === "denied") {
				toast.error("Notifications blocked", {
					description:
						"Allow notifications for this site in your browser settings.",
				})
			}
		} finally {
			setProcessing(false)
		}
	}

	function handleSkip() {
		sessionStorage.setItem(DISMISSED_KEY, "1")
		window.dispatchEvent(new Event(DISMISSED_EVENT))
		setOpen(false)
	}

	return (
		<Dialog
			open={open}
			onOpenChange={(next) => {
				if (!next) {
					handleSkip()
				}
			}}>
			<DialogContent className="sm:max-w-sm">
				<div className="flex flex-col items-center gap-4 pt-2 text-center">
					<div className="flex size-16 items-center justify-center rounded-full bg-primary/10">
						<Bell className="size-8 text-primary" />
					</div>
					<DialogHeader className="items-center gap-2">
						<DialogTitle>Enable notifications</DialogTitle>
						<DialogDescription>
							Enable notifications so you know the moment this week's challenge
							starts, ends, or your photo gets a new like, even when the app
							isn&apos;t open.
						</DialogDescription>
					</DialogHeader>
				</div>
				<DialogFooter className="sm:justify-center">
					<Button
						type="button"
						variant="outline"
						disabled={processing}
						onClick={handleSkip}>
						Not now
					</Button>
					<Button
						type="button"
						disabled={processing}
						onClick={() => void handleEnable()}>
						{processing && <Spinner className="size-4" />}
						Enable notifications
					</Button>
				</DialogFooter>
			</DialogContent>
		</Dialog>
	)
}
