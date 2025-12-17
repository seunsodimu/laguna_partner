# Messaging Feature Guide

## Overview

The Laguna Partners Portal now includes a comprehensive messaging system that enables:
- **Vendor-to-Accounting Team** conversations
- **Vendor-to-Buyer** conversations
- Real-time notifications to Microsoft Teams channels
- Message read receipts and conversation management

## Features

### For Vendors
- Create conversations with either the Accounting Team or Buyer Team
- Send and receive messages
- View conversation history
- Mark messages as read automatically when opened
- Close conversations when issues are resolved

### For Accounting Team (Admin)
- View all vendor conversations
- Initiate new conversations with vendors
- Send messages and responses to vendors
- Manage conversation status
- Receive Teams notifications for new messages

### For Buyers
- View all vendor conversations assigned to them
- Send messages to vendors
- View complete conversation history
- Mark conversations as read
- Close conversations

### Teams Integration
- Automatic notifications sent to configured Teams channels when:
  - A new message is received
  - A message is sent
- Notifications include:
  - Sender information
  - Message preview (first 200 characters)
  - Vendor name and timestamp
  - Direct link to view conversation in portal

## Database Tables

### conversations
Stores conversation metadata
- `id`: Primary key
- `conversation_type`: Type of conversation (vendor_to_accounting or vendor_to_buyer)
- `vendor_id`: Reference to vendor account
- `accounting_user_id`: Reference to accounting team member
- `buyer_user_id`: Reference to buyer team member
- `subject`: Optional conversation subject
- `status`: active, closed, or archived
- `last_message_at`: Timestamp of last message

### messages
Stores individual messages
- `id`: Primary key
- `conversation_id`: Reference to conversation
- `sender_user_id`: Reference to user who sent the message
- `sender_type`: Type of sender (vendor, accounting, buyer)
- `message_text`: The message content
- `is_read`: Whether the recipient has read it
- `read_at`: Timestamp when read
- `created_at`, `updated_at`: Timestamps

### message_attachments
Stores file attachments for messages
- `id`: Primary key
- `message_id`: Reference to message
- `file_name`: Original filename
- `file_path`: Path to stored file
- `file_size`: Size in bytes
- `file_type`: MIME type
- `uploaded_at`: Timestamp

### message_participants
Tracks conversation participants and read status
- `id`: Primary key
- `conversation_id`: Reference to conversation
- `user_id`: Reference to user
- `participant_type`: Type of participant
- `last_read_message_id`: Last message read by participant
- `is_muted`: Whether notifications are muted
- `joined_at`: When user joined conversation

## API Endpoints

### GET /api/messages.php?action=get_conversations
Retrieve all conversations for the logged-in user
**Response:**
```json
{
  "success": true,
  "data": [
    {
      "id": 1,
      "vendor_name": "ACME Corp",
      "conversation_type": "vendor_to_buyer",
      "last_message": "Thanks for the update...",
      "last_message_at": "2024-11-20 10:30:00",
      "unread_count": 2,
      "status": "active"
    }
  ]
}
```

### GET /api/messages.php?action=get_messages&conversation_id=1
Retrieve messages from a specific conversation
**Parameters:**
- `conversation_id`: (required) ID of the conversation
- `limit`: (optional) Number of messages to retrieve (default: 50)
- `offset`: (optional) Offset for pagination (default: 0)

### POST /api/messages.php?action=send_message
Send a new message in a conversation
**Parameters:**
- `conversation_id`: (required) ID of the conversation
- `message`: (required) Message text

### POST /api/messages.php?action=create_conversation
Create a new conversation
**Parameters:**
- `conversation_type`: (required) Type: vendor_to_accounting or vendor_to_buyer
- `vendor_id`: (optional) Vendor account ID (admin only)
- `recipient_id`: (optional) Recipient user ID
- `subject`: (optional) Conversation subject

### POST /api/messages.php?action=mark_as_read
Mark a single message as read
**Parameters:**
- `message_id`: (required) ID of the message

### POST /api/messages.php?action=mark_conversation_as_read
Mark all messages in a conversation as read
**Parameters:**
- `conversation_id`: (required) ID of the conversation

### POST /api/messages.php?action=close_conversation
Close a conversation
**Parameters:**
- `conversation_id`: (required) ID of the conversation

### POST /api/messages.php?action=archive_conversation
Archive a conversation
**Parameters:**
- `conversation_id`: (required) ID of the conversation

### GET /api/messages.php?action=get_unread_count
Get unread message count
**Response:**
```json
{
  "success": true,
  "data": {
    "unread_conversations": 3,
    "unread_messages": 5
  }
}
```

## UI Access

### For Vendors
- Navigate to: `/vendor/messages.php`
- Features:
  - View all conversations in left sidebar
  - Create new conversation with Accounting or Buyer teams
  - Send and receive messages
  - Auto-marks messages as read when viewed

