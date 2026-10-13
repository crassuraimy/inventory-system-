<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PurchaseReceiptItem extends Model
{
    protected $fillable = ['purchase_receipt_id', 'purchase_item_id', 'item_id', 'quantity'];

    protected $casts = ['quantity' => 'decimal:3'];

    public function receipt()
    {
        return $this->belongsTo(PurchaseReceipt::class);
    }

    public function purchaseItem()
    {
        return $this->belongsTo(PurchaseItem::class);
    }

    public function item()
    {
        return $this->belongsTo(Item::class);
    }
}
