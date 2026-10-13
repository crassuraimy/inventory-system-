<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class StockMovement extends Model {
    protected $fillable = [
        'item_id', 'movement_date', 'movement_type', 'quantity_in', 'quantity_out',
        'reference_type', 'reference_id', 'reference_number', 'created_by'
    ];
    protected $casts = [
        'movement_date' => 'date:Y-m-d',
        'quantity_in' => 'decimal:3',
        'quantity_out' => 'decimal:3'
    ];
    public function item() { return $this->belongsTo(Item::class); }
}
