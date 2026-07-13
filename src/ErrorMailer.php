<?php

namespace Artryazanov\ErrorMailer;

use Artryazanov\ErrorMailer\Mail\ExceptionOccurred;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Throwable;

class ErrorMailer
{
    /**
     * Handles the exception and sends an email notification.
     */
    public function handle(Throwable $exception): void
    {
        // Check if the mailer is enabled
        if (! config('error-mailer.enabled', true)) {
            return;
        }

        // Anti-spam: limit error email notifications to a maximum number per time window
        $limit = (int) config('error-mailer.rate_limit.limit', 15);
        $decay = (int) config('error-mailer.rate_limit.window', 3600); // 1 hour by default
        $key = 'error-mailer-mails:'.config('app.env');

        if (RateLimiter::tooManyAttempts($key, $limit)) {
            // Skip sending to prevent spam
            Log::warning('ErrorMailer: Exception email suppressed by rate limiter', [
                'key' => $key,
                'limit' => $limit,
                'window_seconds' => $decay,
            ]);

            return;
        }

        RateLimiter::hit($key, $decay);

        try {
            $content = $this->prepareExceptionData($exception);

            // Queue the email
            Mail::send(new ExceptionOccurred($content));
        } catch (Throwable $e) {
            Log::error('ErrorMailer failed to send exception email: '.$e->getMessage());
        }
    }

    /**
     * Prepares the exception data into a serializable array for the mailable.
     */
    protected function prepareExceptionData(Throwable $exception): array
    {
        $content = [];
        $content['class'] = get_class($exception);
        $content['message'] = $exception->getMessage();
        $content['file'] = $exception->getFile();
        $content['line'] = $exception->getLine();

        // Sanitize trace to serializable, minimal fields used by the view
        $content['trace'] = collect($exception->getTrace())
            ->map(function ($frame) {
                return [
                    'class' => $frame['class'] ?? null,
                    'function' => $frame['function'],
                    'file' => $frame['file'] ?? null,
                    'line' => $frame['line'] ?? null,
                ];
            })
            ->all();

        // Get request details safely (can be called in console commands where request() might not have URL/IP)
        if (app()->runningInConsole()) {
            $content['url'] = 'Command Line / Artisan';
            $content['ip'] = '127.0.0.1';
            $content['body'] = [];
        } else {
            $content['url'] = request()->url();
            $content['body'] = request()->all();
            $content['ip'] = request()->ip();
        }

        // Include previous exception details if present (sanitized)
        $previous = $exception->getPrevious();
        if ($previous instanceof Throwable) {
            $content['previous'] = [
                'class' => get_class($previous),
                'message' => $previous->getMessage(),
                'file' => $previous->getFile(),
                'line' => $previous->getLine(),
                'trace' => collect($previous->getTrace())
                    ->map(function ($frame) {
                        return [
                            'class' => $frame['class'] ?? null,
                            'function' => $frame['function'],
                            'file' => $frame['file'] ?? null,
                            'line' => $frame['line'] ?? null,
                        ];
                    })
                    ->all(),
            ];
        }

        return $content;
    }
}
