<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PurchaseReceipt extends Model
{
    protected $fillable = ['purchase_id', 'receipt_number', 'request_key', 'receipt_date', 'status', 'notes', 'created_by', 'reversed_at', 'reversed_by', 'reversal_reason'];

    protected $casts = ['receipt_date' => 'date:Y-m-d', 'reversed_at' => 'datetime'];

    public function purchase()
    {
        return $this->belongsTo(Purchase::class);
    }

    public function items()
    {
        return $this->hasMany(PurchaseReceiptItem::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function reverser()
    {
        return $this->belongsTo(User::class, 'reversed_by');
    }
}
