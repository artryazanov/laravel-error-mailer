<?php

declare(strict_types=1);

namespace Artryazanov\ErrorMailer\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @method static void handle(\Throwable $exception)
 *
 * @see \Artryazanov\ErrorMailer\ErrorMailer
 */
class ErrorMailer extends Facade
{
    /**
     * Get the registered name of the component.
     */
    protected static function getFacadeAccessor(): string
    {
        return 'error-mailer';
    }
}
