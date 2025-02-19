<?php

namespace App\Models;

use App\Support\HasRefreshTokens;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
/**
 * Extended personal access token model supporting auth and refresh token types.
 *
 * This class extends Laravel Sanctum's PersonalAccessToken to add support for
 * token types (auth/refresh) and token relationships. It manages both authentication
 * tokens and their associated refresh tokens, implementing the token rotation
 * pattern required by the system.
 *
 * @property int $id The token's ID
 * @property string $name Token name (unused in this implementation)
 * @property string $token The hashed token value
 * @property array $abilities The token's allowed abilities
 * @property string $type The token type ('auth' or 'refresh')
 * @property int|null $auth_token_id Reference to parent auth token for refresh tokens
 * @property Carbon|null $expires_at When the token expires
 * @property Carbon $created_at When the token was created
 * @property Carbon $updated_at When the token was last updated
 * @property-read User $tokenable The token's owner
 *
 * @property-read PersonalAccessToken|null $authToken For refresh tokens: the associated auth token
 * @property-read PersonalAccessToken|null $refreshToken For auth tokens: the associated refresh token
 *
 * @method static \Illuminate\Database\Eloquent\Builder|PersonalAccessToken whereType($value)
 * @method static \Illuminate\Database\Eloquent\Builder|PersonalAccessToken whereAuthTokenId($value)
 */
class PersonalAccessToken extends \Laravel\Sanctum\PersonalAccessToken
{
    use HasRefreshTokens;

    /**
     * Token type for authentication tokens
     * @var string
     */
    const TYPE_AUTH = 'auth';

    /**
     * Token type for refresh tokens
     * @var string
     */
    const TYPE_REFRESH = 'refresh';

    /**
     * Valid token types
     * @var array<string>
     */
    const TYPES = [
        self::TYPE_AUTH,
        self::TYPE_REFRESH,
    ];

    protected $fillable = [
        'token',
        'abilities',
        'type',
        'auth_token_id',
        'expires_at'
    ];

    /**
     * Get the auth token that this refresh token belongs to.
     *
     * For refresh tokens, returns the original authentication token that this
     * refresh token was created for. This relationship is null for auth tokens.
     *
     * @return BelongsTo
     */
    public function authToken(): BelongsTo
    {
        return $this->belongsTo(PersonalAccessToken::class, 'auth_token_id');
    }

    /**
     * Get the refresh token for this auth token.
     *
     * For authentication tokens, returns the associated refresh token if one exists.
     * This relationship is null for refresh tokens.
     *
     * @return HasOne
     */
    public function refreshToken(): HasOne
    {
        return $this->hasOne(PersonalAccessToken::class, 'auth_token_id');
    }

    /**
     * Check if the token has expired.
     *
     * @return bool True if the token has an expiration date and that date has passed
     */
    public function isExpired(): bool
    {
        return $this->expires_at && now()->gte($this->expires_at);
    }

    /**
     * Check if this is an authentication token.
     *
     * @return bool True if this is an auth token
     */
    public function isAuthToken(): bool
    {
        return $this->type === self::TYPE_AUTH;
    }

    /**
     * Check if this is a refresh token.
     *
     * @return bool True if this is a refresh token
     */
    public function isRefreshToken(): bool
    {
        return $this->type === self::TYPE_REFRESH;
    }

    /**
     * Invalidate this token and cascade to related tokens.
     *
     * When invalidating an auth token, also invalidates its refresh token.
     * When invalidating a refresh token, also invalidates its auth token.
     * This ensures complete token pair invalidation.
     */
    public function invalidateWithCascade(): void
    {
        if ($this->isAuthToken()) {
            // If this is an auth token, also delete its refresh token
            $this->refreshToken()->delete();
        } else {
            // If this is a refresh token, also delete its auth token
            $this->authToken()->delete();
        }

        $this->delete();
    }

    /**
     * Empty method to override sanctums default behavior of setting the last used at attribute
     *
     * @param $value
     * @return void
     */
    public function setLastUsedAtAttribute($value) {}

}
