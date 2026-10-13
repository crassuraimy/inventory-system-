<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class UserController extends Controller
{
    public function index(Request $request)
    {
        return User::query()
            ->when($request->search, fn ($q, $s) => $q->where('name', 'like', "%$s%")->orWhere('email', 'like', "%$s%"))
            ->when($request->role, fn ($q, $v) => $q->where('role', $v))
            ->orderBy('name')->paginate((int) ($request->per_page ?: 50));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'password' => 'required|string|min:8',
            'role' => 'required|in:'.implode(',', User::ROLES),
            'is_active' => 'boolean',
        ]);
        $data['password'] = Hash::make($data['password']);
        $data['is_active'] = $data['is_active'] ?? true;
        $user = User::create($data);
        AuditLog::create(['user_id' => $request->user()->id, 'action' => 'user_created', 'entity_type' => 'User', 'entity_id' => $user->id, 'new_values' => $user->toArray()]);

        return response()->json($user, 201);
    }

    public function update(Request $request, User $user)
    {
        $data = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'email' => "sometimes|required|email|unique:users,email,{$user->id}",
            'password' => 'nullable|string|min:8',
            'role' => 'sometimes|required|in:'.implode(',', User::ROLES),
            'is_active' => 'boolean',
        ]);
        $old = $user->toArray();
        if (! empty($data['password'])) {
            $data['password'] = Hash::make($data['password']);
        } else {
            unset($data['password']);
        }
        $user->update($data);

        if (array_key_exists('is_active', $data) && ! $data['is_active']) {
            // Revoke any active tokens immediately so a deactivated account
            // cannot keep using an already issued bearer token.
            $user->tokens()->delete();
        }

        AuditLog::create(['user_id' => $request->user()->id, 'action' => 'user_updated', 'entity_type' => 'User', 'entity_id' => $user->id, 'old_values' => $old, 'new_values' => $user->fresh()->toArray()]);

        return $user->fresh();
    }

    public function destroy(User $user)
    {
        if ($user->id === request()->user()->id) {
            return response()->json(['message' => 'You cannot deactivate your own account.'], 422);
        }
        $user->is_active = false;
        $user->save();
        $user->tokens()->delete();
        AuditLog::create(['user_id' => request()->user()->id, 'action' => 'user_deactivated', 'entity_type' => 'User', 'entity_id' => $user->id]);

        return response()->noContent();
    }
}
