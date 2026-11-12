<?php
namespace App\Models;

use App\Core\DatabaseException;

// A kind of discount: its name and its rate in percent (0 = no discount)
class Discount {
    // Encapsulation: private properties, read through getters
    private $discountId;
    private $name;
    private $rate;

    public function __construct($discountId, $name, $rate) {
        $this->discountId = (int)$discountId;
        $this->name = $name;
        $this->setRate($rate);
    }

    public function getDiscountId() {
        return $this->discountId;
    }

    public function getName() {
        return $this->name;
    }

    public function getRate() {
        return $this->rate;
    }

    // The setter checks the data before it is stored
    public function setRate($rate) {
        if (!is_numeric($rate) || $rate < 0 || $rate > 100) {
            throw new DatabaseException("A discount rate must be between 0 and 100 percent.", "Constraint Triggered");
        }
        $this->rate = (float)$rate;
    }

    // True when this discount really takes money off (the rate is above 0)
    public function isDiscount() {
        return $this->rate > 0;
    }

    // The money taken off a subtotal, rounded to centavos
    public function calculateAmount($subtotal) {
        return round($subtotal * $this->rate / 100, 2);
    }

    // Magic method: how the discount reads as text, e.g. "Senior Citizen / PWD (5%)"
    public function __toString() {
        return $this->name . ($this->isDiscount() ? ' (' . rtrim(rtrim(number_format($this->rate, 2, '.', ''), '0'), '.') . '%)' : '');
    }
}
