<?php
/**
 * SMSService Class
 * Handles sending SMS notifications via a gateway API
 */
class SMSService {
    private $api_key;
    private $api_url;
    private $sender_id;
    private $enabled;

    public function __construct() {
        // Load configuration
        // In a real app, these would come from a config file or DB
        $this->api_key = defined('SMS_API_KEY') ? SMS_API_KEY : '';
        $this->api_url = defined('SMS_API_URL') ? SMS_API_URL : 'https://api.sms-provider.com/send';
        $this->sender_id = defined('SMS_SENDER_ID') ? SMS_SENDER_ID : 'HSMS';
        $this->enabled = defined('SMS_ENABLED') ? SMS_ENABLED : false;
    }

    /**
     * Send a single SMS
     *
     * @param string $to Phone number (e.g., +251911...)
     * @param string $message Message content
     * @return array Response with status and message
     */
    public function send($to, $message) {
        if (!$this->enabled) {
            return ['success' => false, 'message' => 'SMS service is disabled'];
        }

        // Format phone number if needed (e.g., ensure +251 prefix)
        $to = $this->formatPhoneNumber($to);

        // Prepare payload
        $data = [
            'api_key' => $this->api_key,
            'to' => $to,
            'sender' => $this->sender_id,
            'message' => $message
        ];

        // Send request (Mock implementation)
        // In production, use curl to post to the API
        // $response = $this->makeRequest($this->api_url, $data);

        // Mock success for demo
        $success = true;

        if ($success) {
            $this->logSMS($to, $message, 'sent');
            return ['success' => true, 'message' => 'SMS sent successfully'];
        } else {
            $this->logSMS($to, $message, 'failed');
            return ['success' => false, 'message' => 'Failed to send SMS'];
        }
    }

    /**
     * Send Bulk SMS
     *
     * @param array $recipients Array of phone numbers
     * @param string $message Message content
     * @return array Summary of sent/failed
     */
    public function sendBulk($recipients, $message) {
        if (!$this->enabled) {
            return ['success' => false, 'message' => 'SMS service is disabled'];
        }

        $sent = 0;
        $failed = 0;

        foreach ($recipients as $to) {
            $result = $this->send($to, $message);
            if ($result['success']) {
                $sent++;
            } else {
                $failed++;
            }
        }

        return [
            'success' => true,
            'total' => count($recipients),
            'sent' => $sent,
            'failed' => $failed
        ];
    }

    /**
     * Format phone number to international standard
     */
    private function formatPhoneNumber($phone) {
        // Remove non-numeric characters
        $phone = preg_replace('/[^0-9]/', '', $phone);

        // Handle Ethiopian numbers
        if (substr($phone, 0, 1) == '0') {
            $phone = '251' . substr($phone, 1);
        } elseif (substr($phone, 0, 3) != '251') {
            $phone = '251' . $phone;
        }

        return '+' . $phone;
    }

    /**
     * Log SMS to database
     */
    private function logSMS($to, $message, $status) {
        // Assuming global DB connection or passed in constructor
        // $conn = getDBConnection();
        // $stmt = $conn->prepare("INSERT INTO sms_logs (recipient, message, status, created_at) VALUES (?, ?, ?, NOW())");
        // $stmt->bind_param("sss", $to, $message, $status);
        // $stmt->execute();

        // For now, just error_log
        error_log("SMS [$status] to $to: $message");
    }

    /**
     * Send Attendance Alert
     */
    public function sendAttendanceAlert($parent_phone, $student_name, $status, $date) {
        $message = "HSMS Alert: Your child $student_name was marked $status on $date. Please contact the school if you have questions.";
        return $this->send($parent_phone, $message);
    }

    /**
     * Send Exam Result Notification
     */
    public function sendResultNotification($parent_phone, $student_name, $exam_name) {
        $message = "HSMS: Results for $exam_name have been published. Please login to the portal to view $student_name's performance.";
        return $this->send($parent_phone, $message);
    }
}


?>