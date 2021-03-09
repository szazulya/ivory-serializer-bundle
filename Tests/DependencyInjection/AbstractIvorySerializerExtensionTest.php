<?php

/*
 * This file is part of the Ivory Serializer bundle package.
 *
 * (c) Eric GELOEN <geloen.eric@gmail.com>
 *
 * For the full copyright and license information, please read the LICENSE
 * file that was distributed with this source code.
 */

namespace Ivory\SerializerBundle\Tests\DependencyInjection;

use Doctrine\Common\Annotations\AnnotationReader;
use FOS\RestBundle\Serializer\Serializer as FOSSerializer;
use FOS\RestBundle\Util\ExceptionValueMap;
use Ivory\Serializer\Mapping\ClassMetadataInterface;
use Ivory\Serializer\Mapping\Factory\CacheClassMetadataFactory;
use Ivory\Serializer\Mapping\Factory\ClassMetadataFactory;
use Ivory\Serializer\Mapping\Loader\ChainClassMetadataLoader;
use Ivory\Serializer\Mapping\PropertyMetadataInterface;
use Ivory\Serializer\Serializer;
use Ivory\Serializer\Type\DateTimeType;
use Ivory\Serializer\Type\ExceptionType;
use Ivory\Serializer\Visitor\Csv\CsvDeserializationVisitor;
use Ivory\Serializer\Visitor\Csv\CsvSerializationVisitor;
use Ivory\Serializer\Visitor\Json\JsonDeserializationVisitor;
use Ivory\Serializer\Visitor\Json\JsonSerializationVisitor;
use Ivory\Serializer\Visitor\Xml\XmlDeserializationVisitor;
use Ivory\Serializer\Visitor\Xml\XmlSerializationVisitor;
use Ivory\Serializer\Visitor\Yaml\YamlDeserializationVisitor;
use Ivory\Serializer\Visitor\Yaml\YamlSerializationVisitor;
use Ivory\SerializerBundle\CacheWarmer\SerializerCacheWarmer;
use Ivory\SerializerBundle\DependencyInjection\IvorySerializerExtension;
use Ivory\SerializerBundle\FOS\Type\ExceptionType as FOSExceptionType;
use Ivory\SerializerBundle\IvorySerializerBundle;
use Ivory\SerializerBundle\Tests\Fixtures\Bundle\AcmeFixtureBundle;
use Ivory\SerializerBundle\Tests\Fixtures\Bundle\Model\Model;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Cache\CacheItemInterface;
use Psr\Cache\CacheItemPoolInterface;
use Symfony\Component\Cache\DependencyInjection\CachePoolPass;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Loader\XmlFileLoader;

/**
 * @author GeLo <geloen.eric@gmail.com>
 */
abstract class AbstractIvorySerializerExtensionTest extends TestCase
{
    /**
     * @var ContainerBuilder
     */
    private $container;

    /**
     * {@inheritdoc}
     */
    protected function setUp(): void
    {
        $this->container = new ContainerBuilder();
        $this->container->setParameter('kernel.bundles', []);
        $this->container->setParameter('kernel.debug', true);
        $this->container->setParameter('kernel.cache_dir', sys_get_temp_dir());
        $this->container->setParameter('kernel.root_dir', __DIR__.'/../Fixtures');
        $this->container->set('annotation_reader', new AnnotationReader());
        $this->container->set('cache.system', $this->createCacheItemPoolMock());
        $this->container->registerExtension($extension = new IvorySerializerExtension());
        $this->container->loadFromExtension($extension->getAlias());
        (new IvorySerializerBundle())->build($this->container);
    }

    abstract protected function loadConfiguration(ContainerBuilder $container, string $configuration);

    public function testSerializer(): void
    {
        $this->container->compile();

        self::assertInstanceOf(Serializer::class, $this->container->get('ivory.serializer'));

        self::assertInstanceOf(
            ClassMetadataFactory::class,
            $this->container->get('ivory.serializer.mapping.factory')
        );

        self::assertInstanceOf(
            ChainClassMetadataLoader::class,
            $this->container->get('ivory.serializer.mapping.loader')
        );
    }

