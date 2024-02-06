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

namespace Esi\Api\Traits;

// Classes
use JsonException;
use Psr\Http\Message\ResponseInterface;
use stdClass;

use function json_decode;

use const JSON_THROW_ON_ERROR;

/**
 * Trait to add a few json response related functions to a class.
 *
 * Note: expects an object implementing Psr\Http\Message\ResponseInterface.
 */
trait ParseJsonResponse
{
    /**
     * Returns the jSON data as-is from the API.
     *
     * @param ResponseInterface $response The response object.
     */
    public function raw(ResponseInterface $response): string
    {
        return $response->getBody()->getContents();
    }

    /**
     * Decodes the jSON returned from the API. Returns as an associative array.
     *
     * @param ResponseInterface $response The response object.
     * @return array<mixed>
     *
     * @throws JsonException
     */
    public function toArray(ResponseInterface $response): array
    {
        /** @var array<mixed> $json **/
        $json = json_decode($this->raw($response), true, flags: JSON_THROW_ON_ERROR);

        return $json;
    }

    /**
     * Decodes the jSON returned from the API. Returns as an array of objects.
     *
     * @param ResponseInterface $response The response object.
     *
     * @throws JsonException
     */
    public function toObject(ResponseInterface $response): stdClass
    {
        /** @var stdClass $json **/
        $json = json_decode($this->raw($response), false, flags: JSON_THROW_ON_ERROR);

        return $json;
    }
}
