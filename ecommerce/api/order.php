<?php 
/**
 * Order API
 *
 * POST /api/order.php         - Create a new order
 * GET /api/order.php          - List user's orders
 * GET /api/order.php?id=123   - Get a single order by ID
 *
 * Requires JWT authentication.
 *
 * @return JSON order data or success/error message
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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);
    $cart = $data['cart'] ?? [];
    $shipping = $data['shipping'] ?? [];
    $paymentMethod = $data['payment_method'] ?? '';

    // Validate input
    if (empty($cart) || empty($shipping) || !$paymentMethod) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Missing required fields']);
        exit;
    }
    $requiredFields = ['full_name', 'email', 'phone', 'address', 'city', 'zip_code'];
    foreach ($requiredFields as $field) {
        if (empty($shipping[$field])) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => "Missing shipping field: $field"]);
            exit;
        }
    }
    try {
        $pdo->beginTransaction();
        // Calculate total
        $totalAmount = 0;
        foreach ($cart as $item) {
            $stmt = $pdo->prepare('SELECT price FROM products WHERE id = ?');
            $stmt->execute([$item['product_id']]);
            $product = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$product) {
                throw new Exception('Product not found');
            }
            $totalAmount += $product['price'] * $item['quantity'];
        }
        // Insert order
        $stmt = $pdo->prepare('INSERT INTO orders (user_id, full_name, email, phone, address, city, zip_code, payment_method, total_amount, order_status, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, "pending", NOW())');
        $stmt->execute([
            $userId,
            $shipping['full_name'],
            $shipping['email'],
            $shipping['phone'],
            $shipping['address'],
            $shipping['city'],
            $shipping['zip_code'],
            $paymentMethod,
            $totalAmount
        ]);
        $orderId = $pdo->lastInsertId();
        // Insert order items
        foreach ($cart as $item) {
            $stmt = $pdo->prepare('SELECT name, price FROM products WHERE id = ?');
            $stmt->execute([$item['product_id']]);
            $product = $stmt->fetch(PDO::FETCH_ASSOC);
            $stmt = $pdo->prepare('INSERT INTO order_items (order_id, product_name, price, quantity, total) VALUES (?, ?, ?, ?, ?)');
            $stmt->execute([$orderId, $product['name'], $product['price'], $item['quantity'], $product['price'] * $item['quantity']]);
        }
        $pdo->commit();
        echo json_encode(['success' => true, 'order_id' => (int)$orderId, 'message' => 'Order placed successfully']);
    } catch (Exception $e) {
        $pdo->rollBack();
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Order creation failed']);
    }
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $id = isset($_GET['id']) ? intval($_GET['id']) : null;
    try {
        if ($id) {
            $stmt = $pdo->prepare('SELECT * FROM orders WHERE id = ? AND user_id = ?');
            $stmt->execute([$id, $userId]);
            $order = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$order) {
                http_response_code(404);
                echo json_encode(['success' => false, 'error' => 'Order not found']);
                exit;
            }
            $stmt = $pdo->prepare('SELECT product_name, price, quantity, total FROM order_items WHERE order_id = ?');
            $stmt->execute([$id]);
            $order['items'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
            echo json_encode($order);
        } else {
            $stmt = $pdo->prepare('SELECT * FROM orders WHERE user_id = ? ORDER BY created_at DESC');
$stmt->execute([$userId]);
$orders = $stmt->fetchAll(PDO::FETCH_ASSOC);
            echo json_encode($orders);
        }
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Server error']);
    }
    exit;
}

http_response_code(405);
echo json_encode(['success' => false, 'error' => 'Method not allowed']);