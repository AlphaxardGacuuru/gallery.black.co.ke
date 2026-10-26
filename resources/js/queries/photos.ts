import {
	type InfiniteData,
	useInfiniteQuery,
	useMutation,
	useQuery,
	useQueryClient,
} from "@tanstack/react-query"
import PhotoCompetitionController from "@/actions/App/Http/Controllers/PhotoCompetitionController"
import PhotoController from "@/actions/App/Http/Controllers/PhotoController"
import ExtraPhotoPurchaseController from "@/actions/App/Http/Controllers/ExtraPhotoPurchaseController"
import PhotoLikeController from "@/actions/App/Http/Controllers/PhotoLikeController"
import PhotoPurchaseController from "@/actions/App/Http/Controllers/PhotoPurchaseController"
import PhotoSlotPurchaseController from "@/actions/App/Http/Controllers/PhotoSlotPurchaseController"
import Axios from "@/lib/axios"
import type { Photo, PhotoCompetition } from "@/types/photo"

export type ExtraSlotState = {
	enabled: boolean
	price: number
	hasPaidExtraSlot: boolean
	pendingPurchaseId: string | null
}

export type ExtraPhotoState = {
	enabled: boolean
	price: number
	paidPhotoSlotsCount: number
	pendingPurchaseId: string | null
	maxPhotos: number
	maxPhotosCap: number
}

type CurrentCompetitionData = {
	competition: PhotoCompetition | null
	nextStartsAt: string | null
	prizeTiers: number[]
	extraSlot: ExtraSlotState
	extraPhoto: ExtraPhotoState
}

export function useCurrentCompetition() {
	return useQuery({
		queryKey: ["photos", "current"],
		queryFn: () =>
			Axios.get<{
				data: PhotoCompetition | null
				nextStartsAt: string | null
				prizeTiers: number[]
				extraSlot: ExtraSlotState
				extraPhoto: ExtraPhotoState
			}>(PhotoCompetitionController.current.url()).then(
				(res): CurrentCompetitionData => ({
					competition: res.data.data,
					nextStartsAt: res.data.nextStartsAt,
					prizeTiers: res.data.prizeTiers,
					extraSlot: res.data.extraSlot,
					extraPhoto: res.data.extraPhoto,
				})
			),
		// Likes and the countdown both move without any action from this
		// viewer, so keep the leaderboard fresh without requiring a reload.
		refetchInterval: 20_000,
	})
}

/**
 * Whether the viewer has used every entry slot in the active competition
 * (one by default, two once they've paid for an extra slot). Shared by the
 * compete page's floating button and the create-post page so the two can't
 * disagree about whether another entry is allowed.
 */
export function hasReachedEntryLimit(
	data: CurrentCompetitionData | undefined,
	userId: string | number | undefined
): boolean {
	const competition = data?.competition

	if (competition?.status !== "active") {
		return false
	}

	const mySubmissionCount = competition.photos.filter(
		(photo) => String(photo.userId) === String(userId)
	).length
	const allowedSlots = data?.extraSlot?.hasPaidExtraSlot ? 2 : 1

	return mySubmissionCount >= allowedSlots
}

// Both the mutation's optimistic update and its rollback need to reach into
// the same current-competition cache entry, which since useCurrentCompetition
// wraps the raw API response now holds { competition, nextStartsAt } rather
// than the competition itself.
function updateCachedPhotos(
	queryClient: ReturnType<typeof useQueryClient>,
	updater: (photos: Photo[]) => Photo[]
) {
	queryClient.setQueryData<CurrentCompetitionData>(
		["photos", "current"],
		(current) =>
			current?.competition
				? {
						...current,
						competition: {
							...current.competition,
							photos: updater(current.competition.photos),
						},
					}
				: current
	)
}

