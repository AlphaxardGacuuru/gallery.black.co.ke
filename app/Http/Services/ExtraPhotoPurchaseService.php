<?php

namespace App\Http\Services;

use App\Http\Services\Concerns\ParsesKopokopoLocation;
use App\Models\Photo;
use App\Models\PhotoCompetition;
use App\Models\PhotoSlotPurchase;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\ValidationException;

class ExtraPhotoPurchaseService extends Service
{
	use ParsesKopokopoLocation;

	public function __construct(protected MPESATransactionService $mpesaTransactionService)
	{
		parent::__construct();
	}

	/**
	 * Start a Kopokopo STK push to buy one additional photo slot (see
	 * Photo::maxImagesFor()). Unlike the "second entry" purchase, this is
	 * repeatable, each payment only adds one slot, up to
	 * Photo::MAX_IMAGES_PER_ENTRY total. The purchase is recorded "pending"
	 * immediately and flips to "paid" async once Kopokopo's webhook
	 * confirms the payment (see GrantPhotoSlotListener, which matches by
	 * kopokopo_reference regardless of purchase type).
	 *
	 * @return array{0: bool, 1: string, 2: PhotoSlotPurchase}
	 */
	public function store(Request $request): array
	{
		$extraPhoto = PhotoCompetition::extraPhotoSettings();

		if (! $extraPhoto['enabled']) {
			throw ValidationException::withMessages([
				'competition' => 'Adding more photos isn\'t available right now.',
			]);
		}

		$competition = PhotoCompetition::active()->first();

		if (! $competition) {
			throw ValidationException::withMessages([
				'competition' => 'There is no active competition right now.',
			]);
		}

		$user = $request->user();

		if (! $user->phone) {
			throw ValidationException::withMessages([
				'competition' => 'Add your M-Pesa phone number in your profile before buying a photo slot.',
			]);
		}

		$lock = Cache::lock("photo-extra-photo-purchase:{$competition->id}:{$user->id}", 10);

		try {
			$lock->block(2);
		} catch (LockTimeoutException) {
			throw ValidationException::withMessages([
				'competition' => 'Please try again in a moment.',
			]);
		}

		try {
			if (Photo::maxImagesFor($competition->id, $user->id) >= Photo::MAX_IMAGES_PER_ENTRY) {
				throw ValidationException::withMessages([
					'competition' => 'You\'ve already unlocked the maximum of ' . Photo::MAX_IMAGES_PER_ENTRY . ' photos.',
				]);
			}

			$hasFreshPendingPurchase = PhotoSlotPurchase::ofType(PhotoSlotPurchase::TYPE_EXTRA_PHOTO)
				->where('competition_id', $competition->id)
				->where('user_id', $user->id)
				->freshPending()
				->exists();

			if ($hasFreshPendingPurchase) {
				throw ValidationException::withMessages([
					'competition' => 'You already have a photo slot payment in progress.',
				]);
			}

			$purchase = PhotoSlotPurchase::create([
				'user_id' => $user->id,
				'competition_id' => $competition->id,
				'type' => PhotoSlotPurchase::TYPE_EXTRA_PHOTO,
				'amount' => $extraPhoto['price'],
				'status' => PhotoSlotPurchase::STATUS_PENDING,
			]);
		} finally {
			$lock->release();
		}

		$request->merge(['amount' => $extraPhoto['price']]);

		[$status, $message, $data] = $this->mpesaTransactionService->stkPush($request);

		if ($status !== 'success') {
			$purchase->update(['status' => PhotoSlotPurchase::STATUS_FAILED]);

			return [false, $message, $purchase];
		}

		$kopokopoReference = $this->locationId($data['location'] ?? '');

		$purchase->update(['kopokopo_reference' => $kopokopoReference]);

		return [true, $message, $purchase];
	}
}
