<?php
/**
 * Copyright © TradeTracker. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace TradeTracker\Connect\Test\Unit\Architecture;

use PHPUnit\Framework\TestCase;

/**
 * Every cron job in crontab.xml points at a class and method that exist, has either a fixed
 * <schedule> or an admin <config_path> (never both, never neither), and lives in the module's own
 * cron group. A typo here only shows as a job that silently never runs.
 */
class CronJobsAreRunnableTest extends TestCase
{
    public function testEveryJobHasARunnableInstanceAndOneScheduleSource(): void
    {
        $dom = new \DOMDocument();
        $dom->load(dirname(__DIR__, 3) . '/etc/crontab.xml');
        $problems = [];
        $groups = [];
        $jobs = 0;
        foreach ($dom->getElementsByTagName('group') as $group) {
            /** @var \DOMElement $group */
            // The name is the module's to choose; what matters is that it is not Magento's
            // default group, where a slow feed run holds up every other cron on the shop.
            $this->assertNotSame('default', $group->getAttribute('id'), 'jobs run in the module\'s own cron group');
            $groups[] = $group->getAttribute('id');
            foreach ($group->getElementsByTagName('job') as $job) {
                /** @var \DOMElement $job */
                $jobs++;
                $name = $job->getAttribute('name');
                $instance = $job->getAttribute('instance');
                $method = $job->getAttribute('method');
                if (!class_exists($instance)) {
                    $problems[] = "$name: class $instance does not exist";
                } elseif (!method_exists($instance, $method)) {
                    $problems[] = "$name: $instance::$method() does not exist";
                }
                $schedule = $job->getElementsByTagName('schedule')->length;
                $configPath = $job->getElementsByTagName('config_path')->length;
                if ($schedule + $configPath !== 1) {
                    $problems[] = "$name: needs exactly one of <schedule> or <config_path>";
                }
                if (!str_starts_with($name, $group->getAttribute('id'))) {
                    $problems[] = "$name: job name does not start with its group";
                }
            }
        }
        $this->assertGreaterThan(0, $jobs, 'no cron jobs declared');
        $this->assertSame([], $problems);
    }

    public function testTheCronGroupIsDeclaredWithASeparateProcess(): void
    {
        $crontab = new \DOMDocument();
        $crontab->load(dirname(__DIR__, 3) . '/etc/crontab.xml');

        $groups = new \DOMDocument();
        $groups->load(dirname(__DIR__, 3) . '/etc/cron_groups.xml');
        $xpath = new \DOMXPath($groups);

        foreach ($crontab->getElementsByTagName('group') as $group) {
            /** @var \DOMElement $group */
            $id = $group->getAttribute('id');
            $this->assertSame(
                '1',
                $xpath->evaluate('string(//group[@id="' . $id . '"]/use_separate_process)'),
                $id . ' is not declared in cron_groups.xml with its own process'
            );
        }
    }
}
