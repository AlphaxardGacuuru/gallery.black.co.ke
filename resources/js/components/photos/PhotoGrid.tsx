import { Copy, Download, ShoppingCart, Trophy } from "lucide-react"
import { Link } from "@tanstack/react-router"
import type {
	InfiniteData,
	UseInfiniteQueryResult,
} from "@tanstack/react-query"
import type { ReactNode } from "react"
import { BuyPhotoDialog, canBuyPhoto } from "@/components/photos/BuyPhotoDialog"
import { Button } from "@/components/ui/button"
import { Skeleton } from "@/components/ui/skeleton"
import { useApp } from "@/contexts/AppContext"
import type { PhotoGridPage } from "@/queries/photos"

const TILE_PILL_CLASS =
	"absolute right-1.5 bottom-1.5 flex cursor-pointer items-center gap-1 rounded-full bg-neutral-900/80 px-1.5 py-0.5 text-[11px] font-semibold text-white shadow dark:bg-neutral-100/90 dark:text-neutral-900"

type Props = {
	query: UseInfiniteQueryResult<InfiniteData<PhotoGridPage>>
	/** Shown when the grid has no photos. */
	empty: ReactNode
}

/**
 * A uniform square grid of photos, as on Discover and profiles. Each tile
 * is the cropped image, and tapping it opens the photo's own page.
 */
export function PhotoGrid({ query, empty }: Props) {
	const { auth } = useApp()
	const { data, isLoading, fetchNextPage, hasNextPage, isFetchingNextPage } =
		query

	const photos = data?.pages.flatMap((page) => page.data) ?? []
	const sale = data?.pages[0]?.sale

	if (isLoading) {
		return (
			<div className="grid grid-cols-3 gap-1 lg:grid-cols-4">
				{Array.from({ length: 12 }).map((_, index) => (
					<Skeleton
						key={index}
						className="aspect-square rounded-none"
					/>
				))}
			</div>
		)
	}

	if (photos.length === 0) {
		return empty
	}

	return (
		<div className="space-y-6">
			<div className="grid grid-cols-3 gap-1 lg:grid-cols-4">
				{photos.map((photo) => (
					<div
						key={photo.id}
						className="relative aspect-square overflow-hidden bg-muted">
						<TileLink
							photoId={photo.id}
							signedIn={Boolean(auth)}>
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
						</TileLink>
						{/* Kept outside the link so tapping it opens the dialog
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
							auth &&
							sale &&
							canBuyPhoto(photo, sale, auth.id) && (
								<BuyPhotoDialog
									photo={photo}
									sale={sale}
									trigger={
										<button
											type="button"
											aria-label={`Buy photo for KES ${sale.price}`}
											className={TILE_PILL_CLASS}>
											<ShoppingCart className="size-3" />
											<span className="hidden sm:inline">KES {sale.price}</span>
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
		</div>
	)
}

/**
 * Opens the photo's own page. That page needs an account, so guests get
 * the tile without a link.
 */
function TileLink({
	photoId,
	signedIn,
	children,
}: {
	photoId: string
	signedIn: boolean
	children: ReactNode
}) {
	if (!signedIn) {
		return <div className="relative block size-full">{children}</div>
	}

	return (
		<Link
			to={`/photos/${photoId}/show` as never}
			aria-label="View full photo"
			className="relative block size-full">
			{children}
		</Link>
	)
}
