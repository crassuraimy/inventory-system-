<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StockMovement extends Model
{
    protected $fillable = [
        'item_id', 'movement_date', 'movement_type', 'quantity_in', 'quantity_out',
        'balance_after', 'reference_type', 'reference_id', 'reference_number',
        'notes', 'request_key', 'created_by',
    ];

    protected $casts = [
        'movement_date' => 'date:Y-m-d',
        'quantity_in' => 'decimal:3',
        'quantity_out' => 'decimal:3',
        'balance_after' => 'decimal:3',
    ];

    public function item()
    {
        return $this->belongsTo(Item::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
