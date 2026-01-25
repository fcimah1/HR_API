<?php

namespace App\Traits;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;

/**
 * API Response Trait for consistent response formatting
 * 
 * Provides standardized methods for API responses with Arabic error messages
 */
trait ApiResponseTrait
{
    /**
     * Return a success response
     *
     * @param mixed $data
     * @param string $message
     * @param int $statusCode
     * @return JsonResponse
     */
    protected function successResponse($data = null, string $message = 'تم بنجاح', int $statusCode = Response::HTTP_OK): JsonResponse
    {
        $response = [
            'success' => true,
            'message' => $message,
        ];

        if ($data !== null) {
            $response['data'] = $data;
        }

        return response()->json($response, $statusCode);
    }

    /**
     * Return an error response
     *
     * @param string $message
     * @param int $statusCode
     * @param mixed $errors
     * @return JsonResponse
     */
    protected function errorResponse(string $message = 'حدث خطأ', int $statusCode = Response::HTTP_BAD_REQUEST, $errors = null): JsonResponse
    {
        $response = [
            'success' => false,
            'message' => $message,
        ];

        if ($errors !== null) {
            $response['errors'] = $errors;
        }

        return response()->json($response, $statusCode);
    }

    /**
     * Return a validation error response
     *
     * @param ValidationException $exception
     * @return JsonResponse
     */
    protected function validationErrorResponse(ValidationException $exception): JsonResponse
    {
        return $this->errorResponse(
            'بيانات غير صحيحة',
            Response::HTTP_UNPROCESSABLE_ENTITY,
            $exception->errors()
        );
    }

    /**
     * Return an unauthorized response
     *
     * @param string $message
     * @return JsonResponse
     */
    protected function unauthorizedResponse(string $message = 'غير مصرح لك بالوصول'): JsonResponse
    {
        return $this->errorResponse($message, Response::HTTP_UNAUTHORIZED);
    }

    /**
     * Return a forbidden response
     *
     * @param string $message
     * @return JsonResponse
     */
    protected function forbiddenResponse(string $message = 'ليس لديك صلاحية للقيام بهذا الإجراء'): JsonResponse
    {
        return $this->errorResponse($message, Response::HTTP_FORBIDDEN);
    }

    /**
     * Return a not found response
     *
     * @param string $message
     * @return JsonResponse
     */
    protected function notFoundResponse(string $message = 'العنصر المطلوب غير موجود'): JsonResponse
    {
        return $this->errorResponse($message, Response::HTTP_NOT_FOUND);
    }

    /**
     * Return a server error response
     *
     * @param string $message
     * @param \Exception|null $exception
     * @param string|null $logContext
     * @return JsonResponse
     */
    protected function serverErrorResponse(string $message = 'حدث خطأ في الخادم', ?\Exception $exception = null, ?string $logContext = null): JsonResponse
    {
        // Log the error for debugging
        if ($exception) {
            Log::error($logContext ?? 'Server Error', [
                'message' => $exception->getMessage(),
                'file' => $exception->getFile(),
                'line' => $exception->getLine(),
                'trace' => $exception->getTraceAsString(),
            ]);
        }

        return $this->errorResponse($message, Response::HTTP_INTERNAL_SERVER_ERROR);
    }

    /**
     * Return a paginated response
     *
     * @param mixed $paginatedData
     * @param string $message
     * @param mixed $resourceClass
     * @return JsonResponse
     */
    protected function paginatedResponse($paginatedData, string $message = 'تم جلب البيانات بنجاح', $resourceClass = null): JsonResponse
    {
        $data = [
            'data' => $resourceClass ? $resourceClass::collection($paginatedData->items()) : $paginatedData->items(),
            'pagination' => [
                'current_page' => $paginatedData->currentPage(),
                'last_page' => $paginatedData->lastPage(),
                'per_page' => $paginatedData->perPage(),
                'total' => $paginatedData->total(),
                'from' => $paginatedData->firstItem(),
                'to' => $paginatedData->lastItem(),
            ]
        ];

        return $this->successResponse($data, $message);
    }

    /**
     * Handle common exceptions and return appropriate responses
     *
     * @param \Exception $exception
     * @param string|null $context
     * @return JsonResponse
     */
    protected function handleException(\Exception $exception, ?string $context = null): JsonResponse
    {
        // Handle validation exceptions
        if ($exception instanceof ValidationException) {
            return $this->validationErrorResponse($exception);
        }

        // Handle model not found exceptions
        if ($exception instanceof \Illuminate\Database\Eloquent\ModelNotFoundException) {
            return $this->notFoundResponse('العنصر المطلوب غير موجود');
        }

        // Handle authorization exceptions
        if ($exception instanceof \Illuminate\Auth\Access\AuthorizationException) {
            return $this->forbiddenResponse('ليس لديك صلاحية للقيام بهذا الإجراء');
        }

        // Handle authentication exceptions
        if ($exception instanceof \Illuminate\Auth\AuthenticationException) {
            return $this->unauthorizedResponse('يجب تسجيل الدخول أولاً');
        }

        // Handle database exceptions
        if ($exception instanceof \Illuminate\Database\QueryException) {
            return $this->serverErrorResponse('حدث خطأ في قاعدة البيانات', $exception, $context);
        }

        // Handle general exceptions
        return $this->serverErrorResponse('حدث خطأ غير متوقع', $exception, $context);
    }

    /**
     * Get Arabic error messages for common HTTP status codes
     *
     * @param int $statusCode
     * @return string
     */
    protected function getArabicErrorMessage(int $statusCode): string
    {
        return match ($statusCode) {
            400 => 'طلب غير صحيح',
            401 => 'غير مصرح لك بالوصول',
            403 => 'ليس لديك صلاحية للقيام بهذا الإجراء',
            404 => 'العنصر المطلوب غير موجود',
            405 => 'الطريقة غير مسموحة',
            409 => 'تعارض في البيانات',
            422 => 'بيانات غير صحيحة',
            429 => 'تم تجاوز الحد المسموح من الطلبات',
            500 => 'حدث خطأ في الخادم',
            502 => 'خطأ في البوابة',
            503 => 'الخدمة غير متاحة مؤقتاً',
            default => 'حدث خطأ غير متوقع',
        };
    }

    /**
     * Validate required parameters and return error if missing
     *
     * @param array $required
     * @param array $data
     * @return JsonResponse|null
     */
    protected function validateRequiredParameters(array $required, array $data): ?JsonResponse
    {
        $missing = [];
        
        foreach ($required as $field) {
            if (!isset($data[$field]) || empty($data[$field])) {
                $missing[] = $field;
            }
        }

        if (!empty($missing)) {
            return $this->errorResponse(
                'حقول مطلوبة مفقودة: ' . implode(', ', $missing),
                Response::HTTP_BAD_REQUEST,
                ['missing_fields' => $missing]
            );
        }

        return null;
    }
}