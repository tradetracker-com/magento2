<?php
/**
 * Copyright © TradeTracker. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace TradeTracker\Connect\Test\Unit\Architecture;

use PHPUnit\Framework\TestCase;

/**
 * Every bin/magento call instantiates every registered command, setup:install included. A command
 * that takes a service directly pulls that service's whole graph up while Magento is still being
 * installed, and the store configuration it reaches does not exist yet: Adobe's review installed
 * 1.2.0 and got "The default website isn't defined". A proxy defers the graph until the command
 * actually runs, so each module dependency a command takes has to be wired as one in di.xml.
 */
class CliDependenciesAreProxiedTest extends TestCase
{
    public function testEveryModuleDependencyOfACommandIsInjectedAsAProxy(): void
    {
        $missing = [];
        foreach ($this->registeredCommands() as $class) {
            $overrides = $this->proxiedArguments($class);
            $constructor = (new \ReflectionClass($class))->getConstructor();
            foreach ($constructor ? $constructor->getParameters() : [] as $parameter) {
                $type = $parameter->getType();
                if (!$type instanceof \ReflectionNamedType || $type->isBuiltin()) {
                    continue;
                }
                if (!str_starts_with($type->getName(), 'TradeTracker\\Connect\\')) {
                    continue;
                }
                if (!in_array($parameter->getName(), $overrides, true)) {
                    $missing[] = sprintf('%s::$%s (%s)', $class, $parameter->getName(), $type->getName());
                }
            }
        }

        $this->assertSame([], $missing, "Command dependencies not wired as \\Proxy in di.xml:\n" . implode("\n", $missing));
    }

    /**
     * @return list<class-string>
     */
    private function registeredCommands(): array
    {
        $classes = [];
        foreach ($this->di()->xpath('//type[@name="Magento\\Framework\\Console\\CommandListInterface"]//item') as $item) {
            $classes[] = trim((string)$item);
        }
        $this->assertNotEmpty($classes, 'no commands registered in di.xml');

        return $classes;
    }

    /**
     * @return list<string> constructor argument names di.xml wires to a \Proxy for this class
     */
    private function proxiedArguments(string $class): array
    {
        $names = [];
        foreach ($this->di()->xpath(sprintf('//type[@name="%s"]/arguments/argument', $class)) as $argument) {
            if (str_ends_with(trim((string)$argument), '\\Proxy')) {
                $names[] = (string)$argument['name'];
            }
        }

        return $names;
    }

    private function di(): \SimpleXMLElement
    {
        $xml = simplexml_load_file(dirname(__DIR__, 3) . '/etc/di.xml');
        $this->assertNotFalse($xml, 'etc/di.xml does not parse');

        return $xml;
    }
}
