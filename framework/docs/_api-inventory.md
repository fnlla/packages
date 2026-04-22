**fnlla (finella) API INVENTORY**

> Generated from `framework/src` (public methods + global helpers).
> Some items listed here are optional utilities and may be moved to packages over time.

**CORE**
**-** `Fnlla\\Core\Application` (`src\Core\Application.php`)
public: __construct, basePath, bootProviders, config, configRepository, registerProvider, registerProviders, version
**-** `Fnlla\\Core\ConfigRepository` (`src\Core\ConfigRepository.php`)
public: __construct, all, forget, fromDirectory, fromRoot, get, resolveAppRoot, set
**-** `Fnlla\\Core\ConfigValidator` (`src\Core\ConfigValidator.php`)
public: assertValid, validate
**-** `Fnlla\\Core\Container` (`src\Core\Container.php`)
public: bind, call, configRepository, get, has, instance, make, registerResetter, reset, resetters, scoped, scopedInstance, singleton
**-** `Fnlla\\Core\ContainerException` (`src\Core\ContainerException.php`)
**-** `Fnlla\\Core\Controller` (`src\Core\Controller.php`)
public: __construct
**-** `Fnlla\\Core\ExceptionHandler` (`src\Core\ExceptionHandler.php`)
public: __construct, handleError, handleException, handleShutdown, register, render, report
**-** `Fnlla\\Core\NotFoundException` (`src\Core\NotFoundException.php`)
**-** `Fnlla\\Core\ServiceProvider` (`src\Core\ServiceProvider.php`)
public: __construct, boot, register

**HTTP**
**-** `Fnlla\\Contracts\Http\KernelInterface` (`src\Contracts\Http\KernelInterface.php`)
public: handle
**-** `Fnlla\\Http\HttpFactory` (`src\Http\HttpFactory.php`)
public: createRequest, createResponse, createServerRequest, createStream, createStreamFromFile, createStreamFromResource, createUploadedFile, createUri
**-** `Fnlla\\Http\HttpKernel` (`src\Http\HttpKernel.php`)
public: __construct, boot, handle, isBooted
**-** `Fnlla\\Http\RouteCacheCompiler` (`src\Http\RouteCacheCompiler.php`)
public: compile
**-** `Fnlla\\Http\Middleware\AuthMiddleware` (`src\Http\Middleware\AuthMiddleware.php`)
public: __construct, __invoke, process
**-** `Fnlla\\Http\Middleware\CookieMiddleware` (`src\Http\Middleware\CookieMiddleware.php`)
public: __construct, __invoke, process
**-** `Fnlla\\Http\Middleware\CsrfMiddleware` (`src\Http\Middleware\CsrfMiddleware.php`)
public: __construct, __invoke, process
**-** `Fnlla\\Http\Middleware\RateLimitMiddleware` (`src\Http\Middleware\RateLimitMiddleware.php`)
public: __construct, __invoke, process
**-** `Fnlla\\Http\Middleware\RequestLoggerMiddleware` (`src\Http\Middleware\RequestLoggerMiddleware.php`)
public: __construct, __invoke, process
**-** `Fnlla\\Http\Middleware\SecurityHeadersMiddleware` (`src\Http\Middleware\SecurityHeadersMiddleware.php`)
public: __construct, __invoke, process
**-** `Fnlla\\Http\Middleware\SessionMiddleware` (`src\Http\Middleware\SessionMiddleware.php`)
public: __construct, __invoke, process
**-** `Fnlla\\Http\Middleware\TrustedProxyMiddleware` (`src\Http\Middleware\TrustedProxyMiddleware.php`)
public: __invoke, process
**-** `Fnlla\\Http\Request` (`src\Http\Request.php`)
public: __construct, all, allInput, clientIp, file, fromGlobals, fromPsr, getAttribute, getAttributes, getBody, getCookieParams, getHeader, getHeaderLine, getHeaders, getMethod, getParsedBody, getProtocolVersion, getQueryParams, getRequestTarget, getServerParams, getUploadedFiles, getUri, hasHeader, header, input, isSecure, validate, wantsJson, withAddedHeader, withAttribute, withBody, withCookieParams, withHeader, withMethod, withParams, withParsedBody, withProtocolVersion, withQueryParams, withRequestTarget, withUploadedFiles, withUri, withoutAttribute, withoutHeader
**-** `Fnlla\\Http\RequestHandler` (`src\Http\RequestHandler.php`)
public: __construct, handle
**-** `Fnlla\\Http\Response` (`src\Http\Response.php`)
public: __construct, download, file, getBody, getHeader, getHeaderLine, getHeaders, getProtocolVersion, getReasonPhrase, getStatusCode, hasHeader, html, json, redirect, send, stream, text, withAddedHeader, withBasePath, withBody, withHeader, withHeaders, withProtocolVersion, withStatus, withoutHeader, xml
**-** `Fnlla\\Http\Router` (`src\Http\Router.php`)
public: __construct, add, cacheIssues, dispatch, get, group, middlewareGroup, post, use
**-** `Fnlla\\Http\Stream` (`src\Http\Stream.php`)
public: __construct, __toString, close, detach, eof, fromString, getContents, getMetadata, getSize, hasCallback, invokeCallback, isReadable, isSeekable, isWritable, read, rewind, seek, tell, withCallback, write
**-** `Fnlla\\Http\UploadedFile` (`src\Http\UploadedFile.php`)
public: __construct, extension, getClientFilename, getClientMediaType, getError, getSize, getStream, isValid, moveTo, store
**-** `Fnlla\\Http\Uri` (`src\Http\Uri.php`)
public: __construct, __toString, getAuthority, getFragment, getHost, getPath, getPort, getQuery, getScheme, getUserInfo, withFragment, withHost, withPath, withPort, withQuery, withScheme, withUserInfo

