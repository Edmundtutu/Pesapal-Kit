<?php

namespace Edtutu\PesaPalToLife;

use function Edtutu\PesaPalToLife\_config;
use function Edtutu\PesaPalToLife\env;

/**
 * Pesapal API 3.0 PHP Integration
 * 
 * A comprehensive PHP library for integrating Pesapal Payment Gateway.
 * Supports all Pesapal API 3.0 endpoints including:
 * - Authentication
 * - IPN Registration & Management
 * - Order Submission (Standard & Recurring)
 * - Transaction Status
 * - Refund Requests
 * - Order Cancellation
 * 
 * @author Edtutu
 * @version 2.0.0
 * @link https://developer.pesapal.com/how-to-integrate/e-commerce/api-30-json/api-reference
 */
class Pesapal
{
    /** @var object Configuration object */
    protected static $config;
    
    /** @var string|null Cached authentication token */
    protected static $cachedToken = null;
    
    /** @var int|null Token expiry timestamp */
    protected static $tokenExpiry = null;

    // =========================================================================
    // CONFIGURATION METHODS
    // =========================================================================

    /**
     * Get configuration from environment
     * @return object
     */
    public static function config()
    {
        if (self::$config === null) {
            self::$config = _config();
        }
        return self::$config;
    }

    /**
     * Get the base URL based on environment (sandbox or production)
     * @return string
     */
    public static function getBaseUrl()
    {
        $config = self::config();
        return $config->sandbox ? $config->sandboxUrl : $config->liveUrl;
    }

    /**
     * Get full endpoint URL
     * @param string $endpoint Endpoint key from config
     * @return string
     */
    protected static function getEndpointUrl($endpoint)
    {
        $config = self::config();
        $baseUrl = self::getBaseUrl();
        
        $endpoints = [
            'auth' => $config->endpoints->auth,
            'registerIpn' => $config->endpoints->registerIpn,
            'listIpn' => $config->endpoints->listIpn,
            'submitOrder' => $config->endpoints->submitOrder,
            'transactionStatus' => $config->endpoints->transactionStatus,
            'refund' => $config->endpoints->refund,
            'cancelOrder' => $config->endpoints->cancelOrder,
        ];
        
        return $baseUrl . ($endpoints[$endpoint] ?? '');
    }

    /**
     * Build standard headers for API requests
     * @param string|null $token Bearer token for authenticated requests
     * @return array
     */
    protected static function buildHeaders($token = null)
    {
        $headers = [
            'Content-Type' => 'application/json',
            'Accept' => 'application/json'
        ];
        
        if ($token) {
            $headers['Authorization'] = 'Bearer ' . $token;
        }
        
        return $headers;
    }

    /**
     * Format API response into standardized object
     * @param array $response Raw response from Curl
     * @return object
     */
    protected static function formatResponse($response)
    {
        return (object) $response;
    }

    // =========================================================================
    // AUTHENTICATION
    // =========================================================================

    /**
     * Authenticate with Pesapal API and get access token
     * 
     * The token is valid for 5 minutes. This method caches the token
     * to avoid unnecessary API calls.
     * 
     * @param bool $forceRefresh Force a new token even if cached one exists
     * @return object Response containing token or error
     * 
     * @example
     * $auth = Pesapal::authenticate();
     * if ($auth->success) {
     *     echo $auth->message->token;
     * }
     */
    public static function authenticate($forceRefresh = false)
    {
        // Return cached token if still valid
        if (!$forceRefresh && self::$cachedToken && self::$tokenExpiry > time()) {
            return (object) [
                'success' => true,
                'message' => (object) [
                    'token' => self::$cachedToken,
                    'expiryDate' => date('c', self::$tokenExpiry),
                    'cached' => true
                ]
            ];
        }

        $config = self::config();

        // Validate credentials
        if (empty($config->consumerKey) || empty($config->consumerSecret)) {
            return (object) [
                'success' => false,
                'message' => 'Missing PESAPAL_CONSUMER_KEY or PESAPAL_CONSUMER_SECRET in .env file'
            ];
        }

        $url = self::getEndpointUrl('auth');
        $headers = self::buildHeaders();
        $body = json_encode([
            'consumer_key' => $config->consumerKey,
            'consumer_secret' => $config->consumerSecret,
        ]);

        $response = Curl::Post($url, $headers, $body);
        $result = self::formatResponse($response);

        // Cache token if successful
        if ($result->success && isset($result->message->token)) {
            self::$cachedToken = $result->message->token;
            // Set expiry to 4 minutes (conservative, actual is 5 min)
            self::$tokenExpiry = time() + 240;
        }

        return $result;
    }

