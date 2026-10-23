<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * The pool of seed accounts that post Unsplash photos into each
 * competition (see PostSeedPhotos). Safe to re-run: existing accounts are
 * left alone. Their emails are on a domain with no inbox, and they get a
 * random password nobody knows, so nobody can sign in as them.
 */
class SeedUserSeeder extends Seeder
{
    private const NAMES = [
        ['Wanjiru Kamau', 'female'],
        ['Otieno Odhiambo', 'male'],
        ['Achieng Atieno', 'female'],
        ['Kiprono Kiptoo', 'male'],
        ['Njeri Mwangi', 'female'],
        ['Mutua Musyoka', 'male'],
        ['Wairimu Njoroge', 'female'],
        ['Barasa Wekesa', 'male'],
        ['Chebet Jepkosgei', 'female'],
        ['Omondi Ouma', 'male'],
    ];

    public function run(): void
    {
        foreach (self::NAMES as [$name, $gender]) {
            $email = Str::slug($name, '.') . '@seed.black.co.ke';

            if (User::where('email', $email)->exists()) {
                continue;
            }

            $user = new User([
                'name' => $name,
                'email' => $email,
                'password' => Hash::make(Str::random(40)),
            ]);

            $user->forceFill([
                'is_seed' => true,
                'gender' => $gender,
                'email_verified_at' => now(),
            ])->save();
        }
    }
}
