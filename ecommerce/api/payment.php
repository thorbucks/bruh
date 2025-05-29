<?php
/**
 * Payment Processing API (Mock)
 *
 * POST /api/payment.php
 *
 * Requires JWT authentication.
 *
 * Request: { order_id, payment_method, payment_details }
 * Response: { success, payment_status, transaction_id }
 *
 * @error 401 Unauthorized, 400 Bad Request, 404 Not Found, 500 Server Error
 */
header('Content-Type: application/json');
require_once '../include/db.php';
require_once '../include/jwt_utils.php';

$user = authenticateJWT();
if (!$user) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}
$userId = $user['id'];

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);
$orderId = intval($data['order_id'] ?? 0);
$paymentMethod = $data['payment_method'] ?? '';
$paymentDetails = $data['payment_details'] ?? [];

if (!$orderId || !$paymentMethod || empty($paymentDetails)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Missing required fields']);
    exit;
}

// Check if order exists and belongs to user
$stmt = $pdo->prepare('SELECT * FROM orders WHERE id = ? AND user_id = ?');
$stmt->execute([$orderId, $userId]);
$order = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$order) {
    http_response_code(404);
    echo json_encode(['success' => false, 'error' => 'Order not found']);
    exit;
}

// Mock payment processing
$transactionId = 'txn_' . uniqid();
$paymentStatus = 'completed';

// Optionally, update order status to 'paid' (if desired)
$stmt = $pdo->prepare('UPDATE orders SET order_status = ? WHERE id = ?');
$stmt->execute(['paid', $orderId]);

// Return mock payment response
http_response_code(200);
echo json_encode([
    'success' => true,
    'payment_status' => $paymentStatus,
    'transaction_id' => $transactionId
]); 