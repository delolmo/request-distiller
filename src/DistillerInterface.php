<?php

declare(strict_types=1);

namespace DelOlmo\Distiller;

use Zend\Filter\FilterInterface as Filter;
use Zend\Validator\ValidatorInterface as Validator;

/**
 * Una sencilla interfaz para validar, añadir reglas de validacion y filtrar los
 * valores de una petición HTTP.
 *
 * @author Antonio del Olmo García <adelolmog@gmail.com>
 */
interface DistillerInterface
{

    /**
     * Añade una función que se ejecuta después de realizar la validación y el
     * filtrado de las variables. Se le pasa como único argumento el vector de
     * datos ya filtrado.
     */
    public function addCallback(callable $callback);

    /**
     *
     * @param string $field
     * @param string $error
     */
    public function addError(string $field, string $error);

    /**
     * Añade una regla de filtrado para el campo $field.
     *
     * @param string $field
     * @param ValidatorInterface $filter
     */
    public function addFilter(string $field, Filter $filter);

    /**
     * Añade una regla de validación para el campo $field.
     *
     * @param string $field
     * @param ValidatorInterface $validator
     */
    public function addValidator(string $field, Validator $validator);

    /**
     * Devuelve un array asociativo cuyas claves son los nombres de los campos
     * y los valores son los valores, validados y filtrados, de los campos.
     *
     * @return array
     * @throws \DelOlmo\Distiller\Exception\InvalidRequestException if the
     * request is not valid
     */
    public function getData(): array;

    /**
     * Devuelve todos los errores de validación de la petición HTTP.
     *
     * @return array
     */
    public function getErrors(): array;

    /**
     * Devuelve un array asociativo cuyas claves son los nombres de los campos
     * y los valores son los valores, sin validar ni filtrar, de los campos.
     *
     * @return array
     */
    public function getRawData(): array;

    /**
     * Devuelve vedadero si la petición HTTP es válida o falso en caso
     * contrario. La petición se valida una única vez.
     *
     * @return bool
     */
    public function isValid(): bool;
}
