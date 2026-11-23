<?php
/**
 * Web Routes
 * 
 * These routes serve the frontend pages. The actual frontend is in the root directory.
 * This router can be used for server-side rendered pages or as a fallback.
 */

use Controllers\Web\HomeController;
use Controllers\Web\AuthController;
use Controllers\Web\AccountController;
use Controllers\Web\ProductController;
use Controllers\Web\CartController;
use Controllers\Web\CheckoutController;
use Controllers\Web\OrderController;
use Controllers\Web\QuotationController;
use Controllers\Web\EnquiryController;
use Controllers\Web\ContactController;

// Public pages
Router::get('/', [HomeController::class, 'index']);
Router::get('/home', [HomeController::class, 'index']);

Router::get('/about', [HomeController::class, 'about']);
Router::get('/services', [HomeController::class, 'services']);
Router::get('/services/{slug}', [HomeController::class, 'serviceDetail']);
Router::get('/products', [ProductController::class, 'index']);
Router::get('/products/{slug}', [ProductController::class, 'show']);
Router::get('/categories/{slug}', [ProductController::class, 'category']);

Router::get('/contact', [ContactController::class, 'index']);
Router::post('/contact', [ContactController::class, 'submit']);

Router::get('/quote', [QuotationController::class, 'create']);
Router::post('/quote', [QuotationController::class, 'store']);

Router::get('/enquiry', [EnquiryController::class, 'create']);
Router::post('/enquiry', [EnquiryController::class, 'store']);

// Authentication pages
Router::get('/login', [AuthController::class, 'showLogin'])->middleware('guest');
Router::post('/login', [AuthController::class, 'login'])->middleware('guest');
Router::get('/register', [AuthController::class, 'showRegister'])->middleware('guest');
Router::post('/register', [AuthController::class, 'register'])->middleware('guest');
Router::get('/logout', [AuthController::class, 'logout']);
Router::get('/forgot-password', [AuthController::class, 'showForgotPassword'])->middleware('guest');
Router::post('/forgot-password', [AuthController::class, 'forgotPassword'])->middleware('guest');
Router::get('/reset-password', [AuthController::class, 'showResetPassword'])->middleware('guest');
Router::post('/reset-password', [AuthController::class, 'resetPassword'])->middleware('guest');
Router::get('/verify-email', [AuthController::class, 'verifyEmail']);
Router::get('/resend-verification', [AuthController::class, 'resendVerification']);

// Protected account pages
Router::group(['middleware' => ['auth']], function () {
    Router::get('/account', [AccountController::class, 'dashboard']);
    Router::get('/account/profile', [AccountController::class, 'profile']);
    Router::put('/account/profile', [AccountController::class, 'updateProfile']);
    Router::put('/account/password', [AccountController::class, 'changePassword']);
    Router::get('/account/addresses', [AccountController::class, 'addresses']);
    Router::post('/account/addresses', [AccountController::class, 'storeAddress']);
    Router::put('/account/addresses/{id}', [AccountController::class, 'updateAddress']);
    Router::delete('/account/addresses/{id}', [AccountController::class, 'deleteAddress']);
    Router::post('/account/addresses/{id}/default', [AccountController::class, 'setDefaultAddress']);

    Router::get('/account/orders', [OrderController::class, 'index']);
    Router::get('/account/orders/{id}', [OrderController::class, 'show']);
    Router::post('/account/orders/{id}/cancel', [OrderController::class, 'cancel']);

    Router::get('/account/quotations', [AccountController::class, 'quotations']);
    Router::get('/account/quotations/{id}', [AccountController::class, 'quotationDetail']);
    Router::post('/account/quotations/{id}/accept', [AccountController::class, 'acceptQuotation']);
    Router::post('/account/quotations/{id}/reject', [AccountController::class, 'rejectQuotation']);

    Router::get('/account/enquiries', [AccountController::class, 'enquiries']);
    Router::get('/account/enquiries/{id}', [AccountController::class, 'enquiryDetail']);
    Router::post('/account/enquiries/{id}/respond', [AccountController::class, 'respondEnquiry']);

    Router::get('/cart', [CartController::class, 'index']);
    Router::post('/cart/add', [CartController::class, 'add']);
    Router::put('/cart/update/{id}', [CartController::class, 'update']);
    Router::delete('/cart/remove/{id}', [CartController::class, 'remove']);
    Router::post('/cart/clear', [CartController::class, 'clear']);

    Router::get('/checkout', [CheckoutController::class, 'index']);
    Router::post('/checkout', [CheckoutController::class, 'process']);
    Router::get('/checkout/success/{orderNumber}', [CheckoutController::class, 'success']);
});

