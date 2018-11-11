<?php

declare(strict_types=1);

namespace DelOlmo\Distiller\Dto;

use PHPUnit\Framework\TestCase;

/**
 * @author Antonio del Olmo García <adelolmog@gmail.com>
 */
class DtoTest extends TestCase
{
    /* @var $dto \DelOlmo\Distiller\Dto\Dto */
    protected $dto;

    public function setUp()
    {
        $this->dto = new Dto(['hello' => 'world']);

        $this->dto['test'] = 'value';
        $this->dto['foo'] = 'bar';
    }

    public function testIsset()
    {
        $this->assertFalse(isset($this->dto['john']));
        $this->assertTrue(isset($this->dto['hello']));
        $this->assertTrue(isset($this->dto['test']));
        $this->assertTrue(isset($this->dto['foo']));
    }

    public function testGetOffset()
    {
        $this->assertEquals('world', $this->dto['hello']);
        $this->assertEquals('value', $this->dto['test']);
        $this->assertEquals('bar', $this->dto['foo']);
    }

    public function testUnset()
    {
        unset($this->dto['test']);
        $this->assertEquals(['hello' => 'world', 'foo' => 'bar'], $this->dto->toArray());
    }

    public function testToArray()
    {
        $expected = ['hello' => 'world', 'test' => 'value', 'foo' => 'bar'];
        $this->assertEquals($expected, $this->dto->toArray());
    }
}
