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

namespace Esi\Api\Tests;

use Esi\Api\Client;
use Esi\Api\Exceptions\RateLimitExceededException;
use Esi\Api\Utils;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\ServerException;
use GuzzleHttp\Psr7\Response;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use RuntimeException;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Filesystem\Path;

use function is_dir;
use function json_decode;
use function serialize;
use function sys_get_temp_dir;

use const DIRECTORY_SEPARATOR;

/**
 * Client Tests.
 *
 * These tests are... a mess. Needs some cleaning up.
 *
 * @todo I do not like testing against a live service. Though, in this case, it uses https://httpbin.org instead of
 *       an actual live API. Would likely be best to just do everything through Mocks.
 *
 * @internal
 */
#[CoversClass(Client::class)]
#[CoversClass(Utils::class)]
final class ClientTest extends TestCase
{
    private static string $cacheDir;

    #[\Override]
    public static function setUpBeforeClass(): void
    {
        $filesystem = new FileSystem();

        self::$cacheDir = Path::normalize(__DIR__ . DIRECTORY_SEPARATOR . 'tmpCache');

        if (!is_dir(self::$cacheDir)) {
            $filesystem->mkdir(self::$cacheDir);
        }
    }

    #[\Override]
    public static function tearDownAfterClass(): void
    {
        $filesystem = new FileSystem();

        $filesystem->remove(self::$cacheDir);

        self::$cacheDir = '';
    }

    private function buildTestClient(string | bool | null ...$params): Client
    {
        static $called;

        // @phpstan-ignore-next-line
        return $called[serialize($params)] ??= new Client(...$params);
    }

    public function testClientWithApiQuery(): void
    {
        $client = $this->buildTestClient('https://httpbin.org', 'test', sys_get_temp_dir(), true, 'api_key');

        $client->build(['persistentHeaders' => ['Accept' => 'application/json']]);

        $response = $client->send('GET', '/get', ['query' => ['foo' => 'bar']]);

        self::assertSame(200, $response->getStatusCode());

        $data = json_decode($response->getBody()->getContents(), true);
        self::assertNotEmpty($data);
        self::assertArrayHasKey('args', $data); // @phpstan-ignore-line
        self::assertSame(['api_key' => 'test', 'foo' => 'bar'], $data['args']);
    }

    public function testClientWithApiParamsAsHeaderNoQuery(): void
    {
        $client = $this->buildTestClient('https://httpbin.org', 'test', sys_get_temp_dir());

        $client->build([
            'persistentHeaders' => [
                'Accept'        => 'application/json',
                'Client-ID'     => 'apiKey',
                'Authorization' => 'someAccessToken',

            ]]);

        $response = $client->send('GET', '/anything');

        self::assertSame(200, $response->getStatusCode());

        /** @var array<string, array{}|string|null> $data * */
        $data = json_decode($response->getBody()->getContents(), true);
        self::assertNotEmpty($data);

        self::assertNotEmpty($data['headers']);
        self::assertArrayHasKey('Accept', $data['headers']); // @phpstan-ignore-line
        self::assertArrayHasKey('Client-Id', $data['headers']);
        self::assertArrayHasKey('Authorization', $data['headers']);
        self::assertSame('application/json', $data['headers']['Accept']);
        self::assertSame('apiKey', $data['headers']['Client-Id']);
        self::assertSame('someAccessToken', $data['headers']['Authorization']);
    }

