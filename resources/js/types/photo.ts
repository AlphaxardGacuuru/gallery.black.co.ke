export type PhotoImage = {
	id: string
	url: string
	thumbnailUrl: string
	width: number | null
	height: number | null
}

export type Photo = {
	id: string
	competitionId: string
	userId: string
	userName: string | null
	userAvatar: string | null
	userVerified: boolean
	url: string
	thumbnailUrl: string
	caption: string | null
	width: number | null
	height: number | null
	aspectRatio: number
	images: PhotoImage[]
	likesCount: number
	isLikedByViewer: boolean
	isWinner: boolean
	position: number | null
	/** Whether this photo can be sold at all (an ended competition's entry
	 *  that isn't an Unsplash seed photo). The viewer's own photos are still
	 *  sellable, the UI just doesn't offer to sell them back to them. */
	isSellable: boolean
	isPurchasedByViewer: boolean
	createdAt: string
}

export type PhotoCompetition = {
	id: string
	startsAt: string
	endsAt: string
	status: "active" | "ended"
	photos: Photo[]
}
