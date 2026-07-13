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
            $content['method'] = 'CLI';
            $content['ip'] = '127.0.0.1';
            $content['body'] = [];
            $content['headers'] = [];
        } else {
            $content['url'] = request()->fullUrl();
            $content['method'] = request()->method();
            $content['ip'] = request()->ip();
            $content['body'] = request()->all();

            $headers = [];
            foreach (request()->headers->all() as $key => $value) {
                $headers[$key] = implode(', ', $value);
            }
            $content['headers'] = $headers;
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

        $content['markdown'] = $this->generateMarkdown($content);

        return $content;
    }

    /**
     * Generates a Markdown representation of the exception.
     */
    protected function generateMarkdown(array $content): string
    {
        $md = "# {$content['class']}\n\n";
        $md .= "{$content['message']}\n\n";

        $appName = config('app.name', 'Laravel');
        $appEnv = config('app.env', 'production');
        $md .= "{$appName} · {$appEnv}\n";

        $md .= 'PHP '.PHP_VERSION."\n";
        $md .= 'Laravel '.app()->version()."\n\n";

        $md .= "## Request Context\n\n";
        $md .= "**Method:** " . ($content['method'] ?? 'N/A') . "\n";
        $md .= "**URL:** " . ($content['url'] ?? 'N/A') . "\n";
        $md .= "**IP Address:** " . ($content['ip'] ?? 'N/A') . "\n\n";

        if (!empty($content['headers'])) {
            $md .= "## Headers\n\n```json\n";
            $md .= json_encode($content['headers'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n```\n\n";
        }

        if (!empty($content['body'])) {
            $md .= "## Request Body\n\n```json\n";
            $md .= json_encode($content['body'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n```\n\n";
        }

        $md .= "## Stack Trace\n\n";
        foreach (array_slice($content['trace'], 0, 40) as $index => $frame) {
            $file = $frame['file'] ?? '[internal]';
            $line = $frame['line'] ?? '';
            $md .= "{$index} - {$file}:{$line}\n";
        }

        if (isset($content['previous'])) {
            $md .= "\n## Previous Exception\n\n";
            $md .= "### {$content['previous']['class']}\n\n";
            $md .= "{$content['previous']['message']}\n\n";
            foreach (array_slice($content['previous']['trace'], 0, 40) as $index => $frame) {
                $file = $frame['file'] ?? '[internal]';
                $line = $frame['line'] ?? '';
                $md .= "{$index} - {$file}:{$line}\n";
            }
        }

        if (! empty($content['headers'])) {
            $md .= "\n## Headers\n\n";
            foreach ($content['headers'] as $key => $value) {
                $md .= "* **{$key}**: {$value}\n";
            }
        }

        if (! empty($content['body'])) {
            $md .= "\n## Request Body\n\n```json\n";
            $md .= json_encode($content['body'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)."\n";
            $md .= "```\n";
        }

        return $md;
    }
}
