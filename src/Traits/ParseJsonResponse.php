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

namespace Esi\Api\Traits;

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
     */
    public function raw(ResponseInterface $response): string
    {
        return $response->getBody()->getContents();
    }

    /**
     * Decodes the jSON returned from the API. Returns as an associative array.
     *
     * @throws JsonException
     *
     * @return array<mixed>
     */
    public function toArray(ResponseInterface $response): array
    {
        $json = json_decode($this->raw($response), true, flags: JSON_THROW_ON_ERROR);

        //@codeCoverageIgnoreStart
        if (!\is_array($json)) {
            throw new JsonException();
        }
        //@codeCoverageIgnoreEnd

        return $json;
    }

    /**
     * Decodes the jSON returned from the API. Returns as an array of objects.
     *
     * @throws JsonException
     */
    public function toObject(ResponseInterface $response): stdClass
    {
        $json = json_decode($this->raw($response), false, flags: JSON_THROW_ON_ERROR);

        //@codeCoverageIgnoreStart
        if (!$json instanceof stdClass) {
            throw new JsonException();
        }
        //@codeCoverageIgnoreEnd

        return $json;
    }
}
