<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class StockIssueItem extends Model {
    protected $fillable = ['stock_issue_id','item_id','quantity'];
    protected $casts = ['quantity'=>'decimal:3'];
    public function item() { return $this->belongsTo(Item::class); }
}
