// frontend/js/auth.js - Fixed Authentication and Permissions

// Wrap code in an IIFE for private scope
(function() {
    'use strict';

    // Check if API is loaded first, to prevent runtime errors
    if (typeof API === 'undefined') {
        console.error('API is not loaded! Check if api.js is loaded first.');
        return;
    }

    console.log('Auth.js loading with API available');

    /**
     * Auth object handles all client-side authentication logic, 
     * token management, user state, and permissions.
     */
    window.Auth = {
        currentUser: null,

        // --- Core State & Retrieval ---

        isLoggedIn() {
            // Determines if a token is present in local storage
            return !!API.getToken();
        },

        getCurrentUser() {
            // Retrieves user data from the stored token payload
            const token = API.getToken();
            if (!token) return null;

            // Priority 1: Check cached user data in localStorage
            const cachedUser = localStorage.getItem('wifight_user');
            if (cachedUser) {
                try {
                    return JSON.parse(cachedUser);
                } catch (error) {
                    console.warn('Cached user data corrupted. Re-decoding token.');
                }
            }

            // Priority 2: Decode user data from JWT payload
            try {
                // JWT structure: header.payload.signature
                const payload = token.split('.')[1];
                // Base64 decode and JSON parse
                const decoded = JSON.parse(atob(payload)); 
                return decoded;
            } catch (error) {
                console.error('Error decoding token or token is invalid:', error);
                // Force logout if token is invalid
                API.removeToken(); 
                return null;
            }
        },

        // --- Initialization and Flow ---

        async init() {
            console.log('Auth initializing...');
            
            if (this.isLoggedIn()) {
                // Use the getCurrentUser which handles both cached data and token decoding
                this.currentUser = this.getCurrentUser();

                // Token validation check (using API.health)
                try {
                    await API.health();
                    this.showDashboard();
                } catch (error) {
                    // Token is invalid, expired, or server is down
                    console.error('Token validation failed, forcing logout:', error);
                    this.logout();
                }
            } else {
                this.showLogin();
            }
        },

        // --- Authentication Actions ---

        async login(email, password) {
            try {
                console.log('Attempting login...');
                const response = await API.auth.login(email, password);
                
                if (response.success && response.data && response.data.token) {
                    API.setToken(response.data.token);
                    this.currentUser = response.data.user;
                    
                    // Store user object alongside token (for quick access without decoding)
                    localStorage.setItem('wifight_user', JSON.stringify(response.data.user));
                    
                    console.log('Login successful, showing dashboard');
                    this.showDashboard();
                    return { success: true };
                } else {
                    console.error('Login failed:', response);
                    return { 
                        success: false, 
                        message: response.message || 'Login failed due to unknown error' 
                    };
                }
            } catch (error) {
                console.error('Login exception:', error);
                // API.request throws an error object, check if it contains a custom message
                const message = error.message || 'Network or server error during login.';
                return { 
                    success: false, 
                    message: message 
                };
            }
        },

        async logout() {
            try {
                // Attempt server-side logout first (ignoring failure)
                await API.auth.logout();
            } catch (error) {
                console.error('Server-side logout error:', error);
            }
            
            // Client-side cleanup (always executed)
            API.removeToken();
            localStorage.removeItem('wifight_user');
            this.currentUser = null;
            this.showLogin();
        },

        // --- View Handlers ---

        showLogin() {
            console.log('Showing login page');
            const loginPage = document.getElementById('loginPage');
            const dashboard = document.getElementById('dashboard');
            
            if (loginPage) loginPage.classList.remove('hidden');
            if (dashboard) dashboard.classList.add('hidden');
        },

        showDashboard() {
            console.log('Showing dashboard');
            const loginPage = document.getElementById('loginPage');
            const dashboard = document.getElementById('dashboard');
            
            if (loginPage) loginPage.classList.add('hidden');
            if (dashboard) dashboard.classList.remove('hidden');
            
            // Update user info display using template literal
            if (this.currentUser) {
                const userInfoEl = document.getElementById('userInfo');
                if (userInfoEl) {
                    userInfoEl.textContent = `${this.currentUser.full_name || this.currentUser.email} (${this.currentUser.role})`;
                }
            }
            
            // Initialize dashboard if available
            if (typeof Dashboard !== 'undefined' && Dashboard.init) {
                console.log('Initializing dashboard...');
                Dashboard.init();
            }
        },

        // --- Permission Checks (New Feature) ---

        hasRole(role) {
            return this.currentUser && this.currentUser.role === role;
        },

        hasPermission(permission) {
            // Permission definitions based on role
            const permissions = {
                'admin': ['all'],
                'manager': ['view', 'create', 'update', 'delete_own', 'manage_plans'],
                'staff': ['view', 'create_vouchers'],
                'customer': ['view_own_vouchers']
            };
            
            if (!this.currentUser) return false;
            
            const userPermissions = permissions[this.currentUser.role] || [];
            
            // Check for 'all' permission or the specific required permission
            return userPermissions.includes('all') || userPermissions.includes(permission);
        }
    };

    // --- DOM Event Handlers ---

    document.addEventListener('DOMContentLoaded', function() {
        console.log('DOM loaded, setting up auth handlers');
        
        const loginForm = document.getElementById('loginForm');
        const loginError = document.getElementById('loginError');

        if (loginForm) {
            // Use async function for the event listener to simplify promise handling
            loginForm.addEventListener('submit', async function(e) {
                e.preventDefault();
                
                const email = document.getElementById('loginEmail').value;
                const password = document.getElementById('loginPassword').value;
                
                // UI Cleanup
                if (loginError) {
                    loginError.classList.add('hidden');
                    loginError.textContent = '';
                }
                
                const submitBtn = loginForm.querySelector('button[type="submit"]');
                const originalText = submitBtn.textContent;
                
                // UI State: Disable button and show loading
                submitBtn.disabled = true;
                submitBtn.textContent = 'Logging in...';
                
                try {
                    const result = await Auth.login(email, password);
                    
                    if (result.success) {
                        loginForm.reset();
                    } else {
                        // Display error message
                        if (loginError) {
                            loginError.textContent = result.message;
                            loginError.classList.remove('hidden');
                        }
                    }
                } catch (error) {
                    // This catches unexpected exceptions during Auth.login execution
                    console.error('Login form exception:', error);
                    if (loginError) {
                        loginError.textContent = 'A critical error occurred. Please check console.';
                        loginError.classList.remove('hidden');
                    }
                } finally {
                    // UI State: Re-enable button
                    submitBtn.disabled = false;
                    submitBtn.textContent = originalText;
                }
            });
        }

        // Initialize auth state after DOM is ready
        Auth.init();
    });

    // --- Global Utility ---
    
    // Attach global logout function for use in HTML via onclick
    window.logout = function() {
        if (confirm('Are you sure you want to logout?')) {
            Auth.logout();
        }
    };

    console.log('Auth.js loaded successfully');
})();