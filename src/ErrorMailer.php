<?php

declare(strict_types=1);

namespace Artryazanov\ErrorMailer;

use Artryazanov\ErrorMailer\Mail\ExceptionOccurred;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Throwable;

class ErrorMailer
{
    private const MASK = '********';

    private const JSON_FLAGS = JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE;

    /**
     * Handles the exception and sends an email notification.
     */
    public function handle(Throwable $exception): void
    {
        // Check if the mailer is enabled
        if (! config('error-mailer.enabled', true)) {
            return;
        }

        if ($this->shouldIgnore($exception)) {
            return;
        }

        if (! $this->hasRecipients()) {
            return;
        }

        if ($this->isRateLimited()) {
            return;
        }

        try {
            $content = $this->prepareExceptionData($exception);

            // Queue the email
            Mail::send(new ExceptionOccurred($content));
        } catch (Throwable $e) {
            Log::error('ErrorMailer failed to send exception email: '.$e->getMessage());
        }
    }

    /**
     * Determine whether the exception is in the ignore list.
     */
    protected function shouldIgnore(Throwable $exception): bool
    {
        $ignored = config('error-mailer.ignore', []);
        if (! is_array($ignored)) {
            return false;
        }

        foreach ($ignored as $ignoredClass) {
            if (is_string($ignoredClass) && $ignoredClass !== '' && is_a($exception, $ignoredClass)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if at least one recipient is configured.
     */
    protected function hasRecipients(): bool
    {
        return ExceptionOccurred::parseEmails(config('error-mailer.to')) !== []
            || ExceptionOccurred::parseEmails(config('error-mailer.cc')) !== []
            || ExceptionOccurred::parseEmails(config('error-mailer.bcc')) !== [];
    }

    /**
     * Apply anti-spam rate limiting safely without crashing if the cache store is down.
     */
    protected function isRateLimited(): bool
    {
        $limit = (int) config('error-mailer.rate_limit.limit', 15);
        $decay = (int) config('error-mailer.rate_limit.window', 3600); // 1 hour by default

        if ($limit <= 0 || $decay <= 0) {
            return false;
        }

        $key = 'error-mailer-mails:'.config('app.env');

        try {
            if (RateLimiter::tooManyAttempts($key, $limit)) {
                // Skip sending to prevent spam
                Log::warning('ErrorMailer: Exception email suppressed by rate limiter', [
                    'key' => $key,
                    'limit' => $limit,
                    'window_seconds' => $decay,
                ]);

                return true;
            }

            RateLimiter::hit($key, $decay);
        } catch (Throwable $e) {
            Log::warning('ErrorMailer: Rate limiter check failed: '.$e->getMessage());
        }

        return false;
    }

    /**
     * Prepares the exception data into a serializable array for the mailable.
     *
     * @return array<string, mixed>
     */
    protected function prepareExceptionData(Throwable $exception): array
    {
        $content = [];
        $content['class'] = get_class($exception);
        $content['message'] = $exception->getMessage();
        $content['file'] = $exception->getFile();
        $content['line'] = $exception->getLine();

        $content['app_name'] = (string) config('app.name', 'Laravel');
        $content['app_env'] = (string) config('app.env', 'production');
        $content['php_version'] = PHP_VERSION;
        $content['laravel_version'] = app()->version();

        // Sanitize trace to serializable, minimal fields used by the view
        $content['trace'] = $this->sanitizeTrace($exception->getTrace());

        // Get request details safely (can be called in console commands where request() might not have URL/IP)
        if (app()->runningInConsole()) {
            $content['is_console'] = true;
            $content['url'] = 'Command Line / Artisan';
            $content['method'] = 'CLI';
            $content['ip'] = '127.0.0.1';
            $content['body'] = [];
            $content['headers'] = [];
            $content['command'] = $this->sanitizeContextData($_SERVER['argv'] ?? []);
            $content['server'] = $this->sanitizeContextData($_SERVER);
        } else {
            $request = request();
            $content['is_console'] = false;
            $content['url'] = $request->fullUrl();
            $content['method'] = $request->method();
            $content['ip'] = $request->ip();
            $content['body'] = $this->sanitizeContextData($request->all());
            $content['headers'] = $this->sanitizeContextData($request->headers->all());
            $content['cookie'] = $this->sanitizeContextData($request->cookie());
            $content['server'] = $this->sanitizeContextData($_SERVER);

            $userStr = $this->resolveUserString();
            if ($userStr !== null) {
                $content['user'] = $userStr;
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
                'trace' => $this->sanitizeTrace($previous->getTrace()),
            ];
        }

        $content['markdown'] = $this->generateMarkdown($content);

        return $content;
    }

    /**
     * Sanitize stack trace to serializable, minimal fields.
     *
     * @param  array<int, array<string, mixed>>  $trace
     * @return array<int, array{class: string|null, type: string|null, function: string, file: string|null, line: int|null}>
     */
    protected function sanitizeTrace(array $trace): array
    {
        $maxFrames = (int) config('error-mailer.trace_max_frames', 100);
        if ($maxFrames > 0) {
            $trace = array_slice($trace, 0, $maxFrames);
        }

        return array_values(array_map(static function (array $frame): array {
            return [
                'class' => isset($frame['class']) ? (string) $frame['class'] : null,
                'type' => isset($frame['type']) ? (string) $frame['type'] : null,
                'function' => isset($frame['function']) ? (string) $frame['function'] : '{closure}',
                'file' => isset($frame['file']) ? (string) $frame['file'] : null,
                'line' => isset($frame['line']) ? (int) $frame['line'] : null,
            ];
        }, $trace));
    }

    /**
     * Safely resolve the authenticated user representation without throwing if the DB/Auth store fails.
     */
    protected function resolveUserString(): ?string
    {
        try {
            /** @var mixed $user */
            $user = request()->user();
            if (! is_object($user)) {
                return null;
            }

            if (isset($user->email)) {
                $userStr = "({$user->email})";
                if (isset($user->name)) {
                    $userStr = "{$user->name} {$userStr}";
                }

                return trim($userStr);
            }

            if (isset($user->id)) {
                return "ID: {$user->id}";
            }

            if (method_exists($user, 'getAuthIdentifier') && $user->getAuthIdentifier() !== null) {
                return 'ID: '.$user->getAuthIdentifier();
            }

            return 'Authenticated User';
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * Recursively mask sensitive keys and convert non-serializable values (like UploadedFile) into safe primitives.
     */
    protected function sanitizeContextData(mixed $data): mixed
    {
        if (is_array($data)) {
            $sanitized = [];
            foreach ($data as $key => $value) {
                if (is_string($key) && $this->isSensitiveKey($key)) {
                    $sanitized[$key] = self::MASK;
                } else {
                    $sanitized[$key] = $this->sanitizeContextData($value);
                }
            }

            return $sanitized;
        }

        if ($data instanceof UploadedFile) {
            return [
                'name' => $data->getClientOriginalName(),
                'size' => $data->isValid() ? $data->getSize() : 0,
                'mime' => $data->getClientMimeType(),
            ];
        }

        return $data;
    }

    /**
     * Determine if a parameter, header, cookie, or server key contains sensitive credentials.
     */
    protected function isSensitiveKey(string $key): bool
    {
        $normalizedKey = strtolower(trim($key));
        if ($normalizedKey === '') {
            return false;
        }

        $hiddenParameters = config('error-mailer.hidden_parameters', []);
        if (is_array($hiddenParameters)) {
            foreach ($hiddenParameters as $param) {
                if (is_string($param) && strtolower(trim($param)) === $normalizedKey) {
                    return true;
                }
            }
        }

        $sensitiveSuffixes = ['_password', '_secret', '_key', '_token', '-password', '-secret', '-key', '-token'];
        foreach ($sensitiveSuffixes as $suffix) {
            if (str_ends_with($normalizedKey, $suffix)) {
                return true;
            }
        }

        $sensitiveServerKeys = [
            'http_authorization',
            'http_proxy_authorization',
            'http_cookie',
            'http_x_api_key',
            'http_x_xsrf_token',
            'http_x_csrf_token',
            'php_auth_pw',
        ];

        return in_array($normalizedKey, $sensitiveServerKeys, true);
    }

    /**
     * Generates a Markdown representation of the exception.
     *
     * @param  array<string, mixed>  $content
     */
    protected function generateMarkdown(array $content): string
    {
        $md = "# {$content['message']}\n\n";
        $md .= "{$content['class']}\n\n";

        $appName = (string) ($content['app_name'] ?? config('app.name', 'Laravel'));
        $appEnv = (string) ($content['app_env'] ?? config('app.env', 'production'));
        $phpVersion = (string) ($content['php_version'] ?? PHP_VERSION);
        $laravelVersion = (string) ($content['laravel_version'] ?? app()->version());

        $md .= "{$appName} · {$appEnv}\n";
        $md .= "PHP {$phpVersion} · Laravel {$laravelVersion}\n\n";

        $md .= "## Request Context\n\n";

        if ($content['is_console'] ?? false) {
            if (! empty($content['command'])) {
                $md .= "### CONSOLE_COMMAND\n\n```json\n";
                $md .= json_encode($content['command'], self::JSON_FLAGS)."\n```\n\n";
            }
        } else {
            if (! empty($content['user'])) {
                $md .= "### USER\n\n\"{$content['user']}\"\n\n";
            }
            $md .= "### URL\n\n\"".($content['url'] ?? 'N/A')."\"\n\n";
            $md .= "### METHOD\n\n\"".($content['method'] ?? 'N/A')."\"\n\n";

            if (! empty($content['body'])) {
                $md .= "### BODY\n\n```json\n";
                $md .= json_encode($content['body'], self::JSON_FLAGS)."\n```\n\n";
            }
        }

        $md .= "## Stack Trace\n\n";
        $md .= "**Exception Location:** {$content['file']}:{$content['line']}\n\n";
        $trace = is_array($content['trace'] ?? null) ? $content['trace'] : [];
        foreach (array_slice($trace, 0, 40) as $index => $frame) {
            $file = $frame['file'] ?? '[internal]';
            $line = $frame['line'] ?? '';
            $md .= "{$index} - {$file}:{$line}\n";
        }

        if (isset($content['previous']) && is_array($content['previous'])) {
            $md .= "\n## Previous Exception\n\n";
            $md .= "### {$content['previous']['class']}\n\n";
            $md .= "{$content['previous']['message']}\n\n";
            $md .= "**Exception Location:** {$content['previous']['file']}:{$content['previous']['line']}\n\n";
            $prevTrace = is_array($content['previous']['trace'] ?? null) ? $content['previous']['trace'] : [];
            foreach (array_slice($prevTrace, 0, 40) as $index => $frame) {
                $file = $frame['file'] ?? '[internal]';
                $line = $frame['line'] ?? '';
                $md .= "{$index} - {$file}:{$line}\n";
            }
        }

        return $md;
    }
}
