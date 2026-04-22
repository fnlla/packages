<?php

/**
 * fnlla
 * (c) TechAyo.co.uk
 * Proprietary License
 */

declare(strict_types=1);

namespace Fnlla\Http;

use Fnlla\Http\Request;
use Fnlla\Http\Response;
use Fnlla\Http\Stream;
use Fnlla\Http\Uri;
use Fnlla\Http\UploadedFile;
use Fnlla\Support\Psr\Http\Factory\RequestFactoryInterface;
use Fnlla\Support\Psr\Http\Factory\ResponseFactoryInterface;
use Fnlla\Support\Psr\Http\Factory\ServerRequestFactoryInterface;
use Fnlla\Support\Psr\Http\Factory\StreamFactoryInterface;
use Fnlla\Support\Psr\Http\Factory\UriFactoryInterface;
use Fnlla\Support\Psr\Http\Factory\UploadedFileFactoryInterface;
use Fnlla\Support\Psr\Http\Message\RequestInterface;
use Fnlla\Support\Psr\Http\Message\ResponseInterface;
use Fnlla\Support\Psr\Http\Message\ServerRequestInterface;
use Fnlla\Support\Psr\Http\Message\StreamInterface;
use Fnlla\Support\Psr\Http\Message\UriInterface;
use Fnlla\Support\Psr\Http\Message\UploadedFileInterface;
use RuntimeException;

/**
 * @api
 */
final class HttpFactory implements
    RequestFactoryInterface,
    ServerRequestFactoryInterface,
    ResponseFactoryInterface,
    StreamFactoryInterface,
    UriFactoryInterface,
    UploadedFileFactoryInterface
{
    /**
     * @param UriInterface|string $uri
     */
    public function createRequest(string $method, mixed $uri): RequestInterface
    {
        $uri = $uri instanceof UriInterface ? $uri : new Uri((string) $uri);
        return new Request($method, $uri);
    }

    /**
     * @param UriInterface|string $uri
     */
    public function createServerRequest(string $method, mixed $uri, array $serverParams = []): ServerRequestInterface
    {
        $uri = $uri instanceof UriInterface ? $uri : new Uri((string) $uri);
        return new Request($method, $uri, [], null, $serverParams);
    }

    public function createResponse(int $code = 200, string $reasonPhrase = ''): ResponseInterface
    {
        return new Response($code, [], null, $reasonPhrase);
    }

    public function createStream(string $content = ''): StreamInterface
    {
        return Stream::fromString($content);
    }

    public function createStreamFromFile(string $filename, string $mode = 'r'): StreamInterface
    {
        $resource = fopen($filename, $mode);
        if ($resource === false) {
            throw new RuntimeException('Unable to open stream from file.');
        }
        return new Stream($resource);
    }

    /**
     * @param resource $resource
     */
    public function createStreamFromResource(mixed $resource): StreamInterface
    {
        return new Stream($resource);
    }

    public function createUri(string $uri = ''): UriInterface
    {
        return new Uri($uri);
    }

    public function createUploadedFile(
        StreamInterface $stream,
        ?int $size = null,
        int $error = UPLOAD_ERR_OK,
        ?string $clientFilename = null,
        ?string $clientMediaType = null
    ): UploadedFileInterface {
        $tmp = [
            'tmp_name' => '',
            'size' => $size ?? $stream->getSize() ?? 0,
            'error' => $error,
            'name' => $clientFilename ?? '',
            'type' => $clientMediaType ?? '',
        ];
        $file = new UploadedFile($tmp, $stream);
        return $file;
    }
}







