import { useEffect, useRef, useState } from "react"
import { useQueryClient } from "@tanstack/react-query"
import { errorMessage } from "@/components/photos/upload-errors"
import { Alert, AlertDescription, AlertTitle } from "@/components/ui/alert"
import { Button } from "@/components/ui/button"
import { Spinner } from "@/components/ui/spinner"
import { useApp } from "@/contexts/AppContext"
import toast from "@/lib/toast"
import {
	usePollPhoneVerification,
	useStartPhoneVerification,
} from "@/queries/phone-verification"
import { paymentFailureMessage } from "@/queries/photos"

/**
 * Shown after the user changes their phone number: paying the small fee
 * from the new number proves it's a real M-Pesa line they control, and
 * lifts the flag the change set. Walks idle -> waiting for the STK push to
 * be approved -> paid (the account refreshes and this disappears) or
 * failed (back to idle with the reason).
 */
export function VerifyPhoneAlert() {
	const { auth } = useApp()
	const queryClient = useQueryClient()
	const startVerification = useStartPhoneVerification()
	const [verificationId, setVerificationId] = useState<string | null>(null)
	const { data: verification } = usePollPhoneVerification(verificationId)
	const notifiedIdRef = useRef<string | null>(null)
	const fee = auth?.phoneVerificationFee

	useEffect(() => {
		if (
			!verification ||
			verification.status === "pending" ||
			notifiedIdRef.current === verificationId
		) {
			return
		}

		notifiedIdRef.current = verificationId

		if (verification.status === "paid") {
			toast.success("Phone number confirmed", {
				description: "Your account is back to normal.",
			})
			queryClient.invalidateQueries({ queryKey: ["auth"] })

			return
		}

		toast.error("Payment not completed", {
			description: paymentFailureMessage(verification.failureReason),
		})
	}, [verification, verificationId, queryClient])

	function handlePay() {
		startVerification.mutate(undefined, {
			onSuccess: ({ verificationId: id }) => setVerificationId(id),
			onError: (error) =>
				toast.error("Couldn't start the payment", {
					description: errorMessage(error, "Please try again."),
				}),
		})
	}

	const waiting =
		!!verificationId && (!verification || verification.status === "pending")

	return (
		<Alert>
			<AlertTitle>Confirm your new phone number</AlertTitle>
			<AlertDescription className="space-y-3">
				<p>
					Pay KES {fee} from {auth?.phone} with M-Pesa to confirm it&apos;s your
					number. Until then you can&apos;t like photos or enter the challenge,
					and payouts are on hold.
				</p>
				<Button
					type="button"
					size="sm"
					disabled={waiting || startVerification.isPending}
					onClick={handlePay}>
					{(waiting || startVerification.isPending) && (
						<Spinner className="size-4" />
					)}
					{waiting ? `Check your phone, approve KES ${fee}` : `Pay KES ${fee}`}
				</Button>
			</AlertDescription>
		</Alert>
	)
}
