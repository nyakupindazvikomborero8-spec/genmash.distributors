-- Genmashy Distributors Database Schema
-- Version: 1.0
-- Description: Initial schema for the complete e-commerce platform

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET time_zone = "+00:00";
SET NAMES utf8mb4;
SET CHARACTER SET utf8mb4;

-- Create database if not exists
CREATE DATABASE IF NOT EXISTS `genmashy` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `genmashy`;

-- ============================================
-- USERS & AUTHENTICATION TABLES
-- ============================================

-- Roles table
CREATE TABLE `roles` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `name` VARCHAR(50) NOT NULL UNIQUE,
    `display_name` VARCHAR(100) NOT NULL,
    `description` TEXT,
    `permissions` JSON,
    `is_system` BOOLEAN NOT NULL DEFAULT FALSE,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_roles_name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Permissions table
CREATE TABLE `permissions` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `name` VARCHAR(100) NOT NULL UNIQUE,
    `display_name` VARCHAR(150) NOT NULL,
    `description` TEXT,
    `module` VARCHAR(50) NOT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_permissions_name` (`name`),
    KEY `idx_permissions_module` (`module`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Users table (customers + staff + admins)
CREATE TABLE `users` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `uuid` CHAR(36) NOT NULL UNIQUE,
    `role_id` INT UNSIGNED NOT NULL,
    `email` VARCHAR(255) NOT NULL UNIQUE,
    `email_verified_at` TIMESTAMP NULL,
    `password_hash` VARCHAR(255) NOT NULL,
    `first_name` VARCHAR(100) NOT NULL,
    `last_name` VARCHAR(100) NOT NULL,
    `phone` VARCHAR(30),
    `phone_verified_at` TIMESTAMP NULL,
    `avatar` VARCHAR(500),
    `is_active` BOOLEAN NOT NULL DEFAULT TRUE,
    `last_login_at` TIMESTAMP NULL,
    `last_login_ip` VARCHAR(45),
    `failed_login_attempts` INT UNSIGNED NOT NULL DEFAULT 0,
    `locked_until` TIMESTAMP NULL,
    `password_reset_token` VARCHAR(100),
    `password_reset_expires` TIMESTAMP NULL,
    `email_verification_token` VARCHAR(100),
    `email_verification_expires` TIMESTAMP NULL,
    `remember_token` VARCHAR(100),
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at` TIMESTAMP NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_users_uuid` (`uuid`),
    UNIQUE KEY `uk_users_email` (`email`),
    KEY `idx_users_role_id` (`role_id`),
    KEY `idx_users_is_active` (`is_active`),
    KEY `idx_users_deleted_at` (`deleted_at`),
    KEY `idx_users_email_verification_token` (`email_verification_token`),
    KEY `idx_users_password_reset_token` (`password_reset_token`),
    CONSTRAINT `fk_users_role_id` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- User sessions (for secure session management)
CREATE TABLE `user_sessions` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id` BIGINT UNSIGNED NOT NULL,
    `session_id` VARCHAR(128) NOT NULL UNIQUE,
    `ip_address` VARCHAR(45) NOT NULL,
    `user_agent` TEXT,
    `payload` JSON,
    `last_activity` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `expires_at` TIMESTAMP NOT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_user_sessions_session_id` (`session_id`),
    KEY `idx_user_sessions_user_id` (`user_id`),
    KEY `idx_user_sessions_expires_at` (`expires_at`),
    CONSTRAINT `fk_user_sessions_user_id` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- CUSTOMER PROFILES & ADDRESSES
-- ============================================

-- Customer profiles (extended info for customers)
CREATE TABLE `customer_profiles` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id` BIGINT UNSIGNED NOT NULL UNIQUE,
    `company_name` VARCHAR(255),
    `tax_number` VARCHAR(100),
    `preferred_currency` ENUM('USD', 'ZWG') NOT NULL DEFAULT 'USD',
    `marketing_consent` BOOLEAN NOT NULL DEFAULT FALSE,
    `notes` TEXT,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_customer_profiles_user_id` (`user_id`),
    CONSTRAINT `fk_customer_profiles_user_id` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Addresses
CREATE TABLE `addresses` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id` BIGINT UNSIGNED NOT NULL,
    `type` ENUM('billing', 'shipping', 'both') NOT NULL DEFAULT 'both',
    `is_default` BOOLEAN NOT NULL DEFAULT FALSE,
    `first_name` VARCHAR(100) NOT NULL,
    `last_name` VARCHAR(100) NOT NULL,
    `company` VARCHAR(255),
    `address_line_1` VARCHAR(255) NOT NULL,
    `address_line_2` VARCHAR(255),
    `city` VARCHAR(100) NOT NULL,
    `state_province` VARCHAR(100),
    `postal_code` VARCHAR(20),
    `country` VARCHAR(100) NOT NULL DEFAULT 'Zimbabwe',
    `phone` VARCHAR(30),
    `instructions` TEXT,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_addresses_user_id` (`user_id`),
    KEY `idx_addresses_is_default` (`is_default`),
    CONSTRAINT `fk_addresses_user_id` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- PRODUCTS & CATEGORIES
-- ============================================

-- Categories
CREATE TABLE `categories` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `parent_id` BIGINT UNSIGNED NULL,
    `name` VARCHAR(150) NOT NULL,
    `slug` VARCHAR(180) NOT NULL UNIQUE,
    `description` TEXT,
    `image` VARCHAR(500),
    `icon` VARCHAR(100),
    `sort_order` INT NOT NULL DEFAULT 0,
    `is_active` BOOLEAN NOT NULL DEFAULT TRUE,
    `is_featured` BOOLEAN NOT NULL DEFAULT FALSE,
    `meta_title` VARCHAR(255),
    `meta_description` VARCHAR(500),
    `meta_keywords` VARCHAR(500),
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at` TIMESTAMP NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_categories_slug` (`slug`),
    KEY `idx_categories_parent_id` (`parent_id`),
    KEY `idx_categories_is_active` (`is_active`),
    KEY `idx_categories_sort_order` (`sort_order`),
    CONSTRAINT `fk_categories_parent_id` FOREIGN KEY (`parent_id`) REFERENCES `categories` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Products
CREATE TABLE `products` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `category_id` BIGINT UNSIGNED NOT NULL,
    `sku` VARCHAR(100) NOT NULL UNIQUE,
    `name` VARCHAR(255) NOT NULL,
    `slug` VARCHAR(280) NOT NULL UNIQUE,
    `short_description` TEXT,
    `description` LONGTEXT,
    `specifications` JSON,
    `price_usd` DECIMAL(12, 2) NOT NULL DEFAULT 0.00,
    `price_zwg` DECIMAL(12, 2) NOT NULL DEFAULT 0.00,
    `cost_price_usd` DECIMAL(12, 2),
    `cost_price_zwg` DECIMAL(12, 2),
    `currency` ENUM('USD', 'ZWG', 'BOTH') NOT NULL DEFAULT 'BOTH',
    `stock_quantity` INT UNSIGNED NOT NULL DEFAULT 0,
    `low_stock_threshold` INT UNSIGNED NOT NULL DEFAULT 5,
    `track_inventory` BOOLEAN NOT NULL DEFAULT TRUE,
    `allow_backorder` BOOLEAN NOT NULL DEFAULT FALSE,
    `weight` DECIMAL(8, 3),
    `dimensions` JSON,
    `is_active` BOOLEAN NOT NULL DEFAULT TRUE,
    `is_featured` BOOLEAN NOT NULL DEFAULT FALSE,
    `is_digital` BOOLEAN NOT NULL DEFAULT FALSE,
    `requires_shipping` BOOLEAN NOT NULL DEFAULT TRUE,
    `tax_class` VARCHAR(50) DEFAULT 'standard',
    `meta_title` VARCHAR(255),
    `meta_description` VARCHAR(500),
    `meta_keywords` VARCHAR(500),
    `published_at` TIMESTAMP NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at` TIMESTAMP NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_products_sku` (`sku`),
    UNIQUE KEY `uk_products_slug` (`slug`),
    KEY `idx_products_category_id` (`category_id`),
    KEY `idx_products_is_active` (`is_active`),
    KEY `idx_products_is_featured` (`is_featured`),
    KEY `idx_products_published_at` (`published_at`),
    KEY `idx_products_price_usd` (`price_usd`),
    KEY `idx_products_price_zwg` (`price_zwg`),
    CONSTRAINT `fk_products_category_id` FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Product images
CREATE TABLE `product_images` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `product_id` BIGINT UNSIGNED NOT NULL,
    `image_path` VARCHAR(500) NOT NULL,
    `alt_text` VARCHAR(255),
    `sort_order` INT NOT NULL DEFAULT 0,
    `is_primary` BOOLEAN NOT NULL DEFAULT FALSE,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_product_images_product_id` (`product_id`),
    KEY `idx_product_images_is_primary` (`is_primary`),
    CONSTRAINT `fk_product_images_product_id` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Product variants (for future use - sizes, colors, etc.)