**VIEW**
**-** `Fnlla\\View\View` (`src\View\View.php`)
public: hasShared, render, share

**SECURITY**
**-** `Fnlla\\Security\CsrfTokenManager` (`src\Security\CsrfTokenManager.php`)
public: __construct, token, validate

**EXTENSIONS**
**-** `Fnlla\\Contracts\Support\ServiceProviderInterface` (`src\Contracts\Support\ServiceProviderInterface.php`)
public: boot, manifest, register
**-** `Fnlla\\Support\ArrayCacheStore` (`src\Support\ArrayCacheStore.php`)
**-** `Fnlla\\Support\ArrayStore` (`src\Support\ArrayStore.php`)
public: clear, forget, get, put
**-** `Fnlla\\Support\Auth\AuthManager` (`src\Support\Auth\AuthManager.php`)
public: __construct, guard, user
**-** `Fnlla\\Support\Auth\AuthServiceProvider` (`src\Support\Auth\AuthServiceProvider.php`)
public: register
**-** `Fnlla\\Support\Auth\CallableUserProvider` (`src\Support\Auth\CallableUserProvider.php`)
public: __construct, retrieveById, retrieveByToken
**-** `Fnlla\\Support\Auth\SessionGuard` (`src\Support\Auth\SessionGuard.php`)
public: __construct, check, id, login, logout, user
**-** `Fnlla\\Support\Auth\TokenGuard` (`src\Support\Auth\TokenGuard.php`)
public: __construct, check, user
**-** `Fnlla\\Support\Auth\UserProviderInterface` (`src\Support\Auth\UserProviderInterface.php`)
public: retrieveById, retrieveByToken
**-** `Fnlla\\Support\Cache` (`src\Support\Cache.php`)
public: __construct, clear, forget, get, put, remember, store
**-** `Fnlla\\Support\CacheItem` (`src\Support\CacheItem.php`)
public: __construct, expirationTimestamp, expiresAfter, expiresAt, get, getKey, isHit, markHit, set
**-** `Fnlla\\Support\CacheItemPool` (`src\Support\CacheItemPool.php`)
public: __construct, clear, commit, deleteItem, deleteItems, getItem, getItems, hasItem, save, saveDeferred
**-** `Fnlla\\Support\CacheServiceProvider` (`src\Support\CacheServiceProvider.php`)
public: register
**-** `Fnlla\\Support\CacheStoreInterface` (`src\Support\CacheStoreInterface.php`)
**-** `Fnlla\\Support\ComposerProviderDiscovery` (`src\Support\ComposerProviderDiscovery.php`)
public: discover
**-** `Fnlla\\Support\Cookie` (`src\Support\Cookie.php`)
public: __construct, toHeader
**-** `Fnlla\\Support\CookieJar` (`src\Support\CookieJar.php`)
public: attachToResponse, make, queue
**-** `Fnlla\\Support\CookieServiceProvider` (`src\Support\CookieServiceProvider.php`)
public: register
**-** `Fnlla\\Support\EventDispatcher` (`src\Support\EventDispatcher.php`)
public: __construct, dispatch, listen
**-** `Fnlla\\Support\EventsServiceProvider` (`src\Support\EventsServiceProvider.php`)
public: register
**-** `Fnlla\\Support\FileCacheStore` (`src\Support\FileCacheStore.php`)
**-** `Fnlla\\Support\FileStore` (`src\Support\FileStore.php`)
public: __construct, clear, forget, get, put
**-** `Fnlla\\Support\HealthChecker` (`src\Support\HealthChecker.php`)
public: __construct, fromConfig, run
**-** `Fnlla\\Support\Logger` (`src\Support\Logger.php`)
public: __construct, alert, critical, debug, emergency, error, info, log, notice, warning
**-** `Fnlla\\Support\LogServiceProvider` (`src\Support\LogServiceProvider.php`)
public: register
**-** `Fnlla\\Support\ProviderCache` (`src\Support\ProviderCache.php`)
public: write
**-** `Fnlla\\Support\ProviderCapability` (`src\Support\ProviderCapability.php`)
**-** `Fnlla\\Support\ProviderManifest` (`src\Support\ProviderManifest.php`)
public: __construct
**-** `Fnlla\\Support\ProviderReport` (`src\Support\ProviderReport.php`)
public: addEntry, toArray, toText
**-** `Fnlla\\Support\ProviderRepository` (`src\Support\ProviderRepository.php`)
public: __construct, add, bootAll, registerAll
**-** `Fnlla\\Support\ProviderValidator` (`src\Support\ProviderValidator.php`)
public: validate
**-** `Fnlla\\Support\Psr\Cache\CacheItemInterface` (`src\Support\Psr\Cache\CacheItemInterface.php`)
public: expiresAfter, expiresAt, get, getKey, isHit, set
**-** `Fnlla\\Support\Psr\Cache\CacheItemPoolInterface` (`src\Support\Psr\Cache\CacheItemPoolInterface.php`)
public: clear, commit, deleteItem, deleteItems, getItem, getItems, hasItem, save, saveDeferred
**-** `Fnlla\\Support\Psr\Cache\InvalidArgumentException` (`src\Support\Psr\Cache\InvalidArgumentException.php`)
**-** `Fnlla\\Support\Psr\Container\ContainerExceptionInterface` (`src\Support\Psr\Container\ContainerExceptionInterface.php`)
**-** `Fnlla\\Support\Psr\Container\ContainerInterface` (`src\Support\Psr\Container\ContainerInterface.php`)
public: get, has
**-** `Fnlla\\Support\Psr\Container\NotFoundExceptionInterface` (`src\Support\Psr\Container\NotFoundExceptionInterface.php`)
**-** `Fnlla\\Support\Psr\Http\Factory\RequestFactoryInterface` (`src\Support\Psr\Http\Factory\RequestFactoryInterface.php`)
public: createRequest
**-** `Fnlla\\Support\Psr\Http\Factory\ResponseFactoryInterface` (`src\Support\Psr\Http\Factory\ResponseFactoryInterface.php`)
public: createResponse
**-** `Fnlla\\Support\Psr\Http\Factory\ServerRequestFactoryInterface` (`src\Support\Psr\Http\Factory\ServerRequestFactoryInterface.php`)
public: createServerRequest
**-** `Fnlla\\Support\Psr\Http\Factory\StreamFactoryInterface` (`src\Support\Psr\Http\Factory\StreamFactoryInterface.php`)
public: createStream, createStreamFromFile, createStreamFromResource
**-** `Fnlla\\Support\Psr\Http\Factory\UploadedFileFactoryInterface` (`src\Support\Psr\Http\Factory\UploadedFileFactoryInterface.php`)
public: createUploadedFile
**-** `Fnlla\\Support\Psr\Http\Factory\UriFactoryInterface` (`src\Support\Psr\Http\Factory\UriFactoryInterface.php`)
public: createUri
**-** `Fnlla\\Support\Psr\Http\Message\MessageInterface` (`src\Support\Psr\Http\Message\MessageInterface.php`)
public: getBody, getHeader, getHeaderLine, getHeaders, getProtocolVersion, hasHeader, withAddedHeader, withBody, withHeader, withProtocolVersion, withoutHeader
**-** `Fnlla\\Support\Psr\Http\Message\RequestInterface` (`src\Support\Psr\Http\Message\RequestInterface.php`)
public: getMethod, getRequestTarget, getUri, withMethod, withRequestTarget, withUri
**-** `Fnlla\\Support\Psr\Http\Message\ResponseInterface` (`src\Support\Psr\Http\Message\ResponseInterface.php`)
public: getReasonPhrase, getStatusCode, withStatus
**-** `Fnlla\\Support\Psr\Http\Message\ServerRequestInterface` (`src\Support\Psr\Http\Message\ServerRequestInterface.php`)
public: getAttribute, getAttributes, getCookieParams, getParsedBody, getQueryParams, getServerParams, getUploadedFiles, withAttribute, withCookieParams, withParsedBody, withQueryParams, withUploadedFiles, withoutAttribute
**-** `Fnlla\\Support\Psr\Http\Message\StreamInterface` (`src\Support\Psr\Http\Message\StreamInterface.php`)
public: __toString, close, detach, eof, getContents, getMetadata, getSize, isReadable, isSeekable, isWritable, read, rewind, seek, tell, write
**-** `Fnlla\\Support\Psr\Http\Message\UploadedFileInterface` (`src\Support\Psr\Http\Message\UploadedFileInterface.php`)
public: getClientFilename, getClientMediaType, getError, getSize, getStream, moveTo
**-** `Fnlla\\Support\Psr\Http\Message\UriInterface` (`src\Support\Psr\Http\Message\UriInterface.php`)
public: __toString, getAuthority, getFragment, getHost, getPath, getPort, getQuery, getScheme, getUserInfo, withFragment, withHost, withPath, withPort, withQuery, withScheme, withUserInfo
**-** `Fnlla\\Support\Psr\Http\Server\MiddlewareInterface` (`src\Support\Psr\Http\Server\MiddlewareInterface.php`)
public: process
**-** `Fnlla\\Support\Psr\Http\Server\RequestHandlerInterface` (`src\Support\Psr\Http\Server\RequestHandlerInterface.php`)
public: handle
**-** `Fnlla\\Support\Psr\Log\LoggerInterface` (`src\Support\Psr\Log\LoggerInterface.php`)
public: alert, critical, debug, emergency, error, info, log, notice, warning
**-** `Fnlla\\Support\Psr\Log\LogLevel` (`src\Support\Psr\Log\LogLevel.php`)
**-** `Fnlla\\Support\Psr\SimpleCache\CacheInterface` (`src\Support\Psr\SimpleCache\CacheInterface.php`)
public: clear, delete, deleteMultiple, get, getMultiple, has, set, setMultiple
**-** `Fnlla\\Support\Psr\SimpleCache\InvalidArgumentException` (`src\Support\Psr\SimpleCache\InvalidArgumentException.php`)
**-** `Fnlla\\Support\Queue` (`src\Support\Queue.php`)
**-** `Fnlla\\Support\QueueServiceProvider` (`src\Support\QueueServiceProvider.php`)
public: register
**-** `Fnlla\\Support\RateLimiter` (`src\Support\RateLimiter.php`)
public: __construct, attempt, remaining
**-** `Fnlla\\Support\RateLimitServiceProvider` (`src\Support\RateLimitServiceProvider.php`)
public: register
**-** `Fnlla\\Support\ServiceProvider` (`src\Support\ServiceProvider.php`)
public: __construct, boot, manifest, register
**-** `Fnlla\\Support\SessionInterface` (`src\Support\SessionInterface.php`)
public: forget, get, put
**-** `Fnlla\\Support\SessionManager` (`src\Support\SessionManager.php`)
public: __construct, all, flash, forget, get, getFlash, put, regenerate, start
**-** `Fnlla\\Support\SessionServiceProvider` (`src\Support\SessionServiceProvider.php`)
public: register
**-** `Fnlla\\Support\SimpleCacheAdapter` (`src\Support\SimpleCacheAdapter.php`)
public: __construct, clear, delete, deleteMultiple, get, getMultiple, has, set, setMultiple
**-** `Fnlla\\Support\SyncQueue` (`src\Support\SyncQueue.php`)
public: __construct, push
**-** `Fnlla\\Support\ValidationException` (`src\Support\ValidationException.php`)
public: __construct, errors, status
**-** `Fnlla\\Support\Validator` (`src\Support\Validator.php`)
public: __construct, errors, make, passes, validated

