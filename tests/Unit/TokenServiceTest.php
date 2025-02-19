<?php

use App\Models\PersonalAccessToken;
use App\Models\User;
use App\Services\TokenService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Laravel\Sanctum\NewAccessToken;

uses(TestCase::class, RefreshDatabase::class)->group('unit');

beforeEach(function () {
    $this->user = User::factory()->create();
});

/*
|--------------------------------------------------------------------------
| Issue Token Tests
|--------------------------------------------------------------------------
*/

test('can issue token pair', function () {
    $tokenPair = TokenService::issueTokenPair($this->user);

    // Check structure
    expect($tokenPair)
        ->toBeArray()
        ->toHaveKeys(['access', 'refresh']);

    // Check access token
    $accessToken = $tokenPair['access']->accessToken;
    expect($accessToken)
        ->toBeInstanceOf(PersonalAccessToken::class)
        ->and($accessToken->type)->toBe(PersonalAccessToken::TYPE_AUTH)
        ->and($accessToken->auth_token_id)->toBeNull()
        ->and($accessToken->isAuthToken())->toBeTrue();

    // Check refresh token
    $refreshToken = $tokenPair['refresh']->accessToken;
    expect($refreshToken)
        ->toBeInstanceOf(PersonalAccessToken::class)
        ->and($refreshToken->type)->toBe(PersonalAccessToken::TYPE_REFRESH)
        ->and($refreshToken->abilities)->toBe(['refresh-auth-token'])
        ->and($refreshToken->auth_token_id)->toBe($accessToken->id)
        ->and($refreshToken->isRefreshToken())->toBeTrue();

    // Verify relationships
    expect($accessToken->refreshToken->id)->toBe($refreshToken->id)
        ->and($refreshToken->authToken->id)->toBe($accessToken->id);
});


test('can refresh token pair', function () {
    $originalPair = TokenService::issueTokenPair($this->user, ['read']);
    $originalAccessToken = $originalPair['access']->accessToken;
    $originalRefreshToken = $originalPair['refresh']->accessToken;
    $newPair = TokenService::refreshTokenPair($originalPair['refresh']->plainTextToken);

    expect($newPair)
        ->toBeArray()
        ->toHaveKeys(['access', 'refresh']);

    // Verify new tokens
    expect($newPair['access']->accessToken->abilities)->toBe(['read'])
        ->and($newPair['access']->accessToken->type)->toBe(PersonalAccessToken::TYPE_AUTH)
        ->and($newPair['refresh']->accessToken->type)->toBe(PersonalAccessToken::TYPE_REFRESH);

    // Verify old tokens are invalidated
    $this->assertDatabaseMissing('personal_access_tokens', ['id' => $originalAccessToken->id]);
    $this->assertDatabaseMissing('personal_access_tokens', ['id' => $originalRefreshToken->id]);
});

test('cannot refresh with expired token', function () {
    $tokenPair = TokenService::issueTokenPair($this->user);
    $refreshToken = $tokenPair['refresh']->accessToken;
    $refreshToken->expires_at = now()->subMinute();
    $refreshToken->save();

    expect(fn() => TokenService::refreshTokenPair($tokenPair['refresh']->plainTextToken))
        ->toThrow(InvalidArgumentException::class, 'Invalid refresh token');
});

test('cannot refresh with invalid token', function () {
    expect(fn() => TokenService::refreshTokenPair('invalid-token'))
        ->toThrow(InvalidArgumentException::class, 'Invalid refresh token');
});

test('cannot refresh with access token', function () {
    $tokenPair = TokenService::issueTokenPair($this->user);

    expect(fn() => TokenService::refreshTokenPair($tokenPair['access']->plainTextToken))
        ->toThrow(InvalidArgumentException::class, 'Invalid refresh token');
});

/*
|--------------------------------------------------------------------------
| Revoke Token Tests
|--------------------------------------------------------------------------
*/

test('can revoke token pair', function () {
    $tokenPair = TokenService::issueTokenPair($this->user);
    $accessTokenId = $tokenPair['access']->accessToken->id;
    $refreshTokenId = $tokenPair['refresh']->accessToken->id;

    TokenService::revokeTokenPair($tokenPair['access']->plainTextToken);

    $this->assertDatabaseMissing('personal_access_tokens', ['id' => $accessTokenId]);
    $this->assertDatabaseMissing('personal_access_tokens', ['id' => $refreshTokenId]);
});

test('can revoke other token pairs', function () {
    $pair1 = TokenService::issueTokenPair($this->user);
    $pair2 = TokenService::issueTokenPair($this->user);
    $pair3 = TokenService::issueTokenPair($this->user);

    $pair1AccessId = $pair1['access']->accessToken->id;
    $pair2AccessId = $pair2['access']->accessToken->id;
    $pair3AccessId = $pair3['access']->accessToken->id;

    TokenService::revokeOtherTokenPairs($pair2['access']->plainTextToken);

    $this->assertDatabaseMissing('personal_access_tokens', ['id' => $pair1AccessId]);
    $this->assertDatabaseMissing('personal_access_tokens', ['id' => $pair3AccessId]);
    $this->assertDatabaseHas('personal_access_tokens', ['id' => $pair2AccessId]);
});

test('can revoke all user token pairs', function () {
    TokenService::issueTokenPair($this->user);
    TokenService::issueTokenPair($this->user);
    TokenService::issueTokenPair($this->user);

    TokenService::revokeAllUserTokenPairs($this->user);

    expect($this->user->tokens()->count())->toBe(0);
});
