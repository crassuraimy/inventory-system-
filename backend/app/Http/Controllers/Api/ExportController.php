<?php

namespace App\Http\Controllers\Api;

use App\Exports\GenericExport;
use App\Http\Controllers\Controller;
use App\Models\Item;
use App\Models\Purchase;
use App\Models\Setting;
use App\Models\StockBalance;
use App\Models\StockMovement;
use App\Services\ExportService;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\Response;

class ExportController extends Controller
{
    /**
     * Dataset => [headings, row builder]. Row builders return iterable rows.
     */
    public function download(Request $request, string $dataset): Response
    {
        $format = strtolower((string) $request->query('format', 'csv'));
        abort_unless(in_array($format, ['csv', 'xlsx', 'xls'], true), 422, 'Unsupported export format.');

        [$headings, $rows, $filename] = match ($dataset) {
            'items' => $this->items($request),
            'stock-balances' => $this->stockBalances($request),
            'stock-in' => $this->stockIn($request),
            'stock-out' => $this->stockOut($request),
            'stock-history' => $this->stockHistory($request),
            'low-stock' => $this->lowStock($request),
            'daily' => $this->daily($request),
            'monthly' => $this->monthly($request),
            'purchases' => $this->purchases($request),
            default => abort(404, 'Unknown export dataset.'),
        };

        if ($format === 'csv') {
            return ExportService::csv($filename, $headings, $rows);
        }

        if (class_exists(Excel::class)) {
            $data = is_array($rows) ? $rows : iterator_to_array($rows, false);

            return Excel::download(
                new GenericExport($headings, $data),
                preg_replace('/\.(csv|xlsx?|xml)$/i', '', $filename).'.xlsx'
            );
        }

        return ExportService::excelXml($filename, $headings, $rows);
    }

    private function companyName(): string
    {
        try {
            return (string) (Setting::where('key', 'company_name')->value('value') ?: 'Inventory Management');
        } catch (\Throwable) {
            return 'Inventory Management';
        }
    }

    private function periodLabel(Request $request, string $fallback = 'All dates'): string
    {
        $from = $request->query('from');
        $to = $request->query('to');
        if ($request->filled('month')) {
            return 'Month '.$request->query('month');
        }
        if ($request->filled('date')) {
            return 'Date '.$request->query('date');
        }
        if ($from || $to) {
            return 'Period '.($from ?: 'start').' to '.($to ?: 'today');
        }

        return $fallback;
    }

    /** @return array{0:array,1:iterable,2:string} */
    private function items(Request $request): array
    {
        $query = Item::with(['category', 'unit', 'supplier', 'balance'])
            ->when($request->search, fn ($q, $s) => $q->where(fn ($w) => $w->where('name', 'like', "%$s%")->orWhere('item_code', 'like', "%$s%")))
            ->when($request->category_id, fn ($q, $v) => $q->where('category_id', $v))
            ->when($request->item_type, fn ($q, $v) => $q->where('item_type', $v))
            ->orderBy('name');

        $headings = ['Item Code', 'Name', 'Type', 'Category', 'Unit', 'Opening/Balance', 'Minimum Stock', 'Status'];
        $rows = (function () use ($query) {
            foreach ($query->lazy(500) as $item) {
                yield [
                    $item->item_code, $item->name, $item->item_type, $item->category?->name, $item->unit?->symbol,
                    (float) ($item->balance->quantity_on_hand ?? 0), (float) $item->minimum_stock,
                    $item->is_active ? 'Active' : 'Inactive',
                ];
            }
        })();

        return [$headings, $rows, 'inventory-items-'.now()->format('Ymd')];
    }

    /** @return array{0:array,1:iterable,2:string} */
    private function stockBalances(Request $request): array
    {
        $query = StockBalance::with(['item.category', 'item.unit'])
            ->when($request->category_id, fn ($q, $v) => $q->whereHas('item', fn ($i) => $i->where('category_id', $v)))
            ->orderBy('item_id');

        $headings = ['Item Code', 'Name', 'Category', 'Unit', 'Quantity On Hand', 'Minimum Stock', 'Status'];
        $rows = (function () use ($query) {
            foreach ($query->lazy(500) as $balance) {
                $item = $balance->item;
                $qty = (float) $balance->quantity_on_hand;
                $min = (float) ($item->minimum_stock ?? 0);
                yield [
                    $item?->item_code, $item?->name, $item?->category?->name, $item?->unit?->symbol,
                    $qty, $min, $qty <= 0 ? 'Out of stock' : ($qty <= $min ? 'Low stock' : 'Normal'),
                ];
            }
        })();

        return [$headings, $rows, 'stock-balances-'.now()->format('Ymd')];
    }

