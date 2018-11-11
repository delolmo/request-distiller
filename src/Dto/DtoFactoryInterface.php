<?php

declare(strict_types=1);

namespace DelOlmo\Distiller\Dto;

/**
 * A factory for creating Data Transfer Objects.
 *
 * @author Antonio del Olmo García <adelolmog@gmail.com>
 */
interface DtoFactoryInterface
{
    /**
     * Creates a new Data Transfer Object.
     *
     * @return \DelOlmo\Distiller\Dto\DtoInterface
     */
    public function create(): DtoInterface;
}
