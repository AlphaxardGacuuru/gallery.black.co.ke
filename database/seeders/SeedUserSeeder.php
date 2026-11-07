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
    /**
     * In posting order: PostSeedPhotos has the first posterCount() of these post
     * each competition's photos, so changing that count always adds or
     * drops names from the end of this list. Keep the first entries where
     * they are, and add new names at the end.
     */
    public const NAMES = [
        ['Mutua Musyoka', 'male'],
        ['Chebet Jepkosgei', 'female'],
        ['Wanjiru Kamau', 'female'],
        ['Otieno Odhiambo', 'male'],
        ['Achieng Atieno', 'female'],
        ['Kiprono Kiptoo', 'male'],
        ['Njeri Mwangi', 'female'],
        ['Wairimu Njoroge', 'female'],
        ['Barasa Wekesa', 'male'],
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
        ['Muthoni Kinyanjui', 'female'],
        ['Gitonga Muriithi', 'male'],
        ['Anyango Obiero', 'female'],
        ['Ndirangu Gachie', 'male'],
        ['Chepkoech Bett', 'female'],
        ['Oduya Okumu', 'male'],
        ['Mueni Kyalo', 'female'],
        ['Okello Agutu', 'male'],
        ['Nasimiyu Barasa', 'female'],
        ['Kiprotich Kemboi', 'male'],
        ['Bosibori Nyakundi', 'female'],
        ['Kipchumba Ngetich', 'male'],
        ['Fatuma Said', 'female'],
        ['Mutinda Mulwa', 'male'],
        ['Amina Hussein', 'female'],
        ['Muthama Ndambuki', 'male'],
        ['Saida Mohamed', 'female'],
        ['Wekesa Masinde', 'male'],
        ['Mwanaisha Bakari', 'female'],
        ['Nyongesa Khisa', 'male'],
        ['Nanjala Wekesa', 'female'],
        ['Simiyu Wanyonyi', 'male'],
        ['Jeruto Kiprop', 'female'],
        ['Omari Rashid', 'male'],
        ['Cherono Sang', 'female'],
        ['Juma Mwinyi', 'male'],
        ['Ndinda Musembi', 'female'],
        ['Salim Athman', 'male'],
        ['Kawira Mugambi', 'female'],
        ['Ochola Ogutu', 'male'],
        ['Gakii Murithi', 'female'],
        ['Obura Odera', 'male'],
        ['Nkatha Kaimenyi', 'female'],
        ['Mokaya Nyakundi', 'male'],
        ['Wangui Ndegwa', 'female'],
        ['Momanyi Orina', 'male'],
        ['Waithera Kiarie', 'female'],
        ['Mugo Wachira', 'male'],
        ['Njoki Gachanja', 'female'],
        ['Kariuki Ngugi', 'male'],
        ['Auma Okello', 'female'],
        ['Parmuat Lekishon', 'male'],
        ['Apiyo Oduor', 'female'],
        ['Lokol Ereng', 'male'],
        ['Taabu Nyongesa', 'female'],
        ['Nderitu Wahome', 'male'],
        ['Nashipae Leshan', 'female'],
        ['Murage Kamande', 'male'],
        ['Akai Lomuria', 'female'],
        ['Kipkurui Chirchir', 'male'],
    ];

    public static function email(string $name): string
    {
        return Str::slug($name, '.') . '@seed.black.co.ke';
    }

    public function run(): void
    {
        foreach (self::NAMES as [$name, $gender]) {
            $email = static::email($name);

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
