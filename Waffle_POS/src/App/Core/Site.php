<?php
namespace App\Core;

// The name of the business, shown in headers, titles and receipts, and the receipt number format.
class Site {
    private static $businessName = '21 Grids Waffle Co.';

    public static function getBusinessName() {
        return self::$businessName;
    }

    // The receipt number is stored as a plain number (1, 2, 3 ...) and shown with 5 digits: 00001, 00002 ...
    public static function receiptNo($orderId) {
        return str_pad((string)(int)$orderId, 5, '0', STR_PAD_LEFT);
    }
}
