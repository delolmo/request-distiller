<?php

declare(strict_types = 1);

namespace DelOlmo\Distiller;

use DelOlmo\Distiller\Dto\DtoFactory;
use DelOlmo\Distiller\Dto\DtoFactoryInterface;
use DelOlmo\Distiller\Dto\DtoInterface;
use DelOlmo\Distiller\Error\ErrorFactory;
use DelOlmo\Distiller\Error\ErrorFactoryInterface;
use DelOlmo\Distiller\Exception\InvalidRequestException;
use DelOlmo\Distiller\Extractor\ExtractorInterface;
use DelOlmo\Distiller\Parser\Modifier;
use DelOlmo\Distiller\Parser\Parser;
use DelOlmo\Distiller\Parser\ParserInterface;
use Psr\Http\Message\ServerRequestInterface;
use Zend\Filter\FilterChain;
use Zend\Filter\FilterInterface as Filter;
use Zend\Validator\ValidatorChain;
use Zend\Validator\ValidatorInterface as Validator;

/**
 * @author Antonio del Olmo García <adelolmog@gmail.com>
 */
class Distiller implements DistillerInterface
{

    /**
     * @var callable[]
     */
    protected $callbacks = [];

    /**
     * @var \DelOlmo\Distiller\Dto\DtoFactoryInterface
     */
    protected $dtoFactory;

    /**
     * @var \DelOlmo\Distiller\Extractor\ExtractorInterface
     */
    protected $extractor;

    /**
     * @var \DelOlmo\Distiller\Error\ErrorFactoryInterface
     */
    protected $errorFactory;

    /**
     * @var \Zend\Filter\FilterInterface[]
     */
    protected $filters = [];

    /**
     * @var \DelOlmo\Distiller\Error\ErrorInterface[]
     */
    protected $errors = [];

    /**
     * @var \DelOlmo\Distiller\Parser\ParserInterface
     */
    protected $parser;

    /**
     * @var \Zend\Validator\ValidatorInterface[]
     */
    protected $validators = [];

    /**
     * @var \Psr\Http\Message\ServerRequestInterface
     */
    protected $request;

    /**
     * @var bool
     */
    protected $isValidated = false;

    /**
     * Constructor
     *
     * @param \Psr\Http\Message\ServerRequestInterface $request
     * @param \DelOlmo\Distiller\Extractor\ExtractorInterface|null $extractor
     * @param \DelOlmo\Distiller\Error\ErrorFactoryInterface|null $errorFactory
     * @param \DelOlmo\Distiller\Dto\DtoFactoryInterface|null $dtoFactory
     * @param \DelOlmo\Distiller\Parser\ParserInterface|null $parser
     */
    public function __construct(
        ServerRequestInterface $request,
        ExtractorInterface $extractor = null,
        ErrorFactoryInterface $errorFactory = null,
        DtoFactoryInterface $dtoFactory = null,
        ParserInterface $parser = null
    ) {
        $this->dtoFactory   = $dtoFactory ?? self::createDefaultDtoFactory();
        $this->errorFactory = $errorFactory ?? self::createDefaultErrorFactory();
        $this->extractor    = $extractor ?? self::createDefaultExtractor();
        $this->parser       = $parser ?? self::createDefaultParser();
        $this->request      = $request;
    }

    /**
     * {@inheritdoc}
     */
    public function addCallback(callable $callback)
    {
        $this->callbacks[] = $callback;
    }

    /**
     * {@inheritdoc}
     */
    public function addFilter(string $pattern, Filter $filter)
    {
        // If the filter is not an instance of FilterChain, create it
        if (empty($this->filters[$pattern])) {
            $this->filters[$pattern] = new FilterChain();
        }

        /* @var $callable callable|array */
        $callable = [$this->filters[$pattern], 'attach'];

        // Test if $callable is actually callable
        if (!\is_callable($callable)) {
            $message = "Trying to call 'attach' on FilterChain, but for "
                . "some reason it is not callable.";
            throw new \Exception($message);
        }

        // Add the new filter to the filter chain
        \call_user_func($callable, $filter);
    }

    /**
     * {@inheritdoc}
     */
    public function addValidator(string $pattern, Validator $validator)
    {
        // If the validator is not an instance of ValidatorChain, create it
        if (empty($this->validators[$pattern])) {
            $this->validators[$pattern] = new ValidatorChain();
        }

        /* @var $callable callable|array */
        $callable = [$this->validators[$pattern], 'attach'];

        // Test if $callable is actually callable
        if (!\is_callable($callable)) {
            $message = "Trying to call 'attach' on ValidatorChain, but for "
                . "some reason it is not callable.";
            throw new \Exception($message);
        }

        // Add the new validator to the validator chain
        \call_user_func($callable, $validator);
    }

