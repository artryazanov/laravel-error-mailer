# Laravel Error Mailer Package Rules

## Tech Stack
*   **Type:** Laravel Package
*   **PHP Version:** 8.2+
*   **Framework Version:** Laravel 11.x - 13.x

## Code Style, Linting & Static Analysis
*   **Conventions:** Follow standard PSR-12 / Laravel Pint conventions (`composer lint` to check, `composer format` to fix).
*   **Static Analysis:** Code must pass PHPStan / Larastan (`composer analyse`) with zero errors.
*   **Typing:** Use strict typing across all PHP files (`declare(strict_types=1);` and native PHP parameter/return type hints).
*   **Language:** Keep all code, comments, configuration files, and variables in English.

## Testing
*   **Framework:** PHPUnit with Orchestra Testbench.
*   **Coverage:** Maintain 100% test coverage across all classes, methods, and lines. Every new feature or bugfix must include corresponding unit/feature tests and template rendering assertions.
*   **Running Tests:** Use `composer test` (or `./vendor/bin/phpunit`). To run with coverage, use `composer test-coverage` (or `./vendor/bin/phpunit --coverage-text` with PCOV/Xdebug enabled).
*   **Email Previews:** When modifying email templates or generating test emails locally, use the provided Docker setup (Mailpit) via `docker compose up -d` and trigger it with `php preview-docker.php`. View results at `http://localhost:18025`.

## Architecture, Security & Email Design
*   **Fault Tolerance:** `ErrorMailer::handle()` must never throw uncaught exceptions (even when cache, database, or mail transport is down).
*   **Queue Serializability:** All data passed to `ExceptionOccurred` must consist of pure arrays and scalars (convert `UploadedFile` and other non-serializable objects before queuing).
*   **Sensitive Data Masking:** Never expose raw passwords, tokens, API keys, authorization headers, cookies, or secret environment variables (`*_PASSWORD`, `*_SECRET`, `*_KEY`, `*_TOKEN`) in emails. Always sanitize context data via `hidden_parameters`.
*   **Email Templates:** Keep email templates safe for strict email clients. Avoid JavaScript or external stylesheets, and use `class_basename()` for cross-platform namespace handling.
*   **Dependencies:** Minimize external dependencies to keep the package lightweight.
*   **Configuration:** Provide sensible defaults in the configuration file (`config/error-mailer.php`) and document all ENV variables in `README.md`.
