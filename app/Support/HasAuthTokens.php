<?php

namespace App\Support;

use App\Models\PersonalAccessToken;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Collection;
use Laravel\Sanctum\HasApiTokens;
use Laravel\Sanctum\NewAccessToken;
/**
 * Trait providing authentication token functionality for users.
 *
 * This trait extends Laravel Sanctum's HasApiTokens to provide specialized token
 * management for the system. It adds support for authentication tokens with configurable
 * expiration and abilities.
 *
 * @property-read Collection|PersonalAccessToken[] $tokens
 *
 * @see PersonalAccessToken
 * @see \App\Services\TokenService
 */
trait HasAuthTokens
{
    use HasApiTokens;

    /**
     * Issue a new authentication token for the user.
     *
     * Creates a new authentication token with specified abilities and expiration.
     * This is typically used to create the initial access token in a token pair.
     *
     * @param array $abilities The token's abilities/permissions
     * @param \DateTimeInterface|null $expiresAt When the token should expire
     * @return NewAccessToken The newly created access token
     *
     * @see \App\Services\TokenService::issueTokenPair() For creating complete token pairs
     */
    public function issueAuthToken(array $abilities = ['*'], ?DateTimeInterface $expiresAt = null): NewAccessToken
    {
        $plainTextToken = $this->generateTokenString();

        $token = $this->tokens()->create([
            'token' => hash('sha256', $plainTextToken),
            'type' => PersonalAccessToken::TYPE_AUTH,
            'abilities' => $abilities,
            'expires_at' => $expiresAt,
        ]);

        return new NewAccessToken($token, $token->getKey().'|'.$plainTextToken);
    }
}
