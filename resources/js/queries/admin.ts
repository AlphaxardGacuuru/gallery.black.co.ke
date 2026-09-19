import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query"
import Axios from "@/lib/axios"

export type AdminDashboardData = {
	totals: {
		totalUsers: number
		totalCompetitions: number
		totalPhotos: number
		totalLikes: number
	}
	dailyVolume: { date: string; submitted: number }[]
}

export function useAdminDashboard() {
	return useQuery({
		queryKey: ["admin", "dashboard"],
		queryFn: () =>
			Axios.get<{ data: AdminDashboardData }>("api/admin/dashboard").then(
				(res) => res.data.data
			),
	})
}

export type AdminUser = {
	id: string
	name: string
	email: string
	phone: string | null
	gender: "male" | "female" | "other" | null
	avatar: string | null
	verified: boolean
	createdAt: string
}

type AdminUsersResponse = {
	data: AdminUser[]
	meta: { current_page: number; last_page: number; total: number }
}

export function useAdminUsers(search: string, page = 1, perPage = 20) {
	return useQuery({
		queryKey: ["admin", "users", search, page, perPage],
		queryFn: () =>
			Axios.get<AdminUsersResponse>("api/admin/users", {
				params: { name: search || undefined, page, per_page: perPage },
			}).then((res) => res.data),
	})
}

export function useToggleUserVerified() {
	const queryClient = useQueryClient()

	return useMutation({
		mutationFn: ({ userId, verified }: { userId: string; verified: boolean }) =>
			Axios.patch(`api/admin/users/${userId}/verify`, { verified }),
		onSuccess: () => {
			queryClient.invalidateQueries({ queryKey: ["admin", "users"] })
		},
	})
}

export type PhotoCompetitionSchedule = {
	startDay: number
	startTime: string
	endDay: number
	endTime: string
}

export type AdminPhotoCompetitionSummary = {
	id: string
	startsAt: string
	endsAt: string
	status: string
	prizeAmount: number
	photosCount: number
	winnerName: string | null
	winnerPhone: string | null
	prizePaidAt: string | null
}

export type AdminPhotoCompetitionsData = {
	current: {
		id: string
		endsAt: string
		prizeAmount: number
		photosCount: number
	} | null
	totals: {
		totalCompetitions: number
		totalPhotos: number
		totalLikes: number
	}
	prizeAmount: number
	schedule: PhotoCompetitionSchedule
}

export function useAdminPhotoCompetitions() {
	return useQuery({
		queryKey: ["admin", "photo-competitions"],
		queryFn: () =>
			Axios.get<{ data: AdminPhotoCompetitionsData }>(
				"api/admin/photo-competitions"
			).then((res) => res.data.data),
	})
}

type AdminRecentPhotoCompetitionsResponse = {
	data: AdminPhotoCompetitionSummary[]
	meta: { current_page: number; last_page: number; total: number }
}

export function useAdminRecentPhotoCompetitions(page = 1, perPage = 10) {
	return useQuery({
		queryKey: ["admin", "photo-competitions", "recent", page, perPage],
		queryFn: () =>
			Axios.get<AdminRecentPhotoCompetitionsResponse>(
				"api/admin/photo-competitions/recent",
				{ params: { page, per_page: perPage } }
			).then((res) => res.data),
	})
}

export function usePayCompetitionWinner() {
	const queryClient = useQueryClient()

	return useMutation({
		// Same status-in-body convention as useSendKopokopoTransfer.
		mutationFn: (competitionId: string) =>
			Axios.post<{ status: unknown; message: string }>(
				`api/admin/photo-competitions/${competitionId}/pay-winner`
			).then((res) => {
				if (res.data.status !== true) {
					throw new Error(res.data.message)
				}

				return res.data
			}),
		onSuccess: () => {
			queryClient.invalidateQueries({
				queryKey: ["admin", "photo-competitions"],
			})
		},
	})
}

export function useUpdatePrizeAmount() {
	const queryClient = useQueryClient()

	return useMutation({
		mutationFn: (prizeAmount: number) =>
			Axios.put("api/admin/photo-competitions/prize-amount", { prizeAmount }),
		onSuccess: () => {
			queryClient.invalidateQueries({
				queryKey: ["admin", "photo-competitions"],
			})
		},
	})
}

export function useUpdatePhotoCompetitionSchedule() {
	const queryClient = useQueryClient()

	return useMutation({
		mutationFn: (schedule: PhotoCompetitionSchedule) =>
			Axios.put("api/admin/photo-competitions/schedule", schedule),
		onSuccess: () => {
			queryClient.invalidateQueries({
				queryKey: ["admin", "photo-competitions"],
			})
		},
	})
}

export function useUpdateActiveCompetition() {
	const queryClient = useQueryClient()

	return useMutation({
		mutationFn: (payload: { prizeAmount: number; endsAt: string }) =>
			Axios.put("api/admin/photo-competitions/active", payload),
		onSuccess: () => {
			queryClient.invalidateQueries({
				queryKey: ["admin", "photo-competitions"],
			})
		},
	})
}

export type AdminKopokopoTransfer = {
	id: string
	user: string | null
	kopokopoId: string | null
	kopokopoCreatedAt: string | null
	amount: string
	currency: string | null
	transferBatches: unknown
	metadata: unknown
	createdAt: string
}

