<?php

declare(strict_types=1);

namespace Artryazanov\ErrorMailer\Tests\Unit;

use Artryazanov\ErrorMailer\Mail\ExceptionOccurred;
use Artryazanov\ErrorMailer\Tests\TestCase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Str;

class ExceptionOccurredTest extends TestCase
{
    public function test_mailable_builds_correctly_with_hash_subject(): void
    {
        Config::set('error-mailer.from', 'system@example.com');
        Config::set('error-mailer.to', 'admin@example.com');
        Config::set('error-mailer.subject', 'Site Error');
        Config::set('error-mailer.include_hash_in_subject', true);
        Config::set('error-mailer.view', 'error-mailer::emails.exception');

        $content = [
            'message' => 'Something went wrong',
            'file' => 'test.php',
            'line' => 123,
        ];

        $mailable = new ExceptionOccurred($content);
        $mailable->build();

        // Check subject contains the message and hashed message
        $hash = substr(sha1('Something went wrong'), 0, 10);
        $this->assertEquals("Site Error: Something went wrong [{$hash}]", $mailable->subject);

        $this->assertTrue($mailable->hasFrom('system@example.com'));
        $this->assertTrue($mailable->hasTo('admin@example.com'));
        $this->assertEquals('error-mailer::emails.exception', $mailable->view);
    }

    public function test_mailable_builds_correctly_without_hash_subject_by_default(): void
    {
        Config::set('error-mailer.from', 'system@example.com');
        Config::set('error-mailer.to', 'admin@example.com');
        Config::set('error-mailer.subject', 'Site Error');
        Config::set('error-mailer.view', 'error-mailer::emails.exception');

        $content = [
            'message' => 'Something went wrong',
            'file' => 'test.php',
            'line' => 123,
        ];

        $mailable = new ExceptionOccurred($content);
        $mailable->build();

        $this->assertEquals('Site Error: Something went wrong', $mailable->subject);
    }

    public function test_mailable_truncates_long_exception_message_to_100_characters_and_hashes_full_message(): void
    {
        Config::set('error-mailer.subject', 'Prod Alert');
        Config::set('error-mailer.include_hash_in_subject', true);

        $longMessage = str_repeat('A', 150);
        $expectedShort = Str::limit($longMessage, 100);
        $expectedHash = substr(sha1($longMessage), 0, 10);

        $mailable = new ExceptionOccurred(['message' => $longMessage]);
        $mailable->build();

        $this->assertEquals("Prod Alert: {$expectedShort} [{$expectedHash}]", $mailable->subject);
    }

    public function test_mailable_normalizes_multiline_message_and_handles_empty_message_or_null_subject(): void
    {
        Config::set('error-mailer.subject', null);
        Config::set('error-mailer.from', null);
        Config::set('error-mailer.view', null);
        Config::set('app.name', 'MyApp');

        $mailable = new ExceptionOccurred([
            'message' => "First line\r\nSecond line\nThird line",
        ]);
        $mailable->build();

        $this->assertEquals('Error on MyApp: First line Second line Third line', $mailable->subject);
        $this->assertEmpty($mailable->from);
        $this->assertEquals('error-mailer::emails.exception', $mailable->view);

        $emptyMessageMailable = new ExceptionOccurred(['message' => '']);
        $emptyMessageMailable->build();
        $this->assertEquals('Error on MyApp', $emptyMessageMailable->subject);
    }

    public function test_it_parses_comma_separated_emails(): void
    {
        Config::set('error-mailer.to', 'admin@example.com, super@example.com');
        Config::set('error-mailer.cc', 'cc1@example.com,cc2@example.com');
        Config::set('error-mailer.bcc', 'bcc@example.com');

        $mailable = new ExceptionOccurred(['message' => 'Test']);
        $mailable->build();

        $this->assertTrue($mailable->hasTo('admin@example.com'));
        $this->assertTrue($mailable->hasTo('super@example.com'));

        $this->assertTrue($mailable->hasCc('cc1@example.com'));
        $this->assertTrue($mailable->hasCc('cc2@example.com'));

        $this->assertTrue($mailable->hasBcc('bcc@example.com'));
    }

    public function test_it_assigns_to_configured_queue(): void
    {
        Config::set('error-mailer.queue', 'high-priority');

        $mailable = new ExceptionOccurred(['message' => 'Test']);

        $this->assertEquals('high-priority', $mailable->queue);
    }

    public function test_it_parses_array_emails(): void
    {
        Config::set('error-mailer.to', ['admin@example.com', 'super@example.com']);

        $mailable = new ExceptionOccurred(['message' => 'Test']);
        $mailable->build();

        $this->assertTrue($mailable->hasTo('admin@example.com'));
        $this->assertTrue($mailable->hasTo('super@example.com'));
    }

    public function test_it_handles_empty_or_invalid_emails_configuration(): void
    {
        Config::set('error-mailer.to', null);
        Config::set('error-mailer.cc', 123);
        Config::set('error-mailer.bcc', []);

        $mailable = new ExceptionOccurred(['message' => 'Test']);
        $mailable->build();

        // The mailable should still build successfully with empty recipients.
        $this->assertEmpty($mailable->to);
        $this->assertEmpty($mailable->cc);
        $this->assertEmpty($mailable->bcc);
    }
}
