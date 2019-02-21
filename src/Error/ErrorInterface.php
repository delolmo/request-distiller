<?php

declare(strict_types=1);

namespace DelOlmo\Distiller\Error;

use Zend\Validator\ValidatorInterface;

/**
 * Represents an error that took place while validating a request.
 *
 * @author Antonio del Olmo García <adelolmog@gmail.com>
 */
interface ErrorInterface
{
    /**
     * Returns the field whose value did not pass validation.
     *
     * @return string
     */
    public function getField(): string;

    /**
     * Returns the error message.
     *
     * @return string
     */
    public function getMessage(): string;

    /**
     * Returns the validator that originated the error.
     *
     * @return \Zend\Validator\ValidatorInterface
     */
    public function getValidator(): ValidatorInterface;

    /**
     * Returns the value that did not pass validation.
     *
     * @return mixed
     */
    public function getValue();
}
