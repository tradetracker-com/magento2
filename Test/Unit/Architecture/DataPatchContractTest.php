<?php
/**
 * Copyright © TradeTracker. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace TradeTracker\Connect\Test\Unit\Architecture;

use Magento\Framework\Setup\Patch\DataPatchInterface;
use Magento\Framework\Setup\Patch\PatchInterface;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionNamedType;

/**
 * Magento's PatchInterface::apply() is documented to return $this. A patch declared `: void` runs fine
 * today, but is incompatible with the interface the moment Magento adds the native return type.
 */
class DataPatchContractTest extends TestCase
{
    public function testEveryDataPatchReturnsItself(): void
    {
        $patches = glob(dirname(__DIR__, 3) . '/Setup/Patch/Data/*.php') ?: [];
        if ($patches === []) {
            $this->markTestSkipped('This module has no data patches.');
        }

        foreach ($patches as $file) {
            $class = 'TradeTracker\\Connect\\Setup\\Patch\\Data\\' . basename($file, '.php');
            $reflection = new ReflectionClass($class);
            self::assertTrue($reflection->implementsInterface(DataPatchInterface::class), $class);

            $returnType = $reflection->getMethod('apply')->getReturnType();
            self::assertInstanceOf(ReflectionNamedType::class, $returnType, "$class::apply() has no return type");
            self::assertContains($returnType->getName(), ['self', 'static', $class, DataPatchInterface::class, PatchInterface::class], "$class::apply() must return \$this");
        }
    }
}
