import {
	AlignRight,
	Bookmark,
	Heart,
	type LucideIcon,
	Pencil,
	Share2,
	Trash2,
	Trophy,
	Users,
} from "lucide-react"
import { Link, useNavigate } from "@tanstack/react-router"
import { useState } from "react"
import type { Photo } from "@/types/photo"
import { AvatarPreviewDialog } from "@/components/avatar-preview-dialog"
import VerifiedBadge, { VerifiedAvatar } from "@/components/verified-badge"
import { Avatar, AvatarFallback, AvatarImage } from "@/components/ui/avatar"
import { EditPhotoDialog } from "@/components/photos/EditPhotoDialog"
import { FlaggedAccountDialog } from "@/components/photos/FlaggedAccountDialog"
import { ManageLikesDialog } from "@/components/photos/ManageLikesDialog"
import { PhotoCarousel } from "@/components/photos/PhotoCarousel"
import { PostedAt } from "@/components/photos/PostedAt"
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
import {
	Sheet,
	SheetContent,
	SheetDescription,
	SheetTitle,
	SheetTrigger,
} from "@/components/ui/sheet"
import { useApp } from "@/contexts/AppContext"
import { useClipboard } from "@/hooks/use-clipboard"
import { track } from "@/lib/analytics"
import { useInitials } from "@/hooks/use-initials"
import { useIsMobile } from "@/hooks/use-mobile"
import { cn } from "@/lib/utils"
import toast from "@/lib/toast"
import {
	useDeletePhoto,
	useLikePhoto,
	useToggleBookmark,
} from "@/queries/photos"

