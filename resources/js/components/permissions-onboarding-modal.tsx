import { Bell } from "lucide-react"
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
import { usePushNotifications } from "@/hooks/use-push-notifications"
import Axios from "@/lib/axios"
import toast from "@/lib/toast"

// Persisted per tab/session (not to the server) so "Not now" only silences
// the prompt for this visit — it can still ask again next time the user
// opens the site, unlike markComplete() which is permanent.
const DISMISSED_KEY = "notifications-prompt-dismissed"

function wasDismissedThisSession(): boolean {
	return sessionStorage.getItem(DISMISSED_KEY) === "1"
}

export default function PermissionsOnboardingModal() {
	const { auth } = useApp()
	const queryClient = useQueryClient()
	const { isSupported, permission, subscribe } = usePushNotifications()

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
		if (!auth || onboardedAt || wasDismissedThisSession()) {
			return
		}

		if (!isSupported) {
			markComplete()
			setOpen(false)
			return
		}

		if (permission === "granted") {
			markComplete()
			setOpen(false)
			return
		}

		setOpen(true)
	}, [auth, onboardedAt, isSupported, permission])

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
