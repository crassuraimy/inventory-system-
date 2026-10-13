<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Item;
use App\Models\StockMovement;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ReportController extends Controller
{
    public function lowStock()
    {
        return Item::query()->with(['unit','category','balance'])
            ->join('stock_balances', 'items.id', '=', 'stock_balances.item_id')
            ->where('items.is_active', true)
            ->whereColumn('stock_balances.quantity_on_hand', '<=', 'items.minimum_stock')
            ->select('items.*')
            ->orderBy('items.name')->get()
            ->map(function ($item) {
                $qty = (float) $item->balance->quantity_on_hand;
                $min = (float) $item->minimum_stock;
                return [
                    'item_id'=>$item->id, 'item_code'=>$item->item_code, 'name'=>$item->name,
                    'item_type'=>$item->item_type, 'unit'=>$item->unit->symbol,
                    'quantity_on_hand'=>$qty, 'minimum_stock'=>$min,
                    'shortage'=>max(0, $min - $qty),
                    'status'=>$qty <= 0 ? 'out_of_stock' : 'low_stock',
                ];
            });
    }

    public function dashboard()
    {
        return response()->json([
            'active_items'=>Item::where('is_active', true)->count(),
            'low_stock_count'=>$this->lowStock()->count(),
            'stock_in_today'=>(float) StockMovement::whereDate('movement_date', today())->sum('quantity_in'),
            'stock_out_today'=>(float) StockMovement::whereDate('movement_date', today())->sum('quantity_out'),
            'low_stock_items'=>$this->lowStock()->take(10)->values(),
        ]);
    }

    public function daily(Request $request)
    {
        $data = $request->validate(['date'=>'required|date']);
        return StockMovement::with('item')
            ->whereDate('movement_date', $data['date'])
            ->orderBy('item_id')->orderBy('id')->get();
    }

    public function monthly(Request $request)
    {
        $data = $request->validate(['month'=>'required|date_format:Y-m']);
        $start = $data['month'].'-01';
        $end = date('Y-m-t', strtotime($start));
        $items = Item::with('balance')->where('is_active', true)->orderBy('name')->get();

        $rows = $items->map(function ($item) use ($start, $end) {
            $opening = (float) StockMovement::where('item_id',$item->id)
                ->whereDate('movement_date','<',$start)
                ->selectRaw('COALESCE(SUM(quantity_in - quantity_out),0) as net')->value('net');

            $movements = StockMovement::where('item_id',$item->id)
                ->whereBetween('movement_date',[$start,$end])
                ->orderBy('movement_date')->orderBy('id')->get()
                ->groupBy(fn($m) => $m->movement_date->format('Y-m-d'));

            $running = $opening;
            $daily = [];
            $days = (int) date('t', strtotime($start));
            for ($day = 1; $day <= $days; $day++) {
                $date = date('Y-m-d', strtotime($start.' +'.($day-1).' days'));
                $dayMoves = $movements->get($date, collect());
                $in = (float) $dayMoves->sum('quantity_in');
                $out = (float) $dayMoves->sum('quantity_out');
                $running += $in - $out;
                $daily[] = ['date'=>$date,'stock_in'=>$in,'stock_out'=>$out,'closing'=>$running];
            }

            return [
                'item_id'=>$item->id, 'item_code'=>$item->item_code, 'name'=>$item->name,
                'item_type'=>$item->item_type, 'opening'=>$opening,
                'daily'=>$daily,
                'total_in'=>(float) collect($daily)->sum('stock_in'),
                'total_out'=>(float) collect($daily)->sum('stock_out'),
                'closing'=>$running,
            ];
        });

        return response()->json([
            'month'=>$data['month'],
            'days_in_month'=>(int) date('t', strtotime($start)),
            'data'=>$rows,
        ]);
    }
}
