<?php
/**
 * CBE Birr Payment Gateway Integration
 */

class CBEBirrGateway {
    private $api_url;
    private $merchant_code;
    private $api_token;

    public function __construct() {
        $this->api_url = 'https://api.cbebirr.com.et/v1/merchant-payment'; // Example URL
        $this->merchant_code = 'YOUR_CBE_BIRR_MERCHANT_CODE';
        $this->api_token = 'YOUR_CBE_BIRR_API_TOKEN';
    }

    /**
     * Request a payment from a customer's CBE Birr account.
     * This typically involves a push notification to the user's phone.
     *
     * @param string $customer_phone The customer's phone number.
     * @param float $amount The amount to be paid.
     * @param string $invoice_id A unique identifier for the transaction.
     * @param string $description A brief description.
     * @return array ['success' => bool, 'transaction_id' => string|null, 'message' => string]
     */
    public function requestPayment($customer_phone, $amount, $invoice_id, $description) {
        $payload = [
            'merchantCode' => $this->merchant_code,
            'customerPhone' => $customer_phone,
            'amount' => $amount,
            'invoiceId' => $invoice_id,
            'description' => $description,
        ];

        $headers = [
            'Authorization: Bearer ' . $this->api_token,
            'Content-Type: application/json',
        ];

        // Make the API call (using cURL)
        // $response = $this->makeRequest($this->api_url, $payload, $headers);

        // --- MOCK RESPONSE FOR DEMONSTRATION ---
        $mock_success = true;
        if ($mock_success) {
            // The API would return a transaction ID to check the status later.
            $transaction_id = 'CBEBIRR_' . time();
            return [
                'success' => true,
                'transaction_id' => $transaction_id,
                'message' => 'Payment request sent to customer. Waiting for confirmation.'
            ];
        } else {
            return [
                'success' => false,
                'transaction_id' => null,
                'message' => 'Failed to send payment request.'
            ];
        }
    }

    /**
     * Check the status of a previously initiated transaction.
     *
     * @param string $transaction_id The ID returned by requestPayment.
     * @return array ['success' => bool, 'status' => string|null]
     */
    public function checkPaymentStatus($transaction_id) {
        $url = $this->api_url . '/status/' . $transaction_id;
        $headers = ['Authorization: Bearer ' . $this->api_token];

        // Make the API call (using cURL GET request)
        // $response = $this->makeRequest($url, null, $headers, 'GET');

        // --- MOCK RESPONSE FOR DEMONSTRATION ---
        return [
            'success' => true,
            'status' => 'completed' // Could be 'pending', 'completed', 'failed'
        ];
    }
}
?>