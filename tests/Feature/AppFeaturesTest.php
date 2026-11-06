<?php

namespace Tests\Feature;

use App\Models\Photo;
use App\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AppFeaturesTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_see_everything_off_by_default(): void
    {
        $this->getJson('/api/photos/features')
            ->assertOk()
            ->assertExactJson([
                'data' => [
                    'photoSales' => false,
                    'extraEntry' => false,
                    'extraPhotos' => false,
                    'maxPhotosPerEntry' => Photo::MAX_IMAGES_PER_ENTRY,
                ],
            ]);
    }

    public function test_a_feature_counts_as_on_only_when_enabled_with_a_price(): void
    {
        Setting::query()->updateOrCreate(['key' => 'photo_sale'], ['value' => ['enabled' => true, 'price' => 200, 'photographerShare' => 50]]);
        Setting::query()->updateOrCreate(['key' => 'photo_extra_slot'], ['value' => ['enabled' => true, 'price' => 0]]);
        Setting::query()->updateOrCreate(['key' => 'photo_extra_photo'], ['value' => ['enabled' => false, 'price' => 50]]);

        $this->getJson('/api/photos/features')
            ->assertOk()
            ->assertJsonPath('data.photoSales', true)
            ->assertJsonPath('data.extraEntry', false)
            ->assertJsonPath('data.extraPhotos', false);
    }
}
