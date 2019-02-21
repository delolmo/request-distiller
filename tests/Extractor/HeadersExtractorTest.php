<?php

declare(strict_types=1);

namespace DelOlmo\Distiller\Extractor;

use PHPUnit\Framework\TestCase;
use Zend\Diactoros\ServerRequest;

/**
 * @author Antonio del Olmo García <adelolmog@gmail.com>
 */
class HeadersExtractorTest extends TestCase
{

    /**
     * @var \DelOlmo\Distiller\Extractor\HeadersExtractor
     */
    protected $extractor;

    /**
     * @var \Psr\Http\Message\ServerRequestInterface
     */
    protected $request;

    public function setUp()
    {
        $this->extractor = new HeadersExtractor();

        $request = (new ServerRequest([], [], '/'))
            ->withHeader('test', 'value');

        $this->request = $request;
    }

    public function testExtract()
    {
        $data = $this->extractor->extract($this->request);
        $this->assertEquals(['test' => ['value']], $data);
    }

    public function testSupports()
    {
        $this->assertTrue($this->extractor->supports($this->request));
    }
}
