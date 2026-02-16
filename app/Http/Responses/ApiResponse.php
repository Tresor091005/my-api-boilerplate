<?php

declare(strict_types=1);

namespace App\Http\Responses;

use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

final class ApiResponse
{
    /**
     * Base responder.
     */
    private static function respond(
        int $status,
        string $message,
        mixed $data = null,
        mixed $errors = null
    ): JsonResponse {
        $payload = array_filter([
            'status'  => $status,
            'message' => $message,
            'data'    => $data,
            'errors'  => $errors,
        ], fn ($value): bool => $value !== null);

        return response()->json($payload, $status);
    }

    /* -----------------------------------------------------------------
     | Success responses
     | -----------------------------------------------------------------
     */

    public static function success(
        mixed $data = null,
        string $message = 'OK'
    ): JsonResponse {
        return self::respond(Response::HTTP_OK, $message, $data);
    }

    public static function created(
        mixed $data = null,
        string $message = 'Resource created successfully'
    ): JsonResponse {
        return self::respond(Response::HTTP_CREATED, $message, $data);
    }

    public static function noContent(): JsonResponse
    {
        return response()->json(null, 204);
    }

    /* -----------------------------------------------------------------
     | Client errors
     | -----------------------------------------------------------------
     */

    public static function badRequest(
        string $message = 'Bad request',
        mixed $errors = null
    ): JsonResponse {
        return self::respond(Response::HTTP_BAD_REQUEST, $message, null, $errors);
    }

    public static function unauthenticated(
        string $message = 'Unauthenticated'
    ): JsonResponse {
        return self::respond(Response::HTTP_UNAUTHORIZED, $message);
    }

    public static function unauthorized(
        string $message = 'Unauthorized'
    ): JsonResponse {
        return self::respond(Response::HTTP_FORBIDDEN, $message);
    }

    public static function notFound(
        string $message = 'Resource not found',
        mixed $errors = null
    ): JsonResponse {
        return self::respond(Response::HTTP_NOT_FOUND, $message, null, $errors);
    }

    public static function conflict(
        string $message = 'Conflict',
        mixed $errors = null
    ): JsonResponse {
        return self::respond(Response::HTTP_CONFLICT, $message, null, $errors);
    }

    public static function unprocessable(
        string $message = 'Unprocessable entity',
        mixed $errors = null
    ): JsonResponse {
        return self::respond(Response::HTTP_UNPROCESSABLE_ENTITY, $message, null, $errors);
    }

    public static function tooManyRequests(
        string $message = 'Too many requests',
        mixed $errors = null
    ): JsonResponse {
        return self::respond(Response::HTTP_TOO_MANY_REQUESTS, $message, null, $errors);
    }

    /* -----------------------------------------------------------------
     | Server errors
     | -----------------------------------------------------------------
     */

    public static function internalError(
        string $message = 'Internal server error',
        mixed $errors = null
    ): JsonResponse {
        return self::respond(Response::HTTP_INTERNAL_SERVER_ERROR, $message, null, $errors);
    }

    /* -----------------------------------------------------------------
     | Custom error response
     | -----------------------------------------------------------------
     */
    public static function custom(
        int $status,
        string $message,
        mixed $data = null,
        mixed $errors = null
    ): JsonResponse {
        return self::respond($status, $message, $data, $errors);
    }
}
