<?php

/*
 * This file is part of the Ivory Serializer bundle package.
 *
 * (c) Eric GELOEN <geloen.eric@gmail.com>
 *
 * For the full copyright and license information, please read the LICENSE
 * file that was distributed with this source code.
 */

namespace Ivory\SerializerBundle\Tests\CacheWarmer;

use Ivory\Serializer\Mapping\Factory\CacheClassMetadataFactory;
use Ivory\Serializer\Mapping\Factory\ClassMetadataFactory;
use Ivory\Serializer\Mapping\Loader\ClassMetadataLoaderInterface;
use Ivory\Serializer\Mapping\Loader\DirectoryClassMetadataLoader;
use Ivory\Serializer\Mapping\Loader\ReflectionClassMetadataLoader;
use Ivory\SerializerBundle\CacheWarmer\SerializerCacheWarmer;
use Ivory\SerializerBundle\Tests\Fixtures\Bundle\Model\Model;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Cache\Adapter\ArrayAdapter;

/**
 * @author GeLo <geloen.eric@gmail.com>
 */
class SerializerCacheWarmerTest extends TestCase
{
    /**
     * @var ArrayAdapter
     */
    private $pool;

    /**
     * {@inheritdoc}
     */
    protected function setUp(): void
    {
        $this->pool = new ArrayAdapter();
    }

    public function testWarmUpWithMappedLoader(): void
    {
        $cacheWarmer = $this->createCacheWarmer();
        $cacheWarmer->warmUp(sys_get_temp_dir());

        self::assertTrue($this->pool->hasItem(str_replace('\\', '_', Model::class)));
    }

    public function testWarmUpWithAnonymousLoader(): void
    {
        $cacheWarmer = $this->createCacheWarmer(new ReflectionClassMetadataLoader());
        $cacheWarmer->warmUp(sys_get_temp_dir());

        self::assertFalse($this->pool->hasItem(str_replace('\\', '_', Model::class)));
    }

    public function testOptional(): void
    {
        self::assertTrue($this->createCacheWarmer()->isOptional());
    }

    /**
     * @param ClassMetadataLoaderInterface|null $loader
     *
     * @return SerializerCacheWarmer
     */
    private function createCacheWarmer(ClassMetadataLoaderInterface $loader = null): SerializerCacheWarmer
    {
        $loader = $loader ?: new DirectoryClassMetadataLoader(__DIR__.'/../Fixtures/Mapping');
        $factory = new CacheClassMetadataFactory(new ClassMetadataFactory($loader), $this->pool);

        return new SerializerCacheWarmer($factory, $loader, $this->pool);
    }
}
