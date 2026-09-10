import { isCancel } from "axios"
import FilePondPluginFileValidateSize from "filepond-plugin-file-validate-size"
import FilePondPluginFileValidateType from "filepond-plugin-file-validate-type"
import FilePondPluginImageExifOrientation from "filepond-plugin-image-exif-orientation"
import FilePondPluginImagePreview from "filepond-plugin-image-preview"
import { Camera } from "lucide-react"
import { useState } from "react"
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
import Axios from "@/lib/axios"
import toast from "@/lib/toast"
import { useSubmitPhoto } from "@/queries/photos"

import "filepond/dist/filepond.min.css"
import "filepond-plugin-image-preview/dist/filepond-plugin-image-preview.css"

registerPlugin(
	FilePondPluginFileValidateSize,
	FilePondPluginFileValidateType,
	FilePondPluginImageExifOrientation,
	FilePondPluginImagePreview
)

export function UploadPhotoDialog() {
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
		if (!temporaryUploadId) {
			return
		}

		submitPhoto.mutate(
			{ temporaryUploadId, caption: caption.trim() || undefined },
			{
				onSuccess: () => {
					toast.success("Photo submitted to this week's challenge")
					reset()
					setOpen(false)
				},
				onError: () => toast.error("Couldn't submit your photo"),
			}
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
				<Button className="gap-2">
					<Camera className="size-4" />
					Submit a photo
				</Button>
			</DialogTrigger>
			<DialogContent>
				<DialogHeader>
					<DialogTitle>Submit your photo</DialogTitle>
					<DialogDescription>
						Entries are open until Friday 8pm. One photo can be liked by anyone
						in the community.
					</DialogDescription>
				</DialogHeader>

				<FilePond
					allowMultiple={false}
					acceptedFileTypes={["image/png", "image/jpeg", "image/webp"]}
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
							Axios.delete(FilePondController.destroyPhoto.url(uniqueFileId))
								.then(() => load())
								.catch(() => error("Could not remove upload"))
						},
					}}
					onremovefile={() => setTemporaryUploadId(null)}
					name="filepond-photo"
				/>

				<input
					type="text"
					value={caption}
					onChange={(event) => setCaption(event.target.value)}
					maxLength={280}
					placeholder="Add a caption (optional)"
					className="w-full rounded-md border bg-background px-3 py-2 text-sm"
				/>

				<DialogFooter>
					<Button
						disabled={!temporaryUploadId || submitPhoto.isPending}
						onClick={handleSubmit}>
						Submit entry
					</Button>
				</DialogFooter>
			</DialogContent>
		</Dialog>
	)
}
