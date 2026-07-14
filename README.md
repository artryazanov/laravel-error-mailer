# 📧 Laravel Error Mailer

[![Latest Version on Packagist](https://img.shields.io/packagist/v/artryazanov/laravel-error-mailer.svg?style=flat-square)](https://packagist.org/packages/artryazanov/laravel-error-mailer)
[![Tests](https://img.shields.io/github/actions/workflow/status/artryazanov/laravel-error-mailer/tests.yml?branch=main&label=tests&style=flat-square)](https://github.com/artryazanov/laravel-error-mailer/actions/workflows/tests.yml)
[![Lint](https://img.shields.io/github/actions/workflow/status/artryazanov/laravel-error-mailer/lint.yml?branch=main&label=lint&style=flat-square)](https://github.com/artryazanov/laravel-error-mailer/actions/workflows/lint.yml)
[![Codecov](https://img.shields.io/codecov/c/github/artryazanov/laravel-error-mailer.svg?style=flat-square)](https://codecov.io/gh/artryazanov/laravel-error-mailer)
[![Total Downloads](https://img.shields.io/packagist/dt/artryazanov/laravel-error-mailer.svg?style=flat-square)](https://packagist.org/packages/artryazanov/laravel-error-mailer)
[![License](https://img.shields.io/github/license/artryazanov/laravel-error-mailer.svg?style=flat-square)](https://github.com/artryazanov/laravel-error-mailer/blob/main/LICENSE)

Laravel 11-13 package to automatically send detailed exception reports to configured email addresses. Features a clean HTML template that includes your application's name and environment, full stack traces, comprehensive context data, and built-in rate limiting to prevent spamming your inbox when your application throws repeated errors.

## ✨ Features
- **Clean HTML Template:** A clear and readable layout for error reporting.
- **Comprehensive Context Capture:**
  - **Web Requests:** URL, Method, User info, Request Body, Headers, Cookies, and `$_SERVER` array.
  - **Console Commands (CLI):** Command arguments and `$_SERVER` array.
- **Rate Limiting:** Protects your inbox from spam when a recurring error floods your application.
- **Smart Email Subjects:** The error message is automatically appended to the email subject line (and truncated if too long), making it easy to identify the error at a glance. An optional short hash can be enabled to group similar errors.
- **Previous Exceptions:** Automatically unwraps and displays previous exceptions in the chain.
## 📋 Requirements
- PHP 8.2+
- Laravel 11.x–13.x

## 📦 Installation

You can install the package via composer:

```bash
composer require artryazanov/laravel-error-mailer
```

Laravel auto-discovers the service provider. No manual registration is needed.

## ⚙️ Configuration

Publish the configuration and view files to customize them:

```bash
php artisan vendor:publish --tag="error-mailer-config"
php artisan vendor:publish --tag="error-mailer-views"
```

Configuration file: `config/error-mailer.php`

In your `.env` file, you can set the following variables:

```env
ERROR_MAILER_ENABLED=true
ERROR_MAILER_FROM=error-mailer@example.com
ERROR_MAILER_TO=admin@example.com
ERROR_MAILER_CC=
ERROR_MAILER_BCC=
# Optional: Override the default subject "Error on {APP_NAME} ({APP_ENV})"
# The exact error message is automatically appended to the subject in either case.
ERROR_MAILER_SUBJECT="Error on production"

# Optional: Append a hash of the exception message to the subject (useful for email clients grouping). Defaults to false.
ERROR_MAILER_INCLUDE_HASH_IN_SUBJECT=false

# Rate limit: Max emails to send per time window (in seconds)
ERROR_MAILER_LIMIT=15
ERROR_MAILER_WINDOW=3600
```

## 💻 Usage

### Laravel 11+
In newer Laravel versions, exception handling is configured in `bootstrap/app.php`. Add the explicit reporting callback inside your `withExceptions` method:

```php
use Artryazanov\ErrorMailer\Facades\ErrorMailer;
use Illuminate\Foundation\Configuration\Exceptions;
use Throwable;

return Application::configure(basePath: dirname(__DIR__))
    // ...
    ->withExceptions(function (Exceptions $exceptions) {
        $exceptions->reportable(function (Throwable $e) {
            ErrorMailer::handle($e);
        });
    })->create();
```

### Older Laravel Versions (or custom Handlers)
If you are using `app/Exceptions/Handler.php`, you can call the facade in your `register` method:

```php
use Artryazanov\ErrorMailer\Facades\ErrorMailer;
use Throwable;

public function register(): void
{
    $this->reportable(function (Throwable $e) {
        ErrorMailer::handle($e);
    });
}
```

## 🛡️ Rate Limiting

If your application loses connection to a database or a critical service goes down, it might throw hundreds of exceptions per minute. 

This package has built-in Rate Limiting to protect your inbox. By default, it allows sending **15 emails per hour**. Any exceptions thrown beyond this limit will be suppressed from the mailer (but a standard Laravel `Log::warning` will be recorded stating that the email was suppressed). 

You can tweak the threshold in your `.env` using `ERROR_MAILER_LIMIT` and `ERROR_MAILER_WINDOW`.

## 🧪 Local Testing & Preview

If you want to see exactly how the generated emails look without deploying to a live server, this package includes a convenient Docker-based preview script using [Mailpit](https://mailpit.axllent.org/).

1. Start the Mailpit Docker container:
   ```bash
   docker compose up -d
   ```
2. Run the preview script to simulate an exception and send the email:
   ```bash
   php preview-docker.php
   ```
3. Open your browser and navigate to **http://localhost:18025** to view the rendered HTML email in Mailpit's beautiful web interface.

## 🎨 Customizing the View

If you published the views, you can find the HTML email template in `resources/views/vendor/error-mailer/emails/exception.blade.php`. You can modify it to match your company's branding, adjust the layout, or include additional variables.

## 📄 License

This package is released under the MIT License. See [LICENSE](LICENSE) for details.
