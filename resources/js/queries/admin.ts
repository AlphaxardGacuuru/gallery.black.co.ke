import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query"
import Axios from "@/lib/axios"
import type { UserSettings } from "@/types/auth"

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
	settings: UserSettings | null
	pushSubscriptionsCount: number
	/** Who this user is credited to as a referral, if anyone. */
	referredBy: { id: string; name: string } | null
	createdAt: string
}

type AdminUsersResponse = {
	data: AdminUser[]
	meta: { current_page: number; last_page: number; total: number }
}

export function useAdminUsers(
	search: string,
	page = 1,
	perPage = 20,
	enabled = true
) {
	return useQuery({
		enabled,
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

/**
 * Credit a user to a referrer by hand, for someone who signed up without a
 * referral link. Refreshes both the users table (its "Referred by" column)
 * and the referrals page's totals/leaderboard.
 */
export function useAttachReferrer() {
	const queryClient = useQueryClient()

	return useMutation({
		mutationFn: ({
			userId,
			referrerId,
		}: {
			userId: string
			referrerId: string
		}) => Axios.post(`api/admin/users/${userId}/referrer`, { referrerId }),
		onSuccess: () => {
			queryClient.invalidateQueries({ queryKey: ["admin", "users"] })
			queryClient.invalidateQueries({ queryKey: ["admin", "referrals"] })
		},
	})
}

export type PhotoCompetitionSchedule = {
	startDay: number
	startTime: string
	endDay: number
	endTime: string
}

export type AdminPhotoCompetitionWinner = {
	id: string
	position: number
	userName: string | null
	userPhone: string | null
	prizeAmount: number
	prizePaidAt: string | null
	kopokopoReference: string | null
}

export type AdminPhotoCompetitionSummary = {
	id: string
	startsAt: string
	endsAt: string
	status: string
	photosCount: number
	winners: AdminPhotoCompetitionWinner[]
}

export type AdminExtraSlotSettings = {
	enabled: boolean
	price: number
}

export type AdminPhotoCompetitionsData = {
	current: {
		id: string
		endsAt: string
		photosCount: number
	} | null
	totals: {
		totalCompetitions: number
		totalPhotos: number
		totalLikes: number
	}
	prizeTiers: number[]
	schedule: PhotoCompetitionSchedule
	extraSlot: AdminExtraSlotSettings
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

export type AdminPhotoSlotPurchase = {
	id: string
	userName: string | null
	userPhone: string | null
	competitionStartsAt: string | null
	amount: number
	status: "pending" | "paid" | "failed"
	createdAt: string
	paidAt: string | null
}

type AdminPhotoSlotPurchasesResponse = {
	data: AdminPhotoSlotPurchase[]
	meta: { current_page: number; last_page: number; total: number }
}

export function useAdminPhotoSlotPurchases(page = 1, perPage = 10) {
	return useQuery({
		queryKey: ["admin", "photo-slot-purchases", page, perPage],
		queryFn: () =>
			Axios.get<AdminPhotoSlotPurchasesResponse>(
				"api/admin/photo-slot-purchases",
				{ params: { page, per_page: perPage } }
			).then((res) => res.data),
	})
}

export function usePayCompetitionWinner() {
	const queryClient = useQueryClient()

	return useMutation({
		// Same status-in-body convention as useSendKopokopoTransfer.
		mutationFn: (winnerId: string) =>
			Axios.post<{ status: unknown; message: string }>(
				`api/admin/photo-competition-winners/${winnerId}/pay`
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

export function useUpdatePrizeTiers() {
	const queryClient = useQueryClient()

	return useMutation({
		mutationFn: (prizeTiers: number[]) =>
			Axios.put("api/admin/photo-competitions/prize-tiers", { prizeTiers }),
		onSuccess: () => {
			queryClient.invalidateQueries({
				queryKey: ["admin", "photo-competitions"],
			})
		},
	})
}

export function useUpdateExtraSlot() {
	const queryClient = useQueryClient()

	return useMutation({
		mutationFn: (payload: AdminExtraSlotSettings) =>
			Axios.put("api/admin/photo-competitions/extra-slot", payload),
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
		mutationFn: (payload: { endsAt: string }) =>
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
	status: string | null
	errors: unknown
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
	phone: string | null
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

export type AdminPhotoLiker = {
	id: string
	name: string
	avatar: string | null
	likesPhoto: boolean
}

export function useAdminPhotoLikers(photoId: string, search: string) {
	return useQuery({
		queryKey: ["admin", "photo-likes", photoId, search],
		queryFn: () =>
			Axios.get<{ data: AdminPhotoLiker[] }>(
				`api/admin/photos/${photoId}/likes`,
				{ params: { name: search || undefined } }
			).then((res) => res.data.data),
	})
}

export function useToggleAdminPhotoLike(photoId: string) {
	const queryClient = useQueryClient()

	return useMutation({
		mutationFn: (userId: string) =>
			Axios.post<{
				data: { userId: string; liked: boolean; likesCount: number }
			}>(`api/admin/photos/${photoId}/likes/${userId}`).then(
				(res) => res.data.data
			),
		onSuccess: () => {
			queryClient.invalidateQueries({
				queryKey: ["admin", "photo-likes", photoId],
			})
			queryClient.invalidateQueries({ queryKey: ["photos", "current"] })
			queryClient.invalidateQueries({ queryKey: ["photos", "discover"] })
			queryClient.invalidateQueries({ queryKey: ["photos", "show", photoId] })
		},
	})
}
