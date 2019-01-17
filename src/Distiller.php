<?php

declare(strict_types=1);

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
use Psr\Http\Message\RequestInterface;
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
     * @param \Psr\Http\Message\RequestInterface $request
     * @param \DelOlmo\Distiller\Extractor\ExtractorInterface $extractor
     * @param \DelOlmo\Distiller\Error\ErrorFactoryInterface $errorFactory
     * @param \DelOlmo\Distiller\Dto\DtoFactoryInterface $dtoFactory
     * @param \DelOlmo\Distiller\Dto\ParserInterface $parser
     */
    public function __construct(
        RequestInterface $request,
        ExtractorInterface $extractor = null,
        ErrorFactoryInterface $errorFactory = null,
        DtoFactoryInterface $dtoFactory = null,
        ParserInterface $parser = null
    ) {
        $this->dtoFactory = $dtoFactory ?? self::createDefaultDtoFactory();
        $this->errorFactory = $errorFactory ?? self::createDefaultErrorFactory();
        $this->extractor = $extractor ?? self::createDefaultExtractor();
        $this->parser = $parser ?? self::createDefaultParser();
        $this->request = $request;
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

        // Add the new filter to the filter chain
        \call_user_func([$this->filters[$pattern], 'attach'], $filter);
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

        // Add the new validator to the validator chain
        \call_user_func([$this->validators[$pattern], 'attach'], $validator);
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
        $data = $this->dtoFactory->create();

        // The raw data, extracted from the request
        $rawData = $this->getRawData();
        $compressedRawData = self::arrayCompress($rawData);

        // Loop through all variables
        foreach ($compressedRawData as $field => $value) {
            // Immediately add field to output
            if (!isset($data[$field])) {
                $data[$field] = $value;
            }

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

        // Execute callbacks on the filtered data
        foreach ($this->callbacks as $callback) {
            $data = $callback($data);
        }

        // Return resulting data
        return $this->dtoFactory
            ->create(
                self::arrayExpand($data->toArray())
            );
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
        // Get raw data
        $rawData = $this->getRawData();
        $compressedRawData = self::arrayCompress($rawData, '', true);

        // Loop through all the validators
        foreach ($this->validators as $pattern => $validator) {
            // Parsed pattern
            $parsedPattern = $this->parser->parse($pattern);

            // Loop through all the extracted variables
            foreach ($compressedRawData as $key => $value) {
                if (\preg_match($parsedPattern, $key) !== 1) {
                    continue;
                }

                // Check to see if value is valid
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
     * Transforms a multidimensional array to a single array with string keys
     * in dot notation.
     *
     * @param array $array
     */
    protected static function arrayCompress(array $array, string $prefix = '', bool $trail = true): array
    {
        $result = array();
        foreach ($array as $key => $value) {
            if ($trail === true) {
                $result[$prefix . $key] = $value;
            }
            if (is_array($value)) {
                $result = $result + self::arrayCompress($value, $prefix . $key . '.', true);
            } else {
                $result[$prefix . $key] = $value;
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
    protected static function arrayExpand(array $array): array
    {
        $newArray = array();
        foreach ($array as $key => $value) {
            $dots = explode(".", $key);
            if (count($dots) > 1) {
                $last = &$newArray[ $dots[0] ];
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
     * @param string $pattern
     * @return string
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