    /**
     * Alias for authenticate() - backward compatibility
     * @return object
     */
    public static function pesapalAuth()
    {
        return self::authenticate();
    }

    /**
     * Get valid token, authenticating if necessary
     * @return string|null Token or null on failure
     */
    protected static function getToken()
    {
        $auth = self::authenticate();
        return $auth->success ? $auth->message->token : null;
    }

    // =========================================================================
    // IPN (INSTANT PAYMENT NOTIFICATION) MANAGEMENT
    // =========================================================================

    /**
     * Register an IPN (Instant Payment Notification) URL
     * 
     * IPN URLs receive notifications when payment status changes.
     * You must register an IPN URL before submitting orders.
     * 
     * @param string $ipnUrl Full URL to receive notifications (must be publicly accessible)
     * @param string $notificationType HTTP method: 'GET' or 'POST' (default: from config)
     * @return object Response containing ipn_id or error
     * 
     * @example
     * $result = Pesapal::registerIPN('https://example.com/ipn-handler', 'POST');
     * if ($result->success) {
     *     $ipnId = $result->message->ipn_id; // Save this for order submissions
     * }
     */
    public static function registerIPN($ipnUrl, $notificationType = null)
    {
        $token = self::getToken();
        if (!$token) {
            return (object) ['success' => false, 'message' => 'Failed to obtain authentication token'];
        }

        $config = self::config();
        $notificationType = $notificationType ?? $config->ipnNotificationType;

        $url = self::getEndpointUrl('registerIpn');
        $headers = self::buildHeaders($token);
        $body = json_encode([
            'url' => $ipnUrl,
            'ipn_notification_type' => strtoupper($notificationType),
        ]);

        $response = Curl::Post($url, $headers, $body);
        return self::formatResponse($response);
    }

    /**
     * Alias for registerIPN() - backward compatibility
     */
    public static function pesapalRegisterIPN($ipnUrl, $notificationType = 'POST')
    {
        return self::registerIPN($ipnUrl, $notificationType);
    }

    /**
     * Get list of all registered IPN URLs
     * 
     * @return object Response containing array of registered IPNs
     * 
     * @example
     * $ipns = Pesapal::listIPNs();
     * if ($ipns->success) {
     *     foreach ($ipns->message as $ipn) {
     *         echo $ipn->ipn_id . ': ' . $ipn->url;
     *     }
     * }
     */
    public static function listIPNs()
    {
        $token = self::getToken();
        if (!$token) {
            return (object) ['success' => false, 'message' => 'Failed to obtain authentication token'];
        }

        $url = self::getEndpointUrl('listIpn');
        $headers = self::buildHeaders($token);

        $response = Curl::Get($url, $headers);
        return self::formatResponse($response);
    }

    // =========================================================================
    // ORDER SUBMISSION
    // =========================================================================

