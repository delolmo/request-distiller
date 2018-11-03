<?php

declare(strict_types=1);

namespace DelOlmo\Distiller;

use Zend\Filter\FilterInterface as Filter;
use Zend\Validator\ValidatorInterface as Validator;

/**
 * A simple interface to validate, add validation rules and filter HTTP
 * requests.
 *
 * @author Antonio del Olmo García <adelolmog@gmail.com>
 */
interface DistillerInterface
{

    /**
     * Adds a callable that will be executed after validating the request and
     * filtering all the fields.
     *
     * Callables receive the filtered data as the only argument.
     */
    public function addCallback(callable $callback);

    /**
     * Adds a new filter to the specified field.
     *
     * @param string $field
     * @param \Zend\Filter\FilterInterface $filter
     */
    public function addFilter(string $field, Filter $filter);

    /**
     * Adds a new validation rule to the specified field.
     *
     * @param string $field
     * @param \Zend\Validator\ValidatorInterface $validator
     */
    public function addValidator(string $field, Validator $validator);

    /**
     * Returns an associative array with the request data, with the key being
     * the name of the field and the value being the filtered field value.
     *
     * @return array
     * @throws \DelOlmo\Distiller\Exception\InvalidRequestException if the
     * request is not valid
     */
    public function getData(): array;

    /**
     * Returns all the validation errors from the HTTP request.
     *
     * @return array
     */
    public function getErrors(): array;

    /**
     * Returns an associative array with the request data, before validation
     * and filtering.
     *
     * @return array
     */
    public function getRawData(): array;

    /**
     * Returns true if the HTTP is valid, or false otherwise.
     *
     * @return bool
     */
    public function isValid(): bool;
}
