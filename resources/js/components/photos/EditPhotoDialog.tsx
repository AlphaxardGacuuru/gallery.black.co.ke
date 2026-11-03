import { isAxiosError } from "axios"
import { useEffect, useState } from "react"
import { Button } from "@/components/ui/button"
import {
	Dialog,
	DialogContent,
	DialogDescription,
	DialogFooter,
	DialogHeader,
	DialogTitle,
} from "@/components/ui/dialog"
import { Input } from "@/components/ui/input"
import { Spinner } from "@/components/ui/spinner"
import toast from "@/lib/toast"
import {
	useAddPhotos,
	useCurrentCompetition,
	useUpdatePhoto,
} from "@/queries/photos"
import type { Photo } from "@/types/photo"
import {
	BuyExtraPhotoSlotRow,
	PhotoUploadField,
	type UploadsState,
} from "./PhotoUploadField"

function validationMessage(error: unknown): string | undefined {
	if (!isAxiosError<{ errors?: Record<string, string[]> }>(error)) {
		return undefined
	}

	const errors = error.response?.data.errors

	return (
		errors?.caption?.[0] ??
		errors?.temporaryUploadIds?.[0] ??
		errors?.["temporaryUploadIds.0"]?.[0]
	)
}

/**
 * Lets the owner of an already-submitted entry edit its description and,
 * while it has room (or after buying another photo slot right here), add
 * more photos to its carousel without deleting and resubmitting. Only
 * offered while the entry's challenge is still active.
 */
export function EditPhotoDialog({
	photo,
	open,
	onOpenChange,
}: {
	photo: Photo
	open: boolean
	onOpenChange: (open: boolean) => void
}) {
	const { data: current } = useCurrentCompetition()
	const extraPhoto = current?.extraPhoto
	const [caption, setCaption] = useState(photo.caption ?? "")
	const [uploads, setUploads] = useState<UploadsState>({ ids: [], busy: false })
	const updatePhoto = useUpdatePhoto(photo.id)
	const addPhotos = useAddPhotos(photo.id)

	// Start from the saved description each time the dialog opens.
	useEffect(() => {
		if (open) {
			setCaption(photo.caption ?? "")
		}
	}, [open])

	const photoCount = 1 + photo.images.length
	const maxPhotos = extraPhoto?.maxPhotos ?? 1
	const remaining = maxPhotos - photoCount
	const canBuyMore =
		Boolean(extraPhoto?.enabled) && maxPhotos < (extraPhoto?.maxPhotosCap ?? 1)
	const captionChanged = caption.trim() !== (photo.caption ?? "").trim()
	const saving = updatePhoto.isPending || addPhotos.isPending

	async function handleSave() {
		if (!caption.trim() || uploads.busy) {
			return
		}

		try {
			if (captionChanged) {
				await updatePhoto.mutateAsync(caption.trim())
			}

			if (uploads.ids.length > 0) {
				await addPhotos.mutateAsync(uploads.ids)
			}

			toast.success("Entry updated")
			setUploads({ ids: [], busy: false })
			onOpenChange(false)
		} catch (error) {
			toast.error(validationMessage(error) ?? "Couldn't update your entry")
		}
	}

	return (
		<Dialog
			open={open}
			onOpenChange={onOpenChange}>
			<DialogContent>
				<DialogHeader>
					<DialogTitle>Edit entry</DialogTitle>
					<DialogDescription>
						Change the description, or add more photos to this entry.
					</DialogDescription>
				</DialogHeader>

				<div className="space-y-4">
					{/* Description Start */}
					<Input
						type="text"
						value={caption}
						onChange={(event) => setCaption(event.target.value)}
						maxLength={280}
						label="Description"
						required={true}
					/>
					{/* Description End */}

					{remaining > 0 ? (
						<div className="space-y-2">
							<p className="text-sm text-muted-foreground">
								You can add up to {remaining} more photo
								{remaining === 1 ? "" : "s"} ({photoCount}/{maxPhotos} used).
							</p>
							{/* Photos Start */}
							<PhotoUploadField
								maxPhotos={remaining}
								extraPhoto={extraPhoto}
								onChange={setUploads}
							/>
							{/* Photos End */}
						</div>
					) : (
						extraPhoto &&
						canBuyMore && (
							<div className="space-y-2">
								<p className="text-sm text-muted-foreground">
									This entry has {photoCount} of {maxPhotos} photo
									{maxPhotos === 1 ? "" : "s"}. Buy another slot to add more.
								</p>
								<BuyExtraPhotoSlotRow extraPhoto={extraPhoto} />
							</div>
						)
					)}
				</div>

				<DialogFooter className="gap-2">
					<Button
						variant="secondary"
						onClick={() => onOpenChange(false)}>
						Cancel
					</Button>
					<Button
						disabled={
							!caption.trim() ||
							uploads.busy ||
							saving ||
							(!captionChanged && uploads.ids.length === 0)
						}
						onClick={handleSave}>
						{saving && <Spinner className="size-4" />}
						Save
					</Button>
				</DialogFooter>
			</DialogContent>
		</Dialog>
	)
}
