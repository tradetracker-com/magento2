<?php
/**
 * Copyright © TradeTracker. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace TradeTracker\Connect\Test\Unit\Architecture;

use Magento\Backend\App\Action;
use PHPUnit\Framework\TestCase;

/**
 * Every admin controller has to name an ACL resource that etc/acl.xml declares, and the resource
 * has to match what the controller does: the config endpoints sit under the configuration
 * resource, the grid and view under withdrawal_view, and everything that changes a withdrawal
 * under withdrawal_process.
 *
 * Without its own constant a controller inherits Magento_Backend::admin, which every admin role
 * holds; with a constant that acl.xml does not declare it is denied to everyone.
 */
class AdminResourceTest extends TestCase
{
    private const CONFIG = 'TradeTracker_Connect::config';

    /**
     * Every admin controller below Controller/Adminhtml and the resource it must declare. A module
     * with its own grid adds its view/process resources here; the config endpoints stay on CONFIG.
     */
    private const EXPECTED = [
        'Credentials\Check' => self::CONFIG,
        'Feed\Download' => self::CONFIG,
        'Feed\Generate' => self::CONFIG,
        'Feed\Preview' => self::CONFIG,
        'Log\Stream' => self::CONFIG,
        'Transaction\Status' => self::CONFIG,
        'VersionCheck\Changelog' => self::CONFIG,
        'VersionCheck\Index' => self::CONFIG,
    ];

    /**
     * @return string[]
     */
    private function declaredResources(): array
    {
        $xml = simplexml_load_file(dirname(__DIR__, 3) . '/etc/acl.xml');
        self::assertNotFalse($xml);

        $ids = [];
        foreach ($xml->xpath('//resource') as $resource) {
            $ids[] = (string)$resource['id'];
        }

        return $ids;
    }

    /**
     * @return string[] relative class names below Controller\Adminhtml
     */
    private function adminControllers(): array
    {
        $dir = dirname(__DIR__, 3) . '/Controller/Adminhtml';
        $classes = [];
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS));
        foreach ($iterator as $file) {
            /** @var \SplFileInfo $file */
            if ($file->getExtension() !== 'php') {
                continue;
            }
            $relative = substr($file->getPathname(), strlen($dir) + 1, -4);
            $classes[] = str_replace('/', '\\', $relative);
        }
        sort($classes);

        return $classes;
    }

    public function testEveryAdminControllerIsListedWithItsResource(): void
    {
        $controllers = $this->adminControllers();
        self::assertNotEmpty($controllers);
        $expected = array_keys(self::EXPECTED);
        sort($expected);
        self::assertSame($expected, $controllers, 'a controller was added or removed: update EXPECTED');
    }

    public function testEveryAdminControllerUsesADeclaredResource(): void
    {
        $declared = $this->declaredResources();

        foreach (self::EXPECTED as $relative => $resource) {
            $class = 'TradeTracker\\Connect\\Controller\\Adminhtml\\' . $relative;
            self::assertTrue(class_exists($class), $class);
            self::assertTrue(is_subclass_of($class, Action::class), "$class extends the backend Action");
            self::assertSame($resource, constant($class . '::ADMIN_RESOURCE'), $class);
            self::assertContains($resource, $declared, "$resource is declared in etc/acl.xml");
            self::assertNotSame('Magento_Backend::admin', constant($class . '::ADMIN_RESOURCE'));
        }
    }

    public function testTheConfigResourceSitsUnderConfigurationAndTheSystemSectionUsesIt(): void
    {
        $xml = simplexml_load_file(dirname(__DIR__, 3) . '/etc/acl.xml');
        self::assertNotFalse($xml);
        self::assertCount(1, $xml->xpath('//resource[@id="Magento_Config::config"]/resource[@id="' . self::CONFIG . '"]'));

        // A module may ship more than one configuration section; every one of them must be behind
        // the module's own resource, not just the first.
        $system = simplexml_load_file(dirname(__DIR__, 3) . '/etc/adminhtml/system.xml');
        self::assertNotFalse($system);
        $sections = $system->xpath('//section');
        self::assertNotSame([], $sections, 'no configuration section found');
        foreach ($sections as $section) {
            self::assertSame(
                self::CONFIG,
                (string)($section->resource[0] ?? ''),
                (string)$section['id'] . ' is not behind ' . self::CONFIG
            );
        }
    }
}
