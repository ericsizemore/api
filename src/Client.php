<?php

declare(strict_types=1);

/**
 * This file is part of Esi\Api.
 *
 * (c) Eric Sizemore <admin@secondversion.com>
 *
 * This source file is subject to the MIT license. For the full copyright and license
 * information, please view the LICENSE file that was distributed with this source code.
 */

namespace Esi\Api;

use Closure;
use DateTime;
use Esi\Api\Exceptions\RateLimitExceededException;
use Esi\Api\Traits\ParseJsonResponse;
use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\Exception\BadResponseException;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Exception\InvalidArgumentException;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\RetryMiddleware;
use Kevinrob\GuzzleCache\CacheMiddleware;
use Kevinrob\GuzzleCache\Storage\Psr6CacheStorage;
use Kevinrob\GuzzleCache\Strategy\PrivateCacheStrategy;
use Kevinrob\GuzzleCache\Strategy\PublicCacheStrategy;
use Psr\Http\Message\MessageInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use RuntimeException;
use Symfony\Component\Cache\Adapter\AbstractAdapter;
use Symfony\Component\OptionsResolver\Exception\ExceptionInterface;
use Symfony\Component\OptionsResolver\Options;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\Url;
use Symfony\Component\Validator\Validation;
use Throwable;

use function array_keys;
use function is_dir;
use function is_numeric;
use function is_writable;
use function ltrim;
use function range;
use function sprintf;
use function str_ends_with;
use function time;
use function trim;

/**
 * Essentially a wrapper, or base class of sorts, for building a Guzzle Client object.
 *
 * Note: Only designed for non-asynchronous, non-pool, and non-promise requests currently.
 *
 * @see Tests\ClientTest
 *
 * @phpstan-type ClientOptionsArray = array{
 *     apiUrl: string,
 *     apiKey: string,
 *     apiParamName?: string|null,
 *     apiRequiresQuery?: bool,
 *     cachePath?: string|null
 * }
 */
final class Client
{
    /**
     * Adds helper functions for handling API json responses.
     *
     * @see Esi\Api\Traits\ParseJsonResponse
     */
    use ParseJsonResponse;

    /**
     * Options allowed to be passed in self::send().
     *
     * @var array<string>
     */
    protected const GUZZLE_OPTIONS = [
        'allow_redirects',
        'auth',
        'body',
        'cert',
        'cookies',
        'connect_timeout',
        'debug',
        'decode_content',
        'delay',
        'expect',
        'force_ip_resolve',
        'form_params',
        'headers',
        'idn_conversion',
        'json',
        'multipart',
        'on_headers',
        'on_stats',
        'progress',
        'proxy',
        'query',
        'read_timeout',
        'sink',
        'ssl_key',
        'stream',
        'synchronous',
        'verify',
        'version',
    ];

    /**
     * GuzzleHttp Client.
     */
    public ?GuzzleClient $client = null;

    /**
     * Will add the Guzzle Retry middleware to requests if enabled.
     *
     * @see self::enableRetryAttempts()
     * @see self::disableRetryAttempts()
     */
    private bool $attemptRetry = false;

    /**
     * Base options for the main class, Client.
     *
     * @var ClientOptionsArray
     */
    private array $clientOptions;

    /**
     * Maximum number of retries that the Retry middleware will attempt.
     */
    private int $maxRetries = 5;

    /**
     * Helps in testing, mostly. Used to keep track of how many retries
     * have been attempted.
     *
     * @internal
     */
    private int $retryCalls = 0;

    /**
     * Constructor.
     *
     * @param ClientOptionsArray $options
     *
     *      apiUrl           - URL to the API.
     *      apiKey           - Your API Key.
     *      cachePath        - The path to your cache on the filesystem.
     *      apiRequiresQuery - True if the API requires the api key sent via query/query string, false otherwise.
     *      apiParamName     - If apiRequiresQuery = true, then the param name for the api key. E.g.: api_key
     *
     * @throws ExceptionInterface If provided options are not of the correct type or an invalid value.
     */
    public function __construct(array $options)
    {
        $optionsResolver = new OptionsResolver();
        $this->configureClientOptions($optionsResolver);

        /**
         * @var ClientOptionsArray $clientOptions
         */
        $clientOptions       = $optionsResolver->resolve($options);
        $this->clientOptions = $clientOptions;
    }

