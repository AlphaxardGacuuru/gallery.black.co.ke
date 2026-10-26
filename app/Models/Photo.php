<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

class Photo extends Model
{
    /** @use HasFactory<\Database\Factories\PhotoFactory> */
    use HasFactory, HasUuids;

    /**
     * 1 free cover image + up to 4 purchased extra slots (see
     * PhotoSlotPurchase::TYPE_EXTRA_PHOTO).
     */
    public const MAX_IMAGES_PER_ENTRY = 5;

    protected $fillable = [
        'competition_id',
        'user_id',
        'disk',
        'path',
        'thumbnail_path',
        'caption',
        'unsplash_id',
        'width',
        'height',
        'likes_count',
    ];

    protected $casts = [
        'width' => 'integer',
        'height' => 'integer',
        'likes_count' => 'integer',
    ];

    public function competition(): BelongsTo
    {
        return $this->belongsTo(PhotoCompetition::class, 'competition_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function likes(): HasMany
    {
        return $this->hasMany(PhotoLike::class);
    }

    /**
     * Images beyond the cover (this entry's own path/thumbnail_path),
     * ordered for the carousel.
     */
    public function images(): HasMany
    {
        return $this->hasMany(PhotoImage::class)->orderBy('position');
    }

    public function purchases(): HasMany
    {
        return $this->hasMany(PhotoPurchase::class);
    }

    /**
     * Whether photos can be bought from Discover, the KES price for one, and
     * the percentage of that price paid on to the photographer (a single
     * JSON-object Setting, all three are always read/written together).
     *
     * @return array{enabled: bool, price: int, photographerShare: int}
     */
    public static function saleSettings(): array
    {
        $value = Setting::query()
            ->where('key', 'photo_sale')
            ->value('value') ?? [];

        return [
            'enabled' => (bool) ($value['enabled'] ?? false),
            'price' => (int) ($value['price'] ?? 0),
            'photographerShare' => (int) ($value['photographerShare'] ?? 0),
        ];
    }

    /**
     * The photographer's cut of a sale at the given price, rounded down to
     * whole shillings so the platform never pays out more than it took.
     */
    public static function photographerShareOf(int $price): int
    {
        return intdiv($price * static::saleSettings()['photographerShare'], 100);
    }

    /**
     * Whether this photo can be sold at all, regardless of who's asking:
     * only from an ended competition, and never an Unsplash seed photo (the
     * Unsplash License doesn't allow selling them). Needs the user and
     * competition relations loaded, and answers false rather than lazy
     * loading them when they aren't.
     */
    public function isSellable(): bool
    {
        if (! $this->relationLoaded('user') || ! $this->relationLoaded('competition')) {
            return false;
        }

        return $this->unsplash_id === null
            && $this->user !== null
            && ! $this->user->is_seed
            && $this->competition?->status === PhotoCompetition::STATUS_ENDED;
    }

    /**
     * How many images this user is allowed to attach to an entry in this
     * competition: 1 free + however many extra-photo slots they've paid
     * for, capped at MAX_IMAGES_PER_ENTRY. Shared by the purchase service,
     * submission, and the frontend state endpoint so they can't drift.
     */
    public static function maxImagesFor(string $competitionId, string $userId): int
    {
        return min(
            1 + PhotoSlotPurchase::paidExtraPhotoCount($competitionId, $userId),
            self::MAX_IMAGES_PER_ENTRY
        );
    }

    /**
     * Annotate each photo with whether the given user has liked it, via a
     * correlated exists() subquery rather than N+1 relation checks.
     */
    public function scopeWithLikedByViewer(Builder $query, ?User $user): Builder
    {
        if (! $user) {
            return $query->selectRaw('photos.*, false as is_liked_by_viewer');
        }

        return $query->addSelect([
            'is_liked_by_viewer' => PhotoLike::query()
                ->selectRaw('1')
                ->whereColumn('photo_id', 'photos.id')
                ->where('user_id', $user->id)
                ->limit(1),
        ]);
    }

    /**
     * Annotate each photo with whether the given user has a paid purchase
     * of it, the same correlated-subquery approach as withLikedByViewer().
     */
    public function scopeWithPurchasedByViewer(Builder $query, ?User $user): Builder
    {
        if (is_null($query->getQuery()->columns)) {
            $query->select('photos.*');
        }

        if (! $user) {
            return $query->selectRaw('false as is_purchased_by_viewer');
        }

        return $query->addSelect([
            'is_purchased_by_viewer' => PhotoPurchase::query()
                ->selectRaw('1')
                ->whereColumn('photo_id', 'photos.id')
                ->where('buyer_id', $user->id)
                ->where('status', PhotoPurchase::STATUS_PAID)
                ->limit(1),
        ]);
    }

    /**
     * Annotate each photo with whether it placed 1st (is_winner) and, if it
     * placed at all, its rank (position) — via correlated subqueries against
     * photo_competition_winners rather than eager-loading the relation.
     */
    public function scopeWithIsWinner(Builder $query): Builder
    {
        return $query->addSelect([
            'is_winner' => PhotoCompetitionWinner::query()
                ->selectRaw('1')
                ->whereColumn('photo_id', 'photos.id')
                ->where('position', 1)
                ->limit(1),
            'position' => PhotoCompetitionWinner::query()
                ->select('position')
                ->whereColumn('photo_id', 'photos.id')
                ->limit(1),
        ]);
    }

    /**
     * Seed photos from Unsplash are hotlinked (their API terms require it),
     * so they're stored on the "remote" disk with full URLs as their paths.
     */
    public const DISK_REMOTE = 'remote';

    protected function url(): Attribute
    {
        return Attribute::make(
            get: fn() => $this->resolveUrl($this->path),
        );
    }

    /**
     * The compressed grid-sized copy generated by GeneratePhotoThumbnailJob
     * after upload. Falls back to the original while that job hasn't run
     * yet (or failed), so callers never have to null-check this.
     */
    protected function thumbnailUrl(): Attribute
    {
        return Attribute::make(
            get: fn() => $this->resolveUrl($this->thumbnail_path ?? $this->path),
        );
    }

    private function resolveUrl(string $path): string
    {
        return $this->disk === self::DISK_REMOTE
            ? $path
            : Storage::disk($this->disk)->url($path);
    }

    protected function aspectRatio(): Attribute
    {
        return Attribute::make(
            get: fn() => ($this->width && $this->height) ? $this->width / $this->height : 1,
        );
    }
}