    public function testClientWithApiParamsAsHeaderWithQuery(): void
    {
        $client = $this->buildTestClient('https://httpbin.org', 'test', sys_get_temp_dir());

        $client->build([
            'persistentHeaders' => [
                'Accept'        => 'application/json',
                'Client-ID'     => 'apiKey',
                'Authorization' => 'someAccessToken',

            ]]);

        $response = $client->send('GET', '/anything', ['query' => ['foo' => 'bar']]);

        self::assertSame(200, $response->getStatusCode());

        /** @var array<string, array{}|string|null> $data * */
        $data = json_decode($response->getBody()->getContents(), true);
        self::assertNotEmpty($data);

        self::assertNotEmpty($data['headers']);
        self::assertArrayHasKey('Accept', $data['headers']); // @phpstan-ignore-line
        self::assertArrayHasKey('Client-Id', $data['headers']);
        self::assertArrayHasKey('Authorization', $data['headers']);
        self::assertSame('application/json', $data['headers']['Accept']);
        self::assertSame('apiKey', $data['headers']['Client-Id']);
        self::assertSame('someAccessToken', $data['headers']['Authorization']);

        self::assertNotEmpty($data['args']);
        self::assertArrayHasKey('args', $data); // @phpstan-ignore-line
        self::assertSame(['foo' => 'bar'], $data['args']);

        //
        $client->build([
            'persistentHeaders' => [
                'Accept'        => 'application/json',
                'Client-ID'     => 'anotherApiKey',
                'Authorization' => 'someOtherAccessToken',

            ]]);

        $response = $client->send('GET', '/anything', ['query' => ['foo' => 'bar']]);

        self::assertSame(200, $response->getStatusCode());

        /** @var array<string, array{}|string|null> $data * */
        $data = json_decode($response->getBody()->getContents(), true);
        self::assertNotEmpty($data);

        self::assertNotEmpty($data['headers']);
        self::assertArrayHasKey('Accept', $data['headers']); // @phpstan-ignore-line
        self::assertArrayHasKey('Client-Id', $data['headers']);
        self::assertArrayHasKey('Authorization', $data['headers']);
        self::assertSame('application/json', $data['headers']['Accept']);
        self::assertSame('anotherApiKey', $data['headers']['Client-Id']);
        self::assertSame('someOtherAccessToken', $data['headers']['Authorization']);

        self::assertNotEmpty($data['args']);
        self::assertArrayHasKey('args', $data); // @phpstan-ignore-line
        self::assertSame(['foo' => 'bar'], $data['args']);
    }

    public function testClientWithRetries(): void
    {
        $client = $this->buildTestClient('https://httpbin.org', 'test', sys_get_temp_dir(), true, 'api_key');
        $client->enableRetryAttempts();
        $client->setMaxRetryAttempts(1);

        $client->build(['persistentHeaders' => ['Accept' => 'application/json']]);

        $this->expectException(ServerException::class);
        $response = $client->send('GET', '/status/500', ['query' => ['foo' => 'bar']]);

        self::assertSame(500, $response->getStatusCode());

        $reflectionClass = new ReflectionClass($client::class);
        $retryCalls      = $reflectionClass->getProperty('retryCalls')->getValue($client);
        self::assertSame(1, $retryCalls);
    }

    public function testClientWithRetriesRetryAfterHeaderRateLimited(): void
    {
        $client = $this->buildTestClient('https://esiapi.free.beeceptor.com/', 'test', sys_get_temp_dir());
        $client->enableRetryAttempts();
        $client->setMaxRetryAttempts(1);

        $client->build(['persistentHeaders' => ['Accept' => 'application/json'],]);

        $this->expectException(RateLimitExceededException::class);
        $response = $client->send('GET', '429');

        self::assertSame(429, $response->getStatusCode());

        $reflectionClass = new ReflectionClass($client::class);
        $retryCalls      = $reflectionClass->getProperty('retryCalls')->getValue($client);
        self::assertSame(1, $retryCalls);
    }

    public function testClientWithRetriesRetryAfterHeaderServerError(): void
    {
        $client = $this->buildTestClient('https://esiapi.free.beeceptor.com/', 'test', sys_get_temp_dir());
        $client->enableRetryAttempts();
        $client->setMaxRetryAttempts(1);

        $client->build(['persistentHeaders' => ['Accept' => 'application/json'],]);

        $this->expectException(ServerException::class);
        $response = $client->send('GET', '500');

        self::assertSame(500, $response->getStatusCode());

        $reflectionClass = new ReflectionClass($client::class);
        $retryCalls      = $reflectionClass->getProperty('retryCalls')->getValue($client);
        self::assertSame(1, $retryCalls);
    }

    public function testEnableRetryAttempts(): void
    {
        $client = $this->buildTestClient('https://httpbin.org', 'test', sys_get_temp_dir(), true, 'api_key');
        $client->enableRetryAttempts();

        $reflectionClass = new ReflectionClass($client::class);
        $attemptRetry    = $reflectionClass->getProperty('attemptRetry')->getValue($client);
        self::assertTrue($attemptRetry);
    }

