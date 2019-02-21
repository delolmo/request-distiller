<?php

declare(strict_types=1);

namespace DelOlmo\Distiller\Extractor;

use DelOlmo\Distiller\Exception\UnsupportedRequestException;
use Psr\Http\Message\RequestInterface as Request;

/**
 * A chain of extractors, whose extracted values are merged using the
 * \array_merge function.
 *
 * Variables are merged sequentially, in the same order that the extractors
 * where added to the chain.
 *
 * A chain is said to support a request if at least one of the extractors
 * supports it.
 *
 * If one of the extractors does not support the given Request, it will be
 * skipped.
 *
 * @author Antonio del Olmo García <adelolmog@gmail.com>
 */
class ExtractorChain implements ExtractorInterface
{

    /**
     * @var \DelOlmo\Distiller\Extractor\ExtractorInterface[]
     */
    protected $extractors;

    /**
     * Constructor
     *
     * @param \DelOlmo\Distiller\Extractor\ExtractorInterface[] ...$extractors
     */
    public function __construct(ExtractorInterface ...$extractors)
    {
        $this->extractors = $extractors;
    }

    /**
     * {@inheritdoc}
     */
    public function attach(ExtractorInterface $extractor)
    {
        $this->extractors[] = $extractor;
    }

    /**
     * {@inheritdoc}
     */
    public function extract(Request $request): array
    {
        if (!$this->supports($request)) {
            throw new UnsupportedRequestException();
        }

        $params = array();

        foreach ($this->extractors as $extractor) {
            if ($extractor->supports($request)) {
                $extracted = $extractor->extract($request);
                $params = \array_merge($params, $extracted);
            }
        }

        return $params;
    }

    /**
     * {@inheritdoc}
     */
    public function supports(Request $request): bool
    {
        foreach ($this->extractors as $extractor) {
            if ($extractor->supports($request)) {
                return true;
            }
        }

        return false;
    }
}
