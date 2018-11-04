<?php

declare(strict_types=1);

namespace DelOlmo\Distiller\Error;

use Zend\Validator\ValidatorInterface;

/**
 * @author Antonio del Olmo García <adelolmog@gmail.com>
 */
class Error implements ErrorInterface
{
    /**
     * @var string
     */
    protected $field;

    /**
     * @var string
     */
    protected $message;

    /**
     * @var \Zend\Validator\ValidatorInterface
     */
    protected $validator;

    /**
     * @var mixed
     */
    protected $value;

    /**
     * Constructor
     */
    public function __construct(
        string $field,
        string $message,
        ValidatorInterface $validator,
        $value
    ) {
        $this->field = $field;
        $this->message = $message;
        $this->validator = $validator;
        $this->value = $value;
    }

    /**
     * Converts the error object to a string.
     *
     * @return string
     */
    public function __toString()
    {
        return $this->getField() . ': ' . $this->getMessage();
    }

    /**
     * {@inheritdoc}
     */
    public function getField(): string
    {
        return $this->field;
    }

    /**
     * {@inheritdoc}
     */
    public function getMessage(): string
    {
        return $this->message;
    }

    /**
     * {@inheritdoc}
     */
    public function getValidator(): ValidatorInterface
    {
        return $this->validator;
    }

    /**
     * {@inheritdoc}
     */
    public function getValue()
    {
        return $this->value;
    }
}
