# delolmo/request-distiller

 [![Packagist Version](https://img.shields.io/packagist/v/delolmo/request-distiller.svg?style=flat-square)](https://packagist.org/packages/delolmo/request-distiller)
 [![License: MIT](https://img.shields.io/badge/License-MIT-yellow.svg)](https://opensource.org/licenses/MIT)
 [![Build Status](https://travis-ci.org/delolmo/request-distiller.svg)](https://travis-ci.org/delolmo/request-distiller)

Powerful, flexible validation and filtering for PSR-7 requests.

## Description

Frameworks usually rely on controllers to read information from the HTTP request and return a response. HTTP requests are usually mapped to controller methods by using a Router component. The Router, however, is meant to match the request using url parameters, request methods, request headers, etc. but validation itself of the request is done in the controller.

Consider the following hypothetical controller:

```php
namespace App\Controller;

use App\Entity\User;
use Psr\Http\Message\RequestInterface as Request;
use Symfony\Component\Routing\Annotation\Route;

class UserController
{
    /**
     * @Route('/users/{user-id}')
     */
    public function show(Request $request)
    {
        $userId = $request->getAttribute('user-id');

        // Check if the userId exists in the database
        // Check whether the logged user has authorization to view the user details (ACLs, RBACs, etc.)
        // Validate parameters in the request body (i.e. a form submission)
        // Other business logic to check if the request is valid
        // ...

        // Until this point, all we did was check that the Request was valid
        $user = $em->find($userId);

        // Only beyond this point, do we begin to consider the normal behavior of the controller
        // Do stuff, like showing the user's page
        return new HtmlResponse($this->render([
            "user" => $user
        ]);
    }
}
```

When the application starts to grow and validation logic gets more complex, many controller methods get populated with a considerable amount of repeated code, making the application less maintainable and the code harder to understand.

This library aims at creating an extra layer of logic, sitting between the Router and the controller, to check if the HTTP Request is valid and keep your code organized as your application grows.

## Requirements

* PHP >= 7.1
* A PSR-7 http library

## Installation

This package is installable and autoloadable via Composer as [delolmo/request-distiller](https://packagist.org/packages/delolmo/request-distiller).

```sh
composer require delolmo/request-distiller
```

## Getting started

Consider the following Psr\Http\Message\RequestInterface:

``` php
use Zend\Diactoros\ServerRequest;

$request = (new ServerRequest([], [], '/', 'POST'))
    ->withParsedBody([
        'email' => 'localhost@localhost.com'
        'name'  => 'John Doe ',
        'type'  => '1'
    ]);

```

Distiller objects allow validating and filtering such types of PSR Request objects.

``` php
use Zend\Filter\StringTrim;
use Zend\Filter\ToInt;
use Zend\Validator\Digits;
use Zend\Validator\EmailAddress;
use Zend\Validator\NotEmpty;

$distiller = new \DelOlmo\Distiller\Distiller($request);

$distiller->addValidator('email', new EmailAddress(), true);
$distiller->addValidator('name', new NotEmpty(), true);
$distiller->addValidator('type', new Digits(), true);
$distiller->addFilter('name', new StringTrim());
$distiller->addFilter('type', new ToInt());

if (!$distiller->isValid()) {
    // Do something, like a redirect
}

// Will return array('email' => 'localhost@localhost.com', 'name' => 'John Doe', 'type' => 1);
$data = $distiller->getData();

```

The above example shows how to do a classic validation for a request containing form data in the request body. However, Distiller objects can be used for much more.

## Enter Extractors

Extractors are objects whose main purpose is to extract variables from the request and return an array. An Extractor has to implement ´`ExtractorInterface`, whose only requirement is to define two methods:

- `extract(Request $request): array`. Given a certain request, an array must be returned with the variables to be processed by the Distiller.
- `supports(Request $request): bool`. Whether the extractor supports the given request.

This library comes with 4 implementations of the `ExtractorInterface`:

- `DelOlmo\Extractor\AttributesExtractor`. Extracts the request attributes from the request.
- `DelOlmo\Extractor\ParsedBodyExtractor`. Extracts the parsed body from the request.
- `DelOlmo\Extractor\QueryParamsExtractor`. Extracts the query params from the request.
- `DelOlmo\Extractor\ExtractorChain`. Allows chaining extractors and merges all the extracted data using the \array_merge function.

If no Extractor is passed to the Distiller constructor, the default Extractor used is an `ExtractorChain` following this definition:

```php
use DelOlmo\Extractor;

$extractor = new Extractor\ExtractorChain();

$extractor->attach(new Extractor\QueryParamsExtractor());
$extractor->attach(new Extractor\ParsedBodyExtractor());
$extractor->attach(new Extractor\AttributesExtractor());

return $extractor;
```

This means that all query params, body params and request attributes are extracted from the request and passed to the Distiller object for processing.

This means that validators and filters can apply to all of the above variables at will.

Be mindful that the `ExtractorChain` extracts variables sequentially. That is, if a certain variable exist with the same name as an attribute, a body parameter and a query parameter, the first takes precedence from the second, and the second takes precedence from the third. You can alter this behavior by defining your own ExtractorChain and altering the order in which extractors are attached.

## Validators, filters and callbacks

Distiller objects leverage the use of validators, filters and callbacks.

The only condition to use a Validator is that it must implement Zend\Validator\ValidatorInterface.

The only condition to use a Filter is that it must implement Zend\Filter\FilterInterface.

Callbacks have to be valid callables.

## Organizing your Distiller objects

As you've seen, a Distiller obejct can be created and used directly in a controller. However, a better practice is to build the Distiller in a separate, standalone PHP class, which can be reused anywhere in your application. Create a new class that will house the logic for validating the HTTP request:

```php

namespace App\Distiller;

use App\Filter\ToUserEntity;
use App\Validator\DenyAccessUnlessGranted;
use App\Validator\UsernameExists;
use DelOlmo\Distiller\Distiller;
use Doctrine\DBAL\Connection;
use Psr\Http\Message\RequestInterface;
use Zend\Filter\StringTrim;
use Zend\Filter\ToInt;
use Zend\Validator\Digits;
use Zend\Validator\EmailAddress;
use Zend\Validator\NotEmpty;

class ChangeUserEmailDistiller extends Distiller
{
    public function __construct(RquestInterface $request, Connection $connection)
    {
        parent::__construct($request);

        // Validators for the 'credentials' field
        $this->addValidator('credentials', new DenyAccessUnlessGranted(), true);

        // Validators for the 'username' field
        $this->addValidator('username', new NotEmpty(), true);
        $this->addValidator('username', new UsernameExists($connection), true);

        // Validators for the 'email' field
        $this->addValidator('email', new NotEmpty(), true);
        $this->addValidator('email', new EmailAddress(), true);
    }
}

```

This new class contains all the directions needed to validate the HTTP request. It can be used to validate the request in the controller:


```php
namespace App\controller;

use App\Entity\User;
use Psr\Http\Message\RequestInterface as Request;
use Symfony\Component\Routing\Annotation\Route;

class Usercontroller
{
    /**
     * @Route('/users/{user-id}')
     */
    public function show(Request $request)
    {
        $connection = $this->getContainer()->get('connection');
        $distiller = new App\Distiller\ChangeUserEmailDistiller($connection);

        if (!$distiller->isValid()) {
            // Redirect, throw a 403 error, etc.
        }

        $data = $distiller->getData();

        // Do stuff, like showing the user's page
        return new HtmlResponse($this->render([
            "user" => $data['user']
        ]);
    }
}
```


## Using an Extractor


## Creating a custom Extractor