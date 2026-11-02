<?php

declare(strict_types=1);

namespace Artryazanov\ErrorMailer\Tests\Feature;

use Artryazanov\ErrorMailer\ErrorMailer;
use Artryazanov\ErrorMailer\ErrorMailerServiceProvider;
use Artryazanov\ErrorMailer\Tests\TestCase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\ServiceProvider;

class ServiceProviderTest extends TestCase
{
    public function test_it_merges_configuration(): void
    {
        $this->assertNotNull(Config::get('error-mailer.enabled'));
        $this->assertEquals('error-mailer@example.com', Config::get('error-mailer.from'));
        $this->assertEquals('default', Config::get('error-mailer.queue'));
        $this->assertIsArray(Config::get('error-mailer.ignore'));
        $this->assertIsArray(Config::get('error-mailer.hidden_parameters'));
    }

    public function test_it_registers_facade_and_singleton(): void
    {
        $mailerByAlias = $this->app->make('error-mailer');
        $mailerByClass = $this->app->make(ErrorMailer::class);

        $this->assertInstanceOf(ErrorMailer::class, $mailerByAlias);
        $this->assertSame($mailerByAlias, $mailerByClass);
    }

    public function test_it_can_publish_assets(): void
    {
        // This tests that the publishers are registered correctly in the Service Provider
        $publishGroups = ServiceProvider::pathsToPublish(
            ErrorMailerServiceProvider::class
        );

        $this->assertArrayHasKey(
            (string) realpath(__DIR__.'/../../config/error-mailer.php'),
            $publishGroups
        );

        $this->assertArrayHasKey(
            (string) realpath(__DIR__.'/../../resources/views'),
            $publishGroups
        );
    }
}
