<?php
/**
 * Copyright © TradeTracker. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace TradeTracker\Connect\Test\Unit\Architecture;

use PHPUnit\Framework\TestCase;

/**
 * Admin grids break quietly: a data source without a CollectionFactory entry renders empty, a
 * resourceModel pointing at a collection gives mass actions no ids (MassDelete then deletes
 * everything on select-all, found in 11 modules), and a filter on a joined column without
 * addFilterToMap() fails with "Something went wrong" (the item grid's SKU/title/GTIN filters).
 */
class GridWiringTest extends TestCase
{
    public function testEveryGridDataSourceHasACollectionAndAResourceModel(): void
    {
        $this->skipWithoutOwnListings();
        foreach ($this->listings() as $file => $listing) {
            $class = $this->collectionFor($listing);
            if ($class === null) {
                continue;
            }
            $resourceModel = $this->resourceModelFor($class);
            $this->assertNotNull($resourceModel, $class . ' has no resourceModel argument in di.xml');
            $this->assertTrue(class_exists($resourceModel), $resourceModel . ' does not exist');
            $this->assertTrue(is_a($resourceModel, \Magento\Framework\Model\ResourceModel\Db\AbstractDb::class, true), $resourceModel . ' is not a resource model (points at a collection?)');
        }
    }

    public function testJoinedFilterColumnsAreMapped(): void
    {
        $this->skipWithoutOwnListings();
        $schema = $this->schemaColumns();
        $problems = [];
        foreach ($this->listings() as $file => $listing) {
            $class = $this->collectionFor($listing);
            if ($class === null) {
                continue;
            }
            $mainTable = $this->mainTableFor($class);
            $tableColumns = $schema[$mainTable] ?? [];
            $source = class_exists($class) ? (string)file_get_contents((new \ReflectionClass($class))->getFileName()) : '';
            foreach ($listing['filterColumns'] as $column) {
                if (in_array($column, $tableColumns, true) || in_array($column, ['ids', 'actions'], true)) {
                    continue;
                }
                if (!str_contains($source, "addFilterToMap('" . $column . "'")
                    && !str_contains($source, "addExpressionFilter('" . $column . "'")
                ) {
                    $problems[] = sprintf('%s filters on %s, which is not a %s column and has no addFilterToMap() in %s', basename($file), $column, $mainTable ?: '(unknown table)', $class);
                }
            }
        }
        $this->assertSame([], $problems);
    }

    public function testThereIsExactlyOneCollectionFactoryBlock(): void
    {
        $this->skipWithoutOwnListings();
        $xpath = new \DOMXPath($this->di());
        $this->assertSame(1, $xpath->query('//type[@name="Magento\\Framework\\View\\Element\\UiComponent\\DataProvider\\CollectionFactory"]')->length, 'the DI schema rejects duplicate CollectionFactory type blocks');
    }

    /**
     * A module can ship a *_listing.xml that only adds a column to someone else's grid: no
     * dataSource of its own, no CollectionFactory entry, nothing here to check. Skip rather than
     * fail, so the gate stays meaningful for the modules that do own a grid.
     */
    private function skipWithoutOwnListings(): void
    {
        if ($this->listings() === []) {
            $this->markTestSkipped('this module declares no listing of its own');
        }
    }

    /**
     * The collection behind a listing: the CollectionFactory entry for its data source, or the
     * collection its own data provider class builds. A listing over another module's entity (a
     * product picker that runs on the catalog's product collection) is that module's grid to
     * wire, and is left out.
     *
     * @param array{dataSource: string, provider: string, filterColumns: string[]} $listing
     */
    private function collectionFor(array $listing): ?string
    {
        $class = $this->collectionFactoryItems()[$listing['dataSource']] ?? null;
        if ($class === null && $listing['provider'] !== '' && class_exists($listing['provider'])) {
            $source = (string)file_get_contents((new \ReflectionClass($listing['provider']))->getFileName());
            if (preg_match('/^use ([^;]+)\\\\CollectionFactory;/m', $source, $m)) {
                $class = $m[1] . '\\Collection';
            }
        }
        if ($class === null) {
            $this->fail($listing['dataSource'] . ' is in no CollectionFactory block and its provider builds no collection');
        }

        return str_starts_with($class, 'TradeTracker\\Connect\\') ? $class : null;
    }

