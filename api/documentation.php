<?php
// API Documentation Endpoint
header('Content-Type: application/json; charset=utf-8');

$documentation = [
    'api_name' => 'HSMS Ethiopia API',
    'version' => '2.0.0',
    'base_url' => BASE_URL . 'api/',
    'description' => 'High School Management System API for Ethiopian Schools',
    'authentication' => [
        'methods' => [
            'jwt' => 'JSON Web Token in Authorization header (Bearer token)',
            'api_key' => 'API Key in X-API-Key header or api_key query parameter',
            'session' => 'PHP Session (for web application)'
        ],
        'endpoints' => [
            'POST /auth/login' => 'Login and get access token',
            'POST /auth/refresh' => 'Refresh access token',
            'POST /auth/logout' => 'Logout and invalidate tokens',
            'GET /auth/profile' => 'Get user profile',
            'PUT /auth/profile' => 'Update user profile',
            'POST /auth/change-password' => 'Change password'
        ]
    ],
    'endpoints' => [
        'dashboard' => [
            'GET /dashboard/stats' => 'Get dashboard statistics',
            'GET /dashboard/charts' => 'Get chart data',
            'GET /dashboard/notifications' => 'Get user notifications',
            'GET /dashboard/activities' => 'Get recent activities'
        ],
        'students' => [
            'GET /students' => 'List students with pagination and filters',
            'POST /students' => 'Create new student',
            'GET /students/{id}' => 'Get student details',
            'PUT /students/{id}' => 'Update student',
            'DELETE /students/{id}' => 'Delete student (soft delete)',
            'GET /students/search' => 'Search students',
            'GET /students/{id}/attendance' => 'Get student attendance',
            'GET /students/{id}/assessments' => 'Get student assessments',
            'GET /students/{id}/fees' => 'Get student fees'
        ],
        'teachers' => [
            'GET /teachers' => 'List teachers',
            'POST /teachers' => 'Create new teacher',
            'GET /teachers/{id}' => 'Get teacher details'
        ],
        'attendance' => [
            'GET /attendance' => 'Get attendance records',
            'POST /attendance/record' => 'Record attendance',
            'POST /attendance/bulk' => 'Record bulk attendance',
            'GET /attendance/report' => 'Get attendance report',
            'GET /attendance/today' => 'Get today\'s classes and attendance'
        ],
        'assessments' => [
            'GET /assessments' => 'Get assessments with filters',
            'POST /assessments/record' => 'Record assessment',
            'POST /assessments/bulk' => 'Record bulk assessments',
            'GET /assessments/grades' => 'Get student grades',
            'POST /assessments/{id}/finalize' => 'Finalize assessment'
        ],
        'fees' => [
            'GET /fees' => 'Get fees with filters',
            'GET /fees/payments' => 'Get payment records',
            'GET /fees/invoices' => 'Get student invoices',
            'POST /fees/record-payment' => 'Record payment',
            'POST /fees/waive-fee' => 'Waive fee amount',
            'GET /fees/structure' => 'Get fee structure',
            'POST /fees/structure' => 'Create fee structure item',
            'PUT /fees/structure' => 'Update fee structure item',
            'DELETE /fees/structure' => 'Delete fee structure item'
        ],
        'reports' => [
            'POST /reports/generate' => 'Generate report',
            'GET /reports/export' => 'Export report',
            'GET /reports/templates' => 'Get report templates'
        ],
        'notifications' => [
            'GET /notifications' => 'Get user notifications',
            'POST /notifications/send' => 'Send notification',
            'POST /notifications/{id}/read' => 'Mark notification as read'
        ],
        'system' => [
            'GET /system/settings' => 'Get system settings',
            'PUT /system/settings' => 'Update system settings',
            'POST /system/backup' => 'Create system backup',
            'GET /system/logs' => 'Get system logs'
        ]
    ],
    'response_format' => [
        'success' => 'boolean indicating success or failure',
        'data' => 'response data (array or object)',
        'message' => 'success or error message',
        'error' => 'error details (only on failure)',
        'pagination' => 'pagination info for list endpoints',
        'timestamp' => 'response timestamp'
    ],
    'status_codes' => [
        200 => 'OK - Request successful',
        201 => 'Created - Resource created successfully',
        400 => 'Bad Request - Invalid parameters',
        401 => 'Unauthorized - Authentication required',
        403 => 'Forbidden - Insufficient permissions',
        404 => 'Not Found - Resource not found',
        405 => 'Method Not Allowed - HTTP method not supported',
        409 => 'Conflict - Resource already exists',
        422 => 'Unprocessable Entity - Validation failed',
        429 => 'Too Many Requests - Rate limit exceeded',
        500 => 'Internal Server Error - Server error',
        503 => 'Service Unavailable - Maintenance mode'
    ],
    'rate_limiting' => [
        'limit' => '60 requests per minute per IP address',
        'headers' => [
            'X-RateLimit-Limit' => 'Request limit per minute',
            'X-RateLimit-Remaining' => 'Remaining requests',
            'X-RateLimit-Reset' => 'Time when limit resets'
        ]
    ],
    'pagination' => [
        'parameters' => [
            'page' => 'Page number (default: 1)',
            'limit' => 'Items per page (default: 25, max: 100)',
            'offset' => 'Offset for pagination (calculated automatically)'
        ],
        'response' => [
            'pagination' => [
                'page' => 'Current page',
                'limit' => 'Items per page',
                'total' => 'Total items',
                'pages' => 'Total pages'
            ]
        ]
    ],
    'filtering' => [
        'common_filters' => [
            'start_date' => 'Start date for date ranges',
            'end_date' => 'End date for date ranges',
            'academic_year' => 'Academic year filter',
            'term' => 'Academic term filter',
            'grade_level' => 'Grade level filter (9, 10, 11, 12)',
            'section' => 'Section filter',
            'status' => 'Status filter'
        ],
        'search' => [
            'q' => 'Search query string',
            'search' => 'Alternative search parameter'
        ]
    ],
    'sorting' => [
        'parameters' => [
            'sort' => 'Field to sort by',
            'order' => 'Sort order (asc or desc)'
        ],
        'default' => 'Most endpoints sort by relevant fields (date desc, name asc)'
    ],
    'data_types' => [
        'date' => 'YYYY-MM-DD format',
        'datetime' => 'YYYY-MM-DD HH:MM:SS format',
        'boolean' => 'true or false',
        'integer' => 'Whole numbers',
        'float' => 'Decimal numbers',
        'string' => 'Text data',
        'array' => 'JSON array',
        'object' => 'JSON object'
    ],
    'examples' => [
        'authentication' => [
            'login_request' => [
                'method' => 'POST',
                'url' => BASE_URL . 'api/auth/login',
                'headers' => ['Content-Type: application/json'],
                'body' => [
                    'username' => 'admin',
                    'password' => 'password123'
                ]
            ],
            'authenticated_request' => [
                'method' => 'GET',
                'url' => BASE_URL . 'api/dashboard/stats',
                'headers' => [
                    'Content-Type: application/json',
                    'Authorization: Bearer {access_token}'
                ]
            ]
        ],
        'student_list' => [
            'method' => 'GET',
            'url' => BASE_URL . 'api/students?page=1&limit=10&grade_level=10&status=active',
            'headers' => ['Authorization: Bearer {access_token}']
        ],
        'create_student' => [
            'method' => 'POST',
            'url' => BASE_URL . 'api/students',
            'headers' => [
                'Content-Type: application/json',
                'Authorization: Bearer {access_token}'
            ],
            'body' => [
                'first_name' => 'John',
                'last_name' => 'Doe',
                'gender' => 'Male',
                'date_of_birth' => '2007-05-15',
                'grade_level' => '10',
                'parent_name' => 'Jane Doe',
                'parent_phone' => '+251911223344'
            ]
        ]
    ],
    'error_handling' => [
        'standard_error' => [
            'success' => false,
            'error' => 'Error message',
            'code' => 'Error code (optional)',
            'details' => 'Additional error details (optional)'
        ],
        'validation_error' => [
            'success' => false,
            'error' => 'Validation failed',
            'errors' => [
                'field_name' => ['Error message 1', 'Error message 2']
            ]
        ]
    ],
    'changelog' => [
        '2.0.0' => [
            'date' => '2024-01-15',
            'changes' => [
                'Initial API release',
                'Complete CRUD operations for all major entities',
                'JWT authentication support',
                'Rate limiting implementation',
                'Comprehensive error handling'
            ]
        ]
    ],
    'support' => [
        'contact' => 'support@hsms.edu.et',
        'documentation' => BASE_URL . 'docs/api',
        'status' => BASE_URL . 'api/system/status'
    ]
];

echo json_encode($documentation, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
?>