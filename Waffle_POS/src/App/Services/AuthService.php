<?php
namespace App\Services;

use App\Core\Database;

// Handles logging in and checking a supervisor's approval
class AuthService {
    private $pdo;

    public function __construct() {
        $this->pdo = Database::connect();
    }

    // Returns the staff row when the username, password and role are right, otherwise null
    public function login($username, $password, $role) {
        $stmt = $this->pdo->prepare(
            "SELECT StaffID, PasswordHash, FullName, Role
             FROM Staff
             WHERE Username = ? AND Role = ? AND IsActive = TRUE"
        );
        $stmt->execute([trim($username), $role]);
        $staff = $stmt->fetch();

        if ($staff && password_verify($password, $staff['PasswordHash'])) {
            return $staff;
        }
        return null;
    }

    // Only an active Owner or Admin can approve a void
    public function verifyApprover($username, $password) {
        if (trim($username) === '' || $password === '') {
            return null;
        }
        $stmt = $this->pdo->prepare(
            "SELECT StaffID, PasswordHash, FullName
             FROM Staff
             WHERE Username = ? AND Role IN ('Owner', 'Admin') AND IsActive = TRUE"
        );
        $stmt->execute([trim($username)]);
        $staff = $stmt->fetch();

        if ($staff && password_verify($password, $staff['PasswordHash'])) {
            return $staff;
        }
        usleep(500000);   // a short pause slows down password guessing
        return null;
    }

    public function isActiveCashier($staffId) {
        $stmt = $this->pdo->prepare("SELECT StaffID FROM Staff WHERE StaffID = ? AND Role = 'Cashier' AND IsActive = TRUE");
        $stmt->execute([(int)$staffId]);
        return $stmt->fetchColumn() !== false;
    }
}
