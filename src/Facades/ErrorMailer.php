<?php

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
     *
     * @return string
     */
    protected static function getFacadeAccessor()
    {
        return 'error-mailer';
    }
}
