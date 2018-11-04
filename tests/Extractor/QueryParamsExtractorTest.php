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
class QueryParamsExtractorTest extends TestCase
{

    /**
     * @var \DelOlmo\Distiller\Extractor\QueryParamsExtractor
     */
    protected $extractor;

    /**
     * @var \Psr\Http\Message\ServerRequestInterface
     */
    protected $request;

    public function setUp()
    {
        $this->extractor = new QueryParamsExtractor();

        $request = (new ServerRequest([], [], '/'))
            ->withQueryParams([
                'test' => 'value'
            ]);

        $this->request = $request;
    }

    public function testExtract()
    {
        $data = $this->extractor->extract($this->request);
        $this->assertEquals(['test' => 'value'], $data);
    }

    public function testInvalidRequest()
    {
        $request = new Request();
        $this->assertFalse($this->extractor->supports($request));

        $this->expectException(UnsupportedRequestException::class);
        $this->extractor->extract($request);
    }

    public function testValidRequest()
    {
        $this->assertTrue($this->extractor->supports($this->request));
    }
}
