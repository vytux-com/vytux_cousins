<?php

declare(strict_types=1);

namespace Vytux\WebtreesModules\VytuxCousins\Tests;

use PHPUnit\Framework\TestCase;
use Vytux\WebtreesModules\VytuxCousins\CousinWalker;

final class CousinWalkerTest extends TestCase
{
    /** child => parents */
    private const PARENTS = [
        'g1'  => ['gg'],
        'g1b' => ['gg'],
        'p1'  => ['g1'],
        'p1b' => ['g1b'],
        'me'  => ['p1'],
        'c2'  => ['p1b'],
        'c2k' => ['c2'],
    ];

    private function walker(): CousinWalker
    {
        $children = [];
        foreach (self::PARENTS as $child => $parents) {
            foreach ($parents as $parent) {
                $children[$parent][] = $child;
            }
        }

        return new CousinWalker(
            fn (string $p): array => self::PARENTS[$p] ?? [],
            fn (string $p): array => $children[$p] ?? [],
            fn (string $p): string => $p,
        );
    }

    public function testParentsOfWalksUpOneGeneration(): void
    {
        $walker = $this->walker();

        self::assertSame(['p1'], array_keys($walker->parentsOf(['me' => 'me'])));
        self::assertSame(['g1'], array_keys($walker->parentsOf(['p1' => 'p1'])));
        self::assertSame([], $walker->parentsOf(['gg' => 'gg']));
    }

    public function testDescendantsAreExactlyNGenerationsDown(): void
    {
        $walker = $this->walker();

        self::assertSame(['g1', 'g1b'], $walker->descendants('gg', 1));
        self::assertSame(['me', 'c2'], $walker->descendants('gg', 3));
        self::assertSame(['c2k'], $walker->descendants('g1b', 3));
        self::assertSame([], $walker->descendants('me', 1));
    }

    public function testSecondCousinIsFoundViaGrandparentsSibling(): void
    {
        $walker = $this->walker();

        // Me -> parent p1 -> grandparent g1; g1's sibling is g1b; its grandchildren are my second cousins.
        self::assertSame(['g1'], array_keys($walker->parentsOf(['p1' => 'p1'])));
        self::assertSame(['c2'], $walker->descendants('g1b', 2));
    }

    public function testPeopleReachedTwiceAreListedOnce(): void
    {
        $walker = new CousinWalker(
            fn (string $p): array => [],
            fn (string $p): array => match ($p) {
                'a'     => ['b', 'c'],
                'b', 'c' => ['x'],
                default => [],
            },
            fn (string $p): string => $p,
        );

        self::assertSame(['x'], $walker->descendants('a', 2));
    }
}
