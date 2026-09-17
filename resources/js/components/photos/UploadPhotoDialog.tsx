import { isAxiosError, isCancel } from "axios"
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
import { Link } from "@/components/ui/link"
import Axios from "@/lib/axios"
import toast from "@/lib/toast"
import { useSubmitPhoto } from "@/queries/photos"
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

export function UploadPhotoDialog({
	disabled,
	hasActiveCompetition = true,
	hasPhoneNumber = true,
}: {
	disabled?: boolean
	hasActiveCompetition?: boolean
	hasPhoneNumber?: boolean
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
						before you can submit a photo, that&apos;s where your prize money
						is sent.
					</p>
				) : (
					<>
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
								!temporaryUploadId ||
								!caption.trim() ||
								submitPhoto.isPending
							}
							onClick={handleSubmit}>
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
