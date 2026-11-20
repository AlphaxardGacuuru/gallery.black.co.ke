<?php

namespace Tests\Unit;

use App\Models\User;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class UserNameTest extends TestCase
{
    public static function names(): array
    {
        return [
            'lowercase' => ['wanjiku kamau', 'Wanjiku Kamau'],
            'hyphen and apostrophe' => ["mary-jane o'brien", "Mary-Jane O'Brien"],
            'inner capitals kept' => ['ronald mcDonald', 'Ronald McDonald'],
            'already capitalized' => ['Alphaxard Gacuuru', 'Alphaxard Gacuuru'],
            'accented letter' => ['élodie njeri', 'Élodie Njeri'],
            'extra spaces' => ['  john  doe', '  John  Doe'],
        ];
    }

    #[DataProvider('names')]
    public function test_names_are_capitalized_for_display(string $stored, string $displayed): void
    {
        $this->assertSame($displayed, (new User)->setRawAttributes(['name' => $stored])->name);
    }

    public function test_a_missing_name_stays_null(): void
    {
        $this->assertNull(User::capitalizeName(null));
    }
}
