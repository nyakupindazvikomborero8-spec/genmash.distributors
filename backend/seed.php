<?php
/**
 * Database Seeder
 * Run this after migrations to populate initial data
 */

require_once __DIR__ . '/../bootstrap/app.php';

echo "Genmashy Distributors - Database Seeder\n";
echo "=======================================\n\n";

$pdo = Database::getConnection();

try {
    $pdo->beginTransaction();
    
    // Check if data already exists
    $count = $pdo->query("SELECT COUNT(*) FROM `roles`")->fetchColumn();
    if ($count > 0) {
        echo "Database already seeded. Skipping...\n";
        $pdo->rollBack();
        exit(0);
    }

    // Roles
    echo "Seeding roles... ";
    $roles = [
        ['super_admin', 'Super Administrator', 'Full platform access including system configuration', '["*"]', 1],
        ['admin', 'Administrator', 'Manage products, customers, orders, quotations, enquiries', '["products.*", "categories.*", "customers.*", "orders.*", "quotations.*", "enquiries.*", "reports.*", "staff.view", "settings.view"]', 1],
        ['sales', 'Sales Staff', 'Manage customer enquiries, prepare quotations, manage assigned customers', '["customers.view", "customers.edit", "quotations.*", "enquiries.*", "products.view", "orders.view"]', 1],
        ['customer', 'Customer', 'Browse products, place orders, request quotations, manage own account', '["products.view", "orders.own", "quotations.own", "enquiries.own", "profile.own", "cart.*"]', 1],
    ];
    
    $stmt = $pdo->prepare("INSERT INTO `roles` (`name`, `display_name`, `description`, `permissions`, `is_system`) VALUES (?, ?, ?, ?, ?)");
    foreach ($roles as $role) {
        $stmt->execute($role);
    }
    echo "OK\n";

    // Permissions
    echo "Seeding permissions... ";
    $permissions = [
        ['products.create', 'Create Products', 'Create new products', 'products'],
        ['products.view', 'View Products', 'View product listings and details', 'products'],
        ['products.edit', 'Edit Products', 'Edit existing products', 'products'],
        ['products.delete', 'Delete Products', 'Delete or deactivate products', 'products'],
        ['categories.create', 'Create Categories', 'Create new categories', 'categories'],
        ['categories.view', 'View Categories', 'View category listings', 'categories'],
        ['categories.edit', 'Edit Categories', 'Edit existing categories', 'categories'],
        ['categories.delete', 'Delete Categories', 'Delete categories', 'categories'],
        ['customers.view', 'View Customers', 'View customer listings and details', 'customers'],
        ['customers.edit', 'Edit Customers', 'Edit customer profiles', 'customers'],
        ['customers.delete', 'Delete Customers', 'Delete customer accounts', 'customers'],
        ['orders.view', 'View Orders', 'View order listings and details', 'orders'],
        ['orders.edit', 'Edit Orders', 'Edit order details and status', 'orders'],
        ['orders.delete', 'Delete Orders', 'Delete or cancel orders', 'orders'],
        ['orders.own', 'View Own Orders', 'View own order history', 'orders'],
        ['quotations.create', 'Create Quotations', 'Create new quotations', 'quotations'],
        ['quotations.view', 'View Quotations', 'View quotation listings and details', 'quotations'],
        ['quotations.edit', 'Edit Quotations', 'Edit existing quotations', 'quotations'],
        ['quotations.delete', 'Delete Quotations', 'Delete quotations', 'quotations'],
        ['quotations.approve', 'Approve Quotations', 'Approve and send quotations', 'quotations'],
        ['quotations.own', 'View Own Quotations', 'View own quotation history', 'quotations'],
        ['enquiries.view', 'View Enquiries', 'View enquiry listings and details', 'enquiries'],
        ['enquiries.edit', 'Edit Enquiries', 'Edit enquiry details and status', 'enquiries'],
        ['enquiries.assign', 'Assign Enquiries', 'Assign enquiries to staff', 'enquiries'],
        ['enquiries.respond', 'Respond to Enquiries', 'Respond to customer enquiries', 'enquiries'],
        ['enquiries.own', 'View Own Enquiries', 'View own enquiry history', 'enquiries'],
        ['reports.view', 'View Reports', 'View business reports and analytics', 'reports'],
        ['reports.export', 'Export Reports', 'Export reports to CSV/PDF', 'reports'],
        ['staff.view', 'View Staff', 'View staff accounts', 'staff'],
        ['staff.create', 'Create Staff', 'Create new staff accounts', 'staff'],
        ['staff.edit', 'Edit Staff', 'Edit staff accounts', 'staff'],
        ['staff.delete', 'Delete Staff', 'Delete staff accounts', 'staff'],
        ['settings.view', 'View Settings', 'View system settings', 'settings'],
        ['settings.edit', 'Edit Settings', 'Edit system settings', 'settings'],
        ['profile.own', 'Manage Own Profile', 'Manage own profile and account', 'profile'],
        ['cart.create', 'Manage Cart', 'Add/update/remove cart items', 'cart'],
        ['cart.view', 'View Cart', 'View own cart', 'cart'],
    ];
    
    $stmt = $pdo->prepare("INSERT INTO `permissions` (`name`, `display_name`, `description`, `module`) VALUES (?, ?, ?, ?)");
    foreach ($permissions as $perm) {
        $stmt->execute($perm);
    }
    echo "OK\n";

    // Categories
    echo "Seeding categories... ";
    $categories = [
        [null, 'Solar Systems', 'solar-systems', 'Complete solar power systems including panels, inverters, and batteries', null, null, 1, 1, 1],
        [null, 'Starlink', 'starlink', 'Starlink satellite internet kits and installation', null, null, 2, 1, 1],
        [null, 'WiFi & Networking', 'wifi-networking', 'WiFi extenders, access points, and network equipment', null, null, 3, 1, 1],
        [null, 'Electrical', 'electrical', 'Electrical tubing, conduit, and wiring accessories', null, null, 4, 1, 1],
        [null, 'Mechanical Equipment', 'mechanical-equipment', 'Industrial mechanical equipment and parts', null, null, 5, 1, 0],
        [null, 'Electrical Equipment', 'electrical-equipment', 'Industrial electrical and electronic equipment', null, null, 6, 1, 0],
    ];
    
    $stmt = $pdo->prepare("INSERT INTO `categories` (`parent_id`, `name`, `slug`, `description`, `image`, `icon`, `sort_order`, `is_active`, `is_featured`) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
    foreach ($categories as $cat) {
        $stmt->execute($cat);
    }
    echo "OK\n";

    // Settings
    echo "Seeding settings... ";
    $settings = [
        ['site_name', 'Genmashy Distributors', 'string', 'general', 'Site Name', 'The name of the website', 1, null],
        ['site_tagline', 'Electrical Power & Internet Solutions', 'string', 'general', 'Site Tagline', 'Short description of the business', 1, null],
        ['site_email', 'sales@genmashy.co.zw', 'string', 'general', 'Contact Email', 'Primary contact email', 1, null],
        ['site_phone', '+263 718 751 792', 'string', 'general', 'Contact Phone', 'Primary contact phone', 1, null],
        ['site_whatsapp', '263718751792', 'string', 'general', 'WhatsApp Number', 'WhatsApp number for quotes', 1, null],
        ['site_address', '63 Mendel Road, Avondale, Harare, Zimbabwe', 'string', 'general', 'Business Address', 'Physical business address', 1, null],
        ['currency_default', 'USD', 'string', 'currency', 'Default Currency', 'Default currency for the store', 1, null],
        ['currency_usd_enabled', '1', 'boolean', 'currency', 'Enable USD', 'Enable USD currency', 1, null],
        ['currency_zwg_enabled', '1', 'boolean', 'currency', 'Enable ZWG', 'Enable ZWG currency', 1, null],
        ['tax_enabled', '1', 'boolean', 'tax', 'Enable Tax', 'Enable tax calculations', 1, null],
        ['tax_rate', '15.00', 'float', 'tax', 'Tax Rate (%)', 'Default tax rate percentage', 1, null],
        ['tax_inclusive', '0', 'boolean', 'tax', 'Tax Inclusive', 'Whether prices include tax', 1, null],
        ['shipping_enabled', '1', 'boolean', 'shipping', 'Enable Shipping', 'Enable shipping calculations', 1, null],
        ['shipping_free_threshold_usd', '500.00', 'float', 'shipping', 'Free Shipping Threshold (USD)', 'Order amount for free shipping in USD', 1, null],
        ['shipping_free_threshold_zwg', '5000.00', 'float', 'shipping', 'Free Shipping Threshold (ZWG)', 'Order amount for free shipping in ZWG', 1, null],
        ['quotation_validity_days', '30', 'integer', 'quotations', 'Quotation Validity (Days)', 'Default quotation validity period', 1, null],
        ['quotation_prefix', 'GMD-Q', 'string', 'quotations', 'Quotation Number Prefix', 'Prefix for quotation numbers', 1, null],
        ['order_prefix', 'GMD-ORD', 'string', 'orders', 'Order Number Prefix', 'Prefix for order numbers', 1, null],
        ['payment_prefix', 'GMD-PAY', 'string', 'payments', 'Payment Number Prefix', 'Prefix for payment numbers', 1, null],
        ['enquiry_prefix', 'GMD-ENQ', 'string', 'enquiries', 'Enquiry Number Prefix', 'Prefix for enquiry numbers', 1, null],
        ['session_lifetime', '7200', 'integer', 'security', 'Session Lifetime (seconds)', 'Session lifetime in seconds', 0, null],
        ['max_login_attempts', '5', 'integer', 'security', 'Max Login Attempts', 'Maximum failed login attempts before lockout', 0, null],
        ['lockout_duration', '900', 'integer', 'security', 'Lockout Duration (seconds)', 'Account lockout duration in seconds', 0, null],
        ['password_min_length', '8', 'integer', 'security', 'Minimum Password Length', 'Minimum password length', 0, null],
        ['require_email_verification', '1', 'boolean', 'security', 'Require Email Verification', 'Require email verification for new accounts', 0, null],
        ['maintenance_mode', '0', 'boolean', 'system', 'Maintenance Mode', 'Enable maintenance mode', 0, null],
        ['items_per_page', '20', 'integer', 'system', 'Items Per Page', 'Default pagination limit', 0, null],
    ];
    
    $stmt = $pdo->prepare("INSERT INTO `settings` (`key`, `value`, `type`, `group`, `label`, `description`, `is_public`, `validation_rules`) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
    foreach ($settings as $setting) {
        $stmt->execute($setting);
    }
    echo "OK\n";

    // Default admin user (password: Admin@123)
    echo "Creating default admin user... ";
    $uuid = sprintf(
        '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
        mt_rand(0, 0xffff), mt_rand(0, 0xffff),
        mt_rand(0, 0xffff),
        mt_rand(0, 0x0fff) | 0x4000,
        mt_rand(0, 0x3fff) | 0x8000,
        mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
    );
    
    $passwordHash = password_hash('Admin@123', PASSWORD_BCRYPT, ['cost' => 10]);
    
    $stmt = $pdo->prepare("INSERT INTO `users` (`uuid`, `role_id`, `email`, `password_hash`, `first_name`, `last_name`, `phone`, `is_active`, `email_verified_at`) VALUES (?, 1, ?, ?, ?, ?, ?, 1, NOW())");
    $stmt->execute([$uuid, 'admin@genmashy.co.zw', $passwordHash, 'Super', 'Admin', '+263718751792']);
    echo "OK\n";

    $pdo->commit();
    echo "\nDatabase seeded successfully!\n";
    echo "\nDefault admin credentials:\n";
    echo "  Email: admin@genmashy.co.zw\n";
    echo "  Password: Admin@123\n";
    echo "\nIMPORTANT: Change the default password immediately after first login!\n";
    
} catch (Exception $e) {
    $pdo->rollBack();
    echo "\nError: {$e->getMessage()}\n";
    exit(1);
}