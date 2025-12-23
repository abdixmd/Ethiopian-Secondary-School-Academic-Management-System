<?php
/**
 * EthioTelecom SMS Gateway Integration
 */

class EthioTelecomSMSGateway {
    private $api_url;
    private $api_key;
    private $sender_name;

    public function __construct() {
        // Load credentials from a secure config file or environment variables
        $this->api_url = 'https://sms-api.ethiotelecom.et/v1/send'; // Example URL
        $this->api_key = 'YOUR_ETHIOTELECOM_API_KEY';
        $this->sender_name = 'HSMS'; // Your registered sender name
    }

    /**
     * Send a single SMS message.
     *
     * @param string $to The recipient's phone number.
     * @param string $message The message content.
     * @return array ['success' => bool, 'message' => string]
     */
    public function send($to, $message) {
        $to = $this->formatPhoneNumber($to);

        $payload = [
            'recipient' => $to,
            'message' => $message,
            'sender' => $this->sender_name,
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
            return ['success' => true, 'message' => 'SMS sent successfully.'];
        } else {
            return ['success' => false, 'message' => 'Failed to send SMS.'];
        }
    }

    /**
     * Send SMS to multiple recipients.
     */
    public function sendBulk($recipients, $message) {
        // Some APIs support bulk sending in a single request.
        // If not, we can just loop through and send individually.
        $sent_count = 0;
        foreach ($recipients as $recipient) {
            $result = $this->send($recipient, $message);
            if ($result['success']) {
                $sent_count++;
            }
        }
        return ['success' => true, 'sent' => $sent_count, 'total' => count($recipients)];
    }

    /**
     * Formats a phone number to the required E.164 standard.
     */
    private function formatPhoneNumber($phone) {
        // Remove non-numeric characters
        $phone = preg_replace('/[^0-9]/', '', $phone);
        
        // Assumes Ethiopian numbers. If it starts with 09, replace 0 with 251.
        if (strlen($phone) == 10 && substr($phone, 0, 2) == '09') {
            return '251' . substr($phone, 1);
        }
        // If it's 9 digits (missing the leading 0), prepend 251.
        if (strlen($phone) == 9) {
            return '251' . $phone;
        }
        // If it already starts with 251, it's fine.
        if (substr($phone, 0, 3) == '251') {
            return $phone;
        }
        
        return $phone; // Return as is if format is unknown
    }
}
?>