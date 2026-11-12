<?php
namespace App\Payments;

use App\Interfaces\PaymentMethod;

class PaymentFactory {
    // Interface-based polymorphism: the caller only knows it gets a PaymentMethod
    public static function create($name): PaymentMethod {
        if ($name === 'Online Payment') {
            return new OnlinePayment();
        }
        return new CashPayment();
    }
}
