<?php
/**
 * Cart Management API
 *
 * GET /api/cart.php           - List cart items
 * POST /api/cart.php          - Add item to cart
 * DELETE /api/cart.php        - Remove item from cart
 *
 * Requires JWT authentication.
 *
 * @return JSON array of cart items or success/error message
 * @error 401 Unauthorized, 400 Bad Request, 405 Method Not Allowed
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

switch ($_SERVER['REQUEST_METHOD']) {
    case 'GET':
        $stmt = $pdo->prepare('SELECT c.id, c.product_id, p.name as product_name, p.price, c.quantity, (p.price * c.quantity) as total FROM cart c JOIN products p ON c.product_id = p.id WHERE c.user_id = ?');
        $stmt->execute([$userId]);
        $cart = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode($cart);
        break;
    case 'POST':
        $data = json_decode(file_get_contents('php://input'), true);
        $productId = intval($data['product_id'] ?? 0);
        $quantity = intval($data['quantity'] ?? 1);
        if (!$productId || $quantity < 1) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Invalid product or quantity']);
            exit;
        }
        // Check if already in cart
        $stmt = $pdo->prepare('SELECT id FROM cart WHERE user_id = ? AND product_id = ?');
        $stmt->execute([$userId, $productId]);
        if ($row = $stmt->fetch()) {
            // Update quantity
            $stmt = $pdo->prepare('UPDATE cart SET quantity = quantity + ? WHERE id = ?');
            $stmt->execute([$quantity, $row['id']]);
        } else {
            $stmt = $pdo->prepare('INSERT INTO cart (user_id, product_id, quantity) VALUES (?, ?, ?)');
            $stmt->execute([$userId, $productId, $quantity]);
        }
        echo json_encode(['success' => true, 'message' => 'Product added to cart']);
        break;
    case 'DELETE':
        $data = json_decode(file_get_contents('php://input'), true);
        $cartItemId = intval($data['cart_item_id'] ?? 0);
        if (!$cartItemId) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Invalid cart item']);
            exit;
        }
        $stmt = $pdo->prepare('DELETE FROM cart WHERE id = ? AND user_id = ?');
        $stmt->execute([$cartItemId, $userId]);
        echo json_encode(['success' => true, 'message' => 'Item removed from cart']);
        break;
    default:
        http_response_code(405);
        echo json_encode(['success' => false, 'error' => 'Method not allowed']);
}
