<?php
/**
 * Email Service
 * Handles email sending via Brevo (Sendinblue) and Amazon SES
 */

namespace LagunaPartners;

class EmailService {
    private $db;
    private $config;
    private $provider; // 'brevo' or 'ses'

    public function __construct() {
        $this->db = Database::getInstance();
        $this->config = require __DIR__ . '/../config/config.php';
        $this->provider = $_ENV['EMAIL_PROVIDER'] ?? 'brevo';
    }

    /**
     * Send OTP email
     */
    public function sendOTP($email, $otp) {
        $template = $this->getTemplate('otp_login');
        
        $subject = $template['subject'];
        $body = $this->replaceVariables($template['body'], [
            'otp_code' => $otp,
            'user_email' => $email
        ]);

        return $this->send($email, $subject, $body);
    }

    /**
     * Send vendor PO update notification to buyer
     */
    public function sendVendorPOUpdate($buyerEmail, $poData, $changes) {
        $template = $this->getTemplate('vendor_po_update');
        
        $variables = [
            'po_number' => $poData['tran_id'],
            'vendor_name' => $poData['vendor_name'],
            'total_amount' => '$' . number_format($poData['total_amount'], 2),
            'status' => $this->getStatusText($poData['status']),
            'changes' => $this->formatChanges($changes),
            'updated_fields' => implode(', ', array_keys($changes)),
            'portal_link' => $this->getPortalLink('po', $poData['id'])
        ];

        $subject = $this->replaceVariables($template['subject'], $variables);
        $body = $this->replaceVariables($template['body'], $variables);

        return $this->send($buyerEmail, $subject, $body);
    }

    /**
     * Send buyer approval notification to vendor
     */
    public function sendBuyerApproval($vendorEmail, $poData, $approvedChanges) {
        $template = $this->getTemplate('buyer_approve_request');
        
        $variables = [
            'po_number' => $poData['tran_id'],
            'total_amount' => '$' . number_format($poData['total_amount'], 2),
            'status' => $this->getStatusText($poData['status']),
            'approved_changes' => $this->formatChanges($approvedChanges),
            'portal_link' => $this->getPortalLink('po', $poData['id'])
        ];

        $subject = $this->replaceVariables($template['subject'], $variables);
        $body = $this->replaceVariables($template['body'], $variables);

        return $this->send($vendorEmail, $subject, $body);
    }

    /**
     * Send item notification to dealer
     */
    public function sendItemNotification($dealerEmail, $itemData, $notificationType) {
        $templateName = 'item_' . $notificationType;
        $template = $this->getTemplate($templateName);
        
        $variables = [
            'item_name' => $itemData['name'],
            'item_sku' => $itemData['item_id'],
            'quantity' => $itemData['quantity'],
            'portal_link' => $this->getPortalLink('item', $itemData['id'])
        ];

        $subject = $this->replaceVariables($template['subject'], $variables);
        $body = $this->replaceVariables($template['body'], $variables);

        return $this->send($dealerEmail, $subject, $body);
    }

    /**
     * Get email template from database
     */
    private function getTemplate($name) {
        $template = $this->db->fetchOne(
            "SELECT * FROM email_templates WHERE name = ?",
            [$name]
        );

        if (!$template) {
            throw new \Exception("Email template '$name' not found");
        }

        return $template;
    }

    /**
     * Replace template variables
     */
    private function replaceVariables($text, $variables) {
        foreach ($variables as $key => $value) {
            $text = str_replace('{{' . $key . '}}', $value, $text);
        }
        return $text;
    }

    /**
     * Send email via configured provider
     */
    private function send($to, $subject, $body, $html = false) {
        if ($this->provider === 'brevo') {
            return $this->sendViaBrevo($to, $subject, $body, $html);
        } elseif ($this->provider === 'ses') {
            return $this->sendViaSES($to, $subject, $body, $html);
        }

        // Fallback to PHP mail
        return $this->sendViaPHPMail($to, $subject, $body, $html);
    }

    /**
     * Send email via Brevo (Sendinblue)
     */
    private function sendViaBrevo($to, $subject, $body, $html = false) {
        $apiKey = $_ENV['BREVO_API_KEY'] ?? null;
        
        if (!$apiKey) {
            error_log("Brevo API key not configured");
            return false;
        }

        $url = 'https://api.brevo.com/v3/smtp/email';
        
        $data = [
            'sender' => [
                'name' => $this->config['notifications']['from_name'],
                'email' => $this->config['notifications']['from_email']
            ],
            'to' => [
                ['email' => $to]
            ],
            'subject' => $subject
        ];

        if ($html) {
            $data['htmlContent'] = $body;
        } else {
            $data['textContent'] = $body;
        }

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => [
                'Accept: application/json',
                'Content-Type: application/json',
                'api-key: ' . $apiKey
            ],
            CURLOPT_POSTFIELDS => json_encode($data)
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode >= 200 && $httpCode < 300) {
            return true;
        } else {
            error_log("Brevo email failed (HTTP $httpCode): $response");
            return false;
        }
    }

    /**
     * Send email via Amazon SES
     */
    private function sendViaSES($to, $subject, $body, $html = false) {
        // Amazon SES implementation would go here
        // For now, fallback to PHP mail
        return $this->sendViaPHPMail($to, $subject, $body, $html);
    }

    /**
     * Send email via PHP mail() function
     */
    private function sendViaPHPMail($to, $subject, $body, $html = false) {
        $headers = [
            'From: ' . $this->config['notifications']['from_name'] . ' <' . $this->config['notifications']['from_email'] . '>',
            'Reply-To: ' . $this->config['notifications']['from_email'],
            'X-Mailer: PHP/' . phpversion()
        ];

        if ($html) {
            $headers[] = 'MIME-Version: 1.0';
            $headers[] = 'Content-type: text/html; charset=utf-8';
        } else {
            $headers[] = 'Content-type: text/plain; charset=utf-8';
        }

        return mail($to, $subject, $body, implode("\r\n", $headers));
    }

    /**
     * Get status text from status code
     */
    private function getStatusText($status) {
        $statuses = [
            'B' => 'Pending Received',
            'E' => 'Partially Received',
            'F' => 'Pending Billing/Partially Received',
            'H' => 'Pending Billing'
        ];

        return $statuses[$status] ?? $status;
    }

    /**
     * Format changes array for email
     */
    private function formatChanges($changes) {
        $formatted = [];
        foreach ($changes as $field => $change) {
            $formatted[] = "- $field: " . ($change['old'] ?? 'N/A') . " → " . ($change['new'] ?? 'N/A');
        }
        return implode("\n", $formatted);
    }

    /**
     * Get portal link
     */
    private function getPortalLink($type, $id) {
        $baseUrl = $_ENV['APP_URL'] ?? 'http://localhost';
        
        if ($type === 'po') {
            return $baseUrl . '/purchase-order.php?id=' . $id;
        } elseif ($type === 'item') {
            return $baseUrl . '/items.php?id=' . $id;
        }

        return $baseUrl;
    }
}