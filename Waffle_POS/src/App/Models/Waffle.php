<?php
namespace App\Models;

// Derived (child) class: a Waffle IS-A MenuItem
class Waffle extends MenuItem {
    // Method overriding
    public function getItemClassificationLabel() {
        return "House Specialized Fresh Baked Waffle Variant";
    }

    public function getCategory() {
        return "Waffle";
    }
}