    public function testSerializerWithoutDebug(): void
    {
        $this->loadConfiguration($this->container, 'mapping_debug');
        $this->container->compile();

        self::assertInstanceOf(Serializer::class, $this->container->get('ivory.serializer'));

        self::assertInstanceOf(
            CacheClassMetadataFactory::class,
            $this->container->get('ivory.serializer.mapping.factory')
        );

        self::assertInstanceOf(
            ChainClassMetadataLoader::class,
            $this->container->get('ivory.serializer.mapping.loader')
        );
    }

    public function testMappingAnnotationEnabled(): void
    {
        $this->container->compile();

        $classMetadataFactory = $this->container->get('ivory.serializer.mapping.factory');

        $this->assertClassMetadata($classMetadataFactory->getClassMetadata(Model::class), [
            'foo' => ['alias' => 'bar'],
        ]);
    }

    public function testMappingAnnotationDisabled(): void
    {
        $this->loadConfiguration($this->container, 'mapping_annotation_disabled');
        $this->container->compile();

        $classMetadataFactory = $this->container->get('ivory.serializer.mapping.factory');

        $this->assertClassMetadata($classMetadataFactory->getClassMetadata(Model::class), ['foo' => []]);
    }

    public function testMappingAutoEnabled(): void
    {
        $this->container->setParameter('kernel.bundles', ['AcmeFixtureBundle' => AcmeFixtureBundle::class]);
        $this->container->compile();

        $classMetadataFactory = $this->container->get('ivory.serializer.mapping.factory');

        $this->assertClassMetadata($classMetadataFactory->getClassMetadata(Model::class), [
            'foo' => [
                'alias'    => 'bar',
                'readable' => false,
                'writable' => false,
                'since'    => '1.0.0',
                'until'    => '2.0.0',
                'groups'   => ['bar'],
                'type'     => 'int',
            ],
        ]);
    }

    public function testMappingAutoDisabled(): void
    {
        $this->loadConfiguration($this->container, 'mapping_auto_disabled');
        $this->container->setParameter('kernel.bundles', ['AcmeFixtureBundle' => AcmeFixtureBundle::class]);
        $this->container->compile();

        $classMetadataFactory = $this->container->get('ivory.serializer.mapping.factory');

        $this->assertClassMetadata($classMetadataFactory->getClassMetadata(Model::class), [
            'foo' => ['alias' => 'bar'],
        ]);
    }

    public function testMappingAutoPaths(): void
    {
        $this->loadConfiguration($this->container, 'mapping_auto_paths');
        $this->container->setParameter('kernel.bundles', ['AcmeFixtureBundle' => AcmeFixtureBundle::class]);
        $this->container->compile();

        $classMetadataFactory = $this->container->get('ivory.serializer.mapping.factory');

        $this->assertClassMetadata($classMetadataFactory->getClassMetadata(Model::class), [
            'foo' => [
                'alias'         => 'bar',
                'since'         => '1.1.0',
                'until'         => '2.1.0',
                'type'          => 'bool',
                'xml_attribute' => true,
                'xml_value'     => true,
            ],
        ], ['xml_root' => 'model']);
    }

    public function testMappingPaths(): void
    {
        $this->loadConfiguration($this->container, 'mapping_paths');
        $this->container->compile();

        $classMetadataFactory = $this->container->get('ivory.serializer.mapping.factory');

        $this->assertClassMetadata($classMetadataFactory->getClassMetadata(Model::class), [
            'foo' => [
                'alias'         => 'bar',
                'xml_attribute' => true,
                'xml_value'     => true,
            ],
        ], ['xml_root' => 'model']);
    }

    public function testClassMetadataLoader(): void
    {
        $this->loadService('class_metadata_loader');
        $this->container->compile();

        $classMetadataFactory = $this->container->get('ivory.serializer.mapping.factory');

        $this->assertClassMetadata($classMetadataFactory->getClassMetadata(Model::class), [
            'foo' => [
                'alias'         => 'bar',
                'xml_attribute' => true,
            ],
        ]);
    }

