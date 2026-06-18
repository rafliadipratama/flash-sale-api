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
     * Create order with items. Uses database transactions to prevent race conditions.
     *
     * Race condition scenario: During flash sale, multiple customers try to buy
     * the same product simultaneously. Without proper locking, inventory could go negative.
     *
     * Solution: Use BEGIN IMMEDIATE transaction mode which:
     * 1. Acquires exclusive lock immediately (not DEFERRED)
     * 2. Prevents dirty reads/writes from concurrent transactions
     * 3. Allows all inventory checks within transaction to see consistent state
     *
     * Note: SQLite uses table-level locks, not row-level like MySQL/PostgreSQL.
     * This is sufficient for our use case since we validate all items before
     * any updates occur (all-or-nothing semantics).
     */
    public static function createWithItems(string $customerName, array $items): array
    {
        $db = Database::connect();

        try {
            // BEGIN IMMEDIATE: Acquire exclusive lock immediately.
            // Prevents other transactions from modifying products table until we commit.
            $db->exec('BEGIN IMMEDIATE');

            $totalAmount = 0;
            $orderId = null;
            $productData = [];

            // First pass: Validate all items have sufficient inventory
            // Critical: We do ALL validation before ANY updates.
            // This ensures if ANY item fails validation, entire order is rejected atomically.
            // (If we updated inventory on-the-fly, partial updates could leak inventory)
            foreach ($items as $item) {
                $productId = $item['product_id'];
                $quantity = $item['quantity'];

                $stmt = $db->prepare('SELECT id, inventory, price, flash_sale_price FROM products WHERE id = ?');
                $stmt->execute([$productId]);
                $row = $stmt->fetch();

                if (!$row) {
                    throw new \Exception("Product {$productId} not found", 404);
                }

                $currentInventory = (int)$row['inventory'];

                // Inventory check: Fail fast if we can't fulfill entire order
                if ($currentInventory < $quantity) {
                    throw new \Exception("Insufficient inventory for product {$productId}. Available: {$currentInventory}, Requested: {$quantity}", 409);
                }

                // Cache product data for second pass
                $productData[$productId] = [
                    'inventory' => $currentInventory,
                    'price' => $row['price'],
                    'flash_sale_price' => $row['flash_sale_price'],
                ];
            }

            // Second pass: All validations passed, now create the order and update inventory
            // Still within transaction, so if anything fails here, entire operation rolls back
            $stmt = $db->prepare('INSERT INTO orders (customer_name, total_amount, status) VALUES (?, ?, ?)');
            $stmt->execute([$customerName, 0, 'pending']);
            $orderId = (int)$db->lastInsertId();

            // Create order items and decrement inventory
            foreach ($items as $item) {
                $productId = $item['product_id'];
                $quantity = $item['quantity'];
                $data = $productData[$productId];

                // Use flash sale price if available
                $unitPrice = $data['flash_sale_price'] ?? $data['price'];
                $itemTotal = $unitPrice * $quantity;
                $totalAmount += $itemTotal;

                // Insert order item
                $stmt = $db->prepare(
                    'INSERT INTO order_items (order_id, product_id, quantity, unit_price) VALUES (?, ?, ?, ?)'
                );
                $stmt->execute([$orderId, $productId, $quantity, $unitPrice]);

                // Update inventory (atomically decrease)
                $stmt = $db->prepare(
                    'UPDATE products SET inventory = inventory - ? WHERE id = ?'
                );
                $stmt->execute([$quantity, $productId]);

                // Log inventory change
                $beforeInventory = $data['inventory'];
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
