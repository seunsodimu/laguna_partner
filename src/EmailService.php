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
    private $logFile;

    public function __construct() {
        $this->db = Database::getInstance();
        $this->config = require __DIR__ . '/../config/config.php';
        $this->provider = $_ENV['EMAIL_PROVIDER'] ?? 'brevo';
        $this->logFile = __DIR__ . '/../logs/email-' . date('Y-m-d') . '.log';
        $this->ensureLogDirectory();
    }

    /**
     * Ensure log directory exists
     */
    private function ensureLogDirectory() {
        $logDir = dirname($this->logFile);
        if (!is_dir($logDir)) {
            try {
                if (!mkdir($logDir, 0755, true)) {
                    error_log("EmailService: Failed to create log directory: $logDir");
                }
            } catch (\Throwable $e) {
                error_log("EmailService: Exception creating log directory: " . $e->getMessage());
            }
        }
        
        // Verify directory is writable
        if (is_dir($logDir) && !is_writable($logDir)) {
            error_log("EmailService: Log directory is not writable: $logDir");
        }
    }

    /**
     * Log email activity
     */
    private function log($message) {
        $timestamp = date('Y-m-d H:i:s');
        $logMessage = "[$timestamp] $message\n";
        
        try {
            if (!file_put_contents($this->logFile, $logMessage, FILE_APPEND | LOCK_EX)) {
                error_log("EmailService: Failed to write to log file: {$this->logFile}. Message: $message");
            }
        } catch (\Throwable $e) {
            error_log("EmailService: Exception writing to log: " . $e->getMessage());
        }
    }

    /**
     * Send OTP email
     */
    public function sendOTP($email, $otp) {
        try {
            $this->log("Attempting to send OTP to $email");
            $template = $this->getTemplate('otp_login');
            
            $subject = $template['subject'];
            $body = $this->replaceVariables($template['body'], [
                'otp_code' => $otp,
                'user_email' => $email
            ]);

            $result = $this->send($email, $subject, $body);
            if ($result) {
                $this->log("OTP sent successfully to $email");
            } else {
                $this->log("Failed to send OTP to $email");
            }
            return $result;
        } catch (\Exception $e) {
            $this->log("Error sending OTP to $email: " . $e->getMessage());
            return false;
        }
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
        $this->log("Send method called with provider: $this->provider, to: $to");
        
        if ($this->provider === 'brevo') {
            return $this->sendViaBrevo($to, $subject, $body, $html);
        } elseif ($this->provider === 'ses') {
            return $this->sendViaSES($to, $subject, $body, $html);
        }

        // Fallback to PHP mail
        $this->log("Using fallback PHP mail() function");
        return $this->sendViaPHPMail($to, $subject, $body, $html);
    }

    /**
     * Send email via Brevo (Sendinblue)
     */
    private function sendViaBrevo($to, $subject, $body, $html = false) {
        $apiKey = $_ENV['BREVO_API_KEY'] ?? null;
        
        if (!$apiKey) {
            $this->log("Brevo API key not configured");
            return false;
        }

        $url = 'https://api.brevo.com/v3/smtp/email';
        $this->log("Sending email via Brevo to $to with subject: $subject");
        
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
        $curlError = curl_error($ch);
        curl_close($ch);

        $this->log("Brevo Response - HTTP $httpCode - Body: $response - cURL Error: " . ($curlError ?: 'none'));

        if ($httpCode >= 200 && $httpCode < 300) {
            $this->log("Email sent successfully to $to (HTTP $httpCode)");
            return true;
        } else {
            $this->log("Brevo email failed (HTTP $httpCode): $response | cURL error: $curlError");
            return false;
        }
    }

    /**
     * Send email via Amazon SES
     */
    private function sendViaSES($to, $subject, $body, $html = false, $allowFallback = true) {
        $accessKey = $_ENV['AWS_ACCESS_KEY_ID'] ?? null;
        $secretKey = $_ENV['AWS_SECRET_ACCESS_KEY'] ?? null;
        $region = $_ENV['AWS_SES_REGION'] ?? 'us-east-1';
        $fromEmail = $this->config['notifications']['from_email'];
        $fromName = trim($this->config['notifications']['from_name'], '"');
        
        if (!$accessKey || !$secretKey) {
            $this->log("AWS SES credentials not configured (AccessKey: " . (!$accessKey ? 'missing' : 'set') . ", SecretKey: " . (!$secretKey ? 'missing' : 'set') . ")" . ($allowFallback ? ", falling back to Brevo" : ""));
            if ($allowFallback) {
                return $this->sendViaBrevo($to, $subject, $body, $html);
            }
            return false;
        }

        $this->log("Sending email via AWS SES to $to (Region: $region) with subject: $subject");
        $this->log("From: $fromEmail, FromName: $fromName");

        // Prepare the email data
        // AWS SES requires Source to be a verified email address
        $emailData = [
            'Source' => $fromEmail,
            'Destination' => [
                'ToAddresses' => [$to]
            ],
            'Message' => [
                'Subject' => [
                    'Data' => $subject,
                    'Charset' => 'UTF-8'
                ],
                'Body' => []
            ]
        ];

        if ($html) {
            $emailData['Message']['Body']['Html'] = [
                'Data' => $body,
                'Charset' => 'UTF-8'
            ];
        } else {
            $emailData['Message']['Body']['Text'] = [
                'Data' => $body,
                'Charset' => 'UTF-8'
            ];
        }

        try {
            // Make the SES API call
            $response = $this->makeSESRequest('SendEmail', $emailData, $region, $accessKey, $secretKey);
            
            if ($response['success']) {
                $this->log("Email sent successfully via SES to $to (Message ID: {$response['messageId']})");
                return true;
            } else {
                $this->log("SES email failed: {$response['error']} | Details: {$response['details']}");
                if ($allowFallback) {
                    // Fallback to Brevo
                    $this->log("Falling back to Brevo due to SES failure...");
                    return $this->sendViaBrevo($to, $subject, $body, $html);
                }
                return false;
            }
        } catch (\Throwable $e) {
            $this->log("Exception during SES call: " . $e->getMessage() . " | Trace: " . $e->getTraceAsString());
            if ($allowFallback) {
                // Fallback to Brevo
                $this->log("Falling back to Brevo due to exception in SES...");
                return $this->sendViaBrevo($to, $subject, $body, $html);
            }
            return false;
        }
    }

    /**
     * Make AWS SES API request with SigV4 signing (Query API format)
     */
    private function makeSESRequest($action, $data, $region, $accessKey, $secretKey) {
        $host = "email.$region.amazonaws.com";
        $endpoint = "https://$host/";
        $service = 'ses';
        $algorithm = 'AWS4-HMAC-SHA256';
        
        // Use UTC for AWS SigV4 signing
        $timestamp = gmdate('Ymd\THis\Z');
        $dateStamp = gmdate('Ymd');
        $credentialScope = $dateStamp . "/$region/$service/aws4_request";

        // Build query string for SES Query API
        $queryParams = [
            'Action' => $action,
            'Source' => $data['Source'],
            'Destination.ToAddresses.member.1' => $data['Destination']['ToAddresses'][0],
            'Message.Subject.Data' => $data['Message']['Subject']['Data'],
            'Message.Subject.Charset' => $data['Message']['Subject']['Charset']
        ];

        if (isset($data['Message']['Body']['Html'])) {
            $queryParams['Message.Body.Html.Data'] = $data['Message']['Body']['Html']['Data'];
            $queryParams['Message.Body.Html.Charset'] = $data['Message']['Body']['Html']['Charset'];
        } else {
            $queryParams['Message.Body.Text.Data'] = $data['Message']['Body']['Text']['Data'];
            $queryParams['Message.Body.Text.Charset'] = $data['Message']['Body']['Text']['Charset'];
        }

        // Sort params for canonical request
        ksort($queryParams);
        
        // Build URL encoded payload
        $payload = http_build_query($queryParams, '', '&', PHP_QUERY_RFC3986);
        $hashedPayload = hash('sha256', $payload);

        // Create canonical request
        $canonicalRequest = "POST\n";
        $canonicalRequest .= "/\n";
        $canonicalRequest .= "\n";
        $canonicalRequest .= "content-type:application/x-www-form-urlencoded; charset=utf-8\n";
        $canonicalRequest .= "host:$host\n";
        $canonicalRequest .= "x-amz-date:$timestamp\n";
        $canonicalRequest .= "\n";
        $canonicalRequest .= "content-type;host;x-amz-date\n";
        $canonicalRequest .= $hashedPayload;

        $hashedCanonicalRequest = hash('sha256', $canonicalRequest);

        // Create string to sign
        $stringToSign = "$algorithm\n$timestamp\n$credentialScope\n$hashedCanonicalRequest";

        // Calculate signature
        $kDate = hash_hmac('sha256', $dateStamp, "AWS4$secretKey", true);
        $kRegion = hash_hmac('sha256', $region, $kDate, true);
        $kService = hash_hmac('sha256', $service, $kRegion, true);
        $kSigning = hash_hmac('sha256', 'aws4_request', $kService, true);
        $signature = hash_hmac('sha256', $stringToSign, $kSigning);

        // Build authorization header
        $authorizationHeader = "$algorithm Credential=$accessKey/$credentialScope, SignedHeaders=content-type;host;x-amz-date, Signature=$signature";

        // Make the request
        $ch = curl_init($endpoint);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/x-www-form-urlencoded; charset=utf-8',
                "Host: $host",
                "X-Amz-Date: $timestamp",
                "Authorization: $authorizationHeader"
            ],
            CURLOPT_POSTFIELDS => $payload
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        $this->log("SES Response - HTTP $httpCode - Body: $response - cURL Error: " . ($curlError ?: 'none'));

        if ($curlError) {
            $errorMsg = "cURL Error: $curlError (HTTP $httpCode)";
            $this->log("SES cURL Error: $errorMsg, Response: $response");
            return [
                'success' => false,
                'error' => 'cURL Error',
                'details' => $errorMsg
            ];
        }

        if ($httpCode >= 200 && $httpCode < 300) {
            // Parse XML response for MessageId
            $xml = simplexml_load_string($response);
            $messageId = $xml ? (string)$xml->SendEmailResult->MessageId : 'unknown';
            $this->log("SES request successful. MessageId: $messageId");
            return [
                'success' => true,
                'messageId' => $messageId
            ];
        } else {
            // Parse XML error response
            $error = 'Unknown Error';
            $message = $response;
            try {
                $xml = simplexml_load_string($response);
                if ($xml && isset($xml->Error->Code)) {
                    $error = (string)$xml->Error->Code;
                    $message = (string)$xml->Error->Message;
                }
            } catch (\Throwable $e) {
                $this->log("Failed to parse SES error response: " . $e->getMessage());
            }
            $errorDetails = "$error: $message (HTTP $httpCode)";
            $this->log("SES request failed: $errorDetails");
            return [
                'success' => false,
                'error' => $error,
                'details' => $errorDetails
            ];
        }
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
     * Send email using template name and variables
     */
    public function sendFromTemplate($templateName, $toEmail, $variables) {
        try {
            $template = $this->db->fetchOne(
                "SELECT * FROM email_templates WHERE name = ?",
                [$templateName]
            );

            if (!$template) {
                throw new \Exception("Email template '$templateName' not found");
            }

            $subject = $this->replaceVariables($template['subject'], $variables);
            $body = $this->replaceVariables($template['body'], $variables);

            return $this->send($toEmail, $subject, $body);
        } catch (\Exception $e) {
            $this->log("Error sending email from template $templateName to $toEmail: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Send PO rejection notification to buyer
     */
    public function sendPORejection($buyerEmail, $poData, $rejectionReason) {
        try {
            $template = $this->getTemplate('po_rejection');
            
            $variables = [
                'po_number' => $poData['tran_id'] ?? $poData['tranid'] ?? 'N/A',
                'vendor_name' => $poData['vendor_name'],
                'total_amount' => '$' . number_format($poData['total_amount'] ?? 0, 2),
                'rejection_reason' => $rejectionReason,
                'rejected_date' => date('m/d/Y H:i:s'),
                'portal_link' => $this->getPortalLink('po', $poData['id'])
            ];

            $subject = $this->replaceVariables($template['subject'], $variables);
            $body = $this->replaceVariables($template['body'], $variables);

            return $this->send($buyerEmail, $subject, $body);
        } catch (\Exception $e) {
            $this->log("Error sending PO rejection email to $buyerEmail: " . $e->getMessage());
            return false;
        }
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

    /**
     * Send test email with configured provider only (no fallback)
     */
    public function testEmailSend($email) {
        try {
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                return [
                    'success' => false,
                    'error' => 'Invalid email address',
                    'message' => 'The provided email address is not valid'
                ];
            }

            $this->log("Test email requested to: $email via provider: $this->provider");
            
            $subject = '[TEST] Laguna Partners Portal - Email Service Test';
            $body = "This is a test email from the Laguna Partners Portal.\n\n" .
                    "Email Provider: " . strtoupper($this->provider) . "\n" .
                    "Sent at: " . date('Y-m-d H:i:s') . "\n" .
                    "Test successful if you received this email.\n\n" .
                    "---\n" .
                    "Laguna Partners Portal\n" .
                    "Do not reply to this email.";

            $result = $this->sendTestViaProvider($email, $subject, $body);

            if ($result) {
                $this->log("Test email sent successfully to $email via $this->provider");
                return [
                    'success' => true,
                    'message' => 'Test email sent successfully to ' . htmlspecialchars($email),
                    'provider' => $this->provider,
                    'timestamp' => date('Y-m-d H:i:s')
                ];
            } else {
                $this->log("Test email failed for $email via $this->provider");
                return [
                    'success' => false,
                    'error' => 'Failed to send test email',
                    'message' => 'The test email could not be sent using ' . strtoupper($this->provider) . '. Check the logs for details.',
                    'provider' => $this->provider
                ];
            }
        } catch (\Exception $e) {
            $this->log("Exception during test email to $email via $this->provider: " . $e->getMessage());
            return [
                'success' => false,
                'error' => 'Exception occurred',
                'message' => 'An error occurred while sending the test email via ' . strtoupper($this->provider) . ': ' . $e->getMessage()
            ];
        }
    }

    /**
     * Send test email via configured provider only (no fallback)
     */
    private function sendTestViaProvider($to, $subject, $body, $html = false) {
        $this->log("sendTestViaProvider called with provider: $this->provider, to: $to");
        
        if ($this->provider === 'brevo') {
            return $this->sendViaBrevo($to, $subject, $body, $html);
        } elseif ($this->provider === 'ses') {
            return $this->sendViaSES($to, $subject, $body, $html, false);
        } else {
            $this->log("Unknown provider: $this->provider. Using PHP mail() for test.");
            return $this->sendViaPHPMail($to, $subject, $body, $html);
        }
    }
}