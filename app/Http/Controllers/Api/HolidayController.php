<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\HolidayService;
use App\Services\SimplePermissionService;
use App\DTOs\Holiday\CreateHolidayDTO;
use App\DTOs\Holiday\UpdateHolidayDTO;
use App\Http\Requests\Holiday\CreateHolidayRequest;
use App\Http\Requests\Holiday\UpdateHolidayRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

/**
 * @OA\Tag(
 *     name="Holidays",
 *     description="Public holidays management"
 * )
 */
class HolidayController extends Controller
{
    public function __construct(
        protected HolidayService $holidayService,
        protected SimplePermissionService $permissionService,
    ) {}

    /**
     * @OA\Get(
     *     path="/api/holidays",
     *     summary="Get holidays list",
     *     tags={"Holidays"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="per_page", in="query", @OA\Schema(type="integer")),
     *     @OA\Parameter(name="is_publish", in="query", @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Holidays retrieved")
     * )
     */
    public function index(Request $request)
    {
        try {
            $user = Auth::user();
            $companyId = $this->permissionService->getEffectiveCompanyId($user);

            $filters = [
                'per_page' => (int)$request->query('per_page', 20),
                'is_publish' => $request->query('is_publish'),
            ];

            $holidays = $this->holidayService->getHolidays($companyId, $filters);

            return response()->json([
                'success' => true,
                'data' => $holidays['data'],
                'pagination' => $holidays['pagination'],
            ]);
        } catch (\Exception $e) {
            Log::error('HolidayController::index failed', ['error' => $e->getMessage()]);
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * @OA\Post(
     *     path="/api/holidays",
     *     summary="Create holiday",
     *     tags={"Holidays"},
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(required=true, @OA\JsonContent(
     *         required={"event_name","start_date","end_date"},
     *         @OA\Property(property="event_name", type="string"),
     *         @OA\Property(property="start_date", type="string", format="date"),
     *         @OA\Property(property="end_date", type="string", format="date"),
     *         @OA\Property(property="description", type="string"),
     *         @OA\Property(property="is_publish", type="integer")
     *     )),
     *     @OA\Response(response=201, description="Holiday created")
     * )
     */
    public function store(CreateHolidayRequest $request)
    {
        try {
            $user = Auth::user();
            $companyId = $this->permissionService->getEffectiveCompanyId($user);

            $dto = CreateHolidayDTO::fromRequest($request, $companyId);
            $holiday = $this->holidayService->createHoliday($dto);

            return response()->json([
                'success' => true,
                'data' => $holiday,
                'message' => 'تم إضافة العطلة بنجاح'
            ], 201);
        } catch (\Exception $e) {
            Log::error('HolidayController::store failed', ['error' => $e->getMessage()]);
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * @OA\Get(
     *     path="/api/holidays/{id}",
     *     summary="Get holiday by ID",
     *     tags={"Holidays"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Holiday retrieved")
     * )
     */
    public function show(int $id)
    {
        try {
            $user = Auth::user();
            $companyId = $this->permissionService->getEffectiveCompanyId($user);

            $holiday = $this->holidayService->getHolidayById($id, $companyId);

            if (!$holiday) {
                return response()->json(['success' => false, 'message' => 'العطلة غير موجودة'], 404);
            }

            return response()->json(['success' => true, 'data' => $holiday]);
        } catch (\Exception $e) {
            Log::error('HolidayController::show failed', ['error' => $e->getMessage()]);
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * @OA\Put(
     *     path="/api/holidays/{id}",
     *     summary="Update holiday",
     *     tags={"Holidays"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\RequestBody(required=true, @OA\JsonContent(
     *         @OA\Property(property="event_name", type="string"),
     *         @OA\Property(property="start_date", type="string"),
     *         @OA\Property(property="end_date", type="string")
     *     )),
     *     @OA\Response(response=200, description="Holiday updated")
     * )
     */
    public function update(int $id, UpdateHolidayRequest $request)
    {
        try {
            $user = Auth::user();
            $companyId = $this->permissionService->getEffectiveCompanyId($user);

            $dto = UpdateHolidayDTO::fromRequest($request);
            $holiday = $this->holidayService->updateHoliday($id, $dto, $companyId);

            return response()->json([
                'success' => true,
                'data' => $holiday,
                'message' => 'تم تحديث العطلة بنجاح'
            ]);
        } catch (\Exception $e) {
            Log::error('HolidayController::update failed', ['error' => $e->getMessage()]);
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * @OA\Delete(
     *     path="/api/holidays/{id}",
     *     summary="Delete holiday",
     *     tags={"Holidays"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Holiday deleted")
     * )
     */
    public function destroy(int $id)
    {
        try {
            $user = Auth::user();
            $companyId = $this->permissionService->getEffectiveCompanyId($user);

            $result = $this->holidayService->deleteHoliday($id, $companyId);

            if (!$result) {
                return response()->json(['success' => false, 'message' => 'العطلة غير موجودة'], 404);
            }

            return response()->json(['success' => true, 'message' => 'تم حذف العطلة بنجاح']);
        } catch (\Exception $e) {
            Log::error('HolidayController::destroy failed', ['error' => $e->getMessage()]);
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * @OA\Get(
     *     path="/api/holidays/check/{date}",
     *     summary="Check if date is holiday",
     *     tags={"Holidays"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="date", in="path", required=true, @OA\Schema(type="string", format="date")),
     *     @OA\Response(response=200, description="Holiday check result")
     * )
     */
    public function checkHoliday(string $date)
    {
        try {
            $user = Auth::user();
            $companyId = $this->permissionService->getEffectiveCompanyId($user);

            $holiday = $this->holidayService->getHolidayForDate($date, $companyId);

            return response()->json([
                'success' => true,
                'is_holiday' => $holiday !== null,
                'holiday' => $holiday,
            ]);
        } catch (\Exception $e) {
            Log::error('HolidayController::checkHoliday failed', ['error' => $e->getMessage()]);
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }
}
