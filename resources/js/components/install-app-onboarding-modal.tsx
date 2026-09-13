import { Download } from "lucide-react"
import { useEffect, useRef, useState } from "react"
import { useQueryClient } from "@tanstack/react-query"
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
import Axios from "@/lib/axios"
import toast from "@/lib/toast"

// Persisted per tab/session so "Not now" only silences the prompt for this
// visit — the modal keeps re-asking every session until the app is actually
// installed (or the browser stops offering an install prompt). markComplete()
// just avoids redundant API calls; it no longer permanently hides the modal,
// since installOnboardedAt can't detect a later uninstall.
const DISMISSED_KEY = "install-prompt-dismissed"

function wasDismissedThisSession(): boolean {
	return sessionStorage.getItem(DISMISSED_KEY) === "1"
}

// The install step is settled for this visit once the user has installed,
// dismissed the prompt, or there's nothing to prompt for (already installed,
// or the browser never offered an install prompt at all). The notifications
// onboarding modal waits on this so it never appears ahead of — or stacked
// on top of — the install prompt.
export function useIsInstallStepSettled(): boolean {
	const { canInstall, isInstalled } = usePwaInstall()

	if (isInstalled || !canInstall) {
		return true
	}

	return wasDismissedThisSession()
}

export default function InstallAppOnboardingModal() {
	const { auth } = useApp()
	const queryClient = useQueryClient()
	const { canInstall, install, isInstalled } = usePwaInstall()

	const [open, setOpen] = useState(false)
	const [processing, setProcessing] = useState(false)
	const markedRef = useRef(false)

	const onboardedAt = auth?.settings?.installOnboardedAt

	function markComplete() {
		if (markedRef.current) {
			return
		}
		markedRef.current = true

		Axios.post("api/onboarding/install").then(() => {
			queryClient.invalidateQueries({ queryKey: ["auth"] })
		})
	}

	useEffect(() => {
		if (!auth || wasDismissedThisSession()) {
			return
		}

		if (isInstalled || !canInstall) {
			if (!onboardedAt) {
				markComplete()
			}
			setOpen(false)
			return
		}

		setOpen(true)
	}, [auth, onboardedAt, isInstalled, canInstall])

	async function handleInstall() {
		setProcessing(true)

		try {
			const accepted = await install()

			if (accepted) {
				toast.success("Black Gallery installed", {
					description: "Find it on your home screen for quick access.",
				})
			}

			markComplete()
			setOpen(false)
		} finally {
			setProcessing(false)
		}
	}

	function handleSkip() {
		sessionStorage.setItem(DISMISSED_KEY, "1")
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
						<Download className="size-8 text-primary" />
					</div>
					<DialogHeader className="items-center gap-2">
						<DialogTitle>Install Black Gallery</DialogTitle>
						<DialogDescription>
							Install the app for quick access to this week&apos;s challenge
							from your home screen, in a window of its own.
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
						onClick={() => void handleInstall()}>
						{processing && <Spinner className="size-4" />}
						Install app
					</Button>
				</DialogFooter>
			</DialogContent>
		</Dialog>
	)
}
