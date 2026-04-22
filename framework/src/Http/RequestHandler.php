<?php

/**
 * fnlla
 * (c) TechAyo.co.uk
 * Proprietary License
 */

declare(strict_types=1);

namespace Fnlla\Http;

use Fnlla\Support\Psr\Http\Message\ResponseInterface;
use Fnlla\Support\Psr\Http\Message\ServerRequestInterface;
use Fnlla\Support\Psr\Http\Server\RequestHandlerInterface;

/**
 * @api
 */
final class RequestHandler implements RequestHandlerInterface
{
    /** @var callable */
    private $handler;

    public function __construct(callable $handler)
    {
        $this->handler = $handler;
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $callable = $this->handler;
        $result = $callable($request);
        if ($result instanceof Response) {
            return $result;
        }
        if ($result instanceof ResponseInterface) {
            return $result;
        }
        return Response::html((string) $result);
    }
}







