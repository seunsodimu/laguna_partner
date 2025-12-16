<?php
/**
 * NetSuite API Client
 * Handles all NetSuite REST API interactions
 */

namespace LagunaPartners;

class NetSuiteClient {
    private $config;
    private $credentials;
    private $accountId;
    private $baseUrl;

    public function __construct() {
        $this->config = require __DIR__ . '/../config/config.php';
        $this->credentials = require __DIR__ . '/../config/credentials.php';
        
        $env = $_ENV['NETSUITE_ENVIRONMENT'] ?? 'sandbox';
        $nsConfig = $this->credentials['netsuite'][$env];
        
        $this->accountId = $nsConfig['account_id'];
        $this->baseUrl = $nsConfig['rest_url'];
    }

    /**
     * Generate OAuth 1.0 signature for NetSuite
     */
    private function generateOAuthHeader($url, $method = 'GET') {
        $env = $_ENV['NETSUITE_ENVIRONMENT'] ?? 'sandbox';
        $nsConfig = $this->credentials['netsuite'][$env];

        $oauth = [
            'oauth_consumer_key' => $nsConfig['consumer_key'],
            'oauth_token' => $nsConfig['token_id'],
            'oauth_signature_method' => 'HMAC-SHA256',
            'oauth_timestamp' => time(),
            'oauth_nonce' => bin2hex(random_bytes(16)),
            'oauth_version' => '1.0'
        ];

        // Parse URL to separate base URL and query parameters
        $urlParts = parse_url($url);
        $baseUrl = $urlParts['scheme'] . '://' . $urlParts['host'] . $urlParts['path'];
        
        // Collect all parameters (OAuth + query string)
        $params = [];
        foreach ($oauth as $key => $value) {
            $params[rawurlencode($key)] = rawurlencode($value);
        }
        
        // Add query string parameters if present
        if (isset($urlParts['query'])) {
            parse_str($urlParts['query'], $queryParams);
            foreach ($queryParams as $key => $value) {
                $params[rawurlencode($key)] = rawurlencode($value);
            }
        }
        
        // Sort parameters
        ksort($params);
        
        // Build parameter string
        $paramString = [];
        foreach ($params as $key => $value) {
            $paramString[] = "$key=$value";
        }
        $paramString = implode('&', $paramString);
        
        // Build base string
        $baseString = $method . '&' . rawurlencode($baseUrl) . '&' . rawurlencode($paramString);

        // Generate signature
        $signingKey = rawurlencode($nsConfig['consumer_secret']) . '&' . rawurlencode($nsConfig['token_secret']);
        $signature = base64_encode(hash_hmac('sha256', $baseString, $signingKey, true));
        $oauth['oauth_signature'] = $signature;

        // Build header
        $headerParts = [];
        foreach ($oauth as $key => $value) {
            $headerParts[] = $key . '="' . rawurlencode($value) . '"';
        }

        return 'OAuth realm="' . $this->accountId . '",' . implode(',', $headerParts);
    }

    /**
     * Execute SuiteQL query
     */
    public function query($sql, $limit = 1000, $offset = 0) {
        $url = $this->baseUrl . '/query/v1/suiteql';
        
        // Add limit and offset as query parameters
        $queryParams = [];
        if ($limit) {
            $queryParams[] = "limit=$limit";
        }
        if ($offset) {
            $queryParams[] = "offset=$offset";
        }
        if (!empty($queryParams)) {
            $url .= '?' . implode('&', $queryParams);
        }
        
        $data = [
            'q' => $sql
        ];

        return $this->request('POST', $url, $data);
    }

    /**
     * Get a record by type and ID
     */
    public function getRecord($recordType, $id, $expandSubResources = true) {
        $url = $this->baseUrl . "/record/v1/$recordType/$id";
        if ($expandSubResources) {
            $url .= '?expandSubResources=true';
        }
        return $this->request('GET', $url);
    }

    /**
     * Update a record
     */
    public function updateRecord($recordType, $id, $data) {
        $url = $this->baseUrl . "/record/v1/$recordType/$id";
        return $this->request('PATCH', $url, $data);
    }

    /**
     * Create a record
     */
    public function createRecord($recordType, $data) {
        $url = $this->baseUrl . "/record/v1/$recordType";
        return $this->request('POST', $url, $data);
    }