### For Accounting Team
- Navigate to: `/admin/messages.php`
- Features:
  - Start conversations with specific vendors
  - View all vendor communications
  - Manage conversation status
  - Receive Teams notifications

### For Buyers
- Navigate to: `/buyer/messages.php`
- Features:
  - View vendor conversations
  - Send responses to vendors
  - Track conversation history

## Teams Channel Webhook Configuration

To enable Teams notifications, ensure these webhook configurations are set up:

1. **message_accounting**: Webhook for accounting team messages
   - Receives: Notifications when vendors message the accounting team
   - Configure in: Admin Dashboard > Teams Webhook Config

2. **message_buyer**: Webhook for buyer team messages
   - Receives: Notifications when vendors message the buyer team
   - Configure in: Admin Dashboard > Teams Webhook Config

### Setting Up Webhooks

1. Go to Admin Dashboard > Teams Webhook
2. Click "Add Webhook Configuration"
3. Select notification type: `message_accounting` or `message_buyer`
4. Enter your Teams channel webhook URL
5. Test the webhook
6. Save configuration

## MessagingService Class

The `MessagingService` class provides the core messaging functionality:

```php
use LagunaPartners\MessagingService;

$messagingService = new MessagingService();

// Get or create a conversation
$conversation = $messagingService->getOrCreateConversation(
    $vendorId, 
    'vendor_to_buyer', 
    $buyerUserId, 
    'Optional Subject'
);

// Send a message
$message = $messagingService->sendMessage(
    $conversationId,
    $userId,
    'vendor',  // sender type
    'Message text'
);

// Get messages
$messages = $messagingService->getMessages($conversationId, 50, 0);

// Mark as read
$messagingService->markConversationAsRead($conversationId, $userId);

// Close conversation
$messagingService->closeConversation($conversationId);
```

## TeamsService Integration

The `TeamsService` class handles Teams notifications:

```php
use LagunaPartners\TeamsService;

$teamsService = new TeamsService();

// Send new message notification
$teamsService->sendNewMessage($conversation, $message, $senderName, $senderType);
```

## File Structure

- `public/vendor/messages.php` - Vendor messaging interface
- `public/buyer/messages.php` - Buyer messaging interface
- `public/admin/messages.php` - Accounting/Admin messaging interface
- `public/api/messages.php` - Messaging API endpoints
- `public/api/accounts.php` - Accounts API (for vendor selection)
- `public/api/users.php` - Updated with get_team_members endpoint
- `src/MessagingService.php` - Core messaging service class
- `src/TeamsService.php` - Updated with new message notification methods
- `database/add_messaging_tables.php` - Database migration script

## Testing the Feature

### Manual Testing

1. **Create a Conversation (Vendor)**
   - Log in as a vendor
   - Go to Messages
   - Click "New Conversation"
   - Select recipient and click "Start Conversation"

2. **Send a Message**
   - Type message in text area
   - Click Send button
   - Message appears in conversation

3. **Receive Response**
   - Log in as buyer/accounting
   - Go to Messages
   - View the vendor's conversation
   - Send a reply

4. **Check Teams Notification**
   - Check configured Teams channel
   - Should see notification with message preview

### API Testing

```bash
# Get conversations
curl -X GET "http://localhost/laguna_partner/api/messages.php?action=get_conversations"

# Send message
curl -X POST "http://localhost/laguna_partner/api/messages.php?action=send_message" \
  -d "conversation_id=1&message=Test message"

# Get unread count
curl -X GET "http://localhost/laguna_partner/api/messages.php?action=get_unread_count"
```

## Troubleshooting

### Tables Not Created
- Run: `php database/add_messaging_tables.php`
- Check database connection in `config/config.php`

### Teams Notifications Not Received
- Verify webhook URL is configured correctly
- Check Teams webhook configuration in admin panel
- Review logs in `logs/teams-*.log`

### Messages Not Appearing
- Clear browser cache
- Check database connection
- Verify user permissions
- Check `logs/` directory for errors

### Foreign Key Errors
- Ensure all referenced tables exist (accounts, users, conversations)
- Run migration with foreign key checks disabled:
  ```bash
  php database/add_messaging_tables.php
  ```

## Security Considerations

- Messages are checked for authorization before display
- Users can only access their own conversations
- All user input is properly escaped
- Foreign key constraints ensure data integrity
- Session authentication required for all endpoints

## Future Enhancements

Potential features for future versions:
- Message search functionality
- File attachments and downloads
- Message typing indicators
- Conversation search and filtering
- Message reactions/emojis
- Message editing and deletion
- Group conversations
- Message encryption
- Conversation templates
- Auto-reply messages
