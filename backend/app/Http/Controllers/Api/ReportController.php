<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Item;
use App\Models\Setting;
use App\Models\StockBalance;
use App\Models\StockMovement;
use Illuminate\Http\Request;

class ReportController extends Controller
{
    private function stockStatus(float $qty, float $reorder): string
    {
        if ($qty <= 0) {
            return 'out_of_stock';
        }
        if ($qty <= $reorder) {
            return 'low_stock';
        }

        return 'normal';
    }

    public function lowStock(Request $request)
    {
        $items = Item::with(['category', 'unit', 'supplier', 'balance'])
            ->where('is_active', true)
            ->when($request->search, fn ($q, $s) => $q->where('name', 'like', "%$s%")->orWhere('item_code', 'like', "%$s%"))
            ->when($request->category_id, fn ($q, $v) => $q->where('category_id', $v))
            ->get()
            ->filter(function ($item) {
                $qty = (float) $item->balance->quantity_on_hand;

                return $qty <= (float) $item->minimum_stock;
            })
            ->values();

        return $items->map(function ($item) {
            $qty = (float) $item->balance->quantity_on_hand;
            $reorder = (float) $item->minimum_stock;
            $target = $item->target_stock_level !== null ? (float) $item->target_stock_level : null;

            return [
                'item_id' => $item->id,
                'item_code' => $item->item_code,
                'name' => $item->name,
                'category' => $item->category?->name,
                'unit' => $item->unit?->symbol,
                'unit_name' => $item->unit?->name,
                'current_stock' => $qty,
                'reorder_level' => $reorder,
                'target_stock_level' => $target,
                'shortage' => max(0, $reorder - $qty),
                'suggested_replenishment' => $target !== null ? max(0, $target - $qty) : max(0, $reorder - $qty),
                'preferred_supplier' => $item->supplier?->name,
                'supplier_code' => $item->supplier?->supplier_code,
                'status' => $this->stockStatus($qty, $reorder),
            ];
        })->values();
    }

    public function dashboard(Request $request)
    {
        $lowStock = $this->lowStock($request);
        $today = now()->toDateString();

        return response()->json([
            'active_items' => Item::where('is_active', true)->count(),
            'total_units_in_stock' => (float) StockBalance::sum('quantity_on_hand'),
            'low_stock_count' => $lowStock->whereIn('status', ['low_stock', 'out_of_stock'])->count(),
            'out_of_stock_count' => $lowStock->where('status', 'out_of_stock')->count(),
            'stock_in_today' => (float) StockMovement::whereDate('movement_date', $today)->sum('quantity_in'),
            'stock_out_today' => (float) StockMovement::whereDate('movement_date', $today)->sum('quantity_out'),
            'stock_adjustment_today' => (float) StockMovement::whereDate('movement_date', $today)
                ->whereIn('movement_type', ['adjustment_in', 'adjustment_out'])->selectRaw('COALESCE(SUM(quantity_in+quantity_out),0) as t')->value('t'),
            'low_stock_items' => $lowStock->take(10)->values(),
            'recent_movements' => StockMovement::with('item:id,name,item_code', 'user:id,name')
                ->orderByDesc('id')->limit(10)->get(),
            'stock_status_breakdown' => [
                'out_of_stock' => $lowStock->where('status', 'out_of_stock')->count(),
                'low_stock' => $lowStock->where('status', 'low_stock')->count(),
            ],
        ]);
    }

    public function daily(Request $request)
    {
        $data = $request->validate(['date' => 'required|date']);

        return StockMovement::with('item:id,name,item_code', 'user:id,name')
            ->whereDate('movement_date', $data['date'])
            ->when($request->item_id, fn ($q, $v) => $q->where('item_id', $v))
            ->orderBy('item_id')->orderBy('id')->get();
    }

