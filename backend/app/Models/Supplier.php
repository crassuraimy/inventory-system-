<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Supplier extends Model
{
    protected $fillable = ['supplier_code', 'name', 'contact_person', 'phone', 'email', 'address', 'is_active'];

    protected $casts = ['is_active' => 'boolean'];
}