    public function testMappingCache(): void
    {
        $this->loadConfiguration($this->container, 'mapping_debug');
        $this->container->compile();

        $classMetadataFactoryService = 'ivory.serializer.mapping.factory';
        $classMetadataFactoryDefinition = $this->container->getDefinition($classMetadataFactoryService);

        self::assertSame(
            'ivory.serializer.mapping.factory.event',
            (string) $classMetadataFactoryDefinition->getArgument(0)
        );

        self::assertSame(
            class_exists(CachePoolPass::class) ? 'cache.system' : 'ivory.serializer.cache',
            (string) $classMetadataFactoryDefinition->getArgument(1)
        );

        self::assertSame('ivory_serializer', $classMetadataFactoryDefinition->getArgument(2));

        self::assertInstanceOf(
            CacheClassMetadataFactory::class,
            $this->container->get($classMetadataFactoryService)
        );
    }

    public function testCustomMappingCache(): void
    {
        $this->container->setDefinition('cache.custom', new Definition($this->createCacheItemPoolMockClass()));
        $this->loadConfiguration($this->container, 'mapping_cache');
        $this->container->compile();

        $classMetadataFactoryService = 'ivory.serializer.mapping.factory';
        $classMetadataFactoryDefinition = $this->container->getDefinition($classMetadataFactoryService);

        self::assertSame(
            'ivory.serializer.mapping.factory.event',
            (string) $classMetadataFactoryDefinition->getArgument(0)
        );

        self::assertSame('cache.custom', (string) $classMetadataFactoryDefinition->getArgument(1));
        self::assertSame('acme', $classMetadataFactoryDefinition->getArgument(2));

        self::assertInstanceOf(
            CacheClassMetadataFactory::class,
            $this->container->get($classMetadataFactoryService)
        );
    }

    public function testCacheWarmer(): void
    {
        $this->loadConfiguration($this->container, 'mapping_debug');
        $this->container->compile();

        $cacheWarmerService = 'ivory.serializer.cache_warmer';

        self::assertSame(
            ['kernel.cache_warmer' => [[]]],
            $this->container->getDefinition($cacheWarmerService)->getTags()
        );

        self::assertInstanceOf(
            SerializerCacheWarmer::class,
            $this->container->get($cacheWarmerService)
        );
    }

    public function testEventEnabled(): void
    {
        $this->container->compile();

        self::assertTrue($this->container->has('ivory.serializer.event.dispatcher'));
        self::assertTrue($this->container->has('ivory.serializer.mapping.factory.event'));
        self::assertTrue($this->container->has('ivory.serializer.navigator.event'));
    }

    public function testEventDisabled(): void
    {
        $this->loadConfiguration($this->container, 'event_disabled');
        $this->container->compile();

        self::assertFalse($this->container->has('ivory.serializer.event.dispatcher'));
        self::assertFalse($this->container->has('ivory.serializer.mapping.factory.event'));
        self::assertFalse($this->container->has('ivory.serializer.navigator.event'));
    }

    public function testDateTimeType(): void
    {
        $this->loadConfiguration($this->container, 'type_date_time');
        $this->container->compile();

        $dateTimeService = 'ivory.serializer.type.date_time';
        $dateTimeDefinition = $this->container->getDefinition($dateTimeService);

        self::assertSame(\DateTime::ATOM, $dateTimeDefinition->getArgument(0));
        self::assertSame('UTC', $dateTimeDefinition->getArgument(1));

        self::assertInstanceOf(DateTimeType::class, $this->container->get($dateTimeService));
    }

    public function testCsvVisitor(): void
    {
        $this->loadConfiguration($this->container, 'visitor_csv');
        $this->container->compile();

        $csvSerializationVisitorService = 'ivory.serializer.visitor.csv.serialization';
        $csvDeserializationVisitorService = 'ivory.serializer.visitor.csv.deserialization';

        $csvSerializationVisitorDefinition = $this->container->getDefinition($csvSerializationVisitorService);
        $csvDeserializationVisitorDefinition = $this->container->getDefinition($csvDeserializationVisitorService);

        self::assertSame('ivory.serializer.accessor', (string) $csvSerializationVisitorDefinition->getArgument(0));
        self::assertSame($delimiter = ',', $csvSerializationVisitorDefinition->getArgument(1));
        self::assertSame($enclosure = '"', $csvSerializationVisitorDefinition->getArgument(2));
        self::assertSame($escapeChar = '\\', $csvSerializationVisitorDefinition->getArgument(3));
        self::assertSame($keySeparator = '.', $csvSerializationVisitorDefinition->getArgument(4));

        self::assertSame('ivory.serializer.instantiator', (string) $csvDeserializationVisitorDefinition->getArgument(0));
        self::assertSame('ivory.serializer.mutator', (string) $csvDeserializationVisitorDefinition->getArgument(1));
        self::assertSame($delimiter, $csvDeserializationVisitorDefinition->getArgument(2));
        self::assertSame($enclosure, $csvDeserializationVisitorDefinition->getArgument(3));
        self::assertSame($escapeChar, $csvDeserializationVisitorDefinition->getArgument(4));
        self::assertSame($keySeparator, $csvDeserializationVisitorDefinition->getArgument(5));

        self::assertInstanceOf(
            CsvSerializationVisitor::class,
            $this->container->get($csvSerializationVisitorService)
        );

        self::assertInstanceOf(
            CsvDeserializationVisitor::class,
            $this->container->get($csvDeserializationVisitorService)
        );
    }

