<?php
// =============================================================
// UMDC – PayMongo Configuration
// =============================================================
// Set your PayMongo keys here OR via environment variables.
// Get your keys from: https://dashboard.paymongo.com/developers
// =============================================================
defined('UMDC_APP') or define('UMDC_APP', true);

define('PAYMONGO_SECRET_KEY',      getenv('PAYMONGO_SECRET_KEY')      ?: 'sk_test_REPLACE_WITH_YOUR_KEY');
define('PAYMONGO_PUBLIC_KEY',      getenv('PAYMONGO_PUBLIC_KEY')       ?: 'pk_test_REPLACE_WITH_YOUR_KEY');
define('PAYMONGO_WEBHOOK_SECRET',  getenv('PAYMONGO_WEBHOOK_SECRET')   ?: '');
define('PAYMONGO_API_BASE',        'https://api.paymongo.com/v1');
define('APP_BASE_URL',             getenv('APP_BASE_URL')               ?: 'http://localhost');

// =============================================================
// PayMongo API Helper
// =============================================================
class PayMongo {

    /** POST /v1/{endpoint} with JSON body */
    public static function post(string $endpoint, array $body): array {
        return self::request('POST', $endpoint, $body);
    }

    /** GET /v1/{endpoint} */
    public static function get(string $endpoint): array {
        return self::request('GET', $endpoint);
    }

    private static function request(string $method, string $endpoint, array $body = []): array {
        $url = PAYMONGO_API_BASE . '/' . ltrim($endpoint, '/');

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER     => [
                'Content-Type: application/json',
                'Accept: application/json',
                'Authorization: Basic ' . base64_encode(PAYMONGO_SECRET_KEY . ':'),
            ],
            CURLOPT_TIMEOUT        => 30,
            CURLOPT_SSL_VERIFYPEER => true,
        ]);

        if ($method === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));
        }

        $response  = curl_exec($ch);
        $httpCode  = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($curlError) {
            error_log("PayMongo cURL error: $curlError");
            throw new RuntimeException('Payment service unreachable. Please try again.');
        }

        $data = json_decode($response, true);
        if (!$data) {
            error_log("PayMongo bad JSON ($httpCode): $response");
            throw new RuntimeException('Invalid response from payment service.');
        }

        if (!empty($data['errors'])) {
            $msg = $data['errors'][0]['detail'] ?? 'Payment error';
            error_log("PayMongo API error ($httpCode): $msg");
            throw new RuntimeException($msg);
        }

        return $data;
    }

    /**
     * Create a GCash / Maya payment link (PaymentIntent + PaymentMethod).
     * Returns ['checkout_url', 'payment_intent_id', 'client_key']
     */
    public static function createQRPayment(float $amount, string $description, int $donationId): array {
        // Amount in centavos
        $amountCents = (int)round($amount * 100);

        // 1. Create Payment Intent
        $pi = self::post('payment_intents', [
            'data' => [
                'attributes' => [
                    'amount'               => $amountCents,
                    'payment_method_allowed'=> ['paymaya', 'gcash', 'qrph'],
                    'payment_method_options'=> ['card' => ['request_three_d_secure' => 'any']],
                    'currency'             => 'PHP',
                    'capture_type'         => 'automatic',
                    'description'          => $description,
                    'metadata'             => ['donation_id' => $donationId],
                ],
            ],
        ]);

        $piId      = $pi['data']['id'];
        $clientKey = $pi['data']['attributes']['client_key'];

        return [
            'payment_intent_id' => $piId,
            'client_key'        => $clientKey,
            'amount_cents'      => $amountCents,
        ];
    }

    /**
     * Create a GCash Source (returns redirect_url for QR / redirect flow)
     */
    public static function createGCashSource(float $amount, string $description, int $donationId): array {
        $amountCents = (int)round($amount * 100);
        $base        = rtrim(APP_BASE_URL, '/');

        $source = self::post('sources', [
            'data' => [
                'attributes' => [
                    'amount'      => $amountCents,
                    'currency'    => 'PHP',
                    'type'        => 'gcash',
                    'description' => $description,
                    'redirect'    => [
                        'success' => $base . '/api/paymongo_return.php?status=success&donation_id=' . $donationId,
                        'failed'  => $base . '/api/paymongo_return.php?status=failed&donation_id='  . $donationId,
                    ],
                    'metadata' => ['donation_id' => $donationId],
                ],
            ],
        ]);

        return [
            'source_id'    => $source['data']['id'],
            'checkout_url' => $source['data']['attributes']['redirect']['checkout_url'],
            'qr_code'      => $source['data']['attributes']['redirect']['checkout_url'], // GCash uses redirect
        ];
    }

    /**
     * Create a PayMaya (Maya) Source
     */
    public static function createMayaSource(float $amount, string $description, int $donationId): array {
        $amountCents = (int)round($amount * 100);
        $base        = rtrim(APP_BASE_URL, '/');

        $source = self::post('sources', [
            'data' => [
                'attributes' => [
                    'amount'      => $amountCents,
                    'currency'    => 'PHP',
                    'type'        => 'paymaya',
                    'description' => $description,
                    'redirect'    => [
                        'success' => $base . '/api/paymongo_return.php?status=success&donation_id=' . $donationId,
                        'failed'  => $base . '/api/paymongo_return.php?status=failed&donation_id='  . $donationId,
                    ],
                    'metadata' => ['donation_id' => $donationId],
                ],
            ],
        ]);

        return [
            'source_id'    => $source['data']['id'],
            'checkout_url' => $source['data']['attributes']['redirect']['checkout_url'],
        ];
    }

    /**
     * Create a QR Ph (InstaPay/PESONet) payment link
     */
    public static function createQRPH(float $amount, string $description, int $donationId): array {
        $amountCents = (int)round($amount * 100);
        $base        = rtrim(APP_BASE_URL, '/');

        // QR Ph uses PaymentMethod + PaymentIntent
        $pm = self::post('payment_methods', [
            'data' => [
                'attributes' => [
                    'type' => 'qrph',
                ],
            ],
        ]);

        $pi = self::post('payment_intents', [
            'data' => [
                'attributes' => [
                    'amount'                => $amountCents,
                    'payment_method_allowed'=> ['qrph'],
                    'currency'              => 'PHP',
                    'capture_type'          => 'automatic',
                    'description'           => $description,
                    'metadata'              => ['donation_id' => $donationId],
                ],
            ],
        ]);

        $piId = $pi['data']['id'];
        $ck   = $pi['data']['attributes']['client_key'];

        // Attach payment method
        $attach = self::post("payment_intents/$piId/attach", [
            'data' => [
                'attributes' => [
                    'payment_method' => $pm['data']['id'],
                    'client_key'     => $ck,
                    'return_url'     => $base . '/api/paymongo_return.php?status=success&donation_id=' . $donationId,
                ],
            ],
        ]);

        $qrCode = $attach['data']['attributes']['next_action']['redirect']['url']
               ?? $attach['data']['attributes']['next_action']['display_details']['qr_image']
               ?? '';

        return [
            'payment_intent_id' => $piId,
            'client_key'        => $ck,
            'qr_code_url'       => $qrCode,
            'checkout_url'      => $qrCode,
        ];
    }

    /**
     * Verify webhook signature
     */
    public static function verifyWebhook(string $payload, string $signature): bool {
        if (!PAYMONGO_WEBHOOK_SECRET) return true; // skip if not set
        $expected = hash_hmac('sha256', $payload, PAYMONGO_WEBHOOK_SECRET);
        return hash_equals($expected, $signature);
    }
}
