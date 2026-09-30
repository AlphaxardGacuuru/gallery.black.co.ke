import { isAxiosError, isCancel } from "axios"
import FilePondPluginFileValidateSize from "filepond-plugin-file-validate-size"
import FilePondPluginFileValidateType from "filepond-plugin-file-validate-type"
import FilePondPluginImageExifOrientation from "filepond-plugin-image-exif-orientation"
import FilePondPluginImagePreview from "filepond-plugin-image-preview"
import { Camera, ShoppingCart } from "lucide-react"
import { useEffect, useRef, useState } from "react"
import { useQueryClient } from "@tanstack/react-query"
import { FilePond, registerPlugin } from "react-filepond"
import FilePondController from "@/actions/App/Http/Controllers/FilePondController"
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
import { Spinner } from "@/components/ui/spinner"
import Axios from "@/lib/axios"
import toast from "@/lib/toast"
import {
	type ExtraSlotState,
	useBuyExtraSlot,
	usePollSlotPurchase,
	useSubmitPhoto,
} from "@/queries/photos"
import { edit } from "@/routes/profile"

import "filepond/dist/filepond.min.css"
import "filepond-plugin-image-preview/dist/filepond-plugin-image-preview.css"
import { Input } from "../ui/input"

registerPlugin(
	FilePondPluginFileValidateSize,
	FilePondPluginFileValidateType,
	FilePondPluginImageExifOrientation,
	FilePondPluginImagePreview
)

function errorMessage(error: unknown, fallback: string): string {
	if (
		isAxiosError<{ message?: string; errors?: Record<string, string[]> }>(error)
	) {
		return (
			error.response?.data.errors?.competition?.[0] ??
			error.response?.data.message ??
			fallback
		)
	}

	return error instanceof Error ? error.message : fallback
}

