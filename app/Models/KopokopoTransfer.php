<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class KopokopoTransfer extends Model
{
    use HasFactory, HasUuids;

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'transfer_batches' => 'array',
        'errors' => 'array',
        'metadata' => 'array',
        'updated_at' => 'datetime:d M Y',
        'created_at' => 'datetime:d M Y',
    ];

    protected function kopokopoCreatedAt(): Attribute
    {
        return Attribute::make(
            get: fn ($value) => Carbon::parse($value)->format('d M Y'),
        );
    }

    protected function updatedAt(): Attribute
    {
        return Attribute::make(
            get: fn ($value) => Carbon::parse($value)->format('d M Y'),
        );
    }

    protected function createdAt(): Attribute
    {
        return Attribute::make(
            get: fn ($value) => Carbon::parse($value)->format('d M Y'),
        );
    }

    /*
     * Relationships
     */

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Kopokopo's send-money status isn't a fixed enum we can exhaustively
     * match (no failure-case sample ships with the SDK), so these bucket
     * by keyword rather than an exact-value comparison. Mirrors the admin
     * UI's own badge-coloring heuristic in kopokopo-transfers.tsx.
     */
    public function isConfirmedSuccess(): bool
    {
        return (bool) preg_match('/process|transfer|complet|success/i', (string) $this->status);
    }

    public function isConfirmedFailure(): bool
    {
        return (bool) preg_match('/fail|reject|declin|error/i', (string) $this->status);
    }
}