    /**
     * {@inheritdoc}
     */
    public function getData(): DtoInterface
    {
        if (!$this->isValid()) {
            throw new InvalidRequestException();
        }

        // Create the Data Transfer Object
        $data = array();

        // The raw data, extracted from the request
        $rawData = $this->prepareRawDataForFiltering();

        // Loop data from the deepest levels to the shallowest, while unpacking
        // the array on each step.
        foreach ($rawData as $depth => $values) {
            // Add current depth level to results
            $data = $data + $rawData[$depth];

            foreach ($values as $field => $value) {
                // Loop through all filters
                foreach ($this->filters as $pattern => $filter) {
                    // Parsed pattern
                    $parsedPattern = $this->parser->parse($pattern);

                    // If the pattern does not match, continue
                    if (\preg_match($parsedPattern, $field) !== 1) {
                        continue;
                    }

                    // Add filtered value to data object
                    $data[$field] = $filter->filter($data[$field]);
                }
            }

            // Unpack data for next step
            $data = self::arrayUnpack($data);
        }

        // Transform to data transfer object
        $data = $this->dtoFactory->create($data);

        // Execute callbacks on the filtered data
        foreach ($this->callbacks as $callback) {
            $data = $callback($data);
        }

        // Return resulting data
        return $data;
    }

    /**
     * {@inheritdoc}
     */
    public function getErrors(): array
    {
        return $this->errors;
    }

    /**
     * {@inheritdoc}
     */
    public function getRawData(): array
    {
        return $this->extractor->extract($this->request);
    }

    /**
     * {@inheritdoc}
     */
    public function isValid(): bool
    {
        // If the form has not been validated yet
        if (!$this->isValidated) {
            $this->validate();
            $this->isValidated = true;
        }

        // The form is valid if and only if no error was generated
        return \count($this->errors) === 0;
    }

    /**
     * @return void
     */
    protected function validate()
    {
        /* @var $rawData array */
        $rawData = $this->prepareRawDataForValidation();

        // Loop through all the validators
        foreach ($this->validators as $pattern => $validator) {
            // The pattern that must be matched
            $parsedPattern = $this->parser->parse($pattern);

            // Loop all variables in raw data
            foreach ($rawData as $key => $value) {
                // Check to see if there is a match
                if (preg_match($parsedPattern, $key) !== 1) {
                    continue;
                }

                // If everything is correct, jump to next field
                if (empty($validator) ||
                    $validator->isValid($value)) {
                    continue;
                }

                // Else generate and save all validation errors
                foreach ($validator->getMessages() as $message) {
                    $error = $this->errorFactory
                            ->create($key, $message, $validator, $value);
                    $this->errors[] = $error;
                }
            }
        }
    }

    /**
     * Divides the packed array of raw data into levels, so that the data can be
     * filtered from the deepest to the shallowest level.
     *
     * @return array
     */
    protected function prepareRawDataForFiltering(): array
    {
        /* @var $rawData array */
        $rawData = self::arrayPack($this->getRawData());

        $results = [];

        foreach ($rawData as $key => $value) {
            $parts = explode(".", $key);
            $results[count($parts)][$key] = $value;
        }

        ksort($results);

        return array_reverse($results);
    }

    /**
     * This function should fill in with nulls any field that has a validation
     * rule attached but has not been sent with the Request object.
     *
     * @return array
     */
    protected function prepareRawDataForValidation(): array
    {
        /* @var $rawData array */
        $rawData = $this->getRawData();

        /* @var $packedRawData array */
        $packedRawData = self::arrayPackWithAncestors($rawData);

        /* @var $patterns array */
        $patterns = array_keys($this->validators);

        foreach ($patterns as $pattern) {
            // The pattern against which fields are matched
            $patternElement = $this->parser->parse($pattern);

            // Every part of the pattern, separated by dots
            $patternParts = explode("\.", trim($patternElement, "/^$"));

            // The last element of the pattern
            $elementPart = array_slice($patternParts, -1, 1)[0];

            // We cannot add nulls to a regular expression, that's up to the user
            if ($elementPart !== \preg_quote($elementPart)) {
                continue;
            }

            // The pattern for the parent element
            $parentParts = array_slice($patternParts, 0, -1);

            // The pattern that must match all parents
            $parentPattern = implode("\.", $parentParts);

            // If there are no parents, simply add nulls using the element part
            if (empty($parentPattern)) {
                if (!isset($packedRawData[$elementPart])) {
                    $packedRawData[$elementPart] = null;
                }
                continue;
            }

            // The exact pattern for the parents
            $parentPattern = "/^{$parentPattern}$/";

            // Function to filter all the parents
            $filterFn = function ($key) use ($parentPattern) {
                return preg_match($parentPattern, $key) === 1;
            };

            // Get all parents
            $parentElements = \array_filter($packedRawData, $filterFn, \ARRAY_FILTER_USE_KEY);

            // Loop through all parents
            foreach ($parentElements as $parentKey => $parentValue) {
                // The pattern for all the descendants
                $parentDescendants = "/^" . preg_quote($parentKey) . "\./";

                $filterFn = function ($key) use ($parentDescendants) {
                    return preg_match($parentDescendants, $key) === 1;
                };

                // Get all descendants of this parent
                $descendantElements = \array_filter($packedRawData, $filterFn, \ARRAY_FILTER_USE_KEY);

                $match = false;

                foreach ($descendantElements as $descendantKey => $decendantValue) {
                    if (\preg_match($patternElement, $descendantKey)) {
                        $match = true;
                        break;
                    }
                }

                if ($match === false) {
                    $packedRawData[$parentKey . ".{$elementPart}"] = null;
                }
            }
        }

        return $packedRawData;
    }