    /** @return array<string, array{dataSource: string, provider: string, filterColumns: string[]}> */
    private function listings(): array
    {
        $listings = [];
        // Listings only, under either name modules use for them. An edit form is a ui_component too
        // and has a dataProvider, but it loads one record by id and has no CollectionFactory entry,
        // so including it fails every assertion here.
        $files = array_merge(
            glob(dirname(__DIR__, 3) . '/view/adminhtml/ui_component/*_listing.xml') ?: [],
            glob(dirname(__DIR__, 3) . '/view/adminhtml/ui_component/*_grid.xml') ?: []
        );
        sort($files);
        foreach ($files as $file) {
            $dom = new \DOMDocument();
            $dom->load($file);
            $xpath = new \DOMXPath($dom);
            $provider = $xpath->query('//dataSource/dataProvider')->item(0) ?? $xpath->query('//dataSource/argument[@name="dataProvider"]//item[@name="name"]')->item(0);
            $dataSource = $provider instanceof \DOMElement && $provider->hasAttribute('name') ? $provider->getAttribute('name') : ($provider?->textContent ?? '');
            if ($dataSource === '') {
                continue;
            }
            $filterColumns = [];
            foreach ($xpath->query('//columns/column[filter]') as $column) {
                /** @var \DOMElement $column */
                $filterColumns[] = $column->getAttribute('name');
            }
            $providerClass = $provider instanceof \DOMElement ? $provider->getAttribute('class') : '';
            $listings[$file] = [
                'dataSource' => trim($dataSource),
                'provider' => $providerClass,
                'filterColumns' => $filterColumns,
            ];
        }
        return $listings;
    }

    /** @return array<string, string> data source name => collection class */
    private function collectionFactoryItems(): array
    {
        $xpath = new \DOMXPath($this->di());
        $items = [];
        foreach ($xpath->query('//type[@name="Magento\\Framework\\View\\Element\\UiComponent\\DataProvider\\CollectionFactory"]//item') as $item) {
            /** @var \DOMElement $item */
            $items[$item->getAttribute('name')] = trim($item->textContent);
        }

        return $items;
    }

    private function resourceModelFor(string $class): ?string
    {
        return $this->argumentFor($class, 'resourceModel') ?? $this->resourceModelFromClass($class);
    }

    /**
     * A grid backed by a model collection names no mainTable in di.xml; its resource model does,
     * through the ENTITY_TABLE constant every resource model in this module declares.
     */
    private function mainTableFor(string $class): ?string
    {
        $mainTable = $this->argumentFor($class, 'mainTable');
        if ($mainTable !== null) {
            return $mainTable;
        }
        $resourceModel = $this->resourceModelFor($class);
        if ($resourceModel !== null && class_exists($resourceModel) && defined($resourceModel . '::ENTITY_TABLE')) {
            return (string)constant($resourceModel . '::ENTITY_TABLE');
        }

        return null;
    }

    private function argumentFor(string $class, string $argument): ?string
    {
        $xpath = new \DOMXPath($this->di());
        $node = $xpath->query(sprintf('//*[(self::type or self::virtualType) and @name="%s"]/arguments/argument[@name="%s"]', $class, $argument))->item(0);

        return $node ? trim($node->textContent) : null;
    }

    /**
     * A concrete collection class may pass the resource model to its parent constructor itself, or
     * name it in _construct() through _init(Model::class, ResourceModel::class).
     */
    private function resourceModelFromClass(string $class): ?string
    {
        if (!class_exists($class)) {
            return null;
        }
        $source = (string)file_get_contents((new \ReflectionClass($class))->getFileName());

        if (preg_match('/\$resourceModel = ([A-Za-z\\\\]+)::class/', $source, $m)) {
            return $this->resolve($class, $m[1]);
        }
        if (preg_match('/_init\([A-Za-z\\\\]+::class, ([A-Za-z\\\\]+)::class\)/', $source, $m)) {
            return $this->resolve($class, $m[1]);
        }

        return null;
    }

    private function resolve(string $context, string $short): string
    {
        if (str_starts_with($short, '\\')) {
            return ltrim($short, '\\');
        }
        $source = (string)file_get_contents((new \ReflectionClass($context))->getFileName());
        if (preg_match('/^use ([^;]+\\\\' . preg_quote($short, '/') . ');/m', $source, $m)) {
            return $m[1];
        }
        if (preg_match('/^use ([^;]+) as ' . preg_quote($short, '/') . ';/m', $source, $m)) {
            return $m[1];
        }

        return (new \ReflectionClass($context))->getNamespaceName() . '\\' . $short;
    }

    /** @return array<string, string[]> table => columns from db_schema.xml */
    private function schemaColumns(): array
    {
        $dom = new \DOMDocument();
        $dom->load(dirname(__DIR__, 3) . '/etc/db_schema.xml');
        $tables = [];
        foreach ($dom->getElementsByTagName('table') as $table) {
            /** @var \DOMElement $table */
            foreach ($table->getElementsByTagName('column') as $column) {
                /** @var \DOMElement $column */
                $tables[$table->getAttribute('name')][] = $column->getAttribute('name');
            }
        }

        return $tables;
    }

    private function di(): \DOMDocument
    {
        $dom = new \DOMDocument();
        $dom->load(dirname(__DIR__, 3) . '/etc/di.xml');

        return $dom;
    }
}
