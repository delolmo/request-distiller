<?php

declare(strict_types=1);

namespace DelOlmo\Distiller\Extractor;

use Psr\Http\Message\RequestInterface as Request;

/**
 * Extracts the headers from the Request.
 *
 * @author Antonio del Olmo García <adelolmog@gmail.com>
 */
class HeadersExtractor implements ExtractorInterface
{
    /**
     * {@inheritdoc}
     */
    public function extract(Request $request): array
    {
        return $request->getHeaders();
    }

    /**
     * {@inheritdoc}
     */
    public function supports(Request $request): bool
    {
        return true;
    }
}
