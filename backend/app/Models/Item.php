<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class Item extends Model {
    protected $fillable = [
        'item_code', 'name', 'item_type', 'category_id', 'unit_id',
        'description', 'minimum_stock', 'track_expiry', 'is_active'
    ];
    protected $casts = ['minimum_stock' => 'decimal:3', 'track_expiry' => 'boolean', 'is_active' => 'boolean'];
    public function category() { return $this->belongsTo(Category::class); }
    public function unit() { return $this->belongsTo(Unit::class); }
    public function balance() { return $this->hasOne(StockBalance::class); }
    public function movements() { return $this->hasMany(StockMovement::class); }
}