// The discover grid (an infinite query) needs the same per-photo update
// applied to every already-fetched page, since a liked photo could be on
// any of them.
function updateCachedDiscoverPhotos(
	queryClient: ReturnType<typeof useQueryClient>,
	updater: (photos: Photo[]) => Photo[]
) {
	queryClient.setQueryData<InfiniteData<DiscoverPage>>(
		["photos", "discover"],
		(current) =>
			current
				? {
						...current,
						pages: current.pages.map((page) => ({
							...page,
							data: updater(page.data),
						})),
					}
				: current
	)
}

/** Whether photos can be bought from Discover, and the KES price. */
export type PhotoSale = {
	enabled: boolean
	price: number
}

export type PhotoDetail = {
	photo: Photo
	canDelete: boolean
	canLike: boolean
	sale: PhotoSale
}

// The single-photo detail page keeps its own cache entry (keyed by id)
// alongside the list-shaped ones above, so a like applied there needs this
// same per-photo update too.
function updateCachedShowPhoto(
	queryClient: ReturnType<typeof useQueryClient>,
	photoId: string,
	updater: (photos: Photo[]) => Photo[]
) {
	queryClient.setQueryData<PhotoDetail>(
		["photos", "show", photoId],
		(current) =>
			current ? { ...current, photo: updater([current.photo])[0] } : current
	)
}

type DiscoverPage = {
	data: Photo[]
	sale: PhotoSale
	meta: { current_page: number; last_page: number }
}

export function useDiscoverPhotos() {
	return useInfiniteQuery({
		queryKey: ["photos", "discover"],
		queryFn: ({ pageParam }) =>
			Axios.get<DiscoverPage>(PhotoCompetitionController.discover.url(), {
				params: { page: pageParam },
			}).then((res) => res.data),
		initialPageParam: 1,
		getNextPageParam: (lastPage) =>
			lastPage.meta.current_page < lastPage.meta.last_page
				? lastPage.meta.current_page + 1
				: undefined,
	})
}

export type LeaderboardEntry = {
	rank: number
	userId: string
	userName: string
	userAvatar: string | null
	userVerified: boolean
	photosCount: number
	/** How many times the user finished at each position, index 0 = 1st place. */
	placements: number[]
}

type LeaderboardPage = {
	data: LeaderboardEntry[]
	meta: { current_page: number; last_page: number }
}

export function useLeaderboard() {
	return useInfiniteQuery({
		queryKey: ["leaderboard"],
		queryFn: ({ pageParam }) =>
			Axios.get<LeaderboardPage>(PhotoCompetitionController.leaderboard.url(), {
				params: { page: pageParam },
			}).then((res) => res.data),
		initialPageParam: 1,
		getNextPageParam: (lastPage) =>
			lastPage.meta.current_page < lastPage.meta.last_page
				? lastPage.meta.current_page + 1
				: undefined,
	})
}

export function usePhoto(id: string) {
	return useQuery({
		queryKey: ["photos", "show", id],
		queryFn: () =>
			Axios.get<{
				data: Photo
				canDelete: boolean
				canLike: boolean
				sale: PhotoSale
			}>(PhotoController.show.url(id)).then(
				(res): PhotoDetail => ({
					photo: res.data.data,
					canDelete: res.data.canDelete,
					canLike: res.data.canLike,
					sale: res.data.sale,
				})
			),
	})
}

export function useSubmitPhoto() {
	const queryClient = useQueryClient()

	return useMutation({
		mutationFn: (payload: { temporaryUploadIds: number[]; caption: string }) =>
			Axios.post<{ data: Photo }>(PhotoController.store.url(), payload).then(
				(res) => res.data.data
			),
		onSuccess: (photo) => {
			// Show the new submission immediately instead of waiting on the
			// background refetch below to land.
			updateCachedPhotos(queryClient, (photos) => [...photos, photo])

			queryClient.invalidateQueries({ queryKey: ["photos", "current"] })
		},
	})
}

/**
 * Add more photos to an already-submitted entry (e.g. after buying an
 * extra slot without wanting to delete and resubmit it).
 */
