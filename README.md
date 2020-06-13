# README

[![Travis Build Status](https://api.travis-ci.com/bresam/ivory-serializer-bundle.svg?branch=master)](https://travis-ci.com/github/bresam/ivory-serializer-bundle)
[![Code Coverage](https://scrutinizer-ci.com/g/bresam/ivory-serializer-bundle/badges/coverage.png?b=master)](https://scrutinizer-ci.com/g/bresam/ivory-serializer-bundle/?branch=master)
[![Scrutinizer Code Quality](https://scrutinizer-ci.com/g/bresam/ivory-serializer-bundle/badges/quality-score.png?b=master)](https://scrutinizer-ci.com/g/bresam/ivory-serializer-bundle/?branch=master)

The bundle provides an integration of the [Ivory Serializer](https://github.com/bresam/ivory-serializer) library for
your Symfony project.

``` php
use Ivory\Serializer\Format;

$stdClass = new \stdClass();
$stdClass->foo = true;
$stdClass->bar = ['foo', [123, 432.1]];

$serializer = $container->get('ivory.serializer');

echo $serializer->serialize($stdClass, Format::JSON);
// {"foo": true,"bar": ["foo", [123, 432.1]]}

$deserialize = $serializer->deserialize($json, \stdClass::class, Format::JSON);
// $deserialize == $stdClass
```

## Documentation

 - [Installation](/Resources/doc/installation.md)
 - [Usage](/Resources/doc/usage.md)
 - [Configuration](/Resources/doc/configuration/index.md)
    - [Mapping](/Resources/doc/configuration/mapping.md)
    - [Type](/Resources/doc/configuration/type.md)
    - [Event](/Resources/doc/configuration/event.md)
    - [Visitor](/Resources/doc/configuration/visitor.md)
    - [Cache](/Resources/doc/configuration/cache.md)
    - [FOSRestBundle Integration](/Resources/doc/configuration/fos_rest.md)

## Testing

The bundle is fully unit tested by [PHPUnit](http://www.phpunit.de/) with a code coverage close to **100%**. To
execute the test suite, check the travis [configuration](/.travis.yml).

## Contribute

We love contributors! Ivory is an open source project. If you'd like to contribute, feel free to propose a PR! You
can follow the [CONTRIBUTING](/CONTRIBUTING.md) file which will explain you how to set up the project.

## License

The Ivory Google Map Bundle is under the MIT license. For the full copyright and license information, please read the
[LICENSE](/LICENSE) file that was distributed with this source code.
