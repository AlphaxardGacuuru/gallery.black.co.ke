export type Photo = {
	id: string
	competitionId: string
	userId: string
	userName: string | null
	userAvatar: string | null
	url: string
	thumbnailUrl: string
	caption: string | null
	width: number | null
	height: number | null
	aspectRatio: number
	likesCount: number
	isLikedByViewer: boolean
	createdAt: string
}

export type PhotoCompetition = {
	id: string
	startsAt: string
	endsAt: string
	status: "active" | "ended"
	prizeAmount: number
	winnerPhotoId: string | null
	photos: Photo[]
}
