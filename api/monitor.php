<?php
// API Monitoring Endpoint (for admins only)
require_once __DIR__ . '/index.php';

if ($this->user['role'] !== 'admin') {
    $this->jsonResponse(['success' => false, 'error' => 'Admin only'], 403);
}

$stats = [
    'api_requests' => [
        'total' => $this->getTotalRequests(),
        'today' => $this->getTodayRequests(),
        'by_endpoint' => $this->getRequestsByEndpoint(),
        'by_method' => $this->getRequestsByMethod()
    ],
    'performance' => [
        'average_response_time' => $this->getAverageResponseTime(),
        'error_rate' => $this->getErrorRate(),
        'most_used_endpoints' => $this->getMostUsedEndpoints()
    ],
    'users' => [
        'active_tokens' => $this->getActiveTokens(),
        'api_keys' => $this->getApiKeyStats()
    ],
    'system' => [
        'uptime' => $this->getUptime(),
        'memory_usage' => memory_get_usage(true),
        'last_backup' => $this->getLastBackupTime()
    ]
];

$this->jsonResponse(['success' => true, 'stats' => $stats]);
?>