    public function testJsonVisitor(): void
    {
        $this->loadConfiguration($this->container, 'visitor_json');
        $this->container->compile();

        $jsonSerializationVisitorService = 'ivory.serializer.visitor.json.serialization';
        $jsonDeserializationVisitorService = 'ivory.serializer.visitor.json.deserialization';

        $jsonSerializationVisitorDefinition = $this->container->getDefinition($jsonSerializationVisitorService);
        $jsonDeserializationVisitorDefinition = $this->container->getDefinition($jsonDeserializationVisitorService);

        self::assertSame('ivory.serializer.accessor', (string) $jsonSerializationVisitorDefinition->getArgument(0));
        self::assertSame(0, $jsonSerializationVisitorDefinition->getArgument(1));

        self::assertSame(
            'ivory.serializer.instantiator',
            (string) $jsonDeserializationVisitorDefinition->getArgument(0)
        );

        self::assertSame('ivory.serializer.mutator', (string) $jsonDeserializationVisitorDefinition->getArgument(1));
        self::assertSame(512, $jsonDeserializationVisitorDefinition->getArgument(2));
        self::assertSame(0, $jsonDeserializationVisitorDefinition->getArgument(3));

        self::assertInstanceOf(
            JsonSerializationVisitor::class,
            $this->container->get($jsonSerializationVisitorService)
        );

        self::assertInstanceOf(
            JsonDeserializationVisitor::class,
            $this->container->get($jsonDeserializationVisitorService)
        );
    }

    public function testXmlVisitor()
    {
        $this->loadConfiguration($this->container, 'visitor_xml');
        $this->container->compile();

        $xmlSerializationVisitorService = 'ivory.serializer.visitor.xml.serialization';
        $xmlDeserializationVisitorService = 'ivory.serializer.visitor.xml.deserialization';

        $xmlSerializationVisitorDefinition = $this->container->getDefinition($xmlSerializationVisitorService);
        $xmlDeserializationVisitorDefinition = $this->container->getDefinition($xmlDeserializationVisitorService);

        self::assertSame('ivory.serializer.accessor', (string) $xmlSerializationVisitorDefinition->getArgument(0));
        self::assertSame('1.0', $xmlSerializationVisitorDefinition->getArgument(1));
        self::assertSame('UTF-8', $xmlSerializationVisitorDefinition->getArgument(2));
        self::assertTrue($xmlSerializationVisitorDefinition->getArgument(3));
        self::assertSame('result', $xmlSerializationVisitorDefinition->getArgument(4));
        self::assertSame($entry = 'entry', $xmlSerializationVisitorDefinition->getArgument(5));
        self::assertSame($entryAttribute = 'key', $xmlSerializationVisitorDefinition->getArgument(6));

        self::assertSame(
            'ivory.serializer.instantiator',
            (string) $xmlDeserializationVisitorDefinition->getArgument(0)
        );

        self::assertSame('ivory.serializer.mutator', (string) $xmlDeserializationVisitorDefinition->getArgument(1));
        self::assertSame($entry, $xmlDeserializationVisitorDefinition->getArgument(2));
        self::assertSame($entryAttribute, $xmlDeserializationVisitorDefinition->getArgument(3));

        self::assertInstanceOf(
            XmlSerializationVisitor::class,
            $this->container->get($xmlSerializationVisitorService)
        );

        self::assertInstanceOf(
            XmlDeserializationVisitor::class,
            $this->container->get($xmlDeserializationVisitorService)
        );
    }

