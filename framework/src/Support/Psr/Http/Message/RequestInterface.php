<?php
/**
 * fnlla
 * (c) TechAyo.co.uk
 * Proprietary License
 */
declare(strict_types=1);

namespace Fnlla\Support\Psr\Http\Message;

interface RequestInterface extends MessageInterface
{
    public function getRequestTarget(): string;

    public function withRequestTarget(string $requestTarget): self;

    public function getMethod(): string;

    public function withMethod(string $method): self;

    public function getUri(): UriInterface;

    public function withUri(UriInterface $uri, bool $preserveHost = false): self;
}




