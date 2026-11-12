<?php
namespace App\Structures;

// Hash table with chaining (DSA Chapter 9): every bucket is a list of [key, value] pairs.
class HashRegistry {
    private $buckets;
    private $size;
    private $count = 0;

    public function __construct($size = 11) {
        $this->size = $size;
        $this->buckets = array_fill(0, $size, []);
    }

    // Hash function: add the letter codes, then % size so the answer fits 0 .. size-1
    private function hash($key) {
        $total = 0;
        for ($charPosition = 0; $charPosition < strlen($key); $charPosition++) {
            $total += ord($key[$charPosition]);
        }
        return $total % $this->size;
    }

    // Put: hash to a bucket, update the key if it is already there, else add to the list
    public function put($key, $value) {
        $this->insert($key, $value);
        if ($this->loadFactor() > 0.75) {   // over 0.75 the table is crowded: grow it
            $this->rehash();
        }
    }

    private function insert($key, $value) {
        $index = $this->hash($key);
        foreach ($this->buckets[$index] as $position => $pair) {
            if ($pair[0] === $key) {
                $this->buckets[$index][$position][1] = $value;
                return;
            }
        }
        $this->buckets[$index][] = [$key, $value];
        $this->count++;
    }

    // Get: hash to a bucket and search only that bucket's list
    public function get($key) {
        $index = $this->hash($key);
        foreach ($this->buckets[$index] as $pair) {
            if ($pair[0] === $key) {
                return $pair[1];
            }
        }
        return null;   // not in the table
    }

    // Load factor = items / buckets
    public function loadFactor() {
        return $this->count / $this->size;
    }

    // Rehash: double the buckets, then hash every pair again with the new size
    private function rehash() {
        $old = $this->buckets;
        $this->size = $this->size * 2;
        $this->buckets = array_fill(0, $this->size, []);
        $this->count = 0;
        foreach ($old as $bucket) {
            foreach ($bucket as $pair) {
                $this->insert($pair[0], $pair[1]);
            }
        }
    }

    public function getSize() {
        return $this->size;
    }

    public function getCount() {
        return $this->count;
    }
}
