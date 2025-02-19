<?php

namespace App\Services;


use App\Models\PersonalAccessToken;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\NewAccessToken;

/**
 * Service class managing token operations for the authentication system.
 *
 * This service handles the creation, refresh, and invalidation of token pairs
 * (access + refresh tokens) for users.
 * It implements the token rotation pattern and manages token lifetimes.
 *
 * @see PersonalAccessToken
 * @see \App\Support\HasAuthTokens
 * @see \App\Support\HasRefreshTokens
 */
class TokenService
{
    /**
     * Access token lifetime in minutes
     * @var int
     */
    private const ACCESS_TOKEN_EXPIRY_IN_MINUTES = 15;

    /**
     * Refresh token lifetime in minutes
     * @var int
     */
    private const REFRESH_TOKEN_EXPIRY_IN_MINUTES = 30 * 24 * 60; // 30 days

    /**
     * Issue a new token pair for a user.
     *
     * Creates a new access token and associated refresh token with appropriate
     * expiration times. The access token will have the specified abilities,
     * while the refresh token will only have the refresh ability.
     *
     * @param User $user The token owner
     * @param array $abilities The abilities for the access token
     * @return array{access: NewAccessToken, refresh: NewAccessToken}
     *
     * @throws \Throwable If the database transaction fails
     */
    static function issueTokenPair(User $user, array $abilities = ['*']): array
    {
        return DB::transaction(function () use ($user, $abilities) {
            // Create access token
            $accessToken = $user->issueAuthToken($abilities, now()->addMinutes(self::ACCESS_TOKEN_EXPIRY_IN_MINUTES));

            // Create refresh token through the auth token
            $refreshToken = $accessToken->accessToken->issueRefreshToken(now()->addMinutes(self::REFRESH_TOKEN_EXPIRY_IN_MINUTES));

            return [
                'access' => $accessToken,
                'refresh' => $refreshToken,
            ];
        });
    }

    /**
     * Refresh a token pair using a valid refresh token.
     *
     * Validates the provided refresh token and, if valid, invalidates the current
     * token pair and issues a new one with the same abilities as the original
     * access token.
     *
     * @param string $refreshToken The refresh token string
     * @return array{access: NewAccessToken, refresh: NewAccessToken}|null
     *         The new token pair or null if refresh failed
     *
     * @throws \Throwable If the database transaction fails
     */
    static function refreshTokenPair(string $refreshToken): ?array
    {
        $token = PersonalAccessToken::findToken($refreshToken);

        if (!$token || !$token->isRefreshToken() || $token->isExpired()) {
            throw new \InvalidArgumentException('Invalid refresh token');
        }

        return DB::transaction(function () use ($token) {
            // Get abilities from the original auth token
            $abilities = $token->authToken->abilities;
            $user = $token->tokenable->tokenable;

            // Invalidate old token pair
            $token->invalidateWithCascade();

            // Issue new token pair with same abilities
            return static::issueTokenPair($user, $abilities);
        });
    }

    /**
     * Invalidate token pair
     *
     * Finds and invalidates the token pair associated with the given token string,
     *
     * @param string $token The token string
     * @return void
     */
    static function revokeTokenPair(string $token): void
    {
        $token = PersonalAccessToken::findToken($token);
        $token->invalidateWithCascade();
    }

    /**
     * Invalidate all other token pairs for a specific user.
     *
     * Finds and invalidates all token pairs owned by the given user
     * except the one specified.
     *
     * @param string $token The token string
     * @return void
     */
    static function revokeOtherTokenPairs(string $token): void
    {
        $token = PersonalAccessToken::findToken($token);
        if ($token->isRefreshToken()) {
            $token = $token->authToken;
        }
        $token->tokenable->tokens()->where('id', '!=', $token->id)->each(
            fn(PersonalAccessToken $token) => $token->invalidateWithCascade()
        );
    }

    /**
     * Invalidate all tokens for a specific user.
     *
     * Finds and invalidates all token pairs owned by the given user,
     * effectively logging them out of all sessions.
     *
     * @param User $user The token owner
     */
    static function revokeAllUserTokenPairs(User $user): void
    {
        $user->tokens()->each(fn (PersonalAccessToken $token) => $token->invalidateWithCascade());
    }

}
