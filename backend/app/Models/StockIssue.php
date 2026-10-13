<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StockIssue extends Model
{
    protected $fillable = ['issue_number', 'issue_date', 'department', 'issued_to', 'status', 'reason', 'created_by'];

    protected $casts = ['issue_date' => 'date:Y-m-d'];

    public function items()
    {
        return $this->hasMany(StockIssueItem::class);
    }
}
