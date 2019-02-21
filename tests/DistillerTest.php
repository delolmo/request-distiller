<?php

declare(strict_types=1);

namespace DelOlmo\Distiller;

use DelOlmo\Distiller\Exception\InvalidRequestException;
use DelOlmo\Distiller\Exception\UnsupportedRequestException;
use PHPUnit\Framework\TestCase;
use Zend\Diactoros\Request;
use Zend\Diactoros\ServerRequest;
use Zend\Filter\StringToUpper;
use Zend\Filter\ToFloat;
use Zend\Filter\ToInt;
use Zend\Validator\EmailAddress;
use Zend\Validator\NotEmpty;

/**
 * @author Antonio del Olmo García <adelolmog@gmail.com>
 */
class DistillerTest extends TestCase
{
    public function testArrayPack()
    {
        $distiller = new Distiller(new ServerRequest());

        $method = new \ReflectionMethod(
            Distiller::class,
            lcfirst(substr(__FUNCTION__, 4))
        );

        $method->setAccessible(true);

        $input = [
            "element1" => [],
            "element2" => "foo bar",
            "list1" => [
                "list1Item1" => ["id" => 1],
                "list1Item2" => ["id" => 2],
            ],
            "list2" => [
                ["list2Item1" => 3],
                ["list2Item2" => 4],
            ]
        ];

        $output = $method->invoke($distiller, $input);

        $this->assertEquals(
            $output,
            [
                "element1" => [],
                "element2" => "foo bar",
                "list1.list1Item1.id" => 1,
                "list1.list1Item2.id" => 2,
                "list2.0.list2Item1" => 3,
                "list2.1.list2Item2" => 4
            ]
        );
    }

    public function testArrayPackWithAncestors()
    {
        $distiller = new Distiller(new ServerRequest());

        $method = new \ReflectionMethod(
            Distiller::class,
            lcfirst(substr(__FUNCTION__, 4))
        );

        $method->setAccessible(true);

        $input = [
            "list1" => [
                "list1Item1" => ["id" => 1],
                "list1Item2" => ["id" => 2],
            ]
        ];

        $output = $method->invoke($distiller, $input);

        $this->assertEquals(
            $output,
            [
                "list1" => [
                    "list1Item1" => ["id" => 1],
                    "list1Item2" => ["id" => 2],
                ],
                "list1.list1Item1" => ["id" => 1],
                "list1.list1Item2" => ["id" => 2],
                "list1.list1Item1.id" => 1,
                "list1.list1Item2.id" => 2,
            ]
        );
    }

    public function testArrayUnpack()
    {
        $distiller = new Distiller(new ServerRequest());

        $arrayPack = new \ReflectionMethod(
            Distiller::class,
            'arrayPack'
        );

        $arrayPack->setAccessible(true);

        $arrayUnpack = new \ReflectionMethod(
            Distiller::class,
            lcfirst(substr(__FUNCTION__, 4))
        );

        $arrayUnpack->setAccessible(true);

        $input = [
            "element1" => [],
            "element2" => "foo bar",
            "list1" => [
                "list1Item1" => ["id" => 1],
                "list1Item2" => ["id" => 2],
            ],
            "list2" => [
                ["list2Item1" => 3],
                ["list2Item2" => 4],
            ]
        ];

        $output = $arrayPack->invoke($distiller, $input);

        $this->assertEquals($input, $arrayUnpack->invoke($distiller, $output));
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

    public function testPrepareRawDataForValidation()
    {
        $request = (new ServerRequest([], [], '/', 'GET'))
            ->withAttribute("user", ["name" => "John Doe"])
            ->withAttribute("groups", [["name" => "admins"], ["name" => "users"]])
            ->withAttribute("permissions", ["permission1", "permission2"]);

        $distiller = (new Distiller($request));
        $distiller->addValidator('user.email', new NotEmpty());
        $distiller->addValidator('groups[].email', new NotEmpty());
        $distiller->addValidator('permissions[].{[0-9]{4}}', new NotEmpty());

        $method = new \ReflectionMethod(
            Distiller::class,
            lcfirst(substr(__FUNCTION__, 4))
        );

        $method->setAccessible(true);

        $output = $method->invoke($distiller);

        $this->assertEquals(
            $output,
            [
                "user" => ["name" => "John Doe"],
                "user.name" => "John Doe",
                "user.email" => null,
                "groups" => [["name" => "admins"], ["name" => "users"]],
                "groups.0" => ["name" => "admins"],
                "groups.0.name" => "admins",
                "groups.0.email" => null,
                "groups.1" => ["name" => "users"],
                "groups.1.name" => "users",
                "groups.1.email" => null,
                "permissions" => ["permission1", "permission2"],
                "permissions.0" => "permission1",
                "permissions.1" => "permission2",
            ]
        );
    }

    public function testIsValid()
    {
        $request = (new ServerRequest([], [], '/', 'GET'))
            ->withQueryParams(['email' => 'localhost@localhost.com'])
            ->withAttribute('users', [["name" => "hello@world.com"], ["name" => 'array@localhost.es']]);

        $distiller = (new Distiller($request));
        $distiller->addValidator('email', new EmailAddress());
        $distiller->addValidator('users', new NotEmpty());
        $distiller->addValidator('users[]', new NotEmpty());
        $distiller->addValidator('users[].name', new NotEmpty());

        $distiller2 = clone $distiller;
        $distiller2->addValidator('users[].email', new EmailAddress());

        $this->assertTrue($distiller->isValid());
        $this->assertFalse($distiller2->isValid());
    }

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
        $distiller->addFilter('array.option1', new ToFloat());
        $distiller->addFilter('list.0', new ToInt());

        $distiller->addCallback(function (\ArrayAccess $data) {
            $data['foo'] = 'bar';
            return $data;
        });

        $expected = [
            'email' => 'LOCALHOST@LOCALHOST.COM',
            'test' => 1,
            'foo' => 'bar',
            'array' => ['option1' => 1.0],
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
        $distiller->addValidator('test', new NotEmpty());

        $distiller->isValid();
        $errors = $distiller->getErrors();
        $this->assertEquals(1, \count($errors));

        $error = "test: Value is required and can't be empty";
        $this->assertEquals($error, $errors[0]);
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
        $request = (new ServerRequest([], [], '/', 'GET'));
        $distiller = (new Distiller($request));
        $this->expectException(UnsupportedRequestException::class);
        $distiller->getRawData();
    }
}
