<?php

namespace App\Http\Services;

use App\Models\Photo;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class UnsplashService extends Service
{
	/**
	 * A random Unsplash photo matching one of the configured Nairobi/Kenya
	 * searches that hasn't already been posted (photos.unsplash_id is
	 * unique), trying a few searches before giving up.
	 *
	 * @return array<string, mixed> The photo as returned by the Unsplash API
	 */
	public function randomUnusedPhoto(int $attempts = 3): array
	{
		$queries = collect(config('services.unsplash.queries'))->shuffle();

		foreach ($queries->take($attempts) as $query) {
			$candidates = $this->client()
				->get('/photos/random', [
					'query' => $query,
					'count' => 10,
					'content_filter' => 'high',
				])
				->throw()
				->json();

			$used = Photo::query()
				->whereIn('unsplash_id', collect($candidates)->pluck('id'))
				->pluck('unsplash_id')
				->all();

			$fresh = collect($candidates)->first(
				fn(array $candidate) => ! in_array($candidate['id'], $used, true)
			);

			if ($fresh) {
				return $fresh;
			}
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
	 * The text credit Unsplash asks for, e.g. "Photo by Jane Doe on Unsplash".
	 *
	 * @param  array<string, mixed>  $photo
	 */
	public static function credit(array $photo): string
	{
		return 'Photo by ' . $photo['user']['name'] . ' (@' . $photo['user']['username'] . ') on Unsplash';
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
