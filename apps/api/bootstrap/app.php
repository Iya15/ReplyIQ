<?php

use App\Http\Middleware\ApiKeyAuth;
use App\Http\Middleware\EnforcePlanLimit;
use App\Http\Middleware\ResolveTenant;
use App\Http\Middleware\SecurityHeaders;
use App\Http\Middleware\SentryContext;
use App\Http\Middleware\WidgetAuth;
use App\Jobs\DailyOpenAiCostReportJob;
use App\Jobs\TrackUsageJob;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        channels: __DIR__.'/../routes/channels.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->append(SecurityHeaders::class);
        // SentryContext enriches Sentry events with authenticated user + org.
        // Runs after auth; only active when SENTRY_LARAVEL_DSN is set.
        $middleware->append(SentryContext::class);
        $middleware->alias([
            'tenant' => ResolveTenant::class,
            'widget' => WidgetAuth::class,
            'api-key' => ApiKeyAuth::class,
            'plan-limit' => EnforcePlanLimit::class,
        ]);
    })
    ->withSchedule(function (Schedule $schedule) {
        $schedule->command('analytics:flush')->everyMinute()->withoutOverlapping();
        $schedule->command('analytics:views:refresh')->everyThirtyMinutes()->withoutOverlapping();
        $schedule->job(TrackUsageJob::class)->dailyAt('02:00')->withoutOverlapping();
        $schedule->job(DailyOpenAiCostReportJob::class)->dailyAt('06:00')->withoutOverlapping();
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();
