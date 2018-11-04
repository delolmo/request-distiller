<?php

declare(strict_types=1);

namespace DelOlmo\Distiller;

use DelOlmo\Distiller\Exception\InvalidRequestException;
use DelOlmo\Distiller\Exception\UnsupportedRequestException;
use PHPUnit\Framework\TestCase;
use Zend\Diactoros\Request;
use Zend\Diactoros\ServerRequest;
use Zend\Filter\StringToUpper;
use Zend\Filter\ToInt;
use Zend\Validator\EmailAddress;
use Zend\Validator\NotEmpty;

/**
 * @author Antonio del Olmo García <adelolmog@gmail.com>
 */
class DistillerTest extends TestCase
{
    public function testGetData()
    {
        $request = (new ServerRequest([], [], '/', 'GET'))
            ->withQueryParams(['email' => 'localhost@localhost.com'])
            ->withAttribute('test', '1');

        $distiller = (new Distiller($request));
        $distiller->addFilter('email', new StringToUpper());
        $distiller->addFilter('test', new ToInt());
        $distiller->addCallback(function (array $data) {
            $data['foo'] = 'bar';
            return $data;
        });

        $this->assertEquals(['email' => 'LOCALHOST@LOCALHOST.COM', 'test' => 1, 'foo' => 'bar'], $distiller->getData());
    }

    public function testGetErrors()
    {
        $request = (new ServerRequest([], [], '/', 'GET'))
            ->withAttribute('test', '');

        $distiller = (new Distiller($request));
        $distiller->addValidator('test', new NotEmpty(), true);

        $distiller->isValid();
        $errors = $distiller->getErrors();
        $this->assertEquals(1, \count($errors));

        $error = "test: Value is required and can't be empty";
        $this->assertEquals($error, $errors[0]);
    }

    public function testGetRawData()
    {
        $request = (new ServerRequest([], [], '/', 'POST'))
            ->withQueryParams(['john' => 'doe'])
            ->withParsedBody(['foo' => 'bar'])
            ->withAttribute('test', 'value');

        $distiller = (new Distiller($request));

        $this->assertEquals(['john' => 'doe', 'foo' => 'bar', 'test' => 'value'], $distiller->getRawData());
    }

    public function testIsValid()
    {
        $request = (new ServerRequest([], [], '/', 'GET'))
            ->withQueryParams(['email' => 'localhost@localhost.com'])
            ->withAttribute('test', 'value');

        $distiller = (new Distiller($request));
        $distiller->addValidator('email', new EmailAddress(), true);
        $distiller->addValidator('test', new NotEmpty(), true);

        $this->assertTrue($distiller->isValid());
    }

    public function testThrowInvalidRequestException()
    {
        $request = (new ServerRequest([], [], '/', 'GET'))
            ->withQueryParams(['email' => 'invalid'])
            ->withAttribute('test', '1');

        $distiller = (new Distiller($request));
        $distiller->addValidator('email', new EmailAddress());
        $distiller->addFilter('test', new ToInt());

        $this->expectException(InvalidRequestException::class);
        $distiller->getData();
    }

    public function testUnsupportedRequestException()
    {
        $request = (new Request('/', 'GET'));
        $distiller = (new Distiller($request));
        $this->expectException(UnsupportedRequestException::class);
        $distiller->getRawData();
    }
}
