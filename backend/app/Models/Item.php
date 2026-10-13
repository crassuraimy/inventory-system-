<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Item extends Model
{
    protected $fillable = [
        'item_code', 'name', 'item_type', 'category_id', 'unit_id',
        'description', 'minimum_stock', 'target_stock_level', 'preferred_supplier_id',
        'unit_cost', 'track_expiry', 'is_active',
    ];

    protected $casts = [
        'minimum_stock' => 'decimal:3',
        'target_stock_level' => 'decimal:3',
        'unit_cost' => 'decimal:4',
        'track_expiry' => 'boolean',
        'is_active' => 'boolean',
    ];

    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    public function unit()
    {
        return $this->belongsTo(Unit::class);
    }

    public function supplier()
    {
        return $this->belongsTo(Supplier::class, 'preferred_supplier_id');
    }

    public function balance()
    {
        return $this->hasOne(StockBalance::class);
    }

    public function movements()
    {
        return $this->hasMany(StockMovement::class);
    }
}
