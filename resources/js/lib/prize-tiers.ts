export const PRIZE_TIER_ORDINALS = [
	"1st",
	"2nd",
	"3rd",
	"4th",
	"5th",
	"6th",
	"7th",
	"8th",
	"9th",
	"10th",
]

// Mirrors the backend's EndPhotoCompetition/notification convention: tiers
// are contiguous and descending, so the first non-positive one means
// nothing further is paid either.
export function activePrizeTiers(tiers: number[]): number[] {
	const active: number[] = []

	for (const amount of tiers) {
		if (amount <= 0) {
			break
		}

		active.push(amount)
	}

	return active
}
