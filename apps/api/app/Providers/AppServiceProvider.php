<?php

namespace App\Providers;

use App\Models\Chatbot;
use App\Models\Document;
use App\Policies\ChatbotPolicy;
use App\Policies\DocumentPolicy;
use App\Services\Ai\Contracts\LlmClient;
use App\Services\Ai\LlmClientFactory;
use App\Services\Embedding\EmbeddingClient;
use App\Services\Embedding\EmbeddingClientFactory;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(
            EmbeddingClient::class,
            fn ($app) => EmbeddingClientFactory::resolve($app),
        );

        $this->app->singleton(
            LlmClient::class,
            fn ($app) => LlmClientFactory::resolve($app),
        );
    }

    public function boot(): void
    {
        $this->configurePolicies();
        $this->configureRateLimiters();
        $this->configureEmailVerification();
    }

    private function configurePolicies(): void
    {
        Gate::policy(Chatbot::class, ChatbotPolicy::class);
        Gate::policy(Document::class, DocumentPolicy::class);
    }

    private function configureRateLimiters(): void
    {
        // 5 requests/min per IP — applied to register, login, forgot-password.
        RateLimiter::for('auth', function (Request $request) {
            return Limit::perMinute(5)->by($request->ip());
        });
    }

    private function configureEmailVerification(): void
    {
        // Override the signed URL that VerifyEmail sends so it points to our
        // API endpoint instead of the default web route. The frontend receives
        // the full signed API URL embedded in the query string and calls it.
        VerifyEmail::createUrlUsing(function (object $notifiable) {
            $apiUrl = URL::temporarySignedRoute(
                'verification.verify',
                Carbon::now()->addMinutes(Config::get('auth.verification.expire', 60)),
                [
                    'id' => $notifiable->getKey(),
                    'hash' => sha1($notifiable->getEmailForVerification()),
                ]
            );

            // Send user to the frontend; the SPA reads the query string and
            // calls the API URL directly.
            return rtrim((string) config('app.frontend_url', ''), '/').'/verify-email?url='.urlencode($apiUrl);
        });
    }
}
