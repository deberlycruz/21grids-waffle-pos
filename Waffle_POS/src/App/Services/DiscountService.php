<?php
namespace App\Services;

use App\Core\Database;
use App\Models\Discount;

// Reads the kinds of discount from the Discount table
class DiscountService {
    private $pdo;

    public function __construct() {
        $this->pdo = Database::connect();
    }

    // Every discount as a Discount object, 'No Discount' first
    public function getAll() {
        $rows = $this->pdo->query("SELECT DiscountID, DiscountName, DiscountRate FROM Discount ORDER BY DiscountID")->fetchAll();
        $discounts = [];
        foreach ($rows as $row) {
            $discounts[] = new Discount($row['DiscountID'], $row['DiscountName'], $row['DiscountRate']);
        }
        return $discounts;
    }

    // One discount by id, or null when it does not exist
    public function find($discountId) {
        $stmt = $this->pdo->prepare("SELECT DiscountID, DiscountName, DiscountRate FROM Discount WHERE DiscountID = ?");
        $stmt->execute([(int)$discountId]);
        $row = $stmt->fetch();
        return $row ? new Discount($row['DiscountID'], $row['DiscountName'], $row['DiscountRate']) : null;
    }
}
