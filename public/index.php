<?php

require __DIR__ . '/../vendor/autoload.php';

use App\Database;
use App\Response;
use App\Validator;
use App\Models\Product;
use App\Models\Order;

// Initialize database on first run
if (!file_exists(__DIR__ . '/../database.db')) {
    Database::initialize();
}

// Get request method and path
$method = $_SERVER['REQUEST_METHOD'];
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$path = str_replace('/public', '', $path);

// Simple router
$routeMatches = [];

// GET /products
if ($method === 'GET' && preg_match('#^/products$#', $path, $routeMatches)) {
    try {
        $products = Product::getAll();
        $data = array_map(fn($p) => $p->toArray(), $products);
        Response::success($data);
    } catch (Exception $e) {
        Response::error($e->getMessage(), 500);
    }
}

// GET /products/{id}
elseif ($method === 'GET' && preg_match('#^/products/(\d+)$#', $path, $routeMatches)) {
    try {
        $id = (int)$routeMatches[1];
        $product = Product::getById($id);

        if (!$product) {
            Response::error('Product not found', 404);
        }

        Response::success($product->toArray());
    } catch (Exception $e) {
        Response::error($e->getMessage(), 500);
    }
}

// POST /products
elseif ($method === 'POST' && preg_match('#^/products$#', $path)) {
    try {
        $input = json_decode(file_get_contents('php://input'), true);

        $validator = new Validator();
        if (!$validator->validate($input, [
            'name' => ['required', 'string', 'min:1', 'max:255'],
            'price' => ['required', 'numeric', 'positive'],
            'flash_sale_price' => ['numeric', 'positive'],
            'inventory' => ['numeric', 'non_negative'],
        ])) {
            Response::error('Validation failed', 400, $validator->getErrors());
        }

        $name = trim($input['name']);
        $price = (float)$input['price'];
        $flashSalePrice = isset($input['flash_sale_price']) ? (float)$input['flash_sale_price'] : null;
        $inventory = isset($input['inventory']) ? (int)$input['inventory'] : 0;

        $product = Product::create($name, $price, $flashSalePrice, $inventory);
        Response::success($product->toArray(), 201);
    } catch (Exception $e) {
        Response::error($e->getMessage(), 500);
    }
}

// POST /orders
elseif ($method === 'POST' && preg_match('#^/orders$#', $path)) {
    try {
        $input = json_decode(file_get_contents('php://input'), true);

        $validator = new Validator();
        if (!$validator->validate($input, [
            'customer_name' => ['required', 'string', 'min:1', 'max:255'],
            'items' => ['required', 'array', 'min_items:1'],
        ])) {
            Response::error('Validation failed', 400, $validator->getErrors());
        }

        $customerName = trim($input['customer_name']);
        $items = $input['items'];

        // Validate each item
        $itemErrors = [];
        foreach ($items as $idx => $item) {
            if (!isset($item['product_id']) || !is_numeric($item['product_id'])) {
                $itemErrors[] = "Item {$idx}: product_id is required and must be numeric";
            }
            if (!isset($item['quantity']) || !is_numeric($item['quantity']) || (int)$item['quantity'] <= 0) {
                $itemErrors[] = "Item {$idx}: quantity is required and must be positive";
            }
        }

        if (!empty($itemErrors)) {
            Response::error('Invalid order items', 400, ['items' => $itemErrors]);
        }

        $result = Order::createWithItems($customerName, $items);
        Response::success($result, 201);
    } catch (Exception $e) {
        $statusCode = (int)($e->getCode() ?: 500);
        Response::error($e->getMessage(), $statusCode);
    }
}

// GET /orders/{id}
elseif ($method === 'GET' && preg_match('#^/orders/(\d+)$#', $path, $routeMatches)) {
    try {
        $id = (int)$routeMatches[1];
        $order = Order::getById($id);

        if (!$order) {
            Response::error('Order not found', 404);
        }

        Response::success($order->toArray());
    } catch (Exception $e) {
        Response::error($e->getMessage(), 500);
    }
}

// 404
else {
    Response::error('Not found', 404);
}
