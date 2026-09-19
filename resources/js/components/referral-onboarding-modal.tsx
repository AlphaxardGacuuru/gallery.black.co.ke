import { Gift } from "lucide-react"
import { useLayoutEffect, useRef, useState } from "react"
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

// Unlike the install/permissions steps, there's no browser-observable state
// to re-check each session (no "is it installed?" equivalent) — this is a
// one-time announcement, so referralOnboardedAt alone gates whether it
// shows again, not a per-session dismissal key.
export default function ReferralOnboardingModal() {
	const { auth } = useApp()
	const queryClient = useQueryClient()
	const navigate = useNavigate()
	const permissionsStepSettled = useIsPermissionsStepSettled()
	const { data: settings } = useReferralSettings()

	const [open, setOpen] = useState(false)
	const markedRef = useRef(false)

	const onboardedAt = auth?.settings?.referralOnboardedAt

	// Returns a promise so callers that navigate away (unmounting this
	// component and mounting a fresh instance on the destination page) can
	// wait for the auth cache to actually reflect referralOnboardedAt first
	// — otherwise the new instance mounts while it's still unset and reopens
	// itself on top of the page just navigated to.
	function markComplete(): Promise<unknown> {
		if (markedRef.current) {
			return Promise.resolve()
		}
		markedRef.current = true

		return Axios.post("api/onboarding/referral")
			.then(() => queryClient.invalidateQueries({ queryKey: ["auth"] }))
			.catch(() => {
				// Bookkeeping only — don't block the user over a failed flag.
			})
	}

	// Self-correcting in both directions, not just "open once": navigating
	// to a fresh page can re-render this component a tick before the auth
	// cache's invalidated refetch (from markComplete()) actually lands, so
	// a stale read of onboardedAt here would otherwise flip it back open
	// with nothing left to close it again. useLayoutEffect (not useEffect)
	// so that correction lands before the browser paints, instead of as a
	// visible flash.
	useLayoutEffect(() => {
		if (onboardedAt) {
			setOpen(false)
			return
		}

		if (!auth || !permissionsStepSettled || !settings) {
			return
		}

		setOpen(true)
	}, [auth, permissionsStepSettled, onboardedAt, settings])

	function handleClose() {
		markComplete()
		setOpen(false)
	}

	async function handleGoToReferrals() {
		setOpen(false)
		await markComplete()
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
					handleClose()
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
						onClick={handleClose}>
						Close
					</Button>
					<Button
						type="button"
						onClick={() => void handleGoToReferrals()}>
						Go to referrals
					</Button>
				</DialogFooter>
			</DialogContent>
		</Dialog>
	)
}
