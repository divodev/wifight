// WiFight ISP - API Client

class API {
    static async request(endpoint, options = {}) {
        const token = localStorage.getItem(CONFIG.TOKEN_KEY);

        const defaultHeaders = {
            'Content-Type': 'application/json'
        };

        if (token) {
            defaultHeaders['Authorization'] = `Bearer ${token}`;
        }

        const config = {
            method: options.method || 'GET',
            headers: { ...defaultHeaders, ...options.headers },
            ...options
        };

        if (options.body && typeof options.body === 'object') {
            config.body = JSON.stringify(options.body);
        }

        try {
            const response = await fetch(`${CONFIG.API_BASE_URL}${endpoint}`, config);
            const data = await response.json();

            if (!response.ok) {
                // Token expired, try to refresh
                if (response.status === 401 && token) {
                    const refreshed = await this.refreshToken();
                    if (refreshed) {
                        // Retry original request
                        return this.request(endpoint, options);
                    } else {
                        // Redirect to login
                        window.location.href = 'login.html';
                        throw new Error('Session expired. Please login again.');
                    }
                }

                throw new Error(data.message || 'Request failed');
            }

            return data;
        } catch (error) {
            console.error('API Error:', error);
            throw error;
        }
    }

    static async refreshToken() {
        const refreshToken = localStorage.getItem(CONFIG.REFRESH_TOKEN_KEY);
        if (!refreshToken) return false;

        try {
            const response = await fetch(`${CONFIG.API_BASE_URL}${CONFIG.ENDPOINTS.AUTH.REFRESH}`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ refresh_token: refreshToken })
            });

            const data = await response.json();

            if (response.ok && data.data) {
                localStorage.setItem(CONFIG.TOKEN_KEY, data.data.access_token);
                if (data.data.refresh_token) {
                    localStorage.setItem(CONFIG.REFRESH_TOKEN_KEY, data.data.refresh_token);
                }
                return true;
            }

            return false;
        } catch (error) {
            console.error('Token refresh error:', error);
            return false;
        }
    }

    // Auth methods
    static async login(email, password) {
        return this.request(CONFIG.ENDPOINTS.AUTH.LOGIN, {
            method: 'POST',
            body: { email, password }
        });
    }

    static async logout() {
        return this.request(CONFIG.ENDPOINTS.AUTH.LOGOUT, {
            method: 'POST'
        });
    }

    // User methods
    static async getUsers(params = {}) {
        const query = new URLSearchParams(params).toString();
        return this.request(`${CONFIG.ENDPOINTS.USERS}?${query}`);
    }

    static async getUser(id) {
        return this.request(`${CONFIG.ENDPOINTS.USERS}/${id}`);
    }

    static async createUser(userData) {
        return this.request(CONFIG.ENDPOINTS.USERS, {
            method: 'POST',
            body: userData
        });
    }

    static async updateUser(id, userData) {
        return this.request(`${CONFIG.ENDPOINTS.USERS}/${id}`, {
            method: 'PUT',
            body: userData
        });
    }

    static async deleteUser(id) {
        return this.request(`${CONFIG.ENDPOINTS.USERS}/${id}`, {
            method: 'DELETE'
        });
    }

    // Plans methods
    static async getPlans(params = {}) {
        const query = new URLSearchParams(params).toString();
        return this.request(`${CONFIG.ENDPOINTS.PLANS}?${query}`);
    }

    static async getPlan(id) {
        return this.request(`${CONFIG.ENDPOINTS.PLANS}/${id}`);
    }

    static async createPlan(planData) {
        return this.request(CONFIG.ENDPOINTS.PLANS, {
            method: 'POST',
            body: planData
        });
    }

    static async updatePlan(id, planData) {
        return this.request(`${CONFIG.ENDPOINTS.PLANS}/${id}`, {
            method: 'PUT',
            body: planData
        });
    }

    static async deletePlan(id) {
        return this.request(`${CONFIG.ENDPOINTS.PLANS}/${id}`, {
            method: 'DELETE'
        });
    }

    // Sessions methods
    static async getSessions(params = {}) {
        const query = new URLSearchParams(params).toString();
        return this.request(`${CONFIG.ENDPOINTS.SESSIONS}?${query}`);
    }

    static async endSession(id, usageData = {}) {
        return this.request(`${CONFIG.ENDPOINTS.SESSIONS}/${id}/end`, {
            method: 'PUT',
            body: usageData
        });
    }

    // Analytics methods
    static async getDashboard() {
        return this.request(`${CONFIG.ENDPOINTS.ANALYTICS}/dashboard`);
    }

    static async getRevenue(days = 30) {
        return this.request(`${CONFIG.ENDPOINTS.ANALYTICS}/revenue?days=${days}`);
    }

    static async getUserGrowth(days = 30) {
        return this.request(`${CONFIG.ENDPOINTS.ANALYTICS}/users?days=${days}`);
    }

    static async getSessionStats(days = 7) {
        return this.request(`${CONFIG.ENDPOINTS.ANALYTICS}/sessions?days=${days}`);
    }

    static async getBandwidth(days = 7) {
        return this.request(`${CONFIG.ENDPOINTS.ANALYTICS}/bandwidth?days=${days}`);
    }

    static async getTopPlans(limit = 10) {
        return this.request(`${CONFIG.ENDPOINTS.ANALYTICS}/top-plans?limit=${limit}`);
    }

    // Notifications methods
    static async getNotifications(params = {}) {
        const query = new URLSearchParams(params).toString();
        return this.request(`${CONFIG.ENDPOINTS.NOTIFICATIONS}?${query}`);
    }

    static async getUnreadCount() {
        return this.request(`${CONFIG.ENDPOINTS.NOTIFICATIONS}/count`);
    }

    static async markAsRead(id) {
        return this.request(`${CONFIG.ENDPOINTS.NOTIFICATIONS}/mark-read/${id}`, {
            method: 'POST'
        });
    }

    static async markAllAsRead() {
        return this.request(`${CONFIG.ENDPOINTS.NOTIFICATIONS}/mark-all-read`, {
            method: 'POST'
        });
    }

    // Payments methods
    static async getPayments(params = {}) {
        const query = new URLSearchParams(params).toString();
        return this.request(`${CONFIG.ENDPOINTS.PAYMENTS}?${query}`);
    }

    // Controllers methods
    static async getControllers(params = {}) {
        const query = new URLSearchParams(params).toString();
        return this.request(`${CONFIG.ENDPOINTS.CONTROLLERS}?${query}`);
    }

    static async testController(id) {
        return this.request(`${CONFIG.ENDPOINTS.CONTROLLERS}/${id}/test`, {
            method: 'POST'
        });
    }
}
