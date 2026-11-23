# Genmashy Distributors - Complete E-commerce Platform

A modern, secure, and professional e-commerce platform for Genmashy Distributors built with PHP 8.3+ and MySQL.

## Features

### Public Website
- Modern, responsive design with existing brand identity
- Product catalog with categories, search, and filtering
- Service pages (Solar, Starlink, WiFi, Electrical)
- Contact forms with WhatsApp integration
- Quotation request system

### E-commerce System
- Shopping cart with multi-currency support (USD/ZWG)
- Secure checkout process
- Order management with status tracking
- Inventory management with stock tracking
- Multiple payment methods (Cash, Bank Transfer, Mobile Money, EcoCash, OneMoney, Paynow)

### Customer Portal
- User registration with email verification
- Secure authentication with password hashing
- Order history and tracking
- Quotation history
- Enquiry management
- Address book
- Profile management

### Quotation Management
- Customer quotation requests
- Admin quotation preparation with USD/ZWG pricing
- Discount, tax, and delivery calculations
- PDF generation
- Status workflow (Pending → Under Review → Prepared → Sent → Accepted/Rejected/Expired)

### Enquiry Management
- Customer enquiries with categorization
- Priority levels (Low, Normal, High, Urgent)
- Status tracking (New → In Progress → Responded → Resolved → Closed)
- Assignment to staff members
- Internal notes and customer responses

### Live Chat
- Real-time customer support chat
- Conversation management
- Agent assignment
- Message history
- Notifications

### Administration Dashboard
- Statistics overview (customers, products, orders, quotations, enquiries)
- Sales charts and reports
- Product management (CRUD, images, variants)
- Category management
- Order management
- Quotation management
- Enquiry management
- User management with roles
- System settings
- Business reports (sales, products, customers)

### Security Features
- CSRF protection
- XSS prevention
- SQL injection prevention (prepared statements)
- Secure password hashing (bcrypt)
- Rate limiting
- Session management
- Role-based access control (Super Admin, Admin, Sales, Customer)
- Account lockout after failed attempts
- Secure file uploads

## Technology Stack

- **Backend**: PHP 8.3+
- **Database**: MySQL 8.0+ / MariaDB 10.5+
- **Frontend**: HTML5, CSS3, JavaScript (ES6+)
- **CSS Framework**: Custom (compatible with Bootstrap 5)
- **Email**: PHPMailer with SMTP
- **Dependencies**: Composer

## Requirements

- PHP 8.3 or higher
- MySQL 8.0+ or MariaDB 10.5+
- Composer 2.x
- Apache/Nginx with mod_rewrite
- SSL certificate (for production)

## Installation

### 1. Clone/Extract the Project

```bash
cd /path/to/genmash/backend
```

### 2. Install Dependencies

```bash
composer install
```

### 3. Configure Environment

```bash
cp .env.example .env
```

Edit `.env` with your configuration:

```env
# Application
APP_ENV=production
APP_DEBUG=false
APP_URL=https://genmashy.co.zw

# Database
DB_HOST=localhost
DB_PORT=3306
DB_DATABASE=genmashy
DB_USERNAME=your_db_user
DB_PASSWORD=your_secure_password

# Session
SESSION_LIFETIME=7200
SESSION_DOMAIN=.genmashy.co.zw

# Security
ALLOWED_HOSTS=genmashy.co.zw,www.genmashy.co.zw
MAX_LOGIN_ATTEMPTS=5
LOCKOUT_DURATION=900
PASSWORD_MIN_LENGTH=8
REQUIRE_EMAIL_VERIFICATION=true

# Mail (SMTP)
MAIL_MAILER=smtp
MAIL_HOST=smtp.gmail.com
MAIL_PORT=587
MAIL_ENCRYPTION=tls
MAIL_USERNAME=your_smtp_username
MAIL_PASSWORD=your_smtp_password
MAIL_FROM_ADDRESS=sales@genmashy.co.zw
MAIL_FROM_NAME="Genmashy Distributors"
```

### 4. Create Database

```sql
CREATE DATABASE genmashy CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'genmashy'@'localhost' IDENTIFIED BY 'your_secure_password';
GRANT ALL PRIVILEGES ON genmashy.* TO 'genmashy'@'localhost';
FLUSH PRIVILEGES;
```

