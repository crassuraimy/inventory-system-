<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Item;
use App\Models\StockBalance;
use App\Models\StockMovement;
use App\Models\Supplier;
use App\Models\Unit;
use Illuminate\Database\Seeder;

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
            Category::firstOrCreate(['name' => $name], ['description' => $description]);
        }

        foreach ([
            ['Piece', 'pcs'], ['Box', 'box'], ['Pack', 'pack'],
            ['Bottle', 'btl'], ['Pair', 'pair'], ['Litre', 'L'],
        ] as [$name, $symbol]) {
            Unit::firstOrCreate(['symbol' => $symbol], ['name' => $name]);
        }

        foreach ([
            ['SUP-A1', 'Alpha Medical Supplies', 'Ardilah Noor', '012-3456789', 'sales@alphamed.example', 'Kuala Lumpur, Malaysia'],
            ['SUP-B2', 'BrightOffice Trading', 'Bryan Lim', '019-8877665', 'accounts@brightoffice.example', 'Selangor, Malaysia'],
            ['SUP-C3', 'CleanCare Distributors', 'Carol Tan', '017-1122334', 'orders@cleancare.example', 'Penang, Malaysia'],
        ] as [$reference, $name, $contact, $phone, $email, $address]) {
            Supplier::firstOrCreate(['name' => $name], [
                'contact_person' => $contact,
                'phone' => $phone,
                'email' => $email,
                'address' => $address,
                'is_active' => true,
            ]);
        }

        $categoryMap = Category::pluck('id', 'name');
        $unitMap = Unit::pluck('id', 'symbol');

        $items = [
            ['ITM-001', 'Disposable Gloves', 'medical', 'Medical Supplies', 'pcs', 150, 50],
            ['ITM-002', 'Face Masks', 'medical', 'Medical Supplies', 'box', 80, 40],
            ['ITM-003', 'Hand Sanitizer', 'medical', 'Medical Supplies', 'btl', 36, 20],
            ['ITM-004', 'A4 Paper Reams', 'general', 'Office Supplies', 'box', 25, 10],
            ['ITM-005', 'Ballpoint Pens (12 Pack)', 'general', 'Office Supplies', 'pack', 120, 30],
            ['ITM-006', 'Disinfectant Cleaner', 'general', 'Cleaning Supplies', 'btl', 18, 15],
            ['ITM-007', 'Floor Mop Set', 'general', 'Cleaning Supplies', 'pcs', 14, 5],
            ['ITM-008', 'Printer Ink Cartridge', 'general', 'Office Supplies', 'pcs', 8, 10],
        ];

        foreach ($items as [$code, $name, $type, $category, $unit, $minimum, $opening]) {
            $item = Item::firstOrCreate(['item_code' => $code], [
                'name' => $name,
                'item_type' => $type,
                'category_id' => $categoryMap[$category],
                'unit_id' => $unitMap[$unit],
                'description' => $name.' for store inventory.',
                'minimum_stock' => $minimum,
                'track_expiry' => false,
                'is_active' => true,
            ]);

            $balance = StockBalance::firstOrCreate(['item_id' => $item->id], [
                'quantity_on_hand' => $opening,
            ]);
            $balance->quantity_on_hand = $opening;
            $balance->save();

            if (! StockMovement::where('item_id', $item->id)->where('movement_type', 'opening')->exists()) {
                StockMovement::create([
                    'item_id' => $item->id,
                    'movement_date' => now()->toDateString(),
                    'movement_type' => 'opening',
                    'quantity_in' => $opening,
                    'quantity_out' => 0,
                    'balance_after' => $opening,
                    'reference_type' => 'opening',
                    'reference_number' => 'OPENING',
                    'created_by' => 1,
                ]);
            }
        }
    }
}