    /**
     * Submit a standard payment order request
     * 
     * Creates a payment request and returns a redirect URL for the customer
     * to complete payment on Pesapal's hosted page.
     * 
     * @param array $orderData Order details
     * @return object Response containing redirect_url and order_tracking_id
     * 
     * Required $orderData keys:
     * - amount (float): Payment amount
     * - callback_url (string): URL to redirect after payment
     * - notification_id (string): IPN ID from registerIPN()
     * - billing_address (array): Customer info with phone_number or email_address
     * 
     * Optional $orderData keys:
     * - id (string): Your unique order reference (auto-generated if not provided)
     * - currency (string): ISO currency code (default: from config)
     * - description (string): Order description (max 100 chars)
     * - redirect_mode (string): TOP_WINDOW or PARENT_WINDOW
     * - cancellation_url (string): URL if customer cancels
     * - branch (string): Store/branch name
     * 
     * @example
     * $order = Pesapal::submitOrder([
     *     'amount' => 1000,
     *     'callback_url' => 'https://example.com/callback',
     *     'notification_id' => 'your-ipn-id',
     *     'description' => 'Order #12345',
     *     'billing_address' => [
     *         'phone_number' => '254700000000',
     *         'email_address' => 'customer@example.com',
     *         'first_name' => 'John',
     *         'last_name' => 'Doe'
     *     ]
     * ]);
     * 
     * if ($order->success) {
     *     // Redirect customer to $order->message->redirect_url
     * }
     */
    public static function submitOrder(array $orderData)
    {
        $token = self::getToken();
        if (!$token) {
            return (object) ['success' => false, 'message' => 'Failed to obtain authentication token'];
        }

        $config = self::config();

        // Validate required fields
        $required = ['amount', 'callback_url', 'notification_id', 'billing_address'];
        foreach ($required as $field) {
            if (empty($orderData[$field])) {
                return (object) ['success' => false, 'message' => "Missing required field: {$field}"];
            }
        }

        // Validate billing address has contact info
        $billing = $orderData['billing_address'];
        if (empty($billing['phone_number']) && empty($billing['email_address'])) {
            return (object) [
                'success' => false, 
                'message' => 'billing_address must contain phone_number or email_address'
            ];
        }

        // Build payload
        $payload = [
            'id' => $orderData['id'] ?? self::generateOrderId(),
            'currency' => strtoupper($orderData['currency'] ?? $config->currency),
            'amount' => (float) $orderData['amount'],
            'description' => substr($orderData['description'] ?? 'Payment', 0, 100),
            'callback_url' => $orderData['callback_url'],
            'notification_id' => $orderData['notification_id'],
            'redirect_mode' => $orderData['redirect_mode'] ?? $config->redirectMode,
            'billing_address' => self::buildBillingAddress($billing),
        ];

        // Optional fields
        if (!empty($orderData['cancellation_url'])) {
            $payload['cancellation_url'] = $orderData['cancellation_url'];
        }
        if (!empty($orderData['branch'])) {
            $payload['branch'] = $orderData['branch'];
        }

        $url = self::getEndpointUrl('submitOrder');
        $headers = self::buildHeaders($token);

        $response = Curl::Post($url, $headers, json_encode($payload));
        return self::formatResponse($response);
    }