    /**
     * Transforms a multidimensional array to a single array with string keys
     * in dot notation.
     *
     * @param array $array
     * @param string $prefix
     */
    protected static function arrayPack(array $array, string $prefix = ''): array
    {
        $result = array();
        foreach ($array as $key => $value) {
            if (\is_array($value) && \count($value) !== 0) {
                $result = $result + self::arrayPack($value, $prefix . $key . '.');
            } else {
                $result[$prefix . $key] = $value;
            }
        }
        return $result;
    }

    /**
     * Transforms a multidimensional array to a single array with string keys
     * in dot notation, but all ancestors are kept untouched in the result.
     *
     * @param array $array
     * @param string $prefix
     * @return array
     */
    protected static function arrayPackWithAncestors(array $array, string $prefix = ''): array
    {
        $result = array();
        foreach ($array as $key => $value) {
            $result[$prefix . $key] = $value;
            if (\is_array($value) && \count($value) !== 0) {
                $result = $result + self::arrayPackWithAncestors($value, $prefix . $key . '.');
            }
        }
        return $result;
    }

    /**
     * Transform an array with string keys in dot notation to a multidimesional
     * array.
     *
     * @param array $array
     * @return array
     */
    protected static function arrayUnpack(array $array): array
    {
        $newArray = array();
        foreach ($array as $key => $value) {
            $dots = explode(".", $key);
            if (count($dots) > 1) {
                $last = &$newArray[$dots[0]];
                foreach ($dots as $k => $dot) {
                    if ($k == 0) {
                        continue;
                    }
                    $last = &$last[$dot];
                }
                $last = $value;
            } else {
                $newArray[$key] = $value;
            }
        }
        return $newArray;
    }

    /**
     * Creates a default DtoFactory implementation.
     *
     * @return \DelOlmo\Distiller\Dto\DtoFactoryInterface
     */
    protected static function createDefaultDtoFactory(): DtoFactoryInterface
    {
        return new DtoFactory();
    }

    /**
     * Creates a default Extractor implementation.
     *
     * @return \DelOlmo\Distiller\Extractor\ExtractorInterface
     */
    protected static function createDefaultExtractor(): ExtractorInterface
    {
        $extractor = new Extractor\ExtractorChain();

        $extractor->attach(new Extractor\QueryParamsExtractor());
        $extractor->attach(new Extractor\ParsedBodyExtractor());
        $extractor->attach(new Extractor\AttributesExtractor());

        return $extractor;
    }

    /**
     * Creates a default ErrorFactor implementation.
     *
     * @return \DelOlmo\Distiller\Error\ErrorFactoryInterface
     */
    protected static function createDefaultErrorFactory(): ErrorFactoryInterface
    {
        return new ErrorFactory();
    }

    /**
     * Creates the regular expression to compare against the field's name.
     *
     * @return \DelOlmo\Distiller\Parser\ParserInterface
     */
    protected static function createDefaultParser(): ParserInterface
    {
        // Create default parser
        $parser = new Parser();

        // Add [] as a shortcut
        $parser->addModifier(new Modifier("[]", ".{[0-9]*}"));

        // Add [uuid] as a shortcut
        $parser->addModifier(
            new Modifier(
                "[uuid]",
                "{([a-fA-F0-9]{8}-(?:[a-fA-F0-9]{4}-){3}[a-fA-F0-9]{12}){1}}"
            )
        );

        // Finally, return default parser
        return $parser;
    }
}
