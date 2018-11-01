# delolmo/request-distiller

 [![Packagist Version](https://img.shields.io/packagist/v/delolmo/request-distiller.svg?style=flat-square)](https://packagist.org/packages/delolmo/request-distiller)
 [![License: MIT](https://img.shields.io/badge/License-MIT-yellow.svg)](https://opensource.org/licenses/MIT)
 [![Build Status](https://travis-ci.org/delolmo/request-distiller.svg)](https://travis-ci.org/delolmo/request-distiller)

Powerful, flexible validation and filtering for PSR-7 requests.

## Requirements

* PHP >= 7.1
* A PSR-7 http library

## Installation

This package is installable and autoloadable via Composer as [delolmo/request-distiller](https://packagist.org/packages/delolmo/request-distiller).

```sh
composer require delolmo/request-distiller
```

## Example

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
    // do something, like a redirect
}

// will return array('email' => 'localhost@localhost.com', 'name' => 'John Doe', 'type' => 1);
$data = $distiller->getData();

```