    public function testDisableRetryAttempts(): void
    {
        $client = $this->buildTestClient('https://httpbin.org', 'test', sys_get_temp_dir(), true, 'api_key');
        $client->disableRetryAttempts();

        $reflectionClass = new ReflectionClass($client::class);
        $attemptRetry    = $reflectionClass->getProperty('attemptRetry')->getValue($client);
        self::assertFalse($attemptRetry);
    }

    public function testSetMaxRetryAttempts(): void
    {
        $client = $this->buildTestClient('https://httpbin.org', 'test', sys_get_temp_dir(), true, 'api_key');
        $client->setMaxRetryAttempts(10);

        $reflectionClass = new ReflectionClass($client::class);
        $maxRetries      = $reflectionClass->getProperty('maxRetries')->getValue($client);
        self::assertSame(10, $maxRetries);
    }

    public function testClientApiUrlEmptyString(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->buildTestClient('', 'test', sys_get_temp_dir());
    }

    public function testClientApiKeyEmptyString(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->buildTestClient('https://httpbin.org', '', sys_get_temp_dir());
    }

    public function testClientApiKeyNull(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->buildTestClient('https://httpbin.org', null, sys_get_temp_dir());
    }

    public function testClientPersistentHeadersInvalid(): void
    {
        $client = $this->buildTestClient('https://httpbin.org', 'test', sys_get_temp_dir());
        $this->expectException(InvalidArgumentException::class);
        $client->build(['persistentHeaders' => ['Accept', 'application/json']]);
    }

    public function testClientBuildExtraOptions(): void
    {
        $client = $this->buildTestClient('https://httpbin.org', 'test', sys_get_temp_dir());
        $client->build(['persistentHeaders' => ['Accept' => 'application/json'], 'timeout' => '5.0']);

        $response = $client->send('GET', '/get', ['query' => ['foo' => 'bar']]);

        self::assertSame(200, $response->getStatusCode());

        $data = json_decode($response->getBody()->getContents(), true);
        self::assertNotEmpty($data);
        self::assertArrayHasKey('args', $data); // @phpstan-ignore-line
        self::assertSame(['foo' => 'bar'], $data['args']);
    }

    public function testClientBuildAndSend(): void
    {
        $client   = $this->buildTestClient('https://httpbin.org', 'test', sys_get_temp_dir());
        $response = $client->buildAndSend('GET', '/get', [
            'persistentHeaders' => ['Accept' => 'application/json'],
            'timeout'           => '5.0',
            'query'             => ['foo' => 'bar'],
        ]);

        self::assertSame(200, $response->getStatusCode());

        $data = json_decode($response->getBody()->getContents(), true);
        self::assertNotEmpty($data);
        self::assertArrayHasKey('args', $data); // @phpstan-ignore-line
        self::assertSame(['foo' => 'bar'], $data['args']);
    }

    public function testClientBuildNoOptionsNoQueryWithApi(): void
    {
        $client = $this->buildTestClient('https://httpbin.org', 'test', sys_get_temp_dir(), true, 'api_key');
        $client->build();

        $response = $client->send('GET', '/get');

        self::assertSame(200, $response->getStatusCode());

        $data = json_decode($response->getBody()->getContents(), true);
        self::assertNotEmpty($data);
        self::assertArrayHasKey('args', $data); // @phpstan-ignore-line
    }

    public function testClientBuildNoOptionsNoQueryWithoutApi(): void
    {
        $client = $this->buildTestClient('https://httpbin.org', 'test', sys_get_temp_dir());
        $client->build();

        $response = $client->send('GET', '/get');

        self::assertSame(200, $response->getStatusCode());

        $data = json_decode($response->getBody()->getContents(), true);
        self::assertNotEmpty($data);
        self::assertArrayHasKey('args', $data); // @phpstan-ignore-line
        self::assertEmpty($data['args']);
    }

    public function testClientSendInvalidMethod(): void
    {
        $client = $this->buildTestClient('https://httpbin.org', 'test', sys_get_temp_dir());
        $client->build(['persistentHeaders' => ['Accept' => 'application/json'], 'timeout' => '5.0']);

        $this->expectException(InvalidArgumentException::class);
        $client->send('INVALID', '/get', ['query' => ['foo' => 'bar']]);
    }

    public function testClientSendWithoutBuildFirst(): void
    {
        $client = $this->buildTestClient('https://httpbin.org', 'testnobuild', sys_get_temp_dir());
        $this->expectException(RuntimeException::class);
        $client->send('GET', '/get', ['query' => ['foo' => 'bar']]);
    }

