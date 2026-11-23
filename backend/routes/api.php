<?php
/**
 * API Routes
 */

use Controllers\Api\AuthController;
use Controllers\Api\ProductController;
use Controllers\Api\CategoryController;
use Controllers\Api\CartController;
use Controllers\Api\OrderController;
use Controllers\Api\QuotationController;
use Controllers\Api\EnquiryController;
use Controllers\Api\ChatController;
use Controllers\Api\NotificationController;
use Controllers\Api\UserController;

// Public routes
Router::post('/auth/register', [AuthController::class, 'register']);
Router::post('/auth/login', [AuthController::class, 'login']);
Router::post('/auth/logout', [AuthController::class, 'logout']);
Router::post('/auth/forgot-password', [AuthController::class, 'forgotPassword']);
Router::post('/auth/reset-password', [AuthController::class, 'resetPassword']);
Router::get('/auth/verify-email', [AuthController::class, 'verifyEmail']);
Router::post('/auth/resend-verification', [AuthController::class, 'resendVerification']);

// Public product routes
Router::get('/products', [ProductController::class, 'index']);
Router::get('/products/featured', [ProductController::class, 'featured']);
Router::get('/products/search', [ProductController::class, 'search']);
Router::get('/products/{slug}', [ProductController::class, 'show']);
Router::get('/products/{id}/related', [ProductController::class, 'related']);

// Public category routes
Router::get('/categories', [CategoryController::class, 'index']);
Router::get('/categories/tree', [CategoryController::class, 'tree']);
Router::get('/categories/{slug}', [CategoryController::class, 'show']);

// Public enquiry routes
Router::post('/enquiries', [EnquiryController::class, 'store']);

// Public quotation routes
Router::post('/quotations', [QuotationController::class, 'store']);

// Protected routes (require authentication)
Router::group(['middleware' => ['auth']], function () {
    // User profile
    Router::get('/user', [UserController::class, 'profile']);
    Router::put('/user', [UserController::class, 'updateProfile']);
    Router::put('/user/password', [UserController::class, 'changePassword']);
    Router::get('/user/addresses', [UserController::class, 'addresses']);
    Router::post('/user/addresses', [UserController::class, 'storeAddress']);
    Router::put('/user/addresses/{id}', [UserController::class, 'updateAddress']);
    Router::delete('/user/addresses/{id}', [UserController::class, 'deleteAddress']);
    Router::post('/user/addresses/{id}/default', [UserController::class, 'setDefaultAddress']);

    // Cart
    Router::get('/cart', [CartController::class, 'index']);
    Router::post('/cart', [CartController::class, 'add']);
    Router::put('/cart/{id}', [CartController::class, 'update']);
    Router::delete('/cart/{id}', [CartController::class, 'remove']);
    Router::delete('/cart', [CartController::class, 'clear']);
    Router::put('/cart/currency', [CartController::class, 'setCurrency']);

    // Orders
    Router::get('/orders', [OrderController::class, 'index']);
    Router::post('/orders', [OrderController::class, 'store']);
    Router::get('/orders/{id}', [OrderController::class, 'show']);
    Router::post('/orders/{id}/cancel', [OrderController::class, 'cancel']);

    // Quotations
    Router::get('/quotations', [QuotationController::class, 'index']);
    Router::get('/quotations/{id}', [QuotationController::class, 'show']);
    Router::post('/quotations/{id}/accept', [QuotationController::class, 'accept']);
    Router::post('/quotations/{id}/reject', [QuotationController::class, 'reject']);

    // Enquiries
    Router::get('/enquiries', [EnquiryController::class, 'index']);
    Router::get('/enquiries/{id}', [EnquiryController::class, 'show']);
    Router::post('/enquiries/{id}/respond', [EnquiryController::class, 'respond']);

    // Chat
    Router::get('/chat/conversations', [ChatController::class, 'index']);
    Router::post('/chat/conversations', [ChatController::class, 'create']);
    Router::get('/chat/conversations/{id}', [ChatController::class, 'show']);
    Router::post('/chat/conversations/{id}/messages', [ChatController::class, 'sendMessage']);
    Router::put('/chat/conversations/{id}/read', [ChatController::class, 'markAsRead']);

    // Notifications
    Router::get('/notifications', [NotificationController::class, 'index']);
    Router::put('/notifications/{id}/read', [NotificationController::class, 'markAsRead']);
    Router::put('/notifications/read-all', [NotificationController::class, 'markAllAsRead']);
});

