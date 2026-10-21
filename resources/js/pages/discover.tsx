import { Compass, Copy } from "lucide-react"
import { Link } from "@tanstack/react-router"
import { Head } from "@/lib/spa"
import Heading from "@/components/heading"
import { Button } from "@/components/ui/button"
import { Skeleton } from "@/components/ui/skeleton"
import { useDiscoverPhotos } from "@/queries/photos"

export default function Discover() {
	const { data, isLoading, fetchNextPage, hasNextPage, isFetchingNextPage } =
		useDiscoverPhotos()

	const photos = data?.pages.flatMap((page) => page.data) ?? []

	return (
		<>
			<Head title="Discover" />

			<div className="space-y-6">
				<Heading
					variant="small"
					title="Discover"
					description="Winners and entries from past challenges"
				/>

				{isLoading ? (
					<div className="grid grid-cols-3 gap-1 lg:grid-cols-4">
						{Array.from({ length: 12 }).map((_, index) => (
							<Skeleton
								key={index}
								className="aspect-square rounded-none"
							/>
						))}
					</div>
				) : photos.length === 0 ? (
					<div className="flex flex-col items-center gap-2 rounded-xl border border-dashed py-16 text-center text-muted-foreground">
						<Compass className="size-8" />
						<p className="font-medium">Nothing here yet</p>
						<p className="text-sm">
							Past competitions will show up here once they end.
						</p>
					</div>
				) : (
					<>
						{/* A uniform square grid keeps discovery symmetrical. Each tile
						    is just the cropped image, and tapping it opens the photo's
						    own page with the full image and its details. */}
						<div className="grid grid-cols-3 gap-1 lg:grid-cols-4">
							{photos.map((photo) => (
								<Link
									key={photo.id}
									to={`/photos/${photo.id}/show` as never}
									aria-label="View full photo"
									className="relative block aspect-square overflow-hidden bg-muted">
									<img
										src={photo.thumbnailUrl}
										alt={photo.caption ?? "Competition entry"}
										loading="lazy"
										className="size-full object-cover"
									/>
									{photo.images.length > 0 && (
										<div
											className="absolute right-1.5 top-1.5 text-white drop-shadow"
											aria-label={`${photo.images.length + 1} photos`}>
											<Copy className="size-4" />
										</div>
									)}
								</Link>
							))}
						</div>

						{hasNextPage && (
							<div className="flex justify-center">
								<Button
									variant="outline"
									disabled={isFetchingNextPage}
									onClick={() => fetchNextPage()}>
									{isFetchingNextPage ? "Loading…" : "Load more"}
								</Button>
							</div>
						)}
					</>
				)}
			</div>
		</>
	)
}
