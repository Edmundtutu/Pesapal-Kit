# PesaPal To Life - Complete Integration Guide

A comprehensive PHP library for integrating Pesapal Payment Gateway API 3.0 into your applications.

## Table of Contents

1. [Requirements](#requirements)
2. [Installation](#installation)
3. [Configuration](#configuration)
4. [Quick Start](#quick-start)
5. [API Reference](#api-reference)
   - [Authentication](#authentication)
   - [IPN Management](#ipn-management)
   - [Order Submission](#order-submission)
   - [Transaction Status](#transaction-status)
   - [Refunds](#refunds)
   - [Order Cancellation](#order-cancellation)
6. [Testing with Postman](#testing-with-postman)
7. [Testing Locally with Ngrok](#testing-locally-with-ngrok)
8. [Webhook/IPN Handler](#webhookipn-handler)
9. [Status Codes Reference](#status-codes-reference)
10. [Troubleshooting](#troubleshooting)
11. [Production Checklist](#production-checklist)

---

## Requirements

- PHP 7.4 or higher
- Composer
- cURL PHP extension
- JSON PHP extension
- Pesapal merchant account (https://developer.pesapal.com)

---

## Installation

### 1. Clone or download the project

```bash
cd c:\xampp\htdocs
git clone <repository-url> pesapal-to-life
cd pesapal-to-life
```

### 2. Install dependencies

```bash
composer install
```

### 3. Create environment file

```bash
# Windows
copy .env.example .env

# Linux/Mac
cp .env.example .env
```

---

## Configuration

### Environment Variables

Edit the `.env` file with your settings:

```env
# ===========================================
# PESAPAL API CREDENTIALS
# ===========================================
PESAPAL_CONSUMER_KEY=your_consumer_key_here
PESAPAL_CONSUMER_SECRET=your_consumer_secret_here

# ===========================================
# ENVIRONMENT SETTINGS
# ===========================================
PESAPAL_SANDBOX=true

# Base URLs (usually no need to change)
PESAPAL_SANDBOX_URL=https://cybqa.pesapal.com/pesapalv3
PESAPAL_LIVE_URL=https://pay.pesapal.com/v3

# ===========================================
# API ENDPOINTS (usually no need to change)
# ===========================================
PESAPAL_AUTH_ENDPOINT=/api/Auth/RequestToken
PESAPAL_REGISTER_IPN_ENDPOINT=/api/URLSetup/RegisterIPN
PESAPAL_LIST_IPN_ENDPOINT=/api/URLSetup/GetIpnList
PESAPAL_SUBMIT_ORDER_ENDPOINT=/api/Transactions/SubmitOrderRequest
PESAPAL_TRANSACTION_STATUS_ENDPOINT=/api/Transactions/GetTransactionStatus
PESAPAL_REFUND_ENDPOINT=/api/Transactions/RefundRequest
PESAPAL_CANCEL_ORDER_ENDPOINT=/api/Transactions/CancelOrder

# ===========================================
# DEFAULT SETTINGS
# ===========================================
PESAPAL_CURRENCY=KES
PESAPAL_REDIRECT_MODE=TOP_WINDOW
PESAPAL_IPN_NOTIFICATION_TYPE=POST

# ===========================================
# APPLICATION SETTINGS
# ===========================================
APP_URL=http://localhost/pesapal-to-life
APP_DEBUG=true
```

### Configuration Variables Reference

| Variable | Description | Values |
|----------|-------------|--------|
| `PESAPAL_CONSUMER_KEY` | Your API consumer key | From Pesapal dashboard |
| `PESAPAL_CONSUMER_SECRET` | Your API consumer secret | From Pesapal dashboard |
| `PESAPAL_SANDBOX` | Environment mode | `true` or `false` |
| `PESAPAL_CURRENCY` | Default currency | `KES`, `UGX`, `TZS`, `USD` |
| `PESAPAL_REDIRECT_MODE` | How payment page opens | `TOP_WINDOW`, `PARENT_WINDOW` |
| `PESAPAL_IPN_NOTIFICATION_TYPE` | How IPNs are sent | `GET` or `POST` |

### Getting API Credentials

1. Go to https://developer.pesapal.com
2. Create an account or log in
3. For sandbox testing: Use the sandbox consumer key/secret
4. For production: Apply for and use live credentials

---

## Quick Start

### Step 1: Test Authentication

```php
<?php
require_once 'vendor/autoload.php';

use Edtutu\PesaPalToLife\Pesapal;
use function Edtutu\PesaPalToLife\jsonResponse;

// Get authentication token
$auth = Pesapal::authenticate();
jsonResponse($auth);
```

### Step 2: Register IPN URL

```php
$ipnUrl = 'https://your-domain.com/ipn-handler.php';
$result = Pesapal::registerIPN($ipnUrl, 'POST');
jsonResponse($result);

// Save the ipn_id from response - you need it for payments!
```

### Step 3: Submit a Payment

```php
$order = Pesapal::submitOrder([
    'amount' => 100,
    'callback_url' => 'https://your-domain.com/callback.php',
    'notification_id' => 'your-ipn-id',
    'description' => 'Order #12345',
    'billing_address' => [
        'phone_number' => '254700000000',
        'email_address' => 'customer@example.com',
        'first_name' => 'John',
        'last_name' => 'Doe'
    ]
]);

// Redirect customer to: $order->message->redirect_url
```

### Step 4: Handle Callback

```php
// Customer is redirected here after payment
$status = Pesapal::getTransactionStatus($_GET['OrderTrackingId']);

if ($status->message->status_code === 1) {
    // Payment successful - fulfill order
}
```

---

## API Reference

### Authentication

#### `Pesapal::authenticate($forceRefresh = false)`

Get an authentication token. Tokens are cached for 4 minutes (actual validity: 5 minutes).

```php
$auth = Pesapal::authenticate();

if ($auth->success) {
    echo $auth->message->token;
    echo $auth->message->expiryDate;
}
```

**Response:**
```json
{
  "success": true,
  "message": {
    "token": "eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1...",
    "expiryDate": "2026-01-24T12:00:00.000Z",
    "cached": false
  }
}
```

---

### IPN Management

#### `Pesapal::registerIPN($ipnUrl, $notificationType = null)`

Register a URL to receive payment notifications.

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `$ipnUrl` | string | Yes | Public URL to receive notifications |
| `$notificationType` | string | No | `GET` or `POST` (default from config) |

```php
$result = Pesapal::registerIPN('https://example.com/ipn', 'POST');

if ($result->success) {
    $ipnId = $result->message->ipn_id;  // Save this!
}
```

**Response:**
```json
{
  "success": true,
  "message": {
    "url": "https://example.com/ipn",
    "ipn_id": "e32182ca-0983-4fa0-91bc-c3bb813ba750",
    "created_date": "2026-01-24T10:00:00.000Z",
    "notification_type": "POST",
    "status": 1
  }
}
```

---

#### `Pesapal::listIPNs()`

Get all registered IPN URLs.

```php
$ipns = Pesapal::listIPNs();

foreach ($ipns->message as $ipn) {
    echo $ipn->ipn_id . ': ' . $ipn->url . "\n";
}
```

---

### Order Submission

#### `Pesapal::submitOrder(array $orderData)`

Submit a standard one-time payment order.

**Required Fields:**

| Field | Type | Description |
|-------|------|-------------|
| `amount` | float | Payment amount |
| `callback_url` | string | URL to redirect after payment |
| `notification_id` | string | IPN ID from registerIPN() |
| `billing_address` | array | Customer details (see below) |

**Optional Fields:**

| Field | Type | Description |
|-------|------|-------------|
| `id` | string | Your order reference (auto-generated if not provided) |
| `currency` | string | ISO currency code (default from config) |
| `description` | string | Order description (max 100 chars) |
| `redirect_mode` | string | `TOP_WINDOW` or `PARENT_WINDOW` |
| `cancellation_url` | string | URL if customer cancels |
| `branch` | string | Store/branch name |

**Billing Address Fields:**

| Field | Required | Description |
|-------|----------|-------------|
| `phone_number` | Conditional | Customer phone (required if no email) |
| `email_address` | Conditional | Customer email (required if no phone) |
| `first_name` | No | Customer first name |
| `last_name` | No | Customer last name |
| `country_code` | No | ISO country code (e.g., KE) |
| `city` | No | Customer city |
| `line_1` | No | Address line 1 |
| `line_2` | No | Address line 2 |
| `postal_code` | No | Postal/ZIP code |
| `state` | No | State/Province |

```php
$order = Pesapal::submitOrder([
    'amount' => 1000,
    'callback_url' => 'https://example.com/callback',
    'notification_id' => 'your-ipn-id',
    'description' => 'Premium Subscription',
    'currency' => 'KES',
    'billing_address' => [
        'phone_number' => '254700000000',
        'email_address' => 'customer@example.com',
        'first_name' => 'John',
        'last_name' => 'Doe',
        'country_code' => 'KE',
        'city' => 'Nairobi'
    ]
]);

if ($order->success) {
    // Save $order->message->order_tracking_id to your database
    // Redirect customer to $order->message->redirect_url
    header('Location: ' . $order->message->redirect_url);
}
```

**Response:**
```json
{
  "success": true,
  "message": {
    "order_tracking_id": "b945e4af-80a5-4ec1-8706-fa6b8a69a0d8",
    "merchant_reference": "ORDER-1737705123",
    "redirect_url": "https://cybqa.pesapal.com/PesapalIframe/PesapalIframe3/Index?OrderTrackingId=..."
  }
}
```

---

#### `Pesapal::submitRecurringOrder(array $orderData, $accountNumber, array $subscriptionDetails = null)`

Submit a recurring/subscription payment order.

**Additional Parameters:**

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `$accountNumber` | string | Yes | Customer account/invoice number |
| `$subscriptionDetails` | array | No | Subscription schedule |

**Subscription Details:**

| Field | Format | Description |
|-------|--------|-------------|
| `start_date` | dd-MM-yyyy | When to start (e.g., "24-01-2026") |
| `end_date` | dd-MM-yyyy | When to end (e.g., "31-12-2026") |
| `frequency` | string | `DAILY`, `WEEKLY`, `MONTHLY`, `YEARLY` |

```php
$subscription = Pesapal::submitRecurringOrder(
    [
        'amount' => 500,
        'callback_url' => 'https://example.com/callback',
        'notification_id' => 'your-ipn-id',
        'description' => 'Monthly Subscription',
        'billing_address' => [
            'email_address' => 'customer@example.com'
        ]
    ],
    'ACCT-12345',  // Account number
    [
        'start_date' => '01-02-2026',
        'end_date' => '01-02-2027',
        'frequency' => 'MONTHLY'
    ]
);
```

---

#### `Pesapal::orderProcess($amount, $phone, $callback, $ipnId, $options = [])`

Simplified order submission (backward compatible).

```php
$payment = Pesapal::orderProcess(
    100,                    // Amount
    '254700000000',         // Phone
    'https://example.com/callback',
    'your-ipn-id',
    [
        'description' => 'Quick Payment',
        'email' => 'customer@example.com',
        'first_name' => 'John',
        'last_name' => 'Doe'
    ]
);
```

---

### Transaction Status

#### `Pesapal::getTransactionStatus($orderTrackingId = null)`

Check payment status. Reads from `$_GET['OrderTrackingId']` if parameter is null.

```php
$status = Pesapal::getTransactionStatus('b945e4af-80a5-4ec1-8706-fa6b8a69a0d8');

if ($status->success) {
    $transaction = $status->message;
    
    switch ($transaction->status_code) {
        case 1:
            echo "Payment completed!";
            break;
        case 2:
            echo "Payment failed.";
            break;
    }
}
```

**Response:**
```json
{
  "success": true,
  "message": {
    "payment_method": "Mpesa",
    "amount": 1000,
    "created_date": "2026-01-24T10:30:00.000Z",
    "confirmation_code": "ABC123XYZ789",
    "payment_status_description": "Completed",
    "status_code": 1,
    "merchant_reference": "ORDER-12345",
    "description": "Premium Subscription",
    "currency": "KES",
    "message": "Request processed successfully"
  }
}
```

---

### Refunds

#### `Pesapal::requestRefund($confirmationCode, $amount, $username, $remarks)`

Request a refund for a completed payment.

**Parameters:**

| Parameter | Type | Description |
|-----------|------|-------------|
| `$confirmationCode` | string | From transaction status response |
| `$amount` | float | Amount to refund |
| `$username` | string | Identity of user initiating refund |
| `$remarks` | string | Reason for refund |

**Refund Rules:**
- Can only refund COMPLETED payments
- Cannot refund more than original amount
- Card payments: partial or full refund allowed
- Mobile payments: only full refund allowed
- Only one refund per payment

```php
$refund = Pesapal::requestRefund(
    'ABC123XYZ789',          // confirmation_code from transaction
    1000.00,                 // amount to refund
    'admin@company.com',     // who initiated
    'Customer requested cancellation'
);
```

---

### Order Cancellation

#### `Pesapal::cancelOrder($orderTrackingId)`

Cancel an unpaid order.

```php
$cancel = Pesapal::cancelOrder('b945e4af-80a5-4ec1-8706-fa6b8a69a0d8');
```

---

## Testing with Postman

A Postman collection is included for testing all API endpoints.

### Import the Collection

1. Open Postman
2. Click "Import"
3. Select `Pesapal_API_Collection.postman_collection.json`
4. The collection includes all endpoints with pre-configured requests

### Set Up Environment Variables

Before using the collection, set these variables in Postman:

| Variable | Value |
|----------|-------|
| `base_url` | `https://cybqa.pesapal.com/pesapalv3` (sandbox) |
| `consumer_key` | Your consumer key |
| `consumer_secret` | Your consumer secret |
| `auth_token` | (Auto-populated by auth request) |

### Collection Structure

```
Pesapal API Collection
├── Authentication
│   └── Get Auth Token
├── IPN Management
│   ├── Register IPN URL
│   └── List IPNs
├── Orders & Payments
│   ├── Submit Standard Order
│   ├── Submit Recurring Order
│   └── Get Transaction Status
└── Refunds & Cancellations
    ├── Request Refund
    └── Cancel Order
```

---

## Testing Locally with Ngrok

Pesapal needs to reach your callback and IPN URLs. For local development, use ngrok.

### 1. Install Ngrok

Download from: https://ngrok.com/download

### 2. Start Ngrok

```bash
ngrok http 80
```

### 3. Use the Public URL

Ngrok provides a URL like: `https://abc123.ngrok-free.app`

Update your `.env`:
```env
APP_URL=https://abc123.ngrok-free.app/pesapal-to-life
```

Use for:
- IPN: `https://abc123.ngrok-free.app/pesapal-to-life/example/ipn.php`
- Callback: `https://abc123.ngrok-free.app/pesapal-to-life/example/pay.php`

---

## Webhook/IPN Handler

The included IPN handler (`example/ipn.php`) processes payment notifications.

### How It Works

1. Customer completes payment on Pesapal
2. Pesapal sends notification to your IPN URL
3. Your handler verifies the transaction
4. You update your database and respond to Pesapal

### IPN Response Format

Pesapal expects this response:

```json
{
  "orderNotificationType": "IPNCHANGE",
  "orderTrackingId": "b945e4af-80a5-4ec1-8706-fa6b8a69a0d8",
  "orderMerchantReference": "ORDER-12345",
  "status": 1
}
```

### Logging

IPNs are logged to `example/logs/ipn_YYYY-MM-DD.log` for debugging.

---

## Status Codes Reference

### Transaction Status

| Code | Status | Description |
|------|--------|-------------|
| 0 | INVALID | Transaction not found or invalid |
| 1 | COMPLETED | Payment successful |
| 2 | FAILED | Payment failed |
| 3 | REVERSED | Payment was reversed/refunded |

### IPN Status

| Code | Status |
|------|--------|
| 0 | Invalid |
| 1 | Active |

---

## Troubleshooting

### "Missing PESAPAL_CONSUMER_KEY or PESAPAL_CONSUMER_SECRET"

- Verify `.env` file exists in project root
- Check credentials have no extra spaces
- Ensure quotes are used correctly

### "Failed to obtain Token"

- Verify API credentials are correct
- Check environment setting matches credentials (sandbox vs live)
- Ensure your IP isn't blocked by Pesapal

### IPN Not Receiving Notifications

- Ensure IPN URL is publicly accessible
- Use ngrok for local testing
- Check logs in `example/logs/`
- Verify IPN is registered and active

### Payment Page Not Loading

- Check `redirect_url` in response
- Use HTTPS for callbacks in production
- Verify IPN ID is valid

### cURL Errors

- Enable cURL PHP extension
- Check internet connection
- Update SSL certificates

### "Missing required field"

- All required fields must be provided
- `billing_address` must have phone OR email

---

## Production Checklist

Before going live:

- [ ] Switch to production credentials (`PESAPAL_SANDBOX=false`)
- [ ] Update `PESAPAL_LIVE_URL` if different from default
- [ ] Use HTTPS for all URLs
- [ ] Register production IPN URL
- [ ] Test with small amounts first
- [ ] Implement proper error handling and logging
- [ ] Store all transactions in database
- [ ] Set up email notifications
- [ ] Add `.env` to `.gitignore`
- [ ] Implement idempotency for order creation
- [ ] Add retry logic for failed API calls
- [ ] Set up monitoring for IPN failures

---

## File Structure

```
pesapal-to-life/
├── .env                                    # Configuration (DO NOT COMMIT)
├── .env.example                            # Example configuration
├── .gitignore                              # Git ignore rules
├── composer.json                           # Composer dependencies
├── GUIDE.md                                # This guide
├── README.MD                               # Project overview
├── Pesapal_API_Collection.postman_collection.json  # Postman collection
├── src/
│   ├── Pesapal.php                         # Main API class
│   ├── Curl.php                            # HTTP client wrapper
│   └── helpers.php                         # Helper functions
├── example/
│   ├── index.php                           # Example usage
│   ├── pay.php                             # Payment callback handler
│   ├── ipn.php                             # IPN notification handler
│   └── logs/                               # IPN logs directory
└── vendor/                                 # Composer dependencies
```

---

## Support

- Pesapal Documentation: https://developer.pesapal.com/how-to-integrate/e-commerce/api-30-json/api-reference
- Pesapal Support: support@pesapal.com

---

## License

MIT License
