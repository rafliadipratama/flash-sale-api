# Online Store API - Flash Sale with Race Condition Handling

A PHP REST API for managing an online store with flash sale functionality. The system is designed to handle high-concurrency scenarios while preventing inventory issues like negative stock.

## Features

- **Product Management**: Create and retrieve products with regular and flash sale prices
- **Order Management**: Create orders with multiple items in a single transaction
- **Race Condition Handling**: Uses database transactions and row-level locking to prevent inventory overallocation during flash sales
- **JSON API**: RESTful API with proper HTTP status codes and error handling
- **Functional Testing**: Includes a race condition test that simulates concurrent flash sale orders

## Technical Stack

- PHP 7.4+
- SQLite (for portability, can be switched to MySQL)
- PDO (PHP Data Objects) for database access

## Installation

1. Clone the repository
2. Install dependencies:
   ```bash
   composer install
   ```

## Running the API

### Development Server

```bash
cd public
php -S localhost:8000
```

The API will be available at `http://localhost:8000`

### Production Deployment

Use a proper web server like Apache or Nginx pointing to the `public/` directory.

## API Endpoints

### Products

#### List all products
```bash
GET /products
```

Response:
```json
{
  "success": true,
  "data": [
    {
      "id": 1,
      "name": "iPhone 15 Pro",
      "price": 1199.99,
      "flash_sale_price": 299.99,
      "inventory": 10
    }
  ]
}
```

#### Get product by ID
```bash
GET /products/{id}
```

#### Create a product
```bash
POST /products
Content-Type: application/json

{
  "name": "iPhone 15 Pro",
  "price": 1199.99,
  "flash_sale_price": 299.99,
  "inventory": 100
}
```

### Orders

#### Create an order
```bash
POST /orders
Content-Type: application/json

{
  "customer_name": "John Doe",
  "items": [
    {
      "product_id": 1,
      "quantity": 2
    },
    {
      "product_id": 2,
      "quantity": 1
    }
  ]
}
```

Response:
```json
{
  "success": true,
  "data": {
    "success": true,
    "order_id": 1,
    "total_amount": 699.97
  }
}
```

#### Get order by ID
```bash
GET /orders/{id}
```

Response:
```json
{
  "success": true,
  "data": {
    "id": 1,
    "customer_name": "John Doe",
    "total_amount": 699.97,
    "status": "pending",
    "created_at": "2026-06-18 10:30:45",
    "items": [
      {
        "id": 1,
        "order_id": 1,
        "product_id": 1,
        "quantity": 2,
        "unit_price": 299.99,
        "name": "iPhone 15 Pro"
      }
    ]
  }
}
```

## Race Condition Handling

The system handles race conditions during flash sales using the following strategies:

1. **Database Transactions with IMMEDIATE Mode**: Each order creation uses `BEGIN IMMEDIATE` to acquire locks early, preventing other transactions from modifying the same products simultaneously.

2. **Row-Level Locking**: The system uses `FOR UPDATE` to lock product rows during inventory checks.

3. **Atomic Operations**: All inventory updates happen within a single transaction, ensuring consistency.

### How it works:

1. Transaction starts with `BEGIN IMMEDIATE` (acquires lock immediately)
2. For each product in the order:
   - Lock the product row with `FOR UPDATE`
   - Check current inventory
   - If insufficient, rollback and return error
3. Only after all inventory checks pass:
   - Create the order
   - Create order items
   - Update inventory
4. Transaction commits

This prevents the classic "overselling" problem where multiple concurrent requests read the same inventory value before any writes occur.

## Running Tests

### Race Condition Test (Functional Test)

Run the functional test to verify the system handles concurrent orders correctly:

```bash
php tests/RaceConditionTest.php
```

This test:
1. Creates a product with 10 units in stock
2. Simulates 15 concurrent orders, each trying to buy 5 units (75 total)
3. Verifies that only 10 units are sold and inventory never goes negative
4. Displays detailed results showing which orders succeeded and which failed

### API Integration Test

Run the API test script to verify all endpoints work correctly:

```bash
# Start the server first
cd public && php -S localhost:8000

# In another terminal
bash tests/ApiTest.sh
```

This script tests:
- Creating products with flash sale prices
- Listing all products
- Getting product details
- Creating orders with multiple items
- Retrieving order details
- Error handling (insufficient inventory, missing fields, 404s)

Expected output:
```
========================================
Race Condition Test - Flash Sale Scenario
========================================

Creating test product with 10 units in stock...
Product created: ID=1, Inventory=10

Simulating flash sale with 15 concurrent orders (5 units each)...
Each order tries to buy 5 units = 75 total units requested
Only 10 units available - system should prevent negative inventory

[✓] Order #1: SUCCESS (Order ID: 1)
[✓] Order #2: SUCCESS (Order ID: 2)
[✗] Order #3: FAILED - Insufficient inventory for product 1...
...

========================================
Test Results
========================================

Final Product Inventory: 0 units
Successful Orders: 2
Failed Orders: 13
Total Units Allocated: 10

Verification:
- Expected final inventory: 0
- Actual final inventory: 0
- Result: ✓ PASS - Negative inventory prevented!
```

## Project Structure

```
.
├── public/
│   └── index.php           # API entry point and routes
├── src/
│   ├── Database.php        # Database connection singleton
│   ├── Response.php        # JSON response helper
│   └── Models/
│       ├── Product.php     # Product model
│       └── Order.php       # Order model with race condition handling
├── tests/
│   └── RaceConditionTest.php # Functional test for race conditions
├── database/
│   └── init.sql            # Database schema
├── config/
│   └── database.php        # Database configuration
├── composer.json
└── README.md
```

## Key Implementation Details

### Why SQLite for now?

- **Portability**: Database is a single file, easy to commit and share
- **Transactions**: Full ACID support with proper locking mechanisms
- **Simplicity**: No external dependencies, works out of the box
- **Good for testing**: Can easily reset database for tests

For production, switch to MySQL/PostgreSQL by updating the DSN in `config/database.php`.

### Error Handling

- **404**: Product or order not found
- **400**: Bad request (missing fields, validation errors)
- **409**: Conflict (insufficient inventory)
- **500**: Server error

All errors are returned in a consistent JSON format:

```json
{
  "success": false,
  "message": "Error description"
}
```

## Future Enhancements

- Add authentication and authorization
- Implement order status tracking and fulfillment
- Add payment processing
- Implement inventory reservations
- Add product categories and filtering
- Rate limiting for API
