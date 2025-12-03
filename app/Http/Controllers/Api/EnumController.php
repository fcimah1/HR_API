<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Enums\OvertimeReasonEnum;
use App\Enums\CompensationTypeEnum;
use App\Enums\TravelModeEnum;

/**
 * @OA\Tag(
 *     name="Enums",
 *     description="System enumerations for API requests"
 * )
 */
class EnumController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/enums/overtime-reasons",
     *     summary="Get available overtime reasons",
     *     tags={"Enums"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="Overtime reasons retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", type="array", @OA\Items(
     *                 @OA\Property(property="value", type="integer", example=1),
     *                 @OA\Property(property="name", type="string", example="STANDBY_PAY"),
     *                 @OA\Property(property="label", type="string", example="Standby Pay")
     *             ))
     *         )
     *     )
     * )
     */
    public function overtimeReasons()
    {
        return response()->json([
            'success' => true,
            'data' => OvertimeReasonEnum::toArray()
        ]);
    }

    /**
     * @OA\Get(
     *     path="/api/enums/compensation-types",
     *     summary="Get available compensation types",
     *     tags={"Enums"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="Compensation types retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", type="array", @OA\Items(
     *                 @OA\Property(property="value", type="integer", example=1),
     *                 @OA\Property(property="name", type="string", example="BANKED"),
     *                 @OA\Property(property="label", type="string", example="Banked")
     *             ))
     *         )
     *     )
     * )
     */
    public function compensationTypes()
    {
        return response()->json([
            'success' => true,
            'data' => CompensationTypeEnum::toArray()
        ]);
    }

    /**
     * @OA\Get(
     *     path="/api/enums/travel-modes",
     *     summary="Get available travel modes",
     *     tags={"Enums"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="Travel modes retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", type="array", @OA\Items(
     *                 @OA\Property(property="value", type="integer", example=1),
     *                 @OA\Property(property="name", type="string", example="BUS")
     *             ))
     *         )
     *     )
     * )
     */
    public function travelModes()
    {
        return response()->json([
            'success' => true,
            'data' => array_map(
                fn($case) => [
                    'value' => $case->value,
                    'name' => $case->name,
                ],
                TravelModeEnum::cases()
            )
        ]);
    }

    /**
     * @OA\Get(
     *     path="/api/enums",
     *     summary="Get all available enumerations",
     *     tags={"Enums"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="All enumerations retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="overtime_reasons", type="array", @OA\Items(type="object")),
     *                 @OA\Property(property="compensation_types", type="array", @OA\Items(type="object")),
     *                 @OA\Property(property="travel_modes", type="array", @OA\Items(type="object"))
     *             )
     *         )
     *     )
     * )
     */
    public function index()
    {
        return response()->json([
            'success' => true,
            'data' => [
                'overtime_reasons' => OvertimeReasonEnum::toArray(),
                'compensation_types' => CompensationTypeEnum::toArray(),
                'travel_modes' => array_map(
                    fn($case) => [
                        'value' => $case->value,
                        'name' => $case->name,
                    ],
                    TravelModeEnum::cases()
                )
            ]
        ]);
    }
}

