<?php
/**
 * Messaging Service
 * Handles vendor-to-accounting and vendor-to-buyer conversations
 */

namespace LagunaPartners;

class MessagingService {
    private $db;
    private $teamsService;

    public function __construct() {
        $this->db = Database::getInstance();
        $this->teamsService = new TeamsService();
    }

    /**
     * Create or get conversation
     */
    public function getOrCreateConversation($vendorId, $conversationType, $otherUserId = null, $subject = null) {
        try {
            $whereClause = "WHERE vendor_id = ? AND conversation_type = ?";
            $params = [$vendorId, $conversationType];

            if ($otherUserId) {
                if ($conversationType === 'vendor_to_accounting') {
                    $whereClause .= " AND accounting_user_id = ?";
                    $params[] = $otherUserId;
                } elseif ($conversationType === 'vendor_to_buyer') {
                    $whereClause .= " AND buyer_user_id = ?";
                    $params[] = $otherUserId;
                }
            } else {
                if ($conversationType === 'vendor_to_accounting') {
                    $whereClause .= " AND accounting_user_id IS NULL";
                } elseif ($conversationType === 'vendor_to_buyer') {
                    $whereClause .= " AND buyer_user_id IS NULL";
                }
            }

            $conversation = $this->db->fetchOne("SELECT * FROM conversations $whereClause", $params);

            if ($conversation) {
                return $conversation;
            }

            $conversationData = [
                'vendor_id' => $vendorId,
                'conversation_type' => $conversationType,
                'subject' => $subject,
                'status' => 'active'
            ];

            if ($conversationType === 'vendor_to_accounting') {
                $conversationData['accounting_user_id'] = $otherUserId;
            } elseif ($conversationType === 'vendor_to_buyer') {
                $conversationData['buyer_user_id'] = $otherUserId;
            }

            $sql = "INSERT INTO conversations (" . implode(', ', array_keys($conversationData)) . ") VALUES (" . implode(', ', array_fill(0, count($conversationData), '?')) . ")";
            $this->db->query($sql, array_values($conversationData));

            $newId = $this->db->lastInsertId();
            return $this->db->fetchOne("SELECT * FROM conversations WHERE id = ?", [$newId]);

        } catch (\Exception $e) {
            error_log("Error in MessagingService::getOrCreateConversation: " . $e->getMessage() . "\n" . $e->getTraceAsString());
            throw $e;
        }
    }

