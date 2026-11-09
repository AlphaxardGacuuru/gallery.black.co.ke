<?php

namespace Database\Seeders;

use App\Http\Services\UnsplashService;
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

    /**
     * Profile pictures, hotlinked from Unsplash like the seed photos (their
     * API terms require it), as [Unsplash id, image path], credited to the
     * photographer. Mostly objects, animals and places; the photos of
     * people are silhouettes or taken from behind, so no real stranger's
     * face stands in for a seed account.
     */
    public const AVATARS = [
        'Mutua Musyoka' => ['sNPfZxrBYdQ', 'photo-1511184059754-e4b5bbbcef75'], // Alexander Andrews (@alex_andrews)
        'Chebet Jepkosgei' => ['2L11W39hDYo', 'photo-1621274220348-41dc235ff439'], // Matthew Ball (@tex450)
        'Wanjiru Kamau' => ['FzdEbrA3Qj0', 'photo-1587080413959-06b859fb107d'], // Guy Basabose (@guybas)
        'Otieno Odhiambo' => ['zHsWYooD42k', 'photo-1622599695405-7a0b9b01d5c0'], // Art and Soil Bangalore (@artandsoil)
        'Achieng Atieno' => ['hQvzY0gXsdk', 'photo-1567070694334-1c48494f8995'], // Maixent Viau (@maixentviau)
        'Kiprono Kiptoo' => ['Yb30HNEUAEs', 'photo-1759488564873-8af17e7eb765'], // Nikita Kulikov (@kulikovniki)
        'Njeri Mwangi' => ['CC7avUChQNU', 'photo-1589471861110-1144cd568519'], // Daniel Hodgkins (@dhphotography24)
        'Wairimu Njoroge' => ['LxVxPA1LOVM', 'photo-1491553895911-0055eca6402d'], // Imani Bahati (@imani_bht)
        'Barasa Wekesa' => ['SssdoXhhtJw', 'photo-1602848597941-0d3d3a2c1241'], // Brett Jordan (@brett_jordan)
        'Omondi Ouma' => ['sfL_QOnmy00', 'photo-1481627834876-b7833e8f5570'], // 🇸🇮 Janko Ferlič (@itfeelslikefilm)
        'Akinyi Onyango' => ['kzSD3xh9tNA', 'photo-1560836930-def39a01665b'], // Vincentiu Solomon (@vincentiu)
        'Kamau Githinji' => ['r2uugFQvAZ0', 'photo-1638803782506-d975a6809f43'], // Stefan Riedl (@steve_volt)
        'Nyambura Wanjiku' => ['172mGIcc3xc', 'photo-1533598313300-ac574e6dd10d'], // Sofia Ornelas (@_sofizzz)
        'Kibet Rotich' => ['6wEM5ZJWVDQ', 'photo-1626548307930-deac221f87d9'], // Jeremiah Del Mar (@jeremiahdelmar)
        'Mwende Kioko' => ['c_cPNXlovvY', 'photo-1501706362039-c06b2d715385'], // Frida Lannerström (@fridalannerstrom)
        'Wafula Simiyu' => ['oP9DfJKDyQc', 'photo-1627828094454-accc9a7c20e9'], // petr sidorov (@m_malkovich)
        'Jepchirchir Koech' => ['jBjQA3LU9Dc', 'photo-1557650454-65194af63bf9'], // Keith Markilie (@rhino007)
        'Mwangi Kariuki' => ['Eb7SHpRb-FA', 'photo-1660562343893-e0790c3da480'], // A OC (@bogl)
        'Adhiambo Okoth' => ['uzyPyMRe3W8', 'photo-1611806413077-b906b7a81d8d'], // Clay Banks (@claybanks)
        'Kilonzo Mutiso' => ['yuCIZAIEVJ8', 'photo-1586491157403-f3407cb8ff5b'], // Sneha Cecil (@sneha_snaps)
        'Wangari Maina' => ['4WG4tiXK3uU', 'photo-1673867299081-571086ebfc62'], // Samiul Alam (@samiulx19)
        'Ochieng Owino' => ['1DIVFUIssdk', 'photo-1707310231175-3548c1c1f037'], // Michael Reinhard (@michaelreinhard)
        'Nekesa Wanjala' => ['27LH_0jXKYI', 'photo-1560174038-da43ac74f01b'], // Piotr Makowski (@m4quuv)
        'Kiplagat Cheruiyot' => ['_Yc7OtfFn-0', 'photo-1460661419201-fd4cecdf8a8b'], // RhondaK Native Florida Folk Artist (@rhondak)
        'Mumbua Nzioka' => ['lQpFRPrepQ8', 'photo-1606925797300-0b35e9d1794e'], // Md Mahdi (@mahdi17)
        'Njuguna Kimani' => ['NqgDRDuLTdM', 'photo-1503212556734-c0ca0c49c8b0'], // Ahmed  Rizkhaan (@ahmed_rizkhaan)
        'Awino Achola' => ['hlkZjUwPExk', 'photo-1638029054929-fa2510568eaf'], // Maggie Isley (@maggieisley)
        'Langat Kirui' => ['hz_BchTSLX8', 'photo-1735989967755-706e5edcb44b'], // Amie Barron (@amiebarronphotography)
        'Wambui Gitau' => ['ng9yenZfeuI', 'photo-1596854372745-0906a0593bca'], // Kabo (@kabofoods)
        'Makokha Juma' => ['qYK-TFNiXqo', 'photo-1557444536-c9525b121b61'], // Leila Boujnane (@leilaboujnane)
        'Kerubo Nyaboke' => ['-BdhIpH1dsQ', 'photo-1771556508061-0810e74cdd8b'], // Trương Tuyết Ly (@liliantruong2603)
        'Onyancha Mogaka' => ['kKtKX144si4', 'photo-1668829297425-a3732c217ebd'], // Hemendra Kumar (@hemc4)
        'Zawadi Mwakio' => ['kVCRP3uiLiE', 'photo-1484980859177-5ac1249fda6f'], // Brooke Lark (@brookelark)
        'Baraka Mwamburi' => ['9xtO8I_5O64', 'photo-1690701346316-1ecb0e67a3e2'], // Anastasiia Ornarin (@ornarin)
        'Halima Abdalla' => ['o9Y3Id49kI4', 'photo-1633649331263-1231d8eba0b0'], // nilufar nattaq (@nillufar)
        'Hassan Omar' => ['RKXg2cJc4rw', 'photo-1688924303823-3c7b0c2d0de9'], // Ian (@iandotphotos)
        'Naliaka Khaemba' => ['n1jB9kcXbpg', 'photo-1504662086540-c3a76649a133'], // Pawan Sharma (@pawan9472)
        'Muriuki Gichuki' => ['FWIV8I2h-s0', 'photo-1696960741206-63a41bb798ad'], // Ambo Ampeng (@ambo_ampeng)
        'Jelagat Tanui' => ['jxAugMA3Kpk', 'photo-1621778764188-bf87fbb77c7b'], // Rojem Cajan (@ichbinjem)
        'Odongo Opiyo' => ['w5rAnFjFm3A', 'photo-1613274507360-8dfbae7b3e1a'], // wu yi (@takeshi2)
        'Kanini Mbithi' => ['nI27kbA597A', 'photo-1695302742675-792b4e3e3a5c'], // Igor Omilaev (@omilaev)
        'Kirimi Mutuma' => ['WF1II9nnnaQ', 'photo-1674824043348-7dd35ef520e7'], // Vikas Raj Yadav (@vraj247)
        'Makena Gatwiri' => ['XPBYi4K8vFI', 'photo-1608667508764-33cf0726b13a'], // Artem Bondarchuk (@artembondarchuk)
        'Lemayian Sankale' => ['hrUhyFq6u-A', 'photo-1602848596718-45693ff58c78'], // Brett Jordan (@brett_jordan)
        'Naserian Parsimei' => ['IOzk8YKDhYg', 'photo-1471970471555-19d4b113e9ed'], // Hope House Press - Leather Diary Studio (@hope_house_press_leather_diary_studio)
        'Ekiru Lokorio' => ['FBHc-fKap2I', 'photo-1615482497366-53ab1f563db7'], // Nikola Johnny Mirkovic (@thejohnnyme)
        'Moraa Kemunto' => ['RgMOBFLGoZM', 'photo-1566355800052-c1fff02fcbae'], // Joel De Vera (@joel_devera)
        'Nyamweya Ondieki' => ['QwXPI_Vts8g', 'photo-1517346921254-4797474379e1'], // Debby Hudson (@hudsoncrafted)
        'Nafula Wamalwa' => ['C8EslxPWoAo', 'photo-1526226244945-39c5d1d9e61a'], // Hu Chen (@huchenme)
        'Kinyua Ndirangu' => ['MJi8Muk1k3Q', 'photo-1518217693188-8e3fe365b18d'], // Antoine Plüss (@wolf51)
        'Muthoni Kinyanjui' => ['QQkGZYVAAis', 'photo-1779638022004-03d335243264'], // Lei Hwang (@blackbird_ii)
        'Gitonga Muriithi' => ['QGofHoPnjCc', 'photo-1579933817521-d1ad9722444f'], // Photos By Beks (@photosbybeks)
        'Anyango Obiero' => ['UGwm5OI5uqw', 'photo-1784944628555-c62ea5df7157'], // Ramon Gilabert (@ramongilabert)
        'Ndirangu Gachie' => ['KbELeVz4WhU', 'photo-1791551740632-23bfef406769'], // Bennett (@bennett29429)
        'Chepkoech Bett' => ['fR6sYEjbDMg', 'photo-1562109245-d184fe2c81ff'], // Krzysztof Kowalik (@kowalikus)
        'Oduya Okumu' => ['QKkFXCpZ3e4', 'photo-1775918714788-05ada25ad44d'], // Jason Mendes (@jasemendess)
        'Mueni Kyalo' => ['OWlvTVcghtg', 'photo-1610804616017-b06185ab8a1a'], // Andre Tan (@andredantan19)
        'Okello Agutu' => ['BhZpQz9pytY', 'photo-1674208732345-3feee91806ac'], // Elliot Zhang (@elliot_zhang)
        'Nasimiyu Barasa' => ['Ky24fCpz1IE', 'photo-1618275341069-262c9ddc62ec'], // Marcus Urbenz (@marcusurbenz)
        'Kiprotich Kemboi' => ['rdu4jslQo-s', 'photo-1490696809909-71f5f1fd23e5'], // channnngma (@channnngma)
        'Bosibori Nyakundi' => ['lqqpMXO_8Tc', 'photo-1508153460964-48ffffcb0829'], // Ahmet Sali (@ahmetsali)
        'Kipchumba Ngetich' => ['li6ebshMGL8', 'photo-1546801233-12c55fc41113'], // Lukas Robertson (@lukas_onthewind)
        'Fatuma Said' => ['7a_tx7Zb6Wc', 'photo-1681131194788-613458a15616'], // Tuomas Nylund (@tuomasnylund)
        'Mutinda Mulwa' => ['CDxeDdf9WB4', 'photo-1597838816882-4435b1977fbe'], // Alexandre Boucey (@thisisareku)
        'Amina Hussein' => ['1DAn0RnHu1o', 'photo-1732224207073-fd179b617cfe'], // Ricky Beron (@nacio1966)
        'Muthama Ndambuki' => ['J-GYnZ8RRuc', 'photo-1766959701561-40a7b9c0117f'], // James Sestric (@jamessestric)
        'Saida Mohamed' => ['buZngHzb8-w', 'photo-1641676887171-254e492059e5'], // abu hurairah (@_abu_hurairah)
        'Wekesa Masinde' => ['bqjyFm32HDM', 'photo-1584559582213-787a2953dcbe'], // Shamblen Studios (@shamblenstudios)
        'Mwanaisha Bakari' => ['Q_TJrtX3lwc', 'photo-1651710105930-8856dd3ddf8d'], // Cristine Despares (@creativitine_1220)
        'Nyongesa Khisa' => ['VLGWE_SumrA', 'photo-1524135220673-c731600a1a50'], // Alexander Andrews (@alex_andrews)
        'Nanjala Wekesa' => ['4tchhX8yUOY', 'photo-1758295970180-69c318772447'], // POOJAN THANEKAR (@poojanclicks)
        'Simiyu Wanyonyi' => ['Of9mvQgbyNA', 'photo-1703411964097-0d4bbf43a76b'], // Colin Watts (@colinwatts)
        'Jeruto Kiprop' => ['91sY2Ch6CT0', 'photo-1691256676407-785f84a30cff'], // personalgraphic.com (@personal_graphic)
        'Omari Rashid' => ['RheaZgfPzLw', 'photo-1666964433026-aca28b75d1c8'], // Shannon Tucker (@shannontuckerla)
        'Cherono Sang' => ['k-i4-J0kj3M', 'photo-1463388660647-1f8d3afa3d2f'], // Milada Vigerova (@milada_vigerova)
        'Juma Mwinyi' => ['0GTeV-RCvmE', 'photo-1608128209757-31e89f69fd6d'], // Ammar El Attar (@ammar_elattar1)
        'Ndinda Musembi' => ['tXregqFpUEA', 'photo-1668604939973-e8066b5a038e'], // manas rb (@manas_rb)
        'Salim Athman' => ['LFlVuWLjYEo', 'photo-1565814636199-ae8133055c1c'], // Warren Jones (@warren602)
        'Kawira Mugambi' => ['P_LEitbpCPg', 'photo-1602848596140-33c2b48c6ade'], // Brett Jordan (@brett_jordan)
        'Ochola Ogutu' => ['lW72DTPYiv4', 'photo-1614226114680-0ee64e82a06d'], // Jacinta Christos (@jacintachristos)
        'Gakii Murithi' => ['xqX1slU55zA', 'photo-1738779735404-1b834f721f0a'], // Jason Grant (@jgrant1)
        'Obura Odera' => ['iY3NQuIybNU', 'photo-1759728886965-da7fd4626b48'], // Mustafa akın (@msaimakin)
        'Nkatha Kaimenyi' => ['IVJiTrJfNjA', 'photo-1597002755425-294af3078d09'], // Łukasz Rawa (@lukasz_rawa)
        'Mokaya Nyakundi' => ['jR1hs1GOqOY', 'photo-1590619312746-88a3736047a4'], // Zahra E (@zahraaa)
        'Wangui Ndegwa' => ['JeTGM9ib16U', 'photo-1517339234442-5cfdc9a90a69'], // sutirta budiman (@sutirtab)
        'Momanyi Orina' => ['muNoTY48VR8', 'photo-1793694697218-873c360a42f2'], // Zijian Wang (@dlow111)
        'Waithera Kiarie' => ['0prglfrYY08', 'photo-1510837267498-0148e51cdfc0'], // Harshil Gudka (@hgudka97)
        'Mugo Wachira' => ['LmaqbW9pUDw', 'photo-1777872721345-dccb2989f646'], // Ammar Sajan (@4mmar)
        'Njoki Gachanja' => ['gzVe-kEQzb0', 'photo-1627613499517-e16b34e7a89f'], // Anh Tuan To (@tuan1561)
        'Kariuki Ngugi' => ['Hy1laFYPMuc', 'photo-1705498146071-767a00cea644'], // Wolfgang Weiser (@hamburgmeinefreundin)
        'Auma Okello' => ['_Na29eYtYvM', 'photo-1613502259260-dd44a42ebdcd'], // Federico Burgalassi (@fede_burgalassi)
        'Parmuat Lekishon' => ['vSgINvbunfo', 'photo-1587354384930-f64486e5d5ca'], // Dexman Ten-Hwe (@dexman19)
        'Apiyo Oduor' => ['zKqq5M0hFsE', 'photo-1718207345122-46b342dadea1'], // Kali Graphist (@kaligraphist)
        'Lokol Ereng' => ['OEjTCc2Hwho', 'photo-1618275340450-a684fa3d7743'], // Marcus Urbenz (@marcusurbenz)
        'Taabu Nyongesa' => ['CD2ofGOkkWE', 'photo-1668068872931-b2349bb82e86'], // Amir Lehri (@amirlehri)
        'Nderitu Wahome' => ['zJfaxg2_kBs', 'photo-1727986234537-dd15392eda4c'], // Bastien Guillot (@bastha)
        'Nashipae Leshan' => ['5CXiV1Gqd5Y', 'photo-1771245784382-a4ef7873a042'], // Syawish Rehman (@syawishrehman)
        'Murage Kamande' => ['ZRZSmK362Xw', 'photo-1497993950456-cdb57afd1cf1'], // Ken Reid (@kennykiyoshi)
        'Akai Lomuria' => ['FilM6ng7VGQ', 'photo-1490650034439-fd184c3c86a5'], // Kari Shea (@karishea)
        'Kipkurui Chirchir' => ['ck1pT18psR4', 'photo-1602167130052-215c677d99b0'], // Niranjan Venkatesh (@mooncolonytn60)
    ];

    public static function avatarUrl(string $name): string
    {
        return 'https://images.unsplash.com/' . self::AVATARS[$name][1] . '?w=256&h=256&fit=crop&crop=entropy&q=80';
    }

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

        $this->fillAvatars();
    }

    /**
     * Gives existing accounts their picture too, but only while they still
     * have the default one, so a picture changed by hand is kept. Each photo
     * put to use is reported to Unsplash, as their API terms ask.
     */
    private function fillAvatars(): void
    {
        $unsplash = app(UnsplashService::class);

        collect(self::AVATARS)
            ->filter(fn (array $avatar, string $name) => User::query()
                ->where('email', static::email($name))
                ->where('avatar', 'avatars/male-avatar.png')
                ->update(['avatar' => static::avatarUrl($name)]))
            ->each(fn (array $avatar) => rescue(
                fn () => $unsplash->trackDownload(['links' => ['download_location' => "https://api.unsplash.com/photos/{$avatar[0]}/download"]]),
                report: false,
            ));
    }
}
