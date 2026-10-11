import { Camera, ShoppingCart } from "lucide-react"
import { useEffect, useRef, useState } from "react"
import { useQueryClient } from "@tanstack/react-query"
import { Button } from "@/components/ui/button"
import { Link } from "@/components/ui/link"
import { Spinner } from "@/components/ui/spinner"
import toast from "@/lib/toast"
import {
	type ExtraSlotState,
	useBuyExtraSlot,
	usePollSlotPurchase,
} from "@/queries/photos"
import { errorMessage } from "./upload-errors"

const FLOATING_BUTTON_CLASS =
	"fixed right-4 bottom-26 z-40 gap-2 rounded-full shadow-lg md:right-70 md:bottom-6"

/** Replaces the disabled "Already submitted" pill when the extra-slot
 *  feature is on, walks idle -> buying -> waiting for the STK push to be
 *  approved on the user's phone -> paid (the submit link takes over) or
 *  failed (back to idle so they can retry). */
function BuyExtraSlotButton({ extraSlot }: { extraSlot: ExtraSlotState }) {
	const queryClient = useQueryClient()
	const buyExtraSlot = useBuyExtraSlot()
	const [purchaseId, setPurchaseId] = useState<string | null>(
		extraSlot.pendingPurchaseId
	)
	const { data: purchase } = usePollSlotPurchase(purchaseId)
	const notifiedPaidRef = useRef(false)

	// The poll above stops once the purchase leaves "pending", but nothing
	// else reacts to a "paid" result landing. Without this, the button
	// sits on "Unlocking your extra slot..." forever instead of handing
	// back to the normal upload flow once extraSlot.hasPaidExtraSlot
	// actually refreshes.
	useEffect(() => {
		if (purchase?.status !== "paid" || notifiedPaidRef.current) {
			return
		}

		notifiedPaidRef.current = true
		toast.success("Payment received", {
			description: "Your extra slot is ready to use.",
		})
		queryClient.invalidateQueries({ queryKey: ["photos", "current"] })
	}, [purchase?.status, queryClient])

	function handleBuy() {
		buyExtraSlot.mutate(undefined, {
			onSuccess: ({ purchaseId: id }) => setPurchaseId(id),
			onError: (error) =>
				toast.error("Couldn't start the payment", {
					description: errorMessage(error, "Please try again."),
				}),
		})
	}

	if (purchaseId && purchase?.status !== "failed") {
		const pending = !purchase || purchase.status === "pending"

		return (
			<Button
				size="xl"
				disabled
				className={FLOATING_BUTTON_CLASS}>
				<Spinner className="size-4" />
				{pending
					? `Check your phone, approve KES ${extraSlot.price}`
					: "Unlocking your extra slot..."}
			</Button>
		)
	}

	return (
		<Button
			size="xl"
			disabled={buyExtraSlot.isPending}
			onClick={handleBuy}
			className={FLOATING_BUTTON_CLASS}>
			{buyExtraSlot.isPending ? (
				<Spinner className="size-4" />
			) : (
				<ShoppingCart className="size-4" />
			)}
			Buy extra slot @ KES {extraSlot.price}
		</Button>
	)
}

/** The compete page's floating entry point. Opens the create-post page
 *  while a slot is free; once every slot is used it becomes the "buy extra
 *  slot" control (when that feature is on) or a disabled pill. */
export function SubmitPhotoButton({
	disabled,
	extraSlot,
}: {
	disabled?: boolean
	extraSlot?: ExtraSlotState
}) {
	if (disabled) {
		if (extraSlot?.enabled && !extraSlot.hasPaidExtraSlot) {
			return <BuyExtraSlotButton extraSlot={extraSlot} />
		}

		return (
			<Button
				size="xl"
				disabled
				className={FLOATING_BUTTON_CLASS}>
				<Camera className="size-4" />
				Already submitted this week
			</Button>
		)
	}

	return (
		<Button
			asChild
			size="xl"
			className={FLOATING_BUTTON_CLASS}>
			<Link
				href="/photos/create"
				variant="unstyled">
				<Camera className="size-4" />
				Submit a photo
			</Link>
		</Button>
	)
}
