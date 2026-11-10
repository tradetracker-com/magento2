<?php
/**
 * Copyright © Magmodules.eu. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace TradeTracker\Connect\Test\Unit\Service\ProductData;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use TradeTracker\Connect\Service\ProductData\ChildSelection;

/**
 * The yoga strap of the sample catalogue is sold through a grouped product and packed into a
 * bundle. With grouped on "Only Linked Simple Products" and bundle on "Only Bundle Product" it
 * never reached the feed: the bundle setting dropped the child whatever the grouped one said.
 */
#[CoversClass(ChildSelection::class)]
class ChildSelectionTest extends TestCase
{
    private const STRAP = 37;
    private const GROUPED = 50;
    private const BUNDLE = 49;

    public function testAChildStaysWhenOneOfItsParentTypesAsksForSimples(): void
    {
        $result = $this->resolve(['grouped' => 'simple', 'bundle' => 'parent']);

        self::assertSame([self::GROUPED], $result['parents']);
        self::assertSame([], $result['children']);
    }

    public function testAChildGoesWhenEveryParentInTheFeedSaysParentOnly(): void
    {
        $result = $this->resolve(['grouped' => 'parent', 'bundle' => 'parent']);

        self::assertSame([], $result['parents']);
        self::assertSame([self::STRAP], $result['children']);
    }

    public function testAParentThatIsNotInTheFeedDoesNotTakeItsChildWithIt(): void
    {
        $result = $this->resolve(['grouped' => 'parent', 'bundle' => 'parent'], [self::STRAP, self::GROUPED]);

        self::assertSame([], $result['parents']);
        self::assertSame([self::STRAP], $result['children']);

        $result = $this->resolve(['grouped' => 'parent', 'bundle' => 'parent'], [self::STRAP]);
        self::assertSame([], $result['children']);
    }

    public function testBothKeepsParentAndChild(): void
    {
        $result = $this->resolve(['grouped' => 'both', 'bundle' => 'both']);

        self::assertSame([], $result['parents']);
        self::assertSame([], $result['children']);
    }

    public function testNoLeavesTheParentOutAndTheChildIn(): void
    {
        $result = $this->resolve(['grouped' => '', 'bundle' => 'parent']);

        self::assertSame([self::GROUPED], $result['parents']);
        self::assertSame([], $result['children']);
    }

    public function testAParentTypeWithoutASettingIsIgnored(): void
    {
        $result = (new ChildSelection())->resolve(
            [self::STRAP => [self::BUNDLE => 'bundle']],
            ['grouped' => ['use' => 'simple']],
            [self::STRAP, self::BUNDLE]
        );

        self::assertSame(['parents' => [], 'children' => []], $result);
    }

    /**
     * @param array<string, string> $use the "use" setting per parent type
     * @param int[]|null $entityIds the ids in the feed; every id by default
     * @return array{parents: int[], children: int[]}
     */
    private function resolve(array $use, ?array $entityIds = null): array
    {
        $behaviours = [];
        foreach ($use as $type => $value) {
            $behaviours[$type] = ['use' => $value];
        }

        return (new ChildSelection())->resolve(
            [self::STRAP => [self::BUNDLE => 'bundle', self::GROUPED => 'grouped']],
            $behaviours,
            $entityIds ?? [self::STRAP, self::GROUPED, self::BUNDLE]
        );
    }
}
