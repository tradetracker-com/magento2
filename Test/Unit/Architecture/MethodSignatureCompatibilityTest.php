<?php
/**
 * Copyright © TradeTracker. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace TradeTracker\Connect\Test\Unit\Architecture;

use PHPUnit\Framework\TestCase;

/**
 * An overriding method may not declare a native parameter type the parent leaves open, or a
 * different one: PHP refuses to load the class ("Declaration must be compatible"), which only shows
 * at setup:di:compile or on the first request. PHPStan trusts the parent's docblock and misses it;
 * richsnippets shipped `setEntityId(int $entityId)` over AbstractModel::setEntityId($entityId) that way.
 */
class MethodSignatureCompatibilityTest extends TestCase
{
    public function testOverridingMethodsKeepTheParentsNativeParameterTypes(): void
    {
        $problems = [];
        foreach ($this->moduleClasses() as $class) {
            $reflection = new \ReflectionClass($class);
            $parent = $reflection->getParentClass();
            if (!$parent) {
                continue;
            }
            foreach ($reflection->getMethods() as $method) {
                if ($method->getDeclaringClass()->getName() !== $class || $method->isConstructor() || $method->isPrivate()) {
                    continue;
                }
                if (!$parent->hasMethod($method->getName())) {
                    continue;
                }
                $parentMethod = $parent->getMethod($method->getName());
                if ($parentMethod->isPrivate()) {
                    continue;
                }
                $problems = array_merge($problems, $this->compare($class, $method, $parentMethod));
            }
        }
        $this->assertSame([], $problems);
    }

    /** @return string[] */
    private function compare(string $class, \ReflectionMethod $child, \ReflectionMethod $parent): array
    {
        $problems = [];
        $parentParams = $parent->getParameters();
        foreach ($child->getParameters() as $i => $param) {
            if (!isset($parentParams[$i])) {
                if (!$param->isOptional()) {
                    $problems[] = sprintf('%s::%s() adds required parameter $%s', $class, $child->getName(), $param->getName());
                }
                continue;
            }
            $childType = $this->typeName($param->getType());
            $parentType = $this->typeName($parentParams[$i]->getType());
            if ($parentType === null && $childType !== null) {
                $problems[] = sprintf('%s::%s() types $%s as %s while %s leaves it untyped', $class, $child->getName(), $param->getName(), $childType, $parent->getDeclaringClass()->getName());
            } elseif ($parentType !== null && $childType !== null && $childType !== $parentType && !$this->widens($childType, $parentType)) {
                $problems[] = sprintf('%s::%s() types $%s as %s, parent has %s', $class, $child->getName(), $param->getName(), $childType, $parentType);
            }
        }
        if (count($child->getParameters()) < count(array_filter($parentParams, static fn (\ReflectionParameter $p): bool => !$p->isOptional()))) {
            $problems[] = sprintf('%s::%s() drops required parameters of the parent', $class, $child->getName());
        }
        $parentReturn = $this->typeName($parent->getReturnType());
        $childReturn = $this->typeName($child->getReturnType());
        if ($parentReturn !== null && $childReturn === null && $parentReturn !== 'mixed') {
            $problems[] = sprintf('%s::%s() drops the return type %s of the parent', $class, $child->getName(), $parentReturn);
        }

        return $problems;
    }

    private function typeName(?\ReflectionType $type): ?string
    {
        if ($type === null) {
            return null;
        }
        if ($type instanceof \ReflectionNamedType) {
            return ($type->allowsNull() && $type->getName() !== 'mixed' && $type->getName() !== 'null' ? '?' : '') . $type->getName();
        }

        return (string)$type;
    }

    /** A child may widen: accept null where the parent does not, or a parent class/interface of the parent's type. */
    private function widens(string $child, string $parent): bool
    {
        if (ltrim($child, '?') === ltrim($parent, '?') && str_starts_with($child, '?')) {
            return true;
        }
        $c = ltrim($child, '?');
        $p = ltrim($parent, '?');
        if ($c === 'mixed') {
            return true;
        }

        return (class_exists($p) || interface_exists($p)) && (class_exists($c) || interface_exists($c)) && is_a($p, $c, true);
    }

    /** @return string[] */
    private function moduleClasses(): array
    {
        $moduleDir = dirname(__DIR__, 3);
        $classes = [];
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($moduleDir, \FilesystemIterator::SKIP_DOTS));
        foreach ($iterator as $file) {
            /** @var \SplFileInfo $file */
            $path = $file->getPathname();
            $relative = substr($path, strlen($moduleDir));
            if ($file->getExtension() !== 'php' || preg_match('#/(Test|vendor|view)/#', $relative) || !preg_match('/^[A-Z]/', basename($path))) {
                continue;
            }
            $class = 'TradeTracker\\Connect\\' . str_replace('/', '\\', substr($relative, 1, -4));
            if (class_exists($class)) {
                $classes[] = $class;
            }
        }
        sort($classes);

        return $classes;
    }
}
