<?php

namespace App\Http\Controllers\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\User\RegisterRequest;
use App\Models\User;


class RegisterController extends Controller
{
    /**
     * Register a new user.
     */
    public function __invoke(RegisterRequest $request): \Illuminate\Http\JsonResponse
    {
        $user = User::create($request->validated());
        return $this->success($user, status: 201);
    }
}
