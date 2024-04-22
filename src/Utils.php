<?php

declare(strict_types=1);

/**
 * This file is part of Esi\Api.
 *
 * (c) Eric Sizemore <admin@secondversion.com>
 *
 * This source file is subject to the MIT license. For the full
 * copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Esi\Api;

use InvalidArgumentException;

use function array_filter;
use function implode;
use function ltrim;
use function sprintf;
use function str_ends_with;

use const ARRAY_FILTER_USE_BOTH;

/**
 */
class Utils
{
    public static function normalizeEndpoint(?string $endpoint, string $apiUrl): string
    {
        $endpoint = ltrim((string) $endpoint, '/');

        if (!str_ends_with($apiUrl, '/')) {
            $endpoint = '/' . $endpoint;
        }

        return $endpoint;
    }

    public static function verifyMethod(string $method): void
    {
        static $availableMethods;

        $availableMethods ??= ['HEAD', 'GET', 'DELETE', 'OPTIONS', 'PATCH', 'POST', 'PUT', ];

        // Check for a valid method
        if (!\in_array($method, $availableMethods, true)) {
            throw new InvalidArgumentException(sprintf(
                'Invalid request method specified, must be one of %s.',
                implode(', ', $availableMethods)
            ));
        }
    }

    /**
     * Performs a very bare-bones 'verification' of Guzzle options.
     *
     * @param array<string, mixed> $options
     */
    public static function verifyOptions(array $options): void
    {
        static $validOptions;

        $validOptions ??= [
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
            'timeout',
            'version',
        ];

        // Remove options not recognized by Guzzle, or ones we want to keep as default.
        unset($options['persistentHeaders'], $options['http_errors']);

        $invalidOptions = [];

        array_filter($options, static function ($value, int|string $key) use (&$invalidOptions, $validOptions): bool {
            if (!\in_array($key, $validOptions, true)) {
                $invalidOptions[] = $key;

                return true;
            }

            return false;
        }, ARRAY_FILTER_USE_BOTH);

        if ($invalidOptions !== []) {
            throw new InvalidArgumentException(sprintf('Invalid option(s) specified: %s', implode(', ', $invalidOptions)));
        }
    }
}
