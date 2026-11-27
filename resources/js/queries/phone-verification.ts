import { useMutation, useQuery } from "@tanstack/react-query"
import PhoneVerificationController from "@/actions/App/Http/Controllers/PhoneVerificationController"
import Axios from "@/lib/axios"
import {
	type PaymentFailureReason,
	type PurchaseStatus,
	settledStatus,
} from "@/queries/photos"

/**
 * Send the verification fee's M-Pesa prompt to the user's new phone
 * number. Like the slot purchases, Kopokopo/M-Pesa errors come back in the
 * body's status/message and are turned into a rejected promise here.
 */
export function useStartPhoneVerification() {
	return useMutation({
		mutationFn: () =>
			Axios.post<{
				status: boolean
				message: string
				data: { verificationId: string }
			}>(PhoneVerificationController.store.url()).then((res) => {
				if (res.data.status !== true) {
					throw new Error(res.data.message)
				}

				return res.data.data
			}),
	})
}

export function usePollPhoneVerification(verificationId: string | null) {
	return useQuery({
		queryKey: ["phone-verifications", verificationId],
		queryFn: () =>
			Axios.get<{
				data: {
					status: PurchaseStatus | "expired"
					failureReason: PaymentFailureReason | null
				}
			}>(PhoneVerificationController.show.url(verificationId!)).then((res) => ({
				...settledStatus(res.data.data.status),
				failureReason: res.data.data.failureReason,
			})),
		enabled: !!verificationId,
		refetchInterval: (query) =>
			query.state.data?.status === "pending" ? 3000 : false,
	})
}
