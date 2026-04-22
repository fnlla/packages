<?php
/**
 * fnlla
 * (c) TechAyo.co.uk
 * Proprietary License
 */
declare(strict_types=1);

namespace Fnlla\Core;

use Fnlla\Support\Psr\Container\ContainerExceptionInterface;
use RuntimeException;

/**
 * Container resolution error.
 *
 * @api
 */
final class ContainerException extends RuntimeException implements ContainerExceptionInterface
{
}






