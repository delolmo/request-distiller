<?php

declare(strict_types=1);

namespace DelOlmo\Distiller\Handler;

use DelOlmo\Distiller\Error\ErrorInterface;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Message\ResponseFactoryInterface as ResponseFactory;
use Psr\Http\Message\ResponseInterface as Response;

/**
 * @author Antonio del Olmo García <adelolmog@gmail.com>
 */
class ResourceNotFoundHandler implements HandlerInterface
{
    /**
     * @var type
     */
    protected $responseFactory;

    /**
     * Constructor
     *
     * @param \Psr\Http\Message\ResponseFactoryInterface $responseFactory
     */
    public function __construct(ResponseFactory $responseFactory)
    {
        $this->responseFactory = $responseFactory;
    }

    /**
     * {@inheritdoc}
     */
    public function getResponseFactory(): ResponseFactory
    {
        return $this->responseFactory;
    }

    /**
     * {@inheritdoc}
     */
    public function handle(Request $request): Response
    {
        return $this->getResponseFactory()->createResponse(404);
    }

    /**
     * {@inheritdoc}
     */
    public function supports(ErrorInterface $error): bool
    {
        return true;
    }
}