    /**
     * Builds our GuzzleHttp client.
     *
     * Some APIs require requests be made with the api key via query string; others, by setting a header. If the particular API you are querying
     * needs it sent via query string, be sure to instantiate this class with 'apiRequiresQuery' set to true and by providing the expected field
     * name used for the api key. E.g.: ['apiParamName' => 'api_key']
     *
     * Otherwise, if it is sent via header, be sure to add the headers in the $options array when calling build(). This can be done with
     * 'persistentHeaders' which contains key => value pairs of headers to set. For e.g.:
     *
     * <code>
     *      $client = new Client([
     *          'apiUrl'    => 'https://myapiurl.com/api',
     *          'apiKey'    => 'myApiKey',
     *          'cachePath' => '/var/tmp'
     *      ]);
     *      $client->build([
     *          'persistentHeaders' => [
     *              'Accept'        => 'application/json',
     *              'Client-ID'     => 'apiKey',
     *              'Authorization' => 'Bearer {someAccessToken}',
     *          ]
     *      ]);
     *      $response = $client->send(...);
     * </code>
     *
     * @param array<string, mixed> $options An associative array with options to set in the initial config of the Guzzle
     *                                      client. {@see https://docs.guzzlephp.org/en/stable/request-options.html}
     *                                      One exception to this is the use of non-Guzzle, Esi\Api specific options:
     *                                      persistentHeaders - Key => Value array where key is the header name and value is the header value.
     *                                      Also of note, right now this class is built in such a way that adding 'query' to the build options
     *                                      should be avoided, and instead sent with the {@see self::send()} method when making a request.
     *
     * @throws InvalidArgumentException If Guzzle encounters an error with passed options.
     * @throws ExceptionInterface       If an invalid headers array is passed in options.
     */
    public function build(array $options = []): GuzzleClient
    {
        // Do not allow overriding these options
        unset($options['base_uri'], $options['http_errors'], $options['query']);

        // Does the API key need to be sent as part of the query?
        if (isset($this->clientOptions['apiRequiresQuery'], $this->clientOptions['apiParamName']) && $this->clientOptions['apiRequiresQuery']) {
            $options += [
                'query' => [
                    $this->clientOptions['apiParamName'] => $this->clientOptions['apiKey'],
                ],
            ];
        }

        $buildOptions = $this->createBuildOptionsResolver()->resolve($options);

        // Create default HandlerStack
        $handlerStack = HandlerStack::create();

        // Do we need to add any persistent headers (headers sent with every request)?
        if (isset($buildOptions['persistentHeaders'])) {
            /** @var array<string> $headers * */
            $headers = $buildOptions['persistentHeaders'];

            // We use Middleware and add the headers to our handler stack.
            foreach ($headers as $header => $value) {
                $handlerStack->unshift(Middleware::mapRequest(
                    static fn (RequestInterface $request): MessageInterface => $request->withHeader($header, $value)
                ));
            }
        }

        // If we have a cache path, create our Cache handler.
        if (isset($this->clientOptions['cachePath'])) {
            $handlerStack->push(new CacheMiddleware(new PrivateCacheStrategy(
                new Psr6CacheStorage(AbstractAdapter::createSystemCache('api', 300, '2.0.0', $this->clientOptions['cachePath'] . \DIRECTORY_SEPARATOR . 'private'))
            )), 'private-cache');

            $handlerStack->push(new CacheMiddleware(new PublicCacheStrategy(
                new Psr6CacheStorage(AbstractAdapter::createSystemCache('api', 300, '2.0.0', $this->clientOptions['cachePath'] . \DIRECTORY_SEPARATOR . 'shared'))
            )), 'shared-cache');
        }

        if ($this->attemptRetry) {
            $handlerStack->push(Middleware::retry($this->retryDecider(), $this->retryDelay()));
        }

        $buildOptions += ['handler' => $handlerStack, ];

        // Attempt instantiating the client. Generally we should only run into issues if any options
        // passed to Guzzle are incorrectly defined/configured.
        $this->client = new GuzzleClient($buildOptions);

        // All done!
        return $this->client;
    }

    /**
     * @param string               $endpoint Endpoint to call on the API.
     * @param array<string, mixed> $options  An associative array with options to set per request.
     *
     * @see https://docs.guzzlephp.org/en/stable/request-options.html
     */
    public function delete(string $endpoint = '', array $options = []): ResponseInterface
    {
        $normalized = $this->normalizeRequestOptions($endpoint, $options);

        return $this->send('DELETE', $normalized[0], $normalized[1]);
    }