// Admin panel (requires admin role)
Router::group(['middleware' => ['auth', 'role:super_admin,admin']], function () {
    Router::get('/admin', [App\Controllers\Admin\DashboardController::class, 'index']);
    Router::get('/admin/dashboard', [App\Controllers\Admin\DashboardController::class, 'index']);
    
    // Products
    Router::get('/admin/products', [App\Controllers\Admin\ProductController::class, 'index']);
    Router::get('/admin/products/create', [App\Controllers\Admin\ProductController::class, 'create']);
    Router::post('/admin/products', [App\Controllers\Admin\ProductController::class, 'store']);
    Router::get('/admin/products/{id}/edit', [App\Controllers\Admin\ProductController::class, 'edit']);
    Router::put('/admin/products/{id}', [App\Controllers\Admin\ProductController::class, 'update']);
    Router::delete('/admin/products/{id}', [App\Controllers\Admin\ProductController::class, 'destroy']);

    // Categories
    Router::get('/admin/categories', [App\Controllers\Admin\CategoryController::class, 'index']);
    Router::get('/admin/categories/create', [App\Controllers\Admin\CategoryController::class, 'create']);
    Router::post('/admin/categories', [App\Controllers\Admin\CategoryController::class, 'store']);
    Router::get('/admin/categories/{id}/edit', [App\Controllers\Admin\CategoryController::class, 'edit']);
    Router::put('/admin/categories/{id}', [App\Controllers\Admin\CategoryController::class, 'update']);
    Router::delete('/admin/categories/{id}', [App\Controllers\Admin\CategoryController::class, 'destroy']);

    // Orders
    Router::get('/admin/orders', [App\Controllers\Admin\OrderController::class, 'index']);
    Router::get('/admin/orders/{id}', [App\Controllers\Admin\OrderController::class, 'show']);
    Router::put('/admin/orders/{id}', [App\Controllers\Admin\OrderController::class, 'update']);
    Router::put('/admin/orders/{id}/status', [App\Controllers\Admin\OrderController::class, 'updateStatus']);

    // Quotations
    Router::get('/admin/quotations', [App\Controllers\Admin\QuotationController::class, 'index']);
    Router::get('/admin/quotations/{id}', [App\Controllers\Admin\QuotationController::class, 'show']);
    Router::get('/admin/quotations/{id}/edit', [App\Controllers\Admin\QuotationController::class, 'edit']);
    Router::put('/admin/quotations/{id}', [App\Controllers\Admin\QuotationController::class, 'update']);
    Router::put('/admin/quotations/{id}/status', [App\Controllers\Admin\QuotationController::class, 'updateStatus']);
    Router::post('/admin/quotations/{id}/approve', [App\Controllers\Admin\QuotationController::class, 'approve']);
    Router::post('/admin/quotations/{id}/pdf', [App\Controllers\Admin\QuotationController::class, 'generatePdf']);

    // Enquiries
    Router::get('/admin/enquiries', [App\Controllers\Admin\EnquiryController::class, 'index']);
    Router::get('/admin/enquiries/{id}', [App\Controllers\Admin\EnquiryController::class, 'show']);
    Router::put('/admin/enquiries/{id}', [App\Controllers\Admin\EnquiryController::class, 'update']);
    Router::put('/admin/enquiries/{id}/status', [App\Controllers\Admin\EnquiryController::class, 'updateStatus']);
    Router::post('/admin/enquiries/{id}/assign', [App\Controllers\Admin\EnquiryController::class, 'assign']);
    Router::post('/admin/enquiries/{id}/respond', [App\Controllers\Admin\EnquiryController::class, 'respond']);

    // Users
    Router::get('/admin/users', [App\Controllers\Admin\UserController::class, 'index']);
    Router::get('/admin/users/create', [App\Controllers\Admin\UserController::class, 'create']);
    Router::post('/admin/users', [App\Controllers\Admin\UserController::class, 'store']);
    Router::get('/admin/users/{id}/edit', [App\Controllers\Admin\UserController::class, 'edit']);
    Router::put('/admin/users/{id}', [App\Controllers\Admin\UserController::class, 'update']);
    Router::delete('/admin/users/{id}', [App\Controllers\Admin\UserController::class, 'destroy']);

    // Settings
    Router::get('/admin/settings', [App\Controllers\Admin\SettingController::class, 'index']);
    Router::put('/admin/settings', [App\Controllers\Admin\SettingController::class, 'update']);

    // Reports
    Router::get('/admin/reports', [App\Controllers\Admin\ReportController::class, 'index']);
    Router::get('/admin/reports/sales', [App\Controllers\Admin\ReportController::class, 'sales']);
    Router::get('/admin/reports/products', [App\Controllers\Admin\ReportController::class, 'products']);
    Router::get('/admin/reports/customers', [App\Controllers\Admin\ReportController::class, 'customers']);
    Router::get('/admin/reports/export', [App\Controllers\Admin\ReportController::class, 'export']);
});

// Fallback for SPA (Single Page Application) - serve index.html for all other routes
Router::any('/{any}', function() {
    // Check if it's a static file request
    $uri = $_SERVER['REQUEST_URI'];
    $path = parse_url($uri, PHP_URL_PATH);
    $ext = pathinfo($path, PATHINFO_EXTENSION);
    
    $staticExtensions = ['css', 'js', 'jpg', 'jpeg', 'png', 'gif', 'svg', 'ico', 'woff', 'woff2', 'ttf', 'eot', 'map', 'txt', 'xml', 'json'];
    
    if ($ext && in_array($ext, $staticExtensions)) {
        abort(404);
    }
    
    // Serve the main index.html for SPA routing
    // This assumes the frontend is built and placed in public/
    $indexPath = __DIR__ . '/../public/index.html';
    if (file_exists($indexPath)) {
        header('Content-Type: text/html; charset=utf-8');
        readfile($indexPath);
        exit;
    }
    
    abort(404);
})->where('any', '.*');