import { Download, ShoppingCart } from "lucide-react"
import { type ReactNode, useEffect, useRef, useState } from "react"
import { useQueryClient } from "@tanstack/react-query"
import { Button } from "@/components/ui/button"
import {
	Dialog,
	DialogContent,
	DialogDescription,
	DialogFooter,
	DialogHeader,
	DialogTitle,
	DialogTrigger,
} from "@/components/ui/dialog"
import { Link } from "@/components/ui/link"
import { Skeleton } from "@/components/ui/skeleton"
import { Spinner } from "@/components/ui/spinner"
import { useApp } from "@/contexts/AppContext"
import toast from "@/lib/toast"
import {
	markPhotoPurchased,
	type PhotoSale,
	useBuyPhoto,
	usePhotoDownloads,
	usePollPhotoPurchase,
} from "@/queries/photos"
import type { Photo } from "@/types/photo"
import { errorMessage } from "./upload-errors"

/**
 * Whether the viewer should be offered this photo at all: sales are on,
 * the photo can be sold, and it isn't their own.
 */
export function canBuyPhoto(
	photo: Photo,
	sale: PhotoSale | undefined,
	viewerId: string | number | undefined
): boolean {
	return Boolean(
		sale?.enabled &&
		photo.isSellable &&
		String(photo.userId) !== String(viewerId)
	)
}

/**
 * Buying a full-resolution copy of a photo: idle -> waiting for the STK
 * push to be approved on the buyer's phone -> paid (download links) or
 * failed (back to idle so they can retry). Once the photo is owned, the
 * same dialog just shows the downloads.
 */
export function BuyPhotoDialog({
	photo,
	sale,
	trigger,
}: {
	photo: Photo
	sale: PhotoSale
	trigger: ReactNode
}) {
	const [open, setOpen] = useState(false)

	return (
		<Dialog
			open={open}
			onOpenChange={setOpen}>
			<DialogTrigger asChild>{trigger}</DialogTrigger>
			<DialogContent>
				{open && (
					<BuyPhotoContent
						photo={photo}
						sale={sale}
					/>
				)}
			</DialogContent>
		</Dialog>
	)
}

function BuyPhotoContent({ photo, sale }: { photo: Photo; sale: PhotoSale }) {
	const queryClient = useQueryClient()
	const { auth } = useApp()
	const buyPhoto = useBuyPhoto(photo.id)
	const [purchaseId, setPurchaseId] = useState<string | null>(null)
	const { data: purchase } = usePollPhotoPurchase(purchaseId)
	const notifiedRef = useRef(false)
	const photoCount = photo.images.length + 1

	useEffect(() => {
		if (purchase?.status !== "paid" || notifiedRef.current) {
			return
		}

		notifiedRef.current = true
		toast.success("Payment received", {
			description: "Your photo is ready to download.",
		})
		markPhotoPurchased(queryClient, photo.id)
	}, [purchase?.status, photo.id, queryClient])

	function handleBuy() {
		buyPhoto.mutate(undefined, {
			onSuccess: ({ purchaseId: id }) => setPurchaseId(id),
			onError: (error) =>
				toast.error("Couldn't start the payment", {
					description: errorMessage(error, "Please try again."),
				}),
		})
	}

	if (photo.isPurchasedByViewer || purchase?.status === "paid") {
		return (
			<>
				<DialogHeader>
					<DialogTitle>Download your photo</DialogTitle>
					<DialogDescription>
						The full-resolution original
						{photoCount > 1 ? `s (${photoCount} photos)` : ""} by{" "}
						{photo.userName}.
					</DialogDescription>
				</DialogHeader>
				<DownloadList photoId={photo.id} />
			</>
		)
	}

	const waiting =
		purchaseId !== null && (!purchase || purchase.status === "pending")
	const failed = purchase?.status === "failed"

	return (
		<>
			<DialogHeader>
				<DialogTitle>Buy this photo</DialogTitle>
				<DialogDescription>
					Get the full-resolution original
					{photoCount > 1 ? `s (all ${photoCount} photos)` : ""} by{" "}
					{photo.userName}. Part of every sale goes straight to the
					photographer.
				</DialogDescription>
			</DialogHeader>

			<div className="flex items-center gap-3">
				<img
					src={photo.thumbnailUrl}
					alt={photo.caption ?? "Competition entry"}
					className="size-20 shrink-0 rounded-md object-cover"
				/>
				<div className="space-y-1">
					<p className="text-2xl font-semibold tabular-nums">
						KES {sale.price}
					</p>
					<p className="text-sm text-muted-foreground">
						{auth?.phone
							? `Paid via M-Pesa from ${auth.phone}`
							: "Paid via M-Pesa"}
					</p>
				</div>
			</div>

			{failed && (
				<p className="text-sm text-destructive">
					The payment didn&apos;t go through. You can try again.
				</p>
			)}

			{!auth?.phone && (
				<p className="text-sm text-muted-foreground">
					Add your M-Pesa phone number in{" "}
					<Link href="/settings/profile">your profile</Link> to buy photos.
				</p>
			)}

			<DialogFooter>
				{waiting ? (
					<Button disabled>
						<Spinner className="size-4" />
						Check your phone, approve KES {sale.price}
					</Button>
				) : (
					<Button
						disabled={!auth?.phone || buyPhoto.isPending}
						onClick={handleBuy}>
						{buyPhoto.isPending ? (
							<Spinner className="size-4" />
						) : (
							<ShoppingCart className="size-4" />
						)}
						{failed ? "Try again" : `Pay KES ${sale.price}`}
					</Button>
				)}
			</DialogFooter>
		</>
	)
}

function DownloadList({ photoId }: { photoId: string }) {
	const {
		data: downloads,
		isLoading,
		isError,
	} = usePhotoDownloads(photoId, true)

	if (isLoading) {
		return <Skeleton className="h-16 w-full" />
	}

	if (isError || !downloads) {
		return (
			<p className="text-sm text-destructive">
				Couldn&apos;t load your downloads. Please try again.
			</p>
		)
	}

	return (
		<ul className="space-y-2">
			{downloads.map((download, index) => (
				<li
					key={download.url}
					className="flex items-center gap-3">
					<img
						src={download.thumbnailUrl}
						alt=""
						className="size-12 shrink-0 rounded-md object-cover"
					/>
					<p className="flex-1 text-sm font-medium">
						Photo {index + 1}
						{downloads.length > 1 ? ` of ${downloads.length}` : ""}
					</p>
					<Button
						asChild
						variant="outline"
						size="sm">
						<a
							href={download.url}
							download>
							<Download className="size-4" />
							Download
						</a>
					</Button>
				</li>
			))}
		</ul>
	)
}
