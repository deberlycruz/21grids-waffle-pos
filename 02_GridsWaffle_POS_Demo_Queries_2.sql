-- =====================================================================
-- 21 Grids Waffle Co.  |  DEMONSTRATION OF SQL OPERATIONS AND QUERIES (POS)
-- Run AFTER 01_GridsWaffle_POS_Schema_and_Data.sql.
-- Run ONE statement at a time and take a screenshot of each result.
-- The SQL used here follows the IMFD lessons: DDL, DML, aggregate
-- functions, GROUP BY / HAVING, JOINs and subqueries.
-- =====================================================================
USE GridsWaffleDB;


-- =====================================================================
-- PART A: DDL  (CREATE, ALTER, DROP)
-- =====================================================================

-- A1. CREATE TABLE: a small practice table
CREATE TABLE Promotion (
    PromoID     INT PRIMARY KEY,
    PromoName   VARCHAR(80) NOT NULL,
    Discount    DECIMAL(5,2) NOT NULL CHECK (Discount >= 0)
);

-- A2. ALTER TABLE: add a column to the practice table
ALTER TABLE Promotion ADD PromoDescription VARCHAR(150);

-- A3. ALTER TABLE: add a column to a real table
ALTER TABLE Staff ADD ContactNumber VARCHAR(20);

-- A4. DROP TABLE: remove the practice table (safe: it holds no real data)
DROP TABLE Promotion;

-- A5. Show the structure of the tables
SHOW TABLES;
DESCRIBE Product;
DESCRIBE CustomerOrder;
DESCRIBE OrderItem;
DESCRIBE OrderVoid;
DESCRIBE Discount;


-- =====================================================================
-- PART B: DML  (INSERT, UPDATE, DELETE) and constraints
-- =====================================================================

-- B1. INSERT a new product (category 1 = Waffle)
INSERT INTO Product (ProductName, CategoryID, Price)
VALUES ('Blueberry Cheesecake Waffle', 1, 120.00);

-- B2. UPDATE a price (old sales keep their own UnitPrice)
UPDATE Product
SET Price = 70.00
WHERE ProductName = 'Plain Waffle';

-- B3. UPDATE: hide an item from the cashier menu
UPDATE Product
SET IsAvailable = FALSE
WHERE ProductName = 'Blueberry Cheesecake Waffle';

-- B4. UPDATE: give a cashier a contact number
UPDATE Staff
SET ContactNumber = '0917-555-0101'
WHERE Username = 'lois';

-- B5. DELETE a product that was never sold (allowed)
DELETE FROM Product
WHERE ProductName = 'Blueberry Cheesecake Waffle';

-- B6. DELETE that is BLOCKED by a foreign key (Product 1 was already sold).
--     This is EXPECTED to fail. Screenshot the error.
DELETE FROM Product
WHERE ProductID = 1;

-- B7. CHECK constraint: an online payment without a reference number is rejected.
--     EXPECTED to fail. Screenshot the error.
INSERT INTO CustomerOrder (CustomerName, PaymentMethod, CashierID)
VALUES ('Test Customer', 'Online Payment', 3);

-- B8. UNIQUE constraint: a reference number cannot be used twice.
--     EXPECTED to fail. Screenshot the error.
INSERT INTO CustomerOrder (CustomerName, PaymentMethod, ReferenceNumber, CashierID)
VALUES ('Test Customer', 'Online Payment', 'BDO7741920384', 3);

-- B9. UNIQUE constraint on OrderVoid: an order can be voided only once (order 8 is a voided order).
--     EXPECTED to fail. Screenshot the error.
INSERT INTO OrderVoid (OrderID, VoidedBy, ApprovedBy, VoidReason)
VALUES (8, 3, 2, 'Second void attempt');

-- B10. CHECK constraint: a discount needs the ID / card number.
--      EXPECTED to fail. Screenshot the error.
INSERT INTO CustomerOrder (CustomerName, SubtotalAmount, DiscountID, DiscountAmount, TotalAmount, PaymentMethod, AmountReceived, CashierID)
VALUES ('Test Customer', 200.00, 2, 10.00, 190.00, 'Cash', 200.00, 3);

