<?php

namespace App\Http\Services;

use App\Enums\EmailNotificationCategory;
use App\Events\UserFlaggedEvent;
use App\Events\UserVerifiedEvent;
use App\Http\Resources\UserResource;
use App\Models\Photo;
use App\Models\PhotoLike;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Http\UploadedFile;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Support\Collection;

class UserService extends Service
{
	public function index(Request $request): LengthAwarePaginator|Collection
	{
		if ($request->filled('idAndName')) {
			return User::query()
				->select('id', 'name')
				->orderBy('id', 'DESC')
				->get();
		}

		$query = $this->search(User::query(), $request);

		if ($request->boolean('excludeSelf') && auth()->check()) {
			$query = $query->whereKeyNot(auth()->id());
		}

		return $query
			->withCount(['referralsMade', 'pushSubscriptions'])
			->with('referral.referrer:id,name')
			->orderBy('id', 'DESC')
			->paginate($request->integer('per_page', 15));
	}

	public function show(int|string $id): User
	{
		return User::query()->findOrFail($id);
	}

	public function store(Request $request): array
	{
		$user = User::query()->create([
			'name' => $request->string('name')->toString(),
			'email' => $request->string('email')->toString(),
			'password' => Hash::make($request->string('password')->toString()),
			'phone' => $request->input('phone'),
			'settings' => $request->input('settings'),
		]);

		if ($request->filled('userRoles')) {
			$user->syncRoles($request->input('userRoles'));
		}

		return [true, 'Account Created', $user->fresh()];
	}

	public function update(Request $request, int|string $id): array
	{
		$user = User::query()->findOrFail($id);

		if ($request->filled('name')) {
			$user->name = $request->string('name')->toString();
		}

		if ($request->filled('email')) {
			$user->email = $request->string('email')->toString();
		}

		if ($request->filled('phone')) {
			$user->phone = $request->input('phone');
		}

		if ($request->filled('password')) {
			$user->password = Hash::make($request->string('password')->toString());
		}

		if ($request->exists('settings')) {
			$user->settings = $request->input('settings');
		}

		if ($request->has('verified')) {
			$user->verified = $request->boolean('verified');
		}

		$saved = $user->save();

		if ($user->wasChanged('verified') && $user->verified) {
			UserVerifiedEvent::dispatch($user);
		}

		if ($request->filled('userRoles')) {
			$user->syncRoles($request->input('userRoles'));
		}

		return [$saved, 'Account Updated', $user->fresh()];
	}

	/**
	 * Flag $user for suspected abuse: their likes stop counting, they can't
	 * like, win or be paid, and they're told how to dispute it.
	 */
	public function flag(User $user, ?string $reason): void
	{
		$user->forceFill([
			'flagged_at' => now(),
			'flag_reason' => $reason,
			'flag_refuted_at' => null,
		])->save();

		$this->recountLikedPhotos($user);

		UserFlaggedEvent::dispatch($user);
	}

	/**
	 * Lift the flag, so $user's likes count again.
	 */
	public function unflag(User $user): void
	{
		$user->forceFill([
			'flagged_at' => null,
			'flag_reason' => null,
			'flag_refuted_at' => null,
		])->save();

		$this->recountLikedPhotos($user);
	}

	/**
	 * Recount every photo $user liked in the running challenge. Ended
	 * challenges keep their counts, since their results are already decided.
	 */
	private function recountLikedPhotos(User $user): void
	{
		Photo::query()
			->whereIn(
				'id',
				PhotoLike::query()
					->select('photo_id')
					->where('user_id', $user->id)
			)
			->whereHas('competition', fn($query) => $query->active())
			->get()
			->each(fn(Photo $photo) => $photo->recountLikes());
	}

	public function destroy(int|string $id): array
	{
		$user = User::query()->findOrFail($id);
		$deleted = $user->delete();

		return [$deleted, $user->name . ' deleted'];
	}

	/**
	 * Merge email preferences into the same users.settings blob that also
	 * holds onboarding progress, leaving the other keys untouched.
	 *
	 * @param  array<string, bool>  $preferences
	 */
	public function updateNotificationPreferences(User $user, array $preferences): void
	{
		$user->settings = array_merge((array) ($user->settings ?? []), $preferences);
		$user->save();
	}

