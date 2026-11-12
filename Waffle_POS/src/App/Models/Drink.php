<?php
namespace App\Models;

// Derived (child) class: a Drink IS-A MenuItem
class Drink extends MenuItem {
    // Method overriding
    public function getItemClassificationLabel() {
        return "Signature Premium Cold Handcrafted Beverage";
    }

    public function getCategory() {
        return "Drink";
    }
}
