<?php
namespace App\Algorithms;

// Searching algorithms (DSA Chapter 5)
class Searching {
    // LINEAR SEARCH (find every match)
    //   Check the items one by one from the start to the end.
    //   Works on ANY list, sorted or not.   Time: O(n).
    // $matches($item) returns true for an item we want.
    // $steps counts how many items were checked.
    public static function linearSearchAll(array $items, callable $matches, &$steps = 0) {
        $found = [];
        foreach ($items as $item) {
            $steps++;
            if ($matches($item)) {
                $found[] = $item;
            }
        }
        return $found;
    }

    // BINARY SEARCH
    //   PRECONDITION: the list must already be sorted (smallest to largest).
    //   Look at the middle item. If it is too small, the target can only be on the
    //   right; if too big, only on the left. Every step throws away half of the list.
    //   Time: O(log n).   Returns the position of the item, or -1 when it is not there.
    // $getKey($item) returns the value the list is sorted by.
    public static function binarySearch(array $items, $target, callable $getKey, &$steps = 0) {
        $low = 0;
        $high = count($items) - 1;
        while ($low <= $high) {
            $middle = intdiv($low + $high, 2);
            $steps++;
            $key = $getKey($items[$middle]);
            if ($key == $target) {
                return $middle;
            }
            if ($key < $target) {
                $low = $middle + 1;     // target is in the right half
            } else {
                $high = $middle - 1;    // target is in the left half
            }
        }
        return -1;
    }
}
