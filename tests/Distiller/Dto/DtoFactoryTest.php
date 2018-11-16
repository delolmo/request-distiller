<?php

declare(strict_types=1);

namespace DelOlmo\Distiller\Dto;

use PHPUnit\Framework\TestCase;

/**
 * @author Antonio del Olmo García <adelolmog@gmail.com>
 */
class DtoFactoryTest extends TestCase
{
    protected $dtoFactory;

    public function setUp()
    {
        $this->dtoFactory = new DtoFactory();
    }

    public function testCreateWithoutData()
    {
        $dto = $this->dtoFactory->create();
        $this->assertEquals(Dto::class, get_class($dto));
        $this->assertEquals([], $dto->toArray());
    }

    public function testCreateWithData()
    {
        $dto = $this->dtoFactory->create(['test' => 'value']);
        $this->assertEquals(Dto::class, get_class($dto));
        $this->assertEquals(['test' => 'value'], $dto->toArray());
    }
}
