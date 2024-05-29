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

namespace Esi\Api\Tests;

use Esi\Api\Client;
use Esi\Api\Exceptions\RateLimitExceededException;
use Generator;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\ServerException;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use RuntimeException;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Filesystem\Path;
use Symfony\Component\OptionsResolver\Exception\InvalidOptionsException;
use Symfony\Component\OptionsResolver\Exception\UndefinedOptionsException;

use function is_dir;
use function json_decode;
use function sys_get_temp_dir;

use const DIRECTORY_SEPARATOR;

/**
 * These tests make use of a local Mock server by using Mocko.
 *
 * @see https://mocko.dev/docs/getting-started/standalone/
 * @see https://github.com/ericsizemore/api#unit-tests
 *
 * @internal
 *
 * @phpstan-import-type ClientOptionsArray from Client
 */
#[CoversClass(Client::class)]
final class ClientTest extends TestCase
{
    private static string $cacheDir;

    #[\Override]
    public static function setUpBeforeClass(): void
    {
        $filesystem = new Filesystem();

        self::$cacheDir = Path::normalize(__DIR__ . DIRECTORY_SEPARATOR . 'tmpCache');

        if (!is_dir(self::$cacheDir)) {
            $filesystem->mkdir(self::$cacheDir);
        }
    }

    #[\Override]
    public static function tearDownAfterClass(): void
    {
        $filesystem = new Filesystem();

        $filesystem->remove(self::$cacheDir);

        self::$cacheDir = '';
    }

    public function testClientApiKeyEmptyString(): void
    {
        $this->expectException(InvalidOptionsException::class);
        $this->buildTestClient([
            'apiUrl'    => 'http://localhost:8080',
            'apiKey'    => '',
            'cachePath' => sys_get_temp_dir(),
        ]);
    }

    public function testClientApiKeyNull(): void
    {
        $this->expectException(InvalidOptionsException::class);
        $this->buildTestClient([
            'apiUrl'    => 'http://localhost:8080',
            'apiKey'    => null,
            'cachePath' => sys_get_temp_dir(),
        ]);
    }

    public function testClientApiUrlEmptyString(): void
    {
        $this->expectException(InvalidOptionsException::class);
        $this->buildTestClient([
            'apiUrl'    => '',
            'apiKey'    => 'test',
            'cachePath' => sys_get_temp_dir(),
        ]);
    }

    public function testClientBuildExtraOptions(): void
    {
        $client = $this->buildTestClient([
            'apiUrl'    => 'http://localhost:8080',
            'apiKey'    => 'test',
            'cachePath' => sys_get_temp_dir(),
        ]);
        $client->build(['persistentHeaders' => ['Accept' => 'application/json'], 'timeout' => '5.0']);

        $response = $client->get('/get', ['query' => ['foo' => 'bar']]);

        self::assertSame(200, $response->getStatusCode());

        /** @var array<array-key, array<string, array<string>>> $data */
        $data = json_decode($response->getBody()->getContents(), true);
        self::assertSame(['foo' => ['bar']], $data['args']);
    }

    public function testClientBuildNoOptionsNoQueryWithApi(): void
    {
        $client = $this->buildTestClient([
            'apiUrl'           => 'http://localhost:8080',
            'apiKey'           => 'test',
            'cachePath'        => sys_get_temp_dir(),
            'apiParamName'     => 'api_key',
            'apiRequiresQuery' => true,
        ]);
        $client->build();

        $response = $client->get('/get/noquery');

        self::assertSame(200, $response->getStatusCode());

        /** @var array<array-key, array<string, array<string>>> $data */
        $data = json_decode($response->getBody()->getContents(), true);
        self::assertArrayHasKey('args', $data);
    }

    public function testClientBuildNoOptionsNoQueryWithoutApi(): void
    {
        $client = $this->buildTestClient([
            'apiUrl'    => 'http://localhost:8080',
            'apiKey'    => 'test',
            'cachePath' => sys_get_temp_dir(),
        ]);
        $client->build();

        $response = $client->get('/get/noquery');

        self::assertSame(200, $response->getStatusCode());

        /** @var array<array-key, array<string, array<string>>> $data */
        $data = json_decode($response->getBody()->getContents(), true);
        self::assertArrayHasKey('args', $data);
        self::assertEmpty($data['args']);
    }

    public function testClientBuildWithInvalidOption(): void
    {
        $client = $this->buildTestClient([
            'apiUrl'    => 'http://localhost:8080',
            'apiKey'    => 'test',
            'cachePath' => sys_get_temp_dir(),
        ]);
        $this->expectException(UndefinedOptionsException::class);
        $client->build(['persistentHeaders' => ['Accept' => 'application/json'], 'timut' => '5.0']);
    }

