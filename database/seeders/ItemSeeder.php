<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Item;

class ItemSeeder extends Seeder
{
    public function run(): void
    {
        Item::insert([
            [
                'name' => 'Lukisan Vintage 1920',
                'description' => 'Lukisan langka karya pelukis ternama dari era 1920an',
                'starting_price' => 5000000,
                'current_highest_bid' => 7500000,
                'auction_status' => 'OPEN',
                'auction_deadline' => '2026-06-30 18:00:00',
                'image_url' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Jam Tangan Antik',
                'description' => 'Jam tangan antik buatan Swiss tahun 1950',
                'starting_price' => 3000000,
                'current_highest_bid' => 0,
                'auction_status' => 'OPEN',
                'auction_deadline' => '2026-07-15 18:00:00',
                'image_url' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Kamera Film Klasik',
                'description' => 'Kamera film klasik merk Leica kondisi masih berfungsi',
                'starting_price' => 8000000,
                'current_highest_bid' => 9000000,
                'auction_status' => 'CLOSED',
                'auction_deadline' => '2026-05-01 18:00:00',
                'image_url' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }
}