import { Heart, Trash2, Trophy } from "lucide-react"
import { useState } from "react"
import type { Photo } from "@/types/photo"
import { Button } from "@/components/ui/button"
import {
	Dialog,
	DialogClose,
	DialogContent,
	DialogDescription,
	DialogFooter,
	DialogTitle,
	DialogTrigger,
} from "@/components/ui/dialog"
import { cn } from "@/lib/utils"
import toast from "@/lib/toast"
import { useDeletePhoto, useLikePhoto } from "@/queries/photos"
import { PhotoLightbox } from "./PhotoLightbox"

type Props = {
	photo: Photo
	/** "square" for the uniform current-challenge grid, "auto" to keep the
	 *  photo's natural aspect ratio for the masonry-style discover grid. */
	aspect?: "square" | "auto"
	/** Only the viewer's own entry in the still-active challenge can be
	 *  deleted — pass true from compete.tsx, never from discover.tsx. */
	canDelete?: boolean
	/** Likes freeze once a challenge ends, so discover.tsx's past entries
	 *  keep showing exactly how many likes they had when the competition
	 *  closed — pass false there; compete.tsx's still-active entries stay
	 *  likeable by default. */
	canLike?: boolean
}

export function PhotoCard({
	photo,
	aspect = "square",
	canDelete = false,
	canLike = true,
}: Props) {
	const likePhoto = useLikePhoto()
	const deletePhoto = useDeletePhoto()
	const [lightboxOpen, setLightboxOpen] = useState(false)

	function handleDelete() {
		deletePhoto.mutate(photo.id, {
			onSuccess: () => toast.success("Photo Deleted Successfully"),
			onError: () => toast.error("Couldn't Delete Your Photo"),
		})
	}

	return (
		<figure
			className={cn(
				"overflow-hidden rounded-xl border bg-card shadow-sm",
				photo.isWinner && "ring-2 ring-amber-400 border-amber-400"
			)}>
			<button
				type="button"
				aria-label="View full photo"
				onClick={() => setLightboxOpen(true)}
				className="relative block w-full cursor-pointer">
				<img
					src={photo.thumbnailUrl}
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
				{photo.isWinner && (
					<div className="absolute left-2 top-2 flex items-center gap-1 rounded-full bg-amber-400 px-2 py-1 text-xs font-semibold text-amber-950 shadow">
						<Trophy className="size-3.5" />
						Winner
					</div>
				)}
			</button>
			<PhotoLightbox
				photo={photo}
				open={lightboxOpen}
				onOpenChange={setLightboxOpen}
				canDelete={canDelete}
				canLike={canLike}
			/>
			<figcaption className="flex items-center justify-between gap-2 p-2">
				<div className="min-w-0">
					<p className="truncate text-sm font-medium">{photo.userName}</p>
					{photo.caption && (
						<p className="truncate text-xs text-muted-foreground">
							{photo.caption}
						</p>
					)}
				</div>
				{/* Actions Start */}
				<div className="flex items-center gap-1">
					{canDelete && (
						<Dialog>
							<DialogTrigger asChild>
								<Button
									type="button"
									variant="ghost"
									size="sm"
									aria-label="Delete photo"
									disabled={deletePhoto.isPending}
									className="shrink-0 px-1">
									<Trash2 className="size-4 text-white/60" />
								</Button>
							</DialogTrigger>
							<DialogContent>
								<DialogTitle>Delete this photo?</DialogTitle>
								<DialogDescription>
									This removes your entry from this week&apos;s challenge and
									can&apos;t be undone.
								</DialogDescription>
								<DialogFooter className="gap-2">
									<DialogClose asChild>
										<Button variant="secondary">Cancel</Button>
									</DialogClose>
									<DialogClose asChild>
										<Button
											variant="destructive"
											onClick={handleDelete}>
											Delete
										</Button>
									</DialogClose>
								</DialogFooter>
							</DialogContent>
						</Dialog>
					)}

					<Button
						variant="ghost"
						size="sm"
						aria-label={photo.isLikedByViewer ? "Liked" : "Like photo"}
						disabled={!canLike || photo.isLikedByViewer || likePhoto.isPending}
						onClick={() => canLike && likePhoto.mutate(photo.id)}
						className="flex shrink-0 items-center gap-1 px-1 text-sm transition-colors cursor-pointer">
						<Heart
							className={cn(
								"size-4",
								photo.isLikedByViewer && "fill-red-500 text-red-500"
							)}
						/>
						<span
							className={cn(
								"tabular-nums",
								photo.isLikedByViewer && "fill-red-500 text-red-500"
							)}>
							{photo.likesCount}
						</span>
					</Button>
				</div>
				{/* Actions End */}
			</figcaption>
		</figure>
	)
}