    public function testClientError(): void
    {
        $client = $this->buildTestClient([
            'apiUrl'    => 'http://localhost:8080',
            'apiKey'    => 'test',
            'cachePath' => sys_get_temp_dir(),
        ]);
        $client->build(['persistentHeaders' => ['Accept' => 'application/json'], 'timeout' => '5.0']);

        $this->expectException(ClientException::class);
        $client->get('/404');
    }

    public function testClientPersistentHeadersInvalid(): void
    {
        $client = $this->buildTestClient([
            'apiUrl'    => 'http://localhost:8080',
            'apiKey'    => 'test',
            'cachePath' => sys_get_temp_dir(),
        ]);
        $this->expectException(InvalidOptionsException::class);
        $client->build(['persistentHeaders' => ['Accept', 'application/json']]);
    }

    public function testClientSendWithoutBuildFirst(): void
    {
        $client = $this->buildTestClient([
            'apiUrl'    => 'http://localhost:8080',
            'apiKey'    => 'test',
            'cachePath' => sys_get_temp_dir(),
        ]);
        $this->expectException(RuntimeException::class);
        $client->get('/get', ['query' => ['foo' => 'bar']]);
    }

    public function testClientWithApiParamsAsHeaderNoQuery(): void
    {
        $client = $this->buildTestClient([
            'apiUrl'    => 'http://localhost:8080/',
            'apiKey'    => 'test',
            'cachePath' => sys_get_temp_dir(),
        ]);

        $client->build([
            'persistentHeaders' => [
                'Accept'        => 'application/json',
                'Client-ID'     => 'apiKey',
                'Authorization' => 'someAccessToken',

            ]]);

        $response = $client->get('/anything');

        self::assertSame(200, $response->getStatusCode());

        /** @var array<array-key, array<array-key, array<string>>|array{}> $data */
        $data = json_decode($response->getBody()->getContents(), true);

        self::assertNotEmpty($data['headers']);
        self::assertArrayHasKey('Accept', $data['headers']);
        self::assertArrayHasKey('Client-Id', $data['headers']);
        self::assertArrayHasKey('Authorization', $data['headers']);
        self::assertSame(['application/json'], $data['headers']['Accept']);
        self::assertSame(['apiKey'], $data['headers']['Client-Id']);
        self::assertSame(['someAccessToken'], $data['headers']['Authorization']);
    }

    public function testClientWithApiParamsAsHeaderWithQuery(): void
    {
        $client = $this->buildTestClient([
            'apiUrl'    => 'http://localhost:8080/',
            'apiKey'    => 'test',
            'cachePath' => sys_get_temp_dir(),
        ]);

        $client->build([
            'persistentHeaders' => [
                'Accept'        => 'application/json',
                'Client-ID'     => 'apiKey',
                'Authorization' => 'someAccessToken',

            ]]);

        $response = $client->get('/anything/query1', ['query' => ['foo' => 'bar']]);

        self::assertSame(200, $response->getStatusCode());

        /** @var array<array-key, array<array-key, array<string>>|array{}> $data */
        $data = json_decode($response->getBody()->getContents(), true);

        self::assertNotEmpty($data['headers']);
        self::assertArrayHasKey('Accept', $data['headers']);
        self::assertArrayHasKey('Client-Id', $data['headers']);
        self::assertArrayHasKey('Authorization', $data['headers']);
        self::assertSame(['application/json'], $data['headers']['Accept']);
        self::assertSame(['apiKey'], $data['headers']['Client-Id']);
        self::assertSame(['someAccessToken'], $data['headers']['Authorization']);
        self::assertSame(['foo' => ['bar']], $data['args']);

        //
        $client->build([
            'persistentHeaders' => [
                'Accept'        => 'application/json',
                'Client-ID'     => 'anotherApiKey',
                'Authorization' => 'someOtherAccessToken',

            ]]);

        $response = $client->get('/anything/query2', ['query' => ['foo' => 'bar']]);

        self::assertSame(200, $response->getStatusCode());

        /** @var array<array-key, array<array-key, array<string>>|array{}> $data */
        $data = json_decode($response->getBody()->getContents(), true);

        self::assertNotEmpty($data['headers']);
        self::assertArrayHasKey('Accept', $data['headers']);
        self::assertArrayHasKey('Client-Id', $data['headers']);
        self::assertArrayHasKey('Authorization', $data['headers']);
        self::assertSame(['application/json'], $data['headers']['Accept']);
        self::assertSame(['anotherApiKey'], $data['headers']['Client-Id']);
        self::assertSame(['someOtherAccessToken'], $data['headers']['Authorization']);
        self::assertSame(['foo' => ['bar']], $data['args']);
    }