### 5. Run Migrations

```bash
php migrate.php
```

### 6. Seed Database (Optional)

```bash
php seed.php
```

This creates:
- Default roles and permissions
- Product categories
- System settings
- Default admin user (admin@genmashy.co.zw / Admin@123)

**⚠️ Change the default admin password immediately!**

### 7. Configure Web Server

#### Apache (.htaccess included in public/)

```apache
<VirtualHost *:80>
    ServerName genmashy.co.zw
    ServerAlias www.genmashy.co.zw
    DocumentRoot /path/to/genmash/backend/public
    
    <Directory /path/to/genmash/backend/public>
        AllowOverride All
        Require all granted
    </Directory>
    
    # Redirect HTTP to HTTPS
    RewriteEngine On
    RewriteCond %{HTTPS} off
    RewriteRule ^(.*)$ https://%{HTTP_HOST}%{REQUEST_URI} [L,R=301]
</VirtualHost>

<VirtualHost *:443>
    ServerName genmashy.co.zw
    ServerAlias www.genmashy.co.zw
    DocumentRoot /path/to/genmash/backend/public
    
    SSLEngine on
    SSLCertificateFile /path/to/certificate.crt
    SSLCertificateKeyFile /path/to/private.key
    
    <Directory /path/to/genmash/backend/public>
        AllowOverride All
        Require all granted
    </Directory>
</VirtualHost>
```

#### Nginx

```nginx
server {
    listen 80;
    server_name genmashy.co.zw www.genmashy.co.zw;
    return 301 https://$server_name$request_uri;
}

server {
    listen 443 ssl http2;
    server_name genmashy.co.zw www.genmashy.co.zw;
    root /path/to/genmash/backend/public;
    index index.php web.php;

    ssl_certificate /path/to/certificate.crt;
    ssl_certificate_key /path/to/private.key;

    location / {
        try_files $uri $uri/ /web.php?$query_string;
    }

    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.3-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        include fastcgi_params;
    }

    # Deny access to sensitive files
    location ~ /\.(env|git|htaccess) {
        deny all;
    }
}
```

### 8. Set Permissions

```bash
chmod -R 755 storage/
chmod -R 755 public/storage/
chmod -R 755 public/uploads/
```

### 9. Set Up Cron Jobs

```bash
# Process email queue every minute
* * * * * cd /path/to/genmash/backend && php artisan schedule:run >> /dev/null 2>&1

# Or manually run email processor
* * * * * cd /path/to/genmash/backend && php -r "require 'bootstrap/app.php'; EmailService::processQueue();" >> /dev/null 2>&1
```

## Project Structure

```
backend/
├── bootstrap/
│   └── app.php              # Application bootstrap
├── config/
│   └── config.php           # Main configuration
├── migrations/
│   └── 001_initial_schema.sql  # Database schema
├── public/
│   ├── index.php            # API entry point
│   ├── web.php              # Web entry point
│   ├── .htaccess            # Apache config
│   └── assets/              # Static assets
├── resources/
│   ├── views/               # Blade-like view templates
│   └── emails/              # Email templates
├── routes/
│   ├── api.php              # API routes
│   └── web.php              # Web routes
├── src/
│   ├── Core/                # Core classes (Config, Database, Model, Router)
│   ├── Models/              # Eloquent-like models
│   ├── Services/            # Business logic services
│   ├── Controllers/         # Controllers (API, Web, Admin)
│   └── Middleware/          # Middleware (Auth, CSRF, RateLimit)
├── storage/
│   ├── logs/                # Application logs
│   ├── uploads/             # File uploads
│   └── cache/               # File cache
├── migrate.php              # Migration runner
├── seed.php                 # Database seeder
└── composer.json            # Composer dependencies
```

## API Endpoints

### Authentication
- `POST /api/auth/register` - Register new customer
- `POST /api/auth/login` - Login
- `POST /api/auth/logout` - Logout
- `POST /api/auth/forgot-password` - Request password reset
- `POST /api/auth/reset-password` - Reset password
- `GET /api/auth/verify-email` - Verify email
- `POST /api/auth/resend-verification` - Resend verification email

### Products
- `GET /api/products` - List products (with filters)
- `GET /api/products/featured` - Featured products
- `GET /api/products/search` - Search products
- `GET /api/products/{slug}` - Product detail
- `GET /api/products/{id}/related` - Related products

