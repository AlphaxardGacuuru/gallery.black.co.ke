import { useQuery } from "@tanstack/react-query"
import Axios from "@/lib/axios"

export type MyReferral = {
	id: string
	referredName: string | null
	createdAt: string
}

type MyReferralsResponse = {
	data: MyReferral[]
	meta: { current_page: number; last_page: number; total: number }
}

export function useMyReferrals(page = 1, perPage = 20) {
	return useQuery({
		queryKey: ["referrals", "mine", page, perPage],
		queryFn: () =>
			Axios.get<MyReferralsResponse>("api/referrals/mine", {
				params: { page, per_page: perPage },
			}).then((res) => res.data),
	})
}

export type ReferralSettings = {
	threshold: number
	rewardAmount: number
}

export function useReferralSettings() {
	return useQuery({
		queryKey: ["referrals", "settings"],
		queryFn: () =>
			Axios.get<{ data: ReferralSettings }>("api/referrals/settings").then(
				(res) => res.data.data
			),
	})
}
