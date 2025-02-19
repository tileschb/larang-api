<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Mysql Grammar for using datetime fields with milliseconds resolution
        \Illuminate\Support\Facades\DB::connection()->setQueryGrammar(
            new \App\Support\Database\Query\Grammars\MysqlGrammar()
        );

        // Use the custom token model
        \Laravel\Sanctum\Sanctum::usePersonalAccessTokenModel(\App\Models\PersonalAccessToken::class);

        // Check token type and route name to ensure the right token is used
        \Laravel\Sanctum\Sanctum::authenticateAccessTokensUsing(function ($token, $isValid) {
            $isRefreshRoute = str_ends_with(request()->route()->getName(), 'token-refresh');
            return $isValid && ($isRefreshRoute ? $token->isRefreshToken() : $token->isAuthToken());
        });
    }
}
