<?php
namespace App\Structures;

// Min-heap (DSA Chapter 8): a complete binary tree stored in a list where every parent
// is smaller than its children, so the SMALLEST priority is always at position 0.
// Used as a priority queue: the item with the smallest priority number is served first.
//   parent of i : (i - 1) / 2        left child : 2i + 1        right child : 2i + 2
//   insert O(log n)    extractMin O(log n)    peek O(1)
class MinHeap {
    private $heap = [];   // each entry is [priority, item]

    public function insert($priority, $item) {
        $this->heap[] = [$priority, $item];        // 1. add at the end
        $this->siftUp(count($this->heap) - 1);     // 2. swap upward until the rule holds
    }

    // Remove and return the entry with the smallest priority, or null when empty
    public function extractMin() {
        if (empty($this->heap)) {
            return null;
        }
        $min = $this->heap[0];
        $last = array_pop($this->heap);            // 1. take the last entry
        if (!empty($this->heap)) {
            $this->heap[0] = $last;                // 2. put it at the top
            $this->siftDown(0);                    // 3. swap downward until the rule holds
        }
        return $min;
    }

    public function peek() {
        return empty($this->heap) ? null : $this->heap[0];
    }

    public function size() {
        return count($this->heap);
    }

    public function isEmpty() {
        return empty($this->heap);
    }

    private function siftUp($index) {
        while ($index > 0) {
            $parent = intdiv($index - 1, 2);
            if ($this->heap[$index][0] >= $this->heap[$parent][0]) {
                break;                             // the rule already holds
            }
            $this->swap($index, $parent);
            $index = $parent;
        }
    }

    private function siftDown($index) {
        $size = count($this->heap);
        while (true) {
            $left = 2 * $index + 1;
            $right = 2 * $index + 2;
            $smallest = $index;
            if ($left < $size && $this->heap[$left][0] < $this->heap[$smallest][0]) {
                $smallest = $left;
            }
            if ($right < $size && $this->heap[$right][0] < $this->heap[$smallest][0]) {
                $smallest = $right;
            }
            if ($smallest === $index) {
                break;
            }
            $this->swap($index, $smallest);
            $index = $smallest;
        }
    }

    private function swap($firstIndex, $secondIndex) {
        $temp = $this->heap[$firstIndex];
        $this->heap[$firstIndex] = $this->heap[$secondIndex];
        $this->heap[$secondIndex] = $temp;
    }
}
