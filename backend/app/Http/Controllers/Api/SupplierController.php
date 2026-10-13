<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Purchase;
use App\Models\Supplier;
use Illuminate\Http\Request;

class SupplierController extends Controller
{
    public function index(Request $request)
    {
        return Supplier::query()
            ->when(! $request->boolean('include_inactive'), fn ($q) => $q->where('is_active', true))
            ->when($request->search, fn ($q, $s) => $q->where('name', 'like', "%$s%")->orWhere('supplier_code', 'like', "%$s%"))
            ->orderBy('name')->paginate((int) ($request->per_page ?: 50));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'supplier_code' => 'nullable|string|max:50|unique:suppliers,supplier_code',
            'name' => 'required|string|max:255|unique:suppliers,name',
            'contact_person' => 'nullable|string|max:255',
            'phone' => 'nullable|string|max:50',
            'email' => 'nullable|email|max:255',
            'address' => 'nullable|string',
            'notes' => 'nullable|string',
            'is_active' => 'boolean',
        ]);
        if (empty($data['supplier_code'])) {
            $data['supplier_code'] = 'SUP-'.now()->format('YmdHis').'-'.random_int(10, 99);
        }
        $data['is_active'] = $data['is_active'] ?? true;

        return response()->json(Supplier::create($data), 201);
    }

    public function show(Supplier $supplier)
    {
        return $supplier->load(['items' => fn ($q) => $q->with('unit')]);
    }

    public function update(Request $request, Supplier $supplier)
    {
        $data = $request->validate([
            'supplier_code' => "nullable|string|max:50|unique:suppliers,supplier_code,{$supplier->id}",
            'name' => "sometimes|required|string|max:255|unique:suppliers,name,{$supplier->id}",
            'contact_person' => 'nullable|string|max:255',
            'phone' => 'nullable|string|max:50',
            'email' => 'nullable|email|max:255',
            'address' => 'nullable|string',
            'notes' => 'nullable|string',
            'is_active' => 'boolean',
        ]);
        $supplier->update($data);

        return $supplier->fresh();
    }

    public function destroy(Supplier $supplier)
    {
        if (Purchase::where('supplier_id', $supplier->id)->exists()) {
            return response()->json(['message' => 'Supplier has purchase history; deactivate it instead.'], 422);
        }
        $supplier->delete();

        return response()->noContent();
    }

    public function receipts(Supplier $supplier)
    {
        return Purchase::with('items.item')->where('supplier_id', $supplier->id)->orderByDesc('id')->paginate(50);
    }
}
