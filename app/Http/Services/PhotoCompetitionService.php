<?php

namespace App\Http\Services;

use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use App\Models\Photo;
use App\Models\PhotoCompetition;
use App\Models\PhotoSlotPurchase;

class PhotoCompetitionService extends Service
{
	/**
	 * Everything the compete page needs: the current competition (active, or
	 * the latest ended one with winners), when the next one starts if none
	 * is running, the prize tiers, and this viewer's extra-slot state.
	 *
	 * @return array{competition: ?PhotoCompetition, nextStartsAt: ?string, prizeTiers: array, extraSlot: array}
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
		];
	}

	public function current(Request $request): ?PhotoCompetition
	{
		$active = PhotoCompetition::active()
			->with(['photos' => function ($query) use ($request) {
				$query->with('user')
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
			->with('user')
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

		$purchase = PhotoSlotPurchase::where('competition_id', $activeCompetition->id)
			->where('user_id', $request->user()->id)
			->latest('created_at')
			->first();

		if ($purchase) {
			$state['hasPaidExtraSlot'] = $purchase->status === PhotoSlotPurchase::STATUS_PAID;
			$state['pendingPurchaseId'] = $purchase->status === PhotoSlotPurchase::STATUS_PENDING
				? $purchase->id
				: null;
		}

		return $state;
	}

	/**
	 * Discovery grid: photos from ended competitions, latest first.
	 */
	public function discover(Request $request): LengthAwarePaginator
	{
		$photos = Photo::query()
			->whereHas('competition', fn($query) => $query->where('status', PhotoCompetition::STATUS_ENDED))
			->with('user')
			->withLikedByViewer($request->user())
			->withIsWinner()
			->latest('created_at')
			->paginate(30);

		return $photos;
	}
}
