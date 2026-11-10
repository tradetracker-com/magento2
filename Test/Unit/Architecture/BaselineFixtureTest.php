<?php
/**
 * Copyright © TradeTracker. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace TradeTracker\Connect\Test\Unit\Architecture;

use PHPUnit\Framework\TestCase;

/**
 * The E2E baseline layer is deliberately identical in every module, with one file per module naming
 * what differs: the configuration sections, the console commands and the error log. That file is
 * copied when a module adopts the layer, so it is the one place where another module's values slip
 * in - and a section that does not exist takes a whole spec down three VPS rounds later.
 *
 * This checks the fixture against what the module actually ships.
 */
class BaselineFixtureTest extends TestCase
{
    private const FIXTURE = 'Test/End-2-end/support/fixtures/baseline.ts';

    public function testTheFixtureNamesEveryConfigurationSectionAndNoOther(): void
    {
        self::assertSame(
            $this->sectionsInSystemXml(),
            $this->listed('SECTIONS'),
            'SECTIONS in ' . self::FIXTURE . ' does not match etc/adminhtml/system.xml'
        );
    }

    public function testEveryCommandTheFixtureNamesIsRegistered(): void
    {
        $registered = $this->registeredCommands();
        $unknown = array_values(array_diff($this->listed('CLI_COMMANDS'), $registered));

        self::assertSame(
            [],
            $unknown,
            sprintf(
                "CLI_COMMANDS in %s names commands the module does not register.\nRegistered: %s",
                self::FIXTURE,
                implode(', ', $registered)
            )
        );
    }

    public function testTheErrorLogTheFixtureNamesIsTheOneTheLoggerWritesTo(): void
    {
        $logs = $this->logFileNames();
        if ($logs === []) {
            self::markTestSkipped('The module ships no logger with a file name of its own.');
        }

        $named = $this->value('ERROR_LOG');
        self::assertContains(
            $named,
            $logs,
            sprintf('ERROR_LOG in %s is not a log this module writes: %s', self::FIXTURE, implode(', ', $logs))
        );
    }

    /**
     * @return string[]
     */
    private function sectionsInSystemXml(): array
    {
        $file = $this->moduleDir() . '/etc/adminhtml/system.xml';
        preg_match_all('#<section id="([^"]+)"#', (string)file_get_contents($file), $matches);

        return $matches[1];
    }

    /**
     * Console command names, as the Command classes declare them.
     *
     * @return string[]
     */
    private function registeredCommands(): array
    {
        $names = [];
        foreach ($this->phpFilesIn($this->moduleDir() . '/Console') as $file) {
            preg_match_all("#COMMAND_NAME\s*=\s*'([^']+)'#", (string)file_get_contents($file), $matches);
            foreach ($matches[1] as $name) {
                $names[$name] = true;
            }
        }

        return array_keys($names);
    }

    /**
     * @return string[]
     */
    private function logFileNames(): array
    {
        $names = [];
        foreach ($this->phpFilesIn($this->moduleDir() . '/Logger') as $file) {
            // The handler names a path ('/var/log/tradetracker-error.log'); the fixture names the file.
            preg_match_all("#'([a-z0-9._/-]+\.log)'#i", (string)file_get_contents($file), $matches);
            foreach ($matches[1] as $name) {
                $names[basename($name)] = true;
            }
        }

        return array_keys($names);
    }

    /**
     * The string values of an exported array constant in the fixture.
     *
     * @return string[]
     */
    private function listed(string $constant): array
    {
        $source = $this->fixture();
        if (!preg_match('#export const ' . $constant . '[^=]*=\s*\[(.*?)\];#s', $source, $match)) {
            self::fail($constant . ' is not exported from ' . self::FIXTURE);
        }
        preg_match_all("#'([^']+)'#", $match[1], $values);

        return $values[1];
    }

    private function value(string $constant): string
    {
        $source = $this->fixture();
        if (!preg_match('#export const ' . $constant . "[^=]*=\s*'([^']+)';#", $source, $match)) {
            self::fail($constant . ' is not exported from ' . self::FIXTURE);
        }

        return $match[1];
    }

    private function fixture(): string
    {
        $file = $this->moduleDir() . '/' . self::FIXTURE;
        if (!is_file($file)) {
            self::markTestSkipped('The module has not adopted the baseline layer.');
        }

        return (string)file_get_contents($file);
    }

    /**
     * @return string[]
     */
    private function phpFilesIn(string $directory): array
    {
        if (!is_dir($directory)) {
            return [];
        }

        $files = [];
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($directory));
        foreach ($iterator as $file) {
            if ($file->isFile() && $file->getExtension() === 'php') {
                $files[] = $file->getPathname();
            }
        }

        return $files;
    }

    private function moduleDir(): string
    {
        return dirname(__DIR__, 3);
    }
}
