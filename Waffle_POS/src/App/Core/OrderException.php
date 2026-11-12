<?php
namespace App\Core;

use Exception;

// Thrown when a business rule is broken (empty cart, wrong approval, unknown receipt, ...)
// A custom exception can carry extra data: here, a title for the message box.
class OrderException extends Exception {
    private $title;

    public function __construct($message, $title = "Cannot Complete Request") {
        parent::__construct($message);
        $this->title = $title;
    }

    public function getTitle() {
        return $this->title;
    }
}
