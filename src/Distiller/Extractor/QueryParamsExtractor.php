<?php

declare(strict_types=1);

namespace DelOlmo\Distiller\Extractor;

use DelOlmo\Distiller\Exception\UnsupportedRequestException;
use Psr\Http\Message\RequestInterface as Request;
use Psr\Http\Message\ServerRequestInterface as ServerRequest;

/**
 * Extracts the query parameters from the Request.
 *
 * @author Antonio del Olmo García <adelolmog@gmail.com>
 */
class QueryParamsExtractor implements ExtractorInterface
{
    /**
     * {@inheritdoc}
     */
    public function extract(Request $request): array
    {
        if (!$this->supports($request)) {
            throw new UnsupportedRequestException();
        }

        return $request->getQueryParams();
    }

    /**
     * {@inheritdoc}
     */
    public function supports(Request $request): bool
    {
        return $request instanceof ServerRequest;
    }
}
