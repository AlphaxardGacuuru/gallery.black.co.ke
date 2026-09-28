import { AlignRight, ArrowLeft, Heart, Trash2, Trophy } from "lucide-react"
import { useCanGoBack, useNavigate, useRouter } from "@tanstack/react-router"
import { useState } from "react"
import { Head } from "@/lib/spa"
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
import { Spinner } from "@/components/ui/spinner"
import { cn } from "@/lib/utils"
import toast from "@/lib/toast"
import { useDeletePhoto, useLikePhoto, usePhoto } from "@/queries/photos"

type Props = {
	id: string
}

export default function PhotoShow({ id }: Props) {
	const router = useRouter()
	const navigate = useNavigate()
	const canGoBack = useCanGoBack()
	const { data, isLoading } = usePhoto(id)
	const likePhoto = useLikePhoto()
	const deletePhoto = useDeletePhoto()
	const [deleteDialogOpen, setDeleteDialogOpen] = useState(false)

	function goBack() {
		if (canGoBack) {
			router.history.back()
		} else {
			navigate({ to: "/discover" })
		}
	}

	function handleDelete() {
		deletePhoto.mutate(id, {
			onSuccess: () => {
				toast.success("Photo Deleted Successfully")
				goBack()
			},
			onError: () => toast.error("Couldn't Delete Your Photo"),
		})
	}

	if (isLoading || !data) {
		return (
			<div className="flex h-dvh items-center justify-center bg-black">
				<Spinner className="size-8 text-white/60" />
			</div>
		)
	}

	const { photo, canDelete, canLike } = data

	return (
		<div className="flex h-dvh max-h-dvh w-full flex-col gap-0 bg-black">
			<Head title={photo.userName ? `${photo.userName}'s photo` : "Photo"} />

			<header className="flex shrink-0 items-center gap-2 p-3 text-white">
				<Button
					variant="ghost"
					size="sm"
					aria-label="Back"
					onClick={goBack}
					className="shrink-0 text-white hover:bg-white/10 hover:text-white">
					<ArrowLeft className="size-5" />
				</Button>
				<p className="truncate text-sm font-medium">{photo.userName}</p>
				{photo.isWinner ? (
					<span className="flex shrink-0 items-center gap-1 rounded-full bg-amber-400 px-2 py-1 text-xs font-semibold text-amber-950">
						<Trophy className="size-3.5" />
						Winner
					</span>
				) : (
					photo.position && (
						<span className="flex shrink-0 items-center rounded-full bg-white/20 px-1.5 py-0.5 text-[11px] font-semibold text-white">
							#{photo.position}
						</span>
					)
				)}
				{canDelete && (
					<div className="ml-auto shrink-0">
						<DropdownMenu>
							<DropdownMenuTrigger asChild>
								<Button
									variant="ghost"
									size="sm"
									aria-label="Photo options"
									className="text-white hover:bg-white/10 hover:text-white">
									<AlignRight className="size-5" />
								</Button>
							</DropdownMenuTrigger>
							<DropdownMenuContent align="end">
								<DropdownMenuItem
									variant="destructive"
									disabled={deletePhoto.isPending}
									onSelect={(event) => {
										event.preventDefault()
										setDeleteDialogOpen(true)
									}}>
									<Trash2 />
									Delete
								</DropdownMenuItem>
							</DropdownMenuContent>
						</DropdownMenu>
						<Dialog open={deleteDialogOpen} onOpenChange={setDeleteDialogOpen}>
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
										<Button variant="destructive" onClick={handleDelete}>
											Delete
										</Button>
									</DialogClose>
								</DialogFooter>
							</DialogContent>
						</Dialog>
					</div>
				)}
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
			</div>
		</div>
	)
}