    public function testYamlVisitor(): void
    {
        $this->loadConfiguration($this->container, 'visitor_yaml');
        $this->container->compile();

        $yamlSerializationVisitorService = 'ivory.serializer.visitor.yaml.serialization';
        $yamlDeserializationVisitorService = 'ivory.serializer.visitor.yaml.deserialization';

        $yamlSerializationVisitorDefinition = $this->container->getDefinition($yamlSerializationVisitorService);
        $yamlDeserializationVisitorDefinition = $this->container->getDefinition($yamlDeserializationVisitorService);

        self::assertSame('ivory.serializer.accessor', (string) $yamlSerializationVisitorDefinition->getArgument(0));
        self::assertSame(2, $yamlSerializationVisitorDefinition->getArgument(1));
        self::assertSame(4, $yamlSerializationVisitorDefinition->getArgument(2));
        self::assertSame(0, $yamlSerializationVisitorDefinition->getArgument(3));

        self::assertSame(
            'ivory.serializer.instantiator',
            (string) $yamlDeserializationVisitorDefinition->getArgument(0)
        );

        self::assertSame('ivory.serializer.mutator', (string) $yamlDeserializationVisitorDefinition->getArgument(1));
        self::assertSame(0, $yamlDeserializationVisitorDefinition->getArgument(2));

        self::assertInstanceOf(
            YamlSerializationVisitor::class,
            $this->container->get($yamlSerializationVisitorService)
        );

        self::assertInstanceOf(
            YamlDeserializationVisitor::class,
            $this->container->get($yamlDeserializationVisitorService)
        );
    }

    public function testFOSDisabled(): void
    {
        $this->container->compile();

        self::assertFalse($this->container->has('ivory.serializer.fos'));
        self::assertInstanceOf(ExceptionType::class, $this->container->get('ivory.serializer.type.exception'));
    }

    public function testFOSEnabled(): void
    {
        $this->container->setDefinition(
            'fos_rest.exception.messages_map',
            new Definition(ExceptionValueMap::class, [[]])
        );

        $this->container->compile();

        self::assertInstanceOf(FOSSerializer::class, $this->container->get('ivory.serializer.fos'));
        self::assertInstanceOf(FOSExceptionType::class, $this->container->get('ivory.serializer.type.exception'));
    }

    public function testMappingPathsInvalid(): void
    {
        $this->expectException(\Symfony\Component\Config\Definition\Exception\InvalidConfigurationException::class);
        $this->expectExceptionMessageMatches("/^The path \"(.*)\" does not exist\.$/");

        $this->loadConfiguration($this->container, 'mapping_paths_invalid');
        $this->container->compile();
    }

    public function testMappingLoaderEmpty(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('You must define at least one class metadata loader by enabling the reflection loader in your configuration or by registering a loader in the container with the tag "ivory.serializer.loader".');

        $this->loadConfiguration($this->container, 'mapping_loader_empty');
        $this->container->compile();
    }

    public function testTypeCompilerMissingAlias(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('No "alias" attribute found for the tag "ivory.serializer.type" on the service "ivory.serializer.type.invalid".');

        $this->loadService('type_alias_missing');
        $this->container->compile();
    }

    public function testVisitorCompilerMissingDirection(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('No "direction" attribute found for the tag "ivory.serializer.visitor" on the service "ivory.serializer.visitor.invalid".');

        $this->loadService('visitor_direction_missing');
        $this->container->compile();
    }

    public function testVisitorCompilerInvalidDirection(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('The "direction" attribute (invalid) found for the tag "ivory.serializer.visitor" on the service "ivory.serializer.visitor.invalid" is not valid (Supported: serialization, deserialization).');

        $this->loadService('visitor_direction_invalid');
        $this->container->compile();
    }

    public function testVisitorCompilerMissingFormat(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('No "format" attribute found for the tag "ivory.serializer.visitor" on the service "ivory.serializer.visitor.invalid".');

        $this->loadService('visitor_format_missing');
        $this->container->compile();
    }

