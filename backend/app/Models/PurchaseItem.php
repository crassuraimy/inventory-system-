<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class PurchaseItem extends Model {
    protected $fillable = ['purchase_id','item_id','quantity','batch_number','expiry_date'];
    protected $casts = ['quantity'=>'decimal:3','expiry_date'=>'date:Y-m-d'];
    public function item() { return $this->belongsTo(Item::class); }
}
