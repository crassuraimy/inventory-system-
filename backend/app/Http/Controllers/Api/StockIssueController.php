<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\StockIssue;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Services\InventoryService;

class StockIssueController extends Controller
{
    public function index() { return StockIssue::with('items.item')->orderByDesc('id')->paginate(50); }

    public function store(Request $request)
    {
        $data = $request->validate([
            'issue_date'=>'required|date',
            'department'=>'nullable|string|max:255',
            'issued_to'=>'nullable|string|max:255',
            'reason'=>'nullable|string',
            'items'=>'required|array|min:1',
            'items.*.item_id'=>'required|exists:items,id',
            'items.*.quantity'=>'required|numeric|gt:0',
        ]);

        $issue = DB::transaction(function () use ($data, $request) {
            $issue = StockIssue::create([
                'issue_number'=>'ISS-'.now()->format('YmdHis').'-'.random_int(100,999),
                'issue_date'=>$data['issue_date'],
                'department'=>$data['department'] ?? null,
                'issued_to'=>$data['issued_to'] ?? null,
                'status'=>'draft',
                'reason'=>$data['reason'] ?? null,
                'created_by'=>$request->user()->id,
            ]);
            foreach ($data['items'] as $line) $issue->items()->create($line);
            return $issue;
        });
        return response()->json($issue->load('items.item'), 201);
    }

    public function post(StockIssue $issue, Request $request, InventoryService $inventory)
    {
        return $inventory->postIssue($issue, (int)$request->user()->id)->load('items.item');
    }
}
