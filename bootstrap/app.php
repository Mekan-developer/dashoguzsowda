<?php

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        channels: __DIR__.'/../routes/channels.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        // В прод-стеке приложение стоит за Caddy → nginx. Без доверия к
        // X-Forwarded-* Laravel считает соединение http и генерирует http-ссылки
        // (mixed content в админке), а request()->ip() возвращает адрес прокси.
        // '*' безопасно: php-fpm доступен только из внутренней docker-сети.
        $middleware->trustProxies(at: '*');

        $middleware->web(append: [
            \App\Http\Middleware\SetAdminLocale::class,
            \App\Http\Middleware\HandleInertiaRequests::class,
            \Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets::class,
        ]);

        $middleware->alias([
            'role'             => \App\Http\Middleware\EnsureRole::class,
            'news.permission'   => \App\Http\Middleware\EnsureNewsPermission::class,
            'banner.permission' => \App\Http\Middleware\EnsureBannerPermission::class,
            'not_blocked'       => \App\Http\Middleware\EnsureUserIsNotBlocked::class,
        ]);

        // Без этого Laravel сортирует auth:sanctum перед SetApiLocale (оба стоят
        // между собой и SubstituteBindings в приоритетном списке фреймворка),
        // из-за чего __() при 401 резолвится в локали 'en', для которой нет lang-файлов.
        $middleware->prependToPriorityList(
            before: \Illuminate\Contracts\Auth\Middleware\AuthenticatesRequests::class,
            prepend: \App\Http\Middleware\SetApiLocale::class,
        );
    })
    ->withExceptions(function (Exceptions $exceptions) {
        // Единый JSON-формат ошибок для мобильного API (см. CLAUDE.md "Формат ответов API"):
        // клиент никогда не должен видеть stack trace, даже при APP_DEBUG=true.
        $exceptions->render(function (Throwable $e, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            if ($e instanceof ValidationException) {
                return response()->json([
                    'message' => $e->getMessage(),
                    'errors'  => $e->errors(),
                ], $e->status);
            }

            if ($e instanceof AuthenticationException) {
                return response()->json([
                    'message' => __('messages.unauthenticated'),
                ], 401);
            }

            if ($e instanceof AuthorizationException) {
                return response()->json([
                    'message' => $e->getMessage() ?: __('messages.forbidden'),
                ], 403);
            }

            if ($e instanceof ModelNotFoundException || $e instanceof NotFoundHttpException) {
                return response()->json([
                    'message' => __('messages.not_found'),
                ], 404);
            }

            if ($e instanceof HttpExceptionInterface) {
                return response()->json([
                    'message' => $e->getMessage() ?: __('messages.server_error'),
                ], $e->getStatusCode());
            }

            return response()->json([
                'message' => __('messages.server_error'),
            ], 500);
        });
    })
    // Листенеры регистрируются только явно в EventServiceProvider::$listen;
    // авто-дискавери отключён, иначе каждый листенер срабатывал бы дважды
    ->withEvents(discover: false)
    ->withProviders([
        \App\Providers\EventServiceProvider::class,
    ])
    ->create();
