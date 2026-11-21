<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class UserController extends Controller
{
    public function index()
    {
        return User::orderBy('role')->orderBy('name')->get();
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:100',
            'email' => 'required|email|unique:users',
            'phone' => 'nullable|string|max:30',
            'role' => ['required', Rule::in(array_keys(User::ROLES))],
            'password' => 'nullable|string|min:6',
        ]);
        $user = User::create($data + ['password' => $data['password'] ?? 'password']);
        ActivityLog::record('user.created', "Added user {$user->name} ({$user->role_label})", $user);

        return response()->json($user, 201);
    }

    public function update(Request $request, User $user)
    {
        $data = $request->validate([
            'name' => 'sometimes|string|max:100',
            'phone' => 'nullable|string|max:30',
            'role' => ['sometimes', Rule::in(array_keys(User::ROLES))],
            'active' => 'sometimes|boolean',
        ]);
        if ($user->id === $request->user()->id && (isset($data['active']) && ! $data['active'])) {
            return response()->json(['message' => 'You cannot deactivate yourself.'], 422);
        }
        $user->update($data);
        ActivityLog::record('user.updated', "Updated user {$user->name}", $user);

        return $user;
    }

    public function activity(Request $request)
    {
        $q = ActivityLog::with('user:id,name,role')->latest('created_at');
        if ($u = $request->query('user_id')) {
            $q->where('user_id', $u);
        }
        if ($a = $request->query('action')) {
            $q->where('action', 'like', "$a%");
        }

        return $q->paginate(25);
    }
}
