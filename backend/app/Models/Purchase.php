<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class Purchase extends Model {
    protected $fillable = ['purchase_number','supplier_id','invoice_number','purchase_date','received_date','status','notes','created_by'];
    protected $casts = ['purchase_date'=>'date:Y-m-d','received_date'=>'date:Y-m-d'];
    public function items() { return $this->hasMany(PurchaseItem::class); }
    public function supplier() { return $this->belongsTo(Supplier::class); }
}
