import { Compass } from "lucide-react"
import { Head } from "@/lib/spa"
import Heading from "@/components/heading"
import { PhotoCard } from "@/components/photos/PhotoCard"
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
					<div className="columns-2 gap-4 sm:columns-3 lg:columns-4 *:mb-4">
						{Array.from({ length: 12 }).map((_, index) => (
							<Skeleton
								key={index}
								className="mb-4 break-inside-avoid"
								style={{ height: `${180 + (index % 3) * 60}px` }}
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
						{/* CSS columns give each photo its own natural aspect ratio,
						    the same "masonry" effect Instagram's discovery grid uses —
						    ordering across columns is a tradeoff we accept for it. */}
						<div className="columns-2 gap-1 sm:columns-3 lg:columns-4 *:mb-1">
							{photos.map((photo) => (
								<div
									key={photo.id}
									className="break-inside-avoid">
									<PhotoCard
										photo={photo}
										aspect="auto"
										canLike={false}
									/>
								</div>
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