CREATE TABLE `product_variants` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `product_id` BIGINT UNSIGNED NOT NULL,
    `sku` VARCHAR(100) NOT NULL UNIQUE,
    `name` VARCHAR(255) NOT NULL,
    `attributes` JSON NOT NULL,
    `price_usd` DECIMAL(12, 2),
    `price_zwg` DECIMAL(12, 2),
    `stock_quantity` INT UNSIGNED NOT NULL DEFAULT 0,
    `is_active` BOOLEAN NOT NULL DEFAULT TRUE,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_product_variants_sku` (`sku`),
    KEY `idx_product_variants_product_id` (`product_id`),
    CONSTRAINT `fk_product_variants_product_id` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- INVENTORY
-- ============================================

-- Inventory movements (stock tracking)
CREATE TABLE `inventory_movements` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `product_id` BIGINT UNSIGNED NOT NULL,
    `variant_id` BIGINT UNSIGNED NULL,
    `type` ENUM('in', 'out', 'adjustment', 'reserved', 'released') NOT NULL,
    `quantity` INT NOT NULL,
    `reference_type` VARCHAR(50),
    `reference_id` BIGINT UNSIGNED,
    `notes` TEXT,
    `user_id` BIGINT UNSIGNED,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_inventory_movements_product_id` (`product_id`),
    KEY `idx_inventory_movements_variant_id` (`variant_id`),
    KEY `idx_inventory_movements_type` (`type`),
    KEY `idx_inventory_movements_reference` (`reference_type`, `reference_id`),
    KEY `idx_inventory_movements_created_at` (`created_at`),
    CONSTRAINT `fk_inventory_movements_product_id` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_inventory_movements_variant_id` FOREIGN KEY (`variant_id`) REFERENCES `product_variants` (`id`) ON DELETE SET NULL,
    CONSTRAINT `fk_inventory_movements_user_id` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- SHOPPING CART
-- ============================================

-- Carts
CREATE TABLE `carts` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id` BIGINT UNSIGNED NULL,
    `session_id` VARCHAR(128),
    `currency` ENUM('USD', 'ZWG') NOT NULL DEFAULT 'USD',
    `expires_at` TIMESTAMP NOT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_carts_user_id` (`user_id`),
    KEY `idx_carts_session_id` (`session_id`),
    KEY `idx_carts_expires_at` (`expires_at`),
    CONSTRAINT `fk_carts_user_id` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Cart items
CREATE TABLE `cart_items` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `cart_id` BIGINT UNSIGNED NOT NULL,
    `product_id` BIGINT UNSIGNED NOT NULL,
    `variant_id` BIGINT UNSIGNED NULL,
    `quantity` INT UNSIGNED NOT NULL DEFAULT 1,
    `price_usd` DECIMAL(12, 2) NOT NULL,
    `price_zwg` DECIMAL(12, 2) NOT NULL,
    `metadata` JSON,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_cart_items_cart_product_variant` (`cart_id`, `product_id`, `variant_id`),
    KEY `idx_cart_items_cart_id` (`cart_id`),
    KEY `idx_cart_items_product_id` (`product_id`),
    CONSTRAINT `fk_cart_items_cart_id` FOREIGN KEY (`cart_id`) REFERENCES `carts` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_cart_items_product_id` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_cart_items_variant_id` FOREIGN KEY (`variant_id`) REFERENCES `product_variants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- ORDERS
-- ============================================

-- Orders
CREATE TABLE `orders` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `order_number` VARCHAR(30) NOT NULL UNIQUE,
    `user_id` BIGINT UNSIGNED NOT NULL,
    `status` ENUM('pending', 'confirmed', 'processing', 'shipped', 'delivered', 'cancelled', 'refunded', 'failed') NOT NULL DEFAULT 'pending',
    `payment_status` ENUM('pending', 'paid', 'partial', 'refunded', 'failed') NOT NULL DEFAULT 'pending',
    `currency` ENUM('USD', 'ZWG') NOT NULL DEFAULT 'USD',
    `subtotal_usd` DECIMAL(12, 2) NOT NULL DEFAULT 0.00,
    `subtotal_zwg` DECIMAL(12, 2) NOT NULL DEFAULT 0.00,
    `tax_usd` DECIMAL(12, 2) NOT NULL DEFAULT 0.00,
    `tax_zwg` DECIMAL(12, 2) NOT NULL DEFAULT 0.00,
    `shipping_usd` DECIMAL(12, 2) NOT NULL DEFAULT 0.00,
    `shipping_zwg` DECIMAL(12, 2) NOT NULL DEFAULT 0.00,
    `discount_usd` DECIMAL(12, 2) NOT NULL DEFAULT 0.00,
    `discount_zwg` DECIMAL(12, 2) NOT NULL DEFAULT 0.00,
    `total_usd` DECIMAL(12, 2) NOT NULL DEFAULT 0.00,
    `total_zwg` DECIMAL(12, 2) NOT NULL DEFAULT 0.00,
    `billing_address` JSON NOT NULL,
    `shipping_address` JSON,
    `shipping_method` VARCHAR(100),
    `shipping_tracking` VARCHAR(255),
    `notes` TEXT,
    `customer_notes` TEXT,
    `confirmed_at` TIMESTAMP NULL,
    `shipped_at` TIMESTAMP NULL,
    `delivered_at` TIMESTAMP NULL,
    `cancelled_at` TIMESTAMP NULL,
    `cancelled_reason` TEXT,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_orders_order_number` (`order_number`),
    KEY `idx_orders_user_id` (`user_id`),
    KEY `idx_orders_status` (`status`),
    KEY `idx_orders_payment_status` (`payment_status`),
    KEY `idx_orders_created_at` (`created_at`),
    CONSTRAINT `fk_orders_user_id` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Order items
