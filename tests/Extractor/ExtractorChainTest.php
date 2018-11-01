<?php

declare(strict_types = 1);

namespace DelOlmo\Distiller\Extractor;

use DelOlmo\Distiller\Exception\UnsupportedRequestException;
use PHPUnit\Framework\TestCase;
use Zend\Diactoros\Request;
use Zend\Diactoros\ServerRequest;

/**
 * @author Antonio del Olmo García <adelolmog@gmail.com>
 */
class ExtractorChainTest extends TestCase
{

    /**
     * @var \Psr\Http\Message\ServerRequestInterface
     */
    protected $request;

    public function setUp()
    {
        $this->request = (new ServerRequest([], [], '/'))
            ->withQueryParams(['foo' => 'bar', 'color' => 'red'])
            ->withParsedBody(['john' => 'doe', 'color' => 'blue'])
            ->withAttribute('test', 'value')
            ->withAttribute('color', 'green');
    }

    public function testExtract()
    {
        $extractor1 = new ExtractorChain();
        $extractor1->attach(new AttributesExtractor());
        $expects1   = ['test' => 'value', 'color' => 'green'];
        $this->assertEquals($expects1, $extractor1->extract($this->request));

        $extractor2 = new ExtractorChain();
        $extractor2->attach(new AttributesExtractor());
        $extractor2->attach(new QueryParamsExtractor());
        $expects2   = ['foo' => 'bar', 'test' => 'value', 'color' => 'red'];
        $this->assertEquals($expects2, $extractor2->extract($this->request));

        $extractor3 = new ExtractorChain();
        $extractor3->attach(new QueryParamsExtractor());
        $extractor3->attach(new AttributesExtractor());
        $expects3   = ['foo' => 'bar', 'test' => 'value', 'color' => 'green'];
        $this->assertEquals($expects3, $extractor3->extract($this->request));
    }

    public function testGetRequest()
    {
        $extractor = new ExtractorChain();
        $extractor->attach(new QueryParamsExtractor());
        $extractor->attach(new ParsedBodyExtractor());
        $extractor->attach(new AttributesExtractor());

        $request = (new ServerRequest([], [], '/', 'GET'))
            ->withQueryParams(['foo' => 'bar', 'color' => 'red'])
            ->withParsedBody(['john' => 'doe', 'color' => 'blue'])
            ->withAttribute('test', 'value')
            ->withAttribute('color', 'green');

        $expects = ['foo' => 'bar', 'color' => 'green', 'test' => 'value'];

        $this->assertEquals($expects, $extractor->extract($request));
    }

    public function testPostRequest()
    {
        $extractor = new ExtractorChain();
        $extractor->attach(new QueryParamsExtractor());
        $extractor->attach(new ParsedBodyExtractor());
        $extractor->attach(new AttributesExtractor());

        $request = (new ServerRequest([], [], '/', 'POST'))
            ->withQueryParams(['foo' => 'bar', 'color' => 'red'])
            ->withParsedBody(['john' => 'doe', 'color' => 'blue'])
            ->withAttribute('test', 'value')
            ->withAttribute('color', 'green');

        $expects = [
            'foo' => 'bar',
            'color' => 'green',
            'test' => 'value',
            'john' => 'doe'
        ];

        $this->assertEquals($expects, $extractor->extract($request));
    }

    public function testInvalidRequest()
    {
        $extractor = new ExtractorChain();
        $this->assertFalse($extractor->supports($this->request));

        $this->expectException(UnsupportedRequestException::class);
        $extractor->extract($this->request);
    }
}