    /**
     * Submit a recurring/subscription payment order
     * 
     * Similar to submitOrder() but enables recurring payments.
     * Customer can set up automated card payments.
     * 
     * @param array $orderData Order details (same as submitOrder)
     * @param string $accountNumber Customer account/invoice number
     * @param array|null $subscriptionDetails Optional subscription parameters
     * @return object
     * 
     * $subscriptionDetails keys (all required if provided):
     * - start_date (string): Format dd-MM-yyyy e.g., "24-01-2026"
     * - end_date (string): Format dd-MM-yyyy e.g., "31-12-2026"
     * - frequency (string): DAILY, WEEKLY, MONTHLY, or YEARLY
     * 
     * @example
     * $order = Pesapal::submitRecurringOrder(
     *     [
     *         'amount' => 500,
     *         'callback_url' => 'https://example.com/callback',
     *         'notification_id' => 'your-ipn-id',
     *         'billing_address' => ['email_address' => 'customer@example.com']
     *     ],
     *     'ACCT-12345',
     *     [
     *         'start_date' => '01-02-2026',
     *         'end_date' => '01-02-2027',
     *         'frequency' => 'MONTHLY'
     *     ]
     * );
     */
    public static function submitRecurringOrder(array $orderData, $accountNumber, array $subscriptionDetails = null)
    {
        $token = self::getToken();
        if (!$token) {
            return (object) ['success' => false, 'message' => 'Failed to obtain authentication token'];
        }

        $config = self::config();

        // Validate required fields
        $required = ['amount', 'callback_url', 'notification_id', 'billing_address'];
        foreach ($required as $field) {
            if (empty($orderData[$field])) {
                return (object) ['success' => false, 'message' => "Missing required field: {$field}"];
            }
        }

        if (empty($accountNumber)) {
            return (object) ['success' => false, 'message' => 'account_number is required for recurring payments'];
        }

        $billing = $orderData['billing_address'];
        if (empty($billing['phone_number']) && empty($billing['email_address'])) {
            return (object) [
                'success' => false, 
                'message' => 'billing_address must contain phone_number or email_address'
            ];
        }

        // Build payload
        $payload = [
            'id' => $orderData['id'] ?? self::generateOrderId(),
            'currency' => strtoupper($orderData['currency'] ?? $config->currency),
            'amount' => (float) $orderData['amount'],
            'description' => substr($orderData['description'] ?? 'Subscription Payment', 0, 100),
            'callback_url' => $orderData['callback_url'],
            'notification_id' => $orderData['notification_id'],
            'redirect_mode' => $orderData['redirect_mode'] ?? $config->redirectMode,
            'billing_address' => self::buildBillingAddress($billing),
            'account_number' => $accountNumber,
        ];

        // Add subscription details if provided
        if ($subscriptionDetails) {
            $requiredSub = ['start_date', 'end_date', 'frequency'];
            foreach ($requiredSub as $field) {
                if (empty($subscriptionDetails[$field])) {
                    return (object) ['success' => false, 'message' => "Missing subscription field: {$field}"];
                }
            }
            
            $validFrequencies = ['DAILY', 'WEEKLY', 'MONTHLY', 'YEARLY'];
            if (!in_array(strtoupper($subscriptionDetails['frequency']), $validFrequencies)) {
                return (object) [
                    'success' => false, 
                    'message' => 'Invalid frequency. Must be: ' . implode(', ', $validFrequencies)
                ];
            }

            $payload['subscription_details'] = [
                'start_date' => $subscriptionDetails['start_date'],
                'end_date' => $subscriptionDetails['end_date'],
                'frequency' => strtoupper($subscriptionDetails['frequency']),
            ];
        }

        $url = self::getEndpointUrl('submitOrder');
        $headers = self::buildHeaders($token);

        $response = Curl::Post($url, $headers, json_encode($payload));
        return self::formatResponse($response);
    }

    /**
     * Simplified order submission (backward compatibility)
     * 
     * @param float $amount Payment amount
     * @param string $phone Customer phone number
     * @param string $callback Callback URL
     * @param string $ipnId IPN notification ID
     * @param array $options Additional options
     * @return object
     */
    public static function orderProcess($amount, $phone, $callback, $ipnId, $options = [])
    {
        $billingAddress = ['phone_number' => $phone];
        
        if (!empty($options['email'])) {
            $billingAddress['email_address'] = $options['email'];
        }
        if (!empty($options['first_name'])) {
            $billingAddress['first_name'] = $options['first_name'];
        }
        if (!empty($options['last_name'])) {
            $billingAddress['last_name'] = $options['last_name'];
        }

        return self::submitOrder([
            'id' => $options['merchant_reference'] ?? null,
            'amount' => $amount,
            'callback_url' => $callback,
            'notification_id' => $ipnId,
            'description' => $options['description'] ?? 'Payment',
            'currency' => $options['currency'] ?? null,
            'billing_address' => $billingAddress,
        ]);
    }

