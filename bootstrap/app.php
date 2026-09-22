<?php

use App\Http\Middleware\Authenticate;
use App\Http\Middleware\RedirectIfAuthenticated;
use App\Http\Middleware\RedirectIfAuthenticatedTwoFactor;
use App\Http\Middleware\TwoFactorVerify;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        channels: __DIR__.'/../routes/channels.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'auth' => Authenticate::class,
            'guest' => RedirectIfAuthenticated::class,
            'two_factor' => TwoFactorVerify::class,
            'two_factor_success' => RedirectIfAuthenticatedTwoFactor::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        // API mobile `/api/v1/field`: FieldApiException → envelope kode khusus
        // mobile (konsep B.7): ALREADY_PROCESSED, RENTAL_STATE_CHANGED, dst.
        $exceptions->render(function (
            \App\Http\Controllers\Api\V1\Field\Concerns\FieldApiException $e,
            Request $request,
        ) {
            return \App\Http\Middleware\RenderFieldApiException::render($request, $e);
        });
    })
    ->withSchedule(function (\Illuminate\Console\Scheduling\Schedule $schedule): void {
        $schedule->command('app:cleanup-temp')->dailyAt('02:00');
        // SYS-07 audit sistem: backup database otomatis + retensi 14 file
        $schedule->command('system:backup --keep=14')->dailyAt('01:30');
        $schedule->command('fleet:check-maintenance')->dailyAt('06:30');
        $schedule->command('rental:send-reminders')->dailyAt('08:00');
        // FASE 3 audit sewa: sweep jatuh tempo + proses antrean tanpa worker khusus
        $schedule->command('rentals:mark-overdue')->everyTenMinutes();
        $schedule->command('queue:work --stop-when-empty --max-time=30')
            ->everyMinute()
            ->withoutOverlapping();
    })->create();