type AdminKopokopoTransfersResponse = {
	data: AdminKopokopoTransfer[]
	meta: { current_page: number; last_page: number; total: number }
}

export function useAdminKopokopoTransfers(page = 1, perPage = 20) {
	return useQuery({
		queryKey: ["admin", "kopokopo-transfers", page, perPage],
		queryFn: () =>
			Axios.get<AdminKopokopoTransfersResponse>(
				"api/admin/kopokopo-transfers",
				{ params: { page, per_page: perPage } }
			).then((res) => res.data),
	})
}

export function useSendKopokopoTransfer() {
	const queryClient = useQueryClient()

	return useMutation({
		// The endpoint always responds 200, even on failure — Kopokopo/M-Pesa
		// errors surface in the body's `status`/`message`, not the HTTP
		// status, so a non-success body is turned into a rejected promise
		// here to fit react-query's normal onSuccess/onError handling.
		mutationFn: (payload: {
			recipientName: string
			destinationReference: string
			amount: number
			description?: string
		}) =>
			Axios.post<{ status: unknown; message: string }>(
				"api/admin/kopokopo-transfers/initiate",
				payload
			).then((res) => {
				if (res.data.status !== true) {
					throw new Error(res.data.message)
				}

				return res.data
			}),
		onSuccess: () => {
			queryClient.invalidateQueries({
				queryKey: ["admin", "kopokopo-transfers"],
			})
		},
	})
}

export type KopokopoRecipientType =
	| "mobile_wallet"
	| "bank_account"
	| "till"
	| "paybill"

export type AdminKopokopoRecipient = {
	id: string
	type: KopokopoRecipientType
	firstName: string | null
	lastName: string | null
	email: string | null
	phoneNumber: string | null
	accountName: string | null
	accountNumber: string | null
	tillName: string | null
	tillNumber: string | null
	paybillName: string | null
	paybillNumber: string | null
	paybillAccountNumber: string | null
	description: string | null
}

export function useAdminKopokopoRecipients() {
	return useQuery({
		queryKey: ["admin", "kopokopo-recipients"],
		queryFn: () =>
			Axios.get<{ data: AdminKopokopoRecipient[] }>(
				"api/admin/kopokopo-recipients"
			).then((res) => res.data.data),
	})
}

export type AddKopokopoRecipientPayload = {
	type: KopokopoRecipientType
	description: string
	firstName?: string
	lastName?: string
	email?: string
	phoneNumber?: string
	accountName?: string
	accountNumber?: string
	bankBranchRef?: string
	tillName?: string
	tillNumber?: string
	paybillName?: string
	paybillNumber?: string
	paybillAccountNumber?: string
}

export function useAddKopokopoRecipient() {
	const queryClient = useQueryClient()

	return useMutation({
		// Same status-in-body convention as useSendKopokopoTransfer above.
		mutationFn: (payload: AddKopokopoRecipientPayload) =>
			Axios.post<{ status: unknown; message: string }>(
				"api/admin/kopokopo-recipients",
				payload
			).then((res) => {
				if (res.data.status !== true) {
					throw new Error(res.data.message)
				}

				return res.data
			}),
		onSuccess: () => {
			queryClient.invalidateQueries({
				queryKey: ["admin", "kopokopo-recipients"],
			})
		},
	})
}

export type AdminReferralLeaderboardEntry = {
	userId: string
	name: string
	avatar: string | null
	referralsCount: number
	eligibleAmount: number
}

export type AdminReferralsData = {
	totalReferrals: number
	totalReferrers: number
	threshold: number
	rewardAmount: number
	leaderboard: AdminReferralLeaderboardEntry[]
}

export function useAdminReferrals() {
	return useQuery({
		queryKey: ["admin", "referrals"],
		queryFn: () =>
			Axios.get<{ data: AdminReferralsData }>("api/admin/referrals").then(
				(res) => res.data.data
			),
	})
}

export type AdminReferral = {
	id: string
	referrerName: string | null
	referredName: string | null
	createdAt: string
	amountPaid: number | null
	paidAt: string | null
}

type AdminRecentReferralsResponse = {
	data: AdminReferral[]
	meta: { current_page: number; last_page: number; total: number }
}

export function useAdminRecentReferrals(page = 1, perPage = 20) {
	return useQuery({
		queryKey: ["admin", "referrals", "recent", page, perPage],
		queryFn: () =>
			Axios.get<AdminRecentReferralsResponse>("api/admin/referrals/recent", {
				params: { page, per_page: perPage },
			}).then((res) => res.data),
	})
}

export function useUpdateReferralSettings() {
	const queryClient = useQueryClient()

	return useMutation({
		mutationFn: (payload: { threshold: number; rewardAmount: number }) =>
			Axios.put("api/admin/referrals/settings", payload),
		onSuccess: () => {
			queryClient.invalidateQueries({ queryKey: ["admin", "referrals"] })
		},
	})
}

export function usePayReferrer() {
	const queryClient = useQueryClient()

	return useMutation({
		// Same status-in-body convention as useSendKopokopoTransfer.
		mutationFn: (userId: string) =>
			Axios.post<{ status: unknown; message: string }>(
				`api/admin/referrals/${userId}/pay`
			).then((res) => {
				if (res.data.status !== true) {
					throw new Error(res.data.message)
				}

				return res.data
			}),
		onSuccess: () => {
			queryClient.invalidateQueries({ queryKey: ["admin", "referrals"] })
		},
	})
}