export function useAddPhotos(photoId: string) {
	const queryClient = useQueryClient()

	return useMutation({
		mutationFn: (temporaryUploadIds: number[]) =>
			Axios.post<{ data: Photo }>(PhotoController.storeImages.url(photoId), {
				temporaryUploadIds,
			}).then((res) => res.data.data),
		onSuccess: (photo) => {
			updateCachedPhotos(queryClient, (photos) =>
				photos.map((existing) => (existing.id === photo.id ? photo : existing))
			)
			queryClient.setQueryData<PhotoDetail>(
				["photos", "show", photoId],
				(current) => (current ? { ...current, photo } : current)
			)
			queryClient.invalidateQueries({ queryKey: ["photos", "current"] })
		},
	})
}

export function useLikePhoto() {
	const queryClient = useQueryClient()

	return useMutation({
		mutationFn: (photoId: string) =>
			Axios.post<{
				data: { photoId: string; likesCount: number; isLikedByViewer: boolean }
			}>(PhotoLikeController.store.url(photoId)).then((res) => res.data.data),
		onMutate: async (photoId) => {
			await queryClient.cancelQueries({ queryKey: ["photos", "current"] })
			await queryClient.cancelQueries({ queryKey: ["photos", "discover"] })
			await queryClient.cancelQueries({ queryKey: ["photos", "show", photoId] })

			const previousCurrent = queryClient.getQueryData<CurrentCompetitionData>([
				"photos",
				"current",
			])
			const previousDiscover = queryClient.getQueryData<
				InfiniteData<DiscoverPage>
			>(["photos", "discover"])
			const previousShow = queryClient.getQueryData<PhotoDetail>([
				"photos",
				"show",
				photoId,
			])

			const applyLike = (photos: Photo[]) =>
				photos.map((photo) =>
					photo.id === photoId && !photo.isLikedByViewer
						? {
								...photo,
								isLikedByViewer: true,
								likesCount: photo.likesCount + 1,
							}
						: photo
				)

			updateCachedPhotos(queryClient, applyLike)
			updateCachedDiscoverPhotos(queryClient, applyLike)
			updateCachedShowPhoto(queryClient, photoId, applyLike)

			return { previousCurrent, previousDiscover, previousShow }
		},
		onError: (_error, photoId, context) => {
			if (context?.previousCurrent !== undefined) {
				queryClient.setQueryData(["photos", "current"], context.previousCurrent)
			}
			if (context?.previousDiscover !== undefined) {
				queryClient.setQueryData(
					["photos", "discover"],
					context.previousDiscover
				)
			}
			if (context?.previousShow !== undefined) {
				queryClient.setQueryData(
					["photos", "show", photoId],
					context.previousShow
				)
			}
		},
		onSettled: (_data, _error, photoId) => {
			queryClient.invalidateQueries({ queryKey: ["photos", "current"] })
			queryClient.invalidateQueries({ queryKey: ["photos", "discover"] })
			queryClient.invalidateQueries({ queryKey: ["photos", "show", photoId] })
		},
	})
}

export function useDeletePhoto() {
	const queryClient = useQueryClient()

	return useMutation({
		mutationFn: (photoId: string) =>
			Axios.delete(PhotoController.destroy.url(photoId)),
		onSuccess: (_response, photoId) => {
			updateCachedPhotos(queryClient, (photos) =>
				photos.filter((photo) => photo.id !== photoId)
			)

			queryClient.invalidateQueries({ queryKey: ["photos", "current"] })
			queryClient.removeQueries({ queryKey: ["photos", "show", photoId] })
		},
	})
}

export function useBuyExtraSlot() {
	const queryClient = useQueryClient()

	return useMutation({
		// Same status-in-body convention as the admin payout mutations.
		// Kopokopo/M-Pesa errors surface in the body's status/message, not
		// the HTTP status, so a non-success body is turned into a rejected
		// promise here. Guard-clause failures (no active competition, etc.)
		// come back as normal 422s instead and reject on their own.
		mutationFn: () =>
			Axios.post<{
				status: boolean
				message: string
				data: { purchaseId: string }
			}>(PhotoSlotPurchaseController.store.url()).then((res) => {
				if (res.data.status !== true) {
					throw new Error(res.data.message)
				}

				return res.data.data
			}),
		onSuccess: () => {
			queryClient.invalidateQueries({ queryKey: ["photos", "current"] })
		},
	})
}

