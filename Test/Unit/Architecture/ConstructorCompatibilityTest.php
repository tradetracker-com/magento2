<?php
/**
 * Copyright © TradeTracker. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace TradeTracker\Connect\Test\Unit\Architecture;

use PHPUnit\Framework\TestCase;

/**
 * Magento's DI compiler refuses a constructor whose arguments are incompatible with its parent's,
 * matched by argument name. That check only runs during `setup:di:compile`, which happens at the
 * very end of a deploy — so a widened parameter type costs a full failed deploy to discover.
 *
 * This reproduces the same check in milliseconds:
 *
 *     Incompatible argument type: Required type: \Exception. Actual type: \Throwable
 *
 * is what cost one E2E run, on ApiException::__construct($cause).
 */
class ConstructorCompatibilityTest extends TestCase
{
    public function testEveryConstructorIsCompatibleWithItsParent(): void
    {
        $problems = [];

        foreach ($this->moduleClasses() as $class) {
            $problems = array_merge($problems, $this->check($class));
        }

        $this->assertSame(
            [],
            $problems,
            "setup:di:compile will reject these:\n- " . implode("\n- ", $problems)
        );
    }

    /**
     * @return string[]
     */
    private function check(string $class): array
    {
        try {
            $reflection = new \ReflectionClass($class);
        } catch (\Throwable $e) {
            return [];
        }

        $parent = $reflection->getParentClass();
        if (!$parent || !$reflection->getConstructor() || $reflection->getConstructor()->class !== $class) {
            return [];
        }

        $parentConstructor = $parent->getConstructor();
        if (!$parentConstructor) {
            return [];
        }

        $parentTypes = [];
        foreach ($parentConstructor->getParameters() as $parameter) {
            $parentTypes[$parameter->getName()] = $this->typeName($parameter);
        }

        $problems = [];
        foreach ($reflection->getConstructor()->getParameters() as $parameter) {
            $name = $parameter->getName();
            if (!isset($parentTypes[$name])) {
                continue;
            }

            $own = $this->typeName($parameter);
            if (!$this->isCompatible($parentTypes[$name], $own)) {
                $problems[] = sprintf(
                    '%s::__construct($%s) is %s where %s declares %s',
                    $class,
                    $name,
                    $own,
                    $parent->getName(),
                    $parentTypes[$name]
                );
            }
        }

        return $problems;
    }

    /**
     * Magento's rule (Magento\Framework\Code\Reader\ArgumentsReader::isCompatibleType): equal types, an
     * untyped side, or a subclass of what the parent declares are compatible; a narrowed `array` is not.
     */
    private function isCompatible(?string $required, ?string $actual): bool
    {
        if ($required === $actual || $required === null || $actual === null) {
            return true;
        }
        if ($required === 'array' || $actual === 'array') {
            return false;
        }
        if ($required === 'mixed' || $actual === 'mixed') {
            return true;
        }

        return is_subclass_of($actual, $required);
    }

    private function typeName(\ReflectionParameter $parameter): ?string
    {
        $type = $parameter->getType();

        return $type instanceof \ReflectionNamedType ? ltrim($type->getName(), '\\') : null;
    }

    /**
     * Every class this module ships, derived from the files on disk.
     *
     * @return string[]
     */
    private function moduleClasses(): array
    {
        $moduleDir = dirname(__DIR__, 3);
        $classes = [];

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($moduleDir, \FilesystemIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            /** @var \SplFileInfo $file */
            $path = $file->getPathname();

            if ($file->getExtension() !== 'php' || $this->isExcluded($path, $moduleDir)) {
                continue;
            }

            $relative = substr($path, strlen($moduleDir) + 1, -strlen('.php'));
            $class = 'TradeTracker\\Connect\\' . str_replace('/', '\\', $relative);

            if (class_exists($class)) {
                $classes[] = $class;
            }
        }

        sort($classes);

        return $classes;
    }

    private function isExcluded(string $path, string $moduleDir): bool
    {
        foreach (['/Test/', '/vendor/', '/view/'] as $fragment) {
            if (str_contains(substr($path, strlen($moduleDir)), $fragment)) {
                return true;
            }
        }

        // registration.php and friends are scripts, not classes — running class_exists() on them
        // executes the file, and registering the module twice throws.
        return !preg_match('/^[A-Z]/', basename($path));
    }
}
