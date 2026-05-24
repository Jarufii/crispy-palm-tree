<?php
// services/PayMongoService.php

class PayMongoService {
    private $secretKey;
    private $publicKey;
    private $baseUrl;
    private $mode;
    
    public function __construct() {
        // Load environment variables from .env file
        $this->loadEnv();
        
        // Get credentials from environment
        $this->secretKey = getenv('PAYMONGO_SECRET_KEY');
        $this->publicKey = getenv('PAYMONGO_PUBLIC_KEY');
        $this->mode = getenv('PAYMONGO_MODE') ?: 'sandbox';
        $this->baseUrl = 'https://api.paymongo.com/v1';
        
        // Debug: Check if secret key is loaded
        if (empty($this->secretKey)) {
            error_log("PayMongo Error: Secret key not found in environment");
            throw new Exception('PayMongo secret key not configured. Please check your .env file.');
        }
        
        error_log("PayMongo Service initialized. Mode: " . $this->mode);
    }
    
    /**
     * Load environment variables from .env file
     */
    private function loadEnv() {
        $envFile = __DIR__ . '/../.env';
        if (file_exists($envFile)) {
            $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            foreach ($lines as $line) {
                $line = trim($line);
                // Skip comments and empty lines
                if (strpos($line, '#') === 0 || empty($line)) {
                    continue;
                }
                
                if (strpos($line, '=') !== false) {
                    list($key, $value) = explode('=', $line, 2);
                    $key = trim($key);
                    $value = trim($value);
                    
                    // Remove quotes if present
                    $value = trim($value, '"\'');
                    
                    putenv("$key=$value");
                    $_ENV[$key] = $value;
                }
            }
        } else {
            error_log("PayMongo Warning: .env file not found at " . __DIR__ . '/../.env');
        }
    }
    
    /**
     * Create a payment link for GCash/PayMaya
     */
    public function createPaymentLink($amount, $description, $successUrl, $failedUrl, $referenceNumber = null, $paymentMethod = 'gcash') {
        // Convert amount to centavos (PayMongo requires amount in smallest currency unit)
        $amountInCentavos = intval($amount * 100);
        
        $payload = [
            'data' => [
                'attributes' => [
                    'amount' => $amountInCentavos,
                    'currency' => 'PHP',
                    'description' => $description,
                    'payment_method_types' => [$paymentMethod],
                    'success_url' => $successUrl,
                    'failed_url' => $failedUrl,
                    'send_email_receipt' => true,
                    'show_description' => true,
                    'show_line_items' => true
                ]
            ]
        ];
        
        // Add reference number if provided
        if ($referenceNumber) {
            $payload['data']['attributes']['reference_number'] = $referenceNumber;
        }
        
        // Log the request for debugging
        error_log("PayMongo Request: " . json_encode($payload));
        
        $ch = curl_init();
        
        curl_setopt($ch, CURLOPT_URL, $this->baseUrl . '/links');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Authorization: Basic ' . base64_encode($this->secretKey . ':')
        ]);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);
        
        // Log the response
        error_log("PayMongo Response Code: " . $httpCode);
        error_log("PayMongo Response: " . $response);
        
        if ($curlError) {
            error_log("PayMongo CURL Error: " . $curlError);
            return [
                'success' => false,
                'message' => 'Connection error: ' . $curlError
            ];
        }
        
        if ($httpCode === 200 || $httpCode === 201) {
            $result = json_decode($response, true);
            if (isset($result['data'])) {
                return [
                    'success' => true,
                    'checkout_url' => $result['data']['attributes']['checkout_url'],
                    'payment_link_id' => $result['data']['id'],
                    'reference_number' => $result['data']['attributes']['reference_number'] ?? $referenceNumber
                ];
            }
        }
        
        // Parse error response
        $errorData = json_decode($response, true);
        $errorMessage = 'Failed to create payment link';
        
        if (isset($errorData['errors'][0]['detail'])) {
            $errorMessage = $errorData['errors'][0]['detail'];
        } elseif (isset($errorData['errors'][0]['title'])) {
            $errorMessage = $errorData['errors'][0]['title'];
        }
        
        return [
            'success' => false,
            'message' => $errorMessage,
            'http_code' => $httpCode
        ];
    }
    
    /**
     * Get payment link details and status
     */
    public function getPaymentLink($linkId) {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $this->baseUrl . '/links/' . $linkId);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Basic ' . base64_encode($this->secretKey . ':')
        ]);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        error_log("PayMongo Get Link Response Code: " . $httpCode);
        
        if ($httpCode === 200) {
            $result = json_decode($response, true);
            if (isset($result['data'])) {
                return [
                    'success' => true,
                    'status' => $result['data']['attributes']['status'],
                    'amount' => $result['data']['attributes']['amount'] / 100,
                    'paid_at' => $result['data']['attributes']['paid_at'] ?? null,
                    'reference_number' => $result['data']['attributes']['reference_number'] ?? null,
                    'checkout_url' => $result['data']['attributes']['checkout_url'] ?? null
                ];
            }
        }
        
        return [
            'success' => false,
            'message' => 'Failed to retrieve payment link',
            'http_code' => $httpCode
        ];
    }
    
    /**
     * Get available payment methods
     */
    public function getPaymentMethods() {
        return [
            'gcash' => 'GCash',
            'paymaya' => 'PayMaya',
            'card' => 'Credit/Debit Card'
        ];
    }
}