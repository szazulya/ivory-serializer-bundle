<?php

declare(strict_types=1);

namespace Ivory\SerializerBundle\DependencyInjection\Compiler;

use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

class PublicForTestsCompilerPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container)
    {
        if (!$this->isPHPUnit()) {
            return;
        }

        foreach ($container->getDefinitions() as $definition) {
            $definition->setPublic(true);
        }

        foreach ($container->getAliases() as $definition) {
            $definition->setPublic(true);
        }
    }

    private function isPHPUnit(): bool
    {
        // there constants are defined by PHPUnit
        return defined('PHPUNIT_COMPOSER_INSTALL') || defined('__PHPUNIT_PHAR__');
    }
}
