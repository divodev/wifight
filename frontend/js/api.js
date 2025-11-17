// frontend/js/api.js - Complete API Implementation

/**
 * Determines the API base URL.
 * Assumes the API is one level up and in a 'backend/api' directory 
 * relative to the path containing 'frontend/'.
 */
function getApiBaseUrl() {
    const origin = window.location.origin;
    const pathname = window.location.pathname;

    // Use a robust check to handle paths in subdirectories
    if (pathname.includes('/frontend/')) {
        return origin + pathname.split('/frontend/')[0] + '/backend/api';
    }
    // Fallback for paths without '/frontend/'
    return origin + '/backend/api';
}

const API_BASE_URL = getApiBaseUrl();
console.log('API Base URL:', API_BASE_URL);

/**
 * The main API object for handling all client-server communication using fetch.
 * Uses modern ES6+ syntax (async/await, object method shorthand, template literals).
 */
const API = {
    // --- Token Management ---

    getToken() {
        return localStorage.getItem('wifight_token');
    },

    setToken(token) {
        localStorage.setItem('wifight_token', token);
    },

    removeToken() {
        localStorage.removeItem('wifight_token');
        // Always remove associated user data on client-side logout
        localStorage.removeItem('wifight_user');
    },

    // --- Core Request Function ---

    /**
     * Executes a fetch request to the API.
     * @param {string} endpoint The API path (e.g., 'auth/login.php').
     * @param {object} options Fetch options (method, body, headers, etc.).
     * @returns {Promise<object>} The parsed JSON data.
     */
    async request(endpoint, options = {}) {
        const token = this.getToken();
        const url = `${API_BASE_URL}/${endpoint}`;

        // Construct headers: default Content-Type, Authorization, and any custom headers
        const headers = {
            'Content-Type': 'application/json',
            ...(token && { 'Authorization': `Bearer ${token}` }),
            ...options.headers // Merge custom headers
        };

        try {
            const response = await fetch(url, {
                method: options.method || 'GET', 
                body: options.body,              
                ...options,                      // Spread remaining fetch options
                headers                          // Ensure the constructed headers are used
            });

            // 1. Content Type Check
            const contentType = response.headers.get('content-type') || '';
            if (!contentType.includes('application/json')) {
                const text = await response.text();
                console.error('Non-JSON response:', text);
                throw new Error('Server returned non-JSON response');
            }
            
            // Read response text once
            const text = await response.text();
            
            // 2. Empty Response Check
            if (!text || text.trim() === '') {
                // If status is 204 No Content, this might be expected, 
                // but the current logic treats it as an error if JSON is expected.
                throw new Error('Server returned empty response');
            }
            
            let data;
            try {
                // 3. JSON Parsing Check
                data = JSON.parse(text);
            } catch (e) {
                console.error('JSON parse error:', text);
                throw new Error('Invalid JSON response');
            }
            
            // 4. HTTP Status Check (for non-2xx codes)
            if (!response.ok) {
                throw new Error(data.message || `Request failed with status ${response.status}`);
            }

            return data;
        } catch (error) {
            console.error('API Error:', error);
            throw error; // Propagate the error
        }
    },

    // --- Auth Endpoints ---
    auth: {
        async login(email, password) {
            return API.request('auth/login.php', {
                method: 'POST',
                body: JSON.stringify({ email, password })
            });
        },
        async logout() {
            try {
                // Attempt to notify the server (ignore network error)
                await API.request('auth/logout.php', { method: 'POST' });
            } catch (e) {
                console.error('Logout API error:', e);
            }
            // Always clean up client-side storage regardless of server response
            API.removeToken();
        }
    },

    // --- Controllers Endpoints ---
    controllers: {
        async list(locationId = null) {
            const query = locationId ? `?location_id=${locationId}` : '';
            return API.request(`controllers/list.php${query}`);
        },
        async create(data) {
            return API.request('controllers/create.php', {
                method: 'POST',
                body: JSON.stringify(data)
            });
        },
        async update(id, data) {
            return API.request('controllers/update.php', {
                method: 'POST',
                body: JSON.stringify({ id, ...data }) // ES6 spread for payload
            });
        },
        async delete(id) {
            return API.request('controllers/delete.php', {
                method: 'POST',
                body: JSON.stringify({ id })
            });
        },
        async testConnection(controllerId) {
            return API.request('omada/test_connection.php', {
                method: 'POST',
                body: JSON.stringify({ controller_id: controllerId })
            });
        }
    },

    // --- Plans Endpoints ---
    plans: {
        async list(locationId = null, status = null) {
            const params = new URLSearchParams();
            if (locationId) params.append('location_id', locationId);
            if (status) params.append('status', status);
            const query = params.toString() ? `?${params}` : '';
            return API.request(`plans/list.php${query}`);
        },
        async create(data) {
            return API.request('plans/create.php', {
                method: 'POST',
                body: JSON.stringify(data)
            });
        },
        async update(id, data) {
            return API.request('plans/update.php', {
                method: 'POST',
                body: JSON.stringify({ id, ...data })
            });
        },
        async delete(id) {
            return API.request('plans/delete.php', {
                method: 'POST',
                body: JSON.stringify({ id })
            });
        }
    },

    // --- Vouchers Endpoints ---
    vouchers: {
        async list(filters = {}) {
            // Use URLSearchParams to handle filter objects cleanly
            const params = new URLSearchParams(filters);
            const query = params.toString() ? `?${params}` : '';
            return API.request(`vouchers/list.php${query}`);
        },
        async generate(planId, quantity, batchName = null) {
            return API.request('vouchers/generate.php', {
                method: 'POST',
                body: JSON.stringify({ 
                    plan_id: planId, 
                    quantity, 
                    batch_name: batchName 
                })
            });
        },
        async validate(code) {
            return API.request('vouchers/validate.php', {
                method: 'POST',
                body: JSON.stringify({ code })
            });
        },
        async stats(locationId = null) {
            const query = locationId ? `?location_id=${locationId}` : '';
            return API.request(`vouchers/stats.php${query}`);
        }
    },

    // --- Sessions Endpoints ---
    sessions: {
        async active(controllerId = null) {
            const query = controllerId ? `?controller_id=${controllerId}` : '';
            return API.request(`sessions/active.php${query}`);
        },
        async history(filters = {}) {
            const params = new URLSearchParams(filters);
            const query = params.toString() ? `?${params}` : '';
            return API.request(`sessions/history.php${query}`);
        },
        async terminate(sessionId) {
            return API.request('sessions/terminate.php', {
                method: 'POST',
                body: JSON.stringify({ id: sessionId })
            });
        }
    },

    // --- Users Endpoints ---
    users: {
        async list(locationId = null, role = null) {
            const params = new URLSearchParams();
            if (locationId) params.append('location_id', locationId);
            if (role) params.append('role', role);
            const query = params.toString() ? `?${params}` : '';
            return API.request(`users/list.php${query}`);
        },
        async create(data) {
            return API.request('users/create.php', {
                method: 'POST',
                body: JSON.stringify(data)
            });
        }
    },

    // --- Health Check ---
    async health() {
        return API.request('health.php');
    }
};

console.log('API object created and ready:', typeof API);