-- B11. CHECK constraint: the total must equal the subtotal minus the discount.
--      EXPECTED to fail. Screenshot the error.
INSERT INTO CustomerOrder (CustomerName, SubtotalAmount, DiscountID, DiscountIDNumber, DiscountAmount, TotalAmount, PaymentMethod, AmountReceived, CashierID)
VALUES ('Test Customer', 200.00, 2, 'SC-TEST-1', 10.00, 150.00, 'Cash', 200.00, 3);

-- B11b. CHECK constraint: a Cash order must have an amount received that covers the total.
--       EXPECTED to fail. Screenshot the error.
INSERT INTO CustomerOrder (CustomerName, SubtotalAmount, TotalAmount, PaymentMethod, AmountReceived, CashierID)
VALUES ('Test Customer', 200.00, 200.00, 'Cash', 150.00, 3);

-- B12. UPDATE: change a discount rate (old orders keep the DiscountAmount they were given)
UPDATE Discount
SET DiscountRate = 5.00
WHERE DiscountName = 'Senior Citizen / PWD';


-- =====================================================================
-- PART C: ONE SALE (order + items + total)
-- The steps must succeed together, so they run inside a transaction.
-- (The PHP checkout also uses a transaction.)
-- =====================================================================
START TRANSACTION;

INSERT INTO CustomerOrder (CustomerName, OrderType, PaymentMethod, AmountReceived, CashierID)
VALUES ('Demo Customer', 'Dine In', 'Cash', 500.00, 3);   -- the real amount received is set below, after the total is known

INSERT INTO OrderItem (OrderID, ProductID, Quantity, UnitPrice)
VALUES (LAST_INSERT_ID(), 6, 2, 99.00);

COMMIT;

-- Update the subtotal and total of the newest order (no discount)
UPDATE CustomerOrder
SET SubtotalAmount = (SELECT SUM(Quantity * UnitPrice)
                      FROM OrderItem
                      WHERE OrderItem.OrderID = CustomerOrder.OrderID),
    TotalAmount    = (SELECT SUM(Quantity * UnitPrice)
                      FROM OrderItem
                      WHERE OrderItem.OrderID = CustomerOrder.OrderID)
WHERE OrderID = (SELECT MAX(OrderID) FROM OrderItem);

-- the customer handed over 200.00 for this 198.00 order
UPDATE CustomerOrder
SET AmountReceived = 200.00
WHERE OrderID = (SELECT MAX(OrderID) FROM OrderItem);

SELECT * FROM CustomerOrder ORDER BY OrderID DESC LIMIT 1;

-- A sale with a Senior Citizen discount (5% of 200.00 = 10.00; the customer pays 190.00)
START TRANSACTION;

INSERT INTO CustomerOrder (CustomerName, OrderType, SubtotalAmount, DiscountID, DiscountIDNumber,
                           DiscountAmount, TotalAmount, PaymentMethod, AmountReceived, CashierID)
VALUES ('Lola Remedios', 'Dine In', 200.00, 2, 'SC-2024-1111', 10.00, 190.00, 'Cash', 200.00, 3);

INSERT INTO OrderItem (OrderID, ProductID, Quantity, UnitPrice)
VALUES (LAST_INSERT_ID(), 2, 2, 100.00);

COMMIT;


-- =====================================================================
-- PART D: SELECT QUERIES (reports for the system)
-- =====================================================================

-- D1. The whole menu with its category, cheapest first (JOIN)
SELECT P.ProductID, P.ProductName, C.CategoryName, P.Price
FROM Product AS P
INNER JOIN Category AS C ON C.CategoryID = P.CategoryID
ORDER BY P.Price;

-- D2. WHERE with BETWEEN and LIKE
SELECT ProductName, Price
FROM Product
WHERE Price BETWEEN 80 AND 100
  AND ProductName LIKE '%Waffle%';

