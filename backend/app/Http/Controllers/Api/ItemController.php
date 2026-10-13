<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Item;
use App\Models\StockBalance;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ItemController extends Controller
{
    public function index(Request $request)
    {
        return Item::query()->with(['category', 'unit', 'balance'])
            ->when($request->search, fn($q, $s) => $q->where(fn($w) => $w->where('name','like',"%$s%")->orWhere('item_code','like',"%$s%")))
            ->when($request->item_type, fn($q, $t) => $q->where('item_type', $t))
            ->orderBy('name')->paginate(50);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'item_code'=>'required|string|max:100|unique:items,item_code',
            'name'=>'required|string|max:255',
            'item_type'=>'required|in:medical,general',
            'category_id'=>'required|exists:categories,id',
            'unit_id'=>'required|exists:units,id',
            'description'=>'nullable|string',
            'minimum_stock'=>'required|numeric|min:0',
            'track_expiry'=>'boolean',
        ]);

        return DB::transaction(function () use ($data) {
            $item = Item::create($data);
            StockBalance::create(['item_id'=>$item->id, 'quantity_on_hand'=>0]);
            return response()->json($item->load(['category','unit','balance']), 201);
        });
    }

    public function show(Item $item) { return $item->load(['category','unit','balance','movements']); }

    public function update(Request $request, Item $item)
    {
        $data = $request->validate([
            'item_code'=>"sometimes|required|string|max:100|unique:items,item_code,{$item->id}",
            'name'=>'sometimes|required|string|max:255',
            'item_type'=>'sometimes|required|in:medical,general',
            'category_id'=>'sometimes|required|exists:categories,id',
            'unit_id'=>'sometimes|required|exists:units,id',
            'description'=>'nullable|string',
            'minimum_stock'=>'sometimes|required|numeric|min:0',
            'track_expiry'=>'boolean',
            'is_active'=>'boolean',
        ]);
        $item->update($data);
        return $item->fresh()->load(['category','unit','balance']);
    }

    public function destroy(Item $item)
    {
        if ($item->movements()->exists()) {
            return response()->json(['message'=>'Item has stock history; deactivate it instead.'], 422);
        }
        $item->delete();
        return response()->noContent();
    }
}
