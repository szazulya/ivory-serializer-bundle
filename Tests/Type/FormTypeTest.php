<?php

/*
 * This file is part of the Ivory Serializer bundle package.
 *
 * (c) Eric GELOEN <geloen.eric@gmail.com>
 *
 * For the full copyright and license information, please read the LICENSE
 * file that was distributed with this source code.
 */

namespace Ivory\SerializerBundle\Tests\Type;

use Ivory\Serializer\Format;
use Ivory\Serializer\Navigator\Navigator;
use Ivory\Serializer\Registry\TypeRegistry;
use Ivory\Serializer\Serializer;
use Ivory\SerializerBundle\Type\FormErrorType;
use Ivory\SerializerBundle\Type\FormType;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ButtonType;
use Symfony\Component\Form\Extension\Core\Type\FormType as SymfonyFormType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Form;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\Forms;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * @author GeLo <geloen.eric@gmail.com>
 */
class FormTypeTest extends TestCase
{
    /**
     * @var Serializer
     */
    private $serializer;

    /**
     * @var MockObject|TranslatorInterface
     */
    private $translator;

    /**
     * {@inheritdoc}
     */
    protected function setUp(): void
    {
        $this->translator = $this->createMock(TranslatorInterface::class);

        $this->translator
            ->expects(self::any())
            ->method('trans')
            ->will(self::returnArgument(0));

        $this->translator
            ->expects(self::any())
            ->method('transChoice')
            ->will(self::returnArgument(1));

        $this->serializer = new Serializer(new Navigator(TypeRegistry::create([
            FormInterface::class => new FormType(),
            FormError::class     => new FormErrorType($this->translator),
        ])));
    }

    /**
     * @dataProvider serializeProvider
     */
    public function testSerialize(string $name, $data, string $format): void
    {
        self::assertSame($this->getDataSet($name, $format), $this->serializer->serialize($data, $format));
    }

    /**
     * @dataProvider formErrorProvider
     */
    public function testSerializeFormErrorWithoutTranslator(string $name, $data, string $format): void
    {
        $this->serializer = new Serializer(new Navigator(TypeRegistry::create([
            FormError::class => new FormErrorType(),
        ])));

        self::assertSame(
            $this->getDataSet($name.'_no_translator', $format),
            $this->serializer->serialize($data, $format)
        );
    }

    /**
     * @dataProvider formatProvider
     */
    public function testDeserializeForm(string $format): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Deserializing a "Symfony\Component\Form\Form" is not supported.');

        $this->serializer->deserialize($this->getDataSet('form', $format), Form::class, $format);
    }

    /**
     * @dataProvider formatProvider
     */
    public function testDeserializeFormError(string $format): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Deserializing a "Symfony\Component\Form\FormError" is not supported.');

        $this->serializer->deserialize($this->getDataSet('form', $format), FormError::class, $format);
    }

    /**
     * @return mixed[]
     */
    public function serializeProvider(): array
    {
        $factory = Forms::createFormFactory();
        $preferFQCN = method_exists(AbstractType::class, 'getBlockPrefix');

        $childForm = $factory->createNamedBuilder(
            'bar',
            $preferFQCN ? SymfonyFormType::class : 'form',
            null,
            ['error_bubbling' => false]
        );

        $childForm
            ->add('baz')
            ->add('bat');

        $form = $factory->createBuilder()
            ->add('foo')
            ->add($childForm)
            ->add('button', $preferFQCN ? ButtonType::class : 'button')
            ->add('submit', $preferFQCN ? SubmitType::class : 'submit')
            ->getForm();

        $form->addError(new FormError('error'));
        $form->get('foo')->addError(new FormError('foo_error'));
        $form->get('bar')->addError(new FormError('bar_error'));
        $form->get('bar')->get('baz')->addError(new FormError('baz_error'));

        return $this->expandCases(array_merge($this->formErrorCases(), [
            ['form', $form],
        ]));
    }

    public function formErrorProvider(): array
    {
        return $this->expandCases($this->formErrorCases());
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

    private function formErrorCases(): array
    {
        $formError = new FormError('error');
        $translatedFormError = new FormError('trans_error', 'trans', []);
        $pluralizedFormError = new FormError('plural_error', 'trans', [], 2);

        return [
            ['form_error', $formError],
            ['form_error_translated', $translatedFormError],
            ['form_error_pluralized', $pluralizedFormError],
        ];
    }

    private function expandCases(array $cases): array
    {
        $providers = [];

        foreach ([Format::CSV, Format::JSON, Format::XML, Format::YAML] as $format) {
            foreach ($cases as $case) {
                $case[] = $format;
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

        return file_get_contents(__DIR__.'/../Fixtures/Data/'.strtolower($format).'/'.$name.'.'.strtolower($extension));
    }
}
