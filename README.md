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
     * @Route("/users/{user-id}")
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

        // Get the user with a Data Access Object
        $user = $dao->find($userId);

        // Only beyond this point, do we begin to consider the normal behavior of the controller
        // Do stuff, like showing the user's page
        return new HtmlResponse($this->render([
            "user" => $user
        ]);
    }
}
```

When the application starts to grow and validation logic gets more complex, many controller methods get populated with a considerable amount of repeated code, making the application less maintainable and the code harder to understand.

This library aims at creating an extra layer of logic, sitting between the Router and the controller, to check if the HTTP Request is valid and keep your code clean and organized as your application grows.

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

$distiller->addValidator('email', new EmailAddress());
$distiller->addValidator('name', new NotEmpty());
$distiller->addValidator('type', new Digits());
$distiller->addFilter('name', new StringTrim());
$distiller->addFilter('type', new ToInt());

if (!$distiller->isValid()) {
    // Handle errors
    $errors = $distiller->getErrors();
    $this->addFlash('error', $errors);

    // Do more stuff, like redirecting the user
    return new RedirectResponse('/');
}

// Will return a Data Transfer Object (further reading below)
$data = $distiller->getData();

// Will return array('email' => 'localhost@localhost.com', 'name' => 'John Doe', 'type' => 1);
var_dump($data->toArray());

```

The above example shows how to do a classic validation for a request containing form data in the request body. However, Distiller objects can be used for much more.

## Enter Extractors

Extractors are objects whose main purpose is to extract variables from the request and return an array. An Extractor has to implement `ExtractorInterface`, whose only requirement is to define two methods:

- `extract(Request $request): array`. Given a certain request, an array must be returned with the variables to be processed by the Distiller.
- `supports(Request $request): bool`. Whether the extractor supports the given request.

This library comes with the following implementations of the `ExtractorInterface`:

- `DelOlmo\Extractor\AttributesExtractor`. Extracts the request attributes from the request.
- `DelOlmo\Extractor\ParsedBodyExtractor`. Extracts the parsed body from the request.
- `DelOlmo\Extractor\QueryParamsExtractor`. Extracts the query params from the request.
- `DelOlmo\Extractor\ExtractorChain`. Allows chaining extractors and merges all the extracted data using the \array_merge function.

If no Extractor is passed to the Distiller constructor, the default Extractor used is an `ExtractorChain` with the following definition:

```php
use DelOlmo\Extractor;

$extractor = new Extractor\ExtractorChain();

$extractor->attach(new Extractor\QueryParamsExtractor());
$extractor->attach(new Extractor\ParsedBodyExtractor());
$extractor->attach(new Extractor\AttributesExtractor());

return $extractor;
```

This means that all query params, body params and request attributes are extracted from the request and passed to the Distiller object for processing. This also means that validators and filters can apply to all of the above variables at will.

Be mindful that the `ExtractorChain` extracts variables sequentially. That is, if a certain variable exist with the same name as an attribute, a body parameter and a query parameter, the first takes precedence from the second, and the second takes precedence from the third. You can alter this behavior by defining your own ExtractorChain and altering the order in which extractors are attached to the chain. For example:

```php
use DelOlmo\Extractor;

$customExtractor = new Extractor\ExtractorChain();

$customExtractor->attach(new Extractor\ParsedBodyExtractor());
$customExtractor->attach(new Extractor\QueryParamsExtractor());

$customDistiller = new \DelOlmo\Distiller\Distiller($request, $customExtractor);
```

This `$customDistiller` will only process query parameters and body parameters, and query parameters will take precedence from body parameters.

## Validators, filters and callbacks

Distiller objects leverage the use of validators, filters and callbacks.

Validators are objects that return true or false when calling `isValid($value)`. Validators MUST implement `Zend\Validator\ValidatorInterface`. Zend's ValidatorInterface has one of the [simplest implementations](https://github.com/zendframework/zend-validator/blob/master/src/ValidatorInterface.php) for validating mixed values.

Filters are objects that transform a variable when `filter($value)` is called. Filters MUST implement `Zend\Filter\FilterInterface`. In a similar way to Validators, Zend's FilterInterface has a very [simple definition](https://github.com/zendframework/zend-filter/blob/master/src/FilterInterface.php). Filters will only be used if the request is valid, when calling `$distiller->getData()`. If the request is not valid, an empty array will be returned.

Callbacks are functions that apply to all the variables at once. Callbacks are only called when a request is considered valid and after applying all the Filters. Their only requirement is that they be callables.

## Organizing your Distiller objects

As you've seen, a Distiller object can be created and used anywhere, including directly in a controller. However, a better practice is to build the Distiller in a separate, standalone PHP class, which can be reused anywhere in your application. Create a new class that will house the logic for validating the HTTP request:

```php
namespace App\Distiller;

use App\Authorization\Rbac;
use App\Filter\ToUserEntity;
use App\Validator\DenyAccessUnlessGranted;
use App\Validator\UsernameExists;
use DelOlmo\Distiller\Distiller;
use Doctrine\DBAL\Connection;
use Psr\Http\Message\RequestInterface;
use Zend\Validator\EmailAddress;
use Zend\Validator\NotEmpty;

class ChangeUserEmailDistiller extends Distiller
{
    public function __construct(RequestInterface $request, Connection $connection, Rbac $rbac)
    {
        parent::__construct($request);

        // Validators for the 'credentials' field
        $this->addValidator('credentials', new DenyAccessUnlessGranted($rbac));

        // Validators for the 'user' field
        $this->addValidator('user', new NotEmpty());
        $this->addValidator('user', new UsernameExists($connection));
        $this->addFilter('user', new ToUserEntity());

        // Validators for the 'email' field
        $this->addValidator('email', new NotEmpty());
        $this->addValidator('email', new EmailAddress());
    }
}

```

This new class contains all the directions needed to validate the HTTP request. It can be used to validate the request in the controller:


```php
namespace App\Controller;

use App\Entity\User;
use Psr\Http\Message\RequestInterface as Request;
use Symfony\Component\Routing\Annotation\Route;
use Zend\Diactoros\RedirectResponse;

class UserController
{
    /**
     * @Route("/users/{user-id}", methods={"POST"})
     */
    public function changeUserEmail(Request $request)
    {
        $connection = $this->getContainer()->get('connection');
        $rbac = $this->getContainer()->get('rbac');
        $distiller = new App\Distiller\ChangeUserEmailDistiller($request, $connection, $rbac);

        if (!$distiller->isValid()) {
            // Redirect, throw a 403 error, etc.
        }

        /* @var $data DelOlmo\Distiller\Dto\DtoInterface */
        $data = $distiller->getData();

        /* @var $user App\Entity\User */
        $user = $data['user'];

        // Do stuff, like updating the User entity
        $user->setEmail($data['email']);

        // Persist the entity using a Data Access Object
        $dao->persist($user);
        $dao->flush($user);

        // Redirect upon success
        return new RedirectResponse('/users/{user-id}', [
            'user-id' => $user->getId()
        ]);
    }
}
```
## Data Transfer Objects

To allow further flexibility, the `getData` method of the `Distiller` object returns Data Transfer Objects. Data Transfer Objects are objects that implement `Dto\DtoInterface`, which in turn implements `\ArrayAccess`. This means that the returned value from `getData` can be accessed like an array, but it can also be made to hold any custom behavior that you want it to have.

In order for `Distiller` objects to know how to create `Dto` objects, a `DtoFactoryInterface` object must be passed to the constructor. When none is passed, the default `Dto\DtoFactory` will be created.
