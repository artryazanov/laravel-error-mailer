<?php

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
    | This is the subject of the exception email. A hash of the exception message
    | will be appended to this subject.
    |
    */

    'subject' => env('ERROR_MAILER_SUBJECT', 'Error on '.env('APP_ENV', 'production')),

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
    | Rate Limiting
    |--------------------------------------------------------------------------
    |
    | Configure how many exception emails can be sent per time window to
    | prevent spamming your inbox.
    | limit: Maximum number of emails.
    | window: Time window in seconds.
    |
    */

    'rate_limit' => [
        'limit' => (int) env('ERROR_MAILER_LIMIT', 15),
        'window' => (int) env('ERROR_MAILER_WINDOW', 3600), // 1 hour by default
    ],

];
