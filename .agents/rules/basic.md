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
*   **Running Tests:** Use `composer test` or `./vendor/bin/phpunit`. To run with coverage, use `./vendor/bin/phpunit --coverage-text` (ensure PCOV or Xdebug is enabled in your PHP environment).

## Architecture & Email Design
*   **Email Templates:** Keep email templates safe for strict email clients. Use inline CSS and avoid JavaScript or external stylesheets. When using interactive HTML elements (like `<details>`/`<summary>`), ensure they degrade gracefully in clients that do not support them (e.g., displaying expanded by default).
*   **Dependencies:** Minimize external dependencies to keep the package lightweight.
*   **Configuration:** Provide sensible defaults in the configuration file (`config/error-mailer.php`) and document all ENV variables.
*   **Consistency:** Keep the structure, order of information, and presented data consistent between the HTML email view (`exception.blade.php`) and the Markdown representation (`generateMarkdown()`). Note that the Markdown representation is considered a "Short" version and explicitly omits verbose blocks (like `HEADER`, `COOKIE`, and `$_SERVER`) to remain compact.
