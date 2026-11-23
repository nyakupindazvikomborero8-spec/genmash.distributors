<?php
/**
 * Genmashy Distributors - Configuration
 * 
 * Copy this file to config.php and update with your environment-specific values.
 * Never commit config.php with real credentials to version control.
 */

return [
    // Application
    'app' => [
        'name' => 'Genmashy Distributors',
        'env' => $_ENV['APP_ENV'] ?? 'production',
        'debug' => filter_var($_ENV['APP_DEBUG'] ?? false, FILTER_VALIDATE_BOOLEAN),
        'url' => $_ENV['APP_URL'] ?? 'https://genmashy.co.zw',
        'timezone' => 'Africa/Harare',
        'locale' => 'en',
        'maintenance_mode' => filter_var($_ENV['MAINTENANCE_MODE'] ?? false, FILTER_VALIDATE_BOOLEAN),
    ],

    // Database
    'database' => [
        'driver' => 'mysql',
        'host' => $_ENV['DB_HOST'] ?? 'localhost',
        'port' => (int)($_ENV['DB_PORT'] ?? 3306),
        'database' => $_ENV['DB_DATABASE'] ?? 'genmashy',
        'username' => $_ENV['DB_USERNAME'] ?? 'genmashy',
        'password' => $_ENV['DB_PASSWORD'] ?? '',
        'charset' => 'utf8mb4',
        'collation' => 'utf8mb4_unicode_ci',
        'prefix' => '',
        'strict' => true,
        'engine' => 'InnoDB',
        'options' => [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci",
        ],
    ],

    // Session
    'session' => [
        'driver' => 'database',
        'lifetime' => (int)($_ENV['SESSION_LIFETIME'] ?? 7200),
        'expire_on_close' => false,
        'encrypt' => true,
        'files' => __DIR__ . '/../storage/framework/sessions',
        'connection' => null,
        'table' => 'user_sessions',
        'store' => null,
        'lottery' => [2, 100],
        'cookie' => [
            'name' => 'genmashy_session',
            'path' => '/',
            'domain' => $_ENV['SESSION_DOMAIN'] ?? '',
            'secure' => true,
            'httponly' => true,
            'samesite' => 'lax',
        ],
    ],

    // Security
    'security' => [
        'csrf_token_name' => '_token',
        'csrf_header_name' => 'X-CSRF-TOKEN',
        'max_login_attempts' => (int)($_ENV['MAX_LOGIN_ATTEMPTS'] ?? 5),
        'lockout_duration' => (int)($_ENV['LOCKOUT_DURATION'] ?? 900),
        'password_min_length' => (int)($_ENV['PASSWORD_MIN_LENGTH'] ?? 8),
        'require_email_verification' => filter_var($_ENV['REQUIRE_EMAIL_VERIFICATION'] ?? true, FILTER_VALIDATE_BOOLEAN),
        'bcrypt_cost' => 10,
        'allowed_hosts' => array_filter(array_map('trim', explode(',', $_ENV['ALLOWED_HOSTS'] ?? 'genmashy.co.zw,www.genmashy.co.zw'))),
        'trusted_proxies' => array_filter(array_map('trim', explode(',', $_ENV['TRUSTED_PROXIES'] ?? ''))),
        'rate_limits' => [
            'login' => ['attempts' => 5, 'window' => 300],
            'register' => ['attempts' => 3, 'window' => 3600],
            'password_reset' => ['attempts' => 3, 'window' => 3600],
            'api' => ['attempts' => 60, 'window' => 60],
        ],
    ],

    // Mail
    'mail' => [
        'default' => $_ENV['MAIL_MAILER'] ?? 'smtp',
        'mailers' => [
            'smtp' => [
                'transport' => 'smtp',
                'host' => $_ENV['MAIL_HOST'] ?? 'smtp.gmail.com',
                'port' => (int)($_ENV['MAIL_PORT'] ?? 587),
                'encryption' => $_ENV['MAIL_ENCRYPTION'] ?? 'tls',
                'username' => $_ENV['MAIL_USERNAME'] ?? '',
                'password' => $_ENV['MAIL_PASSWORD'] ?? '',
                'timeout' => 30,
                'auth_mode' => null,
            ],
            'sendmail' => [
                'transport' => 'sendmail',
                'path' => '/usr/sbin/sendmail -bs',
            ],
            'log' => [
                'transport' => 'log',
                'channel' => 'mail',
            ],
        ],
        'from' => [
            'address' => $_ENV['MAIL_FROM_ADDRESS'] ?? 'sales@genmashy.co.zw',
            'name' => $_ENV['MAIL_FROM_NAME'] ?? 'Genmashy Distributors',
        ],
        'reply_to' => [
            'address' => $_ENV['MAIL_REPLY_TO_ADDRESS'] ?? 'sales@genmashy.co.zw',
            'name' => $_ENV['MAIL_REPLY_TO_NAME'] ?? 'Genmashy Support',
        ],
    ],

    // Files
    'filesystems' => [
        'default' => 'local',
        'disks' => [
            'local' => [
                'driver' => 'local',
                'root' => __DIR__ . '/../storage/app',
                'throw' => false,
            ],
            'public' => [
                'driver' => 'local',
                'root' => __DIR__ . '/../public/storage',
                'url' => $_ENV['APP_URL'] ?? 'https://genmashy.co.zw' . '/storage',
                'visibility' => 'public',
                'throw' => false,
            ],
            'uploads' => [
                'driver' => 'local',
                'root' => __DIR__ . '/../storage/uploads',
                'url' => $_ENV['APP_URL'] ?? 'https://genmashy.co.zw' . '/uploads',
                'visibility' => 'public',
                'throw' => false,
            ],
        ],
    ],

    // Cache
    'cache' => [
        'default' => $_ENV['CACHE_DRIVER'] ?? 'file',
        'stores' => [
            'file' => [
                'driver' => 'file',
                'path' => __DIR__ . '/../storage/cache',
            ],
            'database' => [
                'driver' => 'database',
                'table' => 'cache',
                'connection' => null,
            ],
            'redis' => [
                'driver' => 'redis',
                'connection' => 'default',
            ],
        ],
    ],

    // Queue
    'queue' => [
        'default' => $_ENV['QUEUE_CONNECTION'] ?? 'sync',
        'connections' => [
            'sync' => [
                'driver' => 'sync',
            ],
            'database' => [
                'driver' => 'database',
                'table' => 'jobs',
                'queue' => 'default',
                'retry_after' => 90,
            ],
            'redis' => [
                'driver' => 'redis',
                'connection' => 'default',
                'queue' => 'default',
                'retry_after' => 90,
            ],
        ],
    ],

    // Currency
    'currency' => [
        'default' => 'USD',
        'supported' => ['USD', 'ZWG'],
        'symbols' => [
            'USD' => '$',
            'ZWG' => 'ZWG$',
        ],
        'decimals' => 2,
        'thousands_separator' => ',',
        'decimal_separator' => '.',
    ],

    // Pagination
    'pagination' => [
        'default_per_page' => 20,
        'max_per_page' => 100,
    ],

    // Upload
    'upload' => [
        'max_size' => 10 * 1024 * 1024, // 10MB
        'allowed_mimes' => [
            'image/jpeg',
            'image/png',
            'image/gif',
            'image/webp',
            'application/pdf',
        ],
        'allowed_extensions' => ['jpg', 'jpeg', 'png', 'gif', 'webp', 'pdf'],
    ],

    // Quotation
    'quotation' => [
        'prefix' => 'GMD-Q',
        'validity_days' => 30,
        'tax_rate' => 15.00,
    ],

    // Orders
    'orders' => [
        'prefix' => 'GMD-ORD',
        'statuses' => [
            'pending' => 'Pending',
            'confirmed' => 'Confirmed',
            'processing' => 'Processing',
            'shipped' => 'Shipped',
            'delivered' => 'Delivered',
            'cancelled' => 'Cancelled',
            'refunded' => 'Refunded',
            'failed' => 'Failed',
        ],
        'payment_statuses' => [
            'pending' => 'Pending',
            'paid' => 'Paid',
            'partial' => 'Partial',
            'refunded' => 'Refunded',
            'failed' => 'Failed',
        ],
    ],

    // Enquiries
    'enquiries' => [
        'prefix' => 'GMD-ENQ',
        'types' => [
            'general' => 'General',
            'product' => 'Product Enquiry',
            'quotation' => 'Quotation Request',
            'support' => 'Support',
            'after_sales' => 'After Sales',
            'complaint' => 'Complaint',
        ],
        'priorities' => [
            'low' => 'Low',
            'normal' => 'Normal',
            'high' => 'High',
            'urgent' => 'Urgent',
        ],
        'statuses' => [
            'new' => 'New',
            'in_progress' => 'In Progress',
            'responded' => 'Responded',
            'resolved' => 'Resolved',
            'closed' => 'Closed',
        ],
    ],

    // Chat
    'chat' => [
        'statuses' => [
            'open' => 'Open',
            'waiting_customer' => 'Waiting Customer',
            'waiting_agent' => 'Waiting Agent',
            'closed' => 'Closed',
        ],
    ],

    // Logging
    'logging' => [
        'default' => 'daily',
        'channels' => [
            'daily' => [
                'driver' => 'daily',
                'path' => __DIR__ . '/../storage/logs/app.log',
                'level' => 'info',
                'days' => 30,
            ],
            'error' => [
                'driver' => 'daily',
                'path' => __DIR__ . '/../storage/logs/error.log',
                'level' => 'error',
                'days' => 30,
            ],
            'security' => [
                'driver' => 'daily',
                'path' => __DIR__ . '/../storage/logs/security.log',
                'level' => 'warning',
                'days' => 90,
            ],
        ],
    ],
];