<?php

namespace App\Http\Services;

use App\Http\Services\Concerns\ParsesKopokopoLocation;
use App\Models\Photo;
use App\Models\PhotoImage;
use App\Models\PhotoPurchase;
use App\Models\User;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\URL;
use Illuminate\Validation\ValidationException;

class PhotoPurchaseService extends Service
{
	use ParsesKopokopoLocation;

	/**
	 * How long a download link stays valid. Buyers can always ask for fresh
	 * ones, so this only needs to outlast a slow download.
	 */
	public const DOWNLOAD_LINK_TTL_MINUTES = 30;

	public function __construct(protected MPESATransactionService $mpesaTransactionService)
	{
		parent::__construct();
	}

	/**
	 * What buyers see: whether photos are on sale and for how much. The
	 * photographer's share is admin-only.
	 *
	 * @return array{enabled: bool, price: int}
	 */
	public static function publicSaleSettings(): array
	{
		$settings = Photo::saleSettings();

		return [
			'enabled' => $settings['enabled'] && $settings['price'] > 0,
			'price' => $settings['price'],
		];
	}

	/**
	 * Start a Kopokopo STK push to buy a full-resolution copy of a photo.
	 * The purchase is recorded "pending" immediately and flips to "paid"
	 * async once Kopokopo's webhook confirms the payment (see
	 * MpesaTransactionCreatedListener::confirmPhotoPurchase()).
	 *
	 * @return array{0: bool, 1: string, 2: PhotoPurchase}
	 */
	public function store(Request $request, Photo $photo): array
	{
		$sale = static::publicSaleSettings();

		if (! $sale['enabled']) {
			throw ValidationException::withMessages([
				'photo' => 'Buying photos isn\'t available right now.',
			]);
		}

		$photo->loadMissing(['user', 'competition']);

		if (! $photo->isSellable()) {
			throw ValidationException::withMessages([
				'photo' => 'This photo isn\'t for sale.',
			]);
		}

		$buyer = $request->user();

		if ($photo->user_id === $buyer->id) {
			throw ValidationException::withMessages([
				'photo' => 'You can\'t buy your own photo.',
			]);
		}

		if (! $buyer->phone) {
			throw ValidationException::withMessages([
				'photo' => 'Add your M-Pesa phone number in your profile before buying a photo.',
			]);
		}

		$lock = Cache::lock("photo-purchase:{$photo->id}:{$buyer->id}", 10);

		try {
			$lock->block(2);
		} catch (LockTimeoutException) {
			throw ValidationException::withMessages([
				'photo' => 'Please try again in a moment.',
			]);
		}

		try {
			$existing = PhotoPurchase::query()
				->where('photo_id', $photo->id)
				->where('buyer_id', $buyer->id)
				->where(fn($query) => $query
					->where('status', PhotoPurchase::STATUS_PAID)
					->orWhere(fn($query) => $query->freshPending()))
				->first();

			if ($existing?->status === PhotoPurchase::STATUS_PAID) {
				throw ValidationException::withMessages([
					'photo' => 'You already own this photo.',
				]);
			}

			if ($existing) {
				throw ValidationException::withMessages([
					'photo' => 'A payment for this photo is already waiting on your phone.',
				]);
			}

			$purchase = PhotoPurchase::create([
				'photo_id' => $photo->id,
				'buyer_id' => $buyer->id,
				'seller_id' => $photo->user_id,
				'amount' => $sale['price'],
				'seller_amount' => Photo::photographerShareOf($sale['price']),
				'status' => PhotoPurchase::STATUS_PENDING,
			]);
		} finally {
			$lock->release();
		}

		$request->merge(['amount' => $sale['price']]);

		[$status, $message, $data] = $this->mpesaTransactionService->stkPush($request);

		if ($status !== 'success') {
			$purchase->update(['status' => PhotoPurchase::STATUS_FAILED]);

			return [false, $message, $purchase];
		}

		$purchase->update(['kopokopo_reference' => $this->locationId($data['location'] ?? '')]);

		return [true, $message, $purchase];
	}

	/**
	 * Whether this user may download the photo's originals: they bought it,
	 * or it's their own.
	 */
	public function canDownload(User $user, Photo $photo): bool
	{
		return $photo->user_id === $user->id
			|| $photo->purchases()
				->where('buyer_id', $user->id)
				->paid()
				->exists();
	}

	/**
	 * Short-lived signed links to every original image in the entry (the
	 * cover plus its carousel images). Signed rather than authenticated,
	 * since the app's API auth is a bearer token that a plain browser
	 * download can't carry.
	 *
	 * @return array<int, array{url: string, thumbnailUrl: string}>
	 */
	public function downloadLinks(Photo $photo): array
	{
		$expiresAt = now()->addMinutes(self::DOWNLOAD_LINK_TTL_MINUTES);

		$cover = [
			'url' => URL::temporarySignedRoute('photos.download', $expiresAt, ['photo' => $photo->id]),
			'thumbnailUrl' => $photo->thumbnail_url,
		];

		$images = $photo->images->map(fn(PhotoImage $image) => [
			'url' => URL::temporarySignedRoute('photos.download', $expiresAt, [
				'photo' => $photo->id,
				'image' => $image->id,
			]),
			'thumbnailUrl' => $image->thumbnail_url,
		]);

		return [$cover, ...$images];
	}
}
