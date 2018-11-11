<?php

declare(strict_types=1);

namespace DelOlmo\Distiller\Dto;

/**
 * @author Antonio del Olmo García <adelolmog@gmail.com>
 */
class DtoFactory implements DtoFactoryInterface
{
    /**
     * {@inheritdoc}
     */
    public function create(): DtoInterface
    {
        return new Dto();
    }
}
