<?php

namespace App\Http\Services;

use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use App\Models\Photo;
use App\Models\PhotoCompetition;
use App\Models\PhotoCompetitionWinner;
use App\Models\PhotoSlotPurchase;
use App\Models\User;

class PhotoCompetitionService extends Service
{
	/**
	 * Everything the compete page needs: the current competition (active, or
	 * the latest ended one with winners), when the next one starts if none
	 * is running, the prize tiers, and this viewer's extra-slot state.
	 *
	 * @return array{competition: ?PhotoCompetition, nextStartsAt: ?string, prizeTiers: array, extraSlot: array, extraPhoto: array}
	 */
	public function currentState(Request $request): array
	{
		$competition = $this->current($request);
		$isActive = $competition?->status === PhotoCompetition::STATUS_ACTIVE;

		return [
			'competition' => $competition,
			'nextStartsAt' => $isActive ? null : PhotoCompetition::nextScheduledStart()->toIso8601String(),
			'prizeTiers' => PhotoCompetition::prizeTiers(),
			'extraSlot' => $this->extraSlotState($isActive ? $competition : null, $request),
			'extraPhoto' => $this->extraPhotoState($isActive ? $competition : null, $request),
		];
	}

	public function current(Request $request): ?PhotoCompetition
	{
		$active = PhotoCompetition::active()
			->with(['photos' => function ($query) use ($request) {
				$query->with(['user', 'images'])
					->withLikedByViewer($request->user())
					->orderByDesc('likes_count')
					->orderBy('created_at');
			}])
			->first();

		if ($active) {
			return $active;
		}

		return $this->mostRecentlyEnded($request);
	}

	/**
	 * The most recently ended competition, with its ranked winner photos
	 * loaded — kept on the compete page (with the winner treatment) until
	 * the next competition starts and takes over as the active one.
	 *
	 * Deliberately looks at the single latest-ended competition, not just
	 * the latest one that happens to have winners — a winnerless week (no
	 * entries, or every tier is unpaid) must show nothing rather than
	 * falling back to an older competition's winner, which would dangle a
	 * stale photo on the compete page well past when it actually won.
	 */
	protected function mostRecentlyEnded(Request $request): ?PhotoCompetition
	{
		$ended = PhotoCompetition::query()
			->where('status', PhotoCompetition::STATUS_ENDED)
			->latest('ends_at')
			->first();

		if (! $ended) {
			return null;
		}

		$winnerPhotoIds = $ended
			->winners()
			->pluck('photo_id')
			->filter()
			->values();

		if ($winnerPhotoIds->isEmpty()) {
			return null;
		}

		$photos = Photo::query()
			->whereIn('id', $winnerPhotoIds)
			->with(['user', 'images'])
			->withLikedByViewer($request->user())
			->withIsWinner()
			->get()
			->sortBy(fn(Photo $photo) => $winnerPhotoIds->search($photo->id))
			->values();

		$ended->setRelation('photos', $photos);

		return $ended;
	}

	/**
	 * The extra-slot feature's global on/off + price, plus (only once a
	 * competition is active) this viewer's own purchase state for it.
	 */
	protected function extraSlotState(?PhotoCompetition $activeCompetition, Request $request): array
	{
		$settings = PhotoCompetition::extraSlotSettings();

		$state = [
			'enabled' => $settings['enabled'],
			'price' => $settings['price'],
			'hasPaidExtraSlot' => false,
			'pendingPurchaseId' => null,
		];

		if (! $activeCompetition) {
			return $state;
		}

		$purchase = PhotoSlotPurchase::ofType(PhotoSlotPurchase::TYPE_EXTRA_ENTRY)
			->where('competition_id', $activeCompetition->id)
			->where('user_id', $request->user()->id)
			->latest('created_at')
			->first();

		if ($purchase) {
			$state['hasPaidExtraSlot'] = $purchase->status === PhotoSlotPurchase::STATUS_PAID;
			$state['pendingPurchaseId'] = $purchase->status === PhotoSlotPurchase::STATUS_PENDING
				&& $purchase->created_at->greaterThanOrEqualTo(now()->subMinutes(PhotoSlotPurchase::PENDING_TTL_MINUTES))
				? $purchase->id
				: null;
		}

		return $state;
	}

	/**
	 * The extra-photo feature's global on/off + price, plus (only once a
	 * competition is active) how many slots this viewer has already paid
	 * for and the resulting cap on how many photos they can attach.
	 */
	protected function extraPhotoState(?PhotoCompetition $activeCompetition, Request $request): array
	{
		$settings = PhotoCompetition::extraPhotoSettings();

		$state = [
			'enabled' => $settings['enabled'],
			'price' => $settings['price'],
			'paidPhotoSlotsCount' => 0,
			'pendingPurchaseId' => null,
			'maxPhotos' => 1,
			'maxPhotosCap' => Photo::MAX_IMAGES_PER_ENTRY,
		];

		if (! $activeCompetition) {
			return $state;
		}

		$userId = $request->user()->id;

		$state['paidPhotoSlotsCount'] = PhotoSlotPurchase::paidExtraPhotoCount($activeCompetition->id, $userId);
		$state['maxPhotos'] = Photo::maxImagesFor($activeCompetition->id, $userId);

		$pendingPurchase = PhotoSlotPurchase::ofType(PhotoSlotPurchase::TYPE_EXTRA_PHOTO)
			->where('competition_id', $activeCompetition->id)
			->where('user_id', $userId)
			->freshPending()
			->latest('created_at')
			->first();

		$state['pendingPurchaseId'] = $pendingPurchase?->id;

		return $state;
	}

	/**
	 * Discovery grid: photos from ended competitions, latest first.
	 */
	public function discover(Request $request): LengthAwarePaginator
	{
		$photos = Photo::query()
			->whereHas('competition', fn($query) => $query->where('status', PhotoCompetition::STATUS_ENDED))
			->with(['user', 'images'])
			->withLikedByViewer($request->user())
			->withIsWinner()
			->latest('created_at')
			->paginate(30);

		return $photos;
	}

	/**
	 * Highest placement a leaderboard counts (prize tiers stop at 10).
	 */
	public const LEADERBOARD_POSITIONS = 10;

	/**
	 * Every user who has posted (seed accounts included), ranked by their placements: most 1st
	 * places first, ties broken by most 2nd places, then 3rd, and so on.
	 * Anyone still tied falls back to how many photos they've posted.
	 */
	public function leaderboard(): LengthAwarePaginator
	{
		$positions = range(1, self::LEADERBOARD_POSITIONS);

		$placements = PhotoCompetitionWinner::query()
			->select('user_id')
			->groupBy('user_id');

		foreach ($positions as $position) {
			$placements->selectRaw(
				"SUM(CASE WHEN position = ? THEN 1 ELSE 0 END) AS position_{$position}_count",
				[$position]
			);
		}

		$users = User::query()
			->whereHas('photos')
			->leftJoinSub($placements, 'placements', 'placements.user_id', '=', 'users.id')
			->select('users.*')
			->withCount('photos');

		foreach ($positions as $position) {
			$column = "position_{$position}_count";

			$users->selectRaw("COALESCE(placements.{$column}, 0) AS {$column}")
				->orderByDesc($column);
		}

		return $users->orderByDesc('photos_count')
			->orderBy('users.id')
			->paginate(50);
	}
}
