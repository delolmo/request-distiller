<?php

declare(strict_types=1);

namespace DelOlmo\Distiller\Dto;

/**
 * @author Antonio del Olmo García <adelolmog@gmail.com>
 */
interface DtoInterface extends \ArrayAccess
{
    /**
     * Converts the Data Transfer Object to an array.
     *
     * @return array
     */
    public function toArray(): array;
}
