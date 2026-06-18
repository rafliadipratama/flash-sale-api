<?php

namespace App\Models;

use App\Database;

class Product
{
    private int $id;
    private string $name;
    private float $price;
    private ?float $flashSalePrice;
    private int $inventory;

    public function __construct(int $id, string $name, float $price, ?float $flashSalePrice, int $inventory)
    {
        $this->id = $id;
        $this->name = $name;
        $this->price = $price;
        $this->flashSalePrice = $flashSalePrice;
        $this->inventory = $inventory;
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getPrice(): float
    {
        return $this->price;
    }

    public function getFlashSalePrice(): ?float
    {
        return $this->flashSalePrice;
    }

    public function getInventory(): int
    {
        return $this->inventory;
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'price' => (float)$this->price,
            'flash_sale_price' => $this->flashSalePrice ? (float)$this->flashSalePrice : null,
            'inventory' => $this->inventory,
        ];
    }

    public static function getAll(): array
    {
        $db = Database::connect();
        $stmt = $db->query('SELECT * FROM products');
        $results = $stmt->fetchAll();

        return array_map(function($row) {
            return new self(
                $row['id'],
                $row['name'],
                $row['price'],
                $row['flash_sale_price'],
                $row['inventory']
            );
        }, $results);
    }

    public static function getById(int $id): ?self
    {
        $db = Database::connect();
        $stmt = $db->prepare('SELECT * FROM products WHERE id = ?');
        $stmt->execute([$id]);
        $row = $stmt->fetch();

        if (!$row) {
            return null;
        }

        return new self(
            $row['id'],
            $row['name'],
            $row['price'],
            $row['flash_sale_price'],
            $row['inventory']
        );
    }

    public static function create(string $name, float $price, ?float $flashSalePrice, int $inventory): self
    {
        $db = Database::connect();
        $stmt = $db->prepare(
            'INSERT INTO products (name, price, flash_sale_price, inventory) VALUES (?, ?, ?, ?)'
        );
        $stmt->execute([$name, $price, $flashSalePrice, $inventory]);

        $id = (int)$db->lastInsertId();
        return new self($id, $name, $price, $flashSalePrice, $inventory);
    }

    public static function updateInventory(int $productId, int $quantity): bool
    {
        $db = Database::connect();
        $stmt = $db->prepare('UPDATE products SET inventory = inventory + ? WHERE id = ?');
        return $stmt->execute([$quantity, $productId]);
    }
}
