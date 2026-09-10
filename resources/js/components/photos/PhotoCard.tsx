import { Heart } from "lucide-react"
import type { Photo } from "@/types/photo"
import { cn } from "@/lib/utils"
import { useLikePhoto } from "@/queries/photos"

type Props = {
	photo: Photo
	/** "square" for the uniform current-challenge grid, "auto" to keep the
	 *  photo's natural aspect ratio for the masonry-style discover grid. */
	aspect?: "square" | "auto"
}

export function PhotoCard({ photo, aspect = "square" }: Props) {
	const likePhoto = useLikePhoto()

	return (
		<figure className="group overflow-hidden rounded-xl border bg-card shadow-sm">
			<div className="relative">
				<img
					src={photo.url}
					alt={photo.caption ?? "Competition entry"}
					loading="lazy"
					style={
						aspect === "auto"
							? { aspectRatio: photo.aspectRatio || 1 }
							: undefined
					}
					className={cn(
						"w-full object-cover",
						aspect === "square" && "aspect-square"
					)}
				/>
			</div>
			<figcaption className="flex items-center justify-between gap-2 p-3">
				<div className="min-w-0">
					<p className="truncate text-sm font-medium">{photo.userName}</p>
					{photo.caption && (
						<p className="truncate text-xs text-muted-foreground">
							{photo.caption}
						</p>
					)}
				</div>
				<button
					type="button"
					aria-label={photo.isLikedByViewer ? "Unlike photo" : "Like photo"}
					disabled={likePhoto.isPending}
					onClick={() => likePhoto.mutate(photo.id)}
					className="flex shrink-0 items-center gap-1.5 rounded-full border px-2.5 py-1 text-sm transition-colors hover:bg-accent">
					<Heart
						className={cn(
							"size-4",
							photo.isLikedByViewer && "fill-red-500 text-red-500"
						)}
					/>
					<span className="tabular-nums">{photo.likesCount}</span>
				</button>
			</figcaption>
		</figure>
	)
}
