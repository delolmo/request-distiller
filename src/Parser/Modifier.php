<?php

declare(strict_types = 1);

namespace DelOlmo\Distiller\Parser;

/**
 * @author Antonio del Olmo García <adelolmog@gmail.com>
 */
class Modifier implements ModifierInterface
{
    /**
     * @var string
     */
    protected $modifier;

    /**
     * @var string
     */
    protected $regex;

    /**
     * Constructor.
     *
     * @param string $modifier
     * @param string $regex
     */
    public function __construct(string $modifier, string $regex)
    {
        $this->modifier = $modifier;
        $this->regex = $regex;
    }

    /**
     * {@inheritdoc}
     */
    public function modify(string $pattern): string
    {
        return \str_replace($this->modifier, $this->regex, $pattern);
    }
}
