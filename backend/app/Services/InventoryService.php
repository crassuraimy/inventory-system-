<?php

namespace App\Services;

use App\Models\Item;
use App\Models\StockBalance;
use App\Models\StockMovement;
use App\Models\Purchase;
use App\Models\StockIssue;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class InventoryService
{
    public function opening(int $itemId, string $date, string $quantity, int $userId): StockMovement
    {
        if ((float) $quantity < 0) {
            throw ValidationException::withMessages(['quantity' => 'Opening quantity cannot be negative.']);
        }

        return DB::transaction(function () use ($itemId, $date, $quantity, $userId) {
            $item = Item::query()->whereKey($itemId)->lockForUpdate()->firstOrFail();
            $balance = StockBalance::query()->where('item_id', $item->id)->lockForUpdate()->firstOrFail();

            if (StockMovement::where('item_id', $item->id)->where('movement_type', 'opening')->exists()) {
                throw ValidationException::withMessages(['item_id' => 'Opening stock has already been entered for this item.']);
            }

            $qty = (float) $quantity;
            $movement = StockMovement::create([
                'item_id' => $item->id, 'movement_date' => $date, 'movement_type' => 'opening',
                'quantity_in' => $qty, 'quantity_out' => 0,
                'reference_type' => 'opening', 'reference_number' => 'OPENING',
                'created_by' => $userId,
            ]);
            $balance->quantity_on_hand = (float) $balance->quantity_on_hand + $qty;
            $balance->save();
            return $movement;
        });
    }

    public function postPurchase(Purchase $purchase, int $userId): Purchase
    {
        return DB::transaction(function () use ($purchase, $userId) {
            $purchase = Purchase::query()->whereKey($purchase->id)->lockForUpdate()->with('items')->firstOrFail();
            if ($purchase->status === 'posted') {
                throw ValidationException::withMessages(['purchase' => 'This purchase has already been posted.']);
            }
            if ($purchase->items->isEmpty()) {
                throw ValidationException::withMessages(['items' => 'Add at least one purchase item.']);
            }

            foreach ($purchase->items as $line) {
                $item = Item::query()->whereKey($line->item_id)->lockForUpdate()->firstOrFail();
                $balance = StockBalance::query()->where('item_id', $item->id)->lockForUpdate()->firstOrFail();

                StockMovement::create([
                    'item_id' => $item->id,
                    'movement_date' => $purchase->received_date ?? $purchase->purchase_date,
                    'movement_type' => 'purchase',
                    'quantity_in' => $line->quantity,
                    'quantity_out' => 0,
                    'reference_type' => 'purchase',
                    'reference_id' => $purchase->id,
                    'reference_number' => $purchase->purchase_number,
                    'created_by' => $userId,
                ]);
                $balance->quantity_on_hand = (float) $balance->quantity_on_hand + (float) $line->quantity;
                $balance->save();
            }

            $purchase->status = 'posted';
            $purchase->save();
            return $purchase;
        });
    }

    public function postIssue(StockIssue $issue, int $userId): StockIssue
    {
        return DB::transaction(function () use ($issue, $userId) {
            $issue = StockIssue::query()->whereKey($issue->id)->lockForUpdate()->with('items')->firstOrFail();
            if ($issue->status === 'posted') {
                throw ValidationException::withMessages(['issue' => 'This issue has already been posted.']);
            }
            if ($issue->items->isEmpty()) {
                throw ValidationException::withMessages(['items' => 'Add at least one issue item.']);
            }

            // Lock/check all balances before creating movements, so a failed issue posts nothing.
            foreach ($issue->items as $line) {
                $item = Item::query()->whereKey($line->item_id)->lockForUpdate()->firstOrFail();
                $balance = StockBalance::query()->where('item_id', $item->id)->lockForUpdate()->firstOrFail();
                if ((float) $line->quantity <= 0 || (float) $balance->quantity_on_hand < (float) $line->quantity) {
                    throw ValidationException::withMessages([
                        'items' => "Insufficient stock or invalid quantity for {$item->name}.",
                    ]);
                }
            }

            foreach ($issue->items as $line) {
                $item = Item::query()->whereKey($line->item_id)->firstOrFail();
                $balance = StockBalance::query()->where('item_id', $item->id)->lockForUpdate()->firstOrFail();

                StockMovement::create([
                    'item_id' => $item->id,
                    'movement_date' => $issue->issue_date,
                    'movement_type' => 'issue',
                    'quantity_in' => 0,
                    'quantity_out' => $line->quantity,
                    'reference_type' => 'stock_issue',
                    'reference_id' => $issue->id,
                    'reference_number' => $issue->issue_number,
                    'created_by' => $userId,
                ]);
                $balance->quantity_on_hand = (float) $balance->quantity_on_hand - (float) $line->quantity;
                $balance->save();
            }

            $issue->status = 'posted';
            $issue->save();
            return $issue;
        });
    }
}
