<?php
namespace App\Services;

use PDOException;
use App\Core\Database;
use App\Core\DatabaseException;
use App\Models\MenuItem;
use App\Models\Waffle;
use App\Models\Drink;

// Reads and changes the menu (products)
class MenuService {
    private $pdo;

    public function __construct() {
        $this->pdo = Database::connect();
    }

    // Menu for the cashier: only the items that are available
    public function getAvailableMenu() {
        $rows = $this->pdo->query(
            "SELECT P.ProductID, P.ProductName, C.CategoryName, P.Price
             FROM Product AS P
             INNER JOIN Category AS C ON C.CategoryID = P.CategoryID
             WHERE P.IsAvailable = TRUE
             ORDER BY P.ProductName"
        )->fetchAll();

        $menu = [];
        foreach ($rows as $row) {
            $menu[] = MenuItem::fromRow($row);
        }
        return $menu;
    }

    // Every product, with its availability (admin page)
    public function getAllProducts() {
        $rows = $this->pdo->query(
            "SELECT P.ProductID, P.ProductName, C.CategoryName, P.Price, P.IsAvailable
             FROM Product AS P
             INNER JOIN Category AS C ON C.CategoryID = P.CategoryID
             ORDER BY P.ProductName"
        )->fetchAll();

        $products = [];
        foreach ($rows as $row) {
            $products[] = ['item' => MenuItem::fromRow($row), 'available' => (bool)$row['IsAvailable']];
        }
        return $products;
    }

    public function addProduct($name, $categoryName, $price) {
        // creating the object runs the setters, which validate the data first
        $item = ($categoryName === 'Waffle') ? new Waffle(0, $name, $price) : new Drink(0, $name, $price);

        $stmt = $this->pdo->prepare("SELECT CategoryID FROM Category WHERE CategoryName = ?");
        $stmt->execute([$item->getCategory()]);
        $categoryId = $stmt->fetchColumn();
        if ($categoryId === false) {
            throw new DatabaseException("That category does not exist.", "Could Not Add");
        }
        try {
            $this->pdo->prepare("INSERT INTO Product (ProductName, CategoryID, Price) VALUES (?, ?, ?)")
                 ->execute([$item->getName(), $categoryId, $item->getPrice()]);
        } catch (PDOException $exception) {
            throw new DatabaseException("A product with that name already exists.", "Could Not Add");
        }
    }

    public function editProduct($id, $name, $price) {
        $stmt = $this->pdo->prepare(
            "SELECT P.ProductID, P.ProductName, C.CategoryName, P.Price
             FROM Product AS P
             INNER JOIN Category AS C ON C.CategoryID = P.CategoryID
             WHERE P.ProductID = ?"
        );
        $stmt->execute([(int)$id]);
        $row = $stmt->fetch();
        if (!$row) {
            throw new DatabaseException("That product no longer exists.", "Not Found");
        }
        $item = MenuItem::fromRow($row);
        $item->setName($name);      // the setters validate
        $item->setPrice($price);
        try {
            $this->pdo->prepare("UPDATE Product SET ProductName = ?, Price = ? WHERE ProductID = ?")
                 ->execute([$item->getName(), $item->getPrice(), $item->getProductId()]);
        } catch (PDOException $exception) {
            throw new DatabaseException("Another product already uses that name.", "Could Not Save");
        }
    }

    // Show / hide an item on the cashier menu. Returns true when it is now available.
    public function toggleAvailability($id) {
        $this->pdo->prepare("UPDATE Product SET IsAvailable = NOT IsAvailable WHERE ProductID = ?")->execute([(int)$id]);
        $stmt = $this->pdo->prepare("SELECT IsAvailable FROM Product WHERE ProductID = ?");
        $stmt->execute([(int)$id]);
        return (bool)$stmt->fetchColumn();
    }

    // A product that was already sold is protected by its foreign key.
    public function deleteProduct($id) {
        try {
            $this->pdo->prepare("DELETE FROM Product WHERE ProductID = ?")->execute([(int)$id]);
        } catch (PDOException $exception) {
            throw new DatabaseException(
                "This product appears in past sales, so it cannot be deleted without breaking your sales history. Use Hide instead.",
                "Cannot Delete"
            );
        }
    }
}
