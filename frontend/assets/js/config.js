// WiFight ISP - Configuration

const CONFIG = {
    API_BASE_URL: 'http://localhost/api/v1',
    TOKEN_KEY: 'wifight_token',
    REFRESH_TOKEN_KEY: 'wifight_refresh_token',
    USER_KEY: 'wifight_user',

    // API Endpoints
    ENDPOINTS: {
        AUTH: {
            LOGIN: '/auth/login',
            LOGOUT: '/auth/logout',
            REFRESH: '/auth/refresh',
            REGISTER: '/auth/register'
        },
        USERS: '/users',
        PLANS: '/plans',
        SESSIONS: '/sessions',
        PAYMENTS: '/payments',
        SUBSCRIPTIONS: '/subscriptions',
        CONTROLLERS: '/controllers',
        VOUCHERS: '/vouchers',
        ANALYTICS: '/analytics',
        NOTIFICATIONS: '/notifications',
        WEBHOOKS: '/webhooks',
        PERFORMANCE: '/performance'
    },

    // Pagination
    DEFAULT_PAGE_SIZE: 20,

    // Date format
    DATE_FORMAT: 'YYYY-MM-DD HH:mm:ss',

    // Chart colors
    CHART_COLORS: {
        primary: '#4CAF50',
        secondary: '#2196F3',
        warning: '#ff9800',
        danger: '#f44336',
        success: '#4CAF50'
    }
};