    /**
     * Disable the Retry middleware.
     */
    public function disableRetryAttempts(): Client
    {
        $this->attemptRetry = false;

        return $this;
    }

    /**
     * Enable the Retry middleware.
     */
    public function enableRetryAttempts(): Client
    {
        $this->attemptRetry = true;

        return $this;
    }

    /**
     * @param string               $endpoint Endpoint to call on the API.
     * @param array<string, mixed> $options  An associative array with options to set per request.
     *
     * @see https://docs.guzzlephp.org/en/stable/request-options.html
     */
    public function get(string $endpoint = '', array $options = []): ResponseInterface
    {
        $normalized = $this->normalizeRequestOptions($endpoint, $options);

        return $this->send('GET', $normalized[0], $normalized[1]);
    }

    /**
     * @param string               $endpoint Endpoint to call on the API.
     * @param array<string, mixed> $options  An associative array with options to set per request.
     *
     * @see https://docs.guzzlephp.org/en/stable/request-options.html
     */
    public function post(string $endpoint = '', array $options = []): ResponseInterface
    {
        $normalized = $this->normalizeRequestOptions($endpoint, $options);

        return $this->send('POST', $normalized[0], $normalized[1]);
    }

    /**
     * @param string               $endpoint Endpoint to call on the API.
     * @param array<string, mixed> $options  An associative array with options to set per request.
     *
     * @see https://docs.guzzlephp.org/en/stable/request-options.html
     */
    public function put(string $endpoint = '', array $options = []): ResponseInterface
    {
        $normalized = $this->normalizeRequestOptions($endpoint, $options);

        return $this->send('PUT', $normalized[0], $normalized[1]);
    }

    /**
     * Set the maximum number of retry attempts.
     */
    public function setMaxRetryAttempts(int $maxRetries): Client
    {
        $this->maxRetries = $maxRetries;

        return $this;
    }

    /**
     * Configures options used in building the Client.
     */
    protected function configureClientOptions(OptionsResolver $optionsResolver): void
    {
        $optionsResolver
            ->setRequired(['apiUrl', 'apiKey'])
            ->setDefaults([
                'apiUrl'           => '',
                'apiKey'           => '',
                'apiParamName'     => null,
                'apiRequiresQuery' => false,
                'cachePath'        => null,
            ])
            ->setAllowedTypes('apiUrl', 'string')
            ->setAllowedTypes('apiKey', 'string')
            ->setAllowedTypes('apiParamName', ['string', 'null', ])
            ->setAllowedTypes('apiRequiresQuery', 'bool')
            ->setAllowedTypes('cachePath', ['string', 'null', ])
            ->setNormalizer('apiUrl', static fn (Options $options, string $value): string => trim($value))
            ->setNormalizer('apiKey', static fn (Options $options, string $value): string => trim($value))
            ->setNormalizer('apiParamName', static fn (Options $options, ?string $value): string => trim((string) $value))
            ->setNormalizer('cachePath', static fn (Options $options, ?string $value): string => trim((string) $value))
            ->setAllowedValues('apiUrl', Validation::createIsValidCallable(new Length(['min' => 1]), new Url(['protocols' => ['http', 'https', ], ])))
            ->setAllowedValues('apiKey', Validation::createIsValidCallable(new Length(['min' => 1])))
            ->setAllowedValues('apiParamName', Validation::createIsValidCallable(new Length(['min' => 1])))
            ->setAllowedValues('cachePath', static fn (string $value): bool => is_dir($value) && is_writable($value))
        ;
    }

    /**
     * Creates an OptionsResolver instance with default options.
     */
    protected function createBuildOptionsResolver(): OptionsResolver
    {
        $optionsResolver = new OptionsResolver();
        $optionsResolver->setDefaults([
            'base_uri'          => $this->clientOptions['apiUrl'],
            'http_errors'       => true,
            'timeout'           => 10,
            'persistentHeaders' => null,
        ]);

        foreach (self::GUZZLE_OPTIONS as $guzzleOption) {
            $optionsResolver->setDefined($guzzleOption);
        }

        return $optionsResolver
            ->setAllowedTypes('persistentHeaders', ['array', 'null', ])
            ->setAllowedValues('persistentHeaders', static function (?array $value): bool {
                if ($value === null) {
                    return true;
                }

                return array_keys($value) !== range(0, \count($value) - 1);
            })
        ;
    }

