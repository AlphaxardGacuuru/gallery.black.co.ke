import {
	type InfiniteData,
	useInfiniteQuery,
	useMutation,
	useQuery,
	useQueryClient,
} from "@tanstack/react-query"
import PhotoCompetitionController from "@/actions/App/Http/Controllers/PhotoCompetitionController"
import PhotoController from "@/actions/App/Http/Controllers/PhotoController"
import PhotoLikeController from "@/actions/App/Http/Controllers/PhotoLikeController"
import Axios from "@/lib/axios"
import type { Photo, PhotoCompetition } from "@/types/photo"

type CurrentCompetitionData = {
	competition: PhotoCompetition | null
	nextStartsAt: string | null
}

export function useCurrentCompetition() {
	return useQuery({
		queryKey: ["photos", "current"],
		queryFn: () =>
			Axios.get<{
				data: PhotoCompetition | null
				nextStartsAt: string | null
			}>(PhotoCompetitionController.current.url()).then(
				(res): CurrentCompetitionData => ({
					competition: res.data.data,
					nextStartsAt: res.data.nextStartsAt,
				})
			),
		// Likes and the countdown both move without any action from this
		// viewer, so keep the leaderboard fresh without requiring a reload.
		refetchInterval: 20_000,
	})
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

type DiscoverPage = {
	data: Photo[]
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

export function useSubmitPhoto() {
	const queryClient = useQueryClient()

	return useMutation({
		mutationFn: (payload: { temporaryUploadId: number; caption: string }) =>
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

			const previousCurrent = queryClient.getQueryData<CurrentCompetitionData>([
				"photos",
				"current",
			])
			const previousDiscover = queryClient.getQueryData<
				InfiniteData<DiscoverPage>
			>(["photos", "discover"])

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

			return { previousCurrent, previousDiscover }
		},
		onError: (_error, _photoId, context) => {
			if (context?.previousCurrent !== undefined) {
				queryClient.setQueryData(["photos", "current"], context.previousCurrent)
			}
			if (context?.previousDiscover !== undefined) {
				queryClient.setQueryData(
					["photos", "discover"],
					context.previousDiscover
				)
			}
		},
		onSettled: () => {
			queryClient.invalidateQueries({ queryKey: ["photos", "current"] })
			queryClient.invalidateQueries({ queryKey: ["photos", "discover"] })
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
		},
	})
}
