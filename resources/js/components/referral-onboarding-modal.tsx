import { Gift } from "lucide-react"
import { useEffect, useRef, useState } from "react"
import { useNavigate } from "@tanstack/react-router"
import { useQueryClient } from "@tanstack/react-query"
import { useIsPermissionsStepSettled } from "@/components/permissions-onboarding-modal"
import { Button } from "@/components/ui/button"
import {
	Dialog,
	DialogContent,
	DialogDescription,
	DialogFooter,
	DialogHeader,
	DialogTitle,
} from "@/components/ui/dialog"
import { useApp } from "@/contexts/AppContext"
import Axios from "@/lib/axios"
import { toUrl } from "@/lib/utils"
import { useReferralSettings } from "@/queries/referrals"
import { edit as editReferrals } from "@/routes/referrals"

// Persisted per tab/session so the referral prompt shows once every time the
// user opens the app, after the install and notifications prompts are out of
// the way. Closing it only silences it for this visit. Because it's written
// synchronously before navigating, the fresh instance mounted on the
// destination page reads it straight away and stays closed.
const DISMISSED_KEY = "referral-prompt-dismissed"

function wasDismissedThisSession(): boolean {
	return sessionStorage.getItem(DISMISSED_KEY) === "1"
}

export default function ReferralOnboardingModal() {
	const { auth } = useApp()
	const queryClient = useQueryClient()
	const navigate = useNavigate()
	const permissionsStepSettled = useIsPermissionsStepSettled()
	const { data: settings } = useReferralSettings()

	const [open, setOpen] = useState(false)
	const markedRef = useRef(false)

	const onboardedAt = auth?.settings?.referralOnboardedAt

	// Bookkeeping only: records when the user first saw the prompt. It no
	// longer gates whether the modal shows.
	function markComplete() {
		if (onboardedAt || markedRef.current) {
			return
		}
		markedRef.current = true

		Axios.post("api/onboarding/referral")
			.then(() => queryClient.invalidateQueries({ queryKey: ["auth"] }))
			.catch(() => {
				// Don't block the user over a failed flag.
			})
	}

	useEffect(() => {
		if (
			!auth ||
			!permissionsStepSettled ||
			!settings ||
			wasDismissedThisSession()
		) {
			return
		}

		setOpen(true)
	}, [auth, permissionsStepSettled, settings])

	function dismiss() {
		sessionStorage.setItem(DISMISSED_KEY, "1")
		markComplete()
		setOpen(false)
	}

	function handleGoToReferrals() {
		dismiss()
		navigate({ to: toUrl(editReferrals()) })
	}

	if (!settings) {
		return null
	}

	return (
		<Dialog
			open={open}
			onOpenChange={(next) => {
				if (!next) {
					dismiss()
				}
			}}>
			<DialogContent className="sm:max-w-sm">
				<div className="flex flex-col items-center gap-4 pt-2 text-center">
					<div className="flex size-16 items-center justify-center rounded-full bg-primary/10">
						<Gift className="size-8 text-primary" />
					</div>
					<DialogHeader className="items-center gap-2">
						<DialogTitle>Earn KES {settings.rewardAmount}</DialogTitle>
						<DialogDescription>
							Refer {settings.threshold} friends this week and get KES{" "}
							{settings.rewardAmount} sent straight to your M-Pesa.
						</DialogDescription>
					</DialogHeader>
				</div>
				<DialogFooter className="sm:justify-center">
					<Button
						type="button"
						variant="outline"
						onClick={dismiss}>
						Close
					</Button>
					<Button
						type="button"
						onClick={handleGoToReferrals}>
						Go to referrals
					</Button>
				</DialogFooter>
			</DialogContent>
		</Dialog>
	)
}
