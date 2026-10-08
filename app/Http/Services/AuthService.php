<?php

namespace App\Http\Services;

use App\Models\Referral;
use App\Models\User;
use App\Notifications\WelcomeNotification;
use Illuminate\Auth\Events\Registered;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Laravel\Socialite\Contracts\User as SocialiteUser;

class AuthService extends Service
{
	/**
	 * Create an account from the email/password signup form and credit the
	 * referrer, if any.
	 */
	public function register(
		string $name,
		string $email,
		string $password,
		mixed $referrerId
	): User {
		$user = new User;
		$user->name = $name;
		$user->email = $email;
		$user->password = Hash::make($password);
		$user->settings = [
			'competitionStartedNotification' => true,
			'competitionWonNotification' => true,
		];
		$user->save();

		Referral::record($referrerId, $user);

		return $user;
	}

	/**
	 * Find the local account for a social login (by provider id, then by
	 * email) and sync its details, or create one if this is a new user.
	 */
	public function findOrCreateFromSocialite(
		SocialiteUser $socialUser,
		mixed $referrerId
	): User {
		$avatarUrl = $socialUser->getAvatar();

		$user = User::query()
			->where('google_id', $socialUser->getId())
			->orWhere('email', $socialUser->getEmail())
			->first();

		if ($user) {
			$attributes = [];

			if ($user->google_id !== $socialUser->getId()) {
				$attributes['google_id'] = $socialUser->getId();
			}

			if ($user->email_verified_at === null) {
				$attributes['email_verified_at'] = now();
			}

			$name = $socialUser->getName() ?: null;
			if ($name && $user->name !== $name) {
				$attributes['name'] = $name;
			}

			if (filled($avatarUrl) && $user->avatar !== $avatarUrl) {
				$attributes['avatar'] = $avatarUrl;
			}

			if ($attributes !== []) {
				$user->forceFill($attributes)->save();
			}

			return $user;
		}

		$user = new User;
		$user->name = $socialUser->getName() ?: 'Google User';
		$user->email = $socialUser->getEmail();
		$user->google_id = $socialUser->getId();
		$user->avatar = $avatarUrl;
		$user->email_verified_at = now();
		$user->password = Str::random(40);
		$user->save();

		Referral::record($referrerId, $user);

		$user->notify(new WelcomeNotification);
		event(new Registered($user));

		return $user;
	}

	/**
	 * The account matching an email/password login.
	 *
	 * @throws ValidationException
	 */
	public function findByCredentials(string $email, string $password): User
	{
		$user = User::query()->where('email', $email)->first();

		if (! $user) {
			throw ValidationException::withMessages([
				"email" => ["The Provided Email Doesn't Exist."]
			]);
		}

		if (! Hash::check($password, $user->password)) {
			throw ValidationException::withMessages([
				'password' => ['The provided password is incorrect.']
			]);
		}

		return $user;
	}

	public function findUser(int|string $id): ?User
	{
		return User::find($id);
	}
}
