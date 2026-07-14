<?php

use Artryazanov\ErrorMailer\ErrorMailerServiceProvider;
use Illuminate\Support\Facades\Config;

// Suppress PHP 8.4 deprecation warnings from dependencies
error_reporting(E_ALL & ~E_DEPRECATED & ~E_USER_DEPRECATED);

require_once __DIR__.'/vendor/autoload.php';

echo "Booting Laravel application via Orchestra Testbench...\n";

// Boot a dummy Laravel application using Orchestra Testbench's default skeleton
$app = \Orchestra\Testbench\Foundation\Application::create();

// Register the package's service provider
$app->register(ErrorMailerServiceProvider::class);

// Configure the mailer to use SMTP pointing to Mailpit (Docker)
Config::set('mail.default', 'smtp');
Config::set('mail.mailers.smtp', [
    'transport' => 'smtp',
    'url' => env('MAIL_URL'),
    'host' => '127.0.0.1',
    'port' => 11025,
    'encryption' => env('MAIL_ENCRYPTION', 'tls'),
    'username' => env('MAIL_USERNAME'),
    'password' => env('MAIL_PASSWORD'),
    'timeout' => null,
    'local_domain' => env('MAIL_EHLO_DOMAIN'),
]);

Config::set('error-mailer.enabled', true);
Config::set('error-mailer.to', 'admin@example.com');
Config::set('error-mailer.from', 'error-mailer@example.com');
Config::set('error-mailer.subject', 'Test Exception for Mailpit');

// Throw a fake exception to test the mailer
$exception = new \Exception('This is a test exception generated to be caught by Mailpit via Docker.', 500);

echo "Sending error email to Mailpit (127.0.0.1:11025)...\n";

try {
    // Handle the exception via the package
    $errorMailer = app(\Artryazanov\ErrorMailer\ErrorMailer::class);
    $errorMailer->handle($exception);
    
    echo "✅ Success! The email was sent to Mailpit.\n";
    echo "🌐 Open http://localhost:18025 in your browser to view the beautiful HTML email.\n";
} catch (\Throwable $e) {
    echo "❌ Failed to send email. Is Mailpit running? (Run: docker compose up -d)\n";
    echo "Error Details: " . $e->getMessage() . "\n";
}
