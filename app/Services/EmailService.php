<?php

namespace App\Services;

require_once __DIR__ . '/../../config/database.php';

/**
 * Email Service
 * Handles sending emails with attachments
 */
class EmailService {
    private $db;
    private $fromEmail;
    private $fromName;
    private $smtpHost;
    private $smtpPort;
    private $smtpUser;
    private $smtpPass;
    private $smtpEncryption;
    
    public function __construct() {
        $this->db = \Database::getInstance()->getConnection();
        $this->loadSettings();
    }
    
    /**
     * Load email settings from database
     */
    private function loadSettings() {
        try {
            $query = $this->db->query("SELECT setting_key, setting_value FROM system_settings");
            $settings = $query->fetchAll(\PDO::FETCH_KEY_PAIR);
            
            $this->fromEmail = $settings['mail_from_address'] ?? getenv('MAIL_FROM_ADDRESS') ?: 'noreply@sellapp.store';
            $this->fromName = $settings['mail_from_name'] ?? getenv('MAIL_FROM_NAME') ?: 'SellApp System';
            $this->smtpHost = $settings['mail_host'] ?? getenv('MAIL_HOST') ?: 'mail.sellapp.store';
            $this->smtpPort = (int)($settings['mail_port'] ?? getenv('MAIL_PORT') ?: 465);
            $this->smtpUser = $settings['mail_username'] ?? getenv('MAIL_USERNAME') ?: 'noreply@sellapp.store';
            $this->smtpPass = $settings['mail_password'] ?? getenv('MAIL_PASSWORD') ?: '';
            // Use SSL for port 465, TLS for port 587
            $this->smtpEncryption = $this->smtpPort == 465 ? 'ssl' : ($settings['mail_encryption'] ?? getenv('MAIL_ENCRYPTION') ?: 'tls');
        } catch (\Exception $e) {
            error_log("Error loading email settings: " . $e->getMessage());
            // Use defaults
            $this->fromEmail = 'noreply@sellapp.store';
            $this->fromName = 'SellApp System';
            $this->smtpHost = 'mail.sellapp.store';
            $this->smtpPort = 465;
            $this->smtpUser = 'noreply@sellapp.store';
            $this->smtpEncryption = 'ssl';
        }
    }
    
    /**
     * Send email with attachment
     * 
     * @param string $to Email address
     * @param string $subject Subject line
     * @param string $message Email body
     * @param string|null $attachmentPath Path to file to attach
     * @param string|null $attachmentName Name for the attachment
     * @return array ['success' => bool, 'message' => string]
     */
    public function sendEmail($to, $subject, $message, $attachmentPath = null, $attachmentName = null) {
        try {
            // Use PHPMailer if available, otherwise fall back to mail()
            if (class_exists('\PHPMailer\PHPMailer\PHPMailer')) {
                return $this->sendWithPHPMailer($to, $subject, $message, $attachmentPath, $attachmentName);
            } else {
                return $this->sendWithMail($to, $subject, $message, $attachmentPath, $attachmentName);
            }
        } catch (\Exception $e) {
            error_log("Email sending error: " . $e->getMessage());
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Send email using PHPMailer
     */
    private function sendWithPHPMailer($to, $subject, $message, $attachmentPath = null, $attachmentName = null) {
        $mail = new \PHPMailer\PHPMailer\PHPMailer(true);
        
        try {
            // Server settings
            if (!empty($this->smtpUser)) {
                $mail->isSMTP();
                $mail->Host = $this->smtpHost;
                $mail->SMTPAuth = true;
                $mail->Username = $this->smtpUser;
                $mail->Password = $this->smtpPass;
                
                // Set encryption based on port
                if ($this->smtpPort == 465) {
                    $mail->SMTPSecure = 'ssl';
                } elseif ($this->smtpPort == 587) {
                    $mail->SMTPSecure = 'tls';
                } else {
                    $mail->SMTPSecure = $this->smtpEncryption;
                }
                
                $mail->Port = $this->smtpPort;
                $mail->SMTPOptions = [
                    'ssl' => [
                        'verify_peer' => false,
                        'verify_peer_name' => false,
                        'allow_self_signed' => true
                    ]
                ];
            } else {
                $mail->isMail();
            }
            
            // Recipients
            $mail->setFrom($this->fromEmail, $this->fromName);
            $mail->addAddress($to);
            
            // Content
            $mail->isHTML(true);
            $mail->Subject = $subject;
            $mail->Body = $message;
            $mail->AltBody = strip_tags($message);
            
            // Attachment
            if ($attachmentPath && file_exists($attachmentPath)) {
                $mail->addAttachment($attachmentPath, $attachmentName ?: basename($attachmentPath));
            }
            
            $mail->send();
            
            return [
                'success' => true,
                'message' => 'Email sent successfully'
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => "PHPMailer Error: {$mail->ErrorInfo}"
            ];
        }
    }
    
    /**
     * Send email using PHP mail() function
     */
    private function sendWithMail($to, $subject, $message, $attachmentPath = null, $attachmentName = null) {
        $boundary = md5(time());
        $headers = [];
        $headers[] = "MIME-Version: 1.0";
        $headers[] = "From: {$this->fromName} <{$this->fromEmail}>";
        $headers[] = "Reply-To: {$this->fromEmail}";
        $headers[] = "Content-Type: multipart/mixed; boundary=\"{$boundary}\"";
        
        $body = "--{$boundary}\r\n";
        $body .= "Content-Type: text/html; charset=UTF-8\r\n";
        $body .= "Content-Transfer-Encoding: 7bit\r\n\r\n";
        $body .= $message . "\r\n";
        
        // Add attachment if provided
        if ($attachmentPath && file_exists($attachmentPath)) {
            $fileContent = file_get_contents($attachmentPath);
            $fileContent = chunk_split(base64_encode($fileContent));
            $fileName = $attachmentName ?: basename($attachmentPath);
            
            $body .= "--{$boundary}\r\n";
            $body .= "Content-Type: application/zip; name=\"{$fileName}\"\r\n";
            $body .= "Content-Transfer-Encoding: base64\r\n";
            $body .= "Content-Disposition: attachment; filename=\"{$fileName}\"\r\n\r\n";
            $body .= $fileContent . "\r\n";
        }
        
        $body .= "--{$boundary}--";
        
        $result = mail($to, $subject, $body, implode("\r\n", $headers));
        
        return [
            'success' => $result,
            'message' => $result ? 'Email sent successfully' : 'Failed to send email'
        ];
    }
}

