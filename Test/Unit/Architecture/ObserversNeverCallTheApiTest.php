<?php
/**
 * Copyright © TradeTracker. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace TradeTracker\Connect\Test\Unit\Architecture;

use PHPUnit\Framework\TestCase;

/**
 * No observer and no order-page tab block may reach the merchant API. An observer runs inside an
 * order or credit memo save, the tab block inside the order page: a TradeTracker outage held both
 * for up to 60 seconds per request until 1.10.0. They queue, or they link; the cron and the tab
 * endpoint do the talking.
 */
class ObserversNeverCallTheApiTest extends TestCase
{
    private const FORBIDDEN = [
        'TradeTracker\Connect\Service\Api\Adapter',
        'TradeTracker\Connect\Service\Api\Client',
        'TradeTracker\Connect\Service\Transaction\Lookup',
        'TradeTracker\Connect\Service\Transaction\Assess',
        'TradeTracker\Connect\Service\Transaction\ProcessQueue',
        'SoapClient',
    ];

    private const GUARDED = [
        'Observer',
        'Block/Adminhtml/Order/View/Tab',
    ];

    public function testNothingThatRunsInsideASaveOrTheOrderPageDependsOnTheApi(): void
    {
        $problems = [];
        foreach (self::GUARDED as $directory) {
            foreach ($this->classesIn($directory) as $class) {
                $constructor = (new \ReflectionClass($class))->getConstructor();
                foreach ($constructor ? $constructor->getParameters() : [] as $parameter) {
                    $type = $parameter->getType();
                    if ($type instanceof \ReflectionNamedType && in_array($type->getName(), self::FORBIDDEN, true)) {
                        $problems[] = sprintf('%s takes %s', $class, $type->getName());
                    }
                }
            }
        }

        self::assertSame([], $problems, "These run while an admin waits and must not call TradeTracker:\n" . implode("\n", $problems));
    }

    /**
     * @return list<class-string>
     */
    private function classesIn(string $directory): array
    {
        $root = dirname(__DIR__, 3);
        $classes = [];
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root . '/' . $directory, \FilesystemIterator::SKIP_DOTS));
        foreach ($iterator as $file) {
            /** @var \SplFileInfo $file */
            if ($file->getExtension() !== 'php') {
                continue;
            }
            $relative = substr($file->getPathname(), strlen($root) + 1, -4);
            $class = 'TradeTracker\\Connect\\' . str_replace('/', '\\', $relative);
            self::assertTrue(class_exists($class), $class);
            $classes[] = $class;
        }
        self::assertNotEmpty($classes, $directory . ' holds no classes');

        return $classes;
    }
}
