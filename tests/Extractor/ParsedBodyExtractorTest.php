<?php

declare(strict_types=1);

namespace DelOlmo\Distiller\Extractor;

use DelOlmo\Distiller\Exception\UnsupportedRequestException;
use PHPUnit\Framework\TestCase;
use Zend\Diactoros\Request;
use Zend\Diactoros\ServerRequest;

/**
 * @author Antonio del Olmo García <adelolmog@gmail.com>
 */
class ParsedBodyExtractorTest extends TestCase
{
    /**
     * @var \DelOlmo\Distiller\Extractor\ParsedBodyExtractor
     */
    protected $extractor;

    /**
     * @var array
     */
    protected $supportedMethods = [
        "POST",
        "PUT"
    ];

    /**
     * @var array
     */
    protected $unsupportedMethods = [
        "GET",
        "HEAD",
        "DELETE",
        "CONNECT",
        "OPTIONS",
        "TRACE",
        "PATCH"
    ];

    public function setUp()
    {
        $this->extractor = new ParsedBodyExtractor();
    }

    public function testExtract()
    {
        $request = (new ServerRequest([], [], '/', 'POST'))
            ->withParsedBody([
                'test' => 'value'
            ]);

        $data = $this->extractor->extract($request);

        $this->assertEquals(['test' => 'value'], $data);
    }

    public function testInvalidRequest()
    {
        $request = new Request();
        $this->assertFalse($this->extractor->supports($request));

        foreach ($this->unsupportedMethods as $method) {
            $methodRequest = new ServerRequest([], [], '/', $method);
            $this->assertFalse($this->extractor->supports($methodRequest));
        }

        $this->expectException(UnsupportedRequestException::class);
        $this->extractor->extract($request);
    }

    public function testValidRequest()
    {
        foreach ($this->supportedMethods as $method) {
            $methodRequest = new ServerRequest([], [], '/', $method);
            $this->assertTrue($this->extractor->supports($methodRequest));
        }
    }
}
