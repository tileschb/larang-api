<?php

namespace App\Support;

use App\Models\PersonalAccessToken;
use DateTimeInterface;
use Laravel\Sanctum\HasApiTokens;
use Laravel\Sanctum\NewAccessToken;

/**
 * Trait providing refresh token functionality for access tokens.
 *
 * This trait extends Laravel Sanctum's HasApiTokens to add support for
 * refresh tokens. It allows PersonalAccessToken instances to issue associated
 * refresh tokens with configurable expiration times.
 *
 * @see PersonalAccessToken
 * @see \App\Services\TokenService
 */
trait HasRefreshTokens
{
    use HasApiTokens;

    /**
     * The ability name for refresh tokens.
     *
     * Refresh tokens are limited to only this ability, which allows them
     * to be used only for refreshing token pairs.
     *
     * @var string
     */
    const REFRESH_TOKEN_ABILITY = 'refresh-auth-token';

    /**
     * Issue a new refresh token associated with this token.
     *
     * Creates a refresh token linked to the current auth token, with a
     * configurable expiration time. The refresh token will have only the
     * refresh-auth-token ability.
     *
     * @param DateTimeInterface|null $expiresAt When the refresh token should expire
     * @return NewAccessToken The newly created refresh token
     *
     * @see \App\Services\TokenService::issueTokenPair() For creating complete token pairs
     */
    public function issueRefreshToken(?DateTimeInterface $expiresAt = null): NewAccessToken
    {
        $plainTextToken = $this->generateTokenString();

        $token = $this->tokens()->create([
            'token' => hash('sha256', $plainTextToken),
            'type' => PersonalAccessToken::TYPE_REFRESH,
            'abilities' => [self::REFRESH_TOKEN_ABILITY],
            'expires_at' => $expiresAt,
            'auth_token_id' => $this->getKey(),
        ]);

        return new NewAccessToken($token, $token->getKey().'|'.$plainTextToken);
    }
}
