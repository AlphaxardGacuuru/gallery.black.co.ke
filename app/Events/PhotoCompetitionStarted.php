<?php

namespace App\Events;

use App\Models\PhotoCompetition;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class PhotoCompetitionStarted
{
    use Dispatchable, SerializesModels;

    public function __construct(public PhotoCompetition $competition) {}
}
