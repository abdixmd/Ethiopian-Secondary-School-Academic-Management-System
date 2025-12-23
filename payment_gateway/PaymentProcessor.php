<?php
require_once __DIR__ . '/telebirr/TelebirrClient.php';
require_once __DIR__ . '/cbe_birr/CBEBirrClient.php';

class PaymentProcessor {
    private $gateway;

    /**
     * @param string $provider The payment provider to use ('telebirr', 'cbe_birr').
     * @throws Exception If the provider is not supported.
     */
    public function __construct($provider) {
        switch ($provider) {
            case 'telebirr':
                $this->gateway = new TelebirrClient();
                break;
            case 'cbe_birr':
                $this->gateway = new CBEBirrClient();
                break;
            default:
                throw new Exception("Unsupported payment gateway: " . $provider);
        }
    }

    /**
     * Initiate a payment transaction.
     *
     * @param float $amount The amount to be paid.
     * @param string $invoice_id A unique identifier for the transaction.
     * @param string $redirect_url The URL to return to after payment.
     * @return array The response from the payment gateway.
     */
    public function initiatePayment($amount, $invoice_id, $redirect_url) {
        return $this->gateway->createPayment($amount, $invoice_id, $redirect_url);
    }

    /**
     * Verify a payment transaction.
     *
     * @param string $transaction_id The transaction ID to verify.
     * @return array The verification response.
     */
    public function verifyPayment($transaction_id) {
        return $this->gateway->verifyTransaction($transaction_id);
    }
}
?>