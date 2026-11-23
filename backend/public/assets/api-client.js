/**
 * Genmashy API Client
 * Connects the existing frontend to the new backend API
 */

class GenmashyAPI {
    constructor(baseUrl = '/api') {
        this.baseUrl = baseUrl;
        this.token = null;
        this.csrfToken = null;
    }

    // Initialize CSRF token from meta tag
    init() {
        const meta = document.querySelector('meta[name="csrf-token"]');
        if (meta) {
            this.csrfToken = meta.getAttribute('content');
        }
        return this;
    }

    // Set auth token
    setToken(token) {
        this.token = token;
        return this;
    }

    // Get headers for requests
    getHeaders(includeAuth = true) {
        const headers = {
            'Content-Type': 'application/json',
            'Accept': 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
        };

        if (this.csrfToken) {
            headers['X-CSRF-TOKEN'] = this.csrfToken;
        }

        if (includeAuth && this.token) {
            headers['Authorization'] = `Bearer ${this.token}`;
        }

        return headers;
    }

    // Generic request method
    async request(method, endpoint, data = null, includeAuth = true) {
        const url = `${this.baseUrl}${endpoint}`;
        const options = {
            method: method.toUpperCase(),
            headers: this.getHeaders(includeAuth),
            credentials: 'same-origin',
        };

        if (data && ['POST', 'PUT', 'PATCH'].includes(method.toUpperCase())) {
            options.body = JSON.stringify(data);
        }

        try {
            const response = await fetch(url, options);
            const result = await response.json();

            if (!response.ok) {
                throw new APIError(result.message || 'Request failed', response.status, result.errors);
            }

            return result;
        } catch (error) {
            if (error instanceof APIError) throw error;
            throw new APIError(error.message || 'Network error', 0);
        }
    }

    // Convenience methods
    get(endpoint, includeAuth = true) {
        return this.request('GET', endpoint, null, includeAuth);
    }

    post(endpoint, data, includeAuth = true) {
        return this.request('POST', endpoint, data, includeAuth);
    }

    put(endpoint, data, includeAuth = true) {
        return this.request('PUT', endpoint, data, includeAuth);
    }

    patch(endpoint, data, includeAuth = true) {
        return this.request('PATCH', endpoint, data, includeAuth);
    }

    delete(endpoint, includeAuth = true) {
        return this.request('DELETE', endpoint, null, includeAuth);
    }

    // Authentication
    async register(userData) {
        const result = await this.post('/auth/register', userData, false);
        if (result.data?.token) {
            this.setToken(result.data.token);
        }
        return result;
    }

    async login(email, password, remember = false) {
        const result = await this.post('/auth/login', { email, password, remember }, false);
        if (result.data?.token) {
            this.setToken(result.data.token);
        }
        return result;
    }

    async logout() {
        const result = await this.post('/auth/logout');
        this.token = null;
        return result;
    }

    async forgotPassword(email) {
        return this.post('/auth/forgot-password', { email }, false);
    }

    async resetPassword(token, email, password, password_confirmation) {
        return this.post('/auth/reset-password', { token, email, password, password_confirmation }, false);
    }

    async verifyEmail(token, email) {
        return this.get(`/auth/verify-email?token=${token}&email=${encodeURIComponent(email)}`, false);
    }

    // Products
    async getProducts(params = {}) {
        const query = new URLSearchParams(params).toString();
        return this.get(`/products${query ? '?' + query : ''}`);
    }

    async getFeaturedProducts(limit = 8) {
        return this.get(`/products/featured?limit=${limit}`);
    }

    async searchProducts(query, filters = {}) {
        const params = { q: query, ...filters };
        const queryString = new URLSearchParams(params).toString();
        return this.get(`/products/search?${queryString}`);
    }

    async getProduct(slug) {
        return this.get(`/products/${slug}`);
    }

    async getRelatedProducts(id, limit = 4) {
        return this.get(`/products/${id}/related?limit=${limit}`);
    }

    // Categories
    async getCategories() {
        return this.get('/categories');
    }

    async getCategoryTree() {
        return this.get('/categories/tree');
    }

    async getCategory(slug) {
        return this.get(`/categories/${slug}`);
    }

    // Cart (Auth Required)
    async getCart() {
        return this.get('/cart');
    }

    async addToCart(productId, quantity = 1, variantId = null) {
        return this.post('/cart', { product_id: productId, quantity, variant_id: variantId });
    }

    async updateCartItem(itemId, quantity) {
        return this.put(`/cart/${itemId}`, { quantity });
    }

    async removeFromCart(itemId) {
        return this.delete(`/cart/${itemId}`);
    }

    async clearCart() {
        return this.delete('/cart');
    }

    async setCartCurrency(currency) {
        return this.put('/cart/currency', { currency });
    }

    // Orders (Auth Required)
    async getOrders(params = {}) {
        const query = new URLSearchParams(params).toString();
        return this.get(`/orders${query ? '?' + query : ''}`);
    }

    async createOrder(orderData) {
        return this.post('/orders', orderData);
    }

    async getOrder(id) {
        return this.get(`/orders/${id}`);
    }

    async cancelOrder(id) {
        return this.post(`/orders/${id}/cancel`);
    }

    // Quotations (Auth Required)
    async getQuotations(params = {}) {
        const query = new URLSearchParams(params).toString();
        return this.get(`/quotations${query ? '?' + query : ''}`);
    }

    async createQuotation(quotationData) {
        return this.post('/quotations', quotationData);
    }

    async getQuotation(id) {
        return this.get(`/quotations/${id}`);
    }

    async acceptQuotation(id) {
        return this.post(`/quotations/${id}/accept`);
    }

