<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    /**
     * ===============================
     * CUSTOMER REGISTER (web guard)
     * ===============================
     */
    public function register(Request $request)
    {
        $validated = $request->validate([
            'name'     => 'required|string|max:255',
            'email'    => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:8|confirmed',
            'phone'    => 'nullable|string|max:20',
            'address'  => 'nullable|string',
        ]);

        $user = User::create([
            'name'     => $validated['name'],
            'email'    => $validated['email'],
            'password' => Hash::make($validated['password']),
            'phone'    => $validated['phone'] ?? null,
            'address'  => $validated['address'] ?? null,
        ]);

        $user->assignRole('customer');

        Auth::guard('web')->login($user);

        $request->session()->regenerate();

        return response()->json([
            'success' => true,
            'message' => 'Registration successful',
            'user'    => $this->userResponse($user),
        ]);
    }

    /**
     * ===============================
     * CUSTOMER LOGIN (web guard)
     * ===============================
     */
    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email'    => 'required|email',
            'password' => 'required',
            'remember' => 'boolean',
        ]);

        $remember = $credentials['remember'] ?? false;
        unset($credentials['remember']);

        if (!Auth::guard('web')->attempt($credentials, $remember)) {
            throw ValidationException::withMessages([
                'email' => ['Invalid credentials'],
            ]);
        }

        $user = Auth::guard('web')->user();

        if (!$user->is_active) {
            Auth::guard('web')->logout();

            return response()->json([
                'success' => false,
                'message' => 'Account deactivated',
            ], 403);
        }

        $request->session()->regenerate();

        return response()->json([
            'success' => true,
            'message' => 'Login successful',
            'user'    => $this->userResponse($user),
        ]);
    }

    /**
     * ===============================
     * ADMIN LOGIN (admin guard)
     * ===============================
     */
    public function adminLogin(Request $request)
    {
        $credentials = $request->validate([
            'email'    => 'required|email',
            'password' => 'required',
        ]);

        if (!Auth::guard('admin')->attempt($credentials)) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid credentials',
            ], 401);
        }

        $user = Auth::guard('admin')->user();

        // Ensure user has admin role
        if (!$user->hasRole('admin')) {
            Auth::guard('admin')->logout();

            return response()->json([
                'success' => false,
                'message' => 'Unauthorized',
            ], 403);
        }

        $request->session()->regenerate();

        return response()->json([
            'success' => true,
            'message' => 'Admin login successful',
            'user'    => $this->userResponse($user),
        ]);
    }

    /**
     * ===============================
     * CUSTOMER LOGOUT
     * ===============================
     */
    public function logout(Request $request)
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return response()->json([
            'success' => true,
            'message' => 'Customer logout successful',
        ]);
    }

    /**
     * ===============================
     * ADMIN LOGOUT
     * ===============================
     */
    public function adminLogout(Request $request)
    {
        Auth::guard('admin')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return response()->json([
            'success' => true,
            'message' => 'Admin logout successful',
        ]);
    }

    /**
     * ===============================
     * CUSTOMER ME
     * ===============================
     */
    public function me()
    {
        $user = Auth::guard('web')->user();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated',
            ], 401);
        }

        return response()->json([
            'success' => true,
            'user'    => $this->userResponse($user),
        ]);
    }

    /**
     * ===============================
     * ADMIN ME
     * ===============================
     */
    public function adminMe()
    {
        $user = Auth::guard('admin')->user();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated',
            ], 401);
        }

        return response()->json([
            'success' => true,
            'user'    => $this->userResponse($user),
        ]);
    }

    /**
     * Standard user response
     */
    private function userResponse(User $user)
    {
        return [
            'id'          => $user->id,
            'name'        => $user->name,
            'email'       => $user->email,
            'phone'       => $user->phone,
            'address'     => $user->address,
            'roles'       => $user->roles->pluck('slug'),
            'permissions' => $this->getUserPermissions($user),
        ];
    }

    private function getUserPermissions(User $user)
    {
        return $user->roles
            ->flatMap(fn ($role) => $role->permissions->pluck('slug'))
            ->unique()
            ->values()
            ->toArray();
    }
}
