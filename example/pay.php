<?php

use Edtutu\PesaPalToLife\Pesapal;

use function Edtutu\PesaPalToLife\jsonResponse;

require_once __DIR__ . '/../vendor/autoload.php';

/**
 * Payment Callback Handler
 * 
 * Pesapal redirects customers here after payment attempt.
 * URL: pay.php?OrderTrackingId=xxx&OrderMerchantReference=xxx
 * 
 * This page should:
 * 1. Verify the transaction with Pesapal
 * 2. Update your database based on status
 * 3. Display appropriate message to customer
 */

// Get callback parameters
$orderTrackingId = $_GET['OrderTrackingId'] ?? null;
$merchantReference = $_GET['OrderMerchantReference'] ?? null;

// No parameters - show instructions
if (!$orderTrackingId) {
    jsonResponse([
        'success' => false,
        'message' => 'This is the payment callback page.',
        'info' => 'Pesapal will redirect customers here after payment with OrderTrackingId parameter.',
        'usage' => 'Set this URL as callback_url when creating orders.'
    ]);
}

// Verify transaction status with Pesapal
$verify = Pesapal::getTransactionStatus($orderTrackingId);

if (!$verify->success) {
    jsonResponse([
        'success' => false,
        'message' => 'Failed to verify transaction',
        'error' => $verify->message ?? 'Unknown error',
        'order_tracking_id' => $orderTrackingId
    ]);
}

$transaction = $verify->message;

/**
 * Transaction Status Codes:
 * 0 = INVALID
 * 1 = COMPLETED  
 * 2 = FAILED
 * 3 = REVERSED
 */
$statusCode = $transaction->status_code ?? 0;
$statusMessages = [
    0 => 'INVALID',
    1 => 'COMPLETED',
    2 => 'FAILED',
    3 => 'REVERSED'
];

// Build response for display
$response = [
    'success' => $statusCode === 1,
    'status' => $statusMessages[$statusCode] ?? 'UNKNOWN',
    'status_code' => $statusCode,
    'order_tracking_id' => $orderTrackingId,
    'merchant_reference' => $merchantReference,
    'transaction_details' => [
        'amount' => $transaction->amount ?? null,
        'currency' => $transaction->currency ?? null,
        'payment_method' => $transaction->payment_method ?? null,
        'confirmation_code' => $transaction->confirmation_code ?? null,
        'payment_status_description' => $transaction->payment_status_description ?? null,
        'created_date' => $transaction->created_date ?? null,
    ]
];

// Here you would typically:
// 1. Update database: UPDATE orders SET status = ?, paid_at = ? WHERE reference = ?
// 2. Send email confirmation
// 3. Trigger fulfillment process
// 4. Clear shopping cart

switch ($statusCode) {
    case 1: // COMPLETED
        // Payment successful
        // TODO: Mark order as paid in your database
        // TODO: Send confirmation email
        // TODO: Trigger order fulfillment
        $response['message'] = 'Payment completed successfully. Thank you for your purchase!';
        break;
        
    case 2: // FAILED
        // Payment failed
        // TODO: Update order status to failed
        $response['message'] = 'Payment failed. Please try again or use a different payment method.';
        break;
        
    case 3: // REVERSED
        // Payment was reversed (refunded)
        // TODO: Handle refund in your system
        $response['message'] = 'Payment has been reversed.';
        break;
        
    default: // INVALID or unknown
        // Invalid transaction
        $response['message'] = 'Transaction status is invalid or pending. Please wait or contact support.';
        break;
}

jsonResponse($response);