<?php

namespace App\Http\Controllers\V1;

use App\Exceptions\ApiException;
use App\Exceptions\ApiExceptions;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Models\User;
use App\Services\TokenService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Laravel\Sanctum\NewAccessToken;


class AuthController extends Controller
{

    /**
     * Handle the login request
     *
     * Validates the login request and attempts to authenticate the user. If the credentials are invalid,
     * an InvalidCredentialsException is thrown. If the user is authenticated, a new token pair is issued
     * and a JSON response is returned with the token pair.
     *
     * Response format:
     * {
     *     "accessToken": string,
     *     "tokenType": "Bearer",
     *     "expiresIn": int,        // in milliseconds
     *     "refreshToken": string   // optional
     * }
     *
     * @param LoginRequest $request The login request object containing the user credentials
     * @return JsonResponse The JSON response with the token pair
     * @throws ApiException If the user credentials are invalid
     * @throws \Throwable If the database transaction fails
     */
    public function login(LoginRequest $request): JsonResponse
    {
        ['email' => $email, 'password' => $password] = $request->validated();
        $user = User::where('email', $email)->first();

        if (!$user || !Hash::check($password, $user->password)) {
            Log::alert('Invalid login attempt', [
                'email' => $email,
                'ip' => $request->ip()
            ]);
            throw ApiException::new(ApiExceptions::INVALID_CREDENTIALS);
        }

        $tokens = TokenService::issueTokenPair($user);

        return $this->responseWithToken($tokens['access'], $tokens['refresh']);
    }

    /**
     * Return the authenticated user
     *
     * Returns a JSON response with the authenticated user. The response includes the user's details
     * such as name, email, and id.
     *
     * Response format:
     * {
     *    "id": int,
     *    "name": string,
     *    "email": string,
     * }
     *
     * @return JsonResponse
     */
    public function me(): JsonResponse
    {
        return $this->success(auth()->user());
    }

    /**
     * Rotates the access token and refresh token pair
     *
     * Validates the refresh token provided in the request and issues a new token pair. The old token pair
     * is invalidated and the new token pair is returned in a JSON response. Uses the same response format
     * as the login method.
     *
     * @return JsonResponse The JSON response with the new token pair
     * @throws \Throwable If the database transaction fails
     */
    public function refresh(): JsonResponse
    {
        $refreshToken = \request()->bearerToken();
        $tokens = TokenService::refreshTokenPair($refreshToken);
        return $this->responseWithToken($tokens['access'], $tokens['refresh']);
    }

    /**
     * Handle the logout request
     *
     * Invalidates the current token pair, effectively logging the user out. Returns a JSON response
     * indicating success.
     *
     * @return JsonResponse
     */
    public function logout(): JsonResponse
    {
        TokenService::revokeTokenPair(\request()->bearerToken());
        return $this->success();
    }

    /**
     * Handle the logout from other devices request
     *
     * Invalidates all token pairs except the current one, effectively logging the user out from other
     * devices. Returns a JSON response indicating success.
     *
     * @return JsonResponse
     */
    public function logoutOtherDevices(): JsonResponse
    {
        /** @var \App\Models\User $user */
        $user = auth()->user();
        TokenService::revokeOtherTokenPairs(
            $user->currentAccessToken()
        );

        return response()->json([
            'status' => 'success',
            'message' => 'Successfully logged out from other devices'
        ]);
    }

    /**
     * Handle the logout from all devices request
     *
     * Invalidates all token pairs owned by the user, effectively logging the user out from all devices.
     * Returns a JSON response indicating success.
     *
     * @return JsonResponse
     */
    public function logoutAllDevices(): JsonResponse
    {
        /** @var \App\Models\User $user */
        $user = auth()->user();
        TokenService::revokeAllUserTokenPairs($user);

        return response()->json([
            'status' => 'success',
            'message' => 'Successfully logged out from all devices'
        ]);
    }

    /**
     * Send a JSON response with the token pair.
     *
     * Creates a JSON response with the access token and optional refresh token. Response also includes
     * the token type, expiration time in milliseconds, and refresh token if provided.
     * Response format:
     * {
     *      "accessToken": string,
     *      "tokenType": "Bearer",
     *      "expiresIn": int,        // in milliseconds
     *      "refreshToken": string   // optional
     * }
     *
     * @param NewAccessToken $authToken The new access token
     * @param NewAccessToken|null $refreshToken The new refresh token, if provided
     * @return JsonResponse
     */
    protected function responseWithToken(NewAccessToken $authToken, NewAccessToken $refreshToken = null): JsonResponse
    {
        /** @var \App\Models\PersonalAccessToken $token */
        $token = $authToken->accessToken;
        $responseData = [
            'access_token' => $authToken->plainTextToken,
            'token_type' => 'Bearer',
            'expires_in' => floor(now()->diffInMilliseconds($token->expires_at)),
        ];

        if ($refreshToken) {
            $responseData['refresh_token'] = $refreshToken->plainTextToken;
        }

        return $this->success($responseData);
    }

}
