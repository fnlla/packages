<?php
/**
 * fnlla
 * (c) TechAyo.co.uk
 * Proprietary License
 */
declare(strict_types=1);

namespace Fnlla\Support\Psr\Http\Message;

interface ServerRequestInterface extends RequestInterface
{
    public function getServerParams(): array;

    public function getCookieParams(): array;

    public function withCookieParams(array $cookies): self;

    public function getQueryParams(): array;

    public function withQueryParams(array $query): self;

    public function getUploadedFiles(): array;

    public function withUploadedFiles(array $uploadedFiles): self;

    public function getParsedBody(): mixed;

    public function withParsedBody(mixed $data): self;

    public function getAttributes(): array;

    public function getAttribute(string $name, mixed $default = null): mixed;

    public function withAttribute(string $name, mixed $value): self;

    public function withoutAttribute(string $name): self;
}




