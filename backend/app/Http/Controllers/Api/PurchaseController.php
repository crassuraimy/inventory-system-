<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Purchase;
use Illuminate\Http\Request;
use App\Services\InventoryService;
use Illuminate\Support\Facades\DB;

class PurchaseController extends Controller
{
    public function index() { return Purchase::with(['supplier','items.item'])->orderByDesc('id')->paginate(50); }

    public function store(Request $request)
    {
        $data = $request->validate([
            'supplier_id'=>'required|exists:suppliers,id',
            'invoice_number'=>'nullable|string|max:100',
            'purchase_date'=>'required|date',
            'received_date'=>'nullable|date',
            'notes'=>'nullable|string',
            'items'=>'required|array|min:1',
            'items.*.item_id'=>'required|exists:items,id',
            'items.*.quantity'=>'required|numeric|gt:0',
            'items.*.batch_number'=>'nullable|string|max:100',
            'items.*.expiry_date'=>'nullable|date',
        ]);

        $purchase = DB::transaction(function () use ($data, $request) {
            $purchase = Purchase::create([
                'purchase_number'=>'PUR-'.now()->format('YmdHis').'-'.random_int(100,999),
                'supplier_id'=>$data['supplier_id'],
                'invoice_number'=>$data['invoice_number'] ?? null,
                'purchase_date'=>$data['purchase_date'],
                'received_date'=>$data['received_date'] ?? null,
                'status'=>'draft',
                'notes'=>$data['notes'] ?? null,
                'created_by'=>$request->user()->id,
            ]);
            foreach ($data['items'] as $line) {
                $purchase->items()->create($line);
            }
            return $purchase;
        });
        return response()->json($purchase->load(['supplier','items.item']), 201);
    }

    public function post(Purchase $purchase, Request $request, InventoryService $inventory)
    {
        return $inventory->postPurchase($purchase, (int)$request->user()->id)
            ->load(['supplier','items.item']);
    }
}
