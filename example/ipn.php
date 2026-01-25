<?php

use Edtutu\PesaPalToLife\Pesapal;

use function Edtutu\PesaPalToLife\jsonResponse;

require_once __DIR__ . '/../vendor/autoload.php';

/**
 * IPN (Instant Payment Notification) Handler
 * 
 * Pesapal sends notifications here when payment status changes.
 * Register this URL using: Pesapal::registerIPN($url, 'POST')
 * 
 * Request contains: OrderTrackingId, OrderMerchantReference, OrderNotificationType
 * 
 * IMPORTANT: This endpoint must be publicly accessible!
 * Use ngrok for local development: ngrok http 80
 */

// Define log file path
$logDir = __DIR__ . '/logs';
if (!is_dir($logDir)) {
    mkdir($logDir, 0755, true);
}
$logFile = $logDir . '/ipn_' . date('Y-m-d') . '.log';

/**
 * Log function for debugging
 */
function ipnLog($message, $data = null) {
    global $logFile;
    $entry = '[' . date('Y-m-d H:i:s') . '] ' . $message;
    if ($data !== null) {
        $entry .= ' | ' . json_encode($data);
    }
    file_put_contents($logFile, $entry . "\n", FILE_APPEND);
}

// Log incoming request
ipnLog('IPN Received', [
    'GET' => $_GET,
    'POST' => $_POST,
    'RAW' => file_get_contents('php://input'),
    'HEADERS' => getallheaders()
]);

// Extract parameters (Pesapal may send via GET or POST)
$orderTrackingId = $_GET['OrderTrackingId'] ?? $_POST['OrderTrackingId'] ?? null;
$merchantReference = $_GET['OrderMerchantReference'] ?? $_POST['OrderMerchantReference'] ?? null;
$notificationType = $_GET['OrderNotificationType'] ?? $_POST['OrderNotificationType'] ?? 'IPNCHANGE';

// Validate required parameter
if (empty($orderTrackingId)) {
    ipnLog('ERROR: Missing OrderTrackingId');
    http_response_code(400);
    jsonResponse([
        'orderNotificationType' => $notificationType,
        'orderTrackingId' => '',
        'orderMerchantReference' => $merchantReference ?? '',
        'status' => 0
    ]);
}

ipnLog('Processing IPN', [
    'orderTrackingId' => $orderTrackingId,
    'merchantReference' => $merchantReference,
    'notificationType' => $notificationType
]);

// Verify transaction status with Pesapal
$status = Pesapal::getTransactionStatus($orderTrackingId);

if (!$status->success || !isset($status->message)) {
    ipnLog('ERROR: Failed to verify transaction', ['response' => $status]);
    http_response_code(500);
    jsonResponse([
        'orderNotificationType' => $notificationType,
        'orderTrackingId' => $orderTrackingId,
        'orderMerchantReference' => $merchantReference ?? '',
        'status' => 0
    ]);
}

$transaction = $status->message;

/**
 * Transaction Status Codes:
 * 0 = INVALID
 * 1 = COMPLETED  
 * 2 = FAILED
 * 3 = REVERSED
 */
$statusCode = $transaction->status_code ?? 0;
$amount = $transaction->amount ?? 0;
$currency = $transaction->currency ?? 'KES';
$paymentMethod = $transaction->payment_method ?? 'Unknown';
$confirmationCode = $transaction->confirmation_code ?? '';

ipnLog('Transaction Verified', [
    'status_code' => $statusCode,
    'amount' => "{$currency} {$amount}",
    'payment_method' => $paymentMethod,
    'confirmation_code' => $confirmationCode
]);

// Process based on status
switch ($statusCode) {
    case 1: // COMPLETED
        ipnLog('PAYMENT COMPLETED', ['reference' => $merchantReference]);
        
        // TODO: Your business logic here
        // - Update database: UPDATE orders SET status='paid', confirmation_code=? WHERE reference=?
        // - Send confirmation email to customer
        // - Trigger fulfillment/delivery process
        // - Update inventory
        
        // Example database update (pseudo-code):
        // $db->query("UPDATE orders SET 
        //     status = 'paid',
        //     paid_at = NOW(),
        //     payment_method = ?,
        //     confirmation_code = ?,
        //     pesapal_tracking_id = ?
        //     WHERE reference = ?", 
        //     [$paymentMethod, $confirmationCode, $orderTrackingId, $merchantReference]
        // );
        break;
        
    case 2: // FAILED
        ipnLog('PAYMENT FAILED', ['reference' => $merchantReference]);
        
        // TODO: Handle failed payment
        // - Update order status to 'failed'
        // - Optionally notify customer
        break;
        
    case 3: // REVERSED
        ipnLog('PAYMENT REVERSED', ['reference' => $merchantReference]);
        
        // TODO: Handle reversal/refund
        // - Update order status to 'refunded'
        // - Reverse inventory changes
        // - Notify relevant parties
        break;
        
    default: // INVALID or unknown
        ipnLog('INVALID STATUS', ['status_code' => $statusCode, 'reference' => $merchantReference]);
        break;
}

// Respond to Pesapal - this is REQUIRED
// Pesapal expects this specific response format
http_response_code(200);
jsonResponse([
    'orderNotificationType' => $notificationType,
    'orderTrackingId' => $orderTrackingId,
    'orderMerchantReference' => $merchantReference ?? '',
    'status' => $statusCode
]);
