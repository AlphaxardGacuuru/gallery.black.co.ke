import {
	type InfiniteData,
	useInfiniteQuery,
	useMutation,
	useQuery,
	useQueryClient,
} from "@tanstack/react-query"
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
	finance: {
		revenue: number
		payouts: number
		net: number
		revenueBreakdown: {
			extraEntries: number
			extraPhotos: number
			photoSales: number
		}
		payoutsBreakdown: {
			prizes: number
			referralRewards: number
			photographerPayouts: number
			otherTransfers: number
		}
	}
	referrals: {
		totalReferrals: number
		totalReferrers: number
		totalPaidOut: number
		totalPendingPayout: number
	}
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
	isSeed: boolean
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
	/** What the Kopokopo wallet needs for the prizes still to be paid. */
	payoutTotals: {
		payouts: number
		prizes: number
		/** Kopokopo's per-transfer fee, times payouts. */
		fees: number
		total: number
	}
}

export type AdminExtraSlotSettings = {
	enabled: boolean
	price: number
}

export type AdminExtraPhotoSettings = {
	enabled: boolean
	price: number
}

export type AdminPhotoSaleSettings = {
	enabled: boolean
	price: number
	/** Percentage of each sale paid on to the photographer. */
	photographerShare: number
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
		totalSlotPurchases: number
	}
	prizeTiers: number[]
	schedule: PhotoCompetitionSchedule
	extraSlot: AdminExtraSlotSettings
	extraPhoto: AdminExtraPhotoSettings
	photoSale: AdminPhotoSaleSettings
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
	type: "extra_entry" | "extra_photo"
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

