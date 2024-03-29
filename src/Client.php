<?php

declare(strict_types=1);

/**
 * Esi\Api - A simple wrapper/builder using Guzzle for base API clients.
 *
 * @author    Eric Sizemore <admin@secondversion.com>
 *
 * @version   1.0.0
 *
 * @copyright (C) 2024 Eric Sizemore
 * @license   The MIT License (MIT)
 *
 * Copyright (C) 2024 Eric Sizemore <https://www.secondversion.com/>.
 *
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to
 * deal in the Software without restriction, including without limitation the
 * rights to use, copy, modify, merge, publish, distribute, sublicense, and/or
 * sell copies of the Software, and to permit persons to whom the Software is
 * furnished to do so, subject to the following conditions:
 *
 * The above copyright notice and this permission notice shall be included in
 * all copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN
 * THE SOFTWARE.
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
use GuzzleHttp\Exception\InvalidArgumentException as GuzzleInvalidArgumentException;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\RetryMiddleware;
use InvalidArgumentException;
use Kevinrob\GuzzleCache\CacheMiddleware;
use Kevinrob\GuzzleCache\Storage\Psr6CacheStorage;
use Kevinrob\GuzzleCache\Strategy\PrivateCacheStrategy;
use Psr\Http\Message\MessageInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use RuntimeException;
use SensitiveParameter;
use Symfony\Component\Cache\Adapter\FilesystemAdapter;
use Throwable;

use function array_keys;
use function count;
use function is_dir;
use function is_numeric;
use function is_writable;
use function range;
use function sprintf;
use function strtoupper;
use function time;
use function trim;

/**
 * Essentially a wrapper, or base class of sorts, for building a Guzzle Client object.
 *
 * Note: Only designed for non-asynchronous, non-pool, and non-promise requests currently.
 *
 * @todo Allow more cache options. Currently the only option is via files using Symfony's FilesystemAdapter.
 *
 * @see \Esi\Api\Tests\ClientTest
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
     * API key.
     */
    private readonly string $apiKey;

    /**
     * Base API endpoint.
     */
    private readonly string $apiUrl;

    /**
     * If $this->apiRequiresQuery = true, then what is the name of the
     * query parameter for the API key? For example: api_key.
     */
    private readonly string $apiParamName;

    /**
     * Path to your cache folder on the file system.
     */
    private ?string $cachePath = null;

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
     * @param string  $apiUrl           URL to the API.
     * @param ?string $apiKey           Your API Key.
     * @param ?string $cachePath        The path to your cache on the filesystem.
     * @param bool    $apiRequiresQuery True if the API requires the api key sent via query/query string, false otherwise.
     * @param string  $apiParamName     If $apiRequiresQuery = true, then the param name for the api key. E.g.: api_key
     *
     * @throws InvalidArgumentException If either the api key or api url are empty strings.
     */
    public function __construct(
        string $apiUrl,
        #[SensitiveParameter]
        ?string $apiKey = null,
        ?string $cachePath = null,
        // Does this particular API require the API key to be sent in the query string?
        private readonly bool $apiRequiresQuery = false,
        string $apiParamName = ''
    ) {
        $apiUrl       = trim($apiUrl);
        $apiKey       = trim((string) $apiKey);
        $apiParamName = trim($apiParamName);
        $cachePath    = trim((string) $cachePath);

        if ($apiUrl === '') {
            throw new InvalidArgumentException('API URL expects non-empty-string, empty-string provided.');
        }

        /**
         * @todo Some APIs require api keys, some don't.
         */
        if ($apiKey === '') {
            throw new InvalidArgumentException('API key expects a non-empty-string, empty-string provided.');
        }

        $this->apiUrl       = $apiUrl;
        $this->apiKey       = $apiKey;
        $this->apiParamName = $apiParamName;

        if (is_dir($cachePath) && is_writable($cachePath)) {
            $this->cachePath = $cachePath;
        }
    }

    /**
     * Builds the client and sends the request, all in one: basically just combines {@see self::build()} and {@see self::send()}.
     *
     * @param string               $method   The method to use, such as GET.
     * @param ?string              $endpoint Endpoint to call on the API.
     * @param array<string, mixed> $options  An associative array with options to set in the initial config of the Guzzle
     *                                       client. {@see https://docs.guzzlephp.org/en/stable/request-options.html}
     *                                       One exception to this is the use of non-Guzzle, Esi\Api specific options:
     *                                       persistentHeaders - Key => Value array where key is the header name and value is the header value.
     *                                       Also of note, right now this class is built in such a way that adding 'query' to the build options
     *                                       should be avoided, and instead sent with the {@see self::send()} method when making a request. If a
     *                                       'query' key is found in the $options array, it will raise an InvalidArgumentException.
     *
     * @throws GuzzleInvalidArgumentException                           If Guzzle encounters an error with passed options
     * @throws InvalidArgumentException                                 If 'query' is passed in options. Should only be done on the send() call.
     *                                                                  Or if an invalid headers array is passed in options.
     * @throws RuntimeException
     * @throws ClientException | GuzzleException | BadResponseException
     */
    public function buildAndSend(string $method, ?string $endpoint = null, ?array $options = null): ResponseInterface
    {
        $query = $options['query'] ?? [];
        unset($options['query']);

        $this->build($options);

        if ($query !== []) {
            $options['query'] = $query;
        }

        return $this->send($method, $endpoint, $options);
    }

    /**
     * Builds our GuzzleHttp client.
     *
     * Some APIs require requests be made with the api key via query string; others, by setting a header. If the particular API you are querying
     * needs it sent via query string, be sure to instantiate this class with $apiRequiresQuery set to true and by providing the expected field
     * name used for the api key. E.g.: api_key
     *
     * Otherwise, if it is sent via header, be sure to add the headers in the $options array when calling build(). This can be done with
     * 'persistentHeaders' which contains key => value pairs of headers to set. For e.g.:
     *
     * <code>
     *      $client = new Client('https://myapiurl.com/api', 'myApiKey', '/var/tmp');
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
     *                                      should be avoided, and instead sent with the {@see self::send()} method when making a request. If a
     *                                      'query' key is found in the $options array, it will raise an InvalidArgumentException.
     *
     * @throws GuzzleInvalidArgumentException If Guzzle encounters an error with passed options
     * @throws InvalidArgumentException       If 'query' is passed in options. Should only be done on the send() call.
     *                                        Or if an invalid headers array is passed in options.
     */
    public function build(?array $options = null): GuzzleClient
    {
        // Default options
        $defaultOptions = [
            'base_uri'    => $this->apiUrl,
            'http_errors' => true,
            'timeout'     => 10,
        ];

        // Create default HandlerStack
        $handlerStack = HandlerStack::create();

        // Process options.
        if ($options !== null) {
            // Do we need to add any persistent headers (headers sent with every request)?
            if (isset($options['persistentHeaders'])) {
                /** @var array<string> $headers * */
                $headers = $options['persistentHeaders'];

                if (array_keys($headers) === range(0, count($headers) - 1)) {
                    throw new InvalidArgumentException('The headers array must have header name as keys.');
                }

                // We use Middleware and add the headers to our handler stack.
                foreach ($headers as $header => $value) {
                    $handlerStack->unshift(Middleware::mapRequest(
                        static fn (RequestInterface $request): MessageInterface => $request->withHeader($header, $value)
                    ));
                }
            }

            if (isset($options['query'])) {
                throw new InvalidArgumentException('Please only specify a query parameter for options when using ::send()');
            }

            Utils::verifyOptions($options);

            $defaultOptions += $options;
        }

        // Does the API key need to be sent as part of the query?
        if ($this->apiRequiresQuery) {
            $defaultOptions += [
                'query' => [$this->apiParamName => $this->apiKey],
            ];
        }

        // If we have a cache path, create our Cache handler.
        if ($this->cachePath !== null) {
            $handlerStack->push(new CacheMiddleware(new PrivateCacheStrategy(
                new Psr6CacheStorage(new FilesystemAdapter('', 300, $this->cachePath))
            )), 'cache');
        }

        if ($this->attemptRetry) {
            $handlerStack->push(Middleware::retry($this->retryDecider(), $this->retryDelay()));
        }

        $defaultOptions += ['handler' => $handlerStack,];

        // Attempt instantiating the client. Generally we should only run into issues if any options
        // passed to Guzzle are incorrectly defined/configured.
        $this->client = new GuzzleClient($defaultOptions);

        // All done!
        return $this->client;
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
     * Disable the Retry middleware.
     */
    public function disableRetryAttempts(): Client
    {
        $this->attemptRetry = false;

        return $this;
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
     * Performs a synchronous request with given method and API endpoint.
     *
     * @param string                $method   The method to use, such as GET.
     * @param ?string               $endpoint Endpoint to call on the API.
     * @param ?array<string, mixed> $options  An associative array with options to set per request.
     *
     *                                           @see https://docs.guzzlephp.org/en/stable/request-options.html
     *
     * @return ResponseInterface An object implementing PSR's ResponseInterface object.
     *
     * @throws InvalidArgumentException
     * @throws RuntimeException
     * @throws ClientException | GuzzleException | BadResponseException
     */
    public function send(string $method, ?string $endpoint = null, ?array $options = null): ResponseInterface
    {
        // Check for a valid method
        $method = trim(strtoupper($method));

        Utils::verifyMethod($method);

        /**
         * If passing options, verify against the request options Guzzle expects.
         * {@see https://docs.guzzlephp.org/en/stable/request-options.html}
         * {@see Utils::verifyOptions()}.
         */
        if ($options !== null) {
            Utils::verifyOptions($options);
        } else {
            $options = [];
        }

        if ($this->apiRequiresQuery) {
            if (isset($options['query'])) {
                $options['query'] += [$this->apiParamName => $this->apiKey];
            } else {
                $options['query'] = [$this->apiParamName => $this->apiKey];
            }
        }

        $endpoint = Utils::normalizeEndpoint($endpoint, $this->apiUrl);

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

    /**
     * For use in the Retry middleware. Decides when to retry a request.
     *
     * NOTE: The Retry middleware will not be used without calling {@see self::enableRetryAttempts()}.
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
