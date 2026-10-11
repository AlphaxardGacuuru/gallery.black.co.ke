import { isAxiosError } from "axios"
import { ImagePlus } from "lucide-react"
import { useState } from "react"
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
import { Spinner } from "@/components/ui/spinner"
import toast from "@/lib/toast"
import { type ExtraPhotoState, useAddPhotos } from "@/queries/photos"
import type { Photo } from "@/types/photo"
import { PhotoUploadField, type UploadsState } from "./PhotoUploadField"

/**
 * Lets the owner of an already-submitted entry attach more photos to it
 * (e.g. after buying an extra slot), without deleting and resubmitting.
 * Only renders when there's actually remaining capacity to fill.
 */
export function AddPhotosDialog({
	photo,
	extraPhoto,
}: {
	photo: Photo
	extraPhoto: ExtraPhotoState
}) {
	const [open, setOpen] = useState(false)
	const [uploads, setUploads] = useState<UploadsState>({ ids: [], busy: false })
	const addPhotos = useAddPhotos(photo.id)

	const remaining = extraPhoto.maxPhotos - (1 + photo.images.length)

	if (remaining <= 0) {
		return null
	}

	function handleSubmit() {
		if (uploads.ids.length === 0 || uploads.busy) {
			return
		}

		addPhotos.mutate(uploads.ids, {
			onSuccess: () => {
				toast.success("Photos added to your entry")
				setUploads({ ids: [], busy: false })
				setOpen(false)
			},
			onError: (error) => {
				const message = isAxiosError<{ errors?: Record<string, string[]> }>(
					error
				)
					? (error.response?.data.errors?.temporaryUploadIds?.[0] ??
						error.response?.data.errors?.["temporaryUploadIds.0"]?.[0])
					: undefined

				toast.error(message ?? "Couldn't add photos")
			},
		})
	}

	return (
		<Dialog
			open={open}
			onOpenChange={setOpen}>
			<DialogTrigger asChild>
				<Button
					variant="outline"
					size="sm"
					className="text-white hover:bg-white/10 hover:text-white">
					<ImagePlus className="size-4" />
					Add photos
				</Button>
			</DialogTrigger>
			<DialogContent>
				<DialogHeader>
					<DialogTitle>Add more photos</DialogTitle>
					<DialogDescription>
						You can add up to {remaining} more photo{remaining === 1 ? "" : "s"}{" "}
						to this entry.
					</DialogDescription>
				</DialogHeader>

				<PhotoUploadField
					maxPhotos={remaining}
					extraPhoto={extraPhoto}
					onChange={setUploads}
				/>

				<DialogFooter>
					<Button
						disabled={
							uploads.ids.length === 0 || uploads.busy || addPhotos.isPending
						}
						onClick={handleSubmit}>
						{addPhotos.isPending && <Spinner className="size-4" />}
						Add photos
					</Button>
				</DialogFooter>
			</DialogContent>
		</Dialog>
	)
}
