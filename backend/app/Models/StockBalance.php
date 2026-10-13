<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StockBalance extends Model
{
    protected $fillable = ['item_id', 'quantity_on_hand'];

    protected $casts = ['quantity_on_hand' => 'decimal:3'];

    public function item()
    {
        return $this->belongsTo(Item::class);
    }
}