    /**
     * Make HTTP request to NetSuite
     */
    private function request($method, $url, $data = null) {
        $ch = curl_init();

        $headers = [
            'Content-Type: application/json',
            'Prefer: transient',
            'Authorization: ' . $this->generateOAuthHeader($url, $method)
        ];

        // Allow disabling SSL verification for local development
        $verifySSL = ($_ENV['NETSUITE_VERIFY_SSL'] ?? 'true') === 'true';

        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_SSL_VERIFYPEER => $verifySSL,
            CURLOPT_SSL_VERIFYHOST => $verifySSL ? 2 : 0,
            CURLOPT_TIMEOUT => 60
        ]);

        if ($data && in_array($method, ['POST', 'PATCH', 'PUT'])) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        }

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error) {
            throw new \Exception("cURL Error: $error");
        }

        if ($httpCode >= 400) {
            $errorMsg = "NetSuite API Error (HTTP $httpCode): " . $response;
            error_log($errorMsg);
            throw new \Exception($errorMsg);
        }

        return json_decode($response, true);
    }

    /**
     * Get all vendors with portal access
     */
    public function getVendors() {
        $sql = "SELECT * FROM Vendor WHERE email IS NOT EMPTY AND custentitycustentity_portalaccess = 'T'";
        $results = [];
        $offset = 0;
        $limit = 1000;

        do {
            $response = $this->query($sql, $limit, $offset);
            if (isset($response['items'])) {
                $results = array_merge($results, $response['items']);
            }
            $offset += $limit;
        } while (isset($response['hasMore']) && $response['hasMore']);

        return $results;
    }

    /**
     * Get all dealers with portal access
     */
    public function getDealers() {
        $sql = "SELECT * FROM customer WHERE isperson='F' AND custentitycustentity_portalaccess='T'";
        $results = [];
        $offset = 0;
        $limit = 1000;

        do {
            $response = $this->query($sql, $limit, $offset);
            if (isset($response['items'])) {
                $results = array_merge($results, $response['items']);
            }
            $offset += $limit;
        } while (isset($response['hasMore']) && $response['hasMore']);

        return $results;
    }

    /**
     * Get all buyers (employees with portal access)
     */
    public function getBuyers() {
        $sql = "SELECT * FROM employee WHERE custentitycustentity_portalaccess='T'";
        $results = [];
        $offset = 0;
        $limit = 1000;

        do {
            $response = $this->query($sql, $limit, $offset);
            if (isset($response['items'])) {
                $results = array_merge($results, $response['items']);
            }
            $offset += $limit;
        } while (isset($response['hasMore']) && $response['hasMore']);

        return $results;
    }

    /**
     * Get purchase orders with specific statuses
     */
    public function getPurchaseOrders($statuses = ['B', 'E', 'F', 'H'], $vendorId = null) {
        $statusList = "'" . implode("','", $statuses) . "'";
        $sql = "SELECT * FROM transaction WHERE type='PurchOrd' AND status IN ($statusList) ORDER BY createddate DESC";
        
        if ($vendorId) {
            $sql .= " AND entity=$vendorId";
        }

        $results = [];
        $offset = 0;
        $limit = 1000;

        do {
            $response = $this->query($sql, $limit, $offset);
            if (isset($response['items'])) {
                $results = array_merge($results, $response['items']);
            }
            $offset += $limit;
        } while (isset($response['hasMore']) && $response['hasMore']);

        return $results;
    }

    /**
     * Get purchase order details
     */
    public function getPurchaseOrder($id) {
        return $this->getRecord('purchaseorder', $id, true);
    }

    /**
     * Get items for dealer portal
     */
    public function getItems() {
        $sql = "SELECT * FROM item WHERE custitem41='Y'";
        $results = [];
        $offset = 0;
        $limit = 1000;

        do {
            $response = $this->query($sql, $limit, $offset);
            if (isset($response['items'])) {
                $results = array_merge($results, $response['items']);
            }
            $offset += $limit;
        } while (isset($response['hasMore']) && $response['hasMore']);

        return $results;
    }

    /**
     * Update purchase order
     */
    public function updatePurchaseOrder($id, $data) {
        return $this->updateRecord('purchaseorder', $id, $data);
    }
}