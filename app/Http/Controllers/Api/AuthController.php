<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

/**
 * @OA\Tag(
 *     name="Authentication",
 *     description="User authentication endpoints"
 * )
 */
class AuthController extends Controller
{
    /**
     * @OA\Post(
     *     path="/api/login",
     *     summary="User login",
     *     tags={"Authentication"},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"username","password","company_name"},
     *             @OA\Property(property="username", type="string", example="company"),
     *             @OA\Property(property="password", type="string", format="password", example="12345678"),
     *             @OA\Property(property="company_name", type="string", example="Demo Company")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Login successful",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Login successful"),
     *             @OA\Property(property="token", type="string", example="1|abc123..."),
     *             @OA\Property(property="user", type="object"),
     *             @OA\Property(property="permissions", type="array", @OA\Items(type="string"), example={"dashboard_view", "employees_manage", "reports_view"}),
     *             @OA\Property(
     *                 property="role",
     *                 type="object",
     *                 @OA\Property(property="role_id", type="integer", example=1),
     *                 @OA\Property(property="role_name", type="string", example="Administrator"),
     *                 @OA\Property(property="role_access", type="integer", example=1)
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation error",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Invalid credentials")
     *         )
     *     )
     * )
     */
    public function login(Request $request)
    {
        $request->validate([
            'username' => 'required|string',
            'password' => 'required|string',
            'company_name' => 'required|string',
        ]);

        // Try to find user by username or email
        $user = User::where(function ($query) use ($request) {
            $query->where('username', $request->username)
                ->orWhere('email', $request->username);
        })
            ->where('company_name', $request->company_name)
            ->with('user_details')
            ->first();
        if (!$user || !Hash::check($request->password, $user->password)) {
            throw ValidationException::withMessages([
                'username' => ['The provided credentials are incorrect.'],
            ]);
        }

        // Check if user is active
        if (!$user->is_active) {
            throw ValidationException::withMessages([
                'username' => ['Your account is inactive. Please contact administrator.'],
            ]);
        }

        // Delete old tokens
        $user->tokens()->delete();

        // Create new token with Passport
        $token = $user->createToken('HR-API-Token')->accessToken;

        // Get user permissions and role data
        $permissionData = $user->sendPermissionsWithUserDetails();
        // Remove staffRole relationship completely from the user object
        unset($user->staffRole);

        return response()->json([
            'success' => true,
            'message' => 'Login successful',
            'token' => $token,
            'user' => $user,
            'permissions' => $permissionData['permissions'],
            'role' => [
                'role_id' => $permissionData['role_id'],
                'role_name' => $permissionData['role_name'],
                'role_access' => $permissionData['role_access'],
            ]
        ]);
    }

    // Registration is disabled - Users must be created by admin/HR through employee management

    /**
     * @OA\Post(
     *     path="/api/logout",
     *     summary="User logout",
     *     tags={"Authentication"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="Logout successful",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Logout successful")
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthenticated"
     *     )
     * )
     */
    public function logout(Request $request)
    {
        $request->user()->token()->revoke();

        return response()->json([
            'success' => true,
            'message' => 'Logout successful'
        ]);
    }

    /**
     * @OA\Get(
     *     path="/api/user",
     *     summary="Get authenticated user",
     *     tags={"Authentication"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="User data retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="user", type="object")
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthenticated"
     *     )
     * )
     */
    public function user(Request $request)
    {
        return response()->json([
            'success' => true,
            'user' => $request->user(),
            'permissions' => $request->user()->sendPermissionsWithUserDetails()['permissions'],
        ]);
    }

    /**
     * @OA\Get(
     *     path="/api/user/permissions",
     *     summary="Get authenticated user permissions",
     *     tags={"Authentication"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="Permissions retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="permissions", type="array", @OA\Items(type="string")),
     *             @OA\Property(property="role_id", type="integer"),
     *             @OA\Property(property="role_name", type="string"),
     *             @OA\Property(property="role_access", type="integer")
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthenticated"
     *     )
     * )
     */
    public function permissions(Request $request)
    {
        $user = $request->user();
        $permissionData = $user->sendPermissionsWithUserDetails();

        return response()->json([
            'success' => true,
            'permissions' => $permissionData['permissions'],
            'role_id' => $permissionData['role_id'],
            'role_name' => $permissionData['role_name'],
            'role_access' => $permissionData['role_access'],
        ]);
    }

    /**
     * @OA\Post(
     *     path="/api/refresh",
     *     summary="Refresh token",
     *     tags={"Authentication"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="Token refreshed successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Token refreshed"),
     *             @OA\Property(property="token", type="string", example="2|xyz789...")
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthenticated"
     *     )
     * )
     */
    public function refresh(Request $request)
    {
        $user = $request->user();

        // Revoke current token
        $request->user()->token()->revoke();

        // Create new token with Passport
        $token = $user->createToken('HR-API-Token')->accessToken;

        return response()->json([
            'success' => true,
            'message' => 'Token refreshed',
            'token' => $token
        ]);
    }

    /**
     * @OA\Get(
     *     path="/api/companies",
     *     summary="Get list of companies",
     *     tags={"Authentication"},
     *     @OA\Response(
     *         response=200,
     *         description="Companies retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="companies", type="array", @OA\Items(type="string"))
     *         )
     *     )
     * )
     */
    public function getCompanies()
    {
        $companies = User::whereNotNull('company_name')
            ->distinct()
            ->pluck('company_name')
            ->filter()
            ->values();

        return response()->json([
            'success' => true,
            'companies' => $companies
        ]);
    }
}
