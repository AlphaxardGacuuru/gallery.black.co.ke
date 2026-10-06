<?php

namespace App\Http\Services;

use App\Models\Referral;
use App\Models\Setting;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;

class ReferralService extends Service
{
	/**
	 * The current referral reward threshold/amount, so any signed-in user
	 * (not just admins) can see what referring friends earns them.
	 *
	 * @return array{threshold: int, rewardAmount: float}
	 */
	public function settings(): array
	{
		return [
			'threshold' => (int) (Setting::query()
				->where('key', 'referral_threshold')
				->value('value') ?? 5),
			'rewardAmount' => (float) (Setting::query()
				->where('key', 'referral_reward_amount')
				->value('value') ?? 50),
		];
	}

	/**
	 * The authenticated user's own referral history, most recent first.
	 */
	public function mine(Request $request): LengthAwarePaginator
	{
		return Referral::query()
			->where('referrer_id', $request->user()->id)
			->with('referred')
			->latest('created_at')
			->paginate($request->integer('per_page', 20));
	}
}