    public function testRateLimitExceeded(): void
    {
        $client = $this->buildTestClient('https://httpbin.org', 'test', sys_get_temp_dir());
        $client->build(['persistentHeaders' => ['Accept' => 'application/json'], 'timeout' => '5.0']);

        $this->expectException(RateLimitExceededException::class);
        $client->send('GET', '/status/429');
    }

    public function testClientError(): void
    {
        $client = $this->buildTestClient('https://httpbin.org', 'test', sys_get_temp_dir());
        $client->build(['persistentHeaders' => ['Accept' => 'application/json'], 'timeout' => '5.0']);

        $this->expectException(ClientException::class);
        $client->send('GET', '/status/404');
    }

    public function testClientBuildWithInvalidOption(): void
    {
        $client = $this->buildTestClient('https://httpbin.org', 'test', sys_get_temp_dir());
        $this->expectException(InvalidArgumentException::class);
        $client->build(['persistentHeaders' => ['Accept' => 'application/json'], 'timut' => '5.0']);
    }

    public function testClientWithExtraQueryHeaders(): void
    {
        $client = $this->buildTestClient('https://httpbin.org', 'test', sys_get_temp_dir(), true, 'api_key');
        $client->build([
            'persistentHeaders' => ['Accept' => 'application/json'],
        ]);
        $response = $client->send('GET', '/get', ['query' => ['foo' => 'bar']]);

        self::assertSame(200, $response->getStatusCode());

        $data = json_decode($response->getBody()->getContents(), true);
        self::assertNotEmpty($data);
        self::assertArrayHasKey('args', $data); // @phpstan-ignore-line
        self::assertSame(['api_key' => 'test', 'foo' => 'bar'], $data['args']);
    }

    public function testClientWithQueryInBuildOptions(): void
    {
        $client = $this->buildTestClient('https://httpbin.org', 'test', sys_get_temp_dir(), true, 'api_key');

        $this->expectException(InvalidArgumentException::class);
        $client->build([
            'apiParamName'      => 'api_key',
            'apiRequiresQuery'  => true,
            'persistentHeaders' => ['Accept' => 'application/json'],
            'query'             => ['foo' => 'bar'],
        ]);
    }

    public function testParseWithJsonTraitRaw(): void
    {
        $client = $this->buildTestClient('https://httpbin.org', 'test', sys_get_temp_dir(), true, 'api_key');
        $client->build([
            'persistentHeaders' => ['Accept' => 'application/json'],
        ]);
        $response = $client->send('GET', '/get', ['query' => ['foo' => 'bar']]);

        self::assertSame(200, $response->getStatusCode());
        self::assertInstanceOf(Response::class, $response);
        self::assertNotEmpty($client->raw($response));
    }

    public function testParseWithJsonTraitToArray(): void
    {
        $client = $this->buildTestClient('https://httpbin.org', 'test', sys_get_temp_dir(), true, 'api_key');
        $client->build([
            'persistentHeaders' => ['Accept' => 'application/json'],
        ]);
        $response = $client->send('GET', '/get', ['query' => ['foo' => 'bar']]);

        self::assertSame(200, $response->getStatusCode());
        self::assertInstanceOf(Response::class, $response);

        $array = $client->toArray($response);
        self::assertArrayHasKey('args', $array);
        self::assertArrayHasKey('headers', $array);
        self::assertArrayHasKey('origin', $array);
        self::assertArrayHasKey('url', $array);
    }

    public function testParseWithJsonTraitToObject(): void
    {
        $client = $this->buildTestClient('https://httpbin.org', 'test', sys_get_temp_dir(), true, 'api_key');
        $client->build([
            'persistentHeaders' => ['Accept' => 'application/json'],
        ]);
        $response = $client->send('GET', '/get', ['query' => ['foo' => 'bar']]);

        self::assertSame(200, $response->getStatusCode());
        self::assertInstanceOf(Response::class, $response);

        $object = $client->toObject($response);
        self::assertObjectHasProperty('args', $object);
        self::assertObjectHasProperty('headers', $object);
        self::assertObjectHasProperty('origin', $object);
        self::assertObjectHasProperty('url', $object);
    }
}
