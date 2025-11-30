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
     * @param string $emailType Type of email (automatic, manual, test, monthly_report, backup, notification)
     * @param int|null $companyId Company ID if applicable
     * @param int|null $userId User ID if applicable
     * @param string|null $userRole User role if applicable
     * @return array ['success' => bool, 'message' => string, 'log_id' => int|null]
     */
    public function sendEmail($to, $subject, $message, $attachmentPath = null, $attachmentName = null, $emailType = 'manual', $companyId = null, $userId = null, $userRole = null) {
        // Log email attempt
        $logId = $this->logEmail($to, $subject, $emailType, 'pending', $companyId, $userId, $userRole);
        
        try {
            // Use PHPMailer if available, otherwise fall back to mail()
            if (class_exists('\PHPMailer\PHPMailer\PHPMailer')) {
                $result = $this->sendWithPHPMailer($to, $subject, $message, $attachmentPath, $attachmentName);
            } else {
                $result = $this->sendWithMail($to, $subject, $message, $attachmentPath, $attachmentName);
            }
            
            // Update log with result
            $this->updateEmailLog($logId, $result['success'] ? 'sent' : 'failed', $result['success'] ? null : $result['message']);
            
            $result['log_id'] = $logId;
            return $result;
        } catch (\Exception $e) {
            error_log("Email sending error: " . $e->getMessage());
            
            // Update log with error
            $this->updateEmailLog($logId, 'failed', $e->getMessage());
            
            return [
                'success' => false,
                'message' => $e->getMessage(),
                'log_id' => $logId
            ];
        }
    }
    
    /**
     * Log email attempt
     */
    private function logEmail($recipient, $subject, $emailType, $status, $companyId = null, $userId = null, $userRole = null) {
        try {
            $stmt = $this->db->prepare("
                INSERT INTO email_logs 
                (recipient_email, subject, email_type, status, company_id, user_id, role, created_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
            ");
            $stmt->execute([$recipient, $subject, $emailType, $status, $companyId, $userId, $userRole]);
            return $this->db->lastInsertId();
        } catch (\Exception $e) {
            error_log("Error logging email: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Update email log with result
     */
    private function updateEmailLog($logId, $status, $errorMessage = null) {
        if (!$logId) {
            return;
        }
        
        try {
            $stmt = $this->db->prepare("
                UPDATE email_logs 
                SET status = ?, error_message = ?, sent_at = NOW()
                WHERE id = ?
            ");
            $stmt->execute([$status, $errorMessage, $logId]);
        } catch (\Exception $e) {
            error_log("Error updating email log: " . $e->getMessage());
        }
    }
    
    /**
     * Get SellApp logo HTML for emails
     */
    private function getLogoHtml($baseUrl = null) {
        if (!$baseUrl) {
            // Try to get base URL from settings or construct it
            $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https://' : 'http://';
            $host = $_SERVER['HTTP_HOST'] ?? 'sellapp.store';
            $baseUrl = $protocol . $host;
        }
        
        // Try to use favicon as logo, or create a simple logo HTML
        $logoUrl = $baseUrl . '/assets/images/favicon.svg';
        
        return '
        <div style="text-align: center; margin-bottom: 30px; padding: 20px 0;">
            <div style="display: inline-block; background: linear-gradient(135deg, #3b82f6 0%, #1e40af 100%); padding: 15px 25px; border-radius: 12px; box-shadow: 0 4px 6px rgba(0,0,0,0.1);">
                <h1 style="margin: 0; color: white; font-size: 28px; font-weight: bold; letter-spacing: -0.5px;">
                    📱 SellApp
                </h1>
            </div>
        </div>
        ';
    }
    
    /**
     * Wrap email message with SellApp branding
     */
    private function wrapEmailWithBranding($message) {
        $logoHtml = $this->getLogoHtml();
        
        // Check if message already has full HTML structure
        if (stripos($message, '<!DOCTYPE') !== false || stripos($message, '<html') !== false) {
            // Message already has full HTML, just add logo at the top
            $message = preg_replace('/(<body[^>]*>)/i', '$1' . $logoHtml, $message);
            return $message;
        }
        
        // Wrap message in full HTML structure with logo
        return '
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
        </head>
        <body style="font-family: Arial, sans-serif; line-height: 1.6; color: #333; margin: 0; padding: 0; background-color: #f9fafb;">
            <div style="max-width: 600px; margin: 0 auto; background: white; padding: 0;">
                ' . $logoHtml . '
                <div style="padding: 0 30px 30px 30px;">
                    ' . $message . '
                </div>
                <div style="background: #f3f4f6; padding: 20px; text-align: center; border-top: 1px solid #e5e7eb; margin-top: 30px;">
                    <p style="margin: 0; color: #6b7280; font-size: 12px;">
                        This email was sent by <strong>SellApp</strong> - Multi-Tenant Phone Management System
                    </p>
                    <p style="margin: 5px 0 0 0; color: #9ca3af; font-size: 11px;">
                        © ' . date('Y') . ' SellApp. All rights reserved.
                    </p>
                </div>
            </div>
        </body>
        </html>
        ';
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
            // Wrap message with SellApp branding
            $brandedMessage = $this->wrapEmailWithBranding($message);
            $mail->Body = $brandedMessage;
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
        
        // Wrap message with SellApp branding
        $brandedMessage = $this->wrapEmailWithBranding($message);
        
        $body = "--{$boundary}\r\n";
        $body .= "Content-Type: text/html; charset=UTF-8\r\n";
        $body .= "Content-Transfer-Encoding: 7bit\r\n\r\n";
        $body .= $brandedMessage . "\r\n";
        
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

