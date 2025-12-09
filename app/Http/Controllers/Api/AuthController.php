<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
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
     *             @OA\Property(
     *                 property="permissionData",
     *                 type="object",
     *                 @OA\Property(property="permissions", type="array", @OA\Items(type="string"), example={"dashboard_view", "employees_manage", "reports_view"}),
     *                 @OA\Property(property="role_id", type="integer", example=1),
     *                 @OA\Property(property="role_name", type="string", example="Administrator"),
     *                 @OA\Property(property="role_access", type="integer", example=1),
     *                 @OA\Property(property="department_name", type="string", example="Information Technology"),
     *                 @OA\Property(property="designation_name", type="string", example="Software Engineer")
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
            ->with(['user_details.department', 'user_details.designation'])
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
        $tokenResult = $user->createToken('HR-API-Token');
        $token = $tokenResult->accessToken;
        $tokenResult->token->expires_at = now()->addMinutes(15);
        $tokenResult->token->save();

        $refreshToken = $this->createRefreshToken($user, $tokenResult->token->id);

        // Get user permissions and role data
        $permissionData = $user->sendPermissionsWithUserDetails();
        // Remove staffRole relationship completely from the user object
        unset($user->staffRole);

        return response()->json([
            'success' => true,
            'message' => 'Login successful',
            'access_token' => $token,
            'refresh_token' => $refreshToken,
            'expires_in_minutes' => $tokenResult->token->expires_at->diffInMinutes(now()), // in minutes
            'user' => $user,
            'permissionData' => $permissionData,

        ]);
    }


/**
 * @OA\Post(
 *     path="/api/refresh",
 *     summary="Refresh the access token",
 *     tags={"Authentication"},
 *     @OA\RequestBody(
 *         required=true,
 *         @OA\JsonContent(
 *             required={"refresh_token"},
 *             @OA\Property(property="refresh_token", type="string", example="refresh_token_here")
 *         )
 *     ),
 *     @OA\Response(
 *         response=200,
 *         description="Refresh token successful",
 *         @OA\JsonContent(
 *             @OA\Property(property="success", type="boolean", example=true),
 *             @OA\Property(property="access_token", type="string", example="new_access_token_here"),
 *             @OA\Property(property="refresh_token", type="string", example="new_refresh_token_here"),
 *             @OA\Property(property="expires_in", type="integer", example=900)
 *         )
 *     )
 * )
 */
    public function refresh(Request $request)
    {
        $request->validate([
            'refresh_token' => 'required|string'
        ]);

        // البحث عن refresh token
        $refreshToken = DB::table('oauth_refresh_tokens')
            ->where('id', $request->refresh_token)
            ->where('revoked', 0)
            ->where('expires_at', '>', now())
            ->first();

        if (!$refreshToken) {
            return response()->json([
                'success' => false,
                'message' => 'Refresh token غير صالح أو منتهي الصلاحية'
            ], 401);
        }

        // إلغاء التوكن القديم
        DB::table('oauth_access_tokens')
            ->where('id', $refreshToken->access_token_id)
            ->update(['revoked' => 1]);

        // إلغاء refresh token الحالي
        DB::table('oauth_refresh_tokens')
            ->where('id', $refreshToken->id)
            ->update(['revoked' => 1]);

        // الحصول على معرف المستخدم من جدول oauth_access_tokens
        $accessToken = DB::table('oauth_access_tokens')
            ->where('id', $refreshToken->access_token_id)
            ->first();
            
        if (!$accessToken) {
            return response()->json([
                'success' => false,
                'message' => 'لم يتم العثور على بيانات المستخدم المرتبطة بهذا التوكن'
            ], 401);
        }

        $userId = $accessToken->user_id;
        $user = User::find($userId);

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'المستخدم غير موجود'
            ], 404);
        }

        // إنشاء توكن جديد
        $tokenResult = $user->createToken('HR-API-Token');
        $token = $tokenResult->accessToken;
        $tokenResult->token->expires_at = now()->addDays(1);
        $tokenResult->token->save();

        // Get user permissions and role data
        $permissionData = $user->sendPermissionsWithUserDetails();
        // إنشاء refresh token جديد
        $newRefreshToken = $this->createRefreshToken($user, $tokenResult->token->id);

        return response()->json([
            'success' => true,
            'token' => $token,
            'refresh_token' => $newRefreshToken,
            'expires_in_minutes' => $tokenResult->token->expires_at->diffInMinutes(now()),
            'user' => $user,
            'permissionData' => $permissionData,
        ]);
    }


    /**
     * إنشاء refresh token
     */
    private function createRefreshToken($user, $accessTokenId = null)
    {
        $refreshToken = Str::random(80);
        
        // إذا لم يتم تمرير accessTokenId ولم يكن هناك توكن للمستخدم
        if (!$accessTokenId && !$user->token()) {
            throw new \Exception('No access token available for the user');
        }
        
        DB::table('oauth_refresh_tokens')->insert([
            'id' => $refreshToken,
            'access_token_id' => $accessTokenId ?: $user->token()->id,
            'revoked' => 0, // استخدم 0 بدلاً من false
            'expires_at' => now()->addDays(30)->format('Y-m-d H:i:s')
            // تمت إزالة created_at و updated_at
        ]);
        
        return $refreshToken;
    }   

    /**
     * @OA\Get(
     *     path="/api/companies",
     *     summary="Get companies",
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
}
