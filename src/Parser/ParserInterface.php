<?php

declare(strict_types = 1);

namespace DelOlmo\Distiller\Parser;

/**
 * @author Antonio del Olmo García <adelolmog@gmail.com>
 */
interface ParserInterface
{
    /**
     * Creates the regular expression to compare against the field's name.
     *
     * @param string $value
     * @return string
     */
    public function parse(string $value): string;
}
