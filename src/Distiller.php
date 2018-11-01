<?php

declare(strict_types = 1);

namespace DelOlmo\Distiller;

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
     * @var \DelOlmo\Distiller\ExtractorInterface
     */
    protected $extractor;

    /**
     * @var \Zend\Filter\FilterInterface[]
     */
    protected $filters = [];

    /**
     * @var string[]
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
     * @param \DelOlmo\Distiller\ExtractorInterface $extractor
     */
    public function __construct(
        RequestInterface $request,
        ExtractorInterface $extractor = null
    ) {
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
    public function addError(string $field, string $error)
    {
        $this->errors[] = $field . ': ' . $error;
    }

    /**
     * {@inheritdoc}
     */
    public function addFilter(string $field, Filter $filter, ...$args)
    {
        // If the filter is not an instance of FilterChain, create it
        if (empty($this->filters[$field])) {
            $this->filters[$field] = new FilterChain();
        }

        // Arguments to call the 'attach' method in the filter chain
        \array_unshift($args, $filter);

        // Add the new filter to the filter chain
        \call_user_func_array([$this->filters[$field], 'attach'], $args);
    }

    /**
     * {@inheritdoc}
     */
    public function addValidator(string $field, Validator $validator, ...$args)
    {
        // If the validator is not an instance of ValidatorChain, create it
        if (empty($this->validators[$field])) {
            $this->validators[$field] = new ValidatorChain();
        }

        // Arguments to call the 'attach' method in the validator chain
        \array_unshift($args, $validator);

        // Add the new validator to the validator chain
        \call_user_func_array([$this->validators[$field], 'attach'], $args);
    }

    /**
     * {@inheritdoc}
     */
    public function getData(): array
    {
        if (!$this->isValid()) {
            throw new InvalidRequestException();
        }

        // Empty array to begin with
        $data = [];

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
        return count($this->errors) === 0;
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
                $validator->isValid($value, $this->getRawData())) {
                continue;
            }

            // Else generate and save all validation errors
            foreach ($validator->getMessages() as $message) {
                $this->addError($field, $message);
            }
        }
    }

    /**
     * Creates a default Extractor implementation.
     *
     * @return \DelOlmo\Distiller\Extractor\ExtractorInterface
     */
    public static function createDefaultExtractor(): ExtractorInterface
    {
        $extractor = new Extractor\ExtractorChain();

        $extractor->attach(new Extractor\QueryParamsExtractor());
        $extractor->attach(new Extractor\ParsedBodyExtractor());
        $extractor->attach(new Extractor\AttributesExtractor());

        return $extractor;
    }
}
