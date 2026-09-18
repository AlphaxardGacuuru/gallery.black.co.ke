import { Heart, Trash2, X } from "lucide-react"
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

type Props = {
	photo: Photo
	open: boolean
	onOpenChange: (open: boolean) => void
	canDelete?: boolean
	canLike?: boolean
}

export function PhotoLightbox({
	photo,
	open,
	onOpenChange,
	canDelete = false,
	canLike = true,
}: Props) {
	const likePhoto = useLikePhoto()
	const deletePhoto = useDeletePhoto()

	function handleDelete() {
		deletePhoto.mutate(photo.id, {
			onSuccess: () => {
				toast.success("Photo Deleted Successfully")
				onOpenChange(false)
			},
			onError: () => toast.error("Couldn't Delete Your Photo"),
		})
	}

	return (
		<Dialog
			open={open}
			onOpenChange={onOpenChange}>
			<DialogContent
				showCloseButton={false}
				className="flex h-dvh max-h-dvh w-screen max-w-none flex-col gap-0 border-0 bg-black p-0 sm:max-w-none">
				<DialogTitle className="sr-only">
					Photo by {photo.userName ?? "competitor"}
				</DialogTitle>
				<DialogDescription className="sr-only">
					{photo.caption ?? "Competition entry"}
				</DialogDescription>

				<header className="flex shrink-0 items-center justify-between gap-2 p-3 text-white">
					<p className="truncate text-sm font-medium">{photo.userName}</p>
					<DialogClose asChild>
						<Button
							variant="ghost"
							size="sm"
							aria-label="Close"
							className="shrink-0 text-white hover:bg-white/10 hover:text-white">
							<X className="size-5" />
						</Button>
					</DialogClose>
				</header>

				<div className="flex min-h-0 flex-1 items-center justify-center overflow-hidden">
					<img
						src={photo.url}
						alt={photo.caption ?? "Competition entry"}
						className="max-h-full max-w-full object-contain"
					/>
				</div>

				{photo.caption && (
					<footer className="shrink-0 p-3 pr-16 text-white">
						<p className="truncate text-sm text-white/80">{photo.caption}</p>
					</footer>
				)}

				{/* Instagram/TikTok-style vertical action rail */}
				<div className="absolute right-2 bottom-6 z-10 flex flex-col items-center gap-5 sm:right-4">
					<button
						type="button"
						aria-label={photo.isLikedByViewer ? "Liked" : "Like photo"}
						disabled={!canLike || photo.isLikedByViewer || likePhoto.isPending}
						onClick={() => canLike && likePhoto.mutate(photo.id)}
						className="flex flex-col items-center gap-1 text-white disabled:opacity-70 cursor-pointer disabled:cursor-default">
						<Heart
							className={cn(
								"size-7 drop-shadow",
								photo.isLikedByViewer && "fill-red-500 text-red-500"
							)}
						/>
						<span className="text-xs font-medium tabular-nums drop-shadow">
							{photo.likesCount}
						</span>
					</button>

					{canDelete && (
						<Dialog>
							<DialogTrigger asChild>
								<button
									type="button"
									aria-label="Delete photo"
									disabled={deletePhoto.isPending}
									className="flex flex-col items-center gap-1 text-white disabled:opacity-70 cursor-pointer disabled:cursor-default">
									<Trash2 className="size-7 drop-shadow" />
								</button>
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
				</div>
			</DialogContent>
		</Dialog>
	)
}