CREATE TABLE `order_items` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `order_id` BIGINT UNSIGNED NOT NULL,
    `product_id` BIGINT UNSIGNED NOT NULL,
    `variant_id` BIGINT UNSIGNED NULL,
    `product_name` VARCHAR(255) NOT NULL,
    `product_sku` VARCHAR(100) NOT NULL,
    `variant_name` VARCHAR(255),
    `quantity` INT UNSIGNED NOT NULL,
    `unit_price_usd` DECIMAL(12, 2) NOT NULL,
    `unit_price_zwg` DECIMAL(12, 2) NOT NULL,
    `total_price_usd` DECIMAL(12, 2) NOT NULL,
    `total_price_zwg` DECIMAL(12, 2) NOT NULL,
    `tax_rate` DECIMAL(5, 4) NOT NULL DEFAULT 0.0000,
    `tax_usd` DECIMAL(12, 2) NOT NULL DEFAULT 0.00,
    `tax_zwg` DECIMAL(12, 2) NOT NULL DEFAULT 0.00,
    `metadata` JSON,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_order_items_order_id` (`order_id`),
    KEY `idx_order_items_product_id` (`product_id`),
    CONSTRAINT `fk_order_items_order_id` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_order_items_product_id` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE RESTRICT,
    CONSTRAINT `fk_order_items_variant_id` FOREIGN KEY (`variant_id`) REFERENCES `product_variants` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Order status history
