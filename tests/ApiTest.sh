#!/bin/bash

# API Testing Script for Online Store
# This script tests all API endpoints

BASE_URL="http://localhost:8000"
PRODUCT_ID=""
ORDER_ID=""

echo "========================================="
echo "Online Store API - Integration Tests"
echo "========================================="
echo ""

# Test 1: Create Product 1
echo "Test 1: Create Product (iPhone 15 Pro)"
echo "POST /products"
RESPONSE=$(curl -s -X POST "$BASE_URL/products" \
  -H "Content-Type: application/json" \
  -d '{
    "name": "iPhone 15 Pro",
    "price": 1199.99,
    "flash_sale_price": 299.99,
    "inventory": 100
  }')
echo "$RESPONSE"
PRODUCT_ID=$(echo "$RESPONSE" | grep -o '"id":[0-9]*' | head -1 | grep -o '[0-9]*')
echo "✓ Product created with ID: $PRODUCT_ID"
echo ""

# Test 2: Create Product 2
echo "Test 2: Create Product (iPad Air)"
echo "POST /products"
RESPONSE=$(curl -s -X POST "$BASE_URL/products" \
  -H "Content-Type: application/json" \
  -d '{
    "name": "iPad Air",
    "price": 599.99,
    "flash_sale_price": 399.99,
    "inventory": 50
  }')
echo "$RESPONSE"
PRODUCT_ID_2=$(echo "$RESPONSE" | grep -o '"id":[0-9]*' | head -1 | grep -o '[0-9]*')
echo "✓ Product created with ID: $PRODUCT_ID_2"
echo ""

# Test 3: Get all products
echo "Test 3: Get All Products"
echo "GET /products"
curl -s -X GET "$BASE_URL/products"
echo ""
echo ""

# Test 4: Get single product
echo "Test 4: Get Single Product"
echo "GET /products/$PRODUCT_ID"
curl -s -X GET "$BASE_URL/products/$PRODUCT_ID"
echo ""
echo ""

# Test 5: Create order with multiple items
echo "Test 5: Create Order"
echo "POST /orders"
RESPONSE=$(curl -s -X POST "$BASE_URL/orders" \
  -H "Content-Type: application/json" \
  -d "{
    \"customer_name\": \"John Doe\",
    \"items\": [
      {
        \"product_id\": $PRODUCT_ID,
        \"quantity\": 2
      },
      {
        \"product_id\": $PRODUCT_ID_2,
        \"quantity\": 1
      }
    ]
  }")
echo "$RESPONSE"
ORDER_ID=$(echo "$RESPONSE" | grep -o '"order_id":[0-9]*' | grep -o '[0-9]*')
echo "✓ Order created with ID: $ORDER_ID"
echo ""

# Test 6: Get order details
echo "Test 6: Get Order Details"
echo "GET /orders/$ORDER_ID"
curl -s -X GET "$BASE_URL/orders/$ORDER_ID"
echo ""
echo ""

# Test 7: Test insufficient inventory
echo "Test 7: Test Insufficient Inventory (should fail)"
echo "POST /orders"
curl -s -X POST "$BASE_URL/orders" \
  -H "Content-Type: application/json" \
  -d "{
    \"customer_name\": \"Jane Doe\",
    \"items\": [
      {
        \"product_id\": $PRODUCT_ID,
        \"quantity\": 1000
      }
    ]
  }"
echo ""
echo ""

# Test 8: Test missing customer name
echo "Test 8: Test Missing Customer Name (should fail)"
echo "POST /orders"
curl -s -X POST "$BASE_URL/orders" \
  -H "Content-Type: application/json" \
  -d "{
    \"items\": [
      {
        \"product_id\": $PRODUCT_ID,
        \"quantity\": 1
      }
    ]
  }"
echo ""
echo ""

# Test 9: Test empty order items
echo "Test 9: Test Empty Order Items (should fail)"
echo "POST /orders"
curl -s -X POST "$BASE_URL/orders" \
  -H "Content-Type: application/json" \
  -d "{
    \"customer_name\": \"Test User\",
    \"items\": []
  }"
echo ""
echo ""

# Test 10: Test 404 Not Found
echo "Test 10: Test 404 Not Found"
echo "GET /invalid-endpoint"
curl -s -X GET "$BASE_URL/invalid-endpoint"
echo ""
echo ""

echo "========================================="
echo "All tests completed!"
echo "========================================="
