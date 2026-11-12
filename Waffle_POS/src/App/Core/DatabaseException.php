<?php
namespace App\Core;

use Exception;

// Thrown when data is invalid or the database cannot complete a request
class DatabaseException extends Exception {
    private $title;

    public function __construct($message, $title = "Database Error") {
        parent::__construct($message);
        $this->title = $title;
    }

    public function getTitle() {
        return $this->title;
    }
}