CREATE TABLE `order_status_history` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `order_id` BIGINT UNSIGNED NOT NULL,
    `from_status` VARCHAR(50),
    `to_status` VARCHAR(50) NOT NULL,
    `from_payment_status` VARCHAR(50),
    `to_payment_status` VARCHAR(50),
    `notes` TEXT,
    `user_id` BIGINT UNSIGNED,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_order_status_history_order_id` (`order_id`),
    KEY `idx_order_status_history_created_at` (`created_at`),
    CONSTRAINT `fk_order_status_history_order_id` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_order_status_history_user_id` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- PAYMENTS
-- ============================================

-- Payments
CREATE TABLE `payments` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `payment_number` VARCHAR(30) NOT NULL UNIQUE,
    `order_id` BIGINT UNSIGNED NOT NULL,
    `user_id` BIGINT UNSIGNED NOT NULL,
    `method` ENUM('cash', 'bank_transfer', 'mobile_money', 'card', 'ecocash', 'one_money', 'paynow', 'other') NOT NULL,
    `provider` VARCHAR(100),
    `provider_transaction_id` VARCHAR(255),
    `currency` ENUM('USD', 'ZWG') NOT NULL,
    `amount` DECIMAL(12, 2) NOT NULL,
    `fee` DECIMAL(12, 2) NOT NULL DEFAULT 0.00,
    `status` ENUM('pending', 'processing', 'completed', 'failed', 'refunded', 'cancelled') NOT NULL DEFAULT 'pending',
    `reference` VARCHAR(255),
    `notes` TEXT,
    `paid_at` TIMESTAMP NULL,
    `failed_at` TIMESTAMP NULL,
    `refunded_at` TIMESTAMP NULL,
    `refund_reason` TEXT,
    `metadata` JSON,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_payments_payment_number` (`payment_number`),
    KEY `idx_payments_order_id` (`order_id`),
    KEY `idx_payments_user_id` (`user_id`),
    KEY `idx_payments_status` (`status`),
    KEY `idx_payments_provider_transaction_id` (`provider_transaction_id`),
    KEY `idx_payments_created_at` (`created_at`),
    CONSTRAINT `fk_payments_order_id` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE RESTRICT,
    CONSTRAINT `fk_payments_user_id` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- QUOTATIONS
-- ============================================

-- Quotations
CREATE TABLE `quotations` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `quotation_number` VARCHAR(30) NOT NULL UNIQUE,
    `user_id` BIGINT UNSIGNED NULL,
    `customer_name` VARCHAR(255) NOT NULL,
    `customer_email` VARCHAR(255) NOT NULL,
    `customer_phone` VARCHAR(30) NOT NULL,
    `customer_company` VARCHAR(255),
    `customer_address` TEXT,
    `status` ENUM('pending', 'under_review', 'prepared', 'sent', 'accepted', 'rejected', 'expired') NOT NULL DEFAULT 'pending',
    `currency` ENUM('USD', 'ZWG', 'BOTH') NOT NULL DEFAULT 'BOTH',
    `subtotal_usd` DECIMAL(12, 2) NOT NULL DEFAULT 0.00,
    `subtotal_zwg` DECIMAL(12, 2) NOT NULL DEFAULT 0.00,
    `discount_usd` DECIMAL(12, 2) NOT NULL DEFAULT 0.00,
    `discount_zwg` DECIMAL(12, 2) NOT NULL DEFAULT 0.00,
    `tax_usd` DECIMAL(12, 2) NOT NULL DEFAULT 0.00,
    `tax_zwg` DECIMAL(12, 2) NOT NULL DEFAULT 0.00,
    `delivery_usd` DECIMAL(12, 2) NOT NULL DEFAULT 0.00,
    `delivery_zwg` DECIMAL(12, 2) NOT NULL DEFAULT 0.00,
    `total_usd` DECIMAL(12, 2) NOT NULL DEFAULT 0.00,
    `total_zwg` DECIMAL(12, 2) NOT NULL DEFAULT 0.00,
    `validity_days` INT UNSIGNED NOT NULL DEFAULT 30,
    `notes` TEXT,
    `terms_conditions` TEXT,
    `internal_notes` TEXT,
    `prepared_by` BIGINT UNSIGNED NULL,
    `approved_by` BIGINT UNSIGNED NULL,
    `sent_at` TIMESTAMP NULL,
    `accepted_at` TIMESTAMP NULL,
    `rejected_at` TIMESTAMP NULL,
    `rejected_reason` TEXT,
    `expires_at` TIMESTAMP NULL,
    `pdf_path` VARCHAR(500),
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_quotations_quotation_number` (`quotation_number`),
    KEY `idx_quotations_user_id` (`user_id`),
    KEY `idx_quotations_status` (`status`),
    KEY `idx_quotations_created_at` (`created_at`),
    KEY `idx_quotations_expires_at` (`expires_at`),
    CONSTRAINT `fk_quotations_user_id` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
    CONSTRAINT `fk_quotations_prepared_by` FOREIGN KEY (`prepared_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
    CONSTRAINT `fk_quotations_approved_by` FOREIGN KEY (`approved_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Quotation items
CREATE TABLE `quotation_items` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `quotation_id` BIGINT UNSIGNED NOT NULL,
    `product_id` BIGINT UNSIGNED NULL,
    `product_name` VARCHAR(255) NOT NULL,
    `product_sku` VARCHAR(100),
    `description` TEXT,
    `quantity` INT UNSIGNED NOT NULL DEFAULT 1,
    `unit_price_usd` DECIMAL(12, 2) NOT NULL DEFAULT 0.00,
    `unit_price_zwg` DECIMAL(12, 2) NOT NULL DEFAULT 0.00,
    `discount_percent` DECIMAL(5, 2) NOT NULL DEFAULT 0.00,
    `discount_usd` DECIMAL(12, 2) NOT NULL DEFAULT 0.00,
    `discount_zwg` DECIMAL(12, 2) NOT NULL DEFAULT 0.00,
    `tax_rate` DECIMAL(5, 4) NOT NULL DEFAULT 0.0000,
    `tax_usd` DECIMAL(12, 2) NOT NULL DEFAULT 0.00,
    `tax_zwg` DECIMAL(12, 2) NOT NULL DEFAULT 0.00,
    `total_usd` DECIMAL(12, 2) NOT NULL DEFAULT 0.00,
    `total_zwg` DECIMAL(12, 2) NOT NULL DEFAULT 0.00,
    `sort_order` INT NOT NULL DEFAULT 0,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_quotation_items_quotation_id` (`quotation_id`),
    KEY `idx_quotation_items_product_id` (`product_id`),
    CONSTRAINT `fk_quotation_items_quotation_id` FOREIGN KEY (`quotation_id`) REFERENCES `quotations` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_quotation_items_product_id` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Quotation status history
CREATE TABLE `quotation_status_history` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `quotation_id` BIGINT UNSIGNED NOT NULL,
    `from_status` VARCHAR(50),
    `to_status` VARCHAR(50) NOT NULL,
    `notes` TEXT,
    `user_id` BIGINT UNSIGNED,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_quotation_status_history_quotation_id` (`quotation_id`),
    KEY `idx_quotation_status_history_created_at` (`created_at`),
    CONSTRAINT `fk_quotation_status_history_quotation_id` FOREIGN KEY (`quotation_id`) REFERENCES `quotations` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_quotation_status_history_user_id` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- ENQUIRIES
-- ============================================

-- Enquiries
CREATE TABLE `enquiries` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `enquiry_number` VARCHAR(30) NOT NULL UNIQUE,
    `user_id` BIGINT UNSIGNED NULL,
    `name` VARCHAR(255) NOT NULL,
    `email` VARCHAR(255) NOT NULL,
    `phone` VARCHAR(30),
    `company` VARCHAR(255),
    `subject` VARCHAR(255) NOT NULL,
    `message` TEXT NOT NULL,
    `type` ENUM('general', 'product', 'quotation', 'support', 'after_sales', 'complaint') NOT NULL DEFAULT 'general',
    `priority` ENUM('low', 'normal', 'high', 'urgent') NOT NULL DEFAULT 'normal',
    `status` ENUM('new', 'in_progress', 'responded', 'resolved', 'closed') NOT NULL DEFAULT 'new',
    `assigned_to` BIGINT UNSIGNED NULL,
    `product_id` BIGINT UNSIGNED NULL,
    `quotation_id` BIGINT UNSIGNED NULL,
    `order_id` BIGINT UNSIGNED NULL,
    `source` VARCHAR(50) DEFAULT 'website',
    `ip_address` VARCHAR(45),
    `user_agent` TEXT,
    `responded_at` TIMESTAMP NULL,
    `resolved_at` TIMESTAMP NULL,
    `closed_at` TIMESTAMP NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_enquiries_enquiry_number` (`enquiry_number`),
    KEY `idx_enquiries_user_id` (`user_id`),
    KEY `idx_enquiries_status` (`status`),
    KEY `idx_enquiries_assigned_to` (`assigned_to`),
    KEY `idx_enquiries_type` (`type`),
    KEY `idx_enquiries_priority` (`priority`),
    KEY `idx_enquiries_created_at` (`created_at`),
    CONSTRAINT `fk_enquiries_user_id` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
    CONSTRAINT `fk_enquiries_assigned_to` FOREIGN KEY (`assigned_to`) REFERENCES `users` (`id`) ON DELETE SET NULL,
    CONSTRAINT `fk_enquiries_product_id` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE SET NULL,
    CONSTRAINT `fk_enquiries_quotation_id` FOREIGN KEY (`quotation_id`) REFERENCES `quotations` (`id`) ON DELETE SET NULL,
    CONSTRAINT `fk_enquiries_order_id` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Enquiry responses
CREATE TABLE `enquiry_responses` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `enquiry_id` BIGINT UNSIGNED NOT NULL,
    `user_id` BIGINT UNSIGNED NOT NULL,
    `message` TEXT NOT NULL,
    `is_internal` BOOLEAN NOT NULL DEFAULT FALSE,
    `attachments` JSON,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_enquiry_responses_enquiry_id` (`enquiry_id`),
    KEY `idx_enquiry_responses_user_id` (`user_id`),
    CONSTRAINT `fk_enquiry_responses_enquiry_id` FOREIGN KEY (`enquiry_id`) REFERENCES `enquiries` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_enquiry_responses_user_id` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Enquiry status history
CREATE TABLE `enquiry_status_history` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `enquiry_id` BIGINT UNSIGNED NOT NULL,
    `from_status` VARCHAR(50),
    `to_status` VARCHAR(50) NOT NULL,
    `notes` TEXT,
    `user_id` BIGINT UNSIGNED,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_enquiry_status_history_enquiry_id` (`enquiry_id`),
    KEY `idx_enquiry_status_history_created_at` (`created_at`),
    CONSTRAINT `fk_enquiry_status_history_enquiry_id` FOREIGN KEY (`enquiry_id`) REFERENCES `enquiries` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_enquiry_status_history_user_id` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- MESSAGING / LIVE CHAT
-- ============================================

-- Chat conversations
CREATE TABLE `chat_conversations` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id` BIGINT UNSIGNED NULL,
    `guest_name` VARCHAR(255),
    `guest_email` VARCHAR(255),
    `guest_phone` VARCHAR(30),
    `subject` VARCHAR(255),
    `status` ENUM('open', 'waiting_customer', 'waiting_agent', 'closed') NOT NULL DEFAULT 'open',
    `priority` ENUM('low', 'normal', 'high', 'urgent') NOT NULL DEFAULT 'normal',
    `assigned_to` BIGINT UNSIGNED NULL,
    `last_message_at` TIMESTAMP NULL,
    `last_message_by` ENUM('customer', 'agent', 'system') NULL,
    `closed_at` TIMESTAMP NULL,
    `closed_by` BIGINT UNSIGNED NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_chat_conversations_user_id` (`user_id`),
    KEY `idx_chat_conversations_status` (`status`),
    KEY `idx_chat_conversations_assigned_to` (`assigned_to`),
    KEY `idx_chat_conversations_last_message_at` (`last_message_at`),
    CONSTRAINT `fk_chat_conversations_user_id` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
    CONSTRAINT `fk_chat_conversations_assigned_to` FOREIGN KEY (`assigned_to`) REFERENCES `users` (`id`) ON DELETE SET NULL,
    CONSTRAINT `fk_chat_conversations_closed_by` FOREIGN KEY (`closed_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Chat messages
CREATE TABLE `chat_messages` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `conversation_id` BIGINT UNSIGNED NOT NULL,
    `sender_type` ENUM('customer', 'agent', 'system') NOT NULL,
    `sender_id` BIGINT UNSIGNED NULL,
    `message` TEXT NOT NULL,
    `attachments` JSON,
    `is_read` BOOLEAN NOT NULL DEFAULT FALSE,
    `read_at` TIMESTAMP NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_chat_messages_conversation_id` (`conversation_id`),
    KEY `idx_chat_messages_sender_id` (`sender_id`),
    KEY `idx_chat_messages_created_at` (`created_at`),
    KEY `idx_chat_messages_is_read` (`is_read`),
    CONSTRAINT `fk_chat_messages_conversation_id` FOREIGN KEY (`conversation_id`) REFERENCES `chat_conversations` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_chat_messages_sender_id` FOREIGN KEY (`sender_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- NOTIFICATIONS
-- ============================================

-- Notifications
CREATE TABLE `notifications` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id` BIGINT UNSIGNED NOT NULL,
    `type` VARCHAR(100) NOT NULL,
    `title` VARCHAR(255) NOT NULL,
    `message` TEXT NOT NULL,
    `data` JSON,
    `action_url` VARCHAR(500),
    `is_read` BOOLEAN NOT NULL DEFAULT FALSE,
    `read_at` TIMESTAMP NULL,
    `sent_at` TIMESTAMP NULL,
    `failed_at` TIMESTAMP NULL,
    `failure_reason` TEXT,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_notifications_user_id` (`user_id`),
    KEY `idx_notifications_is_read` (`is_read`),
    KEY `idx_notifications_type` (`type`),
    KEY `idx_notifications_created_at` (`created_at`),
    CONSTRAINT `fk_notifications_user_id` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Email queue
CREATE TABLE `email_queue` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `to_email` VARCHAR(255) NOT NULL,
    `to_name` VARCHAR(255),
    `subject` VARCHAR(500) NOT NULL,
    `body_html` LONGTEXT NOT NULL,
    `body_text` LONGTEXT,
    `from_email` VARCHAR(255) NOT NULL DEFAULT 'sales@genmashy.co.zw',
    `from_name` VARCHAR(255) NOT NULL DEFAULT 'Genmashy Distributors',
    `reply_to` VARCHAR(255),
    `cc` JSON,
    `bcc` JSON,
    `attachments` JSON,
    `priority` ENUM('low', 'normal', 'high') NOT NULL DEFAULT 'normal',
    `status` ENUM('pending', 'processing', 'sent', 'failed', 'cancelled') NOT NULL DEFAULT 'pending',
    `attempts` INT UNSIGNED NOT NULL DEFAULT 0,
    `max_attempts` INT UNSIGNED NOT NULL DEFAULT 3,
    `error_message` TEXT,
    `sent_at` TIMESTAMP NULL,
    `scheduled_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_email_queue_status` (`status`),
    KEY `idx_email_queue_scheduled_at` (`scheduled_at`),
    KEY `idx_email_queue_priority` (`priority`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- SETTINGS & ACTIVITY LOGS
-- ============================================

-- Settings
CREATE TABLE `settings` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `key` VARCHAR(150) NOT NULL UNIQUE,
    `value` LONGTEXT,
    `type` ENUM('string', 'integer', 'float', 'boolean', 'json', 'array') NOT NULL DEFAULT 'string',
    `group` VARCHAR(100) NOT NULL DEFAULT 'general',
    `label` VARCHAR(255),
    `description` TEXT,
    `is_public` BOOLEAN NOT NULL DEFAULT FALSE,
    `validation_rules` JSON,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_settings_key` (`key`),
    KEY `idx_settings_group` (`group`),
    KEY `idx_settings_is_public` (`is_public`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Activity logs
CREATE TABLE `activity_logs` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id` BIGINT UNSIGNED NULL,
    `action` VARCHAR(100) NOT NULL,
    `description` TEXT NOT NULL,
    `subject_type` VARCHAR(100),
    `subject_id` BIGINT UNSIGNED,
    `properties` JSON,
    `ip_address` VARCHAR(45),
    `user_agent` TEXT,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_activity_logs_user_id` (`user_id`),
    KEY `idx_activity_logs_action` (`action`),
    KEY `idx_activity_logs_subject` (`subject_type`, `subject_id`),
    KEY `idx_activity_logs_created_at` (`created_at`),
    CONSTRAINT `fk_activity_logs_user_id` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- INITIAL DATA
-- ============================================

-- Insert default roles
INSERT INTO `roles` (`name`, `display_name`, `description`, `permissions`, `is_system`) VALUES
('super_admin', 'Super Administrator', 'Full platform access including system configuration', '["*"]', TRUE),
('admin', 'Administrator', 'Manage products, customers, orders, quotations, enquiries', '["products.*", "categories.*", "customers.*", "orders.*", "quotations.*", "enquiries.*", "reports.*", "staff.view", "settings.view"]', TRUE),
('sales', 'Sales Staff', 'Manage customer enquiries, prepare quotations, manage assigned customers', '["customers.view", "customers.edit", "quotations.*", "enquiries.*", "products.view", "orders.view"]', TRUE),
('customer', 'Customer', 'Browse products, place orders, request quotations, manage own account', '["products.view", "orders.own", "quotations.own", "enquiries.own", "profile.own", "cart.*"]', TRUE);

-- Insert default permissions
INSERT INTO `permissions` (`name`, `display_name`, `description`, `module`) VALUES
('products.create', 'Create Products', 'Create new products', 'products'),
('products.view', 'View Products', 'View product listings and details', 'products'),
('products.edit', 'Edit Products', 'Edit existing products', 'products'),
('products.delete', 'Delete Products', 'Delete or deactivate products', 'products'),
('categories.create', 'Create Categories', 'Create new categories', 'categories'),
('categories.view', 'View Categories', 'View category listings', 'categories'),
('categories.edit', 'Edit Categories', 'Edit existing categories', 'categories'),
('categories.delete', 'Delete Categories', 'Delete categories', 'categories'),
('customers.view', 'View Customers', 'View customer listings and details', 'customers'),
('customers.edit', 'Edit Customers', 'Edit customer profiles', 'customers'),
('customers.delete', 'Delete Customers', 'Delete customer accounts', 'customers'),
('orders.view', 'View Orders', 'View order listings and details', 'orders'),
('orders.edit', 'Edit Orders', 'Edit order details and status', 'orders'),
('orders.delete', 'Delete Orders', 'Delete or cancel orders', 'orders'),
('orders.own', 'View Own Orders', 'View own order history', 'orders'),
('quotations.create', 'Create Quotations', 'Create new quotations', 'quotations'),
('quotations.view', 'View Quotations', 'View quotation listings and details', 'quotations'),
('quotations.edit', 'Edit Quotations', 'Edit existing quotations', 'quotations'),
('quotations.delete', 'Delete Quotations', 'Delete quotations', 'quotations'),
('quotations.approve', 'Approve Quotations', 'Approve and send quotations', 'quotations'),
('quotations.own', 'View Own Quotations', 'View own quotation history', 'quotations'),
('enquiries.view', 'View Enquiries', 'View enquiry listings and details', 'enquiries'),
('enquiries.edit', 'Edit Enquiries', 'Edit enquiry details and status', 'enquiries'),
('enquiries.assign', 'Assign Enquiries', 'Assign enquiries to staff', 'enquiries'),
('enquiries.respond', 'Respond to Enquiries', 'Respond to customer enquiries', 'enquiries'),
('enquiries.own', 'View Own Enquiries', 'View own enquiry history', 'enquiries'),
('reports.view', 'View Reports', 'View business reports and analytics', 'reports'),
('reports.export', 'Export Reports', 'Export reports to CSV/PDF', 'reports'),
('staff.view', 'View Staff', 'View staff accounts', 'staff'),
('staff.create', 'Create Staff', 'Create new staff accounts', 'staff'),
('staff.edit', 'Edit Staff', 'Edit staff accounts', 'staff'),
('staff.delete', 'Delete Staff', 'Delete staff accounts', 'staff'),
('settings.view', 'View Settings', 'View system settings', 'settings'),
('settings.edit', 'Edit Settings', 'Edit system settings', 'settings'),
('profile.own', 'Manage Own Profile', 'Manage own profile and account', 'profile'),
('cart.create', 'Manage Cart', 'Add/update/remove cart items', 'cart'),
('cart.view', 'View Cart', 'View own cart', 'cart');

-- Insert default categories
INSERT INTO `categories` (`name`, `slug`, `description`, `sort_order`, `is_active`, `is_featured`) VALUES
('Solar Systems', 'solar-systems', 'Complete solar power systems including panels, inverters, and batteries', 1, TRUE, TRUE),
('Starlink', 'starlink', 'Starlink satellite internet kits and installation', 2, TRUE, TRUE),
('WiFi & Networking', 'wifi-networking', 'WiFi extenders, access points, and network equipment', 3, TRUE, TRUE),
('Electrical', 'electrical', 'Electrical tubing, conduit, and wiring accessories', 4, TRUE, TRUE),
('Mechanical Equipment', 'mechanical-equipment', 'Industrial mechanical equipment and parts', 5, TRUE, FALSE),
('Electrical Equipment', 'electrical-equipment', 'Industrial electrical and electronic equipment', 6, TRUE, FALSE);

-- Insert default settings
INSERT INTO `settings` (`key`, `value`, `type`, `group`, `label`, `description`, `is_public`) VALUES
('site_name', 'Genmashy Distributors', 'string', 'general', 'Site Name', 'The name of the website', TRUE),
('site_tagline', 'Electrical Power & Internet Solutions', 'string', 'general', 'Site Tagline', 'Short description of the business', TRUE),
('site_email', 'sales@genmashy.co.zw', 'string', 'general', 'Contact Email', 'Primary contact email', TRUE),
('site_phone', '+263 718 751 792', 'string', 'general', 'Contact Phone', 'Primary contact phone', TRUE),
('site_whatsapp', '263718751792', 'string', 'general', 'WhatsApp Number', 'WhatsApp number for quotes', TRUE),
('site_address', '63 Mendel Road, Avondale, Harare, Zimbabwe', 'string', 'general', 'Business Address', 'Physical business address', TRUE),
('currency_default', 'USD', 'string', 'currency', 'Default Currency', 'Default currency for the store', TRUE),
('currency_usd_enabled', '1', 'boolean', 'currency', 'Enable USD', 'Enable USD currency', TRUE),
('currency_zwg_enabled', '1', 'boolean', 'currency', 'Enable ZWG', 'Enable ZWG currency', TRUE),
('tax_enabled', '1', 'boolean', 'tax', 'Enable Tax', 'Enable tax calculations', TRUE),
('tax_rate', '15.00', 'float', 'tax', 'Tax Rate (%)', 'Default tax rate percentage', TRUE),
('tax_inclusive', '0', 'boolean', 'tax', 'Tax Inclusive', 'Whether prices include tax', TRUE),
('shipping_enabled', '1', 'boolean', 'shipping', 'Enable Shipping', 'Enable shipping calculations', TRUE),
('shipping_free_threshold_usd', '500.00', 'float', 'shipping', 'Free Shipping Threshold (USD)', 'Order amount for free shipping in USD', TRUE),
('shipping_free_threshold_zwg', '5000.00', 'float', 'shipping', 'Free Shipping Threshold (ZWG)', 'Order amount for free shipping in ZWG', TRUE),
('quotation_validity_days', '30', 'integer', 'quotations', 'Quotation Validity (Days)', 'Default quotation validity period', TRUE),
('quotation_prefix', 'GMD-Q', 'string', 'quotations', 'Quotation Number Prefix', 'Prefix for quotation numbers', TRUE),
('order_prefix', 'GMD-ORD', 'string', 'orders', 'Order Number Prefix', 'Prefix for order numbers', TRUE),
('payment_prefix', 'GMD-PAY', 'string', 'payments', 'Payment Number Prefix', 'Prefix for payment numbers', TRUE),
('enquiry_prefix', 'GMD-ENQ', 'string', 'enquiries', 'Enquiry Number Prefix', 'Prefix for enquiry numbers', TRUE),
('session_lifetime', '7200', 'integer', 'security', 'Session Lifetime (seconds)', 'Session lifetime in seconds', FALSE),
('max_login_attempts', '5', 'integer', 'security', 'Max Login Attempts', 'Maximum failed login attempts before lockout', FALSE),
('lockout_duration', '900', 'integer', 'security', 'Lockout Duration (seconds)', 'Account lockout duration in seconds', FALSE),
('password_min_length', '8', 'integer', 'security', 'Minimum Password Length', 'Minimum password length', FALSE),
('require_email_verification', '1', 'boolean', 'security', 'Require Email Verification', 'Require email verification for new accounts', FALSE),
('maintenance_mode', '0', 'boolean', 'system', 'Maintenance Mode', 'Enable maintenance mode', FALSE),
('items_per_page', '20', 'integer', 'system', 'Items Per Page', 'Default pagination limit', FALSE);

-- Create a default super admin user (password: Admin@123 - change on first login!)
-- Email: admin@genmashy.co.zw
INSERT INTO `users` (`uuid`, `role_id`, `email`, `password_hash`, `first_name`, `last_name`, `phone`, `is_active`, `email_verified_at`) VALUES
(UUID(), 1, 'admin@genmashy.co.zw', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Super', 'Admin', '+263718751792', TRUE, NOW());

-- Create indexes for better performance
CREATE INDEX `idx_users_role_active` ON `users` (`role_id`, `is_active`);
CREATE INDEX `idx_products_category_active` ON `products` (`category_id`, `is_active`);
CREATE INDEX `idx_orders_user_status` ON `orders` (`user_id`, `status`);
CREATE INDEX `idx_quotations_user_status` ON `quotations` (`user_id`, `status`);
CREATE INDEX `idx_enquiries_user_status` ON `enquiries` (`user_id`, `status`);