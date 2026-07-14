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

        $content['app_name'] = config('app.name', 'Laravel');
        $content['app_env'] = config('app.env', 'production');
        $content['php_version'] = PHP_VERSION;
        $content['laravel_version'] = app()->version();

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
            $content['is_console'] = true;
            $content['url'] = 'Command Line / Artisan';
            $content['method'] = 'CLI';
            $content['ip'] = '127.0.0.1';
            $content['body'] = [];
            $content['headers'] = [];
            $content['command'] = $_SERVER['argv'] ?? [];
            $content['server'] = $_SERVER;
        } else {
            $content['is_console'] = false;
            $content['url'] = request()->fullUrl();
            $content['method'] = request()->method();
            $content['ip'] = request()->ip();
            $content['body'] = request()->all();
            $content['headers'] = request()->headers->all();
            $content['cookie'] = request()->cookie();
            $content['server'] = $_SERVER;

            $user = request()->user();
            if ($user) {
                $userStr = '';
                if (isset($user->email)) {
                    $userStr = "({$user->email})";
                    if (isset($user->name)) {
                        $userStr = "{$user->name} {$userStr}";
                    }
                } elseif (isset($user->id)) {
                    $userStr = "ID: {$user->id}";
                } else {
                    $userStr = 'Authenticated User';
                }
                $content['user'] = trim($userStr);
            }
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
        $md = "# {$content['message']}\n\n";
        $md .= "{$content['class']}\n\n";

        $appName = config('app.name', 'Laravel');
        $appEnv = config('app.env', 'production');
        $md .= "{$appName} · {$appEnv}\n";
        $md .= 'PHP '.PHP_VERSION.' · Laravel '.app()->version()."\n\n";

        $md .= "## Request Context\n\n";

        if ($content['is_console'] ?? false) {
            if (! empty($content['command'])) {
                $md .= "### CONSOLE_COMMAND\n\n```json\n";
                $md .= json_encode($content['command'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)."\n```\n\n";
            }
        } else {
            if (! empty($content['user'])) {
                $md .= "### USER\n\n\"{$content['user']}\"\n\n";
            }
            $md .= "### URL\n\n\"".($content['url'] ?? 'N/A')."\"\n\n";
            $md .= "### METHOD\n\n\"".($content['method'] ?? 'N/A')."\"\n\n";

            if (! empty($content['body'])) {
                $md .= "### BODY\n\n```json\n";
                $md .= json_encode($content['body'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)."\n```\n\n";
            }
        }

        $md .= "## Stack Trace\n\n";
        $md .= "**Exception Location:** {$content['file']}:{$content['line']}\n\n";
        foreach (array_slice($content['trace'], 0, 40) as $index => $frame) {
            $file = $frame['file'] ?? '[internal]';
            $line = $frame['line'] ?? '';
            $md .= "{$index} - {$file}:{$line}\n";
        }

        if (isset($content['previous'])) {
            $md .= "\n## Previous Exception\n\n";
            $md .= "### {$content['previous']['class']}\n\n";
            $md .= "{$content['previous']['message']}\n\n";
            $md .= "**Exception Location:** {$content['previous']['file']}:{$content['previous']['line']}\n\n";
            foreach (array_slice($content['previous']['trace'], 0, 40) as $index => $frame) {
                $file = $frame['file'] ?? '[internal]';
                $line = $frame['line'] ?? '';
                $md .= "{$index} - {$file}:{$line}\n";
            }
        }

        return $md;
    }
}