type Props = {
	photo: Photo
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
	canDelete = false,
	canLike = true,
	livePosition,
	livePrizeAmount,
}: Props) {
	const { auth } = useApp()
	const isAdmin = Boolean(auth?.isAdmin)
	const likePhoto = useLikePhoto()
	const toggleBookmark = useToggleBookmark()
	const deletePhoto = useDeletePhoto()
	const getInitials = useInitials()
	const [, copy] = useClipboard()
	const [deleteDialogOpen, setDeleteDialogOpen] = useState(false)
	const [manageLikesOpen, setManageLikesOpen] = useState(false)
	const [flaggedOpen, setFlaggedOpen] = useState(false)
	const [editOpen, setEditOpen] = useState(false)
	const [optionsOpen, setOptionsOpen] = useState(false)
	const [captionExpanded, setCaptionExpanded] = useState(false)
	const isMobile = useIsMobile()
	const navigate = useNavigate()

	// Rendered as a bottom sheet on small screens and a dropdown otherwise.
	const options: {
		label: string
		icon: LucideIcon
		destructive?: boolean
		onSelect: () => void
	}[] = [
		...(isAdmin
			? [
					{
						label: "Manage likes",
						icon: Users,
						onSelect: () => setManageLikesOpen(true),
					},
				]
			: []),
		...(canDelete
			? [
					{
						label: "Edit",
						icon: Pencil,
						onSelect: () => setEditOpen(true),
					},
					{
						label: "Delete",
						icon: Trash2,
						destructive: true,
						onSelect: () => setDeleteDialogOpen(true),
					},
				]
			: []),
	]

	const optionsTrigger = (
		<Button
			type="button"
			variant="ghost"
			size="sm"
			aria-label="Photo options"
			className="shrink-0 px-1">
			<AlignRight className="size-4 text-muted-foreground" />
		</Button>
	)

	// A guest has to sign in to like, and a flagged account can't like, so
	// explain why instead.
	function like() {
		if (!canLike) {
			return
		}

		if (!auth) {
			navigate({ to: "/login" })

			return
		}

		if (auth?.flaggedAt) {
			setFlaggedOpen(true)

			return
		}

		likePhoto.mutate(photo.id)
	}

	// Saved photos are listed under Saved on the viewer's profile. Guests
	// sign in first.
	function bookmark() {
		if (!auth) {
			navigate({ to: "/login" })

			return
		}

		toggleBookmark.mutate({
			photoId: photo.id,
			bookmarked: !photo.isBookmarkedByViewer,
		})
	}

	// The phone's share sheet where there is one, otherwise copy the link.
	async function share() {
		const url = `${window.location.origin}/photos/${photo.id}/show`

		if (navigator.share) {
			try {
				await navigator.share({
					title: "Black Gallery",
					text: photo.userName
						? `Check out ${photo.userName}'s photo on Black Gallery`
						: "Check out this photo on Black Gallery",
					url,
				})
				track("photo.shared", { photoId: photo.id, source: "share_sheet" })
			} catch {
				// The user cancelled the share sheet, nothing to do.
			}

			return
		}

		if (await copy(url)) {
			track("photo.shared", { photoId: photo.id, source: "copied_link" })
			toast.success("Link copied")
		} else {
			toast.error("Couldn't copy the link")
		}
	}

	function handleDelete() {
		deletePhoto.mutate(photo.id, {
			onSuccess: () => toast.success("Photo Deleted Successfully"),
			onError: () => toast.error("Couldn't Delete Your Photo"),
		})
	}

	const image = (
		<>
			{photo.images.length > 0 ? (
				<PhotoCarousel
					fitWidth
					slides={[photo, ...photo.images].map((image) => ({
						url: image.thumbnailUrl,
						alt: photo.caption ?? "Competition entry",
					}))}
				/>
			) : (
				<img
					src={photo.thumbnailUrl}
					alt={photo.caption ?? "Competition entry"}
					loading="lazy"
					className="h-auto w-full"
				/>
			)}
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
		</>
	)

	const author = (
		<div className="flex min-w-0 items-center gap-1">
			<p className="truncate text-sm font-medium">{photo.userName}</p>
			{photo.userVerified && <VerifiedBadge className="size-3.5 shrink-0" />}
		</div>
	)

	return (
		<figure className="overflow-hidden rounded-xl border bg-card shadow-sm">
			<figcaption className="flex items-center justify-between gap-2 p-2">
				{/* Profiles need an account, so guests get the avatar preview instead. */}
				{auth ? (
					<Link
						to={`/users/${photo.userId}` as never}
						className="flex min-w-0 items-center gap-2">
						<VerifiedAvatar verified={photo.userVerified}>
							<Avatar className="size-8">
								<AvatarImage
									src={photo.userAvatar ?? undefined}
									alt={photo.userName ?? "Competitor"}
								/>
								<AvatarFallback className="bg-neutral-200 text-xs text-black dark:bg-neutral-700 dark:text-white">
									{photo.userName ? getInitials(photo.userName) : "?"}
								</AvatarFallback>
							</Avatar>
						</VerifiedAvatar>
						{author}
					</Link>
				) : (
					<div className="flex min-w-0 items-center gap-2">
						<AvatarPreviewDialog
							src={photo.userAvatar}
							alt={photo.userName ?? "Competitor"}
							fallback={photo.userName ? getInitials(photo.userName) : "?"}
							className="size-8 shrink-0"
							fallbackClassName="bg-neutral-200 text-xs text-black dark:bg-neutral-700 dark:text-white"
							verified={photo.userVerified}
						/>
						{author}
					</div>
				)}
				<div className="flex justify-between items-center gap-1">
					{/* Options Menu Start */}
					{(canDelete || isAdmin) && (
						<>
							{isMobile ? (
								<Sheet
									open={optionsOpen}
									onOpenChange={setOptionsOpen}>
									<SheetTrigger asChild>{optionsTrigger}</SheetTrigger>
									<SheetContent
										side="bottom"
										overlayClassName="bg-black/20"
										className="inset-x-2 bottom-2 gap-0 rounded-3xl border border-white/40 bg-white/34 p-2 pb-[max(0.5rem,env(safe-area-inset-bottom))] shadow-[0_20px_45px_-28px_rgba(15,23,42,0.45)] backdrop-blur-xl dark:border-white/12 dark:bg-slate-950/20">
										<SheetTitle className="px-3 py-2 text-sm">
											Photo options
										</SheetTitle>
										<SheetDescription className="sr-only">
											Choose what to do with this photo
										</SheetDescription>
										{options.map(
											({ label, icon: Icon, destructive, onSelect }) => (
												<button
													key={label}
													type="button"
													disabled={destructive && deletePhoto.isPending}
													onClick={() => {
														setOptionsOpen(false)
														onSelect()
													}}
													className={cn(
														"flex w-full items-center gap-3 rounded-2xl px-3 py-3 text-left text-sm hover:bg-white/30 active:bg-white/30 disabled:opacity-50 dark:hover:bg-white/10 dark:active:bg-white/10 [&_svg]:size-4 [&_svg]:text-muted-foreground",
														destructive &&
															"text-destructive-foreground [&_svg]:text-destructive-foreground"
													)}>
													<Icon />
													{label}
												</button>
											)
										)}
									</SheetContent>
								</Sheet>
							) : (
								<DropdownMenu>
									<DropdownMenuTrigger asChild>
										{optionsTrigger}
									</DropdownMenuTrigger>
									<DropdownMenuContent align="end">
										{options.map(
											({ label, icon: Icon, destructive, onSelect }) => (
												<DropdownMenuItem
													key={label}
													variant={destructive ? "destructive" : "default"}
													className="cursor-pointer"
													disabled={destructive && deletePhoto.isPending}
													onSelect={(event) => {
														event.preventDefault()
														onSelect()
													}}>
													<Icon />
													{label}
												</DropdownMenuItem>
											)
										)}
									</DropdownMenuContent>
								</DropdownMenu>
							)}
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
							{canDelete && (
								<EditPhotoDialog
									photo={photo}
									open={editOpen}
									onOpenChange={setEditOpen}
								/>
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
			{/* The photo's own page needs an account, so guests just see the image. */}
			{auth ? (
				<Link
					to={`/photos/${photo.id}/show` as never}
					aria-label="View full photo"
					className="relative block w-full">
					{image}
				</Link>
			) : (
				<div className="relative block w-full">{image}</div>
			)}
			<figcaption className="flex flex-col gap-1">
				<div className="flex items-center">
					<button
						type="button"
						aria-label={photo.isLikedByViewer ? "Liked" : "Like photo"}
						disabled={!canLike || photo.isLikedByViewer || likePhoto.isPending}
						onClick={like}
						className="flex shrink-0 cursor-pointer items-center gap-1 p-2 text-sm text-muted-foreground disabled:cursor-default">
						<Heart
							className={cn(
								"size-4 shrink-0",
								photo.isLikedByViewer && "fill-red-500 text-red-500"
							)}
						/>
						<span
							className={cn(
								"tabular-nums",
								photo.isLikedByViewer && "text-red-500"
							)}>
							{photo.likesCount}
						</span>
					</button>
					<button
						type="button"
						aria-label="Share photo"
						onClick={share}
						className="flex shrink-0 cursor-pointer items-center p-2 text-muted-foreground hover:text-foreground">
						<Share2 className="size-4 shrink-0" />
					</button>
					<button
						type="button"
						aria-label={
							photo.isBookmarkedByViewer ? "Remove from saved" : "Save photo"
						}
						aria-pressed={photo.isBookmarkedByViewer}
						onClick={bookmark}
						className="ms-auto flex shrink-0 cursor-pointer items-center p-2 text-muted-foreground hover:text-foreground">
						<Bookmark
							className={cn(
								"size-4 shrink-0",
								photo.isBookmarkedByViewer && "fill-current text-foreground"
							)}
						/>
					</button>
				</div>
				{photo.caption && (
					<button
						type="button"
						aria-expanded={captionExpanded}
						onClick={() => setCaptionExpanded((expanded) => !expanded)}
						className={cn(
							"cursor-pointer px-2 text-left text-xs text-muted-foreground",
							captionExpanded
								? "whitespace-pre-line wrap-break-word"
								: "truncate"
						)}>
						{photo.caption}
					</button>
				)}
				<PostedAt
					date={photo.createdAt}
					withTime={false}
					className="px-2 pb-2 text-[11px]"
				/>
			</figcaption>
			<FlaggedAccountDialog
				open={flaggedOpen}
				onOpenChange={setFlaggedOpen}
			/>
		</figure>
	)
}