    async rejectQuotation(id, reason = '') {
        return this.post(`/quotations/${id}/reject`, { reason });
    }

    // Enquiries (Auth Required)
    async getEnquiries(params = {}) {
        const query = new URLSearchParams(params).toString();
        return this.get(`/enquiries${query ? '?' + query : ''}`);
    }

    async createEnquiry(enquiryData) {
        return this.post('/enquiries', enquiryData);
    }

    async getEnquiry(id) {
        return this.get(`/enquiries/${id}`);
    }

    async respondToEnquiry(id, message) {
        return this.post(`/enquiries/${id}/respond`, { message });
    }

    // Chat (Auth Required)
    async getConversations(params = {}) {
        const query = new URLSearchParams(params).toString();
        return this.get(`/chat/conversations${query ? '?' + query : ''}`);
    }

    async createConversation(data) {
        return this.post('/chat/conversations', data);
    }

    async getConversation(id) {
        return this.get(`/chat/conversations/${id}`);
    }

    async sendMessage(conversationId, message, attachments = []) {
        return this.post(`/chat/conversations/${conversationId}/messages`, { message, attachments });
    }

    async markConversationAsRead(id) {
        return this.put(`/chat/conversations/${id}/read`);
    }

    // Notifications (Auth Required)
    async getNotifications(params = {}) {
        const query = new URLSearchParams(params).toString();
        return this.get(`/notifications${query ? '?' + query : ''}`);
    }

    async markNotificationAsRead(id) {
        return this.put(`/notifications/${id}/read`);
    }

    async markAllNotificationsAsRead() {
        return this.put('/notifications/read-all');
    }

    // User Profile (Auth Required)
    async getProfile() {
        return this.get('/user');
    }

    async updateProfile(data) {
        return this.put('/user', data);
    }

    async changePassword(currentPassword, password, password_confirmation) {
        return this.put('/user/password', { current_password: currentPassword, password, password_confirmation });
    }

    async getAddresses() {
        return this.get('/user/addresses');
    }

    async addAddress(addressData) {
        return this.post('/user/addresses', addressData);
    }

    async updateAddress(id, addressData) {
        return this.put(`/user/addresses/${id}`, addressData);
    }

    async deleteAddress(id) {
        return this.delete(`/user/addresses/${id}`);
    }

    async setDefaultAddress(id) {
        return this.post(`/user/addresses/${id}/default`);
    }
}

// API Error class
class APIError extends Error {
    constructor(message, status, errors = null) {
        super(message);
        this.name = 'APIError';
        this.status = status;
        this.errors = errors;
    }
}

// Global API instance
window.GenmashyAPI = new GenmashyAPI().init();

// Auto-attach CSRF token to forms
document.addEventListener('DOMContentLoaded', () => {
    const forms = document.querySelectorAll('form[data-api]');
    forms.forEach(form => {
        form.addEventListener('submit', async (e) => {
            e.preventDefault();
            const endpoint = form.dataset.api;
            const method = form.dataset.method || 'POST';
            const formData = new FormData(form);
            const data = Object.fromEntries(formData.entries());

            try {
                const result = await window.GenmashyAPI[method.toLowerCase()](endpoint, data);
                // Handle success
                if (form.dataset.successUrl) {
                    window.location.href = form.dataset.successUrl;
                } else if (form.dataset.successCallback) {
                    window[form.dataset.successCallback](result);
                }
            } catch (error) {
                // Handle error
                if (form.dataset.errorCallback) {
                    window[form.dataset.errorCallback](error);
                } else {
                    console.error('API Error:', error);
                    alert(error.message);
                }
            }
        });
    });
});

// Utility functions for common UI patterns
window.GenmashyUI = {
    // Show loading state on button
    setButtonLoading(button, loading = true) {
        if (loading) {
            button.disabled = true;
            button.dataset.originalText = button.innerHTML;
            button.innerHTML = '<span class="spinner"></span> Loading...';
        } else {
            button.disabled = false;
            button.innerHTML = button.dataset.originalText || button.innerHTML;
        }
    },

    // Show toast notification
    toast(message, type = 'info', duration = 5000) {
        const container = this.getOrCreateToastContainer();
        const toast = document.createElement('div');
        toast.className = `toast toast-${type}`;
        toast.innerHTML = `
            <div class="toast-content">${message}</div>
            <button class="toast-close">&times;</button>
        `;
        
        toast.querySelector('.toast-close').addEventListener('click', () => toast.remove());
        container.appendChild(toast);
        
        setTimeout(() => toast.classList.add('show'), 10);
        setTimeout(() => {
            toast.classList.remove('show');
            setTimeout(() => toast.remove(), 300);
        }, duration);
    },

    getOrCreateToastContainer() {
        let container = document.getElementById('toast-container');
        if (!container) {
            container = document.createElement('div');
            container.id = 'toast-container';
            container.style.cssText = 'position:fixed;top:20px;right:20px;z-index:9999;display:flex;flex-direction:column;gap:10px;';
            document.body.appendChild(container);
        }
        return container;
    },

    // Format currency
    formatCurrency(amount, currency = 'USD') {
        const symbols = { USD: '$', ZWG: 'ZWG$' };
        return `${symbols[currency] || '$'}${Number(amount).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;
    },

    // Format date
    formatDate(dateString, options = {}) {
        const defaultOptions = { year: 'numeric', month: 'short', day: 'numeric' };
        return new Date(dateString).toLocaleDateString('en-US', { ...defaultOptions, ...options });
    },

    // Get URL parameter
    getUrlParam(name) {
        return new URLSearchParams(window.location.search).get(name);
    },
};

// Export for module systems
if (typeof module !== 'undefined' && module.exports) {
    module.exports = { GenmashyAPI, APIError };
}