**OTHER**
**-** `Fnlla\\Contracts\Cache\CacheStoreInterface` (`src\Contracts\Cache\CacheStoreInterface.php`)
public: clear, forget, get, put
**-** `Fnlla\\Contracts\Events\EventDispatcherInterface` (`src\Contracts\Events\EventDispatcherInterface.php`)
public: dispatch, listen
**-** `Fnlla\\Contracts\Log\LoggerInterface` (`src\Contracts\Log\LoggerInterface.php`)
**-** `Fnlla\\Contracts\Queue\JobInterface` (`src\Contracts\Queue\JobInterface.php`)
public: handle
**-** `Fnlla\\Contracts\Queue\QueueInterface` (`src\Contracts\Queue\QueueInterface.php`)
public: push
**-** `Fnlla\\Contracts\Runtime\RuntimeInterface` (`src\Contracts\Runtime\RuntimeInterface.php`)
public: run
**-** `Fnlla\\Database\Database` (`src\Database\Database.php`)
public: pdo
**-** `Fnlla\\Database\DatabaseManager` (`src\Database\DatabaseManager.php`)
public: __construct, connection, table
**-** `Fnlla\\Database\DatabaseServiceProvider` (`src\Database\DatabaseServiceProvider.php`)
public: register
**-** `Fnlla\\Database\IdentifierGuard` (`src\Database\IdentifierGuard.php`)
public: assertAllowed
**-** `Fnlla\\Database\MigrationInterface` (`src\Database\MigrationInterface.php`)
public: down, up
**-** `Fnlla\\Database\Migrator` (`src\Database\Migrator.php`)
public: __construct, rollback, run, status
**-** `Fnlla\\Database\MySqlQuoter` (`src\Database\MySqlQuoter.php`)
public: quote
**-** `Fnlla\\Database\OperatorGuard` (`src\Database\OperatorGuard.php`)
public: assertAllowed
**-** `Fnlla\\Database\QueryBuilder` (`src\Database\QueryBuilder.php`)
public: __construct, delete, first, get, groupBy, insert, limit, offset, orWhere, orderBy, select, toSql, update, where
**-** `Fnlla\\Database\QuoterInterface` (`src\Database\QuoterInterface.php`)
public: quote
**-** `Fnlla\\Plugin\PluginInterface` (`src\Plugin\PluginInterface.php`)
public: register
**-** `Fnlla\\Plugin\PluginManager` (`src\Plugin\PluginManager.php`)
public: __construct, all, app, boot, config, load
**-** `Fnlla\\Runtime\FpmRuntime` (`src\Runtime\FpmRuntime.php`)
public: run
**-** `Fnlla\\Runtime\RequestContext` (`src\Runtime\RequestContext.php`)
public: __construct, begin, cspNonce, current, end, includeRequestIdHeader, includeSpanIdHeader, includeTraceIdHeader, locale, requestId, setCspNonce, setHeaderFlags, setLocale, spanId, startedAt, traceId
**-** `Fnlla\\Runtime\ResetManager` (`src\Runtime\ResetManager.php`)
public: register, reset
**-** `Fnlla\\Runtime\Resetter` (`src\Runtime\Resetter.php`)
public: reset
**-** `Fnlla\\Runtime\RoadRunnerRuntime` (`src\Runtime\RoadRunnerRuntime.php`)
public: __construct, run

**HELPERS (GLOBAL)**
**-** app, view