    /**
     * Takes the endpoint and options for one of the request (get, post, put, ...) functions and normalizes
     * the endpoint, along with validating any passed options.
     *
     * @param array<string, mixed> $options
     *
     * @throws ExceptionInterface
     *
     * @return array{0: string, 1: array<string, mixed>}
     */
    protected function normalizeRequestOptions(string $endpoint = '', array $options = []): array
    {
        // Do not allow overriding these options
        unset($options['base_uri'], $options['http_errors']);

        // Does the API key need to be sent as part of the query?
        if (isset($this->clientOptions['apiRequiresQuery'], $this->clientOptions['apiParamName']) && $this->clientOptions['apiRequiresQuery']) {
            if (isset($options['query'])) {
                $options['query'] += [$this->clientOptions['apiParamName'] => $this->clientOptions['apiKey'], ];
            } else {
                $options['query'] = [$this->clientOptions['apiParamName'] => $this->clientOptions['apiKey'], ];
            }
        }

        $sendOptions = $this->createBuildOptionsResolver()->resolve($options);
        unset($sendOptions['persistentHeaders']);

        $endpoint = self::normalizeEndpoint($endpoint, $this->clientOptions['apiUrl']);

        return [
            $endpoint,
            $sendOptions,
        ];
    }

    /**
     * @param array<string, mixed> $options
     *
     * @throws RuntimeException
     * @throws BadResponseException|ClientException|GuzzleException
     */
    protected function send(string $method, string $endpoint = '', array $options = []): ResponseInterface
    {
        // Do we have Guzzle instantiated already?
        if (!$this->client instanceof GuzzleClient) {
            throw new RuntimeException(sprintf(
                'No valid Guzzle client detected, a client must be built with the "%s" method of "%s" first.',
                'build',
                Client::class
            ));
        }

        try {
            return $this->client->request($method, $endpoint, $options);
        } catch (ClientException $clientException) {
            // This is not necessarily standard across the many APIs out there, but included as it does indicate "Too Many Requests".
            if ($clientException->getResponse()->getStatusCode() === 429) {
                throw new RateLimitExceededException('API rate limit exceeded.', previous: $clientException);
            }

            throw $clientException;
        }
    }

    protected static function normalizeEndpoint(string $endpoint, string $apiUrl): string
    {
        $endpoint = ltrim($endpoint, '/');

        if (!str_ends_with($apiUrl, '/')) {
            $endpoint = '/' . $endpoint;
        }

        return $endpoint;
    }

    /**
     * For use in the Retry middleware. Decides when to retry a request.
     *
     * NOTE: The Retry middleware will not be used without calling {@see self::enableRetryAttempts()}.
     *
     * @return Closure(int, RequestInterface, ResponseInterface=, Throwable=): bool
     */
    private function retryDecider(): Closure
    {
        return function (
            int $retries,
            RequestInterface $request,
            ?ResponseInterface $response = null,
            ?Throwable $throwable = null
        ): bool {
            $this->retryCalls = $retries;

            // Don't retry if we have run out of retries.
            if ($this->retryCalls >= $this->maxRetries) {
                return false;
            }

            /**
             * @todo Add the ability/option to log retry attempts.
             */
            return match (true) {
                // Retry connection exceptions.
                $throwable instanceof ConnectException => true,
                // Retry on server errors.
                $response instanceof ResponseInterface => ($response->getStatusCode() >= 500 || $response->getStatusCode() === 429),
                // Do not retry.
                default => false
            };
        };
    }

    /**
     * Adds a delay to each retry attempt, based on the number of retries.
     */
    private function retryDelay(): Closure
    {
        return static function (int $numberOfRetries, ResponseInterface $response): int {
            if (!$response->hasHeader('Retry-After')) {
                return RetryMiddleware::exponentialDelay($numberOfRetries);
            }

            $retryAfter = $response->getHeaderLine('Retry-After');

            // @codeCoverageIgnoreStart
            if (!is_numeric($retryAfter)) {
                $retryAfter = (new DateTime($retryAfter))->getTimestamp() - time();
            }

            // @codeCoverageIgnoreEnd
            return 1000 * (int) $retryAfter;
        };
    }
}
