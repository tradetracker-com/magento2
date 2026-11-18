<?php
/**
 * Copyright © Magmodules.eu. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace TradeTracker\Connect\Service\ProductData;

/**
 * Decides which parents and which children leave the feed, from the per-type behaviour settings.
 *
 * A simple can be the child of more than one parent: a yoga strap sold on its own through a
 * grouped product and packed into a bundle. The settings for those two types can disagree, and
 * the child must not be dropped by the stricter one: it stays as soon as one of its parent
 * relations wants it ("Only Linked Simple Products", "... and Linked Simple Products", or "No"),
 * and goes only when every parent that is in the feed itself says "Only Parent Product".
 *
 * The same child also inherits from one parent only: its title, URL suffix, images and
 * categories. That is the configurable when it has one, then the grouped, then the bundle. The
 * database returns the relations in no particular order, so the collector asks here first.
 */
class ChildSelection
{
    private const PRECEDENCE = ['configurable' => 0, 'grouped' => 1, 'bundle' => 2];

    /**
     * Order every child's parents by type precedence, so the first one is the one it follows.
     *
     * @param array<int, array<int, string>> $parents child id => [parent id => parent type]
     * @return array<int, array<int, string>> the same map, parents in precedence order
     */
    public function orderParents(array $parents): array
    {
        foreach ($parents as &$relations) {
            if (count($relations) < 2) {
                continue;
            }
            uasort($relations, static function (string $left, string $right): int {
                return (self::PRECEDENCE[$left] ?? PHP_INT_MAX) <=> (self::PRECEDENCE[$right] ?? PHP_INT_MAX);
            });
        }

        return $parents;
    }

    /**
     * @param array<int, array<int, string>> $parents child id => [parent id => parent type]
     * @param array<string, array{use?: string|null}> $behaviours settings per parent type
     * @param int[] $entityIds the ids the feed covers
     * @return array{parents: int[], children: int[]} ids to leave out
     */
    public function resolve(array $parents, array $behaviours, array $entityIds): array
    {
        $dropParents = [];
        $dropChildren = [];
        $keepChildren = [];
        $inFeed = array_flip($entityIds);

        foreach ($parents as $childId => $relations) {
            foreach ($relations as $parentId => $parentType) {
                if (!isset($behaviours[$parentType])) {
                    continue;
                }
                $use = $behaviours[$parentType]['use'] ?? '';

                // "No" (an empty value) leaves the parent out, like "only simples"; its linked
                // simples are then handled as standalone products.
                if (empty($use) || $use === 'simple') {
                    $dropParents[$parentId] = true;
                    $keepChildren[$childId] = true;
                } elseif ($use === 'parent') {
                    // A parent that is not in the feed itself has no say over its child.
                    if (isset($inFeed[$parentId])) {
                        $dropChildren[$childId] = true;
                    }
                } else {
                    $keepChildren[$childId] = true;
                }
            }
        }

        return [
            'parents' => array_keys($dropParents),
            'children' => array_keys(array_diff_key($dropChildren, $keepChildren)),
        ];
    }
}
