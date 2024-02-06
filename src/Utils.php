<?php

declare(strict_types=1);

/**
 * Esi\Api - A simple wrapper/builder using Guzzle for base API clients.
 *
 * @author    Eric Sizemore <admin@secondversion.com>
 * @version   1.0.0
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

use InvalidArgumentException;

use function array_filter;
use function in_array;
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

    /**
     */
    public static function verifyMethod(string $method): void
    {
        static $availableMethods;

        $availableMethods ??= ['HEAD', 'GET', 'DELETE', 'OPTIONS', 'PATCH', 'POST', 'PUT',];

        // Check for a valid method
        if (!in_array($method, $availableMethods, true)) {
            throw new InvalidArgumentException(sprintf(
                'Invalid request method specified, must be one of %s.',
                implode(', ', $availableMethods)
            ));
        }
    }

    /**
     * Performs a very bare-bones 'verification' of Guzzle options.
     *
     * @param   array<string, mixed>  $options
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
            if (!in_array($key, $validOptions, true)) {
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
