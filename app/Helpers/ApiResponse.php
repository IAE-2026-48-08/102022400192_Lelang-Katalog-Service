<?php

namespace App\Helpers;

class ApiResponse
{
    public static function success($data, $message = 'Data retrieved successfully', $code = 200)
    {
        return response()->json([
            'status' => 'success',
            'message' => $message,
            'data' => $data,
            'meta' => [
                'service_name' => 'Katalog-Service',
                'api_version' => 'v1',
            ],
        ], $code);
    }

    public static function error($message = 'Something went wrong', $code = 400, $errors = null)
    {
        return response()->json([
            'status' => 'error',
            'message' => $message,
            'errors' => $errors,
        ], $code);
    }
}