<?php
/**
 * Telebirr Payment Gateway Integration
 */

class TelebirrGateway {
    private $api_url;
    private $app_key;
    private $app_id;
    private $short_code;

    public function __construct() {
        // Load credentials from a secure config file or environment variables
        $this->api_url = 'https://api.telebirr.com/v1/payment'; // Example URL
        $this->app_key = 'YOUR_TELEBIRR_APP_KEY';
        $this->app_id = 'YOUR_TELEBIRR_APP_ID';
        $this->short_code = 'YOUR_TELEBIRR_SHORT_CODE';
    }

    /**
     * Create a payment request and return the payment URL.
     *
     * @param float $amount The amount to be paid.
     * @param string $invoice_id A unique identifier for the transaction.
     * @param string $notify_url The URL Telebirr will send a notification to.
     * @param string $return_url The URL the user will be redirected to after payment.
     * @return array ['success' => bool, 'payment_url' => string|null, 'message' => string]
     */
    public function createPayment($amount, $invoice_id, $notify_url, $return_url) {
        $nonce = bin2hex(random_bytes(16));
        $timestamp = time();

        $payload = [
            'appId' => $this->app_id,
            'appKey' => $this->app_key,
            'nonce' => $nonce,
            'notifyUrl' => $notify_url,
            'outTradeNo' => $invoice_id,
            'receiveName' => 'HSMS Ethiopia',
            'returnUrl' => $return_url,
            'shortCode' => $this->short_code,
            'subject' => 'School Fee Payment',
            'timeoutExpress' => '30m',
            'timestamp' => $timestamp,
            'totalAmount' => $amount,
        ];

        // Sign the payload
        $payload['sign'] = $this->generateSignature($payload);

        // Make the API call (using cURL)
        // $response = $this->makeRequest($this->api_url, $payload);
        
        // --- MOCK RESPONSE FOR DEMONSTRATION ---
        $mock_success = true;
        if ($mock_success) {
            return [
                'success' => true,
                'payment_url' => 'https://telebirr.com/mock/payment?trade_no=' . $invoice_id,
                'message' => 'Payment request created successfully.'
            ];
        } else {
            return [
                'success' => false,
                'payment_url' => null,
                'message' => 'Failed to create payment request.'
            ];
        }
    }

    /**
     * Generate the signature for the payload.
     * Telebirr requires a specific way of sorting and concatenating parameters.
     */
    private function generateSignature(array $params) {
        // 1. Remove 'sign' from the array
        unset($params['sign']);

        // 2. Sort by key
        ksort($params);

        // 3. Concatenate into a string "key1=value1&key2=value2..."
        $string_to_sign = http_build_query($params);

        // 4. Sign with your private key (this is a simplified example)
        // In reality, you'd use something like:
        // openssl_sign($string_to_sign, $signature, 'YOUR_PRIVATE_KEY', OPENSSL_ALGO_SHA256);
        // return base64_encode($signature);

        return hash('sha256', $string_to_sign . '&key=' . $this->app_key); // Simplified hash for demo
    }

    /**
     * Verify the signature of an incoming notification from Telebirr.
     */
    public function verifyNotification(array $notification_data) {
        $received_sign = $notification_data['sign'];
        $expected_sign = $this->generateSignature($notification_data);

        return $received_sign === $expected_sign;
    }
}
?>