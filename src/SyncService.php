<?php
/**
 * Sync Service
 * Handles synchronization between NetSuite and local database
 */

namespace LagunaPartners;

class SyncService {
    private $db;
    private $netsuite;
    private $syncLogId;

    public function __construct($config = null) {
        $this->db = Database::getInstance();
        $this->netsuite = new NetSuiteClient();
    }

    /**
     * Sync accounts (alias for syncAccountsAndUsers)
     */
    public function syncAccounts($startedBy = null) {
        return $this->syncAccountsAndUsers($startedBy);
    }

    /**
     * Sync accounts and users from NetSuite
     */
    public function syncAccountsAndUsers($startedBy = null) {
        $this->syncLogId = $this->startSyncLog('accounts', $startedBy);

        try {
            $this->db->beginTransaction();

            // Sync vendors
            $vendors = $this->netsuite->getVendors();
            $vendorStats = $this->syncVendors($vendors);

            // Sync dealers
            $dealers = $this->netsuite->getDealers();
            $dealerStats = $this->syncDealers($dealers);

            // Sync buyers
            $buyers = $this->netsuite->getBuyers();
            $buyerStats = $this->syncBuyers($buyers);

            $this->db->commit();

            $totalStats = [
                'processed' => $vendorStats['processed'] + $dealerStats['processed'] + $buyerStats['processed'],
                'created' => $vendorStats['created'] + $dealerStats['created'] + $buyerStats['created'],
                'updated' => $vendorStats['updated'] + $dealerStats['updated'] + $buyerStats['updated'],
                'failed' => $vendorStats['failed'] + $dealerStats['failed'] + $buyerStats['failed']
            ];

            $this->completeSyncLog('completed', $totalStats);

            return [
                'success' => true,
                'stats' => $totalStats,
                'details' => [
                    'vendors' => $vendorStats,
                    'dealers' => $dealerStats,
                    'buyers' => $buyerStats
                ]
            ];

        } catch (\Exception $e) {
            $this->db->rollback();
            $this->completeSyncLog('failed', [], $e->getMessage());
            
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Sync vendors
     */
    private function syncVendors($vendors) {
        $stats = ['processed' => 0, 'created' => 0, 'updated' => 0, 'failed' => 0];

        foreach ($vendors as $vendor) {
            $stats['processed']++;

            try {
                // Check if account exists
                $existing = $this->db->fetchOne(
                    "SELECT * FROM accounts WHERE id = ? AND type = 'vendor'",
                    [$vendor['id']]
                );

                $accountData = [
                    'id' => $vendor['id'],
                    'type' => 'vendor',
                    'company_name' => $vendor['companyname'] ?? $vendor['entityid'],
                    'category' => $vendor['category'] ?? null,
                    'email' => $vendor['email'] ?? null,
                    'phone' => $vendor['phone'] ?? null,
                    'is_active' => ($vendor['isinactive'] ?? 'F') === 'F',
                    'netsuite_data' => json_encode($vendor)
                ];

                if ($existing) {
                    // Update existing account
                    unset($accountData['id']); // Don't update ID
                    $this->db->update('accounts', $accountData, 'id = ?', [$vendor['id']]);
                    $stats['updated']++;
                } else {
                    // Create new account
                    $this->db->insert('accounts', $accountData);
                    $stats['created']++;
                }

                // Sync vendor profile data
                $this->syncVendorProfile($vendor);

                // Sync users for this vendor
                $this->syncVendorUsers($vendor);

            } catch (\Exception $e) {
                $stats['failed']++;
                error_log("Failed to sync vendor {$vendor['id']}: " . $e->getMessage());
            }
        }

        return $stats;
    }

    /**
     * Sync vendor profile data
     */
    private function syncVendorProfile($vendor) {
        try {
            // Check if vendor profile exists
            $existing = $this->db->fetchOne(
                "SELECT * FROM vendor_profiles WHERE vendor_id = ?",
                [$vendor['id']]
            );

            $profileData = [
                'term' => $vendor['terms'] ?? null
            ];

            if ($existing) {
                // Update existing profile
                $this->db->update('vendor_profiles', $profileData, 'vendor_id = ?', [$vendor['id']]);
            } else {
                // Create new profile
                $profileData['vendor_id'] = $vendor['id'];
                $this->db->insert('vendor_profiles', $profileData);
            }
        } catch (\Exception $e) {
            error_log("Failed to sync vendor profile for vendor {$vendor['id']}: " . $e->getMessage());
        }
    }

    /**
     * Sync users for a vendor account
     */
    private function syncVendorUsers($vendor) {
        $emails = [];
        
        // Collect all email addresses
        if (!empty($vendor['email'])) {
            $emails[] = $vendor['email'];
        }
        if (!empty($vendor['custentityap_email_1'])) {
            $emails[] = $vendor['custentityap_email_1'];
        }
        if (!empty($vendor['custentityap_email_2'])) {
            $emails[] = $vendor['custentityap_email_2'];
        }
        if (!empty($vendor['custentityap_email_3'])) {
            $emails[] = $vendor['custentityap_email_3'];
        }
        if (!empty($vendor['custentity2nd_email_address'])) {
            $emails[] = $vendor['custentity2nd_email_address'];
        }

        $emails = array_unique(array_filter($emails));

        foreach ($emails as $email) {
            // Get or create user
            $user = $this->db->fetchOne(
                "SELECT * FROM users WHERE email = ? AND type = 'vendor'",
                [$email]
            );

            if (!$user) {
                $userId = $this->db->insert('users', [
                    'email' => $email,
                    'type' => 'vendor',
                    'is_active' => 1
                ]);
            } else {
                $userId = $user['id'];
            }

            // Link user to account
            $link = $this->db->fetchOne(
                "SELECT * FROM user_accounts WHERE user_id = ? AND account_id = ?",
                [$userId, $vendor['id']]
            );

            if (!$link) {
                $this->db->insert('user_accounts', [
                    'user_id' => $userId,
                    'account_id' => $vendor['id'],
                    'is_primary' => $email === $vendor['email']
                ]);
            }
        }
    }

    /**
     * Sync dealers
     */
    private function syncDealers($dealers) {
        $stats = ['processed' => 0, 'created' => 0, 'updated' => 0, 'failed' => 0];

        foreach ($dealers as $dealer) {
            $stats['processed']++;

            try {
                // Check if account exists
                $existing = $this->db->fetchOne(
                    "SELECT * FROM accounts WHERE id = ? AND type = 'dealer'",
                    [$dealer['id']]
                );

                $accountData = [
                    'id' => $dealer['id'],
                    'type' => 'dealer',
                    'company_name' => $dealer['companyname'] ?? $dealer['entityid'],
                    'category' => $dealer['category'] ?? null,
                    'email' => $dealer['email'] ?? null,
                    'phone' => $dealer['phone'] ?? null,
                    'is_active' => ($dealer['isinactive'] ?? 'F') === 'F',
                    'netsuite_data' => json_encode($dealer)
                ];

                if ($existing) {
                    unset($accountData['id']);
                    $this->db->update('accounts', $accountData, 'id = ?', [$dealer['id']]);
                    $stats['updated']++;
                } else {
                    $this->db->insert('accounts', $accountData);
                    $stats['created']++;
                }

                // Sync users for this dealer
                $this->syncDealerUsers($dealer);

            } catch (\Exception $e) {
                $stats['failed']++;
                error_log("Failed to sync dealer {$dealer['id']}: " . $e->getMessage());
            }
        }

        return $stats;
    }

    /**
     * Sync users for a dealer account
     */
    private function syncDealerUsers($dealer) {
        $emails = [];
        
        // Collect all email addresses
        if (!empty($dealer['email'])) {
            $emails[] = $dealer['email'];
        }
        if (!empty($dealer['custentityap_email_1'])) {
            $emails[] = $dealer['custentityap_email_1'];
        }
        if (!empty($dealer['custentityap_email_2'])) {
            $emails[] = $dealer['custentityap_email_2'];
        }
        if (!empty($dealer['custentityap_email_3'])) {
            $emails[] = $dealer['custentityap_email_3'];
        }

        $emails = array_unique(array_filter($emails));

        foreach ($emails as $email) {
            $user = $this->db->fetchOne(
                "SELECT * FROM users WHERE email = ? AND type = 'dealer'",
                [$email]
            );

            if (!$user) {
                $userId = $this->db->insert('users', [
                    'email' => $email,
                    'type' => 'dealer',
                    'is_active' => 1
                ]);
            } else {
                $userId = $user['id'];
            }

            // Link user to account
            $link = $this->db->fetchOne(
                "SELECT * FROM user_accounts WHERE user_id = ? AND account_id = ?",
                [$userId, $dealer['id']]
            );

            if (!$link) {
                $this->db->insert('user_accounts', [
                    'user_id' => $userId,
                    'account_id' => $dealer['id'],
                    'is_primary' => $email === $dealer['email']
                ]);
            }
        }
    }

    /**
     * Sync buyers (employees)
     */
    private function syncBuyers($buyers) {
        $stats = ['processed' => 0, 'created' => 0, 'updated' => 0, 'failed' => 0];

        foreach ($buyers as $buyer) {
            $stats['processed']++;

            try {
                $email = $buyer['email'] ?? null;
                if (!$email) {
                    $stats['failed']++;
                    continue;
                }

                $user = $this->db->fetchOne(
                    "SELECT * FROM users WHERE email = ? AND type = 'user'",
                    [$email]
                );

                $userData = [
                    'email' => $email,
                    'type' => 'user',
                    'role' => 'buyer',
                    'first_name' => $buyer['firstname'] ?? null,
                    'last_name' => $buyer['lastname'] ?? null,
                    'netsuite_id' => $buyer['id'],
                    'status' => ($buyer['isinactive'] ?? 'F') === 'F' ? 'active' : 'inactive'
                ];

                if ($user) {
                    $this->db->update('users', $userData, 'id = ?', [$user['id']]);
                    $stats['updated']++;
                } else {
                    $this->db->insert('users', $userData);
                    $stats['created']++;
                }

            } catch (\Exception $e) {
                $stats['failed']++;
                error_log("Failed to sync buyer {$buyer['id']}: " . $e->getMessage());
            }
        }

        return $stats;
    }

    /**
     * Sync purchase orders from NetSuite
     */
    public function syncPurchaseOrders($startedBy = null, $limit = 1000) {
        $this->syncLogId = $this->startSyncLog('purchase_orders', $startedBy);

        try {
            $this->db->beginTransaction();

            $purchaseOrders = $this->netsuite->getPurchaseOrders();
            $stats = ['processed' => 0, 'created' => 0, 'updated' => 0, 'failed' => 0, 'skipped' => 0];
            
            $totalPOs = count($purchaseOrders);
            $stats['total_available'] = $totalPOs;

            // Limit the number of POs to process in one run
            $purchaseOrders = array_slice($purchaseOrders, 0, $limit);
            $stats['skipped'] = max(0, $totalPOs - $limit);

            foreach ($purchaseOrders as $po) {
                $stats['processed']++;

                try {
                    // Get full PO details
                    $poDetails = $this->netsuite->getPurchaseOrder($po['id']);
                    $this->syncPurchaseOrder($poDetails);
                    
                    $existing = $this->db->fetchOne("SELECT id FROM purchase_orders WHERE id = ?", [$po['id']]);
                    if ($existing) {
                        $stats['updated']++;
                    } else {
                        $stats['created']++;
                    }

                } catch (\Exception $e) {
                    $stats['failed']++;
                    error_log("Failed to sync PO {$po['id']}: " . $e->getMessage());
                }
                
                // Commit every 10 records to avoid long transactions
                if ($stats['processed'] % 10 === 0) {
                    $this->db->commit();
                    $this->db->beginTransaction();
                }
            }

            $this->db->commit();
            $this->completeSyncLog('completed', $stats);

            return [
                'success' => true,
                'stats' => $stats,
                'records_processed' => $stats['processed']
            ];

        } catch (\Exception $e) {
            $this->db->rollback();
            $this->completeSyncLog('failed', [], $e->getMessage());
            
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Sync single purchase order by ID (public method for webhooks/API calls)
     */
    public function syncSinglePurchaseOrder($poId) {
        if (!$poId) {
            throw new \Exception('Purchase order ID required');
        }

        try {
            $this->db->beginTransaction();

            $poDetails = $this->netsuite->getPurchaseOrder($poId);
            if (!$poDetails) {
                throw new \Exception('Purchase order not found in NetSuite');
            }

            $this->syncPurchaseOrder($poDetails);

            $this->db->commit();

            $existing = $this->db->fetchOne("SELECT id FROM purchase_orders WHERE id = ?", [$poId]);

            return [
                'success' => true,
                'message' => $existing ? 'Purchase order updated' : 'Purchase order synced',
                'po_id' => $poId,
                'action' => $existing ? 'updated' : 'created'
            ];

        } catch (\Exception $e) {
            $this->db->rollback();
            throw $e;
        }
    }

    /**
     * Sync single purchase order
     */
    private function syncPurchaseOrder($poDetails) {
        $existing = $this->db->fetchOne(
            "SELECT * FROM purchase_orders WHERE id = ?",
            [$poDetails['id']]
        );

        $poData = [
            'id' => $poDetails['id'],
            'tran_id' => $poDetails['tranId'] ?? $poDetails['id'],
            'vendor_id' => $poDetails['entity']['id'] ?? null,
            'vendor_name' => $poDetails['entity']['refName'] ?? '',
            'buyer_id' => $poDetails['custbody_lt_next_approver']['id'] ?? null,
            'status' => $poDetails['orderStatus']['id'] ?? $poDetails['status'] ?? '',
            'status_text' => $this->getStatusText($poDetails['orderStatus']['id'] ?? $poDetails['status'] ?? ''),
            'total_amount' => $poDetails['total'] ?? 0,
            'currency' => $poDetails['currency']['refName'] ?? 'USD',
            'created_date' => isset($poDetails['createdDate']) ? date('Y-m-d', strtotime($poDetails['createdDate'])) : null,
            'due_date' => $poDetails['dueDate'] ?? null,
            'port_date' => $poDetails['custbody_port_date'] ?? null,
            'estimated_delivery_date' => $poDetails['custcol_est_delivery_date'] ?? null,
            'ship_date' => $poDetails['shipDate'] ?? null,
            'location' => $poDetails['location']['refName'] ?? null,
            'department' => $poDetails['department']['refName'] ?? null,
            'is_synced_to_netsuite' => 1,
            'netsuite_data' => json_encode($poDetails)
        ];

        if ($existing) {
            // unset($poData['id']);
            // $this->db->update('purchase_orders', $poData, 'id = ?', [$poDetails['id']]);
        } else {
            $this->db->insert('purchase_orders', $poData);
        }

        // Sync PO items
        if (isset($poDetails['item']['items'])) {
            $this->syncPOItems($poDetails['id'], $poDetails['item']['items']);
        }
    }

    /**
     * Sync purchase order items
     */
    private function syncPOItems($poId, $items) {
        // Delete existing items
        $this->db->delete('po_items', 'po_id = ?', [$poId]);

        // Insert new items
        foreach ($items as $item) {
            $this->db->insert('po_items', [
                'po_id' => $poId,
                'line_number' => $item['line'] ?? 0,
                'item_id' => $item['item']['id'] ?? null,
                'item_name' => $item['item']['refName'] ?? '',
                'description' => $item['description'] ?? null,
                'quantity' => $item['quantity'] ?? 0,
                'vendor_quantity' => $item['quantityReceived'] ?? null,
                'rate' => $item['rate'] ?? 0,
                'amount' => $item['amount'] ?? 0,
                'netsuite_data' => json_encode($item)
            ]);
        }
    }

    /**
     * Sync items from NetSuite
     */
    public function syncItems($startedBy = null) {
        $this->syncLogId = $this->startSyncLog('items', $startedBy);

        try {
            $this->db->beginTransaction();

            $items = $this->netsuite->getItems();
            $stats = ['processed' => 0, 'created' => 0, 'updated' => 0, 'failed' => 0];

            foreach ($items as $item) {
                $stats['processed']++;

                try {
                    $existing = $this->db->fetchOne(
                        "SELECT * FROM items WHERE id = ?",
                        [$item['id']]
                    );

                    $itemData = [
                        'id' => $item['id'],
                        'item_id' => $item['itemid'] ?? '',
                        'name' => $item['displayname'] ?? $item['itemid'] ?? '',
                        'description' => $item['description'] ?? null,
                        'quantity' => $item['totalquantityonhand'] ?? 0,
                        'price' => $item['cost'] ?? 0,
                        'category' => $item['class'] ?? null,
                        'is_active' => ($item['isinactive'] ?? 'F') === 'F',
                        'netsuite_data' => json_encode($item)
                    ];

                    if ($existing) {
                        unset($itemData['id']);
                        $this->db->update('items', $itemData, 'id = ?', [$item['id']]);
                        $stats['updated']++;
                        
                        // Check for quantity changes and trigger notifications
                        $this->checkItemNotifications($item['id'], $existing['quantity'], $itemData['quantity']);
                    } else {
                        $this->db->insert('items', $itemData);
                        $stats['created']++;
                    }

                } catch (\Exception $e) {
                    $stats['failed']++;
                    error_log("Failed to sync item {$item['id']}: " . $e->getMessage());
                }
            }

            $this->db->commit();
            $this->completeSyncLog('completed', $stats);

            return [
                'success' => true,
                'stats' => $stats
            ];

        } catch (\Exception $e) {
            $this->db->rollback();
            $this->completeSyncLog('failed', [], $e->getMessage());
            
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Check and trigger item notifications
     */
    private function checkItemNotifications($itemId, $oldQty, $newQty) {
        // Get active notifications for this item
        $notifications = $this->db->fetchAll(
            "SELECT * FROM item_notifications WHERE item_id = ? AND is_active = 1",
            [$itemId]
        );

        $email = new EmailService();
        $item = $this->db->fetchOne("SELECT * FROM items WHERE id = ?", [$itemId]);

        foreach ($notifications as $notification) {
            $shouldNotify = false;
            $notificationType = '';

            if ($notification['notification_type'] === 'in_stock' && $oldQty == 0 && $newQty > 0) {
                $shouldNotify = true;
                $notificationType = 'in_stock';
            } elseif ($notification['notification_type'] === 'out_of_stock' && $oldQty > 0 && $newQty == 0) {
                $shouldNotify = true;
                $notificationType = 'out_of_stock';
            } elseif ($notification['notification_type'] === 'low_stock' && $newQty > 0 && $newQty < $notification['threshold']) {
                $shouldNotify = true;
                $notificationType = 'low_stock';
            }

            if ($shouldNotify) {
                // Get user email
                $user = $this->db->fetchOne("SELECT email FROM users WHERE id = ?", [$notification['user_id']]);
                if ($user) {
                    $email->sendItemNotification($user['email'], $item, $notificationType);
                    
                    // Update last notified time
                    $this->db->update('item_notifications',
                        ['last_notified_at' => date('Y-m-d H:i:s')],
                        'id = ?',
                        [$notification['id']]
                    );
                }
            }
        }
    }

    /**
     * Start sync log
     */
    private function startSyncLog($syncType, $startedBy = null) {
        return $this->db->insert('sync_logs', [
            'sync_type' => $syncType,
            'status' => 'running',
            'started_by' => $startedBy
        ]);
    }

    /**
     * Complete sync log
     */
    private function completeSyncLog($status, $stats = [], $errorMessage = null) {
        $this->db->update('sync_logs', [
            'status' => $status,
            'records_processed' => $stats['processed'] ?? 0,
            'records_created' => $stats['created'] ?? 0,
            'records_updated' => $stats['updated'] ?? 0,
            'records_failed' => $stats['failed'] ?? 0,
            'error_message' => $errorMessage,
            'completed_at' => date('Y-m-d H:i:s')
        ], 'id = ?', [$this->syncLogId]);
    }

    /**
     * Get status text
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
}