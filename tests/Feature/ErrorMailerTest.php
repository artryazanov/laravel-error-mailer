<?php

namespace Artryazanov\ErrorMailer\Tests\Feature;

use Artryazanov\ErrorMailer\Facades\ErrorMailer;
use Artryazanov\ErrorMailer\Mail\ExceptionOccurred;
use Artryazanov\ErrorMailer\Tests\TestCase;
use Exception;
use Illuminate\Support\Facades\Config;
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
}
