#!/usr/bin/env bash

set -e

DOCKER_BUILD=${DOCKER_BUILD-false}
TRAVIS_PHP_VERSION=${TRAVIS_PHP_VERSION-7.4}

if [ "$DOCKER_BUILD" = false ]; then
    XDEBUG_MODE=coverage php vendor/bin/phpunit --coverage-clover clover.xml
fi

if [ "$DOCKER_BUILD" = true ]; then
    docker-compose run --rm php XDEBUG_MODE=coverage php vendor/bin/phpunit
fi
