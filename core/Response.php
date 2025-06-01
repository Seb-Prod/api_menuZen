<?php

class Response
{
    public static function json($data, int $statusCode = 200)
    {
        http_response_code($statusCode);
        header("Access-Control-Allow-Origin: *");
        header("Content-Type: application/json; charset=UTF-8");
        header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
        header("Access-Control-Max-Age: 3600");
        header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

        echo json_encode($data);
        exit;
    }

    public static function error($message, int $statusCode = 400)
    {
        self::json(
            [
                'status' => 'error',
                'message' => $message
            ],
            $statusCode
        );
    }


    public static function success($message, array $data = [], int $statusCode = 200)
    {
        self::json([
            'status' => 'success',
            'message' => $message,
            'data' => $data
        ], $statusCode);
    }
}
