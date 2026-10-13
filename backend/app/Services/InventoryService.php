<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\Item;
use App\Models\Purchase;
use App\Models\PurchaseReceipt;
use App\Models\PurchaseReceiptItem;
use App\Models\StockBalance;
use App\Models\StockIssue;
use App\Models\StockMovement;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class InventoryService
{
    private function balance(int $itemId): StockBalance
    {
        return StockBalance::query()->where('item_id', $itemId)->lockForUpdate()->firstOrFail();
    }

    private function ensureActive(Item $item): void
    {
        if (! $item->is_active) {
            throw ValidationException::withMessages(['item_id' => 'Inactive items cannot be used in stock movements.']);
        }
    }

    public function opening(int $itemId, string $date, string $quantity, int $userId, ?string $notes = null, ?string $requestKey = null): StockMovement
    {
        if ((float) $quantity < 0) {
            throw ValidationException::withMessages(['quantity' => 'Opening quantity cannot be negative.']);
        }

        return DB::transaction(function () use ($itemId, $date, $quantity, $userId, $notes, $requestKey) {
            if ($requestKey) {
                $existing = StockMovement::where('request_key', $requestKey)->first();
                if ($existing) {
                    return $existing;
                }
            }
            $item = Item::query()->whereKey($itemId)->lockForUpdate()->firstOrFail();
            $this->ensureActive($item);
            $balance = $this->balance($item->id);
            if (StockMovement::where('item_id', $item->id)->where('movement_type', 'opening')->exists()) {
                throw ValidationException::withMessages(['item_id' => 'Opening stock has already been entered for this item.']);
            }
            $qty = (float) $quantity;
            $new = (float) $balance->quantity_on_hand + $qty;
            $movement = StockMovement::create([
                'item_id' => $item->id,
                'movement_date' => $date,
                'movement_type' => 'opening',
                'quantity_in' => $qty,
                'quantity_out' => 0,
                'balance_after' => $new,
                'reference_type' => 'opening',
                'reference_number' => 'OPENING',
                'notes' => $notes,
                'request_key' => $requestKey,
                'created_by' => $userId,
            ]);
            $balance->quantity_on_hand = $new;
            $balance->save();

            return $movement;
        });
    }

    public function stockIn(array $data, int $userId): StockMovement
    {
        if ((float) $data['quantity'] <= 0) {
            throw ValidationException::withMessages(['quantity' => 'Received quantity must be greater than zero.']);
        }

        return DB::transaction(function () use ($data, $userId) {
            if (! empty($data['request_key'])) {
                $existing = StockMovement::where('request_key', $data['request_key'])->first();
                if ($existing) {
                    return $existing;
                }
            }
            $item = Item::query()->whereKey($data['item_id'])->lockForUpdate()->firstOrFail();
            $this->ensureActive($item);
            $balance = $this->balance($item->id);
            $qty = (float) $data['quantity'];
            $new = (float) $balance->quantity_on_hand + $qty;
            $movement = StockMovement::create([
                'item_id' => $item->id,
                'movement_date' => $data['movement_date'],
                'movement_type' => 'purchase',
                'quantity_in' => $qty,
                'quantity_out' => 0,
                'balance_after' => $new,
                'reference_type' => 'stock_in',
                'reference_id' => $data['supplier_id'] ?? null,
                'reference_number' => $data['reference_number'] ?? 'STOCKIN-'.now()->format('YmdHis'),
                'notes' => $data['notes'] ?? null,
                'request_key' => $data['request_key'] ?? null,
                'created_by' => $userId,
            ]);
            $balance->quantity_on_hand = $new;
            $balance->save();

            return $movement;
        });
    }

    public function stockOut(array $data, int $userId): StockMovement
    {
        if ((float) $data['quantity'] <= 0) {
            throw ValidationException::withMessages(['quantity' => 'Issued quantity must be greater than zero.']);
        }

        return DB::transaction(function () use ($data, $userId) {
            if (! empty($data['request_key'])) {
                $existing = StockMovement::where('request_key', $data['request_key'])->first();
                if ($existing) {
                    return $existing;
                }
            }
            $item = Item::query()->whereKey($data['item_id'])->lockForUpdate()->firstOrFail();
            $this->ensureActive($item);
            $balance = $this->balance($item->id);
            $qty = (float) $data['quantity'];
            if (! ($data['allow_negative'] ?? false) && (float) $balance->quantity_on_hand < $qty) {
                throw ValidationException::withMessages(['quantity' => 'Insufficient stock for '.$item->name.'.']);
            }
            $new = (float) $balance->quantity_on_hand - $qty;
            $movement = StockMovement::create([
                'item_id' => $item->id,
                'movement_date' => $data['movement_date'],
                'movement_type' => 'issue',
                'quantity_in' => 0,
                'quantity_out' => $qty,
                'balance_after' => $new,
                'reference_type' => 'stock_out',
                'reference_id' => null,
                'reference_number' => $data['reference_number'] ?? 'STOCKOUT-'.now()->format('YmdHis'),
                'notes' => ($data['department'] ?? null ? 'Department: '.$data['department'].'. ' : '').($data['reason'] ?? '').(isset($data['notes']) ? ' '.$data['notes'] : ''),
                'request_key' => $data['request_key'] ?? null,
                'created_by' => $userId,
            ]);
            $balance->quantity_on_hand = $new;
            $balance->save();

            return $movement;
        });
    }

    public function adjust(array $data, int $userId): StockMovement
    {
        $mode = $data['mode'] ?? 'increase';
        if (! in_array($mode, ['increase', 'decrease', 'set'], true)) {
            throw ValidationException::withMessages(['mode' => 'Invalid adjustment mode.']);
        }
        $reason = trim((string) ($data['reason'] ?? ''));
        if (strlen($reason) < 3) {
            throw ValidationException::withMessages(['reason' => 'A reason is required for stock adjustments.']);
        }

        return DB::transaction(function () use ($data, $userId, $mode, $reason) {
            if (! empty($data['request_key'])) {
                $existing = StockMovement::where('request_key', $data['request_key'])->first();
                if ($existing) {
                    return $existing;
                }
            }
            $item = Item::query()->whereKey($data['item_id'])->lockForUpdate()->firstOrFail();
            $this->ensureActive($item);
            $balance = $this->balance($item->id);
            $previous = (float) $balance->quantity_on_hand;
            $qty = (float) ($data['quantity'] ?? 0);
            $newQuantity = match ($mode) {
                'increase' => $previous + $qty,
                'decrease' => $previous - $qty,
                'set' => $qty,
            };
            $difference = $newQuantity - $previous;
            if (abs($difference) < 0.0000001) {
                throw ValidationException::withMessages(['quantity' => 'Adjustment quantity cannot be zero.']);
            }
            $outbound = $difference < 0;
            $amount = abs($difference);
            if ($outbound && (float) $balance->quantity_on_hand < $amount && ! ($data['allow_negative'] ?? false)) {
                // decrease amount cannot exceed stock for negative adjustment
                if (! ($data['allow_negative'] ?? false) && $amount > (float) $balance->quantity_on_hand) {
                    throw ValidationException::withMessages(['quantity' => 'Insufficient stock for negative adjustment.']);
                }
            }
            $movement = StockMovement::create([
                'item_id' => $item->id,
                'movement_date' => $data['movement_date'] ?? now()->toDateString(),
                'movement_type' => $outbound ? 'adjustment_out' : 'adjustment_in',
                'quantity_in' => $outbound ? 0 : $amount,
                'quantity_out' => $outbound ? $amount : 0,
                'balance_after' => $newQuantity,
                'reference_type' => 'adjustment',
                'reference_number' => $data['reference_number'] ?? 'ADJ-'.now()->format('YmdHis').'-'.random_int(100, 999),
                'notes' => 'Adjustment: previous='.$previous.', adjustment='.($difference >= 0 ? '+' : '').$difference.', new='.$newQuantity.'. Reason: '.$reason,
                'request_key' => $data['request_key'] ?? null,
                'created_by' => $userId,
            ]);
            $balance->quantity_on_hand = $newQuantity;
            $balance->save();
            AuditLog::create([
                'user_id' => $userId,
                'action' => 'stock_adjustment',
                'entity_type' => 'Item',
                'entity_id' => $item->id,
                'old_values' => ['quantity_on_hand' => $previous],
                'new_values' => ['quantity_on_hand' => $newQuantity],
                'reason' => $reason,
                'reference_number' => $movement->reference_number,
            ]);

            return $movement;
        });
    }

    public function reverse(int $movementId, int $userId, ?string $reason = null): StockMovement
    {
        $reason = trim((string) ($reason ?? ''));
        if (strlen($reason) < 3) {
            throw ValidationException::withMessages(['reason' => 'A reason is required for reversal.']);
        }

        return DB::transaction(function () use ($movementId, $userId, $reason) {
            $orig = StockMovement::query()->whereKey($movementId)->lockForUpdate()->firstOrFail();
            $item = Item::query()->whereKey($orig->item_id)->lockForUpdate()->firstOrFail();
            $balance = $this->balance($item->id);
            $in = (float) $orig->quantity_in;
            $out = (float) $orig->quantity_out;
            // reverse
            $new = (float) $balance->quantity_on_hand - $in + $out;
            if ($new < 0) {
                throw ValidationException::withMessages(['quantity' => 'Reversal would create negative stock.']);
            }
            $movement = StockMovement::create([
                'item_id' => $item->id,
                'movement_date' => now()->toDateString(),
                'movement_type' => $in > 0 ? 'adjustment_out' : 'adjustment_in',
                'quantity_in' => $out,
                'quantity_out' => $in,
                'balance_after' => $new,
                'reference_type' => 'reversal',
                'reference_id' => $orig->id,
                'reference_number' => 'REV-'.$orig->id.'-'.now()->format('YmdHis'),
                'notes' => 'Reversal of movement #'.$orig->id.'. Reason: '.$reason,
                'created_by' => $userId,
            ]);
            $balance->quantity_on_hand = $new;
            $balance->save();
            AuditLog::create([
                'user_id' => $userId,
                'action' => 'stock_reversal',
                'entity_type' => 'StockMovement',
                'entity_id' => $orig->id,
                'reason' => $reason,
                'reference_number' => $movement->reference_number,
            ]);

            return $movement;
        });
    }

    /**
     * Record a goods receipt against an approved/ordered purchase order.
     *
     * @param  array<int, array{purchase_item_id:int, quantity:float|int|string}>  $lines
     */
    public function receivePurchase(Purchase $purchase, array $lines, array $meta, int $userId): PurchaseReceipt
    {
        if (empty($lines)) {
            throw ValidationException::withMessages(['items' => 'At least one receipt line is required.']);
        }

        $requestKey = $meta['request_key'] ?? null;

        return DB::transaction(function () use ($purchase, $lines, $meta, $userId, $requestKey) {
            if ($requestKey) {
                $existing = PurchaseReceipt::where('purchase_id', $purchase->id)
                    ->where('request_key', $requestKey)
                    ->first();
                if ($existing) {
                    return $existing->load('items');
                }
            }

            $purchase = Purchase::query()->whereKey($purchase->id)->lockForUpdate()->with('items')->firstOrFail();

            if ($purchase->status === 'cancelled') {
                throw ValidationException::withMessages(['purchase' => 'Receipts cannot be posted against a cancelled purchase order.']);
            }
            if (! in_array($purchase->status, ['approved', 'ordered', 'partially_received'], true)) {
                throw ValidationException::withMessages(['purchase' => 'This purchase order must be approved before goods can be received.']);
            }
            if ($purchase->items->isEmpty()) {
                throw ValidationException::withMessages(['items' => 'Add at least one purchase item.']);
            }

            $receipt = PurchaseReceipt::create([
                'purchase_id' => $purchase->id,
                'receipt_number' => $meta['receipt_number'] ?? 'GRN-'.now()->format('YmdHis').'-'.random_int(100, 999),
                'request_key' => $requestKey,
                'receipt_date' => $meta['receipt_date'] ?? now()->toDateString(),
                'status' => 'posted',
                'notes' => $meta['notes'] ?? null,
                'created_by' => $userId,
            ]);

            $items = $purchase->items->keyBy('id');
            foreach ($lines as $line) {
                $purchaseItem = $items->get((int) $line['purchase_item_id']);
                if (! $purchaseItem) {
                    throw ValidationException::withMessages(['items' => 'Receipt line references an item that is not on this purchase order.']);
                }

                $qty = (float) $line['quantity'];
                if ($qty <= 0) {
                    throw ValidationException::withMessages(['items' => 'Received quantities must be greater than zero.']);
                }

                $outstanding = (float) $purchaseItem->quantity - (float) $purchaseItem->received_quantity;
                if ($qty - $outstanding > 0.0005) {
                    throw ValidationException::withMessages(['items' => 'Received quantity exceeds the outstanding quantity for this line.']);
                }

                $item = Item::query()->whereKey($purchaseItem->item_id)->lockForUpdate()->firstOrFail();
                $this->ensureActive($item);
                $balance = $this->balance($item->id);
                $new = (float) $balance->quantity_on_hand + $qty;

                StockMovement::create([
                    'item_id' => $item->id,
                    'movement_date' => $receipt->receipt_date,
                    'movement_type' => 'purchase',
                    'quantity_in' => $qty,
                    'quantity_out' => 0,
                    'balance_after' => $new,
                    'reference_type' => 'purchase_receipt',
                    'reference_id' => $receipt->id,
                    'reference_number' => $receipt->receipt_number,
                    'notes' => $meta['notes'] ?? $purchase->notes,
                    'created_by' => $userId,
                ]);

                $balance->quantity_on_hand = $new;
                $balance->save();

                $purchaseItem->received_quantity = (float) $purchaseItem->received_quantity + $qty;
                $purchaseItem->save();

                PurchaseReceiptItem::create([
                    'purchase_receipt_id' => $receipt->id,
                    'purchase_item_id' => $purchaseItem->id,
                    'item_id' => $item->id,
                    'quantity' => $qty,
                ]);
            }

            $purchase->received_date = $receipt->receipt_date;
            $purchase->status = $this->resolvePurchaseStatus($purchase->fresh('items'));
            $purchase->save();

            AuditLog::create([
                'user_id' => $userId, 'action' => 'purchase_received', 'entity_type' => 'Purchase', 'entity_id' => $purchase->id,
                'new_values' => ['receipt_number' => $receipt->receipt_number, 'status' => $purchase->status],
                'reason' => $meta['notes'] ?? null, 'reference_number' => $receipt->receipt_number,
            ]);

            return $receipt->load('items.item');
        });
    }

    /**
     * Reverse a posted goods receipt, returning stock and ordered/received quantities.
     */
    public function reverseReceipt(PurchaseReceipt $receipt, int $userId, string $reason): PurchaseReceipt
    {
        $reason = trim($reason);
        if (strlen($reason) < 3) {
            throw ValidationException::withMessages(['reason' => 'A reason is required for reversing a receipt.']);
        }

        return DB::transaction(function () use ($receipt, $userId, $reason) {
            $receipt = PurchaseReceipt::query()->whereKey($receipt->id)->lockForUpdate()->with('items')->firstOrFail();
            if ($receipt->status !== 'posted') {
                throw ValidationException::withMessages(['receipt' => 'Only posted receipts can be reversed.']);
            }

            $purchase = Purchase::query()->whereKey($receipt->purchase_id)->lockForUpdate()->with('items')->firstOrFail();

            foreach ($receipt->items as $line) {
                $item = Item::query()->whereKey($line->item_id)->lockForUpdate()->firstOrFail();
                $balance = $this->balance($item->id);
                $qty = (float) $line->quantity;
                if ((float) $balance->quantity_on_hand - $qty < -0.0000001) {
                    throw ValidationException::withMessages(['receipt' => 'Reversing this receipt would create negative stock. Adjust the affected items first.']);
                }
                $new = (float) $balance->quantity_on_hand - $qty;

                StockMovement::create([
                    'item_id' => $item->id,
                    'movement_date' => now()->toDateString(),
                    'movement_type' => 'adjustment_out',
                    'quantity_in' => 0,
                    'quantity_out' => $qty,
                    'balance_after' => $new,
                    'reference_type' => 'receipt_reversal',
                    'reference_id' => $receipt->id,
                    'reference_number' => 'REV-'.$receipt->receipt_number,
                    'notes' => 'Reversal of goods receipt '.$receipt->receipt_number.'. Reason: '.$reason,
                    'created_by' => $userId,
                ]);

                $balance->quantity_on_hand = $new;
                $balance->save();

                $purchaseItem = $purchase->items->firstWhere('id', $line->purchase_item_id);
                if ($purchaseItem) {
                    $purchaseItem->received_quantity = max(0, (float) $purchaseItem->received_quantity - $qty);
                    $purchaseItem->save();
                }
            }

            $receipt->status = 'reversed';
            $receipt->reversed_at = now();
            $receipt->reversed_by = $userId;
            $receipt->reversal_reason = $reason;
            $receipt->save();

            $purchase->status = $this->resolvePurchaseStatus($purchase->fresh('items'));
            $purchase->save();

            AuditLog::create([
                'user_id' => $userId, 'action' => 'receipt_reversed', 'entity_type' => 'PurchaseReceipt', 'entity_id' => $receipt->id,
                'reason' => $reason, 'reference_number' => $receipt->receipt_number,
            ]);

            return $receipt->load('items.item');
        });
    }

    private function resolvePurchaseStatus(Purchase $purchase): string
    {
        $items = $purchase->items;
        $anyReceived = $items->contains(fn ($line) => (float) $line->received_quantity > 0);
        $allReceived = $items->isNotEmpty() && $items->every(fn ($line) => (float) $line->received_quantity + 0.0005 >= (float) $line->quantity);

        if ($allReceived) {
            return 'fully_received';
        }
        if ($anyReceived) {
            return 'partially_received';
        }

        return 'ordered';
    }

    /**
     * Backwards compatible "post" action: receives all outstanding quantities.
     */
    public function postPurchase(Purchase $purchase, int $userId): Purchase
    {
        $fresh = Purchase::with('items')->findOrFail($purchase->id);
        $lines = $fresh->items
            ->map(fn ($line) => [
                'purchase_item_id' => $line->id,
                'quantity' => (float) $line->quantity - (float) $line->received_quantity,
            ])
            ->filter(fn ($line) => $line['quantity'] > 0)
            ->values()
            ->all();

        $this->receivePurchase($fresh, $lines, ['notes' => $fresh->notes], $userId);

        return $fresh->fresh(['supplier', 'items.item']);
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
            foreach ($issue->items as $line) {
                $item = Item::query()->whereKey($line->item_id)->lockForUpdate()->firstOrFail();
                $this->ensureActive($item);
                $balance = $this->balance($item->id);
                $qty = (float) $line->quantity;
                if ((float) $balance->quantity_on_hand < $qty) {
                    throw ValidationException::withMessages(['items' => "Insufficient stock or invalid quantity for {$item->name}."]);
                }
            }
            foreach ($issue->items as $line) {
                $item = Item::query()->whereKey($line->item_id)->firstOrFail();
                $balance = $this->balance($item->id);
                $qty = (float) $line->quantity;
                $new = (float) $balance->quantity_on_hand - $qty;
                StockMovement::create([
                    'item_id' => $item->id,
                    'movement_date' => $issue->issue_date,
                    'movement_type' => 'issue',
                    'quantity_in' => 0,
                    'quantity_out' => $qty,
                    'balance_after' => $new,
                    'reference_type' => 'stock_issue',
                    'reference_id' => $issue->id,
                    'reference_number' => $issue->issue_number,
                    'notes' => $issue->reason,
                    'created_by' => $userId,
                ]);
                $balance->quantity_on_hand = $new;
                $balance->save();
            }
            $issue->status = 'posted';
            $issue->save();
            AuditLog::create([
                'user_id' => $userId, 'action' => 'issue_posted', 'entity_type' => 'StockIssue', 'entity_id' => $issue->id,
                'reason' => $issue->reason, 'reference_number' => $issue->issue_number,
            ]);

            return $issue;
        });
    }
}
