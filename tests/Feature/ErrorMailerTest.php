<?php

declare(strict_types=1);

namespace Artryazanov\ErrorMailer\Tests\Feature;

use Artryazanov\ErrorMailer\Facades\ErrorMailer;
use Artryazanov\ErrorMailer\Mail\ExceptionOccurred;
use Artryazanov\ErrorMailer\Tests\TestCase;
use Exception;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Mail\Mailer;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use InvalidArgumentException;
use LogicException;
use RuntimeException;

class CustomNamespacedException extends RuntimeException {}

class PreviousNamespacedException extends LogicException {}

class ErrorMailerTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
    }

    protected function simulateHttpRequest(): void
    {
        $app = app();
        $reflection = new \ReflectionClass($app);
        if ($reflection->hasProperty('isRunningInConsole')) {
            $property = $reflection->getProperty('isRunningInConsole');
            $property->setAccessible(true);
            $property->setValue($app, false);
        } elseif ($reflection->hasProperty('runningInConsole')) {
            $property = $reflection->getProperty('runningInConsole');
            $property->setAccessible(true);
            $property->setValue($app, false);
        }
    }

    public function test_it_sends_email_on_handle_and_generates_console_markdown(): void
    {
        Config::set('error-mailer.enabled', true);
        Config::set('error-mailer.to', 'admin@example.com');

        $exception = new Exception('A test exception');

        ErrorMailer::handle($exception);

        Mail::assertQueued(ExceptionOccurred::class, function (ExceptionOccurred $mail): bool {
            $mail->build();
            $html = $mail->render();

            $this->assertStringContainsString('A test exception', $html);
            $this->assertStringContainsString('Command:', $html);
            $this->assertStringContainsString('-&gt;</span><span class="trace-method">', $html);

            $markdown = $mail->content['markdown'];
            $this->assertStringContainsString('# A test exception', $markdown);
            $this->assertStringContainsString('### CONSOLE_COMMAND', $markdown);
            $this->assertStringContainsString('## Stack Trace', $markdown);

            return $mail->content['message'] === 'A test exception' &&
                   $mail->content['class'] === Exception::class &&
                   $mail->hasTo('admin@example.com');
        });
    }

    public function test_it_does_not_send_email_when_disabled(): void
    {
        Config::set('error-mailer.enabled', false);

        $exception = new Exception('A test exception');

        ErrorMailer::handle($exception);

        Mail::assertNothingQueued();
    }

    public function test_it_does_not_send_email_when_exception_or_parent_class_is_ignored(): void
    {
        Config::set('error-mailer.enabled', true);
        Config::set('error-mailer.ignore', [
            LogicException::class,
        ]);

        // Direct match
        ErrorMailer::handle(new LogicException('Direct ignored error'));
        // Subclass match (InvalidArgumentException extends LogicException)
        ErrorMailer::handle(new InvalidArgumentException('Subclass ignored error'));
        Mail::assertNothingQueued();

        // Invalid non-array ignore config should not break sending
        Config::set('error-mailer.ignore', 'invalid');
        ErrorMailer::handle(new RuntimeException('Reported error'));
        Mail::assertQueued(ExceptionOccurred::class, 1);
    }

    public function test_it_does_not_send_email_when_no_recipients_configured_but_sends_when_only_cc_or_bcc_set(): void
    {
        Config::set('error-mailer.enabled', true);
        Config::set('error-mailer.to', '');
        Config::set('error-mailer.cc', '');
        Config::set('error-mailer.bcc', '');

        ErrorMailer::handle(new Exception('No recipients'));
        Mail::assertNothingQueued();

        // Only CC configured
        Config::set('error-mailer.cc', 'cc-only@example.com');
        ErrorMailer::handle(new Exception('CC only recipient'));

        Mail::assertQueued(ExceptionOccurred::class, function (ExceptionOccurred $mail): bool {
            $mail->build();

            return empty($mail->to) && $mail->hasCc('cc-only@example.com');
        });
    }

    public function test_it_respects_rate_limit_and_logs_warning_when_suppressed(): void
    {
        Config::set('error-mailer.enabled', true);
        Config::set('error-mailer.rate_limit.limit', 2);
        Config::set('error-mailer.rate_limit.window', 60);

        Log::shouldReceive('warning')
            ->twice()
            ->withArgs(function (string $message, array $context): bool {
                return $message === 'ErrorMailer: Exception email suppressed by rate limiter'
                    && $context['limit'] === 2
                    && $context['window_seconds'] === 60;
            });

        $exception = new Exception('Rate limit test');

        // Send 1st
        ErrorMailer::handle($exception);
        // Send 2nd
        ErrorMailer::handle($exception);

        // These 2 should be blocked and logged
        ErrorMailer::handle($exception);
        ErrorMailer::handle($exception);

        Mail::assertQueued(ExceptionOccurred::class, 2);

        $key = 'error-mailer-mails:'.config('app.env');
        $this->assertTrue(RateLimiter::tooManyAttempts($key, 2));
    }

    public function test_it_disables_rate_limit_when_limit_is_zero_or_negative(): void
    {
        Config::set('error-mailer.enabled', true);
        Config::set('error-mailer.rate_limit.limit', 0);

        ErrorMailer::handle(new Exception('Unlimited 1'));
        ErrorMailer::handle(new Exception('Unlimited 2'));

        Mail::assertQueued(ExceptionOccurred::class, 2);
    }

    public function test_it_still_sends_email_if_rate_limiter_cache_throws_exception(): void
    {
        Config::set('error-mailer.enabled', true);

        RateLimiter::shouldReceive('tooManyAttempts')
            ->once()
            ->andThrow(new RuntimeException('Redis connection refused'));

        Log::shouldReceive('warning')
            ->once()
            ->withArgs(fn (string $msg): bool => str_contains($msg, 'ErrorMailer: Rate limiter check failed: Redis connection refused'));

        ErrorMailer::handle(new Exception('Cache down error'));

        Mail::assertQueued(ExceptionOccurred::class, 1);
    }

    private static function createNamespacedException(): CustomNamespacedException
    {
        $previous = new PreviousNamespacedException('Previous namespaced error');

        return new CustomNamespacedException('Current namespaced error', 0, $previous);
    }

    public function test_it_renders_namespaced_classes_previous_exception_internal_frames_and_respects_trace_max_frames(): void
    {
        Config::set('error-mailer.enabled', true);
        Config::set('error-mailer.trace_max_frames', 4);

        $exception = self::createNamespacedException();

        ErrorMailer::handle($exception);

        Mail::assertQueued(ExceptionOccurred::class, function (ExceptionOccurred $mail): bool {
            // Add an explicit internal frame (file=null, line=null) to verify Blade rendering of [internal function]
            $mail->content['trace'][] = [
                'class' => null,
                'type' => null,
                'function' => 'call_user_func',
                'file' => null,
                'line' => null,
            ];
            $mail->content['previous']['trace'][] = [
                'class' => null,
                'type' => null,
                'function' => 'array_map',
                'file' => null,
                'line' => null,
            ];

            $mail->build();
            $html = $mail->render();

            // Check counters and class_basename vs full FQCN in title
            $this->assertStringContainsString('(1/2)', $html);
            $this->assertStringContainsString('(2/2)', $html);
            $this->assertStringContainsString('title="'.CustomNamespacedException::class.'">CustomNamespacedException</span>', $html);
            $this->assertStringContainsString('title="'.PreviousNamespacedException::class.'">PreviousNamespacedException</span>', $html);
            $this->assertStringContainsString('::</span><span class="trace-method">createNamespacedException</span>', $html);
            $this->assertStringContainsString('Previous namespaced error', $html);
            $this->assertStringContainsString('[internal function]', $html);

            // Check Markdown for Previous Exception
            $markdown = $mail->content['markdown'];
            $this->assertStringContainsString('## Previous Exception', $markdown);
            $this->assertStringContainsString('### '.PreviousNamespacedException::class, $markdown);
            $this->assertStringContainsString('Previous namespaced error', $markdown);

            // Verify trace_max_frames limited the original trace capture to 4 frames (+1 synthetic = 5)
            $this->assertCount(5, $mail->content['trace']);
            $this->assertCount(5, $mail->content['previous']['trace']);

            return isset($mail->content['previous']) &&
                   $mail->content['previous']['message'] === 'Previous namespaced error' &&
                   $mail->content['previous']['class'] === PreviousNamespacedException::class;
        });
    }

    public function test_it_logs_error_when_mail_send_fails(): void
    {
        Config::set('error-mailer.enabled', true);

        $mailerMock = \Mockery::mock(Mailer::class);
        $mailerMock->shouldReceive('send')->andThrow(new Exception('Mail failed'));
        // Swap the Mail facade directly to bypass Mail::fake()
        Mail::swap($mailerMock);

        Log::shouldReceive('error')
            ->once()
            ->withArgs(function (string $msg): bool {
                return str_contains($msg, 'ErrorMailer failed to send exception email: Mail failed');
            });

        $exception = new Exception('A test exception');

        ErrorMailer::handle($exception);
    }

    public function test_it_captures_http_request_context_masks_secrets_and_handles_invalid_utf8(): void
    {
        Config::set('error-mailer.enabled', true);
        $this->simulateHttpRequest();

        $file = UploadedFile::fake()->create('document.pdf', 12, 'application/pdf');
        $invalidUtf8 = "malformed-\xB1\x31-utf8";

        // Set up the request
        $request = request();
        $request->server->set('REMOTE_ADDR', '192.168.1.1');
        $_SERVER['DB_PASSWORD'] = 'super-secret-db-pass';
        $_SERVER['APP_KEY'] = 'base64:secret-app-key';
        $_SERVER['PHP_AUTH_PW'] = 'basic-auth-pass';
        $request->setMethod('POST');
        $request->merge([
            'foo' => 'bar',
            'binary_text' => $invalidUtf8,
            'password' => 'my-secret-password',
            'api_token' => 'secret-token-123',
            'nested' => [
                'client_secret' => 'should-be-masked',
                'public' => 'visible',
            ],
            '   ' => 'blank-key',
        ]);
        $request->files->set('attachment', $file);
        $request->cookies->set('session_token', 'secret-cookie-val');
        $request->cookies->set('theme', 'dark');
        $request->headers->set('X-Test-Header', 'TestValue');
        $request->headers->set('Authorization', 'Bearer secret-jwt');

        $exception = new Exception('HTTP error');

        ErrorMailer::handle($exception);

        Mail::assertQueued(ExceptionOccurred::class, function (ExceptionOccurred $mail): bool {
            // Verify that the mailable can be safely serialized for queues even with UploadedFile
            $serialized = serialize($mail);
            $this->assertNotEmpty($serialized);

            $mail->build();
            $html = $mail->render();

            $this->assertStringContainsString('Payload:', $html);
            $this->assertStringContainsString('malformed-', $html);
            $this->assertStringNotContainsString('my-secret-password', $html);
            $this->assertStringNotContainsString('secret-jwt', $html);
            $this->assertStringNotContainsString('super-secret-db-pass', $html);
            $this->assertStringNotContainsString('secret-cookie-val', $html);

            // Check Markdown for HTTP request
            $markdown = $mail->content['markdown'];
            $this->assertStringContainsString('### URL', $markdown);
            $this->assertStringContainsString('### METHOD', $markdown);
            $this->assertStringContainsString('### BODY', $markdown);
            $this->assertStringNotContainsString('my-secret-password', $markdown);

            return $mail->content['ip'] === '192.168.1.1' &&
                   $mail->content['method'] === 'POST' &&
                   $mail->content['body']['foo'] === 'bar' &&
                   $mail->content['body']['password'] === '********' &&
                   $mail->content['body']['api_token'] === '********' &&
                   $mail->content['body']['nested']['client_secret'] === '********' &&
                   $mail->content['body']['nested']['public'] === 'visible' &&
                   $mail->content['body']['attachment']['name'] === 'document.pdf' &&
                   $mail->content['cookie']['session_token'] === '********' &&
                   $mail->content['cookie']['theme'] === 'dark' &&
                   $mail->content['headers']['x-test-header'][0] === 'TestValue' &&
                   $mail->content['headers']['authorization'] === '********' &&
                   $mail->content['server']['DB_PASSWORD'] === '********' &&
                   $mail->content['server']['APP_KEY'] === '********' &&
                   $mail->content['server']['PHP_AUTH_PW'] === '********';
        });
    }

    public function test_it_captures_user_context_and_handles_auth_failures(): void
    {
        Config::set('error-mailer.enabled', true);
        $this->simulateHttpRequest();

        $request = request();

        // 1. Test with email and name
        $request->setUserResolver(function () {
            $user = new \stdClass;
            $user->name = 'John Doe';
            $user->email = 'john@example.com';

            return $user;
        });

        ErrorMailer::handle(new Exception('HTTP error 1'));

        Mail::assertQueued(ExceptionOccurred::class, function (ExceptionOccurred $mail): bool {
            $mail->build();
            $html = $mail->render();
            $this->assertStringContainsString('User: John Doe (john@example.com)', $html);
            $this->assertStringContainsString("### USER\n\n\"John Doe (john@example.com)\"", $mail->content['markdown']);

            return $mail->content['user'] === 'John Doe (john@example.com)';
        });

        // 2. Test with email only (no name)
        $request->setUserResolver(function () {
            $user = new \stdClass;
            $user->email = 'only-email@example.com';

            return $user;
        });

        ErrorMailer::handle(new Exception('HTTP error 2'));

        Mail::assertQueued(ExceptionOccurred::class, function (ExceptionOccurred $mail): bool {
            return $mail->content['message'] === 'HTTP error 2'
                && $mail->content['user'] === '(only-email@example.com)';
        });

        // 3. Test with ID only
        $request->setUserResolver(function () {
            $user = new \stdClass;
            $user->id = 123;

            return $user;
        });

        ErrorMailer::handle(new Exception('HTTP error 3'));

        Mail::assertQueued(ExceptionOccurred::class, function (ExceptionOccurred $mail): bool {
            return $mail->content['message'] === 'HTTP error 3'
                && $mail->content['user'] === 'ID: 123';
        });

        // 4. Test with Authenticatable contract (custom identifier)
        $request->setUserResolver(function () {
            $auth = \Mockery::mock(Authenticatable::class);
            $auth->shouldReceive('getAuthIdentifier')->andReturn('uuid-999');

            return $auth;
        });

        ErrorMailer::handle(new Exception('HTTP error 4'));

        Mail::assertQueued(ExceptionOccurred::class, function (ExceptionOccurred $mail): bool {
            return $mail->content['message'] === 'HTTP error 4'
                && $mail->content['user'] === 'ID: uuid-999';
        });

        // 5. Test with basic authenticated user (no specific attributes)
        $request->setUserResolver(function () {
            return new \stdClass;
        });

        ErrorMailer::handle(new Exception('HTTP error 5'));

        Mail::assertQueued(ExceptionOccurred::class, function (ExceptionOccurred $mail): bool {
            return $mail->content['message'] === 'HTTP error 5'
                && $mail->content['user'] === 'Authenticated User';
        });

        // 6. Test when user resolver throws an exception (e.g. database down)
        $request->setUserResolver(function () {
            throw new RuntimeException('Database connection lost while resolving user');
        });

        ErrorMailer::handle(new Exception('HTTP error 6'));

        Mail::assertQueued(ExceptionOccurred::class, function (ExceptionOccurred $mail): bool {
            return $mail->content['message'] === 'HTTP error 6' && ! isset($mail->content['user']);
        });
    }
}
