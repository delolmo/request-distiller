<?php

declare(strict_types = 1);

namespace DelOlmo\Distiller\Parser;

/**
 * @author Antonio del Olmo García <adelolmog@gmail.com>
 */
interface ModifierInterface
{
    /**
     *
     * @param string $pattern
     */
    public function modify(string $pattern): string;
}
