import { Compass, Copy, Download, ShoppingCart, Trophy } from "lucide-react"
import { Link } from "@tanstack/react-router"
import { Head } from "@/lib/spa"
import Heading from "@/components/heading"
import { BuyPhotoDialog, canBuyPhoto } from "@/components/photos/BuyPhotoDialog"
import { Button } from "@/components/ui/button"
import { Skeleton } from "@/components/ui/skeleton"
import { useApp } from "@/contexts/AppContext"
import { useDiscoverPhotos } from "@/queries/photos"

const TILE_PILL_CLASS =
	"absolute right-1.5 bottom-1.5 flex cursor-pointer items-center gap-1 rounded-full bg-neutral-900/80 px-1.5 py-0.5 text-[11px] font-semibold text-white shadow dark:bg-neutral-100/90 dark:text-neutral-900"

export default function Discover() {
	const { data, isLoading, fetchNextPage, hasNextPage, isFetchingNextPage } =
		useDiscoverPhotos()

	const { auth } = useApp()

	const photos = data?.pages.flatMap((page) => page.data) ?? []
	const sale = data?.pages[0]?.sale

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
								<div
									key={photo.id}
									className="relative aspect-square overflow-hidden bg-muted">
									<Link
										to={`/photos/${photo.id}/show` as never}
										aria-label="View full photo"
										className="relative block size-full">
										<img
											src={photo.thumbnailUrl}
											alt={photo.caption ?? "Competition entry"}
											loading="lazy"
											className="size-full object-cover"
										/>
										{photo.isWinner ? (
											<div
												className="absolute left-1.5 top-1.5 flex items-center gap-1 rounded-full bg-amber-400 px-1.5 py-0.5 text-[11px] font-semibold text-amber-950 shadow"
												aria-label="Winner">
												<Trophy className="size-3" />
												<span className="hidden sm:inline">Winner</span>
											</div>
										) : (
											photo.position && (
												<div className="absolute left-1.5 top-1.5 rounded-full bg-neutral-900/80 px-1.5 py-0.5 text-[11px] font-semibold text-white shadow dark:bg-neutral-100/90 dark:text-neutral-900">
													#{photo.position}
												</div>
											)
										)}
										{photo.images.length > 0 && (
											<div
												className="absolute right-1.5 top-1.5 text-white drop-shadow"
												aria-label={`${photo.images.length + 1} photos`}>
												<Copy className="size-4" />
											</div>
										)}
									</Link>
									{/* Kept outside the Link so tapping it opens the dialog
								    instead of navigating to the photo page. */}
									{sale && photo.isPurchasedByViewer ? (
										<BuyPhotoDialog
											photo={photo}
											sale={sale}
											trigger={
												<button
													type="button"
													aria-label="Download photo"
													className={TILE_PILL_CLASS}>
													<Download className="size-3" />
												</button>
											}
										/>
									) : (
										sale &&
										canBuyPhoto(photo, sale, auth?.id) && (
											<BuyPhotoDialog
												photo={photo}
												sale={sale}
												trigger={
													<button
														type="button"
														aria-label={`Buy photo for KES ${sale.price}`}
														className={TILE_PILL_CLASS}>
														<ShoppingCart className="size-3" />
														<span className="hidden sm:inline">
															KES {sale.price}
														</span>
													</button>
												}
											/>
										)
									)}
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