/** STK pushes the admin's wallet number for a competition's payout total. */
export function useLoadKopokopoWallet() {
	return useMutation({
		// Same status-in-body convention as usePayCompetitionWinner.
		mutationFn: (competitionId: string) =>
			Axios.post<{ status: unknown; message: string }>(
				`api/admin/photo-competitions/${competitionId}/load-wallet`
			).then((res) => {
				if (res.data.status !== true) {
					throw new Error(res.data.message)
				}

				return res.data
			}),
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

export function useUpdateExtraPhoto() {
	const queryClient = useQueryClient()

	return useMutation({
		mutationFn: (payload: AdminExtraPhotoSettings) =>
			Axios.put("api/admin/photo-competitions/extra-photo", payload),
		onSuccess: () => {
			queryClient.invalidateQueries({
				queryKey: ["admin", "photo-competitions"],
			})
		},
	})
}

export function useUpdatePhotoSale() {
	const queryClient = useQueryClient()

	return useMutation({
		mutationFn: (payload: AdminPhotoSaleSettings) =>
			Axios.put("api/admin/photo-purchases/settings", payload),
		onSuccess: () => {
			queryClient.invalidateQueries({
				queryKey: ["admin", "photo-competitions"],
			})
		},
	})
}

export type AdminPhotoPurchase = {
	id: string
	photoId: string
	photoThumbnailUrl: string | null
	buyerName: string | null
	buyerPhone: string | null
	sellerName: string | null
	sellerPhone: string | null
	amount: number
	sellerAmount: number
	status: "pending" | "paid" | "failed"
	createdAt: string
	paidAt: string | null
	payoutReference: string | null
	payoutPaidAt: string | null
}

type AdminPhotoPurchasesResponse = {
	data: AdminPhotoPurchase[]
	meta: { current_page: number; last_page: number; total: number }
}

export function useAdminPhotoPurchases(page = 1, perPage = 10) {
	return useQuery({
		queryKey: ["admin", "photo-purchases", page, perPage],
		queryFn: () =>
			Axios.get<AdminPhotoPurchasesResponse>("api/admin/photo-purchases", {
				params: { page, per_page: perPage },
			}).then((res) => res.data),
	})
}

export function usePayPhotographer() {
	const queryClient = useQueryClient()

	return useMutation({
		// Same status-in-body convention as usePayCompetitionWinner.
		mutationFn: (purchaseId: string) =>
			Axios.post<{ status: unknown; message: string }>(
				`api/admin/photo-purchases/${purchaseId}/pay`
			).then((res) => {
				if (res.data.status !== true) {
					throw new Error(res.data.message)
				}

				return res.data
			}),
		onSuccess: () => {
			queryClient.invalidateQueries({ queryKey: ["admin", "photo-purchases"] })
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
	recipientName: string | null
	recipientPhone: string | null
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
	verified: boolean
	phone: string | null
	referralsCount: number
	eligibleAmount: number
}

export type AdminReferralsData = {
	totalReferrals: number
	totalReferrers: number
	totalPaidOut: number
	totalPendingPayout: number
	threshold: number
	rewardAmount: number
	/** One-off KES bonus for each referred user who posts a photo. */
	photoBonusAmount: number
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
	referredHasPosted: boolean
	photoBonusAmount: number | null
	photoBonusPaidAt: string | null
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
		mutationFn: (payload: {
			threshold: number
			rewardAmount: number
			photoBonusAmount: number
		}) => Axios.put("api/admin/referrals/settings", payload),
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
	verified: boolean
	isSeed: boolean
	likesPhoto: boolean
}

type AdminPhotoLikersPage = {
	data: AdminPhotoLiker[]
	meta: { current_page: number; last_page: number; total: number }
}

// Only mounted while the dialog is open, and gcTime 0 drops the pages as
// soon as it closes, so every open starts from page 1 with fresh like state
// instead of flashing whatever was cached from the last time.
export function useAdminPhotoLikers(photoId: string, search: string) {
	return useInfiniteQuery({
		queryKey: ["admin", "photo-likes", photoId, search],
		queryFn: ({ pageParam }) =>
			Axios.get<AdminPhotoLikersPage>(`api/admin/photos/${photoId}/likes`, {
				params: { name: search || undefined, page: pageParam },
			}).then((res) => res.data),
		initialPageParam: 1,
		getNextPageParam: (lastPage) =>
			lastPage.meta.current_page < lastPage.meta.last_page
				? lastPage.meta.current_page + 1
				: undefined,
		gcTime: 0,
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
		onSuccess: ({ userId, liked }) => {
			// Patch the one row in place rather than refetching every loaded
			// page, which would reset the list under the admin's scroll.
			queryClient.setQueriesData<InfiniteData<AdminPhotoLikersPage>>(
				{ queryKey: ["admin", "photo-likes", photoId] },
				(current) =>
					current && {
						...current,
						pages: current.pages.map((page) => ({
							...page,
							data: page.data.map((user) =>
								user.id === userId ? { ...user, likesPhoto: liked } : user
							),
						})),
					}
			)
			queryClient.invalidateQueries({ queryKey: ["photos", "current"] })
			queryClient.invalidateQueries({ queryKey: ["photos", "discover"] })
			queryClient.invalidateQueries({ queryKey: ["photos", "show", photoId] })
		},
	})
}

export type AdminNoticeAudience =
	| "all"
	| "never_referred"
	| "not_entered_active_competition"

export type AdminNoticeInput = {
	key: string
	title: string
	body: string
	icon: string
	primaryLabel: string
	primaryUrl: string | null
	secondaryLabel: string | null
	audience: AdminNoticeAudience
	priority: number
	cooldownHours: number
	maxImpressions: number | null
	stopAfterClick: boolean
	isActive: boolean
	startsAt: string | null
	endsAt: string | null
}

export type AdminNotice = AdminNoticeInput & {
	id: string
	stats: {
		shown: number
		uniqueViewers: number
		primaryClicks: number
		secondaryClicks: number
		dismissed: number
	}
}

export type AdminNoticeLimits = {
	dailyLimit: number
	minGapHours: number
}

export type AdminNoticeVariable = {
	name: string
	description: string
	/** The value it would render as right now, for the admin. */
	example: string | null
}

export type AdminNoticesData = {
	notices: AdminNotice[]
	limits: AdminNoticeLimits
	variables: AdminNoticeVariable[]
	audiences: AdminNoticeAudience[]
	icons: string[]
}

export function useAdminNotices() {
	return useQuery({
		queryKey: ["admin", "notices"],
		queryFn: () =>
			Axios.get<{ data: AdminNoticesData }>("api/admin/notices").then(
				(res) => res.data.data
			),
	})
}

export function useSaveNotice() {
	const queryClient = useQueryClient()

	return useMutation({
		mutationFn: ({ id, ...payload }: AdminNoticeInput & { id?: string }) =>
			id
				? Axios.put(`api/admin/notices/${id}`, payload)
				: Axios.post("api/admin/notices", payload),
		onSuccess: () => {
			queryClient.invalidateQueries({ queryKey: ["admin", "notices"] })
		},
	})
}

export function useDeleteNotice() {
	const queryClient = useQueryClient()

	return useMutation({
		mutationFn: (id: string) => Axios.delete(`api/admin/notices/${id}`),
		onSuccess: () => {
			queryClient.invalidateQueries({ queryKey: ["admin", "notices"] })
		},
	})
}

export function useUpdateNoticeLimits() {
	const queryClient = useQueryClient()

	return useMutation({
		mutationFn: (payload: AdminNoticeLimits) =>
			Axios.put("api/admin/notices/limits", payload),
		onSuccess: () => {
			queryClient.invalidateQueries({ queryKey: ["admin", "notices"] })
		},
	})
}

export type AdminNoticeEvent = {
	id: string
	noticeKey: string | null
	userName: string | null
	userPhone: string | null
	version: string
	action: "shown" | "primary_click" | "secondary_click" | "dismissed"
	createdAt: string
}

type AdminNoticeEventsResponse = {
	data: AdminNoticeEvent[]
	meta: { current_page: number; last_page: number; total: number }
}

export function useAdminNoticeEvents(
	page = 1,
	perPage = 20,
	filters: { noticeId?: string; action?: string } = {}
) {
	return useQuery({
		queryKey: ["admin", "notices", "events", page, perPage, filters],
		queryFn: () =>
			Axios.get<AdminNoticeEventsResponse>("api/admin/notices/events", {
				params: {
					page,
					per_page: perPage,
					notice_id: filters.noticeId || undefined,
					action: filters.action || undefined,
				},
			}).then((res) => res.data),
	})
}
