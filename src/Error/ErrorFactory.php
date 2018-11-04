<?php

declare(strict_types=1);

namespace DelOlmo\Distiller\Error;

use Zend\Validator\ValidatorInterface;

/**
 * @author Antonio del Olmo García <adelolmog@gmail.com>
 */
class ErrorFactory implements ErrorFactoryInterface
{
    /**
     * {@inheritdoc}
     */
    public function create(
        string $field,
        string $message,
        ValidatorInterface $validator,
        $value
    ): ErrorInterface {
        return new Error($field, $message, $validator, $value);
    }
}
