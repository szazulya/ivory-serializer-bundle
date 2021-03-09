<?php

/*
 * This file is part of the Ivory Serializer bundle package.
 *
 * (c) Eric GELOEN <geloen.eric@gmail.com>
 *
 * For the full copyright and license information, please read the LICENSE
 * file that was distributed with this source code.
 */

namespace Ivory\SerializerBundle\Tests\FOS;

use FOS\RestBundle\Context\Context as FOSContext;
use Ivory\Serializer\Context\Context;
use Ivory\Serializer\Exclusion\ChainExclusionStrategy;
use Ivory\Serializer\Exclusion\ExclusionStrategyInterface;
use Ivory\Serializer\Exclusion\GroupsExclusionStrategy;
use Ivory\Serializer\Exclusion\MaxDepthExclusionStrategy;
use Ivory\Serializer\Exclusion\VersionExclusionStrategy;
use Ivory\Serializer\Format;
use Ivory\Serializer\SerializerInterface;
use Ivory\SerializerBundle\FOS\Serializer;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * @author GeLo <geloen.eric@gmail.com>
 */
class SerializerTest extends TestCase
{
    /**
     * @var Serializer
     */
    private $serializer;

    /**
     * @var MockObject|SerializerInterface
     */
    private $innerSerializer;

    /**
     * {@inheritdoc}
     */
    protected function setUp(): void
    {
        $this->innerSerializer = $this->createSerializerMock();
        $this->serializer = new Serializer($this->innerSerializer);
    }

    public function testIgnoreNull(): void
    {
        $context = new FOSContext();
        $context->setSerializeNull(false);

        $callback = function (Context $context) {
            return $context->isNullIgnored();
        };

        $this->assertContext($context, $callback);
    }

    public function testGroups(): void
    {
        $context = new FOSContext();
        $context->setGroups(['foo', 'bar']);

        $callback = function (Context $context) {
            return $context->getExclusionStrategy() instanceof GroupsExclusionStrategy;
        };

        $this->assertContext($context, $callback);
    }

    public function testVersion(): void
    {
        $context = new FOSContext();
        $context->setVersion('1.0.0');

        $callback = function (Context $context) {
            return $context->getExclusionStrategy() instanceof VersionExclusionStrategy;
        };

        $this->assertContext($context, $callback);
    }

    public function testMaxDepth(): void
    {
        $context = new FOSContext();

        if (method_exists($context, 'enableMaxDepth')) {
            $context->enableMaxDepth();
        } else {
            $context->setMaxDepth(1);
        }

        $callback = function (Context $context) {
            return $context->getExclusionStrategy() instanceof MaxDepthExclusionStrategy;
        };

        $this->assertContext($context, $callback);
    }

    public function testCustomExclusionStrategy(): void
    {
        $context = new FOSContext();
        $context->setAttribute(
            'ivory_exclusion_strategies',
            [$exclusionStrategy = $this->createExclusionStrategyMock()]
        );

        $callback = function (Context $context) use ($exclusionStrategy) {
            return $context->getExclusionStrategy() === $exclusionStrategy;
        };

        $this->assertContext($context, $callback);
    }

    public function testCustomExclusionStrategies(): void
    {
        $context = new FOSContext();
        $context->setAttribute(
            'ivory_exclusion_strategies',
            [$this->createExclusionStrategyMock(), $this->createExclusionStrategyMock()]
        );

        $callback = function (Context $context) {
            return $context->getExclusionStrategy() instanceof ChainExclusionStrategy;
        };

        $this->assertContext($context, $callback);
    }

    public function testOptions(): void
    {
        $context = new FOSContext();
        $context->setAttribute('foo', 'bar');

        $callback = function (Context $context) {
            return $context->getOptions() === ['foo' => 'bar'];
        };

        $this->assertContext($context, $callback);
    }

    public function testInvalidExclusionStrategies(): void
    {
        $context = new FOSContext();
        $context->setAttribute('ivory_exclusion_strategies', 'invalid');

        $this->assertInvalidContext(
            $context,
            'The "ivory_exclusion_strategies" context attribute must be an array or implement "Traversable".'
        );
    }

    public function testInvalidExclusionStrategy(): void
    {
        $context = new FOSContext();
        $context->setAttribute('ivory_exclusion_strategies', ['invalid']);

        $this->assertInvalidContext(
            $context,
            'The "ivory_exclusion_strategies" context attribute must be an array of '.
            '"Ivory\Serializer\Exclusion\ExclusionStrategyInterface", got "string".'
        );
    }

    private function assertContext(FOSContext $context, callable $callback)
    {
        $this->innerSerializer
            ->expects(self::once())
            ->method('serialize')
            ->with(
                self::identicalTo($data = 'data'),
                self::identicalTo($format = Format::JSON),
                self::callback($callback)
            )
            ->willReturn($serializeResult = 'serialize');

        $this->innerSerializer
            ->expects($this->once())
            ->method('deserialize')
            ->with(
                self::identicalTo($data),
                self::identicalTo($type = 'type'),
                self::identicalTo($format),
                self::callback($callback)
            )
            ->willReturn($deserializeResult = 'deserialize');

        self::assertSame($serializeResult, $this->serializer->serialize($data, $format, $context));
        self::assertSame($deserializeResult, $this->serializer->deserialize($data, $type, $format, $context));
    }

    private function assertInvalidContext(FOSContext $context, string $message): void
    {
        $data = 'data';
        $type = 'type';
        $format = Format::JSON;

        try {
            $this->serializer->serialize($data, $format, $context);
            self::fail();
        } catch (\Exception $e) {
            self::assertInstanceOf(\RuntimeException::class, $e);
            self::assertSame($message, $e->getMessage());
        }

        try {
            $this->serializer->deserialize($data, $type, $format, $context);
            self::fail();
        } catch (\Exception $e) {
            self::assertInstanceOf(\RuntimeException::class, $e);
            self::assertSame($message, $e->getMessage());
        }
    }

    /**
     * @return MockObject|SerializerInterface
     */
    private function createSerializerMock()
    {
        return $this->createMock(SerializerInterface::class);
    }

    /**
     * @return MockObject|ExclusionStrategyInterface
     */
    private function createExclusionStrategyMock()
    {
        return $this->createMock(ExclusionStrategyInterface::class);
    }
}
