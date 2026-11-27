<?php

namespace App\Http\Services;

use App\Http\Services\Concerns\ParsesKopokopoLocation;
use App\Models\PhoneVerification;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class PhoneVerificationService extends Service
{
	use ParsesKopokopoLocation;

	public function __construct(protected MPESATransactionService $mpesaTransactionService)
	{
		parent::__construct();
	}

	/**
	 * Start a Kopokopo STK push for the verification fee on the user's new
	 * phone number. Approving it with their M-Pesa PIN proves the number
	 * is a real line they control. The payment is recorded "pending" and
	 * flips to "paid" async, once Kopokopo's webhook confirms it, which
	 * lifts the flag (see MpesaTransactionCreatedListener).
	 *
	 * @return array{0: bool, 1: string, 2: PhoneVerification}
	 */
	public function store(Request $request): array
	{
		$user = $request->user();

		if (! $user->isFlaggedUntilPhoneVerified()) {
			throw ValidationException::withMessages([
				'phone' => 'Your phone number doesn\'t need confirming.',
			]);
		}

		$hasOpenPayment = $user
			->phoneVerifications()
			->freshPending()
			->exists();

		if ($hasOpenPayment) {
			throw ValidationException::withMessages([
				'phone' => 'A payment prompt was just sent to your phone. Approve it, or wait a few minutes and try again.',
			]);
		}

		$fee = User::phoneVerificationFee();

		$verification = $user
			->phoneVerifications()
			->create([
				'phone' => $user->phone,
				'amount' => $fee,
				'status' => PhoneVerification::STATUS_PENDING,
			]);

		$request->merge(['amount' => $fee]);

		[$status, $message, $data] = $this->mpesaTransactionService->stkPush($request);

		if ($status !== 'success') {
			$verification->update(['status' => PhoneVerification::STATUS_FAILED]);

			return [false, $message, $verification];
		}

		$verification->update(['kopokopo_reference' => $this->locationId($data['location'] ?? '')]);

		return [true, $message, $verification];
	}
}