    public function monthly(Request $request)
    {
        $data = $request->validate([
            'month' => 'required|date_format:Y-m',
            'item_id' => 'nullable|exists:items,id',
            'category_id' => 'nullable|exists:categories,id',
        ]);
        $start = $data['month'].'-01';
        $end = date('Y-m-t', strtotime($start));
        $daysInMonth = (int) date('t', strtotime($start));

        $items = Item::with('balance', 'category')
            ->where('is_active', true)
            ->when($data['item_id'] ?? null, fn ($q, $v) => $q->whereKey($v))
            ->when($data['category_id'] ?? null, fn ($q, $v) => $q->where('category_id', $v))
            ->orderBy('name')->get();

        $rows = $items->map(function ($item) use ($start, $end, $daysInMonth) {
            $opening = (float) StockMovement::where('item_id', $item->id)
                ->whereDate('movement_date', '<', $start)
                ->selectRaw('COALESCE(SUM(quantity_in - quantity_out),0) as net')->value('net');

            $movements = StockMovement::where('item_id', $item->id)
                ->whereBetween('movement_date', [$start, $end])
                ->orderBy('movement_date')->orderBy('id')->get()
                ->groupBy(fn ($m) => $m->movement_date->format('Y-m-d'));

            $running = $opening;
            $daily = [];
            for ($day = 1; $day <= $daysInMonth; $day++) {
                $date = date('Y-m-d', strtotime($start.' +'.($day - 1).' days'));
                $dayMoves = $movements->get($date, collect());
                $in = (float) $dayMoves->sum('quantity_in');
                $out = (float) $dayMoves->sum('quantity_out');
                $adjIn = (float) $dayMoves->whereIn('movement_type', ['adjustment_in'])->sum('quantity_in') + (float) $dayMoves->where('movement_type', 'adjustment_in')->sum('quantity_in');
                // simpler: use movement quantities already split
                $dayOpening = $running;
                $running += $in - $out;
                $daily[] = [
                    'date' => $date,
                    'opening' => (float) $dayOpening,
                    'stock_in' => (float) $in,
                    'stock_out' => (float) $out,
                    'adjustments_in' => (float) $dayMoves->where('movement_type', 'adjustment_in')->sum('quantity_in'),
                    'adjustments_out' => (float) $dayMoves->where('movement_type', 'adjustment_out')->sum('quantity_out'),
                    'closing' => (float) $running,
                    'status' => $this->stockStatus($running, (float) $item->minimum_stock),
                ];
            }
            $totalIn = (float) collect($daily)->sum('stock_in');
            $totalOut = (float) collect($daily)->sum('stock_out');

            return [
                'item_id' => $item->id,
                'item_code' => $item->item_code,
                'name' => $item->name,
                'unit' => $item->unit?->symbol,
                'category_id' => $item->category_id,
                'opening' => $opening,
                'daily' => $daily,
                'total_in' => $totalIn,
                'total_out' => $totalOut,
                'total_adjustments_in' => (float) collect($daily)->sum('adjustments_in'),
                'total_adjustments_out' => (float) collect($daily)->sum('adjustments_out'),
                'closing' => $running,
            ];
        })->values();

        return response()->json([
            'month' => $data['month'],
            'days_in_month' => $daysInMonth,
            'data' => $rows,
        ]);
    }

    public function stockInReport(Request $request)
    {
        $data = $request->validate([
            'from' => 'nullable|date', 'to' => 'nullable|date|after_or_equal:from',
            'item_id' => 'nullable|exists:items,id', 'category_id' => 'nullable|exists:categories,id',
        ]);

        return StockMovement::with('item.category', 'user')
            ->where('quantity_in', '>', 0)
            ->when($data['from'] ?? null, fn ($q, $v) => $q->whereDate('movement_date', '>=', $v))
            ->when($data['to'] ?? null, fn ($q, $v) => $q->whereDate('movement_date', '<=', $v))
            ->when($data['item_id'] ?? null, fn ($q, $v) => $q->where('item_id', $v))
            ->when($data['category_id'] ?? null, fn ($q, $v) => $q->whereHas('item', fn ($i) => $i->where('category_id', $v)))
            ->orderByDesc('movement_date')->orderByDesc('id')
            ->paginate((int) ($request->per_page ?: 100));
    }

    public function stockOutReport(Request $request)
    {
        $data = $request->validate([
            'from' => 'nullable|date', 'to' => 'nullable|date|after_or_equal:from',
            'item_id' => 'nullable|exists:items,id', 'category_id' => 'nullable|exists:categories,id',
        ]);

        return StockMovement::with('item.category', 'user')
            ->where('quantity_out', '>', 0)
            ->when($data['from'] ?? null, fn ($q, $v) => $q->whereDate('movement_date', '>=', $v))
            ->when($data['to'] ?? null, fn ($q, $v) => $q->whereDate('movement_date', '<=', $v))
            ->when($data['item_id'] ?? null, fn ($q, $v) => $q->where('item_id', $v))
            ->when($data['category_id'] ?? null, fn ($q, $v) => $q->whereHas('item', fn ($i) => $i->where('category_id', $v)))
            ->orderByDesc('movement_date')->orderByDesc('id')
            ->paginate((int) ($request->per_page ?: 100));
    }

    public function valuation()
    {
        return Item::with(['unit', 'balance'])
            ->where('is_active', true)
            ->get()
            ->map(fn ($item) => [
                'item_id' => $item->id,
                'item_code' => $item->item_code,
                'name' => $item->name,
                'unit' => $item->unit?->symbol,
                'quantity' => (float) $item->balance->quantity_on_hand,
                'unit_cost' => (float) ($item->unit_cost ?? 0),
                'value' => (float) $item->balance->quantity_on_hand * (float) ($item->unit_cost ?? 0),
            ]);
    }

    /**
     * Shared, non-sensitive application metadata for authenticated screens
     * such as the company name used on print headers.
     */
    public function meta()
    {
        $settings = Setting::whereIn('key', ['company_name', 'company_address', 'company_phone', 'currency'])
            ->pluck('value', 'key');

        return response()->json([
            'company_name' => $settings['company_name'] ?? 'Inventory Management',
            'company_address' => $settings['company_address'] ?? null,
            'company_phone' => $settings['company_phone'] ?? null,
            'currency' => $settings['currency'] ?? 'MYR',
        ]);
    }
}
