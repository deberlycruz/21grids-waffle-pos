<?php
namespace App\Algorithms;

// Sorting algorithms (DSA Chapter 6: divide and conquer)
class Sorting {
    // MERGE SORT
    //   Divide : split the list into two halves
    //   Conquer: sort each half (recursion; a list of 0 or 1 items is already sorted)
    //   Combine: merge the two sorted halves into one sorted list
    // Time: O(n log n) in the best, average and worst case.  Extra space: O(n).
    // $compare($first, $second) returns a negative number, 0 or a positive number,
    // like strcmp, so the same sort works for numbers, dates and names.
    // $comparisons counts the work done, so we can show the cost.
    public static function mergeSort(array $items, callable $compare, &$comparisons = 0) {
        $count = count($items);
        if ($count <= 1) {              // base case
            return $items;
        }
        $middle = intdiv($count, 2);
        $left  = self::mergeSort(array_slice($items, 0, $middle), $compare, $comparisons);
        $right = self::mergeSort(array_slice($items, $middle), $compare, $comparisons);
        return self::merge($left, $right, $compare, $comparisons);
    }

    // The merge step: repeatedly take the smaller front item of the two halves
    private static function merge(array $left, array $right, callable $compare, &$comparisons) {
        $result = [];
        $leftIndex = 0;
        $rightIndex = 0;
        while ($leftIndex < count($left) && $rightIndex < count($right)) {
            $comparisons++;
            if ($compare($left[$leftIndex], $right[$rightIndex]) <= 0) {   // <= keeps equal items in their old order (stable)
                $result[] = $left[$leftIndex];
                $leftIndex++;
            } else {
                $result[] = $right[$rightIndex];
                $rightIndex++;
            }
        }
        while ($leftIndex < count($left)) {     // one half still has items left over
            $result[] = $left[$leftIndex];
            $leftIndex++;
        }
        while ($rightIndex < count($right)) {
            $result[] = $right[$rightIndex];
            $rightIndex++;
        }
        return $result;
    }
}