    public function testClientWithApiQuery(): void
    {
        $client = $this->buildTestClient([
            'apiUrl'           => 'http://localhost:8080/',
            'apiKey'           => 'test',
            'cachePath'        => sys_get_temp_dir(),
            'apiParamName'     => 'api_key',
            'apiRequiresQuery' => true,
        ]);

        $client->build(['persistentHeaders' => ['Accept' => 'application/json']]);

        $response = $client->get('/get/withkey', ['query' => ['foo' => 'bar']]);

        self::assertSame(200, $response->getStatusCode());

        /** @var array<array-key, array<string, array<string>>> $data */
        $data = json_decode($response->getBody()->getContents(), true);
        self::assertSame(['api_key' => ['test'], 'foo' => ['bar']], $data['args']);
    }

    public function testClientWithRetries(): void
    {
        $client = $this->buildTestClient([
            'apiUrl'           => 'http://localhost:8080/',
            'apiKey'           => 'test',
            'cachePath'        => sys_get_temp_dir(),
            'apiParamName'     => 'api_key',
            'apiRequiresQuery' => true,
        ]);
        $client->enableRetryAttempts();
        $client->setMaxRetryAttempts(1);

        $client->build(['persistentHeaders' => ['Accept' => 'application/json']]);

        $this->expectException(ServerException::class);
        $response = $client->get('/500/nodelay', ['query' => ['foo' => 'bar']]);

        self::assertSame(500, $response->getStatusCode());

        $reflectionClass = new ReflectionClass($client::class);
        $retryCalls      = $reflectionClass->getProperty('retryCalls')->getValue($client);
        self::assertSame(1, $retryCalls);
    }

    public function testClientWithRetriesRetryAfterHeaderRateLimited(): void
    {
        $client = $this->buildTestClient([
            'apiUrl'    => 'http://localhost:8080/',
            'apiKey'    => 'test',
            'cachePath' => sys_get_temp_dir(),
        ]);
        $client->enableRetryAttempts();
        $client->setMaxRetryAttempts(1);

        $client->build(['persistentHeaders' => ['Accept' => 'application/json'], ]);

        $this->expectException(RateLimitExceededException::class);
        $response = $client->get('/429');

        self::assertSame(429, $response->getStatusCode());

        $reflectionClass = new ReflectionClass($client::class);
        $retryCalls      = $reflectionClass->getProperty('retryCalls')->getValue($client);
        self::assertSame(1, $retryCalls);
    }

    public function testClientWithRetriesRetryAfterHeaderServerError(): void
    {
        $client = $this->buildTestClient([
            'apiUrl'    => 'http://localhost:8080/',
            'apiKey'    => 'test',
            'cachePath' => sys_get_temp_dir(),
        ]);
        $client->enableRetryAttempts();
        $client->setMaxRetryAttempts(1);

        $client->build(['persistentHeaders' => ['Accept' => 'application/json'], ]);

        $this->expectException(ServerException::class);
        $response = $client->get('/500');

        self::assertSame(500, $response->getStatusCode());

        $reflectionClass = new ReflectionClass($client::class);
        $retryCalls      = $reflectionClass->getProperty('retryCalls')->getValue($client);
        self::assertSame(1, $retryCalls);
    }

    public function testDisableRetryAttempts(): void
    {
        $client = $this->buildTestClient([
            'apiUrl'           => 'http://localhost:8080/',
            'apiKey'           => 'test',
            'cachePath'        => sys_get_temp_dir(),
            'apiParamName'     => 'api_key',
            'apiRequiresQuery' => true,
        ]);
        $client->disableRetryAttempts();

        $reflectionClass = new ReflectionClass($client::class);
        $attemptRetry    = $reflectionClass->getProperty('attemptRetry')->getValue($client);
        self::assertFalse($attemptRetry);
    }

    public function testEnableRetryAttempts(): void
    {
        $client = $this->buildTestClient([
            'apiUrl'           => 'http://localhost:8080/',
            'apiKey'           => 'test',
            'cachePath'        => sys_get_temp_dir(),
            'apiParamName'     => 'api_key',
            'apiRequiresQuery' => true,
        ]);
        $client->enableRetryAttempts();

        $reflectionClass = new ReflectionClass($client::class);
        $attemptRetry    = $reflectionClass->getProperty('attemptRetry')->getValue($client);
        self::assertTrue($attemptRetry);
    }

