<?php

declare(strict_types=1);
/**
 * NotFoundException — thrown by Container::get() when no factory
 * is registered for the requested ID.
 *
 * v4.5.3: removed `implements \Psr\Container\NotFoundExceptionInterface`
 * to eliminate the PSR-11 interface load-order fragility that caused
 * repeated fatal errors in v4.5.0–v4.5.2. The container still behaves
 * identically — only the formal PSR-11 compliance marker is gone.
 *
 * @package Linked3
 * @subpackage Includes
 */

namespace Linked3\Includes;

if (!defined('ABSPATH')) {
    exit;
}

class NotFoundException extends \Exception
{
}
