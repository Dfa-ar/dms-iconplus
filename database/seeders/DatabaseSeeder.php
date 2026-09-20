<?php

namespace Database\Seeders;

// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // Data acuan/referensi — selalu jalan
        $this->call([
            RoleSeeder::class,
            KendalaReasonSeeder::class,
            SlaSettingSeeder::class,
        ]);

        // Data dummy untuk development lokal saja — jangan pernah dijalankan
        // di environment production.
        if (app()->environment('local')) {
            $this->call(DemoDataSeeder::class);
        }
    }
}
