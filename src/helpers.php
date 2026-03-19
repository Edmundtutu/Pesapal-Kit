<?php

namespace Edtutu\PesaPalToLife;

use stdClass;

/**
 * Helper Functions for PesaPal To Life
 * 
 * Provides utility functions for environment configuration,
 * JSON responses, and general helper methods.
 */

// =========================================================================
// ENVIRONMENT LOADING
// =========================================================================

/**
 * Load environment variables from .env file
 * 
 * Parses a .env file and sets environment variables.
 * Supports comments (#), empty lines, and quoted values.
 * 
 * @param string|null $path Path to directory containing .env file
 * @return void
 */
function loadEnv($path = null)
{
    static $loaded = false;
    
    if ($loaded) {
        return;
    }
    
    if ($path === null) {
        $path = dirname(__DIR__);
    }
    
    $envFile = rtrim($path, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . '.env';
    
    if (!file_exists($envFile)) {
        return;
    }
    
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    
    foreach ($lines as $line) {
        $line = trim($line);
        
        // Skip comments and empty lines
        if (empty($line) || strpos($line, '#') === 0) {
            continue;
        }
        
        // Parse key=value
        if (strpos($line, '=') !== false) {
            list($key, $value) = explode('=', $line, 2);
            $key = trim($key);
            $value = trim($value);
            
            // Remove surrounding quotes if present
            if (preg_match('/^(["\'])(.*)\\1$/', $value, $matches)) {
                $value = $matches[2];
            }
            
            if (!empty($key)) {
                $_ENV[$key] = $value;
                putenv("{$key}={$value}");
            }
        }
    }
    
    $loaded = true;
}

/**
 * Get environment variable value
 * 
 * @param string $key Environment variable name
 * @param mixed $default Default value if not found
 * @return mixed
 */
function env($key, $default = null)
{
    // Ensure .env is loaded
    loadEnv();
    
    $value = getenv($key);
    
    if ($value === false) {
        $value = $_ENV[$key] ?? $default;
    }
    
    // Convert string booleans
    if (is_string($value)) {
        $lower = strtolower($value);
        if ($lower === 'true') return true;
        if ($lower === 'false') return false;
        if ($lower === 'null') return null;
    }
    
    return $value !== false ? $value : $default;
}

// =========================================================================
// CONFIGURATION
// =========================================================================

/**
 * Get Pesapal configuration from environment variables
 * 
 * Loads all configuration settings from .env file and returns
 * a structured configuration object.
 * 
 * @return stdClass Configuration object
 */
function _config()
{
    // Ensure environment is loaded
    loadEnv();
    
    $config = new stdClass();
    
    // API Credentials
    $config->consumerKey = env('PESAPAL_CONSUMER_KEY', '');
    $config->consumerSecret = env('PESAPAL_CONSUMER_SECRET', '');
    
    // Environment
    $config->sandbox = env('PESAPAL_SANDBOX', true);
    
    // Base URLs
    $config->sandboxUrl = env('PESAPAL_SANDBOX_URL', 'https://cybqa.pesapal.com/pesapalv3');
    $config->liveUrl = env('PESAPAL_LIVE_URL', 'https://pay.pesapal.com/v3');
    
    // API Endpoints
    $config->endpoints = new stdClass();
    $config->endpoints->auth = env('PESAPAL_AUTH_ENDPOINT', '/api/Auth/RequestToken');
    $config->endpoints->registerIpn = env('PESAPAL_REGISTER_IPN_ENDPOINT', '/api/URLSetup/RegisterIPN');
    $config->endpoints->listIpn = env('PESAPAL_LIST_IPN_ENDPOINT', '/api/URLSetup/GetIpnList');
    $config->endpoints->submitOrder = env('PESAPAL_SUBMIT_ORDER_ENDPOINT', '/api/Transactions/SubmitOrderRequest');
    $config->endpoints->transactionStatus = env('PESAPAL_TRANSACTION_STATUS_ENDPOINT', '/api/Transactions/GetTransactionStatus');
    $config->endpoints->refund = env('PESAPAL_REFUND_ENDPOINT', '/api/Transactions/RefundRequest');
    $config->endpoints->cancelOrder = env('PESAPAL_CANCEL_ORDER_ENDPOINT', '/api/Transactions/CancelOrder');
    
    // Default Values
    $config->currency = env('PESAPAL_CURRENCY', 'UGX');
    $config->redirectMode = env('PESAPAL_REDIRECT_MODE', 'PARENT_WINDOW');
    $config->ipnNotificationType = env('PESAPAL_IPN_NOTIFICATION_TYPE', 'POST');
    
    // Application Settings
    $config->appUrl = env('APP_URL', 'http://localhost');
    $config->debug = env('APP_DEBUG', false);
    
    // Backward compatibility aliases
    $config->pesapalConsumerKey = $config->consumerKey;
    $config->pesapalConsumerSecret = $config->consumerSecret;
    $config->businessCurrency = $config->currency;
    
    return $config;
}

// =========================================================================
// RESPONSE HELPERS
// =========================================================================

/**
 * Send a JSON response and exit
 * 
 * Sets proper content-type header, encodes data as JSON,
 * outputs it, and terminates script execution.
 * 
 * @param mixed $data Data to encode as JSON
 * @param int $statusCode HTTP status code (default: 200)
 * @return void
 */
function jsonResponse($data = null, $statusCode = 200)
{
    // Set HTTP status code
    http_response_code($statusCode);
    
    // Set content type header
    header('Content-Type: application/json; charset=utf-8');
    
    // Handle legacy usage where data was passed as func_get_arg
    if ($data === null && func_num_args() > 0) {
        $data = func_get_arg(0);
    }
    
    // Output JSON
    echo json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    exit;
}

/**
 * Send a success JSON response
 * 
 * @param mixed $data Response data
 * @param string $message Success message
 * @return void
 */
function jsonSuccess($data = null, $message = 'Success')
{
    jsonResponse([
        'success' => true,
        'message' => $message,
        'data' => $data
    ], 200);
}

/**
 * Send an error JSON response
 * 
 * @param string $message Error message
 * @param int $statusCode HTTP status code
 * @param mixed $data Additional error data
 * @return void
 */
function jsonError($message = 'An error occurred', $statusCode = 400, $data = null)
{
    $response = [
        'success' => false,
        'message' => $message
    ];
    
    if ($data !== null) {
        $response['data'] = $data;
    }
    
    jsonResponse($response, $statusCode);
}

// =========================================================================
// UTILITY HELPERS
// =========================================================================

/**
 * Debug dump and die
 * 
 * @param mixed ...$vars Variables to dump
 * @return void
 */
function dd(...$vars)
{
    header('Content-Type: text/html; charset=utf-8');
    echo '<pre style="background:#1e1e1e;color:#d4d4d4;padding:15px;font-size:13px;overflow:auto;">';
    foreach ($vars as $var) {
        var_dump($var);
        echo "\n";
    }
    echo '</pre>';
    exit;
}

/**
 * Log a message to file
 * 
 * @param string $message Message to log
 * @param string $level Log level (info, warning, error)
 * @param string|null $file Log file path
 * @return void
 */
function logMessage($message, $level = 'info', $file = null)
{
    if ($file === null) {
        $file = dirname(__DIR__) . '/logs/app.log';
    }
    
    // Ensure directory exists
    $dir = dirname($file);
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }
    
    $timestamp = date('Y-m-d H:i:s');
    $level = strtoupper($level);
    $logLine = "[{$timestamp}] [{$level}] {$message}\n";
    
    file_put_contents($file, $logLine, FILE_APPEND | LOCK_EX);
}

/**
 * Get the application base URL
 * 
 * @return string
 */
function baseUrl()
{
    return env('APP_URL', 'http://localhost');
}

/**
 * Build a full URL from a path
 * 
 * @param string $path Path to append
 * @return string
 */
function url($path = '')
{
    $base = rtrim(baseUrl(), '/');
    $path = ltrim($path, '/');
    return $path ? "{$base}/{$path}" : $base;
}

/**
 * Sanitize a string for safe output
 * 
 * @param string $string Input string
 * @return string
 */
function sanitize($string)
{
    return htmlspecialchars($string, ENT_QUOTES | ENT_HTML5, 'UTF-8');
}

/**
 * Generate a random string
 * 
 * @param int $length Length of string
 * @return string
 */
function randomString($length = 16)
{
    return bin2hex(random_bytes($length / 2));
}
