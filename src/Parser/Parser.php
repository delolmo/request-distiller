<?php

declare(strict_types = 1);

namespace DelOlmo\Distiller\Parser;

/**
 * @author Antonio del Olmo García <adelolmog@gmail.com>
 */
class Parser implements ParserInterface
{
    /**
     *
     * @var array
     */
    protected $modifiers = [];

    /**
     *
     * @param ModifierInterface $modifier
     */
    public function addModifier(ModifierInterface $modifier)
    {
        $this->modifiers[] = $modifier;
    }

    /**
     * {@inheritdoc}
     */
    public function parse(string $pattern): string
    {
        $matches = [];

        foreach ($this->modifiers as $modifier) {
            $pattern = $modifier->modify($pattern);
        }

        \preg_match_all('#\{(.*)\}#', $pattern, $matches, \PREG_PATTERN_ORDER);

        foreach ($matches[0] as $key => $match) {
            $pattern = \str_replace($match, "#{$key}", $pattern);
        }

        $pattern = \preg_quote($pattern);

        foreach ($matches[0] as $key => $match) {
            $pattern = \str_replace("#{$key}", substr($match, 1, -1), $pattern);
        }

        return "/^" . $pattern . "$/";
    }
}
