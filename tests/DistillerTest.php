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
            ->withAttribute('test', '1')
            ->withAttribute('array', ['option1' => '1'])
            ->withAttribute('list', ['2', 'foo']);

        $distiller = (new Distiller($request));
        $distiller->addFilter('email', new StringToUpper());
        $distiller->addFilter('test', new ToInt());
        $distiller->addFilter('array.option1', new ToInt());
        $distiller->addFilter('list.0', new ToInt());
        $distiller->addCallback(function (\ArrayAccess $data) {
            $data['foo'] = 'bar';
            return $data;
        });

        $expected = [
            'email' => 'LOCALHOST@LOCALHOST.COM',
            'test' => 1,
            'foo' => 'bar',
            'array' => ['option1' => 1],
            'list' => [2, 'foo']
        ];
        $result = $distiller->getData()->toArray();
        $this->assertEquals($expected['email'], $distiller->getData()['email']);
        $this->assertEquals($expected['test'], $distiller->getData()['test']);
        $this->assertEquals($expected['foo'], $distiller->getData()['foo']);
        $this->assertEquals($expected, $result);
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
        // Test unidimensional array
        $request1 = (new ServerRequest([], [], '/', 'POST'))
            ->withQueryParams(['john' => 'doe'])
            ->withParsedBody(['foo' => 'bar'])
            ->withAttribute('test', 'value');

        $distiller1 = (new Distiller($request1));

        $this->assertEquals(
            [
                'john' => 'doe',
                'foo' => 'bar',
                'test' => 'value'
            ],
            $distiller1->getRawData()
        );

        // Test multidimensional array
        $request2 = (new ServerRequest([], [], '/', 'POST'))
            ->withQueryParams(['john' => 'doe'])
            ->withParsedBody(['foo' => ['bar', 'baz']])
            ->withAttribute('test', 'value');

        $distiller2 = (new Distiller($request2));

        $this->assertEquals(
            [
                'john' => 'doe',
                'foo' => ['bar', 'baz'],
                'test' => 'value'
            ],
            $distiller2->getRawData()
        );
    }

    public function testIsValid()
    {
        $request = (new ServerRequest([], [], '/', 'GET'))
            ->withQueryParams(['email' => 'localhost@localhost.com'])
            ->withAttribute('testAssoc', ["hello" => 'world'])
            ->withAttribute('testNonAssoc', [["name" => "hello@world.com"], ["name" => 'array@localhost.es']]);

        $distiller = (new Distiller($request));
        $distiller->addValidator('email', new EmailAddress(), true);
        $distiller->addValidator('testAssoc.{[a-z]{4}}', new NotEmpty(), true);
        $distiller->addValidator('testNonAssoc[].name', new EmailAddress(), true);

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
