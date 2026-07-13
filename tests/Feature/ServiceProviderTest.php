<?php

namespace Artryazanov\ErrorMailer\Tests\Feature;

use Artryazanov\ErrorMailer\Tests\TestCase;
use Artryazanov\ErrorMailer\ErrorMailer;
use Illuminate\Support\Facades\Config;

class ServiceProviderTest extends TestCase
{
    public function test_it_merges_configuration()
    {
        $this->assertNotNull(Config::get('error-mailer.enabled'));
        $this->assertEquals('error-mailer@example.com', Config::get('error-mailer.from'));
    }

    public function test_it_registers_facade()
    {
        $mailer = $this->app->make('error-mailer');
        
        $this->assertInstanceOf(ErrorMailer::class, $mailer);
    }

    public function test_it_can_publish_assets()
    {
        // This tests that the publishers are registered correctly in the Service Provider
        $publishGroups = \Illuminate\Support\ServiceProvider::pathsToPublish(
            \Artryazanov\ErrorMailer\ErrorMailerServiceProvider::class
        );

        $this->assertArrayHasKey(
            realpath(__DIR__.'/../../config/error-mailer.php'),
            $publishGroups
        );

        $this->assertArrayHasKey(
            realpath(__DIR__.'/../../resources/views'),
            $publishGroups
        );
    }
}