/**
 * Buy one additional photo slot (see Photo::maxImagesFor()), unlike
 * useBuyExtraSlot(), this is repeatable: each call buys one more slot, up
 * to extraPhoto.maxPhotosCap. Confirmation is polled the same way via
 * usePollSlotPurchase(), which is generic by purchase id regardless of type.
 */
export function useBuyExtraPhotoSlot() {
	const queryClient = useQueryClient()

	return useMutation({
		mutationFn: () =>
			Axios.post<{
				status: boolean
				message: string
				data: { purchaseId: string }
			}>(ExtraPhotoPurchaseController.store.url()).then((res) => {
				if (res.data.status !== true) {
					throw new Error(res.data.message)
				}

				return res.data.data
			}),
		onSuccess: () => {
			queryClient.invalidateQueries({ queryKey: ["photos", "current"] })
		},
	})
}

export function usePollSlotPurchase(purchaseId: string | null) {
	return useQuery({
		queryKey: ["photo-slot-purchases", purchaseId],
		queryFn: () =>
			Axios.get<{ data: { status: "pending" | "paid" | "failed" } }>(
				PhotoSlotPurchaseController.show.url(purchaseId!)
			).then((res) => res.data.data),
		enabled: !!purchaseId,
		refetchInterval: (query) =>
			query.state.data?.status === "pending" ? 3000 : false,
	})
}

/**
 * Start an STK push to buy a full-resolution copy of a photo. Like the
 * slot purchases, the payment is confirmed async by Kopokopo's webhook, so
 * callers follow up with usePollPhotoPurchase().
 */
export function useBuyPhoto(photoId: string) {
	return useMutation({
		mutationFn: () =>
			Axios.post<{
				status: boolean
				message: string
				data: { purchaseId: string }
			}>(PhotoPurchaseController.store.url(photoId)).then((res) => {
				if (res.data.status !== true) {
					throw new Error(res.data.message)
				}

				return res.data.data
			}),
	})
}

export function usePollPhotoPurchase(purchaseId: string | null) {
	return useQuery({
		queryKey: ["photo-purchases", purchaseId],
		queryFn: () =>
			Axios.get<{ data: { status: "pending" | "paid" | "failed" } }>(
				PhotoPurchaseController.show.url(purchaseId!)
			).then((res) => res.data.data),
		enabled: !!purchaseId,
		refetchInterval: (query) =>
			query.state.data?.status === "pending" ? 3000 : false,
	})
}

export type PhotoDownload = {
	url: string
	thumbnailUrl: string
}

/**
 * Signed download links for each original in an entry. They expire after
 * 30 minutes, so they're fetched fresh whenever the download view opens
 * rather than kept in the cache.
 */
export function usePhotoDownloads(photoId: string, enabled: boolean) {
	return useQuery({
		queryKey: ["photos", "downloads", photoId],
		queryFn: () =>
			Axios.get<{ data: PhotoDownload[] }>(
				PhotoPurchaseController.downloads.url(photoId)
			).then((res) => res.data.data),
		enabled,
		staleTime: 0,
		gcTime: 0,
	})
}

/**
 * Once a purchase is confirmed, flag the photo as owned in every cached
 * view of it so Discover and the photo page both swap "Buy" for "Download"
 * without a refetch.
 */
export function markPhotoPurchased(
	queryClient: ReturnType<typeof useQueryClient>,
	photoId: string
) {
	const markOwned = (photos: Photo[]) =>
		photos.map((photo) =>
			photo.id === photoId ? { ...photo, isPurchasedByViewer: true } : photo
		)

	updateCachedDiscoverPhotos(queryClient, markOwned)
	updateCachedShowPhoto(queryClient, photoId, markOwned)
}
