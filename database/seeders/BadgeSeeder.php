<?php

namespace Database\Seeders;

use App\Models\Badge;
use Illuminate\Database\Seeder;

class BadgeSeeder extends Seeder
{
    public function run(): void
    {
        $badges = [
            ['key' => 'pahlawan_berbagi', 'name' => 'Pahlawan Berbagi', 'description' => "Selesai 5 cerita 'berbagi'", 'icon' => 'heart', 'color' => '#FFD8E3', 'sort_order' => 1],
            ['key' => 'si_jujur_hebat', 'name' => 'Si Jujur Hebat', 'description' => 'Pilihan jujur 10x berturut', 'icon' => 'check', 'color' => '#FFE4BC', 'sort_order' => 2],
            ['key' => 'penabung_cilik', 'name' => 'Penabung Cilik', 'description' => "Selesai 3 cerita 'menabung'", 'icon' => 'wallet', 'color' => '#D9D5F4', 'sort_order' => 3],
            ['key' => 'petualang_cerdas', 'name' => 'Petualang Cerdas', 'description' => '10 jelajah selesai', 'icon' => 'compass', 'color' => '#C7E4C2', 'sort_order' => 4],
            ['key' => 'master_cerita', 'name' => 'Master Cerita', 'description' => 'Selesaikan 25 cerita', 'icon' => 'trophy', 'color' => '#FCD968', 'sort_order' => 5],
            ['key' => 'pembelajar_setia', 'name' => 'Pembelajar Setia', 'description' => 'Belajar 30 hari beruntun', 'icon' => 'fire', 'color' => '#BFE3F3', 'sort_order' => 6],
        ];

        foreach ($badges as $row) {
            Badge::updateOrCreate(['key' => $row['key']], $row);
        }
    }
}
