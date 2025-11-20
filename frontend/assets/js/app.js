// WiFight ISP - Main Application

class App {
    constructor() {
        this.currentPage = 'dashboard';
        this.init();
    }

    init() {
        this.setupEventListeners();
        this.loadUserInfo();
        this.loadNotificationCount();
        this.loadPage('dashboard');

        // Refresh notification count every minute
        setInterval(() => this.loadNotificationCount(), 60000);
    }

    setupEventListeners() {
        // Sidebar navigation
        document.querySelectorAll('.nav-link[data-page]').forEach(link => {
            link.addEventListener('click', (e) => {
                e.preventDefault();
                const page = link.dataset.page;
                this.loadPage(page);

                // Update active state
                document.querySelectorAll('.nav-link').forEach(l => l.classList.remove('active'));
                link.classList.add('active');

                // Close sidebar on mobile
                if (window.innerWidth <= 768) {
                    document.getElementById('sidebar').classList.remove('active');
                }
            });
        });

        // Menu toggle
        document.getElementById('menu-toggle')?.addEventListener('click', () => {
            document.getElementById('sidebar').classList.toggle('active');
        });

        // Logout
        document.getElementById('logout-btn')?.addEventListener('click', (e) => {
            e.preventDefault();
            if (confirm('Are you sure you want to logout?')) {
                Auth.logout();
            }
        });
    }

    async loadUserInfo() {
        const user = Auth.getUser();
        if (user) {
            document.getElementById('user-name').textContent = user.username;
            document.getElementById('user-role').textContent = user.role.toUpperCase();
        }
    }

    async loadNotificationCount() {
        try {
            const response = await API.getUnreadCount();
            if (response.success && response.data) {
                const count = response.data.unread_count;
                const badge = document.getElementById('notification-badge');
                if (badge) {
                    badge.textContent = count;
                    badge.style.display = count > 0 ? 'inline' : 'none';
                }
            }
        } catch (error) {
            console.error('Failed to load notification count:', error);
        }
    }

    async loadPage(pageName) {
        this.currentPage = pageName;
        const content = document.getElementById('content');

        try {
            switch (pageName) {
                case 'dashboard':
                    await this.loadDashboard();
                    break;
                case 'users':
                    await this.loadUsers();
                    break;
                case 'plans':
                    await this.loadPlans();
                    break;
                case 'sessions':
                    await this.loadSessions();
                    break;
                case 'payments':
                    await this.loadPayments();
                    break;
                case 'analytics':
                    await this.loadAnalytics();
                    break;
                default:
                    content.innerHTML = `
                        <div class="card">
                            <h2>${pageName.charAt(0).toUpperCase() + pageName.slice(1)}</h2>
                            <p>This page is under development.</p>
                        </div>
                    `;
            }
        } catch (error) {
            console.error('Page load error:', error);
            this.showError('Failed to load page content');
        }
    }

    async loadDashboard() {
        const content = document.getElementById('content');
        content.innerHTML = '<div class="loading">Loading dashboard...</div>';

        try {
            const response = await API.getDashboard();
            if (response.success && response.data) {
                const data = response.data;
                content.innerHTML = DashboardPage.render(data);
            }
        } catch (error) {
            this.showError('Failed to load dashboard');
        }
    }

    async loadUsers() {
        const content = document.getElementById('content');
        content.innerHTML = '<div class="loading">Loading users...</div>';

        try {
            const response = await API.getUsers({ limit: 50 });
            if (response.success && response.data) {
                content.innerHTML = UsersPage.render(response.data.users);
            }
        } catch (error) {
            this.showError('Failed to load users');
        }
    }

    async loadPlans() {
        const content = document.getElementById('content');
        content.innerHTML = '<div class="loading">Loading plans...</div>';

        try {
            const response = await API.getPlans();
            if (response.success && response.data) {
                content.innerHTML = PlansPage.render(response.data.plans);
            }
        } catch (error) {
            this.showError('Failed to load plans');
        }
    }

    async loadSessions() {
        const content = document.getElementById('content');
        content.innerHTML = '<div class="loading">Loading sessions...</div>';

        try {
            const response = await API.getSessions({ status: 'active', limit: 100 });
            if (response.success && response.data) {
                content.innerHTML = SessionsPage.render(response.data.sessions);
            }
        } catch (error) {
            this.showError('Failed to load sessions');
        }
    }

    async loadPayments() {
        const content = document.getElementById('content');
        content.innerHTML = '<div class="loading">Loading payments...</div>';

        try {
            const response = await API.getPayments({ limit: 50 });
            if (response.success && response.data) {
                content.innerHTML = PaymentsPage.render(response.data.payments);
            }
        } catch (error) {
            this.showError('Failed to load payments');
        }
    }

    async loadAnalytics() {
        const content = document.getElementById('content');
        content.innerHTML = '<div class="loading">Loading analytics...</div>';

        try {
            const [revenueResp, userResp, sessionResp] = await Promise.all([
                API.getRevenue(30),
                API.getUserGrowth(30),
                API.getSessionStats(7)
            ]);

            content.innerHTML = AnalyticsPage.render({
                revenue: revenueResp.data,
                users: userResp.data,
                sessions: sessionResp.data
            });
        } catch (error) {
            this.showError('Failed to load analytics');
        }
    }

    showError(message) {
        const content = document.getElementById('content');
        content.innerHTML = `
            <div class="alert alert-error">
                <i class="fas fa-exclamation-circle"></i> ${message}
            </div>
        `;
    }

    static showLoading() {
        document.getElementById('loading-overlay').classList.add('active');
    }

    static hideLoading() {
        document.getElementById('loading-overlay').classList.remove('active');
    }

    static showToast(message, type = 'success') {
        // Simple toast notification
        const toast = document.createElement('div');
        toast.className = `alert alert-${type}`;
        toast.style.position = 'fixed';
        toast.style.top = '20px';
        toast.style.right = '20px';
        toast.style.zIndex = '10000';
        toast.textContent = message;

        document.body.appendChild(toast);

        setTimeout(() => {
            toast.remove();
        }, 3000);
    }
}

// Initialize app when DOM is ready
document.addEventListener('DOMContentLoaded', () => {
    if (Auth.requireAuth()) {
        window.app = new App();
    }
});
