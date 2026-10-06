<?php

namespace App\Http\Services;

use App\Models\Photo;
use App\Models\PhotoCompetition;
use App\Models\PhotoSlotPurchase;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class PhotoSlotPurchaseService extends Service
{
	public function __construct(protected MPESATransactionService $mpesaTransactionService)
	{
		parent::__construct();
	}

	/**
	 * Start a Kopokopo STK push to buy this week's extra photo slot. The
	 * purchase is recorded "pending" immediately and flips to "paid" async,
	 * once Kopokopo's webhook confirms the payment (see
	 * GrantPhotoSlotListener).
	 *
	 * @return array{0: bool, 1: string, 2: PhotoSlotPurchase}
	 */
	public function store(Request $request): array
	{
		$extraSlot = PhotoCompetition::extraSlotSettings();

		if (! $extraSlot['enabled']) {
			throw ValidationException::withMessages([
				'competition' => 'Buying an extra slot isn\'t available right now.',
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
				'competition' => 'Add your M-Pesa phone number in your profile before buying a slot.',
			]);
		}

		$submittedCount = Photo::where('competition_id', $competition->id)
			->where('user_id', $user->id)
			->count();

		if ($submittedCount < 1) {
			throw ValidationException::withMessages([
				'competition' => 'Submit this week\'s free entry before buying an extra slot.',
			]);
		}

		if ($submittedCount >= 2) {
			throw ValidationException::withMessages([
				'competition' => 'You already have the maximum number of entries this week.',
			]);
		}

		$hasOpenPurchase = PhotoSlotPurchase::where('competition_id', $competition->id)
			->where('user_id', $user->id)
			->whereIn('status', [PhotoSlotPurchase::STATUS_PENDING, PhotoSlotPurchase::STATUS_PAID])
			->exists();

		if ($hasOpenPurchase) {
			throw ValidationException::withMessages([
				'competition' => 'You already have a slot purchase in progress or completed.',
			]);
		}

		$purchase = PhotoSlotPurchase::create([
			'user_id' => $user->id,
			'competition_id' => $competition->id,
			'amount' => $extraSlot['price'],
			'status' => PhotoSlotPurchase::STATUS_PENDING,
		]);

		$request->merge(['amount' => $extraSlot['price']]);

		[$status, $message, $data] = $this->mpesaTransactionService->stkPush($request);

		if ($status !== 'success') {
			$purchase->update(['status' => PhotoSlotPurchase::STATUS_FAILED]);

			return [false, $message, $purchase];
		}

		$purchase->update(['kopokopo_reference' => $this->locationId($data['location'] ?? '')]);

		return [true, $message, $purchase];
	}

	/**
	 * The trailing UUID segment of a Kopokopo Location URL, e.g.
	 * ".../incoming_payments/{this}", the same id that later shows up as
	 * the webhook payload's top-level "data.id".
	 */
	protected function locationId(string $location): ?string
	{
		if ($location === '') {
			return null;
		}

		$segments = explode('/', rtrim($location, '/'));

		return end($segments) ?: null;
	}
}
