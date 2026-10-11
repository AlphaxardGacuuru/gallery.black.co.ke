import { isAxiosError } from "axios"
import { useNavigate } from "@tanstack/react-router"
import { type ReactNode, useState } from "react"
import { Head } from "@/lib/spa"
import Heading from "@/components/heading"
import {
	PhotoUploadField,
	type UploadsState,
} from "@/components/photos/PhotoUploadField"
import { Button } from "@/components/ui/button"
import { Input } from "@/components/ui/input"
import { Link } from "@/components/ui/link"
import { Skeleton } from "@/components/ui/skeleton"
import { Spinner } from "@/components/ui/spinner"
import { useApp } from "@/contexts/AppContext"
import toast from "@/lib/toast"
import {
	hasReachedEntryLimit,
	useCurrentCompetition,
	useSubmitPhoto,
} from "@/queries/photos"
import { edit } from "@/routes/profile"

const COMPETE_URL = "/compete"

/** Full-page version of what used to be the compete page's upload dialog:
 *  upload one or more photos, add a description, submit, then land back
 *  on the compete page where the new entry is already in the grid. */
export default function CreatePhoto() {
	const navigate = useNavigate()
	const { auth } = useApp()
	const { data, isLoading } = useCurrentCompetition()
	const submitPhoto = useSubmitPhoto()
	const [uploads, setUploads] = useState<UploadsState>({ ids: [], busy: false })
	const [caption, setCaption] = useState("")

	const activeCompetition =
		data?.competition?.status === "active" ? data.competition : undefined
	const extraPhoto = activeCompetition ? data?.extraPhoto : undefined
	const maxPhotos = extraPhoto?.maxPhotos ?? 1
	// Once the submission lands, the cache already counts it, which would
	// flip this page to "already submitted" for the moment before the
	// redirect takes effect.
	const reachedLimit =
		!submitPhoto.isSuccess && hasReachedEntryLimit(data, auth?.id)

	function handleSubmit() {
		if (uploads.ids.length === 0 || uploads.busy || !caption.trim()) {
			return
		}

		submitPhoto.mutate(
			{ temporaryUploadIds: uploads.ids, caption: caption.trim() },
			{
				onSuccess: () => {
					toast.success("Photo submitted to this week's challenge")
					navigate({ to: COMPETE_URL })
				},
				onError: (error) => {
					const message = isAxiosError<{ errors?: Record<string, string[]> }>(
						error
					)
						? (error.response?.data.errors?.temporaryUploadIds?.[0] ??
							error.response?.data.errors?.["temporaryUploadIds.0"]?.[0])
						: undefined

					toast.error(message ?? "Couldn't submit your photo")
				},
			}
		)
	}

	return (
		<>
			<Head title="Submit a photo" />

			<div className="mx-auto w-full max-w-xl space-y-6 pb-16 md:pb-20">
				<Heading
					title="Submit your photo"
					description="Entries are open while this week's challenge is live. One photo can be liked by anyone in the community."
				/>

				{isLoading ? (
					<Skeleton className="h-48 w-full" />
				) : !activeCompetition ? (
					<Notice>
						There&apos;s no challenge running right now, so you can&apos;t
						upload a photo until the next one opens. Check back soon.
					</Notice>
				) : !auth?.phone ? (
					<Notice>
						You need to add your M-Pesa phone number in your{" "}
						<Link
							href={edit().url}
							variant="text">
							profile
						</Link>{" "}
						before you can submit a photo, that&apos;s where your prize money is
						sent.
					</Notice>
				) : reachedLimit ? (
					<Notice>
						You&apos;ve already submitted this week. Head back to the challenge
						to see how your entry is doing.
					</Notice>
				) : (
					<div className="space-y-4">
						<PhotoUploadField
							maxPhotos={maxPhotos}
							extraPhoto={extraPhoto}
							onChange={setUploads}
						/>

						{maxPhotos > 1 && (
							<p className="text-xs text-muted-foreground">
								{uploads.ids.length}/{maxPhotos} photos selected
							</p>
						)}

						<Input
							type="text"
							value={caption}
							onChange={(event) => setCaption(event.target.value)}
							maxLength={280}
							label="Description"
							required={true}
						/>

						<div className="flex justify-end gap-2">
							<Button
								asChild
								variant="outline">
								<Link
									href={COMPETE_URL}
									variant="unstyled">
									Cancel
								</Link>
							</Button>
							<Button
								disabled={
									uploads.ids.length === 0 ||
									uploads.busy ||
									!caption.trim() ||
									submitPhoto.isPending
								}
								onClick={handleSubmit}>
								{submitPhoto.isPending && <Spinner className="size-4" />}
								Submit entry
							</Button>
						</div>
					</div>
				)}

				{!isLoading && (!activeCompetition || !auth?.phone || reachedLimit) && (
					<Link
						href={COMPETE_URL}
						variant="text">
						Back to this week&apos;s challenge
					</Link>
				)}
			</div>
		</>
	)
}

function Notice({ children }: { children: ReactNode }) {
	return (
		<p className="rounded-lg border bg-muted px-4 py-3 text-sm text-muted-foreground">
			{children}
		</p>
	)
}

CreatePhoto.layout = {
	breadcrumbs: [
		{ title: "This week's challenge", href: COMPETE_URL },
		{ title: "Submit a photo", href: "/photos/create" },
	],
}