    // =========================================================================
    // TRANSACTION STATUS
    // =========================================================================

    /**
     * Get transaction/payment status
     * 
     * Check the status of a payment using the OrderTrackingId.
     * Call this on callback URL or when IPN is triggered.
     * 
     * Status codes:
     * - 0: INVALID
     * - 1: COMPLETED
     * - 2: FAILED
     * - 3: REVERSED
     * 
     * @param string|null $orderTrackingId Pesapal order tracking ID (reads from $_GET if null)
     * @return object Response containing payment details and status
     * 
     * @example
     * $status = Pesapal::getTransactionStatus('b945e4af-80a5-4ec1-8706-e03f8332fb04');
     * if ($status->success && $status->message->status_code === 1) {
     *     // Payment completed successfully
     * }
     */
    public static function getTransactionStatus($orderTrackingId = null)
    {
        $trackingId = $orderTrackingId ?? ($_GET['OrderTrackingId'] ?? null);

        if (empty($trackingId)) {
            return (object) ['success' => false, 'message' => 'Missing OrderTrackingId parameter'];
        }

        $token = self::getToken();
        if (!$token) {
            return (object) ['success' => false, 'message' => 'Failed to obtain authentication token'];
        }

        $config = self::config();
        $url = self::getEndpointUrl('transactionStatus') . '?orderTrackingId=' . urlencode($trackingId);
        $headers = self::buildHeaders($token);

        $response = Curl::Get($url, $headers);
        return self::formatResponse($response);
    }

    /**
     * Alias for getTransactionStatus() - backward compatibility
     */
    public static function transactionStatus($orderTrackingId = null)
    {
        return self::getTransactionStatus($orderTrackingId);
    }

    // =========================================================================
    // REFUND REQUESTS
    // =========================================================================

    /**
     * Request a refund for a completed payment
     * 
     * Limitations:
     * - Can only refund COMPLETED payments
     * - Cannot refund more than original amount
     * - Card payments: partial or full refund allowed
     * - Mobile payments: only full refund allowed
     * - Only one refund per payment
     * 
     * @param string $confirmationCode Payment confirmation code from getTransactionStatus()
     * @param float $amount Amount to refund
     * @param string $username Identity of user initiating refund
     * @param string $remarks Reason for refund
     * @return object
     * 
     * @example
     * $refund = Pesapal::requestRefund(
     *     'ABC123XYZ',      // confirmation_code from transaction
     *     1000.00,          // amount to refund
     *     'admin@shop.com', // who initiated
     *     'Customer requested cancellation'
     * );
     */
    public static function requestRefund($confirmationCode, $amount, $username, $remarks)
    {
        $token = self::getToken();
        if (!$token) {
            return (object) ['success' => false, 'message' => 'Failed to obtain authentication token'];
        }

        // Validate inputs
        if (empty($confirmationCode)) {
            return (object) ['success' => false, 'message' => 'confirmation_code is required'];
        }
        if ($amount <= 0) {
            return (object) ['success' => false, 'message' => 'amount must be greater than 0'];
        }
        if (empty($username)) {
            return (object) ['success' => false, 'message' => 'username is required'];
        }
        if (empty($remarks)) {
            return (object) ['success' => false, 'message' => 'remarks is required'];
        }

        $url = self::getEndpointUrl('refund');
        $headers = self::buildHeaders($token);
        $body = json_encode([
            'confirmation_code' => $confirmationCode,
            'amount' => number_format((float) $amount, 2, '.', ''),
            'username' => $username,
            'remarks' => $remarks,
        ]);

        $response = Curl::Post($url, $headers, $body);
        return self::formatResponse($response);
    }

    // =========================================================================
    // ORDER CANCELLATION
    // =========================================================================