    /**
     * @param array{
     *     code: int,
     *     hasKey: string,
     *     expectedArgs: array{apiKey: array<string>, foo: array<string>}
     * } $options
     */
    #[DataProvider('methodRequestProvider')]
    public function testMethodSendsRequest(string $method, string $endpoint, array $options): void
    {
        $client = $this->buildTestClient([
            'apiUrl'           => 'http://localhost:8080/',
            'apiKey'           => 'test',
            'cachePath'        => sys_get_temp_dir(),
            'apiParamName'     => 'api_key',
            'apiRequiresQuery' => true,
        ]);

        $client->build(['persistentHeaders' => ['Accept' => 'application/json']]);

        $response = [$client, $method]($endpoint, ['query' => ['foo' => 'bar']]);

        self::assertSame($options['code'], $response->getStatusCode());

        /** @var array{args: array{apiKey: array<string>, foo: array<string>}} $data */
        $data = json_decode($response->getBody()->getContents(), true);
        self::assertSame($options['expectedArgs'], $data['args']);
    }

    public function testParseWithJsonTraitRaw(): void
    {
        $client = $this->buildTestClient([
            'apiUrl'           => 'http://localhost:8080/',
            'apiKey'           => 'test',
            'cachePath'        => sys_get_temp_dir(),
            'apiParamName'     => 'api_key',
            'apiRequiresQuery' => true,
        ]);
        $client->build([
            'persistentHeaders' => ['Accept' => 'application/json'],
        ]);
        $response = $client->get('/get', ['query' => ['foo' => 'bar']]);

        self::assertSame(200, $response->getStatusCode());
        self::assertInstanceOf(Response::class, $response);
        self::assertNotEmpty($client->raw($response));
    }

    public function testParseWithJsonTraitToArray(): void
    {
        $client = $this->buildTestClient([
            'apiUrl'           => 'http://localhost:8080/',
            'apiKey'           => 'test',
            'cachePath'        => sys_get_temp_dir(),
            'apiParamName'     => 'api_key',
            'apiRequiresQuery' => true,
        ]);
        $client->build([
            'persistentHeaders' => ['Accept' => 'application/json'],
        ]);
        $response = $client->get('/get/full', ['query' => ['foo' => 'bar']]);

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
        $client = $this->buildTestClient([
            'apiUrl'           => 'http://localhost:8080/',
            'apiKey'           => 'test',
            'cachePath'        => sys_get_temp_dir(),
            'apiParamName'     => 'api_key',
            'apiRequiresQuery' => true,
        ]);
        $client->build([
            'persistentHeaders' => ['Accept' => 'application/json'],
        ]);
        $response = $client->get('/get/full', ['query' => ['foo' => 'bar']]);

        self::assertSame(200, $response->getStatusCode());
        self::assertInstanceOf(Response::class, $response);

        $object = $client->toObject($response);
        self::assertObjectHasProperty('args', $object);
        self::assertObjectHasProperty('headers', $object);
        self::assertObjectHasProperty('origin', $object);
        self::assertObjectHasProperty('url', $object);
    }

    public function testRateLimitExceeded(): void
    {
        $client = $this->buildTestClient([
            'apiUrl'    => 'http://localhost:8080/',
            'apiKey'    => 'test',
            'cachePath' => sys_get_temp_dir(),
        ]);
        $client->build(['persistentHeaders' => ['Accept' => 'application/json'], 'timeout' => '5.0']);

        $this->expectException(RateLimitExceededException::class);
        $client->get('/429');
    }

    public function testSetMaxRetryAttempts(): void
    {
        $client = $this->buildTestClient([
            'apiUrl'           => 'http://localhost:8080/',
            'apiKey'           => 'test',
            'cachePath'        => sys_get_temp_dir(),
            'apiParamName'     => 'api_key',
            'apiRequiresQuery' => true,
        ]);
        $client->setMaxRetryAttempts(10);

        $reflectionClass = new ReflectionClass($client::class);
        $maxRetries      = $reflectionClass->getProperty('maxRetries')->getValue($client);
        self::assertSame(10, $maxRetries);
    }

    public static function methodRequestProvider(): Generator
    {
        yield ['delete', '/delete', ['code' => 200, 'expectedArgs' => ['api_key' => ['test'], 'foo' => ['bar']]]];
        yield ['post', '/post', ['code' => 200, 'expectedArgs' => ['api_key' => ['test'], 'foo' => ['bar']]]];
        yield ['put', '/put', ['code' => 200, 'expectedArgs' => ['api_key' => ['test'], 'foo' => ['bar']]]];

    }

    /**
     * @param ClientOptionsArray $params
     */
    private function buildTestClient(array $params): Client
    {
        return new Client($params);
    }
}
