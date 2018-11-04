<?php

declare(strict_types=1);

namespace DelOlmo\Distiller\Error;

use DelOlmo\Distiller\Error\ErrorInterface;
use Zend\Validator\ValidatorInterface;

/**
 * A factory for creating Error objects.
 *
 * @author Antonio del Olmo García <adelolmog@gmail.com>
 */
interface ErrorFactoryInterface
{
    /**
     * Creates a new Error object.
     *
     * @param string $field
     * @param string $message
     * @param \Zend\Validator\ValidatorInterface $validator
     * @param mixed $value
     * @return \DelOlmo\Distiller\Error\ErrorInterface
     */
    public function create(
        string $field,
        string $message,
        ValidatorInterface $validator,
        $value
    ): ErrorInterface;
}
