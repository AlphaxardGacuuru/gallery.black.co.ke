import {
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

export function useCurrentCompetition() {
	return useQuery({
		queryKey: ["photos", "current"],
		queryFn: () =>
			Axios.get<{ data: PhotoCompetition | null }>(
				PhotoCompetitionController.current.url()
			).then((res) => res.data.data),
		// Likes and the countdown both move without any action from this
		// viewer, so keep the leaderboard fresh without requiring a reload.
		refetchInterval: 20_000,
	})
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
		mutationFn: (payload: { temporaryUploadId: number; caption?: string }) =>
			Axios.post<{ data: Photo }>(PhotoController.store.url(), payload).then(
				(res) => res.data.data
			),
		onSuccess: () => {
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

			const previous = queryClient.getQueryData<PhotoCompetition | null>([
				"photos",
				"current",
			])

			queryClient.setQueryData<PhotoCompetition | null>(
				["photos", "current"],
				(current) =>
					current
						? {
								...current,
								photos: current.photos.map((photo) =>
									photo.id === photoId
										? {
												...photo,
												isLikedByViewer: !photo.isLikedByViewer,
												likesCount:
													photo.likesCount + (photo.isLikedByViewer ? -1 : 1),
											}
										: photo
								),
							}
						: current
			)

			return { previous }
		},
		onError: (_error, _photoId, context) => {
			if (context?.previous !== undefined) {
				queryClient.setQueryData(["photos", "current"], context.previous)
			}
		},
		onSettled: () => {
			queryClient.invalidateQueries({ queryKey: ["photos", "current"] })
		},
	})
}
