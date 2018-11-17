<?php

declare(strict_types=1);

namespace DelOlmo\Middleware;

use DelOlmo\Distiller\DistillerInterface as Distiller;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\MiddlewareInterface as Middleware;
use Psr\Http\Server\RequestHandlerInterface as Handler;

/**
 * @author Antonio del Olmo García <adelolmog@gmail.com>
 */
class DistillerMiddleware implements Middleware
{
    /**
     * @var \DelOlmo\Distiller\DistillerInterface
     */
    private $distiller;

    /**
     * @param \DelOlmo\Distiller\DistillerInterface $distiller
     */
    public function __construct(Distiller $distiller)
    {
        $this->distiller = $distiller;
    }

    /**
     * Process a request and return a response.
     */
    public function process(Request $request, Handler $handler): Response
    {
        $distiller = $this->distiller;

        if ($distiller->isValid()) {
            // do something
        }

        // do something else

        return $handler->handle($request);
    }
}
