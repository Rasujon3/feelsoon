<?php

    namespace App\Traits\Api;

    use Illuminate\Http\JsonResponse;

    trait UniformResponseTrait
    {
        /**
         * @param bool $status
         * @param string $message
         * @param array $response
         * @param int $statusCode
         * @param array $headers
         *
         * @return JsonResponse
         */
        protected function sendResponse(bool $status = TRUE, string $message = '', mixed $response = NULL, int $statusCode = 200, string $responseKey = 'result', array $headers = []): JsonResponse
        {
            $result = [
                'status' => $status,
                'status_code' => $statusCode,
                'message' => $message,
                $responseKey => $response
            ];

            if (!empty($headers)) {
                $result['headers'] = $headers;
            }

            return response()->json($result, $statusCode);
        }
    }