/** Replaces the disabled "Already submitted" pill when the extra-slot
 *  feature is on, walks idle -> buying -> waiting for the STK push to be
 *  approved on the user's phone -> paid (upload dialog takes over) or
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
				className="fixed right-4 bottom-26 z-40 gap-2 rounded-full shadow-lg md:right-70 md:bottom-6">
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
			className="fixed right-4 bottom-26 z-40 gap-2 rounded-full shadow-lg md:right-70 md:bottom-6">
			{buyExtraSlot.isPending ? (
				<Spinner className="size-4" />
			) : (
				<ShoppingCart className="size-4" />
			)}
			Buy extra slot @ KES {extraSlot.price}
		</Button>
	)
}

export function UploadPhotoDialog({
	disabled,
	hasActiveCompetition = true,
	hasPhoneNumber = true,
	extraSlot,
}: {
	disabled?: boolean
	hasActiveCompetition?: boolean
	hasPhoneNumber?: boolean
	extraSlot?: ExtraSlotState
}) {
	const [open, setOpen] = useState(false)
	const [temporaryUploadId, setTemporaryUploadId] = useState<number | null>(
		null
	)
	const [caption, setCaption] = useState("")
	const submitPhoto = useSubmitPhoto()

	function reset() {
		setTemporaryUploadId(null)
		setCaption("")
	}

	function handleSubmit() {
		if (!temporaryUploadId || !caption.trim()) {
			return
		}

		submitPhoto.mutate(
			{ temporaryUploadId, caption: caption.trim() },
			{
				onSuccess: () => {
					toast.success("Photo submitted to this week's challenge")
					reset()
					setOpen(false)
				},
				onError: (error) => {
					const message = isAxiosError<{ errors?: Record<string, string[]> }>(
						error
					)
						? error.response?.data.errors?.temporaryUploadId?.[0]
						: undefined

					toast.error(message ?? "Couldn't submit your photo")
				},
			}
		)
	}

	if (disabled) {
		if (extraSlot?.enabled && !extraSlot.hasPaidExtraSlot) {
			return <BuyExtraSlotButton extraSlot={extraSlot} />
		}

		return (
			<Button
				size="xl"
				disabled
				className="fixed right-4 bottom-26 z-40 gap-2 rounded-full shadow-lg md:right-70 md:bottom-6">
				<Camera className="size-4" />
				Already submitted this week
			</Button>
		)
	}

	return (
		<Dialog
			open={open}
			onOpenChange={(next) => {
				setOpen(next)
				if (!next) {
					reset()
				}
			}}>
			<DialogTrigger asChild>
				<Button
					size="xl"
					className="fixed right-4 bottom-26 z-40 gap-2 rounded-full shadow-lg md:right-70 md:bottom-6">
					<Camera className="size-4" />
					Submit a photo
				</Button>
			</DialogTrigger>
			<DialogContent>
				<DialogHeader>
					<DialogTitle>Submit your photo</DialogTitle>
					<DialogDescription>
						{!hasActiveCompetition
							? "There's no challenge running right now."
							: !hasPhoneNumber
								? "Add your M-Pesa phone number before entering."
								: "Entries are open while this week's challenge is live. One photo can be liked by anyone in the community."}
					</DialogDescription>
				</DialogHeader>

				{!hasActiveCompetition ? (
					<p className="rounded-lg border bg-muted px-4 py-3 text-sm text-muted-foreground">
						You can&apos;t upload a photo until the next challenge opens. Check
						back soon.
					</p>
				) : !hasPhoneNumber ? (
					<p className="rounded-lg border bg-muted px-4 py-3 text-sm text-muted-foreground">
						You need to add your M-Pesa phone number in your{" "}
						<Link
							href={edit().url}
							variant="text"
							onClick={() => setOpen(false)}>
							profile
						</Link>{" "}
						before you can submit a photo, that&apos;s where your prize money is
						sent.
					</p>
				) : (
					<>
						<FilePond
							allowMultiple={false}
							// A plain "image/*" (rather than a specific MIME list) is
							// what makes Chrome on Android open its full Photos
							// picker — including Google Photos as a source — instead
							// of falling back to the bare system Files browser.
							acceptedFileTypes={["image/*"]}
							maxFileSize="25MB"
							credits={false}
							labelIdle='<span class="filepond--label-action">Choose a photo</span> or drag and drop'
							server={{
								process: (
									fieldName,
									file,
									_metadata,
									load,
									error,
									progress,
									abort
								) => {
									const controller = new AbortController()
									const formData = new FormData()
									formData.append(fieldName, file, file.name)

									Axios.post(FilePondController.storePhoto.url(), formData, {
										signal: controller.signal,
										onUploadProgress: (event) => {
											if (event.total) {
												progress(true, event.loaded, event.total)
											}
										},
									})
										.then((response) => {
											setTemporaryUploadId(Number(response.data))
											load(String(response.data))
										})
										.catch((requestError) => {
											if (isCancel(requestError)) {
												return
											}
											error("Upload failed")
										})

									return {
										abort: () => {
											controller.abort()
											abort()
										},
									}
								},
								revert: (uniqueFileId, load, error) => {
									Axios.delete(
										FilePondController.destroyPhoto.url(uniqueFileId)
									)
										.then(() => load())
										.catch(() => error("Could not remove upload"))
								},
							}}
							onremovefile={() => setTemporaryUploadId(null)}
							name="filepond-photo"
						/>

						<Input
							type="text"
							value={caption}
							onChange={(event) => setCaption(event.target.value)}
							maxLength={280}
							label="Description"
							required={true}
						/>
					</>
				)}

				<DialogFooter>
					{hasActiveCompetition && hasPhoneNumber ? (
						<Button
							disabled={
								!temporaryUploadId || !caption.trim() || submitPhoto.isPending
							}
							onClick={handleSubmit}>
							{submitPhoto.isPending && <Spinner className="size-4" />}
							Submit entry
						</Button>
					) : (
						<Button
							variant="outline"
							onClick={() => setOpen(false)}>
							Close
						</Button>
					)}
				</DialogFooter>
			</DialogContent>
		</Dialog>
	)
}
