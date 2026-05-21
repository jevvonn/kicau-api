<?php

namespace Database\Seeders;

use App\Models\CharacterValue;
use Illuminate\Database\Seeder;

class CharacterValueSeeder extends Seeder
{
    public function run(): void
    {
        $values = [
            ['key' => 'kejujuran', 'label' => 'Kejujuran', 'color' => '#E66B85', 'sort_order' => 1],
            ['key' => 'menabung', 'label' => 'Menabung', 'color' => '#F59330', 'sort_order' => 2],
            ['key' => 'berbagi', 'label' => 'Berbagi', 'color' => '#D45872', 'sort_order' => 3],
            ['key' => 'bijak', 'label' => 'Bijak', 'color' => '#6F62C8', 'sort_order' => 4],
        ];

        foreach ($values as $row) {
            CharacterValue::updateOrCreate(['key' => $row['key']], $row);
        }
    }
}
