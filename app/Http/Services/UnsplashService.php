<?php

namespace App\Http\Services;

use App\Models\Photo;
use App\Models\PhotoImage;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class UnsplashService extends Service
{
	/**
	 * Up to $count random Unsplash photos from one of the configured
	 * Nairobi/Kenya searches that haven't already been posted, as a cover
	 * or as an extra image (unsplash_id is unique on both tables). Tries a
	 * few searches for a full set, then settles for the biggest set found
	 * rather than posting nothing.
	 *
	 * @return array<int, array<string, mixed>> Photos as returned by the Unsplash API
	 */
	public function randomUnusedPhotos(int $count, int $attempts = 3): array
	{
		$queries = collect(config('services.unsplash.queries'))->shuffle();
		$best = [];

		foreach ($queries->take($attempts) as $query) {
			$candidates = $this->client()
				->get('/photos/random', [
					'query' => $query,
					'count' => 10,
					'content_filter' => 'high',
				])
				->throw()
				->json();

			$ids = collect($candidates)->pluck('id');
			$used = Photo::query()
				->whereIn('unsplash_id', $ids)
				->pluck('unsplash_id')
				->merge(PhotoImage::query()
					->whereIn('unsplash_id', $ids)
					->pluck('unsplash_id'))
				->all();

			$fresh = collect($candidates)
				->reject(fn(array $candidate) => in_array($candidate['id'], $used, true))
				->unique('id')
				->take($count)
				->values()
				->all();

			if (count($fresh) === $count) {
				return $fresh;
			}

			if (count($fresh) > count($best)) {
				$best = $fresh;
			}
		}

		if ($best !== []) {
			return $best;
		}

		throw new RuntimeException('Unsplash returned no photos that haven\'t been posted already.');
	}

	/**
	 * Unsplash's API terms require hitting a photo's download_location
	 * whenever the app "uses" it, which is how photographers get credited
	 * with a download.
	 *
	 * @param  array<string, mixed>  $photo
	 */
	public function trackDownload(array $photo): void
	{
		$this->client()->get($photo['links']['download_location'])->throw();
	}

	/**
	 * The text credit Unsplash asks for, e.g. "Photo by Jane Doe (@jane) on
	 * Unsplash", naming each photographer once across several photos.
	 *
	 * @param  array<string, mixed>  ...$photos
	 */
	public static function credit(array ...$photos): string
	{
		$photographers = collect($photos)
			->pluck('user')
			->unique('username')
			->map(fn(array $user) => $user['name'] . ' (@' . $user['username'] . ')')
			->join(', ', ' and ');

		return (count($photos) > 1 ? 'Photos' : 'Photo') . ' by ' . $photographers . ' on Unsplash';
	}

	private function client(): PendingRequest
	{
		$key = config('services.unsplash.access_key');

		if (! $key) {
			throw new RuntimeException('UNSPLASH_ACCESS_KEY is not set.');
		}

		return Http::baseUrl(config('services.unsplash.base_url'))
			->withHeaders([
				'Authorization' => 'Client-ID ' . $key,
				'Accept-Version' => 'v1',
			])
			->timeout(15);
	}
}
