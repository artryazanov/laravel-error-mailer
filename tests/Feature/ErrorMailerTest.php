<?php

namespace Artryazanov\ErrorMailer\Tests\Feature;

use Artryazanov\ErrorMailer\Facades\ErrorMailer;
use Artryazanov\ErrorMailer\Mail\ExceptionOccurred;
use Artryazanov\ErrorMailer\Tests\TestCase;
use Exception;
use Illuminate\Contracts\Mail\Mailer;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;

class ErrorMailerTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
    }

    public function test_it_sends_email_on_handle()
    {
        Config::set('error-mailer.enabled', true);
        Config::set('error-mailer.to', 'admin@example.com');

        $exception = new Exception('A test exception');

        ErrorMailer::handle($exception);

        Mail::assertQueued(ExceptionOccurred::class, function ($mail) {
            $mail->build();

            return $mail->content['message'] === 'A test exception' &&
                   $mail->content['class'] === Exception::class &&
                   $mail->hasTo('admin@example.com');
        });
    }

    public function test_it_does_not_send_email_when_disabled()
    {
        Config::set('error-mailer.enabled', false);

        $exception = new Exception('A test exception');

        ErrorMailer::handle($exception);

        Mail::assertNothingQueued();
    }

    public function test_it_respects_rate_limit()
    {
        Config::set('error-mailer.enabled', true);
        Config::set('error-mailer.rate_limit.limit', 2);
        Config::set('error-mailer.rate_limit.window', 60);

        $exception = new Exception('Rate limit test');

        // Send 1st
        ErrorMailer::handle($exception);
        // Send 2nd
        ErrorMailer::handle($exception);

        // These should be blocked
        ErrorMailer::handle($exception);
        ErrorMailer::handle($exception);

        Mail::assertQueued(ExceptionOccurred::class, 2);

        $key = 'error-mailer-mails:'.config('app.env');
        $this->assertTrue(RateLimiter::tooManyAttempts($key, 2));
    }

    public function test_it_sanitizes_previous_exception()
    {
        Config::set('error-mailer.enabled', true);

        $previous = new Exception('Previous error');
        $exception = new Exception('Current error', 0, $previous);

        ErrorMailer::handle($exception);

        Mail::assertQueued(ExceptionOccurred::class, function ($mail) {
            return isset($mail->content['previous']) &&
                   $mail->content['previous']['message'] === 'Previous error';
        });
    }

    public function test_it_logs_error_when_mail_send_fails()
    {
        Config::set('error-mailer.enabled', true);

        $mailerMock = \Mockery::mock(Mailer::class);
        $mailerMock->shouldReceive('send')->andThrow(new Exception('Mail failed'));
        // Swap the Mail facade directly to bypass Mail::fake()
        Mail::swap($mailerMock);

        Log::shouldReceive('error')
            ->once()
            ->withArgs(function ($msg) {
                return str_contains($msg, 'ErrorMailer failed to send exception email: Mail failed');
            });

        $exception = new Exception('A test exception');

        ErrorMailer::handle($exception);
    }

    public function test_it_captures_http_request_context()
    {
        Config::set('error-mailer.enabled', true);

        // Force runningInConsole to false using Reflection to simulate HTTP request
        $app = app();
        $reflection = new \ReflectionClass($app);
        if ($reflection->hasProperty('isRunningInConsole')) {
            $property = $reflection->getProperty('isRunningInConsole');
            $property->setAccessible(true);
            $property->setValue($app, false);
        } elseif ($reflection->hasProperty('runningInConsole')) {
            // Older laravel versions
            $property = $reflection->getProperty('runningInConsole');
            $property->setAccessible(true);
            $property->setValue($app, false);
        }

        // Set up the request
        $request = request();
        $request->server->set('REMOTE_ADDR', '192.168.1.1');
        $request->setMethod('POST');
        $request->merge(['foo' => 'bar']);
        $request->headers->set('X-Test-Header', 'TestValue');

        $exception = new Exception('HTTP error');

        ErrorMailer::handle($exception);

        Mail::assertQueued(ExceptionOccurred::class, function ($mail) {
            $mail->build();

            return $mail->content['ip'] === '192.168.1.1' &&
                   $mail->content['method'] === 'POST' &&
                   $mail->content['body'] === ['foo' => 'bar'] &&
                   $mail->content['headers']['x-test-header'][0] === 'TestValue';
        });
    }

    public function test_it_captures_user_context()
    {
        Config::set('error-mailer.enabled', true);

        // Force runningInConsole to false
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

        $request = request();

        // 1. Test with email and name
        $request->setUserResolver(function () {
            $user = new \stdClass;
            $user->name = 'John Doe';
            $user->email = 'john@example.com';

            return $user;
        });

        ErrorMailer::handle(new Exception('HTTP error 1'));

        Mail::assertQueued(ExceptionOccurred::class, function ($mail) {
            $mail->build();

            return $mail->content['user'] === 'John Doe (john@example.com)';
        });

        // 2. Test with ID only
        $request->setUserResolver(function () {
            $user = new \stdClass;
            $user->id = 123;

            return $user;
        });

        ErrorMailer::handle(new Exception('HTTP error 2'));

        Mail::assertQueued(ExceptionOccurred::class, function ($mail) {
            $mail->build();

            return $mail->content['user'] === 'ID: 123';
        });

        // 3. Test with basic authenticated user (no specific attributes)
        $request->setUserResolver(function () {
            return new \stdClass;
        });

        ErrorMailer::handle(new Exception('HTTP error 3'));

        Mail::assertQueued(ExceptionOccurred::class, function ($mail) {
            $mail->build();

            return $mail->content['user'] === 'Authenticated User';
        });
    }
}
