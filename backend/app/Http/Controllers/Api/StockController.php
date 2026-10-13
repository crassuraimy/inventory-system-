<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Item;
use App\Models\StockMovement;
use App\Services\InventoryService;
use Illuminate\Http\Request;

class StockController extends Controller
{
    public function balances(Request $request)
    {
        return Item::with(['category', 'unit', 'balance'])
            ->where('is_active', true)
            ->when($request->item_type, fn ($q, $t) => $q->where('item_type', $t))
            ->when($request->search, fn ($q, $s) => $q->where('name', 'like', "%$s%")->orWhere('item_code', 'like', "%$s%"))
            ->orderBy('name')->paginate((int) ($request->per_page ?: 100));
    }

    public function itemTransactions(Request $request, int $id)
    {
        return StockMovement::with('user')
            ->where('item_id', $id)
            ->when($request->movement_type, fn ($q, $v) => $q->where('movement_type', $v))
            ->when($request->from, fn ($q, $v) => $q->whereDate('movement_date', '>=', $v))
            ->when($request->to, fn ($q, $v) => $q->whereDate('movement_date', '<=', $v))
            ->orderByDesc('movement_date')->orderByDesc('id')
            ->paginate((int) ($request->per_page ?: 50));
    }

    public function opening(Request $request, InventoryService $inventory)
    {
        $data = $request->validate([
            'item_id' => 'required|exists:items,id',
            'movement_date' => 'required|date',
            'quantity' => 'required|numeric|min:0',
            'notes' => 'nullable|string',
            'request_key' => 'nullable|string|max:100',
        ]);
        $movement = $inventory->opening(
            (int) $data['item_id'], $data['movement_date'],
            (string) $data['quantity'], (int) $request->user()->id,
            $data['notes'] ?? null, $data['request_key'] ?? null
        );

        return response()->json($movement->load('item'), 201);
    }

    public function stockIn(Request $request, InventoryService $inventory)
    {
        $data = $request->validate([
            'item_id' => 'required|exists:items,id',
            'movement_date' => 'required|date',
            'quantity' => 'required|numeric|gt:0',
            'supplier_id' => 'nullable|exists:suppliers,id',
            'reference_number' => 'nullable|string|max:100',
            'invoice_reference' => 'nullable|string|max:100',
            'unit_cost' => 'nullable|numeric|min:0',
            'notes' => 'nullable|string',
            'request_key' => 'nullable|string|max:100',
        ]);
        $data['reference_number'] = $data['reference_number'] ?? $data['invoice_reference'] ?? null;
        $movement = $inventory->stockIn($data, (int) $request->user()->id);

        return response()->json($movement->load('item'), 201);
    }

    public function stockOut(Request $request, InventoryService $inventory)
    {
        $data = $request->validate([
            'item_id' => 'required|exists:items,id',
            'movement_date' => 'required|date',
            'quantity' => 'required|numeric|gt:0',
            'department' => 'nullable|string|max:255',
            'recipient' => 'nullable|string|max:255',
            'issued_to' => 'nullable|string|max:255',
            'reason' => 'nullable|string',
            'reference_number' => 'nullable|string|max:100',
            'notes' => 'nullable|string',
            'allow_negative' => 'boolean',
            'request_key' => 'nullable|string|max:100',
        ]);
        $data['department'] = $data['department'] ?? $data['recipient'] ?? $data['issued_to'] ?? null;
        $movement = $inventory->stockOut($data, (int) $request->user()->id);

        return response()->json($movement->load('item'), 201);
    }

    public function adjust(Request $request, InventoryService $inventory)
    {
        $data = $request->validate([
            'item_id' => 'required|exists:items,id',
            'movement_date' => 'nullable|date',
            'mode' => 'nullable|in:increase,decrease,set',
            'quantity' => 'required|numeric',
            'reason' => 'required|string|min:3',
            'reference_number' => 'nullable|string|max:100',
            'notes' => 'nullable|string',
            'request_key' => 'nullable|string|max:100',
        ]);
        $movement = $inventory->adjust($data, (int) $request->user()->id);

        return response()->json($movement->load('item'), 201);
    }

    public function reverse(int $id, Request $request, InventoryService $inventory)
    {
        $data = $request->validate(['reason' => 'required|string|min:3']);
        $movement = $inventory->reverse($id, (int) $request->user()->id, $data['reason']);

        return response()->json($movement->load('item'), 201);
    }

    public function movements(Request $request)
    {
        $data = $request->validate([
            'item_id' => 'nullable|exists:items,id',
            'user_id' => 'nullable|exists:users,id',
            'from' => 'nullable|date',
            'to' => 'nullable|date|after_or_equal:from',
            'type' => 'nullable|string',
            'movement_type' => 'nullable|string',
            'search' => 'nullable|string',
        ]);

        return StockMovement::with(['item:id,name,item_code', 'user:id,name,email'])
            ->when($data['item_id'] ?? null, fn ($q, $v) => $q->where('item_id', $v))
            ->when($data['user_id'] ?? null, fn ($q, $v) => $q->where('created_by', $v))
            ->when($data['from'] ?? null, fn ($q, $v) => $q->whereDate('movement_date', '>=', $v))
            ->when($data['to'] ?? null, fn ($q, $v) => $q->whereDate('movement_date', '<=', $v))
            ->when($data['movement_type'] ?? ($data['type'] ?? null), fn ($q, $v) => $q->where('movement_type', $v))
            ->when($data['search'] ?? null, fn ($q, $v) => $q->where('reference_number', 'like', "%$v%"))
            ->orderByDesc('movement_date')->orderByDesc('id')
            ->paginate((int) ($request->per_page ?: 100));
    }
}
