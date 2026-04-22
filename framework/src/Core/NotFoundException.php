<?php
/**
 * fnlla
 * (c) TechAyo.co.uk
 * Proprietary License
 */
declare(strict_types=1);

namespace Fnlla\Core;

use Fnlla\Support\Psr\Container\NotFoundExceptionInterface;
use RuntimeException;

/**
 * Container entry not found.
 *
 * @api
 */
final class NotFoundException extends RuntimeException implements NotFoundExceptionInterface
{
}