	/**
	 * Turn a single email category off, from a signed unsubscribe link.
	 */
	public function unsubscribe(User $user, EmailNotificationCategory $category): void
	{
		$settings = (array) ($user->settings ?? []);
		$settings[$category->settingsKey()] = false;
		$user->update(['settings' => $settings]);
	}

	/**
	 * Update the user's own profile, resetting email verification when the
	 * email address changes.
	 *
	 * @param  array<string, mixed>  $attributes
	 */
	public function updateProfile(User $user, array $attributes): void
	{
		$user->fill($attributes);

		if ($user->isDirty('email')) {
			$user->email_verified_at = null;
		}

		// A flagged user disputes the flag by updating their phone number
		// (see UserFlaggedNotification). An admin reviews it from the users
		// page; it doesn't lift the flag by itself.
		if ($user->isFlagged() && $user->isDirty('phone') && $user->phone) {
			$user->flag_refuted_at = now();
		}

		$user->save();
	}

	/**
	 * Store a new profile picture for $user, deleting the one they uploaded
	 * before (Google and default avatars aren't ours to delete).
	 */
	public function updateAvatar(User $user, UploadedFile $avatar): void
	{
		$previous = $user->hasUploadedAvatar()
			? $user->getRawOriginal('avatar')
			: null;

		$user
			->forceFill(['avatar' => $avatar->store(User::AVATAR_UPLOAD_DIRECTORY, 'public')])
			->save();

		if ($previous) {
			Storage::disk('public')->delete($previous);
		}
	}

	public function updatePassword(User $user, string $password): void
	{
		$user->update(['password' => $password]);
	}

	/**
	 * Set a new password from a reset link, rotating the remember token so
	 * any "remember me" sessions on other devices are signed out.
	 */
	public function resetPassword(User $user, string $password): void
	{
		$user->forceFill([
			'password' => Hash::make($password),
			'remember_token' => Str::random(60),
		])->save();
	}

	public function auth(): UserResource|Response
	{
		if (! auth('sanctum')->check()) {
			return response(['message' => 'Not Authenticated'], 401);
		}

		return new UserResource(auth('sanctum')->user());
	}

	/**
	 * Filters for the admin users table. Each yes/no filter is skipped when
	 * absent, so "any" is simply not sending it.
	 */
	protected function search($query, Request $request)
	{
		return $query
			->when(
				$request->filled('roleId'),
				fn($query) => $query->role(
					Role::query()
						->findOrFail($request->input('roleId'))
						->name
				)
			)
			->when(
				$request->filled('name'),
				fn($query) => $query->where('name', 'LIKE', '%' . $request->input('name') . '%')
			)
			->when(
				$request->filled('search'),
				fn($query) => $query->where(
					fn($query) => $query
						->where('name', 'LIKE', '%' . $request->input('search') . '%')
						->orWhere('email', 'LIKE', '%' . $request->input('search') . '%')
						->orWhere('phone', 'LIKE', '%' . $request->input('search') . '%')
				)
			)
			->when(
				$request->filled('verified'),
				fn($query) => $query->where('verified', $request->boolean('verified'))
			)
			->when(
				$request->filled('hasPhone'),
				fn($query) => $request->boolean('hasPhone')
					? $query
						->whereNotNull('phone')
						->where('phone', '!=', '')
					: $query->where(
						fn($query) => $query
							->whereNull('phone')
							->orWhere('phone', '')
					)
			)
			->when(
				$request->filled('seed'),
				fn($query) => $query->where('is_seed', $request->boolean('seed'))
			)
			->when(
				$request->filled('installed'),
				fn($query) => $request->boolean('installed')
					? $query->whereNotNull('settings->pwaInstalledAt')
					: $query->whereNull('settings->pwaInstalledAt')
			)
			->when(
				$request->filled('flagged'),
				fn($query) => $request->boolean('flagged')
					? $query->whereNotNull('flagged_at')
					: $query->unflagged()
			)
			->when(
				$request->filled('referred'),
				fn($query) => $request->boolean('referred')
					? $query->whereHas('referral')
					: $query->whereDoesntHave('referral')
			);
	}
}
