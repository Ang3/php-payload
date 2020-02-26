# PHP Payload

[![Build Status](https://travis-ci.org/Ang3/php-payload.svg?branch=master)](https://travis-ci.org/Ang3/php-payload) [![Latest Stable Version](https://poser.pugx.org/ang3/php-payload/v/stable)](https://packagist.org/packages/ang3/php-payload) [![Latest Unstable Version](https://poser.pugx.org/ang3/php-payload/v/unstable)](https://packagist.org/packages/ang3/php-payload) [![Total Downloads](https://poser.pugx.org/ang3/php-payload/downloads)](https://packagist.org/packages/ang3/php-payload)

Basic and powerful payload decorator to manipulate metadata. This decorator depends on symfony component ```symfony/property-access``` to read and write data. Please see [related documentation](https://symfony.com/doc/4.3/components/property_access.html) for more informations about **property paths**.

This decorator provides the useful method ```discover``` so as to get all data property paths with some options.

## Installation

You can install the component in 2 different ways:

- Install it via Composer (ang3/php-payload on Packagist)
- Use the official Git repository (https://github.com/Ang3/php-payload).

Then, require the vendor/autoload.php file to enable the autoloading mechanism provided by Composer. 
Otherwise, your application won't be able to find the classes of this component.

## Usage

### Create a payload

```php
require_once 'vendor/autoload.php';

use Ang3\Component\Http\Payload;

// Your data... Whatever...
$data = json_decode('{
    "results": [
        {
            "error": {
                "id": 0,
                "name": "NO_ERROR",
                "description": "No Error",
                "groupId": 0,
                "groupName": "OK",
                "permanent": false
            },
            "id": "8779618781693984179",
        }
    ]
}');

// Create the payload instance from data (mixed)
$payload = new Payload($data); // No exception
```

### Discover values

```php
// ...

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

On each operation, the payload normalizes data so as to be able to traverse all types of variables.

### Check values

```php
// ...

// Check is the path is readable
$payload->isReadable('results[0].error.name'); // Returns bool

// Check is the path is writable
$payload->isWritable('results[0].error.name'); // Returns bool
```

Whatever the type of data, you can check if the payload has at least one property.

```php
// ...

// If the payload has at least one property (not empty)
if(!$payload->isEmpty()) {
  //...
}
```

### Read values

```php
// ...

// Get the value from a property path
$payload->get('results[0].error.name', $defaultValue = null); // Output: "NO_ERROR"

// You can get simple var thanks to the magic method "__get"
$myVar = $payload->myVar;
```

### Write values

```php
// ...

// Update a value from a property path
$payload->set('results[0].error.name', 'UNKOWN_ERROR');

// You can set simple var thanks to the magic method "__set"
$payload->myVar = 'foo';
```

### Retrieve original data

```php
// ...
// 
// Do not forget main things..
$originalData = $payload->getData();
```

### Parse a payload

I highly suggest you to read the [documentation]((https://symfony.com/doc/current/components/serializer.html#encoders)) of the component to know more about *encoding context* (options).

All encoders are created *on demand* and locally cached with *default context*, **except** JSON encoder. Indeed, by default Symfony enables the "associative" option but property paths are more complicated than objects ones in a payload mapping context. It's a personal choice but thoughtful in an API flows normalization context.

```php
// ...

// Use a static method to create th payload from a content
$payload = Payload::parseJson($data, $context = []);
$payload = Payload::parseXml($data, $context = []);
$payload = Payload::parseYaml($data, $context = []);
$payload = Payload::parseCsv($data, $context = []);

// Or parse directly a response or a request
// DO NOT FORGET TO INSTALL NEEDED PACKAGES
$payload = Payload::parseResponse($response, 'json', $context = []); // formats: 'json', 'xml', 'yaml', 'csv'
```

- A ```RuntimeException``` is thrown if a package is missing or decoding failed.

Of course, you can encode your payload easily too:

```php
// ...

// Encoding payload data to desired format
$json = $payload->toJson($context = []);
$xml = $payload->toXml($context = []);
$yaml = $payload->toYaml($context = []);
$csv = $payload->toCsv($context = []);
```

- A ```RuntimeException``` is thrown if a package is missing or decoding failed.

### Slice a payload

To get a new payload from the path of an existent payload, just call the ```slice``` method like an array:

```php
// ...

// Fake data
$myData = [
  'foo' => [
    'bar' => 'baz'
  ]
];

// Create the payload normally
$payload = new Payload($myData);

// Slice it and get a new Payload with target value
$subPayload = $payload->slice('[foo][bar]'); // Contains just 'baz'
```

- An ```OutOfBoundsException``` is thrown if the path is not readable.

## Todo

- [ ] Payload related exceptions?

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

### v4.0.0

- Implemented normalizer ```PayloadPropertyNormalizer```
- Packages ```symfony/serializer``` and ```psr/http-message``` installed by default.

### v3.2.0

- Fixed private method ```getObjectProperties``` by checking readability of property by the accessor.
- Added method ```slice``` which returns a part of a payload in a new Payload instance.
- Added method ```isEmpty``` which returns true when the payload has no property.
- Replaced exception ```InvalidArgumentException``` by ```OutOfBoundsException``` for method ```set``` when a path is not writable.
- Fixed indentation in composer.json
- Deleted discovery option ```ignore_null```. The aim is to discover *all readable* properties.

### v3.1.0

- Added EncodingTrait for encoding support of formats ```json```, ```xml```, ```yaml``` and ```csv``` from package ```symfony/serializer```
  - Added static method ```parseJson```
  - Added static method ```parseXml```
  - Added static method ```parseYaml```
  - Added static method ```parseCsv```
  - Added method ```toJson```
  - Added method ```toXml```
  - Added method ```toYaml```
  - Added method ```toCsv```
  - Added method ```buildHttpQuery``` (see [http_build query function](https://www.php.net/manual/fr/function.http-build-query.php))
- Added static method ```createFromUrl``` so as to create a payload from ```$_GET```
- Added static method ```parseResponse``` to parse response interface from package ```psr/http-message```

### v3.0.0

- Deleted method ```has```
- Added methods ```isReadable``` and ```isWritable```
- Updated reading process of object properties
- Updated phpunit dependency for security reasons

### v2.0.1

- Remove data strict type.

### v2.0.0

- Added magic method ```__set```
- Added methods ```isArray``` and ```isObject```
- Reamed method ```iterate``` to ```discover```
- Renamed method ```setValue``` to ```set```
- Renamed method ```getValue``` to ```get```
- Deleted encoding/decoding features
- Deleted static method ```parse```
- Deleted method ```normalize```
- Deleted method ```iterate```
- Deleted custom exceptions
- Implemented tests
- Updated composer dependencies

### v1.0.3

- Added method ```normalize```
- Updated property accessor config
- Implemented method ```parse``` to replace methods ```createFromRequest``` and ```createFromRequest```

### v1.0.2

- Added ```scope``` options in payload discover method
- Added magic method ```toString```

### v1.0.1

- Implemented payload exceptions