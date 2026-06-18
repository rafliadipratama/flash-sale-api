<?php

namespace App\Models;

use App\Database;
use PDO;

class Order
{
    private int $id;
    private string $customerName;
    private float $totalAmount;
    private string $status;
    private string $createdAt;
    private array $items = [];

    public function __construct(int $id, string $customerName, float $totalAmount, string $status, string $createdAt)
    {
        $this->id = $id;
        $this->customerName = $customerName;
        $this->totalAmount = $totalAmount;
        $this->status = $status;
        $this->createdAt = $createdAt;
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getCustomerName(): string
    {
        return $this->customerName;
    }

    public function getTotalAmount(): float
    {
        return $this->totalAmount;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function getCreatedAt(): string
    {
        return $this->createdAt;
    }

    public function getItems(): array
    {
        return $this->items;
    }

    public function setItems(array $items): void
    {
        $this->items = $items;
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'customer_name' => $this->customerName,
            'total_amount' => (float)$this->totalAmount,
            'status' => $this->status,
            'created_at' => $this->createdAt,
            'items' => $this->items,
        ];
    }

    public static function getById(int $id): ?self
    {
        $db = Database::connect();
        $stmt = $db->prepare('SELECT * FROM orders WHERE id = ?');
        $stmt->execute([$id]);
        $row = $stmt->fetch();

        if (!$row) {
            return null;
        }

        $order = new self($row['id'], $row['customer_name'], $row['total_amount'], $row['status'], $row['created_at']);
        $order->setItems(self::getOrderItems($id));
        return $order;
    }

    private static function getOrderItems(int $orderId): array
    {
        $db = Database::connect();
        $stmt = $db->prepare(
            'SELECT oi.*, p.name FROM order_items oi JOIN products p ON oi.product_id = p.id WHERE oi.order_id = ?'
        );
        $stmt->execute([$orderId]);
        return $stmt->fetchAll();
    }

    /**
     * Create order with items. Uses transaction and row-level locking to handle race conditions.
     * The key is using IMMEDIATE transaction mode to lock early and prevent inventory overallocation.
     */
    public static function createWithItems(string $customerName, array $items): array
    {
        $db = Database::connect();

        try {
            // Start transaction with IMMEDIATE mode to acquire locks immediately
            $db->exec('BEGIN IMMEDIATE');

            $totalAmount = 0;
            $orderId = null;

            // Lock and validate inventory for all products first
            foreach ($items as $item) {
                $productId = $item['product_id'];
                $quantity = $item['quantity'];

                // Lock the product row and check inventory
                $stmt = $db->prepare('SELECT inventory FROM products WHERE id = ? FOR UPDATE');
                $stmt->execute([$productId]);
                $row = $stmt->fetch();

                if (!$row) {
                    throw new \Exception("Product {$productId} not found", 404);
                }

                $currentInventory = (int)$row['inventory'];

                if ($currentInventory < $quantity) {
                    throw new \Exception("Insufficient inventory for product {$productId}. Available: {$currentInventory}, Requested: {$quantity}", 409);
                }
            }

            // All inventory checks passed, now create the order
            $stmt = $db->prepare('INSERT INTO orders (customer_name, total_amount, status) VALUES (?, ?, ?)');
            $stmt->execute([$customerName, 0, 'pending']);
            $orderId = (int)$db->lastInsertId();

            // Create order items and update inventory
            foreach ($items as $item) {
                $productId = $item['product_id'];
                $quantity = $item['quantity'];

                // Get product for pricing
                $product = Product::getById($productId);
                if (!$product) {
                    throw new \Exception("Product {$productId} not found", 404);
                }

                // Use flash sale price if available, otherwise regular price
                $unitPrice = $product->getFlashSalePrice() ?? $product->getPrice();
                $itemTotal = $unitPrice * $quantity;
                $totalAmount += $itemTotal;

                // Insert order item
                $stmt = $db->prepare(
                    'INSERT INTO order_items (order_id, product_id, quantity, unit_price) VALUES (?, ?, ?, ?)'
                );
                $stmt->execute([$orderId, $productId, $quantity, $unitPrice]);

                // Update inventory (decrease)
                $stmt = $db->prepare(
                    'UPDATE products SET inventory = inventory - ? WHERE id = ?'
                );
                $stmt->execute([$quantity, $productId]);

                // Log inventory change
                $beforeInventory = $product->getInventory();
                $afterInventory = $beforeInventory - $quantity;
                $stmt = $db->prepare(
                    'INSERT INTO inventory_logs (product_id, quantity_before, quantity_after, order_id) VALUES (?, ?, ?, ?)'
                );
                $stmt->execute([$productId, $beforeInventory, $afterInventory, $orderId]);
            }

            // Update order total amount
            $stmt = $db->prepare('UPDATE orders SET total_amount = ? WHERE id = ?');
            $stmt->execute([$totalAmount, $orderId]);

            $db->exec('COMMIT');

            return [
                'success' => true,
                'order_id' => $orderId,
                'total_amount' => $totalAmount,
            ];
        } catch (\Exception $e) {
            $db->exec('ROLLBACK');
            throw $e;
        }
    }
}
