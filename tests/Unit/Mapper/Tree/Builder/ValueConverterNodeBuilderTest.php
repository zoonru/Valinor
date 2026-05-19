<?php

declare(strict_types=1);

namespace CuyZ\Valinor\Tests\Unit\Mapper\Tree\Builder;

use CuyZ\Valinor\Definition\Attributes;
use CuyZ\Valinor\Definition\ClassDefinition;
use CuyZ\Valinor\Definition\Repository\ClassDefinitionRepository;
use CuyZ\Valinor\Mapper\Tree\Builder\ConverterContainer;
use CuyZ\Valinor\Mapper\Tree\Builder\ValueConverterNodeBuilder;
use CuyZ\Valinor\Mapper\Tree\Shell;
use CuyZ\Valinor\Tests\Fake\Definition\FakeClassDefinition;
use CuyZ\Valinor\Tests\Fake\Definition\Repository\FakeFunctionDefinitionRepository;
use CuyZ\Valinor\Tests\Fake\Mapper\Tree\Builder\FakeNodeBuilder;
use CuyZ\Valinor\Tests\Fake\Type\FakeObjectType;
use CuyZ\Valinor\Tests\Unit\UnitTestCase;
use CuyZ\Valinor\Type\Dumper\TypeDumper;
use CuyZ\Valinor\Type\ObjectType;
use stdClass;
use Throwable;

final class ValueConverterNodeBuilderTest extends UnitTestCase
{
    public function test_already_valid_object_does_not_need_class_definition_when_no_converter_is_registered(): void
    {
        $classDefinitionRepository = new class () implements ClassDefinitionRepository {
            public int $callCount = 0;

            public function for(ObjectType $type): ClassDefinition
            {
                $this->callCount++;

                return FakeClassDefinition::new($type->className());
            }
        };

        $builder = new ValueConverterNodeBuilder(
            new FakeNodeBuilder(),
            new ConverterContainer(new FakeFunctionDefinitionRepository(), []),
            $classDefinitionRepository,
            new FakeFunctionDefinitionRepository(),
            static fn (Throwable $exception) => throw $exception,
        );

        $value = new stdClass();
        $shell = new Shell(
            name: '',
            path: '*root*',
            type: FakeObjectType::accepting(stdClass::class),
            hasValue: true,
            value: $value,
            attributes: Attributes::empty(),
            allowScalarValueCasting: false,
            allowNonSequentialList: false,
            allowUndefinedValues: false,
            allowSuperfluousKeys: false,
            allowPermissiveTypes: true,
            allowedSuperfluousKeys: [],
            shouldApplyConverters: true,
            nodeBuilder: $builder,
            typeDumper: $this->getService(TypeDumper::class),
            childrenCount: 0,
        );

        $node = $builder->build($shell);

        self::assertSame($value, $node->value());
        self::assertSame(0, $classDefinitionRepository->callCount);
    }
}
