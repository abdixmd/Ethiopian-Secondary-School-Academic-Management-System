<?php
/**
 * CBE (Commercial Bank of Ethiopia) Payment Gateway Integration
 */

class CBEGateway {
    private $api_url;
    private $merchant_id;
    private $api_key;

    public function __construct() {
        $this->api_url = 'https://api.cbe.com.et/v1/payments'; // Example URL
        $this->merchant_id = 'YOUR_CBE_MERCHANT_ID';
        $this->api_key = 'YOUR_CBE_API_KEY';
    }

    /**
     * Initiate a payment with CBE.
     *
     * @param float $amount The amount.
     * @param string $invoice_id Unique transaction ID.
     * @param string $description A brief description of the payment.
     * @param string $callback_url URL for CBE to send payment status updates.
     * @return array ['success' => bool, 'redirect_url' => string|null, 'message' => string]
     */
    public function createPayment($amount, $invoice_id, $description, $callback_url) {
        $payload = [
            'merchantId' => $this->merchant_id,
            'amount' => $amount,
            'invoiceId' => $invoice_id,
            'description' => $description,
            'callbackUrl' => $callback_url,
        ];

        $headers = [
            'Authorization: Bearer ' . $this->api_key,
            'Content-Type: application/json',
        ];

        // Make the API call (using cURL)
        // $response = $this->makeRequest($this->api_url, $payload, $headers);

        // --- MOCK RESPONSE FOR DEMONSTRATION ---
        $mock_success = true;
        if ($mock_success) {
            return [
                'success' => true,
                'redirect_url' => 'https://cbe.com.et/mock/pay?invoice=' . $invoice_id,
                'message' => 'CBE payment initiated.'
            ];
        } else {
            return [
                'success' => false,
                'redirect_url' => null,
                'message' => 'Failed to initiate CBE payment.'
            ];
        }
    }

    /**
     * Handle the callback from CBE.
     *
     * @param array $data The POST data from CBE's callback.
     * @return array ['success' => bool, 'invoice_id' => string, 'status' => string]
     */
    public function handleCallback(array $data) {
        // Verify the request came from CBE (e.g., by checking a signature or IP address)
        
        if (isset($data['invoiceId']) && isset($data['status'])) {
            return [
                'success' => true,
                'invoice_id' => $data['invoiceId'],
                'status' => $data['status'] // e.g., 'completed', 'failed'
            ];
        }

        return ['success' => false, 'message' => 'Invalid callback data.'];
    }
}
?>