    /** @return array{0:array,1:iterable,2:string} */
    private function stockIn(Request $request): array
    {
        return $this->movementExport($request, 'in', 'stock-in');
    }

    /** @return array{0:array,1:iterable,2:string} */
    private function stockOut(Request $request): array
    {
        return $this->movementExport($request, 'out', 'stock-out');
    }

    /** @return array{0:array,1:iterable,2:string} */
    private function stockHistory(Request $request): array
    {
        return $this->movementExport($request, 'all', 'stock-history', ['Company', 'Report Period']);
    }

    /** @return array{0:array,1:iterable,2:string} */
    private function movementExport(Request $request, string $direction, string $slug, array $meta = []): array
    {
        $data = $request->validate([
            'from' => 'nullable|date',
            'to' => 'nullable|date|after_or_equal:from',
            'item_id' => 'nullable|integer',
            'category_id' => 'nullable|integer',
        ]);

        $query = StockMovement::with(['item.category', 'user'])
            ->when($direction === 'in', fn ($q) => $q->where('quantity_in', '>', 0))
            ->when($direction === 'out', fn ($q) => $q->where('quantity_out', '>', 0))
            ->when($data['from'] ?? null, fn ($q, $v) => $q->whereDate('movement_date', '>=', $v))
            ->when($data['to'] ?? null, fn ($q, $v) => $q->whereDate('movement_date', '<=', $v))
            ->when($data['item_id'] ?? null, fn ($q, $v) => $q->where('item_id', $v))
            ->when($data['category_id'] ?? null, fn ($q, $v) => $q->whereHas('item', fn ($i) => $i->where('category_id', $v)))
            ->orderByDesc('movement_date')->orderByDesc('id');

        $headings = ['Date', 'Item Code', 'Item', 'Category', 'Type', 'Reference', 'Quantity In', 'Quantity Out', 'Balance After', 'Recorded By'];
        $rows = (function () use ($query) {
            foreach ($query->lazy(500) as $m) {
                yield [
                    $m->movement_date?->format('Y-m-d'),
                    $m->item?->item_code, $m->item?->name, $m->item?->category?->name,
                    $m->movement_type, $m->reference_number,
                    (float) $m->quantity_in, (float) $m->quantity_out, (float) $m->balance_after,
                    $m->user?->name,
                ];
            }
        })();

        return [$headings, $rows, $slug.'-'.now()->format('Ymd')];
    }

    /** @return array{0:array,1:iterable,2:string} */
    private function lowStock(Request $request): array
    {
        $query = Item::with(['category', 'unit', 'supplier', 'balance'])
            ->where('is_active', true)
            ->when($request->category_id, fn ($q, $v) => $q->where('category_id', $v))
            ->orderBy('name');

        $headings = ['Item Code', 'Name', 'Category', 'Unit', 'Current Stock', 'Reorder Level', 'Shortage', 'Preferred Supplier'];
        $rows = (function () use ($query) {
            foreach ($query->lazy(500) as $item) {
                $qty = (float) ($item->balance->quantity_on_hand ?? 0);
                $min = (float) $item->minimum_stock;
                if ($qty > $min) {
                    continue;
                }
                yield [
                    $item->item_code, $item->name, $item->category?->name, $item->unit?->symbol,
                    $qty, $min, max(0, $min - $qty), $item->supplier?->name,
                ];
            }
        })();

        return [$headings, $rows, 'low-stock-'.now()->format('Ymd')];
    }

    /** @return array{0:array,1:iterable,2:string} */
    private function daily(Request $request): array
    {
        $data = $request->validate(['date' => 'required|date']);
        $query = StockMovement::with(['item:id,name,item_code', 'user:id,name'])
            ->whereDate('movement_date', $data['date'])
            ->when($request->item_id, fn ($q, $v) => $q->where('item_id', $v))
            ->orderBy('item_id')->orderBy('id');

        $headings = ['Date', 'Item Code', 'Item', 'Type', 'Reference', 'Quantity In', 'Quantity Out', 'Balance After', 'Recorded By'];
        $rows = (function () use ($query) {
            foreach ($query->lazy(500) as $m) {
                yield [
                    $m->movement_date?->format('Y-m-d'), $m->item?->item_code, $m->item?->name,
                    $m->movement_type, $m->reference_number,
                    (float) $m->quantity_in, (float) $m->quantity_out, (float) $m->balance_after,
                    $m->user?->name,
                ];
            }
        })();

        return [$headings, $rows, 'daily-inventory-'.$data['date']];
    }

