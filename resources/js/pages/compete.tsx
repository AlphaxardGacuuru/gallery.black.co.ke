import { Trophy } from "lucide-react"
import { Head } from "@/lib/spa"
import { CompetitionCountdown } from "@/components/photos/CompetitionCountdown"
import { PhotoCard } from "@/components/photos/PhotoCard"
import { UploadPhotoDialog } from "@/components/photos/UploadPhotoDialog"
import Heading from "@/components/heading"
import { Skeleton } from "@/components/ui/skeleton"
import { useCurrentCompetition } from "@/queries/photos"

export default function Compete() {
	const { data: competition, isLoading } = useCurrentCompetition()

	return (
		<>
			<Head title="This week's challenge" />

			<div className="space-y-6">
				<div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
					<Heading
						variant="small"
						title="This week's challenge"
						description={
							competition
								? `KES ${competition.prizeAmount} to the most-liked photo`
								: "Submissions open Monday, close Friday at 8pm"
						}
					/>
					<UploadPhotoDialog />
				</div>

				{isLoading ? (
					<Skeleton className="h-24 w-full max-w-md" />
				) : competition ? (
					<CompetitionCountdown endsAt={competition.endsAt} />
				) : null}

				{isLoading ? (
					<div className="grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-4">
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
					<div className="grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-4">
						{competition.photos.map((photo) => (
							<PhotoCard
								key={photo.id}
								photo={photo}
							/>
						))}
					</div>
				)}
			</div>
		</>
	)
}
