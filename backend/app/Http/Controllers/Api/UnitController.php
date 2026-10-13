<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Unit;
use Illuminate\Http\Request;

class UnitController extends Controller
{
    public function index(Request $request)
    {
        return Unit::query()
            ->when(! $request->boolean('include_inactive'), fn ($q) => $q->where('is_active', true))
            ->when($request->search, fn ($q, $s) => $q->where('name', 'like', "%$s%")->orWhere('symbol', 'like', "%$s%"))
            ->orderBy('name')->get();
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:100',
            'symbol' => 'required|string|max:20|unique:units,symbol',
            'is_active' => 'boolean',
        ]);
        $data['is_active'] = $data['is_active'] ?? true;

        return response()->json(Unit::create($data), 201);
    }

    public function show(Unit $unit)
    {
        return $unit->loadCount('items');
    }

    public function update(Request $request, Unit $unit)
    {
        $data = $request->validate([
            'name' => 'sometimes|required|string|max:100',
            'symbol' => "sometimes|required|string|max:20|unique:units,symbol,{$unit->id}",
            'is_active' => 'boolean',
        ]);
        $unit->update($data);

        return $unit->fresh()->loadCount('items');
    }

    public function destroy(Unit $unit)
    {
        if ($unit->items()->exists()) {
            return response()->json(['message' => 'Unit is referenced by inventory items; deactivate it instead.'], 422);
        }
        $unit->delete();

        return response()->noContent();
    }
}