    /** @return array{0:array,1:iterable,2:string} */
    private function monthly(Request $request): array
    {
        $data = $request->validate([
            'month' => 'required|date_format:Y-m',
            'item_id' => 'nullable|exists:items,id',
            'category_id' => 'nullable|exists:categories,id',
        ]);

        $start = $data['month'].'-01';
        $end = date('Y-m-t', strtotime($start));

        $query = Item::with(['balance', 'unit', 'category'])
            ->where('is_active', true)
            ->when($data['item_id'] ?? null, fn ($q, $v) => $q->whereKey($v))
            ->when($data['category_id'] ?? null, fn ($q, $v) => $q->where('category_id', $v))
            ->orderBy('name');

        $headings = ['Item Code', 'Item', 'Category', 'Unit', 'Opening', 'Total In', 'Total Out', 'Closing', 'Minimum Stock', 'Status'];
        $rows = (function () use ($query, $start, $end) {
            foreach ($query->lazy(200) as $item) {
                $opening = (float) StockMovement::where('item_id', $item->id)
                    ->whereDate('movement_date', '<', $start)
                    ->selectRaw('COALESCE(SUM(quantity_in - quantity_out),0) as net')->value('net');
                $totals = StockMovement::where('item_id', $item->id)
                    ->whereBetween('movement_date', [$start, $end])
                    ->selectRaw('COALESCE(SUM(quantity_in),0) as tin, COALESCE(SUM(quantity_out),0) as tout')
                    ->first();
                $closing = $opening + (float) $totals->tin - (float) $totals->tout;
                $min = (float) $item->minimum_stock;
                yield [
                    $item->item_code, $item->name, $item->category?->name, $item->unit?->symbol,
                    $opening, (float) $totals->tin, (float) $totals->tout, $closing, $min,
                    $closing <= 0 ? 'Out of stock' : ($closing <= $min ? 'Low stock' : 'Normal'),
                ];
            }
        })();

        return [$headings, $rows, 'monthly-inventory-'.$data['month']];
    }

    /** @return array{0:array,1:iterable,2:string} */
    private function purchases(Request $request): array
    {
        $query = Purchase::with(['supplier', 'items.item'])
            ->when($request->status, fn ($q, $v) => $q->where('status', $v))
            ->when($request->supplier_id, fn ($q, $v) => $q->where('supplier_id', $v))
            ->when($request->from, fn ($q, $v) => $q->whereDate('purchase_date', '>=', $v))
            ->when($request->to, fn ($q, $v) => $q->whereDate('purchase_date', '<=', $v))
            ->orderByDesc('id');

        $headings = ['PO Number', 'Supplier', 'Purchase Date', 'Status', 'Item Code', 'Item', 'Ordered', 'Received', 'Outstanding', 'Unit', 'Invoice', 'Notes'];
        $rows = (function () use ($query) {
            foreach ($query->lazy(200) as $purchase) {
                if ($purchase->items->isEmpty()) {
                    yield [$purchase->purchase_number, $purchase->supplier?->name, $purchase->purchase_date?->format('Y-m-d'), $purchase->status, '', '', 0, 0, 0, '', $purchase->invoice_number, $purchase->notes];

                    continue;
                }
                foreach ($purchase->items as $line) {
                    $ordered = (float) $line->quantity;
                    $received = (float) $line->received_quantity;
                    yield [
                        $purchase->purchase_number, $purchase->supplier?->name, $purchase->purchase_date?->format('Y-m-d'),
                        $purchase->status, $line->item?->item_code, $line->item?->name,
                        $ordered, $received, max(0, $ordered - $received), $line->item?->unit?->symbol ?? '',
                        $purchase->invoice_number, $purchase->notes,
                    ];
                }
            }
        })();

        return [$headings, $rows, 'purchase-orders-'.now()->format('Ymd')];
    }
}
