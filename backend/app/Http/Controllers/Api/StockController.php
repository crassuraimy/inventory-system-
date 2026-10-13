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
        return Item::with(['category','unit','balance'])
            ->where('is_active', true)
            ->when($request->item_type, fn($q,$t) => $q->where('item_type',$t))
            ->orderBy('name')->paginate(100);
    }

    public function opening(Request $request, InventoryService $inventory)
    {
        $data = $request->validate([
            'item_id'=>'required|exists:items,id',
            'movement_date'=>'required|date',
            'quantity'=>'required|numeric|min:0',
        ]);
        $movement = $inventory->opening(
            (int)$data['item_id'], $data['movement_date'],
            (string)$data['quantity'], (int)$request->user()->id
        );
        return response()->json($movement, 201);
    }

    public function movements(Request $request)
    {
        $data = $request->validate([
            'item_id'=>'nullable|exists:items,id',
            'from'=>'nullable|date',
            'to'=>'nullable|date|after_or_equal:from',
            'movement_type'=>'nullable|in:opening,purchase,issue,adjustment_in,adjustment_out',
        ]);
        return StockMovement::with('item')
            ->when($data['item_id'] ?? null, fn($q,$v) => $q->where('item_id',$v))
            ->when($data['from'] ?? null, fn($q,$v) => $q->whereDate('movement_date','>=',$v))
            ->when($data['to'] ?? null, fn($q,$v) => $q->whereDate('movement_date','<=',$v))
            ->when($data['movement_type'] ?? null, fn($q,$v) => $q->where('movement_type',$v))
            ->orderByDesc('movement_date')->orderByDesc('id')->paginate(100);
    }
}
