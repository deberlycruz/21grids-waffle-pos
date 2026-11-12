<?php
namespace App\Interfaces;

// Interface: a contract. Every payment class must provide these methods.
interface PaymentMethod {
    public function getName();
    public function requiresReference();
    public function validate($reference);
    public function describe(float $amount);
    public function requiresAmountReceived();
    public function calculateChange(float $total, $amountReceived);
}
