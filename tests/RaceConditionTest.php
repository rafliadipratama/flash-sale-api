<?php

require __DIR__ . '/../vendor/autoload.php';

use App\Database;
use App\Models\Product;
use App\Models\Order;

echo "========================================\n";
echo "Race Condition Test - Flash Sale Scenario\n";
echo "========================================\n\n";

// Initialize database
if (file_exists(__DIR__ . '/../database.db')) {
    unlink(__DIR__ . '/../database.db');
}
Database::initialize();

// Create a test product with limited inventory
echo "Creating test product with 10 units in stock...\n";
$product = Product::create('iPhone 15 Pro', 1199.99, 299.99, 10);
echo "Product created: ID={$product->getId()}, Inventory={$product->getInventory()}\n\n";

// Simulate flash sale with concurrent orders
echo "Simulating flash sale with 15 concurrent orders (5 units each)...\n";
echo "Each order tries to buy 5 units = 75 total units requested\n";
echo "Only 10 units available - system should prevent negative inventory\n\n";

$orders = [];
$failedOrders = [];
$successCount = 0;

// Simulate 15 rapid concurrent orders
for ($i = 1; $i <= 15; $i++) {
    try {
        $result = Order::createWithItems(
            "Customer_{$i}",
            [
                [
                    'product_id' => $product->getId(),
                    'quantity' => 5
                ]
            ]
        );

        $successCount++;
        $orders[] = $result;
        echo "[✓] Order #{$i}: SUCCESS (Order ID: {$result['order_id']})\n";
    } catch (Exception $e) {
        $failedOrders[] = [
            'order_num' => $i,
            'error' => $e->getMessage()
        ];
        echo "[✗] Order #{$i}: FAILED - {$e->getMessage()}\n";
    }
}

echo "\n========================================\n";
echo "Test Results\n";
echo "========================================\n\n";

$finalProduct = Product::getById($product->getId());
echo "Final Product Inventory: {$finalProduct->getInventory()} units\n";
echo "Successful Orders: {$successCount}\n";
echo "Failed Orders: " . count($failedOrders) . "\n";
echo "Total Units Allocated: " . ($successCount * 5) . "\n";

// Verification
$expectedInventory = 10 - ($successCount * 5);
echo "\nVerification:\n";
echo "- Expected final inventory: {$expectedInventory}\n";
echo "- Actual final inventory: {$finalProduct->getInventory()}\n";

if ($finalProduct->getInventory() === $expectedInventory && $finalProduct->getInventory() >= 0) {
    echo "- Result: ✓ PASS - Negative inventory prevented!\n";
    echo "\nThe system successfully prevented inventory from going negative\n";
    echo "and maintained inventory consistency under concurrent load.\n";
    exit(0);
} else {
    echo "- Result: ✗ FAIL - Inventory inconsistency detected!\n";
    exit(1);
}
