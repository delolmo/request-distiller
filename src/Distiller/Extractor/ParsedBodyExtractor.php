<?php

declare(strict_types=1);

namespace DelOlmo\Distiller\Extractor;

use DelOlmo\Distiller\Exception\UnsupportedRequestException;
use Psr\Http\Message\RequestInterface as Request;
use Psr\Http\Message\ServerRequestInterface as ServerRequest;

/**
 * Extracts the body params from the Request, only when the parsed body is an
 * array.
 *
 * Only POST and PUT request methods are allowed since, according to the HTTP
 * specs, these are the only ones that should contain a message body.
 *
 * @author Antonio del Olmo García <adelolmog@gmail.com>
 */
class ParsedBodyExtractor implements ExtractorInterface
{
    /**
     * {@inheritdoc}
     */
    public function extract(Request $request): array
    {
        if (!$this->supports($request)) {
            throw new UnsupportedRequestException();
        }

        $parsedBody = $request->getParsedBody();

        return \is_array($parsedBody) ? $parsedBody : [];
    }

    /**
     * {@inheritdoc}
     */
    public function supports(Request $request): bool
    {
        return $request instanceof ServerRequest &&
            \in_array($request->getMethod(), ["POST", "PUT"]);
    }
}
