<?php

declare(strict_types=1);

namespace DelOlmo\Distiller\Error;

use PHPUnit\Framework\TestCase;
use Zend\Validator\NotEmpty;

/**
 * @author Antonio del Olmo García <adelolmog@gmail.com>
 */
class ErrorFactoryTest extends TestCase
{
    /**
     * @var \DelOlmo\Distiller\Error\ErrorFactory
     */
    protected $errorFactory;

    public function setUp()
    {
        $this->errorFactory = new ErrorFactory();
    }

    public function testCreate()
    {
        $validator = new NotEmpty();
        $errorExpected = new Error('test', 'message', $validator, 'value');

        $errorCreated = $this->errorFactory
            ->create('test', 'message', $validator, 'value');

        $this->assertEquals($errorExpected, $errorCreated);
    }
}
