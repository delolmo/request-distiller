<?php

declare(strict_types=1);

namespace DelOlmo\Distiller\Handler;

use DelOlmo\Distiller\Error\ErrorInterface as Error;
use Psr\Http\Message\ResponseFactoryInterface as ResponseFactory;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * @author Antonio del Olmo García <adelolmog@gmail.com>
 */
interface HandlerInterface extends RequestHandlerInterface
{
    /**
     * Returns a ResponseFactoryInterface object.
     *
     * @return \Psr\Http\Message\ResponseFactoryInterface
     */
    public function getResponseFactory(): ResponseFactory;

    /**
     * Returns true if the error handler can handle the request, or false
     * otherwise.
     *
     * @param \DelOlmo\Distiller\Error\ErrorInterface $error
     * @return bool
     */
    public function supports(Error $error): bool;
}
