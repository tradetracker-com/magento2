<?php
/**
 * Copyright © TradeTracker. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace TradeTracker\Connect\Test\Unit\Architecture;

use PHPUnit\Framework\TestCase;

/**
 * A cron job's frequency has to be the same setting the admin writes, and nothing checks that.
 *
 * The right construction is the simple one: the admin field owns the setting, `config.xml` gives it
 * a default, and `etc/crontab.xml` points `<config_path>` straight at it. Then there is one path
 * and nothing to drift.
 *
 * It drifts when a module puts something in between. Up to release 1.8.0 this module's field
 * wrote `tradetracker/feed/cron_frequency`, a backend model copied the expression to
 * `crontab/default/jobs/tradetracker_generate_feeds/schedule`, and the job itself checked
 * `tradetracker/feeds/cron_frequency` — three paths, none of them agreeing — so the feed cron
 * never generated anything. Nothing errored.
 *
 * Deliberately static: no database, no Magento bootstrap. The defect is in the files.
 */
class CronScheduleWiringTest extends TestCase
{
    public function testEveryConfigPathACronJobReadsIsWrittenBySomething(): void
    {
        $jobs = $this->cronJobs();

        if ($jobs === []) {
            $this->markTestSkipped('This module declares no cron jobs.');
        }

        $written = $this->pathsSomethingWrites();
        $problems = [];

        foreach ($jobs as $name => $configPath) {
            if ($configPath === null) {
                // No config_path: the schedule comes from crontab.xml or from Magento's own
                // `crontab/<group>/jobs/<name>/schedule` convention. Nothing to disagree about.
                continue;
            }

            if (!in_array($configPath, $written, true)) {
                $problems[] = sprintf(
                    "%s reads %s\n    but nothing writes that path. Written paths are:\n      - %s",
                    $name,
                    $configPath,
                    implode("\n      - ", $written) ?: '(none)'
                );
            }
        }

        $this->assertSame(
            [],
            $problems,
            "A cron job reading a path nothing writes runs on its fallback schedule, or never:\n\n"
            . implode("\n\n", $problems)
        );
    }

    /**
     * The other direction: a value saved where no job reads it is dead configuration.
     *
     * Less harmful than the case above — the merchant's setting is merely ignored rather than
     * silently replaced — but it is the same drift, and it is usually how the first one starts.
     */
    public function testEveryPathTheBackendModelWritesIsReadBySomething(): void
    {
        $backend = $this->backendModelPaths();

        if ($backend === []) {
            $this->markTestSkipped('This module has no cron backend model.');
        }

        $read = array_values(array_filter($this->cronJobs()));
        $problems = [];

        foreach ($backend as $path) {
            // Magento reads this shape natively, without a config_path in crontab.xml.
            if (preg_match('#^crontab/[^/]+/jobs/[^/]+/schedule(/cron_expr)?$#', $path)) {
                continue;
            }

            if (!in_array($path, $read, true)) {
                $problems[] = sprintf('%s is written on save but no job reads it.', $path);
            }
        }

        $this->assertSame([], $problems, implode("\n", $problems));
    }

    /**
     * A job that is scheduled but cannot run.
     *
     * `crontab.xml` names a class and a method, and nothing checks either. A renamed class, a typo
     * in the method, or a job left behind after a refactor all look identical from the outside: the
     * admin shows a frequency, `cron_schedule` fills up, and the job fails — or never appears — in
     * production.
     *
     * Ported from magento2-bol, where this is `CronJobsAreRunnableTest`.
     */
    public function testEveryJobNamesAClassAndMethodThatExist(): void
    {
        $problems = [];

        foreach ($this->jobDefinitions() as $name => $job) {
            $relative = str_replace(
                ['TradeTracker\\Connect\\', '\\'],
                ['', '/'],
                $job['instance']
            );
            $file = $this->modulePath($relative . '.php');

            if (!is_file($file)) {
                $problems[] = sprintf('%s points at %s, which has no file', $name, $job['instance']);
                continue;
            }

            $pattern = '/function\s+' . preg_quote($job['method'], '/') . '\s*\(/';
            if (!preg_match($pattern, (string)file_get_contents($file))) {
                $problems[] = sprintf(
                    '%s calls %s::%s(), which is not declared there',
                    $name,
                    $job['instance'],
                    $job['method']
                );
            }
        }

        $this->assertSame([], $problems, implode("\n", $problems));
    }

