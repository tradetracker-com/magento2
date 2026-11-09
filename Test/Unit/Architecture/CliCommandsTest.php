<?php
/**
 * Copyright © TradeTracker. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace TradeTracker\Connect\Test\Unit\Architecture;

use PHPUnit\Framework\TestCase;

/**
 * Every command class is registered in di.xml, every registered class exists, names share the module
 * prefix and are unique, and the CLI documentation names exactly these commands. The selftest command
 * had a class, a docs page and no di.xml entry, so `bin/magento tradetracker:selftest` did not exist.
 */
class CliCommandsTest extends TestCase
{
    private const PREFIX = 'tradetracker:';

    public function testEveryCommandClassIsRegisteredAndEveryRegistrationExists(): void
    {
        $classes = $this->commandClasses();
        $registered = $this->registeredClasses();
        $this->assertSame([], array_values(array_diff($classes, $registered)), 'command classes missing from di.xml CommandListInterface');
        $this->assertSame([], array_values(array_diff($registered, $classes)), 'di.xml registers command classes that do not exist');
    }

    public function testCommandNamesCarryThePrefixAndAreUnique(): void
    {
        $names = $this->commandNames();
        $this->assertNotEmpty($names);
        foreach ($names as $class => $name) {
            $this->assertStringStartsWith(self::PREFIX, $name, $class);
        }
        $this->assertSame(count($names), count(array_unique($names)), 'duplicate command names');
    }

    public function testDocumentationNamesExactlyTheRegisteredCommands(): void
    {
        $root = dirname(__DIR__, 3);
        // A module may document its commands in the README alone; reading a file that is not there
        // only produces a PHP warning and hides what the assertion is really saying.
        $docs = '';
        foreach (['/docs/CLI_COMMANDS.md', '/README.md'] as $file) {
            if (is_file($root . $file)) {
                $docs .= (string)file_get_contents($root . $file);
            }
        }
        preg_match_all('/\b' . preg_quote(self::PREFIX, '/') . '[a-z:-]+/', $docs, $m);
        $documented = array_values(array_unique($m[0]));
        $names = array_values($this->commandNames());
        sort($documented);
        sort($names);
        $this->assertSame($names, $documented, 'docs/CLI_COMMANDS.md + README.md must name exactly the registered commands');
    }

    /** @return string[] FQCNs under Console/Command */
    private function commandClasses(): array
    {
        $classes = [];
        foreach (glob(dirname(__DIR__, 3) . '/Console/Command/*.php') ?: [] as $file) {
            $classes[] = 'TradeTracker\\Connect\\Console\\Command\\' . basename($file, '.php');
        }
        sort($classes);

        return $classes;
    }

    /** @return string[] */
    private function registeredClasses(): array
    {
        $dom = new \DOMDocument();
        $dom->load(dirname(__DIR__, 3) . '/etc/di.xml');
        $xpath = new \DOMXPath($dom);
        $items = $xpath->query('//type[@name="Magento\\Framework\\Console\\CommandListInterface"]//item');
        $classes = [];
        foreach ($items as $item) {
            // Commands are registered through a generated proxy so that the console does not build
            // every command's dependencies on each bin/magento call; the proxy stands for the class.
            $classes[] = (string)preg_replace('/\\\\Proxy$/', '', trim($item->textContent));
        }
        sort($classes);

        return $classes;
    }

    /** @return array<string, string> class => COMMAND_NAME */
    private function commandNames(): array
    {
        $names = [];
        foreach ($this->commandClasses() as $class) {
            $source = (string)file_get_contents(dirname(__DIR__, 3) . '/Console/Command/' . substr($class, strrpos($class, '\\') + 1) . '.php');
            $this->assertSame(1, preg_match("/COMMAND_NAME = '([^']+)'/", $source, $m), $class . ' declares COMMAND_NAME');
            $names[$class] = $m[1];
        }

        return $names;
    }
}
