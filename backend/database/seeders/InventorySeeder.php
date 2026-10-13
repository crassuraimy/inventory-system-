<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Category;
use App\Models\Unit;

class InventorySeeder extends Seeder
{
    public function run(): void
    {
        foreach ([
            ['Medical Supplies', 'Medical supplies and consumables'],
            ['Office Supplies', 'Office and administrative items'],
            ['Cleaning Supplies', 'Cleaning and hygiene items'],
            ['Other', 'Other general inventory'],
        ] as [$name, $description]) {
            Category::firstOrCreate(['name'=>$name], ['description'=>$description]);
        }

        foreach ([
            ['Piece', 'pcs'], ['Box', 'box'], ['Pack', 'pack'],
            ['Bottle', 'btl'], ['Pair', 'pair'], ['Litre', 'L']
        ] as [$name, $symbol]) {
            Unit::firstOrCreate(['symbol'=>$symbol], ['name'=>$name]);
        }
    }
}
