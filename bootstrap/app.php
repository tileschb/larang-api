<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        apiPrefix: '',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->use([
            \App\Http\Middleware\ForceJsonResponse::class,
            \Illuminate\Http\Middleware\TrustProxies::class,
            \Illuminate\Http\Middleware\HandleCors::class,
            \Illuminate\Foundation\Http\Middleware\PreventRequestsDuringMaintenance::class,
            \Illuminate\Foundation\Http\Middleware\TrimStrings::class,
            \Illuminate\Foundation\Http\Middleware\ConvertEmptyStringsToNull::class
        ]);
        $middleware->group('api', [
            \Illuminate\Routing\Middleware\SubstituteBindings::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        // custom API exceptions
        $exceptions->render(
            fn(\App\Exceptions\ApiException $e) =>
            \App\Services\ApiResponseService::errorResponse(
                $e->getMessage(),
                $e->getTypeName(),
                $e->getStatusCode(),
                $e->getDetails()
            )
        );
        // base framework exceptions
        $exceptions->render(
            fn(\Symfony\Component\HttpKernel\Exception\NotFoundHttpException $e) =>
            \App\Services\ApiResponseService::errorResponse(
                'Resource route not found',
                'ROUTE_NOT_FOUND',
                404
            )
        );
        $exceptions->render(
            fn(\Illuminate\Validation\ValidationException $e) =>
            \App\Services\ApiResponseService::errorResponse(
                'The given data was invalid.',
                'VALIDATION_ERROR',
                422,
                $e->errors()
            )
        );
        $exceptions->render(
            fn(\Illuminate\Auth\AuthenticationException $e) =>
            \App\Services\ApiResponseService::errorResponse(
                'Unauthenticated.',
                'UNAUTHENTICATED',
                401
            )
        );
        $exceptions->render(
            fn(\Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException $e) =>
            \App\Services\ApiResponseService::errorResponse(
                'Forbidden.',
                'FORBIDDEN',
                403
            )
        );

        // in production, catch all exceptions and return a generic error message
        if (config('app.env') === 'production') {
            $exceptions->render(
                function(Throwable $e) {
                    \App\Services\ApiResponseService::errorResponse(
                        'An unexpected error occurred.',
                        'UNEXPECTED_ERROR',
                    );
                    report($e);
                }
            );
        }
    })->create();
