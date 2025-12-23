<?php
/**
 * EmailService Class
 * Handles sending emails using PHP's mail() function or SMTP
 */
class EmailService {
    private $from_email;
    private $from_name;
    private $use_smtp;
    
    public function __construct() {
        // Load configuration from settings or constants
        $this->from_email = defined('MAIL_FROM') ? MAIL_FROM : 'noreply@hsms.et';
        $this->from_name = defined('MAIL_NAME') ? MAIL_NAME : 'HSMS Ethiopia';
        $this->use_smtp = defined('USE_SMTP') ? USE_SMTP : false;
    }
    
    /**
     * Send a basic email
     * 
     * @param string $to Recipient email
     * @param string $subject Email subject
     * @param string $message Email body (HTML)
     * @param array $attachments Array of file paths to attach (optional)
     * @return bool True on success, false on failure
     */
    public function send($to, $subject, $message, $attachments = []) {
        if ($this->use_smtp) {
            return $this->sendSMTP($to, $subject, $message, $attachments);
        } else {
            return $this->sendNative($to, $subject, $message, $attachments);
        }
    }
    
    /**
     * Send email using PHP's native mail() function
     */
    private function sendNative($to, $subject, $message, $attachments = []) {
        $boundary = md5(time());
        
        // Headers
        $headers = "From: {$this->from_name} <{$this->from_email}>\r\n";
        $headers .= "Reply-To: {$this->from_email}\r\n";
        $headers .= "MIME-Version: 1.0\r\n";
        $headers .= "Content-Type: multipart/mixed; boundary=\"{$boundary}\"\r\n";
        
        // Body
        $body = "--{$boundary}\r\n";
        $body .= "Content-Type: text/html; charset=UTF-8\r\n";
        $body .= "Content-Transfer-Encoding: 7bit\r\n\r\n";
        $body .= $this->wrapTemplate($message) . "\r\n\r\n";
        
        // Attachments
        if (!empty($attachments)) {
            foreach ($attachments as $file) {
                if (file_exists($file)) {
                    $filename = basename($file);
                    $content = chunk_split(base64_encode(file_get_contents($file)));
                    
                    $body .= "--{$boundary}\r\n";
                    $body .= "Content-Type: application/octet-stream; name=\"{$filename}\"\r\n";
                    $body .= "Content-Description: {$filename}\r\n";
                    $body .= "Content-Disposition: attachment; filename=\"{$filename}\"; size=" . filesize($file) . ";\r\n";
                    $body .= "Content-Transfer-Encoding: base64\r\n\r\n";
                    $body .= $content . "\r\n\r\n";
                }
            }
        }
        
        $body .= "--{$boundary}--";
        
        return mail($to, $subject, $body, $headers);
    }
    
    /**
     * Send email using SMTP (Placeholder for PHPMailer or similar integration)
     */
    private function sendSMTP($to, $subject, $message, $attachments = []) {
        // In a real production environment, you would use PHPMailer or SwiftMailer here.
        // For this implementation, we'll fallback to native mail or log the attempt.
        error_log("SMTP sending not implemented. Falling back to native mail for: $to");
        return $this->sendNative($to, $subject, $message, $attachments);
    }
    
    /**
     * Wrap message in a standard HTML template
     */
    private function wrapTemplate($content) {
        $year = date('Y');
        return "
        <!DOCTYPE html>
        <html>
        <head>
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .header { background-color: #4e73df; color: white; padding: 15px; text-align: center; border-radius: 5px 5px 0 0; }
                .content { padding: 20px; border: 1px solid #ddd; border-top: none; background: #fff; }
                .footer { font-size: 12px; text-align: center; margin-top: 20px; color: #777; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <h2>{$this->from_name}</h2>
                </div>
                <div class='content'>
                    {$content}
                </div>
                <div class='footer'>
                    &copy; {$year} {$this->from_name}. All rights reserved.<br>
                    This is an automated message, please do not reply directly.
                </div>
            </div>
        </body>
        </html>
        ";
    }
    
    /**
     * Send Welcome Email
     */
    public function sendWelcomeEmail($user) {
        $subject = "Welcome to HSMS Ethiopia";
        $message = "
            <h3>Hello {$user['full_name']},</h3>
            <p>Welcome to the High School Management System. Your account has been successfully created.</p>
            <p><strong>Username:</strong> {$user['username']}</p>
            <p>Please contact the administrator for your initial password if it wasn't provided.</p>
            <p><a href='" . $this->getBaseUrl() . "/login.php'>Click here to login</a></p>
        ";
        return $this->send($user['email'], $subject, $message);
    }
    
    /**
     * Send Password Reset Email
     */
    public function sendPasswordReset($email, $token) {
        $link = $this->getBaseUrl() . "/reset_password.php?token=" . $token;
        $subject = "Password Reset Request";
        $message = "
            <h3>Password Reset</h3>
            <p>We received a request to reset your password. Click the link below to proceed:</p>
            <p><a href='{$link}'>Reset Password</a></p>
            <p>If you didn't request this, please ignore this email.</p>
            <p>This link expires in 1 hour.</p>
        ";
        return $this->send($email, $subject, $message);
    }
    
    /**
     * Helper to get base URL
     */
    private function getBaseUrl() {
        $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http";
        $host = $_SERVER['HTTP_HOST'];
        // Assuming the script is in a subdirectory, adjust as needed
        return "$protocol://$host/HSMS";
    }
}
?>