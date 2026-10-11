import { isCancel } from "axios"
import FilePondPluginFileValidateSize from "filepond-plugin-file-validate-size"
import FilePondPluginFileValidateType from "filepond-plugin-file-validate-type"
import FilePondPluginImageExifOrientation from "filepond-plugin-image-exif-orientation"
import FilePondPluginImagePreview from "filepond-plugin-image-preview"
import { ShoppingCart } from "lucide-react"
import { useEffect, useRef, useState } from "react"
import { useQueryClient } from "@tanstack/react-query"
import { FilePond, FileStatus, registerPlugin } from "react-filepond"
import FilePondController from "@/actions/App/Http/Controllers/FilePondController"
import { Button } from "@/components/ui/button"
import { Spinner } from "@/components/ui/spinner"
import Axios from "@/lib/axios"
import toast from "@/lib/toast"
import {
	type ExtraPhotoState,
	useBuyExtraPhotoSlot,
	usePollSlotPurchase,
} from "@/queries/photos"
import { errorMessage, filePondErrorMessage } from "./upload-errors"

import "filepond/dist/filepond.min.css"
import "filepond-plugin-image-preview/dist/filepond-plugin-image-preview.css"

registerPlugin(
	FilePondPluginFileValidateSize,
	FilePondPluginFileValidateType,
	FilePondPluginImageExifOrientation,
	FilePondPluginImagePreview
)

export type UploadsState = { ids: number[]; busy: boolean }

const BUSY_STATUSES = [
	FileStatus.PROCESSING,
	FileStatus.PROCESSING_QUEUED,
	FileStatus.IDLE,
]

/**
 * The FilePond instance shared by the create-post page (a fresh submission)
 * and AddPhotosDialog (adding to one already submitted), plus the inline "buy
 * another slot" control, since both need the same multi-file upload
 * tracking and the same way to unlock more capacity mid-upload.
 */
export function PhotoUploadField({
	maxPhotos,
	extraPhoto,
	onChange,
}: {
	maxPhotos: number
	extraPhoto?: ExtraPhotoState
	onChange: (state: UploadsState) => void
}) {
	const pondRef = useRef<FilePond>(null)

	function syncUploads() {
		const files = pondRef.current?.getFiles() ?? []

		onChange({
			// On-screen order matters here: the first id becomes the entry's
			// cover image (see PhotoService::store()), and FilePond's
			// allowReorder lets the user drag to pick which one that is.
			ids: files
				.filter((file) => file.status === FileStatus.PROCESSING_COMPLETE)
				.map((file) => Number(file.serverId)),
			busy: files.some((file) => BUSY_STATUSES.includes(file.status)),
		})
	}

	useEffect(() => {
		// Guarantees the parent's mirrored state starts empty whenever this
		// field (re)mounts, e.g. a dialog reopening after it was closed.
		onChange({ ids: [], busy: false })
	}, [])

	return (
		<div className="space-y-3">
			<FilePond
				ref={pondRef}
				allowMultiple
				allowReorder
				maxFiles={maxPhotos}
				// A plain "image/*" (rather than a specific MIME list) is
				// what makes Chrome on Android open its full Photos picker
				// (including Google Photos) instead of falling back to the
				// bare system Files browser.
				acceptedFileTypes={["image/*"]}
				maxFileSize="25MB"
				credits={false}
				labelIdle={`<span class="filepond--label-action">Choose ${maxPhotos > 1 ? `up to ${maxPhotos} photos` : "a photo"}</span> or drag and drop`}
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
							.then((response) => load(String(response.data)))
							.catch((requestError) => {
								if (isCancel(requestError)) {
									return
								}
								error(filePondErrorMessage(requestError, "Upload failed"))
							})

						return {
							abort: () => {
								controller.abort()
								abort()
							},
						}
					},
					revert: (uniqueFileId, load, error) => {
						Axios.delete(FilePondController.destroyPhoto.url(uniqueFileId))
							.then(() => load())
							.catch((requestError) =>
								error(
									filePondErrorMessage(requestError, "Could not remove upload")
								)
							)
					},
				}}
				onprocessfile={syncUploads}
				onremovefile={syncUploads}
				onreorderfiles={syncUploads}
				onupdatefiles={syncUploads}
				name="filepond-photo"
			/>
			{extraPhoto?.enabled && maxPhotos < extraPhoto.maxPhotosCap && (
				<BuyExtraPhotoSlotRow extraPhoto={extraPhoto} />
			)}
		</div>
	)
}

/** Unlike the entry-level "buy extra slot" button, this is repeatable: once
 *  a purchase settles (paid or failed), the control resets to idle so
 *  another slot can be bought right away, up to the overall cap. */
function BuyExtraPhotoSlotRow({ extraPhoto }: { extraPhoto: ExtraPhotoState }) {
	const queryClient = useQueryClient()
	const buyExtraPhotoSlot = useBuyExtraPhotoSlot()
	const [purchaseId, setPurchaseId] = useState<string | null>(
		extraPhoto.pendingPurchaseId
	)
	const { data: purchase } = usePollSlotPurchase(purchaseId)
	const notifiedPurchaseIdRef = useRef<string | null>(null)

	useEffect(() => {
		if (
			purchase?.status !== "paid" ||
			notifiedPurchaseIdRef.current === purchaseId
		) {
			return
		}

		notifiedPurchaseIdRef.current = purchaseId
		toast.success("Photo slot unlocked")
		queryClient.invalidateQueries({ queryKey: ["photos", "current"] })
		setPurchaseId(null)
	}, [purchase?.status, purchaseId, queryClient])

	function handleBuy() {
		buyExtraPhotoSlot.mutate(undefined, {
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
			<div className="flex items-center gap-2 text-sm text-muted-foreground">
				<Spinner className="size-4" />
				{pending
					? `Check your phone, approve KES ${extraPhoto.price}`
					: "Unlocking your photo slot..."}
			</div>
		)
	}

	return (
		<Button
			type="button"
			variant="outline"
			size="sm"
			disabled={buyExtraPhotoSlot.isPending}
			onClick={handleBuy}>
			{buyExtraPhotoSlot.isPending ? (
				<Spinner className="size-4" />
			) : (
				<ShoppingCart className="size-4" />
			)}
			Add another photo slot (KES {extraPhoto.price})
		</Button>
	)
}
