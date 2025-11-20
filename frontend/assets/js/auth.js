// WiFight ISP - Authentication Manager

class Auth {
    static isAuthenticated() {
        return !!localStorage.getItem(CONFIG.TOKEN_KEY);
    }

    static getUser() {
        const userStr = localStorage.getItem(CONFIG.USER_KEY);
        return userStr ? JSON.parse(userStr) : null;
    }

    static setUser(user) {
        localStorage.setItem(CONFIG.USER_KEY, JSON.stringify(user));
    }

    static setTokens(accessToken, refreshToken) {
        localStorage.setItem(CONFIG.TOKEN_KEY, accessToken);
        if (refreshToken) {
            localStorage.setItem(CONFIG.REFRESH_TOKEN_KEY, refreshToken);
        }
    }

    static clearAuth() {
        localStorage.removeItem(CONFIG.TOKEN_KEY);
        localStorage.removeItem(CONFIG.REFRESH_TOKEN_KEY);
        localStorage.removeItem(CONFIG.USER_KEY);
    }

    static requireAuth() {
        if (!this.isAuthenticated()) {
            window.location.href = 'login.html';
            return false;
        }
        return true;
    }

    static hasRole(roles) {
        const user = this.getUser();
        if (!user) return false;

        if (Array.isArray(roles)) {
            return roles.includes(user.role);
        }
        return user.role === roles;
    }

    static async login(email, password) {
        try {
            const response = await API.login(email, password);

            if (response.success && response.data) {
                this.setTokens(response.data.access_token, response.data.refresh_token);
                this.setUser(response.data.user);
                return { success: true };
            }

            return { success: false, error: response.message || 'Login failed' };
        } catch (error) {
            return { success: false, error: error.message };
        }
    }

    static async logout() {
        try {
            await API.logout();
        } catch (error) {
            console.error('Logout error:', error);
        } finally {
            this.clearAuth();
            window.location.href = 'login.html';
        }
    }
}

// Check authentication on protected pages
if (window.location.pathname.includes('index.html') ||
    (window.location.pathname.endsWith('/') && !window.location.pathname.includes('login'))) {
    Auth.requireAuth();
}
