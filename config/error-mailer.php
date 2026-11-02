<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Exception Email Enabled
    |--------------------------------------------------------------------------
    |
    | Enable/Disable exception email notifications.
    |
    */

    'enabled' => env('ERROR_MAILER_ENABLED', true),

    /*
    |--------------------------------------------------------------------------
    | Exception Email From
    |--------------------------------------------------------------------------
    |
    | This is the email your exception notification will be sent from.
    |
    */

    'from' => env('ERROR_MAILER_FROM', 'error-mailer@example.com'),

    /*
    |--------------------------------------------------------------------------
    | Exception Email To
    |--------------------------------------------------------------------------
    |
    | This is the email(s) the exceptions will be emailed to.
    | Can be a comma-separated string in the .env file.
    |
    */

    'to' => env('ERROR_MAILER_TO', 'admin@example.com'),

    /*
    |--------------------------------------------------------------------------
    | Exception Email CC
    |--------------------------------------------------------------------------
    |
    | This is the email(s) the exceptions will be CC emailed to.
    |
    */

    'cc' => env('ERROR_MAILER_CC', ''),

    /*
    |--------------------------------------------------------------------------
    | Exception Email BCC
    |--------------------------------------------------------------------------
    |
    | This is the email(s) the exceptions will be BCC emailed to.
    |
    */

    'bcc' => env('ERROR_MAILER_BCC', ''),

    /*
    |--------------------------------------------------------------------------
    | Exception Email Subject
    |--------------------------------------------------------------------------
    |
    | This is the subject of the exception email.
    |
    */

    'subject' => env('ERROR_MAILER_SUBJECT', 'Error on '.env('APP_NAME', 'Laravel')),

    /*
    |--------------------------------------------------------------------------
    | Exception Email Include Hash
    |--------------------------------------------------------------------------
    |
    | Whether to append a hash of the exception message to the subject.
    | Useful for some email clients to group similar errors.
    |
    */

    'include_hash_in_subject' => env('ERROR_MAILER_INCLUDE_HASH_IN_SUBJECT', false),

    /*
    |--------------------------------------------------------------------------
    | Exception Email View
    |--------------------------------------------------------------------------
    |
    | This is the view that will be used for the email. You can publish the views
    | and modify them, or provide your own custom view name here.
    |
    */

    'view' => env('ERROR_MAILER_VIEW', 'error-mailer::emails.exception'),

    /*
    |--------------------------------------------------------------------------
    | Exception Email Queue
    |--------------------------------------------------------------------------
    |
    | The queue name to which the exception email job should be dispatched.
    |
    */

    'queue' => env('ERROR_MAILER_QUEUE', 'default'),

    /*
    |--------------------------------------------------------------------------
    | Rate Limiting
    |--------------------------------------------------------------------------
    |
    | Configure how many exception emails can be sent per time window to
    | prevent spamming your inbox.
    | limit: Maximum number of emails (set to 0 to disable rate limiting).
    | window: Time window in seconds.
    |
    */

    'rate_limit' => [
        'limit' => (int) env('ERROR_MAILER_LIMIT', 15),
        'window' => (int) env('ERROR_MAILER_WINDOW', 3600), // 1 hour by default
    ],

    /*
    |--------------------------------------------------------------------------
    | Ignored Exceptions
    |--------------------------------------------------------------------------
    |
    | A list of the exception types that should not be emailed.
    |
    */

    'ignore' => [
        // \Symfony\Component\HttpKernel\Exception\NotFoundHttpException::class,
    ],

    /*
    |--------------------------------------------------------------------------
    | Maximum Stack Trace Frames
    |--------------------------------------------------------------------------
    |
    | Limit the number of stack trace frames captured per exception to prevent
    | oversized queue payloads during deep recursion errors.
    |
    */

    'trace_max_frames' => (int) env('ERROR_MAILER_TRACE_MAX_FRAMES', 100),

    /*
    |--------------------------------------------------------------------------
    | Hidden / Redacted Parameters
    |--------------------------------------------------------------------------
    |
    | Keys listed here (as well as keys ending with _password, _secret, _key,
    | or _token) will have their values masked with "********" in request
    | payloads, headers, cookies, and $_SERVER context.
    |
    */

    'hidden_parameters' => [
        'password',
        'password_confirmation',
        'current_password',
        'secret',
        'token',
        'api_key',
        'access_token',
        'refresh_token',
        'authorization',
        'proxy-authorization',
        'cookie',
        'set-cookie',
        'x-api-key',
        'x-csrf-token',
        'x-xsrf-token',
        'php-auth-pw',
        'app_key',
        'credit_card',
        'card_number',
        'cvv',
    ],

];