-- D3. Aggregate functions on the sales
SELECT COUNT(*)            AS TotalOrders,
       SUM(SubtotalAmount) AS GrossSales,
       SUM(DiscountAmount) AS TotalDiscounts,
       SUM(TotalAmount)    AS NetSales,
       AVG(TotalAmount)    AS AverageOrder,
       MAX(TotalAmount)    AS BiggestOrder
FROM CustomerOrder
WHERE Status = 'Completed';

-- D4. GROUP BY: sales per day
SELECT DATE(OrderDate)  AS SaleDate,
       COUNT(*)         AS TotalOrders,
       SUM(TotalAmount) AS NetSales
FROM CustomerOrder
WHERE Status = 'Completed'
GROUP BY DATE(OrderDate)
ORDER BY SaleDate DESC;

-- D5. GROUP BY: sales per payment method
SELECT PaymentMethod, COUNT(*) AS TotalOrders, SUM(TotalAmount) AS Total
FROM CustomerOrder
WHERE Status = 'Completed'
GROUP BY PaymentMethod;

-- D6. GROUP BY: Dine In vs Take Out
SELECT OrderType, COUNT(*) AS TotalOrders, SUM(TotalAmount) AS Total
FROM CustomerOrder
WHERE Status = 'Completed'
GROUP BY OrderType;

-- D7. GROUP BY with HAVING: days that sold more than 400 pesos
SELECT DATE(OrderDate) AS SaleDate, SUM(TotalAmount) AS NetSales
FROM CustomerOrder
WHERE Status = 'Completed'
GROUP BY DATE(OrderDate)
HAVING SUM(TotalAmount) > 400
ORDER BY NetSales DESC;

-- D8. INNER JOIN (4 tables): the details of every receipt
SELECT CO.OrderID, CO.OrderDate, CO.CustomerName, CO.OrderType,
       S.FullName AS Cashier,
       P.ProductName, OI.Quantity, OI.UnitPrice,
       OI.Quantity * OI.UnitPrice AS LineTotal
FROM CustomerOrder AS CO
INNER JOIN OrderItem AS OI ON OI.OrderID = CO.OrderID
INNER JOIN Product   AS P  ON P.ProductID = OI.ProductID
INNER JOIN Staff     AS S  ON S.StaffID = CO.CashierID
ORDER BY CO.OrderID, P.ProductName;

-- D9. Best-selling products (JOIN + GROUP BY + ORDER BY + LIMIT)
SELECT P.ProductName, SUM(OI.Quantity) AS UnitsSold,
       SUM(OI.Quantity * OI.UnitPrice) AS Revenue
FROM OrderItem AS OI
INNER JOIN CustomerOrder AS CO ON CO.OrderID = OI.OrderID
INNER JOIN Product AS P ON P.ProductID = OI.ProductID
WHERE CO.Status = 'Completed'
GROUP BY P.ProductID, P.ProductName
ORDER BY UnitsSold DESC
LIMIT 5;

-- D10. Sales per category
SELECT C.CategoryName, SUM(OI.Quantity) AS UnitsSold,
       SUM(OI.Quantity * OI.UnitPrice) AS Revenue
FROM OrderItem AS OI
INNER JOIN CustomerOrder AS CO ON CO.OrderID = OI.OrderID
INNER JOIN Product  AS P ON P.ProductID = OI.ProductID
INNER JOIN Category AS C ON C.CategoryID = P.CategoryID
WHERE CO.Status = 'Completed'
GROUP BY C.CategoryID, C.CategoryName;

-- D11. Sales of each cashier
SELECT S.FullName, COUNT(CO.OrderID) AS OrdersHandled, SUM(CO.TotalAmount) AS Sales
FROM Staff AS S
INNER JOIN CustomerOrder AS CO ON CO.CashierID = S.StaffID
WHERE CO.Status = 'Completed'
GROUP BY S.StaffID, S.FullName
ORDER BY Sales DESC;

-- D12. Voided orders: who processed it, who approved it, and why
SELECT CO.OrderID, CO.CustomerName, CO.TotalAmount,
       C.FullName AS Cashier, A.FullName AS ApprovedBy,
       V.VoidedAt, V.VoidReason
