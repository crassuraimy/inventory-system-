<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Supplier;
use Illuminate\Http\Request;

class SupplierController extends Controller
{
    public function index() { return Supplier::where('is_active', true)->orderBy('name')->paginate(50); }
    public function store(Request $request) {
        $data = $request->validate([
            'name'=>'required|string|max:255','contact_person'=>'nullable|string|max:255',
            'phone'=>'nullable|string|max:50','email'=>'nullable|email|max:255',
            'address'=>'nullable|string','is_active'=>'boolean'
        ]);
        return response()->json(Supplier::create($data), 201);
    }
}