### Categories
- `GET /api/categories` - List categories
- `GET /api/categories/tree` - Category tree
- `GET /api/categories/{slug}` - Category with products

### Cart (Auth Required)
- `GET /api/cart` - Get cart
- `POST /api/cart` - Add to cart
- `PUT /api/cart/{id}` - Update quantity
- `DELETE /api/cart/{id}` - Remove item
- `DELETE /api/cart` - Clear cart
- `PUT /api/cart/currency` - Set currency

### Orders (Auth Required)
- `GET /api/orders` - List orders
- `POST /api/orders` - Create order
- `GET /api/orders/{id}` - Order detail
- `POST /api/orders/{id}/cancel` - Cancel order

### Quotations (Auth Required)
- `GET /api/quotations` - List quotations
- `POST /api/quotations` - Request quotation
- `GET /api/quotations/{id}` - Quotation detail
- `POST /api/quotations/{id}/accept` - Accept quotation
- `POST /api/quotations/{id}/reject` - Reject quotation

### Enquiries (Auth Required)
- `GET /api/enquiries` - List enquiries
- `POST /api/enquiries` - Submit enquiry
- `GET /api/enquiries/{id}` - Enquiry detail
- `POST /api/enquiries/{id}/respond` - Respond to enquiry

### Chat (Auth Required)
- `GET /api/chat/conversations` - List conversations
- `POST /api/chat/conversations` - Start chat
- `GET /api/chat/conversations/{id}` - Conversation detail
- `POST /api/chat/conversations/{id}/messages` - Send message
- `PUT /api/chat/conversations/{id}/read` - Mark as read

### Notifications (Auth Required)
- `GET /api/notifications` - List notifications
- `PUT /api/notifications/{id}/read` - Mark as read
- `PUT /api/notifications/read-all` - Mark all as read

### User Profile (Auth Required)
- `GET /api/user` - Get profile
- `PUT /api/user` - Update profile
- `PUT /api/user/password` - Change password
- `GET /api/user/addresses` - List addresses
- `POST /api/user/addresses` - Add address
- `PUT /api/user/addresses/{id}` - Update address
- `DELETE /api/user/addresses/{id}` - Delete address
- `POST /api/user/addresses/{id}/default` - Set default

### Admin (Admin Role Required)
- `GET /api/admin/dashboard/stats` - Dashboard stats
- `GET /api/admin/dashboard/charts` - Dashboard charts
- `GET /api/admin/products` - Manage products
- `GET /api/admin/categories` - Manage categories
- `GET /api/admin/orders` - Manage orders
- `GET /api/admin/quotations` - Manage quotations
- `GET /api/admin/enquiries` - Manage enquiries
- `GET /api/admin/users` - Manage users
- `GET /api/admin/settings` - System settings
- `GET /api/admin/reports/*` - Business reports

## Deployment Checklist

- [ ] Backup existing website files and database
- [ ] Verify PHP 8.3+ and required extensions
- [ ] Verify MySQL 8.0+ availability
- [ ] Configure `.env` with production values
- [ ] Run migrations and seed database
- [ ] Configure web server (Apache/Nginx)
- [ ] Set up SSL certificate
- [ ] Configure SMTP for emails
- [ ] Test all functionality locally
- [ ] Set up cron jobs for email queue
- [ ] Configure backup strategy
- [ ] Deploy to production
- [ ] Verify all pages and forms
- [ ] Test email delivery
- [ ] Verify database operations
- [ ] Change default admin password
- [ ] Set up monitoring and logging

## Security Considerations

1. **Never commit `.env` file** - Add to `.gitignore`
2. **Use strong passwords** for database and admin accounts
3. **Enable HTTPS** in production
3. **Regular updates** - Keep PHP and dependencies updated
4. **Monitor logs** - Check `storage/logs/` regularly
5. **Backup strategy** - Daily database backups, weekly file backups
6. **Rate limiting** - Configured for login, registration, API
7. **File uploads** - Validated for type and size
8. **Session security** - HttpOnly, Secure, SameSite cookies

## Support

For issues or questions:
- Email: sales@genmashy.co.zw
- Phone: +263 718 751 792
- WhatsApp: +263 718 751 792

## License

Proprietary - Genmashy Distributors. All rights reserved.