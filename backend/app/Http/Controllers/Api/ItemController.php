<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Item;
use App\Models\StockBalance;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ItemController extends Controller
{
    public function index(Request $request)
    {
        $sort = in_array($request->sort, ['name', 'item_code', 'created_at', 'minimum_stock'], true) ? $request->sort : 'name';
        $dir = $request->direction === 'desc' ? 'desc' : 'asc';

        return Item::query()->with(['category', 'unit', 'supplier', 'balance'])
            ->when($request->search, fn ($q, $s) => $q->where(fn ($w) => $w->where('name', 'like', "%$s%")->orWhere('item_code', 'like', "%$s%")))
            ->when($request->category_id, fn ($q, $v) => $q->where('category_id', $v))
            ->when($request->item_type, fn ($q, $t) => $q->where('item_type', $t))
            ->when($request->filled('is_active'), fn ($q) => $q->where('is_active', $request->boolean('is_active')))
            ->when($request->stock_status, function ($q, $status) {
                if ($status === 'out') {
                    $q->whereHas('balance', fn ($b) => $b->where('quantity_on_hand', '<=', 0));
                }
                if ($status === 'low') {
                    $q->whereHas('balance', fn ($b) => $b->whereRaw('quantity_on_hand > 0 and quantity_on_hand <= (select minimum_stock from items where items.id = stock_balances.item_id)'));
                }
                if ($status === 'normal') {
                    $q->whereHas('balance', fn ($b) => $b->whereRaw('quantity_on_hand > (select minimum_stock from items where items.id = stock_balances.item_id)'));
                }
            })
            ->orderBy($sort, $dir)
            ->paginate((int) ($request->per_page ?: 50));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'item_code' => 'required|string|max:100|unique:items,item_code',
            'name' => 'required|string|max:255',
            'item_type' => 'required|in:medical,general',
            'category_id' => 'required|exists:categories,id',
            'unit_id' => 'required|exists:units,id',
            'description' => 'nullable|string',
            'minimum_stock' => 'required|numeric|min:0',
            'target_stock_level' => 'nullable|numeric|min:0',
            'preferred_supplier_id' => 'nullable|exists:suppliers,id',
            'unit_cost' => 'nullable|numeric|min:0',
            'track_expiry' => 'boolean',
            'is_active' => 'boolean',
        ]);

        return DB::transaction(function () use ($data, $request) {
            $item = Item::create($data);
            StockBalance::create(['item_id' => $item->id, 'quantity_on_hand' => 0]);
            AuditLog::create([
                'user_id' => $request->user()->id,
                'action' => 'item_created',
                'entity_type' => 'Item',
                'entity_id' => $item->id,
                'new_values' => $item->toArray(),
            ]);

            return response()->json($item->fresh()->load(['category', 'unit', 'supplier', 'balance']), 201);
        });
    }

    public function show(Item $item)
    {
        return $item->load(['category', 'unit', 'supplier', 'balance', 'movements']);
    }

    public function update(Request $request, Item $item)
    {
        $data = $request->validate([
            'item_code' => "sometimes|required|string|max:100|unique:items,item_code,{$item->id}",
            'name' => 'sometimes|required|string|max:255',
            'item_type' => 'sometimes|required|in:medical,general',
            'category_id' => 'sometimes|required|exists:categories,id',
            'unit_id' => 'sometimes|required|exists:units,id',
            'description' => 'nullable|string',
            'minimum_stock' => 'sometimes|required|numeric|min:0',
            'target_stock_level' => 'nullable|numeric|min:0',
            'preferred_supplier_id' => 'nullable|exists:suppliers,id',
            'unit_cost' => 'nullable|numeric|min:0',
            'track_expiry' => 'boolean',
            'is_active' => 'boolean',
        ]);

        $old = $item->toArray();
        $item->update($data);
        AuditLog::create([
            'user_id' => $request->user()->id,
            'action' => 'item_updated',
            'entity_type' => 'Item',
            'entity_id' => $item->id,
            'old_values' => $old,
            'new_values' => $item->fresh()->toArray(),
        ]);

        return $item->fresh()->load(['category', 'unit', 'supplier', 'balance']);
    }

    public function destroy(Item $item)
    {
        if ($item->movements()->exists()) {
            return response()->json(['message' => 'Item has stock history; deactivate it instead.'], 422);
        }
        $item->delete();

        return response()->noContent();
    }
}