FROM OrderVoid AS V
INNER JOIN CustomerOrder AS CO ON CO.OrderID = V.OrderID
INNER JOIN Staff AS C ON C.StaffID = V.VoidedBy
INNER JOIN Staff AS A ON A.StaffID = V.ApprovedBy;

-- D13. Subquery: products priced above the average price
SELECT ProductName, Price
FROM Product
WHERE Price > (SELECT AVG(Price) FROM Product);

-- D14. Subquery with IN: products that appear in at least one order
SELECT ProductName
FROM Product
WHERE ProductID IN (SELECT ProductID FROM OrderItem);

-- D15. Correlated subquery: cashiers who have at least one voided order
SELECT FullName
FROM Staff
WHERE EXISTS (SELECT 1
              FROM OrderVoid
              WHERE OrderVoid.VoidedBy = Staff.StaffID);

-- D16. LEFT JOIN: products that were never sold
SELECT P.ProductName
FROM Product AS P
LEFT JOIN OrderItem AS OI ON OI.ProductID = P.ProductID
WHERE OI.OrderID IS NULL;

-- D17. Online payments with their reference numbers
SELECT OrderID, OrderDate, CustomerName, ReferenceNumber, TotalAmount, Status
FROM CustomerOrder
WHERE PaymentMethod = 'Online Payment'
ORDER BY OrderDate;

-- D18. Integrity check: every order subtotal must equal the sum of its items
SELECT CO.OrderID, CO.SubtotalAmount AS StoredSubtotal,
       SUM(OI.Quantity * OI.UnitPrice) AS ItemsTotal
FROM CustomerOrder AS CO
INNER JOIN OrderItem AS OI ON OI.OrderID = CO.OrderID
GROUP BY CO.OrderID, CO.SubtotalAmount
HAVING CO.SubtotalAmount <> SUM(OI.Quantity * OI.UnitPrice);
-- (an empty result means every subtotal is correct)

-- D19. UNION: every person who is either a cashier of an order or an approver of a void
SELECT S.FullName, 'Cashier' AS Duty
FROM Staff AS S
WHERE S.StaffID IN (SELECT CashierID FROM CustomerOrder)
UNION
SELECT S.FullName, 'Approver'
FROM Staff AS S
WHERE S.StaffID IN (SELECT ApprovedBy FROM OrderVoid);

-- D20. Discounts given, per kind of discount (JOIN + GROUP BY)
SELECT D.DiscountName, COUNT(*) AS TotalOrders,
       SUM(CO.SubtotalAmount) AS GrossSales,
       SUM(CO.DiscountAmount) AS TotalDiscount,
       SUM(CO.TotalAmount)    AS NetSales
FROM CustomerOrder AS CO
INNER JOIN Discount AS D ON D.DiscountID = CO.DiscountID
WHERE CO.Status = 'Completed'
GROUP BY D.DiscountID, D.DiscountName
ORDER BY D.DiscountID;

-- D21. Every order that received a discount, with the ID or card number
SELECT CO.OrderID, CO.CustomerName, D.DiscountName, D.DiscountRate,
       CO.DiscountIDNumber, CO.SubtotalAmount, CO.DiscountAmount, CO.TotalAmount
FROM CustomerOrder AS CO
INNER JOIN Discount AS D ON D.DiscountID = CO.DiscountID
WHERE CO.DiscountAmount > 0
ORDER BY CO.OrderDate;

-- D22. The receipt number as shown on screens and receipts (5 digits, LPAD)
SELECT LPAD(OrderID, 5, '0') AS ReceiptNo, CustomerName, TotalAmount, Status
FROM CustomerOrder
ORDER BY OrderID;

-- D23. Change given on each Cash order (the change is worked out, not stored: AmountReceived - TotalAmount)
SELECT LPAD(OrderID, 5, '0') AS ReceiptNo, CustomerName, TotalAmount, AmountReceived,
       AmountReceived - TotalAmount AS ChangeGiven
FROM CustomerOrder
WHERE PaymentMethod = 'Cash' AND Status = 'Completed'
ORDER BY OrderID;
