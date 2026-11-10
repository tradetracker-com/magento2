<?php
/**
 * Copyright © TradeTracker. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace TradeTracker\Connect\Test\Unit\Architecture;

use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionMethod;
use ReflectionNamedType;
use ReflectionParameter;

/**
 * Every Api interface with a di.xml preference is the contract its callers see. The implementation can
 * silently drift from it: a method that only exists on the concrete class, or a parameter the interface
 * requires and the implementation made optional. Callers then compile against the class and break the
 * moment another implementation (or a plugin proxy) is put in front of the interface.
 */
class ApiContractTest extends TestCase
{
    public function testEveryPreferenceAgreesWithItsInterface(): void
    {
        $problems = [];
        foreach ($this->preferences() as [$interface, $implementation]) {
            $problems = [...$problems, ...$this->drift($interface, $implementation)];
        }

        self::assertSame([], $problems, implode("\n\n", $problems));
    }

    /**
     * @return string[] One line per place where the implementation does not match the interface.
     */
    private function drift(string $interface, string $implementation): array
    {
        $contract = new ReflectionClass($interface);
        $concrete = new ReflectionClass($implementation);
        $problems = [];

        foreach ($contract->getMethods() as $method) {
            $actual = $concrete->getMethod($method->getName());
            // A method the implementation inherits from a framework base class has no freedom: PHP
            // refuses to narrow Magento\...\AbstractModel::setEntityId($entityId) to int, so the
            // widening is required, not drift. Our own base classes stay in scope.
            if ($this->inheritedFromFramework($concrete, $method->getName())) {
                continue;
            }
            if ($this->signature($method) !== $this->signature($actual, $interface)) {
                $problems[] = sprintf(
                    "%s::%s is declared as\n    %s\nbut %s implements it as\n    %s",
                    $contract->getShortName(),
                    $method->getName(),
                    $this->signature($method),
                    $concrete->getShortName(),
                    $this->signature($actual)
                );
            }
        }

        $declaredOnInterface = array_map(fn(ReflectionMethod $m) => $m->getName(), $contract->getMethods());
        foreach ($concrete->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
            if ($method->getDeclaringClass()->getName() !== $implementation
                || $method->isConstructor()
                || $method->isStatic()
                || in_array($method->getName(), $declaredOnInterface, true)
            ) {
                continue;
            }
            // A public method the parent class already declares (beforeSave, afterLoad, …) belongs to
            // Magento's contract, not to ours.
            $parent = $concrete->getParentClass();
            if ($parent && $parent->hasMethod($method->getName())) {
                continue;
            }
            $problems[] = sprintf(
                '%s::%s() is public but missing from %s',
                $concrete->getShortName(),
                $method->getName(),
                $interface
            );
        }

        return $problems;
    }

    /** Does a class outside this module already declare this method, fixing its signature? */
    private function inheritedFromFramework(ReflectionClass $concrete, string $method): bool
    {
        for ($parent = $concrete->getParentClass(); $parent; $parent = $parent->getParentClass()) {
            if ($parent->hasMethod($method)) {
                return !str_starts_with($parent->getMethod($method)->getDeclaringClass()->getName(), 'TradeTracker\\Connect\\');
            }
        }

        return false;
    }

    /**
     * @return iterable<array{string, string}>
     */
    private function preferences(): iterable
    {
        $diXml = simplexml_load_file(dirname(__DIR__, 3) . '/etc/di.xml');
        foreach ($diXml->preference as $preference) {
            $for = (string)$preference['for'];
            $type = (string)$preference['type'];
            if (str_starts_with($for, 'TradeTracker\\Connect\\') && str_starts_with($type, 'TradeTracker\\Connect\\')) {
                yield [$for, $type];
            }
        }
    }

    /**
     * The textual signature, with `self` spelled out as the declaring class. An implementation may return
     * the interface where the interface says `self`; `\$contract` makes those two read the same.
     */
    private function signature(ReflectionMethod $method, ?string $contract = null): string
    {
        $self = $contract ?? $method->getDeclaringClass()->getName();
        $parameters = array_map(function (ReflectionParameter $parameter) use ($self): string {
            $type = $parameter->getType();
            $text = ($type ? $this->typeName($type, $self) . ' ' : '') . '$' . $parameter->getName();
            if ($parameter->isDefaultValueAvailable()) {
                $text .= ' = ' . var_export($parameter->getDefaultValue(), true);
            }
            return $text;
        }, $method->getParameters());

        $returnType = $method->getReturnType();

        return sprintf(
            '%s(%s)%s',
            $method->getName(),
            implode(', ', $parameters),
            $returnType ? ': ' . $this->typeName($returnType, $self) : ''
        );
    }

    private function typeName(\ReflectionType $type, string $self): string
    {
        if ($type instanceof ReflectionNamedType) {
            $name = in_array($type->getName(), ['self', 'static'], true) ? $self : $type->getName();
            return ($type->allowsNull() && $name !== 'mixed' && $name !== 'null' ? '?' : '') . $name;
        }

        return (string)$type;
    }
}