    /**
     * Every job must have a frequency it can read on a fresh install.
     *
     * Either a literal `<schedule>` in crontab.xml, or a `<config_path>` that `config.xml` gives a
     * default. A job with neither never runs until somebody opens the configuration and saves it —
     * which is how magento2-bol's invoice upload sat idle from the day it shipped.
     */
    public function testEveryJobHasAScheduleItCanActuallyRead(): void
    {
        $defaults = simplexml_load_file($this->modulePath('etc/config.xml'));

        if ($defaults === false) {
            $this->markTestSkipped('This module has no etc/config.xml.');
        }

        $problems = [];

        foreach ($this->jobDefinitions() as $name => $job) {
            if ($job['schedule'] !== null && $job['schedule'] !== '') {
                continue;
            }

            if ($job['configPath'] === null) {
                $problems[] = sprintf(
                    '%s has neither a <schedule> nor a <config_path>, so it has no frequency at all',
                    $name
                );
                continue;
            }

            if (!$defaults->xpath('default/' . $job['configPath'])) {
                $problems[] = sprintf(
                    '%s reads %s, which config.xml gives no default — the job stays unscheduled '
                    . 'until someone saves the configuration',
                    $name,
                    $job['configPath']
                );
            }
        }

        $this->assertSame([], $problems, implode("\n", $problems));
    }

    /**
     * Every job crontab.xml declares, with what it says about how to run and when.
     *
     * @return array<string, array{instance: string, method: string, schedule: ?string, configPath: ?string}>
     */
    private function jobDefinitions(): array
    {
        $file = $this->modulePath('etc/crontab.xml');

        if (!is_file($file)) {
            return [];
        }

        $xml = simplexml_load_file($file);
        $jobs = [];

        foreach ($xml->group ?? [] as $group) {
            foreach ($group->job ?? [] as $job) {
                $schedule = isset($job->schedule) ? trim((string)$job->schedule) : null;
                $configPath = isset($job->config_path) ? trim((string)$job->config_path) : null;

                $jobs[(string)$job['name']] = [
                    'instance' => (string)$job['instance'],
                    'method' => (string)$job['method'],
                    'schedule' => $schedule === '' ? null : $schedule,
                    'configPath' => $configPath === '' ? null : $configPath,
                ];
            }
        }

        return $jobs;
    }



    /**
     * Every job crontab.xml declares, mapped to the config path it reads (or null).
     *
     * @return array<string, string|null>
     */
    private function cronJobs(): array
    {
        $file = $this->modulePath('etc/crontab.xml');

        if (!is_file($file)) {
            return [];
        }

        $xml = simplexml_load_file($file);
        $jobs = [];

        foreach ($xml->group ?? [] as $group) {
            foreach ($group->job ?? [] as $job) {
                $name = (string)$job['name'];
                $configPath = isset($job->config_path) ? trim((string)$job->config_path) : null;
                $jobs[$name] = $configPath === '' ? null : $configPath;
            }
        }

        return $jobs;
    }

    /**
     * Every config path something in this module writes a schedule to: the admin fields, and
     * whatever the cron backend model copies them to.
     *
     * @return string[]
     */
    private function pathsSomethingWrites(): array
    {
        return array_values(array_unique(array_merge(
            $this->systemFieldPaths(),
            $this->backendModelPaths()
        )));
    }

    /**
     * Config paths declared by admin fields.
     *
     * @return string[]
     */
    private function systemFieldPaths(): array
    {
        $paths = [];

        foreach ($this->systemXmlFiles() as $file) {
            preg_match_all('#<config_path>([^<]+)</config_path>#', (string)file_get_contents($file), $matches);
            foreach ($matches[1] as $path) {
                $paths[] = trim($path);
            }
        }

        return $paths;
    }

    /**
     * Config paths the cron backend model writes.
     *
     * Read out of the source rather than by loading the class: the paths live in a constant whose
     * shape differs per module (a flat list in one, a map of job name to settings in another), and
     * the only thing that matters here is which strings end up as a path.
     *
     * @return string[]
     */
    private function backendModelPaths(): array
    {
        $file = $this->modulePath('Model/Config/Backend/Cron.php');

        if (!is_file($file)) {
            return [];
        }

        preg_match_all("#'(crontab/[^']+)'#", (string)file_get_contents($file), $matches);

        return array_values(array_unique($matches[1]));
    }

    /**
     * @return string[]
     */
    private function systemXmlFiles(): array
    {
        // system.xml plus every file it includes: the <include path> elements decide where a
        // module keeps its groups, not a fixed directory name.
        $root = $this->modulePath('etc/adminhtml');
        $files = glob($root . '/system.xml') ?: [];
        foreach ($files as $main) {
            $dom = new \DOMDocument();
            $dom->load($main);
            foreach ($dom->getElementsByTagName('include') as $include) {
                /** @var \DOMElement $include */
                $file = $root . '/' . (string)preg_replace('#^[^:]+::#', '', $include->getAttribute('path'));
                if (is_file($file)) {
                    $files[] = $file;
                }
            }
        }

        return $files;
    }

    private function modulePath(string $relative): string
    {
        return dirname(__DIR__, 3) . '/' . $relative;
    }
}
