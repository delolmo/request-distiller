<?php

declare(strict_types=1);

namespace DelOlmo\Distiller;

use PHPUnit\Framework\TestCase;
use Zend\Validator\NotEmpty;

/**
 * @author Antonio del Olmo García <adelolmog@gmail.com>
 */
class ErrorTest extends TestCase
{
    /**
     * @var \DelOlmo\Distiller\Error
     */
    protected $error;

    /**
     * @var \Zend\Validator\NotEmpty
     */
    protected $validator;

    public function setUp()
    {
        $this->validator = new NotEmpty();
        $this->error = new Error('test', 'message', $this->validator, 'value');
    }

    public function testGetField()
    {
        $this->assertSame('test', $this->error->getField());
    }

    public function testGetMessage()
    {
        $this->assertSame('message', $this->error->getMessage());
    }

    public function testGetValidator()
    {
        $this->assertSame($this->validator, $this->error->getValidator());
    }

    public function testGetValue()
    {
        $this->assertSame('value', $this->error->getValue());
    }
}
