<?php
// lookup_receipt.php  (used by the Void window: shows the receipt while the cashier types its number)
session_start();
header('Content-Type: application/json; charset=utf-8');
if (($_SESSION['role'] ?? '') !== 'Cashier') { http_response_code(403); echo json_encode(['error' => 'Not allowed']); exit(); }
require_once 'db.php';

use App\Services\OrderService;

$orders = new OrderService();
$result = $orders->findReceipt((int)($_GET['id'] ?? 0));   // BINARY SEARCH

echo json_encode([
    'found'    => $result['found'],
    'receipt'  => \App\Core\Site::receiptNo((int)($_GET['id'] ?? 0)),
    'customer' => $result['order']['CustomerName'] ?? '',
    'type'     => $result['order']['OrderType'] ?? '',
    'payment'  => $result['order']['PaymentMethod'] ?? '',
    'total'    => isset($result['order']) ? number_format($result['order']['TotalAmount'], 2) : '',
    'status'   => $result['order']['Status'] ?? '',
    'steps'    => $result['steps'],
    'records'  => $result['total']
]);
