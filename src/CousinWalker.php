<?php

declare(strict_types=1);

namespace Vytux\WebtreesModules\VytuxCousins;

use Closure;

/**
 * Pure family-tree traversal used to find Nth cousins, independent of webtrees classes.
 *
 * @template T of object|string
 */
final class CousinWalker
{
    /**
     * @param Closure(T):array<T> $parents  parents of a person
     * @param Closure(T):array<T> $children children of a person, across all their marriages
     * @param Closure(T):string   $key      unique key of a person
     */
    public function __construct(
        private readonly Closure $parents,
        private readonly Closure $children,
        private readonly Closure $key,
    ) {
    }

    /**
     * @param array<string,T> $people
     *
     * @return array<string,T> the parents of all the given people
     */
    public function parentsOf(array $people): array
    {
        $result = [];

        foreach ($people as $person) {
            foreach (($this->parents)($person) as $parent) {
                $result[($this->key)($parent)] = $parent;
            }
        }

        return $result;
    }

    /**
     * @param T $person
     *
     * @return array<T> descendants exactly $generations below the person
     */
    public function descendants(mixed $person, int $generations): array
    {
        $current = [($this->key)($person) => $person];

        for ($i = 0; $i < $generations; $i++) {
            $next = [];
            foreach ($current as $p) {
                foreach (($this->children)($p) as $child) {
                    $next[($this->key)($child)] = $child;
                }
            }
            $current = $next;
        }

        return array_values($current);
    }
}
