<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        if (config("app.env") == "production") {
            $this->call([
                RoleSeeder::class,
                AdminUserSeeder::class,
            ]);
        } else {
            $this->call([
                RoleSeeder::class,
                AdminUserSeeder::class,
                UserSeeder::class,
                PhotoCompetitionSeeder::class,
            ]);
        }
    }
}
