<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use Illuminate\Http\Request;

class AuditLogController extends Controller
{
    public function index(Request $request)
    {
        return AuditLog::with('user:id,name,email')
            ->when($request->action, fn ($q, $v) => $q->where('action', $v))
            ->when($request->entity_type, fn ($q, $v) => $q->where('entity_type', $v))
            ->when($request->from, fn ($q, $v) => $q->whereDate('created_at', '>=', $v))
            ->when($request->to, fn ($q, $v) => $q->whereDate('created_at', '<=', $v))
            ->orderByDesc('id')->paginate((int) ($request->per_page ?: 50));
    }
}
