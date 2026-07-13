<?php

namespace Artryazanov\ErrorMailer\Tests\Unit;

use Artryazanov\ErrorMailer\Mail\ExceptionOccurred;
use Artryazanov\ErrorMailer\Tests\TestCase;
use Illuminate\Support\Facades\Config;

class ExceptionOccurredTest extends TestCase
{
    public function test_mailable_builds_correctly_with_hash_subject()
    {
        Config::set('error-mailer.from', 'system@example.com');
        Config::set('error-mailer.to', 'admin@example.com');
        Config::set('error-mailer.subject', 'Site Error');
        Config::set('error-mailer.view', 'error-mailer::emails.exception');

        $content = [
            'message' => 'Something went wrong',
            'file' => 'test.php',
            'line' => 123
        ];

        $mailable = new ExceptionOccurred($content);
        $mailable->build();

        // Check subject contains the hashed message
        $hash = substr(sha1('Something went wrong'), 0, 10);
        $this->assertEquals("Site Error [{$hash}]", $mailable->subject);

        $this->assertTrue($mailable->hasFrom('system@example.com'));
        $this->assertTrue($mailable->hasTo('admin@example.com'));
        $this->assertEquals('error-mailer::emails.exception', $mailable->view);
    }

    public function test_it_parses_comma_separated_emails()
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

    public function test_it_assigns_to_configured_queue()
    {
        Config::set('error-mailer.queue', 'high-priority');

        $mailable = new ExceptionOccurred(['message' => 'Test']);

        $this->assertEquals('high-priority', $mailable->queue);
    }
}
