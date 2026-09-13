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
	avatar: string | null
	verified: boolean
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
