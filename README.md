# PHP Payload

[![Build Status](https://travis-ci.org/Ang3/php-payload.svg?branch=master)](https://travis-ci.org/Ang3/php-payload) [![Latest Stable Version](https://poser.pugx.org/ang3/php-payload/v/stable)](https://packagist.org/packages/ang3/php-payload) [![Latest Unstable Version](https://poser.pugx.org/ang3/php-payload/v/unstable)](https://packagist.org/packages/ang3/php-payload) [![Total Downloads](https://poser.pugx.org/ang3/php-payload/downloads)](https://packagist.org/packages/ang3/php-payload)

Basic and powerful payload decorator to manipulate metadata. This decorator depends on symfony component ```symfony/property-access``` to read and write data. Please see [related documentation](https://symfony.com/doc/4.3/components/property_access.html) for more informations about **property paths**.

This decorator provides the useful method ```discover``` so as to get all data property paths with some options.

## Installation

```shell
composer require ang3/php-payload
```

If you install this component outside of a Symfony application, you must require the vendor/autoload.php file in your code to enable the class autoloading mechanism provided by Composer. Read [this article](https://symfony.com/doc/current/components/using_components.html) for more details.

## Usage

**Create a payload**

```php
require_once 'vendor/autoload.php';

use Ang3\Component\Http\Payload;

// Your data...
$myData = [
  'foo' => [
    'bar' => 'baz'
  ]
];

// Create the payload instance from data (object|iterable)
$payload = Payload::create($myData);
```

- An ```InvalidArgumentException``` is thrown if the type of data is not ```object|iterable```.

You can check if a payload can be created with the static method ```supports```:

```php
if(Payload::supports($myData)) {
  $payload = Payload::create($myData);
} else {
  // The payload cannot be created
}
```

**Discover values**

This method returns an array of all flattened properties of data. Each key is a valid *property path*.

```php
// Default options of the discovery
$defaultOptions = [
  'recursive' => true, // Traverse iterable values and objects through property accessor component
  'path' => null // You can use it to insert a scope - By default we start at the root node
];

// Discover and get values in array
$values = $payload->discover($defaultOptions);

/**
 * Output: array:18 [
 *    "results[0].error.id" => 0
 *    "results[0].error.name" => "NO_ERROR"
 *    "results[0].error.description" => "No Error"
 *    "results[0].error.groupId" => 0
 *    "results[0].error.groupName" => "OK"
 *    "results[0].error.permanent" => false
 *    "results[0].id" => "8779618781693984179"
 * ]
 */

// Iterate on each value...
foreach($values as $flattenedKey => $value) {
	// ...
}


// The payload object implements interface \IteratorAggregate
// You can use it to iterate with default options
foreach($payload as $flattenedKey => $value) {
  // ...
}
```

**Check values**

```php
// Check is the path is readable
$payload->isReadable('results[0].error.name'); // Returns bool

// Check is the path is writable
$payload->isWritable('results[0].error.name'); // Returns bool
```

Whatever the type of data, you can check if the payload has at least one property.

```php
// If the payload has at least one property (not empty)
if(!$payload->isEmpty()) {
  //...
}
```

**Read values**

```php
// Get the value from a property path
$payload->get('results[0].error.name', $defaultValue = null); // Output: "NO_ERROR"

// You can get simple var thanks to the magic method "__get"
$myVar = $payload->myVar;
```

**Write values**

```php
// Update a value from a property path
$payload->set('results[0].error.name', 'UNKOWN_ERROR');

// You can set simple var thanks to the magic method "__set"
$payload->myVar = 'foo';
```

- A ```OutOfBoundsException``` is thrown when the path is not writable.
- A ```RuntimeException``` is thrown if setting value failed.

**Retrieve data**

Just use the getter:

```php
$originalData = $payload->getData();
```

### Parse a payload

I highly suggest you to read the [documentation](https://symfony.com/doc/current/components/serializer.html#encoders) of the component "Serializer" to know more about *encoding context* (options).

All encoders are created with *default context*, **except** JSON and CSV encoders:

- ```JSON```
  - By default Symfony enables the "associative" option but property paths are more complicated than objects ones in a payload mapping context. It's a personal choice but thoughtful in an API flows normalization context.
- ```CSV```
  - The option ```as_collection``` is set ```true``` (by default in ```symfony/serializer ^5.0```)

```php
// Use a static method to create th payload from a content
$payload = Payload::parseJson($data, $context = []);
$payload = Payload::parseXml($data, $context = []);
$payload = Payload::parseYaml($data, $context = []);
$payload = Payload::parseCsv($data, $context = []);

// Or parse directly a response
$payload = Payload::parseResponse($response, 'json', $context = []);
```

- A ```RuntimeException``` is thrown if decoding failed.

Of course, you can encode your payload easily too:

```php
// Encoding payload data to desired format
$json = $payload->toJson($context = []);
$xml = $payload->toXml($context = []);
$yaml = $payload->toYaml($context = []);
$csv = $payload->toCsv($context = []);
```

- A ```RuntimeException``` is thrown if decoding failed.

### Slice a payload

To get a new payload from the path of an existent payload, just call the ```slice``` method like an array:

```php
// Fake data
$myData = [
  'foo' => [
    'bar' => 'baz'
  ]
];

// Create the payload normally
$payload = Payload::create($myData);

// Slice it and get a new payload with target value
$subPayload = $payload->slice('[foo]'); // Contains just $myData['foo']
```

- An ```OutOfBoundsException``` is thrown if the path is not readable.
- An ```InvalidArgumentException``` is thrown if the type of the target value is not supported.

## Todo

- [ ] Write missing tests
- [ ] Payload related exceptions

## Run tests

```shell
$ git clone git@github.com:Ang3/php-payload.git
```

Inside root directory of the project:

```shell
$ composer install
$ ./vendor/bin/simple-phpunit
```

## Update logs

**v1.0.0**

- First release