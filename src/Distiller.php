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
     */
    public function __construct(
        RequestInterface $request,
        ExtractorInterface $extractor = null,
        ErrorFactoryInterface $errorFactory = null,
        DtoFactoryInterface $dtoFactory = null
    ) {
        $this->dtoFactory = $dtoFactory ?? self::createDefaultDtoFactory();
        $this->errorFactory = $errorFactory ?? self::createDefaultErrorFactory();
        $this->extractor = $extractor ?? self::createDefaultExtractor();
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
    public function addFilter(string $field, Filter $filter)
    {
        // If the filter is not an instance of FilterChain, create it
        if (empty($this->filters[$field])) {
            $this->filters[$field] = new FilterChain();
        }

        // Add the new filter to the filter chain
        \call_user_func([$this->filters[$field], 'attach'], $filter);
    }

    /**
     * {@inheritdoc}
     */
    public function addValidator(string $field, Validator $validator)
    {
        // If the validator is not an instance of ValidatorChain, create it
        if (empty($this->validators[$field])) {
            $this->validators[$field] = new ValidatorChain();
        }

        // Add the new validator to the validator chain
        \call_user_func([$this->validators[$field], 'attach'], $validator);
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

        // Filter raw values
        foreach ($rawData as $field => $value) {
            $filter = $this->filters[$field] ?? null;

            $data[$field] = empty($filter) ? $value : $filter->filter($value);
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
        return self::arrayCompress(
            $this->extractor->extract($this->request)
        );
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

        foreach ($this->validators as $field => $validator) {
            // The field's value
            $value = $rawData[$field] ?? null;

            // If everything is correct, jump to next field
            if (empty($validator) ||
                $validator->isValid($value)) {
                continue;
            }

            // Else generate and save all validation errors
            foreach ($validator->getMessages() as $message) {
                $error = $this->errorFactory
                    ->create($field, $message, $validator, $value);
                $this->errors[] = $error;
            }
        }
    }

    /**
     * Transforms a multidimensional array to a single array with string keys
     * in dot notation.
     *
     * @param array $array
     * @param string $prefix
     */
    protected static function arrayCompress(array $array, string $prefix = ''): array
    {
        $result = array();
        foreach ($array as $key => $value) {
            if (is_array($value)) {
                $result = $result + self::arrayFlatten($value, $prefix . $key . '.');
            } else {
                $result[$prefix.$key] = $value;
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
}