// Admin routes (require admin role)
Router::group(['middleware' => ['auth', 'role:super_admin,admin']], function () {
    // Dashboard
    Router::get('/admin/dashboard/stats', [App\Controllers\Admin\DashboardController::class, 'stats']);
    Router::get('/admin/dashboard/charts', [App\Controllers\Admin\DashboardController::class, 'charts']);
    Router::get('/admin/dashboard/recent', [App\Controllers\Admin\DashboardController::class, 'recent']);

    // Products management
    Router::get('/admin/products', [App\Controllers\Admin\ProductController::class, 'index']);
    Router::post('/admin/products', [App\Controllers\Admin\ProductController::class, 'store']);
    Router::get('/admin/products/{id}', [App\Controllers\Admin\ProductController::class, 'show']);
    Router::put('/admin/products/{id}', [App\Controllers\Admin\ProductController::class, 'update']);
    Router::delete('/admin/products/{id}', [App\Controllers\Admin\ProductController::class, 'destroy']);
    Router::post('/admin/products/{id}/images', [App\Controllers\Admin\ProductController::class, 'uploadImage']);
    Router::delete('/admin/products/images/{id}', [App\Controllers\Admin\ProductController::class, 'deleteImage']);

    // Categories management
    Router::get('/admin/categories', [App\Controllers\Admin\CategoryController::class, 'index']);
    Router::post('/admin/categories', [App\Controllers\Admin\CategoryController::class, 'store']);
    Router::get('/admin/categories/{id}', [App\Controllers\Admin\CategoryController::class, 'show']);
    Router::put('/admin/categories/{id}', [App\Controllers\Admin\CategoryController::class, 'update']);
    Router::delete('/admin/categories/{id}', [App\Controllers\Admin\CategoryController::class, 'destroy']);

    // Orders management
    Router::get('/admin/orders', [App\Controllers\Admin\OrderController::class, 'index']);
    Router::get('/admin/orders/{id}', [App\Controllers\Admin\OrderController::class, 'show']);
    Router::put('/admin/orders/{id}', [App\Controllers\Admin\OrderController::class, 'update']);
    Router::put('/admin/orders/{id}/status', [App\Controllers\Admin\OrderController::class, 'updateStatus']);
    Router::post('/admin/orders/{id}/notes', [App\Controllers\Admin\OrderController::class, 'addNote']);

    // Quotations management
    Router::get('/admin/quotations', [App\Controllers\Admin\QuotationController::class, 'index']);
    Router::get('/admin/quotations/{id}', [App\Controllers\Admin\QuotationController::class, 'show']);
    Router::put('/admin/quotations/{id}', [App\Controllers\Admin\QuotationController::class, 'update']);
    Router::put('/admin/quotations/{id}/status', [App\Controllers\Admin\QuotationController::class, 'updateStatus']);
    Router::post('/admin/quotations/{id}/approve', [App\Controllers\Admin\QuotationController::class, 'approve']);
    Router::post('/admin/quotations/{id}/send', [App\Controllers\Admin\QuotationController::class, 'send']);
    Router::post('/admin/quotations/{id}/pdf', [App\Controllers\Admin\QuotationController::class, 'generatePdf']);

    // Enquiries management
    Router::get('/admin/enquiries', [App\Controllers\Admin\EnquiryController::class, 'index']);
    Router::get('/admin/enquiries/{id}', [App\Controllers\Admin\EnquiryController::class, 'show']);
    Router::put('/admin/enquiries/{id}', [App\Controllers\Admin\EnquiryController::class, 'update']);
    Router::put('/admin/enquiries/{id}/status', [App\Controllers\Admin\EnquiryController::class, 'updateStatus']);
    Router::post('/admin/enquiries/{id}/assign', [App\Controllers\Admin\EnquiryController::class, 'assign']);
    Router::post('/admin/enquiries/{id}/respond', [App\Controllers\Admin\EnquiryController::class, 'respond']);

    // Users management
    Router::get('/admin/users', [App\Controllers\Admin\UserController::class, 'index']);
    Router::post('/admin/users', [App\Controllers\Admin\UserController::class, 'store']);
    Router::get('/admin/users/{id}', [App\Controllers\Admin\UserController::class, 'show']);
    Router::put('/admin/users/{id}', [App\Controllers\Admin\UserController::class, 'update']);
    Router::delete('/admin/users/{id}', [App\Controllers\Admin\UserController::class, 'destroy']);
    Router::put('/admin/users/{id}/role', [App\Controllers\Admin\UserController::class, 'updateRole']);
    Router::put('/admin/users/{id}/toggle-status', [App\Controllers\Admin\UserController::class, 'toggleStatus']);

    // Settings
    Router::get('/admin/settings', [App\Controllers\Admin\SettingController::class, 'index']);
    Router::put('/admin/settings', [App\Controllers\Admin\SettingController::class, 'update']);
    Router::get('/admin/settings/{group}', [App\Controllers\Admin\SettingController::class, 'group']);

    // Reports
    Router::get('/admin/reports/sales', [App\Controllers\Admin\ReportController::class, 'sales']);
    Router::get('/admin/reports/products', [App\Controllers\Admin\ReportController::class, 'products']);
    Router::get('/admin/reports/customers', [App\Controllers\Admin\ReportController::class, 'customers']);
    Router::get('/admin/reports/export', [App\Controllers\Admin\ReportController::class, 'export']);
});