    /**
     * Cancel a pending or failed order
     * 
     * Limitations:
     * - Only for FAILED or PENDING payments
     * - Cannot cancel completed payments
     * - Only one cancellation per order
     * 
     * @param string $orderTrackingId Pesapal order tracking ID
     * @return object
     * 
     * @example
     * $cancel = Pesapal::cancelOrder('b945e4af-80a5-4ec1-8706-e03f8332fb04');
     */
    public static function cancelOrder($orderTrackingId)
    {
        $token = self::getToken();
        if (!$token) {
            return (object) ['success' => false, 'message' => 'Failed to obtain authentication token'];
        }

        if (empty($orderTrackingId)) {
            return (object) ['success' => false, 'message' => 'order_tracking_id is required'];
        }

        $url = self::getEndpointUrl('cancelOrder');
        $headers = self::buildHeaders($token);
        $body = json_encode([
            'order_tracking_id' => $orderTrackingId,
        ]);

        $response = Curl::Post($url, $headers, $body);
        return self::formatResponse($response);
    }

    // =========================================================================
    // HELPER METHODS
    // =========================================================================

    /**
     * Generate a unique order ID
     * @return string
     */
    protected static function generateOrderId()
    {
        return 'ORD-' . date('Ymd') . '-' . strtoupper(substr(md5(uniqid(mt_rand(), true)), 0, 8));
    }

    /**
     * Build billing address object with proper structure
     * @param array $address Input address data
     * @return array Formatted address
     */
    protected static function buildBillingAddress(array $address)
    {
        $billing = [];
        
        $fields = [
            'phone_number', 'email_address', 'country_code',
            'first_name', 'middle_name', 'last_name',
            'line_1', 'line_2', 'city', 'state',
            'postal_code', 'zip_code'
        ];
        
        foreach ($fields as $field) {
            if (!empty($address[$field])) {
                $billing[$field] = $address[$field];
            }
        }
        
        return $billing;
    }

    /**
     * Check if current environment is sandbox
     * @return bool
     */
    public static function isSandbox()
    {
        return self::config()->sandbox;
    }

    /**
     * Get current environment name
     * @return string 'sandbox' or 'production'
     */
    public static function getEnvironment()
    {
        return self::isSandbox() ? 'sandbox' : 'production';
    }

    /**
     * Validate IPN callback data
     * @param array|null $data POST or GET data (uses $_REQUEST if null)
     * @return array Validated IPN data or error
     */
    public static function validateIPNCallback($data = null)
    {
        $data = $data ?? $_REQUEST;
        
        $orderTrackingId = $data['OrderTrackingId'] ?? null;
        $merchantReference = $data['OrderMerchantReference'] ?? null;
        $notificationType = $data['OrderNotificationType'] ?? null;
        
        if (empty($orderTrackingId)) {
            return [
                'valid' => false,
                'error' => 'Missing OrderTrackingId'
            ];
        }
        
        return [
            'valid' => true,
            'order_tracking_id' => $orderTrackingId,
            'merchant_reference' => $merchantReference,
            'notification_type' => $notificationType,
            'is_callback' => $notificationType === 'CALLBACKURL',
            'is_ipn' => $notificationType === 'IPNCHANGE',
            'is_recurring' => $notificationType === 'RECURRING',
        ];
    }

    /**
     * Build IPN response to send back to Pesapal
     * @param string $orderTrackingId
     * @param string $merchantReference
     * @param string $notificationType
     * @param int $status 200 for success, 500 for failure
     * @return array
     */
    public static function buildIPNResponse($orderTrackingId, $merchantReference, $notificationType, $status = 200)
    {
        return [
            'orderNotificationType' => $notificationType,
            'orderTrackingId' => $orderTrackingId,
            'orderMerchantReference' => $merchantReference,
            'status' => $status
        ];
    }

    /**
     * Clear cached authentication token
     * Useful when switching environments or credentials
     */
    public static function clearCache()
    {
        self::$cachedToken = null;
        self::$tokenExpiry = null;
        self::$config = null;
    }
}
