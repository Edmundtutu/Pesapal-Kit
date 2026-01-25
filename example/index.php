<?php

use Edtutu\PesaPalToLife\Pesapal;

use function Edtutu\PesaPalToLife\jsonResponse;
use function Edtutu\PesaPalToLife\env;

require_once __DIR__ . '/../vendor/autoload.php';

/**
 * Pesapal API 3.0 - Complete Integration Examples
 * 
 * This file demonstrates ALL available Pesapal API methods.
 * 
 * Configuration (set in .env file):
 * - PESAPAL_CONSUMER_KEY     : Your API consumer key
 * - PESAPAL_CONSUMER_SECRET  : Your API consumer secret
 * - PESAPAL_SANDBOX          : true for testing, false for production
 * - APP_URL                  : Your application base URL
 * 
 * Usage: Uncomment ONE section at a time and run in browser or CLI
 */

// ============================================================================
// CONFIGURATION - Change these values for testing
// ============================================================================

// Your public URL for IPN/callbacks (use ngrok for local testing)
$publicUrl = env('APP_URL', 'http://localhost/pesapal-to-life');

// Test data
$testPhone = '254700000000';           // Customer phone number
$testEmail = 'customer@example.com';   // Customer email
$testAmount = 100;                     // Amount to charge

// ============================================================================
// UNCOMMENT ONE TEST SECTION AT A TIME
// ============================================================================


// ============================================
// 1. AUTHENTICATION - Get API Token
// ============================================
// Token is valid for 5 minutes and auto-cached
$auth = Pesapal::authenticate();
jsonResponse($auth);


// ============================================
// 2. REGISTER IPN URL (Instant Payment Notification)
// You need an IPN to receive payment status updates
// ============================================
// $ipnUrl = $publicUrl . '/example/ipn.php';
// $result = Pesapal::registerIPN($ipnUrl, 'POST');
// jsonResponse($result);
// NOTE: Save the ipn_id from the response - you need it for payments!


// ============================================
// 3. LIST ALL REGISTERED IPN URLs
// ============================================
// $ipns = Pesapal::listIPNs();
// jsonResponse($ipns);


// ============================================
// 4. SUBMIT STANDARD ORDER (One-time Payment)
// ============================================
// $ipnId = 'your_ipnId';  // Get this from step 2

// $order = Pesapal::submitOrder([
//     'amount' => $testAmount,
//     'callback_url' => $publicUrl . '/example/pay.php',
//     'notification_id' => $ipnId,
//     'description' => 'Test Order #' . time(),
//     'currency' => 'KES',
//     'billing_address' => [
//         'phone_number' => $testPhone,
//         'email_address' => $testEmail,
//         'first_name' => 'John',
//         'last_name' => 'Doe',   
//         'country_code' => 'KE',
//         'city' => 'Nairobi',
//     ]
// ]);
// jsonResponse($order);
// Redirect user to: $order->message->redirect_url


// ============================================
// 5. SUBMIT RECURRING ORDER (Subscription Payment)
// Enables automated recurring card payments
// ============================================
// $ipnId = 'YOUR_IPN_ID_HERE';
// 
// $subscription = Pesapal::submitRecurringOrder(
//     [
//         'amount' => 500,
//         'callback_url' => $publicUrl . '/example/pay.php',
//         'notification_id' => $ipnId,
//         'description' => 'Monthly Subscription',
//         'billing_address' => [
//             'email_address' => $testEmail,
//             'first_name' => 'Jane',
//             'last_name' => 'Doe'
//         ]
//     ],
//     'ACCT-' . time(),  // Customer account number
//     [
//         'start_date' => date('d-m-Y'),                    // Format: dd-mm-yyyy
//         'end_date' => date('d-m-Y', strtotime('+1 year')), // 1 year subscription
//         'frequency' => 'MONTHLY'                           // DAILY, WEEKLY, MONTHLY, YEARLY
//     ]
// );
// jsonResponse($subscription);


// ============================================
// 6. SIMPLIFIED ORDER (Backward Compatible)
// Quick method for simple payments
// ============================================
// $ipnId = 'YOUR_IPN_ID_HERE';
// 
// $payment = Pesapal::orderProcess(
//     $testAmount,                              // Amount
//     $testPhone,                               // Phone
//     $publicUrl . '/example/pay.php',          // Callback URL
//     $ipnId,                                   // IPN ID
//     [
//         'description' => 'Quick Payment Test',
//         'email' => $testEmail,
//         'first_name' => 'John',
//         'last_name' => 'Doe',
//         'merchant_reference' => 'ORDER-' . time()
//     ]
// );
// jsonResponse($payment);


// ============================================
// 7. CHECK TRANSACTION STATUS
// Use OrderTrackingId from order response or callback
// ============================================
// $orderTrackingId = 'YOUR_ORDER_TRACKING_ID';
// $status = Pesapal::getTransactionStatus($orderTrackingId);
// jsonResponse($status);
// 
// Status codes:
// 0 = INVALID
// 1 = COMPLETED
// 2 = FAILED  
// 3 = REVERSED


// ============================================
// 8. REQUEST REFUND
// Can only refund COMPLETED payments
// ============================================
// $refund = Pesapal::requestRefund(
//     'CONFIRMATION_CODE',    // From transaction status response
//     100.00,                 // Amount to refund
//     'admin@example.com',    // Who is requesting
//     'Customer requested cancellation'  // Reason
// );
// jsonResponse($refund);
// 
// Notes:
// - Card payments: partial or full refund allowed
// - Mobile payments: only full refund allowed
// - Only one refund per payment


// ============================================
// 9. CANCEL ORDER
// Cancel an unpaid order
// ============================================
// $cancel = Pesapal::cancelOrder('YOUR_ORDER_TRACKING_ID');
// jsonResponse($cancel);