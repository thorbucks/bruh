<?php
/**
 * Product Retrieval API
 *
 * GET /api/products.php         - List all products
 * GET /api/products.php?id=123  - Get a single product by ID
 *
 * Requires JWT authentication.
 *
 * @return JSON array of products or single product object
 * @error 401 Unauthorized, 404 Not Found, 500 Server Error
 */
header('Content-Type: application/json');
require_once '../include/db.php';
require_once '../include/jwt_utils.php';

// JWT authentication
$user = authenticateJWT();
if (!$user) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

$id = isset($_GET['id']) ? intval($_GET['id']) : null;

try {
    if ($id) {
        $stmt = $pdo->prepare('SELECT p.id, p.name, p.brand, c.name AS category, p.price, p.rating, p.reviews, p.icon FROM products p JOIN categories c ON p.categories_id = c.id WHERE p.id = ?');
        $stmt->execute([$id]);
        $product = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$product) {
            http_response_code(404);
            echo json_encode(['success' => false, 'error' => 'Product not found']);
            exit;
        }
        // Fetch sizes
        $sizeStmt = $pdo->prepare('SELECT size FROM product_sizes WHERE product_id = ?');
        $sizeStmt->execute([$id]);
        $product['sizes'] = array_map('intval', array_column($sizeStmt->fetchAll(PDO::FETCH_ASSOC), 'size'));
        echo json_encode($product);
    } else {
        $stmt = $pdo->query('SELECT p.id, p.name, p.brand, c.name AS category, p.price, p.rating, p.reviews, p.icon FROM products p JOIN categories c ON p.categories_id = c.id');
        $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
        // Fetch all sizes
        $sizeStmt = $pdo->query('SELECT product_id, size FROM product_sizes');
        $sizesRaw = $sizeStmt->fetchAll(PDO::FETCH_ASSOC);
        $productSizes = [];
        foreach ($sizesRaw as $row) {
            $productSizes[$row['product_id']][] = (int)$row['size'];
        }
        foreach ($products as &$product) {
            $product['sizes'] = $productSizes[$product['id']] ?? [];
        }
        echo json_encode($products);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Server error']);
}
