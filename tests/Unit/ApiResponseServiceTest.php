<?php

use App\Services\ApiResponseService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Support\Facades\Request;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
*/

uses(TestCase::class, WithFaker::class)->group('unit');

beforeEach(function () {
$this->request = Request::create('/', 'GET');
$this->app->instance('request', $this->request);
});


// Basic success response test
test('success response has correct structure', function () {
    // Given
    $testData = ['test_key' => 'value'];

    // When
    $response = ApiResponseService::successResponse($testData);
    $responseData = $response->getData(true);

    // Then
    expect($responseData)
        ->toBeArray()
        ->toHaveKeys(['success', 'data', 'meta', 'error'])
        ->and($responseData['success'])->toBeTrue()
        ->and($responseData['data'])->toBe(['testKey' => 'value'])
        ->and($responseData['error'])->toBeNull();
});

// Basic error response test
test('error response has correct structure', function () {
    // Given
    $message = 'Something went wrong';
    $code = 'CUSTOM_ERROR';
    $status = 400;
    $details = ['error_detail' => 'Additional info'];

    // When
    $response = ApiResponseService::errorResponse($message, $code, $status, $details);
    $responseData = $response->getData(true);

    // Then
    expect($responseData)
        ->toBeArray()
        ->toHaveKeys(['success', 'data', 'meta', 'error'])
        ->and($responseData['success'])->toBeFalse()
        ->and($responseData['data'])->toBeNull()
        ->and($responseData['error'])->toMatchArray([
            'code' => $code,
            'message' => $message,
            'details' => ['errorDetail' => 'Additional info']
        ]);
});

/*
|--------------------------------------------------------------------------
| Data Transformation Tests
|--------------------------------------------------------------------------
*/

test('transforms nested arrays correctly', function () {
    // Given
    $data = [
        'user_info' => [
            'first_name' => 'John',
            'last_name' => 'Doe',
            'contact_details' => [
                'phone_number' => '1234567890'
            ]
        ]
    ];

    // When
    $response = ApiResponseService::successResponse($data);
    $responseData = $response->getData(true);

    // Then
    expect($responseData['data'])->toBe([
        'userInfo' => [
            'firstName' => 'John',
            'lastName' => 'Doe',
            'contactDetails' => [
                'phoneNumber' => '1234567890'
            ]
        ]
    ]);
});

test('transforms eloquent model correctly', function () {
    // Given
    $model = new class extends Model {
        protected $fillable = ['first_name', 'last_name'];

        public function toArray()
        {
            return [
                'first_name' => 'John',
                'last_name' => 'Doe'
            ];
        }
    };

    // When
    $response = ApiResponseService::successResponse($model);
    $responseData = $response->getData(true);

    // Then
    expect($responseData['data'])->toBe([
        'firstName' => 'John',
        'lastName' => 'Doe'
    ]);
});

test('transforms collection correctly', function () {
    // Given
    $collection = new Collection([
        ['user_name' => 'john_doe'],
        ['user_name' => 'jane_doe']
    ]);

    // When
    $response = ApiResponseService::successResponse($collection);
    $responseData = $response->getData(true);

    // Then
    expect($responseData['data'])->toBe([
        ['userName' => 'john_doe'],
        ['userName' => 'jane_doe']
    ]);
});

test('transforms datetime to unix timestamp with microseconds', function () {
    // Given
    $date = Carbon::create(2024, 1, 1, 12, 0, 0);

    // When
    $response = ApiResponseService::successResponse($date);
    $responseData = $response->getData(true);

    // Then
    expect($responseData['data'])
        ->toBeInt()
        ->toBe((int)($date->format('U.u') * 1000000));
});
