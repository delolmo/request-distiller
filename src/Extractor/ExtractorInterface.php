<?php

declare(strict_types=1);

namespace DelOlmo\Distiller\Extractor;

use Psr\Http\Message\RequestInterface as Request;

/**
 * Extracts variables from a Request object.
 *
 * @author Antonio del Olmo García <adelolmog@gmail.com>
 */
interface ExtractorInterface
{
    /**
     * Grabs data from the request and returns it as an array.
     *
     * The extract method should not be called if the Request is unsupported.
     *
     * @param \Psr\Http\Message\RequestInterface $request
     * @return array
     * @throws \DelOlmo\Distiller\Exception\UnsupportedRequestException if the
     * given Request is unsupported.
     */
    public function extract(Request $request): array;

    /**
     * Whether or not the extractor supports the given request.
     *
     * @param \Psr\Http\Message\RequestInterface $request
     * @return bool
     */
    public function supports(Request $request): bool;
}