    /**
     * Send a message in a conversation
     */
    public function sendMessage($conversationId, $senderUserId, $senderType, $messageText) {
        try {
            $conversation = $this->db->fetchOne("SELECT * FROM conversations WHERE id = ?", [$conversationId]);
            if (!$conversation) {
                throw new \Exception("Conversation not found");
            }

            if ($senderType === 'accounting' && !$conversation['accounting_user_id']) {
                $this->db->query(
                    "UPDATE conversations SET accounting_user_id = ? WHERE id = ?",
                    [$senderUserId, $conversationId]
                );
                $conversation['accounting_user_id'] = $senderUserId;
            } elseif ($senderType === 'buyer' && !$conversation['buyer_user_id']) {
                $this->db->query(
                    "UPDATE conversations SET buyer_user_id = ? WHERE id = ?",
                    [$senderUserId, $conversationId]
                );
                $conversation['buyer_user_id'] = $senderUserId;
            }

            $messageData = [
                'conversation_id' => $conversationId,
                'sender_user_id' => $senderUserId,
                'sender_type' => $senderType,
                'message_text' => $messageText,
                'is_read' => 0
            ];

            $sql = "INSERT INTO messages (" . implode(', ', array_keys($messageData)) . ") VALUES (" . implode(', ', array_fill(0, count($messageData), '?')) . ")";
            $this->db->query($sql, array_values($messageData));

            $messageId = $this->db->lastInsertId();

            $this->db->query(
                "UPDATE conversations SET last_message_at = NOW() WHERE id = ?",
                [$conversationId]
            );

            $this->notifyNewMessage($conversation, $messageId, $senderUserId, $senderType);

            return $this->db->fetchOne("SELECT m.*, u.first_name, u.last_name FROM messages m LEFT JOIN users u ON m.sender_user_id = u.id WHERE m.id = ?", [$messageId]);

        } catch (\Exception $e) {
            error_log("Error in MessagingService::sendMessage: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Get messages for a conversation
     */
    public function getMessages($conversationId, $limit = 50, $offset = 0) {
        try {
            $messages = $this->db->fetchAll(
                "SELECT m.*, u.first_name, u.last_name, a.company_name 
                 FROM messages m 
                 LEFT JOIN users u ON m.sender_user_id = u.id 
                 LEFT JOIN accounts a ON a.id = ? 
                 WHERE m.conversation_id = ? 
                 ORDER BY m.created_at DESC 
                 LIMIT ? OFFSET ?",
                [
                    $this->db->fetchOne("SELECT vendor_id FROM conversations WHERE id = ?", [$conversationId])['vendor_id'],
                    $conversationId,
                    $limit,
                    $offset
                ]
            );

            return array_reverse($messages);
        } catch (\Exception $e) {
            error_log("Error in MessagingService::getMessages: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Get conversations for a user
     */
    public function getUserConversations($userId, $userType = 'vendor') {
        try {
            $where = "WHERE 1=1";
            $params = [];

            if ($userType === 'vendor') {
                $where .= " AND c.vendor_id = ?";
                
                if (session_status() === PHP_SESSION_NONE) {
                    session_start();
                }
                $vendorId = $_SESSION['active_account_id'] ?? null;
                
                if (!$vendorId) {
                    $accounts = $this->db->fetchAll(
                        "SELECT a.id FROM accounts a
                         INNER JOIN user_accounts ua ON a.id = ua.account_id
                         WHERE ua.user_id = ? AND a.type = 'vendor' AND a.is_active = 1",
                        [$userId]
                    );
                    $vendorId = $accounts[0]['id'] ?? null;
                }
                
                error_log("MessagingService::getUserConversations - userId: $userId, vendorId: " . ($vendorId ?? 'NULL'));
                
                $params = [$userId, $vendorId];
            } elseif ($userType === 'accounting') {
                $where .= " AND c.conversation_type = 'vendor_to_accounting' AND (c.accounting_user_id = ? OR c.accounting_user_id IS NULL)";
                $params = [$userId, $userId];
            } elseif ($userType === 'buyer') {
                $where .= " AND c.conversation_type = 'vendor_to_buyer' AND (c.buyer_user_id = ? OR c.buyer_user_id IS NULL)";
                $params = [$userId, $userId];
            }

            $conversations = $this->db->fetchAll(
                "SELECT c.*, 
                        CONCAT(v.company_name) as vendor_name,
                        (SELECT COUNT(*) FROM messages WHERE conversation_id = c.id AND is_read = 0 AND sender_user_id != ?) as unread_count,
                        (SELECT message_text FROM messages WHERE conversation_id = c.id ORDER BY created_at DESC LIMIT 1) as last_message
                 FROM conversations c
                 LEFT JOIN accounts v ON c.vendor_id = v.id
                 $where
                 ORDER BY c.last_message_at DESC",
                $params
            );

            return $conversations;
        } catch (\Exception $e) {
            error_log("Error in MessagingService::getUserConversations: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Mark message as read
     */
    public function markMessageAsRead($messageId, $userId) {
        try {
            $this->db->query(
                "UPDATE messages SET is_read = 1, read_at = NOW() WHERE id = ? AND sender_user_id != ?",
                [$messageId, $userId]
            );

            return true;
        } catch (\Exception $e) {
            error_log("Error in MessagingService::markMessageAsRead: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Mark conversation messages as read
     */
    public function markConversationAsRead($conversationId, $userId) {
        try {
            $this->db->query(
                "UPDATE messages SET is_read = 1, read_at = NOW() 
                 WHERE conversation_id = ? AND sender_user_id != ? AND is_read = 0",
                [$conversationId, $userId]
            );

            return true;
        } catch (\Exception $e) {
            error_log("Error in MessagingService::markConversationAsRead: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Get unread message count for user
     */
    public function getUnreadCount($userId) {
        try {
            $result = $this->db->fetchOne(
                "SELECT COUNT(DISTINCT m.conversation_id) as unread_conversations,
                        COUNT(m.id) as unread_messages
                 FROM messages m
                 INNER JOIN conversations c ON m.conversation_id = c.id
                 LEFT JOIN message_participants mp ON c.id = mp.conversation_id AND mp.user_id = ?
                 WHERE m.is_read = 0 AND m.sender_user_id != ?
                 AND (mp.user_id IS NOT NULL OR c.accounting_user_id = ? OR c.buyer_user_id = ?)",
                [$userId, $userId, $userId, $userId]
            );

            return $result;
        } catch (\Exception $e) {
            error_log("Error in MessagingService::getUnreadCount: " . $e->getMessage());
            return ['unread_conversations' => 0, 'unread_messages' => 0];
        }
    }

    /**
     * Notify Teams about new message
     */
    private function notifyNewMessage($conversation, $messageId, $senderUserId, $senderType) {
        try {
            $message = $this->db->fetchOne(
                "SELECT m.*, u.first_name, u.last_name FROM messages m 
                 LEFT JOIN users u ON m.sender_user_id = u.id 
                 WHERE m.id = ?",
                [$messageId]
            );

            $vendor = $this->db->fetchOne("SELECT * FROM accounts WHERE id = ?", [$conversation['vendor_id']]);

            $senderName = '';
            if ($senderType === 'vendor') {
                $senderName = $vendor['company_name'] ?? 'Vendor';
            } elseif ($senderType === 'accounting') {
                $senderName = ($message['first_name'] ?? '') . ' ' . ($message['last_name'] ?? '') . ' (Accounting)';
            } elseif ($senderType === 'buyer') {
                $senderName = ($message['first_name'] ?? '') . ' ' . ($message['last_name'] ?? '') . ' (Buyer)';
            }

            $this->teamsService->sendNewMessage($conversation, $message, $senderName, $senderType);

        } catch (\Exception $e) {
            error_log("Error in MessagingService::notifyNewMessage: " . $e->getMessage());
        }
    }

    /**
     * Add file attachment to message
     */
    public function addMessageAttachment($messageId, $fileName, $filePath, $fileSize, $fileType) {
        try {
            $attachmentData = [
                'message_id' => $messageId,
                'file_name' => $fileName,
                'file_path' => $filePath,
                'file_size' => $fileSize,
                'file_type' => $fileType
            ];

            $sql = "INSERT INTO message_attachments (" . implode(', ', array_keys($attachmentData)) . ") VALUES (" . implode(', ', array_fill(0, count($attachmentData), '?')) . ")";
            $this->db->query($sql, array_values($attachmentData));

            return true;
        } catch (\Exception $e) {
            error_log("Error in MessagingService::addMessageAttachment: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Get message attachments
     */
    public function getMessageAttachments($messageId) {
        try {
            return $this->db->fetchAll(
                "SELECT * FROM message_attachments WHERE message_id = ? ORDER BY uploaded_at DESC",
                [$messageId]
            );
        } catch (\Exception $e) {
            error_log("Error in MessagingService::getMessageAttachments: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Close conversation
     */
    public function closeConversation($conversationId) {
        try {
            $this->db->query(
                "UPDATE conversations SET status = 'closed' WHERE id = ?",
                [$conversationId]
            );
            return true;
        } catch (\Exception $e) {
            error_log("Error in MessagingService::closeConversation: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Archive conversation
     */
    public function archiveConversation($conversationId) {
        try {
            $this->db->query(
                "UPDATE conversations SET status = 'archived' WHERE id = ?",
                [$conversationId]
            );
            return true;
        } catch (\Exception $e) {
            error_log("Error in MessagingService::archiveConversation: " . $e->getMessage());
            return false;
        }
    }
}
?>
