import { Trophy } from "lucide-react"
import { Head } from "@/lib/spa"
import { CompetitionCountdown } from "@/components/photos/CompetitionCountdown"
import { NextCompetitionCountdown } from "@/components/photos/NextCompetitionCountdown"
import { PhotoCard } from "@/components/photos/PhotoCard"
import { UploadPhotoDialog } from "@/components/photos/UploadPhotoDialog"
import { Skeleton } from "@/components/ui/skeleton"
import { useApp } from "@/contexts/AppContext"
import { useCurrentCompetition } from "@/queries/photos"

export default function Compete() {
	const { auth } = useApp()
	const { data, isLoading } = useCurrentCompetition()
	const competition = data?.competition
	const hasSubmitted = competition?.photos.some(
		(photo) => String(photo.userId) === String(auth?.id)
	)

	return (
		<>
			<Head title="This week's challenge" />

			<div className="space-y-6">
				<header className="mb-8 space-y-1">
					<h2 className="text-3xl font-bold tracking-tight sm:text-4xl">
						This week&apos;s challenge
					</h2>
					<p className="text-xl text-muted-foreground">
						{competition ? (
							<>
								<span className="text-5xl font-bold text-green-600">
									KES {competition.prizeAmount}
								</span>{" "}
								to the most liked photo
							</>
						) : (
							"No challenge is running right now, check back soon."
						)}
					</p>
				</header>

				<div className="mx-auto w-[80vw]">
					{isLoading ? (
						<Skeleton className="h-24 w-full max-w-md" />
					) : competition ? (
						<CompetitionCountdown endsAt={competition.endsAt} />
					) : data?.nextStartsAt ? (
						<NextCompetitionCountdown startsAt={data.nextStartsAt} />
					) : null}
				</div>

				{isLoading ? (
					<div className="grid grid-cols-1 gap-4 sm:grid-cols-3 lg:grid-cols-4">
						{Array.from({ length: 8 }).map((_, index) => (
							<Skeleton
								key={index}
								className="aspect-square"
							/>
						))}
					</div>
				) : !competition || competition.photos.length === 0 ? (
					<div className="flex flex-col items-center gap-2 rounded-xl border border-dashed py-16 text-center text-muted-foreground">
						<Trophy className="size-8" />
						<p className="font-medium">No entries yet</p>
						<p className="text-sm">Be the first to submit a photo this week.</p>
					</div>
				) : (
					<div className="grid grid-cols-1 gap-4 sm:grid-cols-3 lg:grid-cols-4">
						{competition.photos.map((photo) => (
							<PhotoCard
								key={photo.id}
								photo={photo}
							/>
						))}
					</div>
				)}
			</div>

			<UploadPhotoDialog
				disabled={hasSubmitted}
				hasActiveCompetition={Boolean(competition)}
			/>
		</>
	)
}
