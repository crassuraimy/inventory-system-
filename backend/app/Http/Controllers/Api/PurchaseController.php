<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Purchase;
use App\Models\PurchaseReceipt;
use App\Services\InventoryService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PurchaseController extends Controller
{
    private function load(Purchase $purchase): Purchase
    {
        return $purchase->load(['supplier', 'items.item', 'receipts.items.item', 'receipts.creator:id,name']);
    }

    public function index(Request $request)
    {
        return Purchase::with(['supplier', 'items.item'])
            ->when($request->status, fn ($q, $v) => $q->where('status', $v))
            ->when($request->supplier_id, fn ($q, $v) => $q->where('supplier_id', $v))
            ->when($request->from, fn ($q, $v) => $q->whereDate('purchase_date', '>=', $v))
            ->when($request->to, fn ($q, $v) => $q->whereDate('purchase_date', '<=', $v))
            ->when($request->search, fn ($q, $s) => $q->where(fn ($w) => $w->where('purchase_number', 'like', "%$s%")->orWhere('invoice_number', 'like', "%$s%")))
            ->orderByDesc('id')
            ->paginate((int) ($request->per_page ?: 25));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'supplier_id' => 'required|exists:suppliers,id',
            'invoice_number' => 'nullable|string|max:100',
            'purchase_date' => 'required|date',
            'received_date' => 'nullable|date',
            'notes' => 'nullable|string',
            'items' => 'required|array|min:1',
            'items.*.item_id' => 'required|exists:items,id',
            'items.*.quantity' => 'required|numeric|gt:0',
            'items.*.batch_number' => 'nullable|string|max:100',
            'items.*.expiry_date' => 'nullable|date',
        ]);

        $purchase = DB::transaction(function () use ($data, $request) {
            $purchase = Purchase::create([
                'purchase_number' => 'PO-'.now()->format('YmdHis').'-'.random_int(100, 999),
                'supplier_id' => $data['supplier_id'],
                'invoice_number' => $data['invoice_number'] ?? null,
                'purchase_date' => $data['purchase_date'],
                'received_date' => $data['received_date'] ?? null,
                'status' => 'draft',
                'notes' => $data['notes'] ?? null,
                'created_by' => $request->user()->id,
            ]);
            foreach ($data['items'] as $line) {
                $purchase->items()->create($line);
            }

            return $purchase;
        });

        return response()->json($this->load($purchase), 201);
    }

    public function show(Purchase $purchase)
    {
        return $this->load($purchase);
    }

    public function update(Request $request, Purchase $purchase)
    {
        if (! in_array($purchase->status, ['draft', 'pending_approval'], true)) {
            throw ValidationException::withMessages(['purchase' => 'Only draft or pending approval orders can be edited.']);
        }
        if ($purchase->receipts()->exists()) {
            throw ValidationException::withMessages(['purchase' => 'Orders with posted receipts cannot be edited.']);
        }

        $data = $request->validate([
            'supplier_id' => 'sometimes|required|exists:suppliers,id',
            'invoice_number' => 'nullable|string|max:100',
            'purchase_date' => 'sometimes|required|date',
            'received_date' => 'nullable|date',
            'notes' => 'nullable|string',
            'items' => 'sometimes|array|min:1',
            'items.*.item_id' => 'required_with:items|exists:items,id',
            'items.*.quantity' => 'required_with:items|numeric|gt:0',
            'items.*.batch_number' => 'nullable|string|max:100',
            'items.*.expiry_date' => 'nullable|date',
        ]);

        DB::transaction(function () use ($data, $purchase) {
            if (isset($data['items'])) {
                $purchase->items()->delete();
                foreach ($data['items'] as $line) {
                    $purchase->items()->create($line);
                }
            }
            $purchase->update(collect($data)->except('items')->toArray());
        });

        return $this->load($purchase->fresh());
    }

    public function submit(Request $request, Purchase $purchase)
    {
        return $this->transition($purchase, ['draft'], 'pending_approval', 'purchase_submitted', $request);
    }

    public function approve(Request $request, Purchase $purchase)
    {
        return $this->transition($purchase, ['pending_approval', 'draft'], 'approved', 'purchase_approved', $request);
    }

    public function reject(Request $request, Purchase $purchase)
    {
        $request->validate(['reason' => 'nullable|string|max:1000']);

        return $this->transition($purchase, ['pending_approval', 'approved'], 'draft', 'purchase_rejected', $request);
    }

    public function markOrdered(Request $request, Purchase $purchase)
    {
        return $this->transition($purchase, ['approved'], 'ordered', 'purchase_ordered', $request);
    }

    public function cancel(Request $request, Purchase $purchase, InventoryService $inventory)
    {
        $request->validate(['reason' => 'nullable|string|max:1000']);

        return DB::transaction(function () use ($request, $purchase) {
            $purchase = Purchase::query()->whereKey($purchase->id)->lockForUpdate()->firstOrFail();
            if (in_array($purchase->status, ['fully_received', 'cancelled'], true)) {
                throw ValidationException::withMessages(['purchase' => 'This order cannot be cancelled.']);
            }
            if ($purchase->receipts()->where('status', 'posted')->exists()) {
                throw ValidationException::withMessages(['purchase' => 'Reverse posted receipts before cancelling this order.']);
            }
            $purchase->status = 'cancelled';
            $purchase->save();

            AuditLog::create([
                'user_id' => $request->user()->id,
                'action' => 'purchase_cancelled',
                'entity_type' => 'Purchase',
                'entity_id' => $purchase->id,
                'reason' => $request->input('reason'),
                'reference_number' => $purchase->purchase_number,
            ]);

            return $this->load($purchase->fresh());
        });
    }

    public function receive(Request $request, Purchase $purchase, InventoryService $inventory)
    {
        $data = $request->validate([
            'receipt_number' => 'nullable|string|max:50',
            'receipt_date' => 'nullable|date',
            'notes' => 'nullable|string|max:1000',
            'request_key' => 'nullable|string|max:100',
            'items' => 'required|array|min:1',
            'items.*.purchase_item_id' => 'required|integer|exists:purchase_items,id',
            'items.*.quantity' => 'required|numeric|gt:0',
        ]);

        $receipt = $inventory->receivePurchase($purchase, $data['items'], [
            'receipt_number' => $data['receipt_number'] ?? null,
            'receipt_date' => $data['receipt_date'] ?? null,
            'notes' => $data['notes'] ?? null,
            'request_key' => $data['request_key'] ?? null,
        ], (int) $request->user()->id);

        return response()->json($receipt, 201);
    }

    public function receipts(Purchase $purchase)
    {
        return $purchase->receipts()->with(['items.item', 'creator:id,name'])->get();
    }

    public function reverseReceipt(Request $request, Purchase $purchase, PurchaseReceipt $receipt, InventoryService $inventory)
    {
        if ((int) $receipt->purchase_id !== (int) $purchase->id) {
            throw ValidationException::withMessages(['receipt' => 'This receipt does not belong to the purchase order.']);
        }
        $data = $request->validate(['reason' => 'required|string|min:3|max:1000']);

        return response()->json($inventory->reverseReceipt($receipt, (int) $request->user()->id, $data['reason']), 201);
    }

    /**
     * Backwards compatible endpoint that receives every outstanding quantity.
     */
    public function post(Request $request, Purchase $purchase, InventoryService $inventory)
    {
        return $inventory->postPurchase($purchase, (int) $request->user()->id);
    }

    private function transition(Purchase $purchase, array $allowedFrom, string $to, string $action, Request $request)
    {
        return DB::transaction(function () use ($purchase, $allowedFrom, $to, $action, $request) {
            $purchase = Purchase::query()->whereKey($purchase->id)->lockForUpdate()->with('items')->firstOrFail();
            if (! in_array($purchase->status, $allowedFrom, true)) {
                throw ValidationException::withMessages(['purchase' => "The order cannot move from {$purchase->status} to {$to}."]);
            }
            if ($purchase->items->isEmpty()) {
                throw ValidationException::withMessages(['items' => 'Add at least one purchase item before submitting.']);
            }
            $purchase->status = $to;
            $purchase->save();

            AuditLog::create([
                'user_id' => $request->user()->id,
                'action' => $action,
                'entity_type' => 'Purchase',
                'entity_id' => $purchase->id,
                'new_values' => ['status' => $to],
                'reason' => $request->input('reason'),
                'reference_number' => $purchase->purchase_number,
            ]);

            return $this->load($purchase->fresh());
        });
    }
}
