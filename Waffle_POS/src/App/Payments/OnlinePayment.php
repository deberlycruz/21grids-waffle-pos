<?php
namespace App\Payments;

use App\Core\OrderException;
use App\Interfaces\PaymentMethod;

class OnlinePayment implements PaymentMethod {
    public function getName() {
        return "Online Payment";
    }

    public function requiresReference() {
        return true;
    }

    // An online payment is only valid with a proper reference number
    public function validate($reference) {
        if (!preg_match('/^[A-Za-z0-9\-]{4,50}$/', $reference)) {
            throw new OrderException("Online Payment needs the transfer's reference number (4 to 50 letters, numbers or dashes).", "Reference Number Required");
        }
    }

    // An online transfer is exact, so there is no cash to count and no change
    public function requiresAmountReceived() {
        return false;
    }

    public function calculateChange(float $total, $amountReceived) {
        return null;
    }

    public function describe(float $amount) {
        return "Paid " . number_format($amount, 2) . " by online transfer.";
    }
}
