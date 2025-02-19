<?php

namespace App\Http\Controllers;

use App\Services\ApiResponseService;

abstract class Controller
{
    /**
     * Success response
     * @see ApiResponseService::successResponse()
     */
    protected function success(mixed $data = null, array $meta = [], int $status = 200): \Illuminate\Http\JsonResponse
    {
        return ApiResponseService::successResponse($data, $meta, $status);
    }

    /**
     * Error response
     * @see ApiResponseService::errorResponse()
     */
    protected function error(string $message, string $code = 'ERROR', int $status = 500, array $details = []): \Illuminate\Http\JsonResponse
    {
        return ApiResponseService::errorResponse($message, $code, $status, $details);
    }
}
