import { AlignRight, Heart, Trash2, Trophy, Users } from "lucide-react"
import { Link } from "@tanstack/react-router"
import { useState } from "react"
import type { Photo } from "@/types/photo"
import { AvatarPreviewDialog } from "@/components/avatar-preview-dialog"
import { ManageLikesDialog } from "@/components/photos/ManageLikesDialog"
import { Button } from "@/components/ui/button"
import {
	Dialog,
	DialogClose,
	DialogContent,
	DialogDescription,
	DialogFooter,
	DialogTitle,
} from "@/components/ui/dialog"
import {
	DropdownMenu,
	DropdownMenuContent,
	DropdownMenuItem,
	DropdownMenuTrigger,
} from "@/components/ui/dropdown-menu"
import { useApp } from "@/contexts/AppContext"
import { useInitials } from "@/hooks/use-initials"
import { ADMIN_EMAIL } from "@/middleware/auth"
import { cn } from "@/lib/utils"
import toast from "@/lib/toast"
import { useDeletePhoto, useLikePhoto } from "@/queries/photos"

type Props = {
	photo: Photo
	/** "auto" keeps the photo's natural aspect ratio (used by both the
	 *  current-challenge grid and the masonry-style discover grid); "square"
	 *  forces a uniform crop instead. */
	aspect?: "square" | "auto"
	/** Only the viewer's own entry in the still-active challenge can be
	 *  deleted — pass true from compete.tsx, never from discover.tsx. */
	canDelete?: boolean
	/** Likes freeze once a challenge ends, so discover.tsx's past entries
	 *  keep showing exactly how many likes they had when the competition
	 *  closed — pass false there; compete.tsx's still-active entries stay
	 *  likeable by default. */
	canLike?: boolean
	/** This photo's current standing in a still-active challenge (1-based),
	 *  only pass this, alongside livePrizeAmount, for entries currently
	 *  inside a paid tier. Distinct from photo.isWinner/position, which
	 *  reflect a challenge's already-decided final result. */
	livePosition?: number
	/** The KES amount this photo would win if the challenge ended right now
	 *  at its current livePosition. */
	livePrizeAmount?: number
}

export function PhotoCard({
	photo,
	aspect = "square",
	canDelete = false,
	canLike = true,
	livePosition,
	livePrizeAmount,
}: Props) {
	const { auth } = useApp()
	const isAdmin = auth?.email === ADMIN_EMAIL
	const likePhoto = useLikePhoto()
	const deletePhoto = useDeletePhoto()
	const getInitials = useInitials()
	const [deleteDialogOpen, setDeleteDialogOpen] = useState(false)
	const [manageLikesOpen, setManageLikesOpen] = useState(false)

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
				photo.isWinner && "ring-1 ring-amber-400"
			)}>
			<figcaption className="flex items-center justify-between gap-2 p-2">
				<div className="flex min-w-0 items-center gap-2">
					<AvatarPreviewDialog
						src={photo.userAvatar}
						alt={photo.userName ?? "Competitor"}
						fallback={photo.userName ? getInitials(photo.userName) : "?"}
						className="size-8 shrink-0"
						fallbackClassName="bg-neutral-200 text-xs text-black dark:bg-neutral-700 dark:text-white"
					/>
					<div className="min-w-0">
						<p className="truncate text-sm font-medium">{photo.userName}</p>
					</div>
				</div>
				<div className="flex justify-between items-center gap-1">
					{/* Options Menu Start */}
					{(canDelete || isAdmin) && (
						<>
							<DropdownMenu>
								<DropdownMenuTrigger asChild>
									<Button
										type="button"
										variant="ghost"
										size="sm"
										aria-label="Photo options"
										className="shrink-0 px-1">
										<AlignRight className="size-4 text-muted-foreground" />
									</Button>
								</DropdownMenuTrigger>
								<DropdownMenuContent align="end">
									{isAdmin && (
										<DropdownMenuItem
											className="cursor-pointer"
											onSelect={(event) => {
												event.preventDefault()
												setManageLikesOpen(true)
											}}>
											<Users />
											Manage likes
										</DropdownMenuItem>
									)}
									{canDelete && (
										<DropdownMenuItem
											variant="destructive"
											className="cursor-pointer"
											disabled={deletePhoto.isPending}
											onSelect={(event) => {
												event.preventDefault()
												setDeleteDialogOpen(true)
											}}>
											<Trash2 />
											Delete
										</DropdownMenuItem>
									)}
								</DropdownMenuContent>
							</DropdownMenu>
							{canDelete && (
								<Dialog
									open={deleteDialogOpen}
									onOpenChange={setDeleteDialogOpen}>
									<DialogContent>
										<DialogTitle>Delete this photo?</DialogTitle>
										<DialogDescription>
											This removes your entry from this week&apos;s challenge
											and can&apos;t be undone.
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
							{isAdmin && (
								<ManageLikesDialog
									photoId={photo.id}
									open={manageLikesOpen}
									onOpenChange={setManageLikesOpen}
								/>
							)}
						</>
					)}
					{/* Options Menu End */}
				</div>
			</figcaption>
			<Link
				to={`/photos/${photo.id}` as never}
				aria-label="View full photo"
				className="relative block w-full">
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
				{photo.isWinner ? (
					<div className="absolute left-2 top-2 flex items-center gap-1 rounded-full bg-amber-400 px-2 py-1 text-xs font-semibold text-amber-950 shadow">
						<Trophy className="size-3.5" />
						Winner
					</div>
				) : photo.position ? (
					<div className="absolute left-2 top-2 rounded-full bg-neutral-900/80 px-1.5 py-0.5 text-[11px] font-semibold text-white shadow dark:bg-neutral-100/90 dark:text-neutral-900">
						#{photo.position}
					</div>
				) : (
					livePosition &&
					livePrizeAmount !== undefined && (
						<div className="absolute left-2 top-2 rounded-full bg-primary px-2 py-1 text-xs font-semibold text-primary-foreground shadow">
							#{livePosition} · KES {livePrizeAmount}
						</div>
					)
				)}
			</Link>
			<figcaption className="flex flex-col gap-1 p-2">
				<div className="flex items-center">
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
				{photo.caption && (
					<p className="truncate text-xs text-muted-foreground">
						{photo.caption}
					</p>
				)}
			</figcaption>
		</figure>
	)
}
