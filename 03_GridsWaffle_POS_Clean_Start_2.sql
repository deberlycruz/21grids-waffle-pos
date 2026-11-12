-- =====================================================================
-- 21 Grids Waffle Co.  |  CLEAN START (POS)
-- Removes the pretend sample orders (and their void records).
-- KEEPS: Staff, Category, Discount and Product.
-- Run this ONCE, before you enter your real test sales.
--
-- Rule for deleting: delete the CHILD rows first, then the PARENT rows
-- (the opposite order of creating the tables).
-- =====================================================================
USE GridsWaffleDB;

-- 1. children first: the void records and the items of every order
DELETE FROM OrderVoid;
DELETE FROM OrderItem;

-- 2. the orders themselves (parent)
DELETE FROM CustomerOrder;

-- 3. the next real order and void start at receipt 00001 again
ALTER TABLE CustomerOrder AUTO_INCREMENT = 1;
ALTER TABLE OrderVoid AUTO_INCREMENT = 1;

-- 4. check: 0 orders left, the menu is still there
SELECT COUNT(*) AS OrdersLeft FROM CustomerOrder;
SELECT P.ProductName, C.CategoryName, P.Price
FROM Product AS P
INNER JOIN Category AS C ON C.CategoryID = P.CategoryID
ORDER BY C.CategoryName, P.ProductName;
