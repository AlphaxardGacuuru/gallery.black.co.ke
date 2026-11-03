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
        ['Akinyi Onyango', 'female'],
        ['Kamau Githinji', 'male'],
        ['Nyambura Wanjiku', 'female'],
        ['Kibet Rotich', 'male'],
        ['Mwende Kioko', 'female'],
        ['Wafula Simiyu', 'male'],
        ['Jepchirchir Koech', 'female'],
        ['Mwangi Kariuki', 'male'],
        ['Adhiambo Okoth', 'female'],
        ['Kilonzo Mutiso', 'male'],
        ['Wangari Maina', 'female'],
        ['Ochieng Owino', 'male'],
        ['Nekesa Wanjala', 'female'],
        ['Kiplagat Cheruiyot', 'male'],
        ['Mumbua Nzioka', 'female'],
        ['Njuguna Kimani', 'male'],
        ['Awino Achola', 'female'],
        ['Langat Kirui', 'male'],
        ['Wambui Gitau', 'female'],
        ['Makokha Juma', 'male'],
        ['Kerubo Nyaboke', 'female'],
        ['Onyancha Mogaka', 'male'],
        ['Zawadi Mwakio', 'female'],
        ['Baraka Mwamburi', 'male'],
        ['Halima Abdalla', 'female'],
        ['Hassan Omar', 'male'],
        ['Naliaka Khaemba', 'female'],
        ['Muriuki Gichuki', 'male'],
        ['Jelagat Tanui', 'female'],
        ['Odongo Opiyo', 'male'],
        ['Kanini Mbithi', 'female'],
        ['Kirimi Mutuma', 'male'],
        ['Makena Gatwiri', 'female'],
        ['Lemayian Sankale', 'male'],
        ['Naserian Parsimei', 'female'],
        ['Ekiru Lokorio', 'male'],
        ['Moraa Kemunto', 'female'],
        ['Nyamweya Ondieki', 'male'],
        ['Nafula Wamalwa', 'female'],
        ['Kinyua Ndirangu', 'male'],
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
