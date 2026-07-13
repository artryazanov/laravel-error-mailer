# Laravel Error Mailer Package Rules

## Tech Stack
*   **Type:** Laravel Package
*   **PHP Version:** 8.2+
*   **Framework Version:** Laravel 11.x - 13.x

## Code Style & Standards
*   **Conventions:** Follow standard PSR-12 and Laravel package conventions.
*   **Typing:** Use strict typing where possible (`declare(strict_types=1);` and native PHP type hints/return types).
*   **Language:** Keep all code, comments, and variables in English.

## Testing
*   **Framework:** PHPUnit.
*   **Coverage:** Aim for 100% test coverage. Every new feature or bugfix must include corresponding tests.
*   **Running Tests:** Use `./vendor/bin/phpunit` or `composer test`.

## Architecture & Email Design
*   **Email Templates:** Keep email templates safe for strict email clients. Use inline CSS and HTML tables where necessary, and avoid JavaScript or external stylesheets.
*   **Dependencies:** Minimize external dependencies to keep the package lightweight.
*   **Configuration:** Provide sensible defaults in the configuration file (`config/error-mailer.php`) and document all ENV variables.