    private function loadService(string $service): void
    {
        $loader = new XmlFileLoader($this->container, new FileLocator(__DIR__.'/../Fixtures/Service'));
        $loader->load($service.'.xml');
    }

    private function createCacheItemPoolMockClass(): string
    {
        return $this->getMockClass(CacheItemPoolInterface::class);
    }

    /**
     * @return MockObject|CacheItemPoolInterface
     */
    private function createCacheItemPoolMock()
    {
        $pool = $this->createMock(CacheItemPoolInterface::class);
        $pool
            ->expects(self::any())
            ->method('getItem')
            ->willReturn($this->createCacheItemMock());

        return $pool;
    }

    /**
     * @return MockObject|CacheItemInterface
     */
    private function createCacheItemMock()
    {
        $item = $this->createMock(CacheItemInterface::class);
        $item
            ->expects(self::any())
            ->method('set')
            ->will(self::returnSelf());

        return $item;
    }

    /**
     * @param mixed[][] $properties
     * @param mixed[]   $options
     */
    private function assertClassMetadata(
        ClassMetadataInterface $classMetadata,
        array $properties,
        array $options = []
    ): void {
        self::assertSame(isset($options['xml_root']), $classMetadata->hasXmlRoot());
        self::assertSame($options['xml_root'] ?? null, $classMetadata->getXmlRoot());

        foreach ($properties as $property => $data) {
            self::assertTrue($classMetadata->hasProperty($property));
            $this->assertPropertyMetadata($classMetadata->getProperty($property), $data);
        }
    }

    /**
     * @param mixed[] $data
     */
    private function assertPropertyMetadata(PropertyMetadataInterface $propertyMetadata, array $data): void
    {
        self::assertSame(isset($data['alias']), $propertyMetadata->hasAlias());
        self::assertSame($data['alias'] ?? null, $propertyMetadata->getAlias());

        self::assertSame(isset($data['type']), $propertyMetadata->hasType());
        self::assertSame(
            $data['type'] ?? null,
            $propertyMetadata->hasType() ? (string) $propertyMetadata->getType() : null
        );

        self::assertSame($data['readable'] ?? true, $propertyMetadata->isReadable());
        self::assertSame($data['writable'] ?? true, $propertyMetadata->isWritable());

        self::assertSame(isset($data['accessor']), $propertyMetadata->hasAccessor());
        self::assertSame($data['accessor'] ?? null, $propertyMetadata->getAccessor());

        self::assertSame(isset($data['mutator']), $propertyMetadata->hasMutator());
        self::assertSame($data['mutator'] ?? null, $propertyMetadata->getMutator());

        self::assertSame(isset($data['since']), $propertyMetadata->hasSinceVersion());
        self::assertSame($data['since'] ?? null, $propertyMetadata->getSinceVersion());

        self::assertSame(isset($data['until']), $propertyMetadata->hasUntilVersion());
        self::assertSame($data['until'] ?? null, $propertyMetadata->getUntilVersion());

        self::assertSame(isset($data['max_depth']), $propertyMetadata->hasMaxDepth());
        self::assertSame($data['max_depth'] ?? null, $propertyMetadata->getMaxDepth());

        self::assertSame(isset($data['groups']), $propertyMetadata->hasGroups());
        self::assertSame($data['groups'] ?? [], $propertyMetadata->getGroups());

        self::assertSame(isset($data['xml_attribute']) && $data['xml_attribute'], $propertyMetadata->isXmlAttribute());
        self::assertSame(isset($data['xml_inline']) && $data['xml_inline'], $propertyMetadata->isXmlInline());
        self::assertSame(isset($data['xml_value']) && $data['xml_value'], $propertyMetadata->isXmlValue());
        self::assertSame($data['xml_entry'] ?? null, $propertyMetadata->getXmlEntry());

        self::assertSame(
            $data['xml_entry_attribute'] ?? null,
            $propertyMetadata->getXmlEntryAttribute()
        );

        self::assertSame(
            $data['xml_key_as_attribute'] ?? null,
            $propertyMetadata->useXmlKeyAsAttribute()
        );

        self::assertSame(
            $data['xml_key_as_node'] ?? null,
            $propertyMetadata->useXmlKeyAsNode()
        );
    }
}
