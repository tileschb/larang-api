<?php

namespace App\Services;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Http\Resources\Json\ResourceCollection;
use Illuminate\Support\Collection;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;
use BackedEnum;
use DateTimeInterface;
use JsonSerializable;
use UnitEnum;

/**
 * Service for standardizing API responses and transforming data.
 *
 * This service provides methods to create consistent API responses with proper
 * data transformation. It handles various data types including Laravel-specific
 * types and ensures all response keys are in camelCase format.
 *
 * Features:
 * - Standardized success/error response format
 * - Automatic camelCase key transformation
 * - DateTime to Unix timestamp conversion (microsecond precision)
 * - Proper handling of Laravel Resource Collections and pagination
 * - Support for various PHP and Laravel data types
 *
 * Response Format:
 * {
 *     "success": boolean,
 *     "data": mixed|null,
 *     "meta": {
 *         "pagination": {
 *             "currentPage": int,
 *             "perPage": int,
 *             "total": int
 *         }
 *     },
 *     "error": {
 *         "code": string,
 *         "message": string,
 *         "details": array
 *     }|null
 * }
 */
class ApiResponseService
{
    /**
     * Cache for transformed keys to improve performance
     *
     * @var array<string, string>
     */
    private static array $keyCache = [];

    /**
     * Returns a JSON success response with standardized format.
     *
     * @param mixed $data Main response data
     * @param array $meta Additional metadata
     * @param int $status HTTP status code
     * @return JsonResponse
     */
    public static function successResponse(mixed $data = null, array $meta = [], int $status = 200): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => self::transformToCamelCase($data),
            'meta' => self::transformToCamelCase($meta),
            'error' => null
        ], $status);
    }

    /**
     * Returns a JSON error response with standardized format.
     *
     * @param string $message User-friendly error message
     * @param string $code Error identifier code
     * @param int $status HTTP status code
     * @param array $details Additional error details
     * @return JsonResponse
     */
    public static function errorResponse(string $message, string $code = 'ERROR', int $status = 500, array $details = []): JsonResponse
    {
        return response()->json([
            'success' => false,
            'data' => null,
            'meta' => [],
            'error' => [
                'code' => $code,
                'message' => $message,
                'details' => self::transformToCamelCase($details)
            ]
        ], $status);
    }

    /**
     * Main transformation method that handles different data types.
     *
     * Supported types:
     * - Arrays (associative and indexed)
     * - Eloquent Models
     * - Collections
     * - JsonResource and ResourceCollection
     * - DateTime objects (converts to Unix timestamp with microseconds)
     * - Enums (BackedEnum and UnitEnum)
     * - Objects implementing JsonSerializable
     * - Generic objects and stdClass
     *
     * @param mixed $data The data to transform
     * @return mixed Transformed data with camelCase keys
     */
    private static function transformToCamelCase(mixed $data): mixed
    {
        if ($data === null) {
            return null;
        }

        if (is_array($data)) {
            return self::transformArray($data);
        }

        if (is_object($data)) {
            return self::transformObject($data);
        }

        return $data;
    }

    /**
     * Transform array keys to camelCase and recursively transform values.
     *
     * @param array $array Array to transform
     * @return array Transformed array with camelCase keys
     */
    private static function transformArray(array $array): array
    {
        $result = [];

        foreach ($array as $key => $value) {
            $transformedKey = is_string($key) ? self::transformKey($key) : $key;
            $result[$transformedKey] = self::transformToCamelCase($value);
        }

        return $result;
    }

    /**
     * Transform object properties based on object type.
     *
     * Special handling for:
     * - Laravel Resources and ResourceCollections (with pagination)
     * - DateTime objects (converts to Unix timestamp with microseconds)
     * - Eloquent Models (transforms to array)
     * - Collections (transforms all items)
     * - Enums (extracts value or name)
     * - JsonSerializable (uses custom serialization)
     *
     * @param object $object Object to transform
     * @return mixed Transformed data
     */
    private static function transformObject(object $object): mixed
    {
        // Handle Resource Collections
        if ($object instanceof ResourceCollection) {
            $data = $object->resolve(request());

            // For paginated collections
            if (isset($data['data'])) {
                // Extract pagination metadata
                $meta = [];
                if (isset($data['current_page'], $data['per_page'], $data['total'])) {
                    $meta['pagination'] = [
                        'currentPage' => $data['current_page'],
                        'perPage' => $data['per_page'],
                        'total' => $data['total']
                    ];
                }

                // Return transformed data with pagination in meta
                return [
                    'data' => self::transformToCamelCase($data['data']),
                    'meta' => $meta
                ];
            }

            // For non-paginated collections
            return self::transformToCamelCase($data);
        }

        // Handle Laravel Resources
        if ($object instanceof JsonResource) {
            return self::transformToCamelCase($object->resolve(request()));
        }

        // Handle DateTime objects (including Carbon)
        if ($object instanceof DateTimeInterface) {
            return (int) $object->format('Uu');
        }

        // Handle Eloquent Models
        if ($object instanceof Model) {
            return self::transformArray($object->toArray());
        }

        // Handle Collections
        if ($object instanceof Collection) {
            return $object->map(fn($item) => self::transformToCamelCase($item))->all();
        }

        // Handle Enums (PHP 8.1+)
        if ($object instanceof BackedEnum) {
            return $object->value;
        }
        if ($object instanceof UnitEnum) {
            return $object->name;
        }

        // Handle objects that implement JsonSerializable
        if ($object instanceof JsonSerializable) {
            return self::transformToCamelCase($object->jsonSerialize());
        }

        // Handle stdClass and any other object by converting to array
        return self::transformArray((array) $object);
    }

    /**
     * Transform a single key to camelCase with caching.
     *
     * @param string $key Key to transform
     * @return string Transformed key in camelCase
     */
    private static function transformKey(string $key): string
    {
        if (!isset(self::$keyCache[$key])) {
            self::$keyCache[$key] = Str::camel($key);
        }

        return self::$keyCache[$key];
    }

    /**
     * Clear the key transformation cache.
     *
     * Useful in testing scenarios or when memory usage is a concern
     * in long-running processes.
     *
     * @return void
     */
    public static function clearCache(): void
    {
        self::$keyCache = [];
    }
}
