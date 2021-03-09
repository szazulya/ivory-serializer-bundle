<?php

/*
 * This file is part of the Ivory Serializer bundle package.
 *
 * (c) Eric GELOEN <geloen.eric@gmail.com>
 *
 * For the full copyright and license information, please read the LICENSE
 * file that was distributed with this source code.
 */

namespace Ivory\SerializerBundle\Tests\FOS\Type;

use FOS\RestBundle\Util\ExceptionValueMap;
use Ivory\Serializer\Context\Context;
use Ivory\Serializer\Context\ContextInterface;
use Ivory\Serializer\Format;
use Ivory\Serializer\Navigator\Navigator;
use Ivory\Serializer\Registry\TypeRegistry;
use Ivory\Serializer\Serializer;
use Ivory\Serializer\Type\Type;
use Ivory\SerializerBundle\FOS\Type\ExceptionType;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * @author GeLo <geloen.eric@gmail.com>
 */
class ExceptionTypeTest extends TestCase
{
    /**
     * @var Serializer
     */
    private $serializer;

    /**
     * {@inheritdoc}
     */
    protected function setUp(): void
    {
        $this->serializer = new Serializer(new Navigator(TypeRegistry::create([
            Type::EXCEPTION => new ExceptionType($this->createExceptionValueMapMock()),
        ])));
    }

    /**
     * @param mixed $data
     *
     * @dataProvider serializeProvider
     */
    public function testSerialize(string $name, $data, string $format, ContextInterface $context = null): void
    {
        self::assertSame(
            $this->getDataSet($name, $format),
            $this->serializer->serialize($data, $format, $context)
        );
    }

    /**
     * @param mixed $data
     *
     * @dataProvider serializeProvider
     */
    public function testSerializeDebug(string $name, $data, string $format, ContextInterface $context = null): void
    {
        $this->serializer = new Serializer(new Navigator(TypeRegistry::create([
            Type::EXCEPTION => new ExceptionType($this->createExceptionValueMapMock(), true),
        ])));

        self::assertMatchesRegularExpression(
            '/^'.$this->getDataSet($name.'_debug', $format).'$/s',
            $this->serializer->serialize($data, $format, $context)
        );
    }

    /**
     * @dataProvider formatProvider
     */
    public function testDeserialize(string $format): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Deserializing an "Exception" is not supported.');

        $this->serializer->deserialize($this->getDataSet('exception_parent', $format), \Exception::class, $format);
    }

    public function serializeProvider(): array
    {
        $parentException = new \Exception('Parent exception', 321);
        $childException = new \Exception('Child exception', 123, $parentException);

        return $this->expandCases([
            ['exception_parent', $parentException],
            ['exception_child', $childException],
            ['exception_status_code', $parentException, (new Context())->setOption('template_data', ['status_code' => 400])],
        ]);
    }

    public function formatProvider(): array
    {
        return [
            [Format::CSV],
            [Format::JSON],
            [Format::XML],
            [Format::YAML],
        ];
    }

    /**
     * @return MockObject|ExceptionValueMap
     */
    private function createExceptionValueMapMock()
    {
        return $this->createMock(ExceptionValueMap::class);
    }

    private function expandCases(array $cases): array
    {
        $providers = [];

        foreach ([Format::CSV, Format::JSON, Format::XML, Format::YAML] as $format) {
            foreach ($cases as $case) {
                if (isset($case[2])) {
                    $case[3] = $case[2];
                }

                $case[2] = $format;
                $providers[] = $case;
            }
        }

        return $providers;
    }

    private function getDataSet(string $name, string $format): string
    {
        $extension = $format;

        if (Format::YAML === $extension) {
            $extension = 'yml';
        }

        return file_get_contents(__DIR__.'/../../Fixtures/Data/'.strtolower($format).'/'.$name.'.'.strtolower($extension));
    }
}
