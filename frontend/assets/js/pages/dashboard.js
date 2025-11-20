// WiFight ISP - Dashboard Page

class DashboardPage {
    static render(data) {
        return `
            <h1 class="page-title">Dashboard</h1>

            <!-- Stats Grid -->
            <div class="stats-grid">
                <!-- Revenue -->
                <div class="stat-card">
                    <div class="stat-icon primary">
                        <i class="fas fa-dollar-sign"></i>
                    </div>
                    <div class="stat-content">
                        <div class="stat-label">Today's Revenue</div>
                        <div class="stat-value">$${data.revenue.today.toFixed(2)}</div>
                        <div class="stat-change ${data.revenue.change_percent >= 0 ? 'positive' : 'negative'}">
                            <i class="fas fa-arrow-${data.revenue.change_percent >= 0 ? 'up' : 'down'}"></i>
                            ${Math.abs(data.revenue.change_percent)}% vs yesterday
                        </div>
                    </div>
                </div>

                <!-- Active Users -->
                <div class="stat-card">
                    <div class="stat-icon secondary">
                        <i class="fas fa-users"></i>
                    </div>
                    <div class="stat-content">
                        <div class="stat-label">Active Users</div>
                        <div class="stat-value">${data.users.total_active}</div>
                        <div class="stat-change">
                            <i class="fas fa-user-plus"></i>
                            ${data.users.new_today} new today
                        </div>
                    </div>
                </div>

                <!-- Active Sessions -->
                <div class="stat-card">
                    <div class="stat-icon warning">
                        <i class="fas fa-network-wired"></i>
                    </div>
                    <div class="stat-content">
                        <div class="stat-label">Active Sessions</div>
                        <div class="stat-value">${data.sessions.active}</div>
                        <div class="stat-change">
                            ${data.sessions.today_total} total today
                        </div>
                    </div>
                </div>

                <!-- Subscriptions -->
                <div class="stat-card">
                    <div class="stat-icon danger">
                        <i class="fas fa-sync-alt"></i>
                    </div>
                    <div class="stat-content">
                        <div class="stat-label">Active Subscriptions</div>
                        <div class="stat-value">${data.subscriptions.active}</div>
                        <div class="stat-change ${data.subscriptions.expiring_soon > 0 ? 'negative' : ''}">
                            <i class="fas fa-exclamation-triangle"></i>
                            ${data.subscriptions.expiring_soon} expiring soon
                        </div>
                    </div>
                </div>
            </div>

            <!-- Payments Overview -->
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Today's Payments</h3>
                </div>
                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="stat-content">
                            <div class="stat-label">Successful Payments</div>
                            <div class="stat-value">${data.payments.successful_today}</div>
                        </div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-content">
                            <div class="stat-label">Failed Payments</div>
                            <div class="stat-value">${data.payments.failed_today}</div>
                        </div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-content">
                            <div class="stat-label">Success Rate</div>
                            <div class="stat-value">${data.payments.success_rate}%</div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Monthly Revenue -->
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Monthly Revenue</h3>
                    <span class="badge badge-success">$${data.revenue.month.toFixed(2)}</span>
                </div>
                <div class="chart-container" id="revenue-chart">
                    <!-- Chart would go here with Chart.js -->
                    <p style="text-align: center; padding: 50px; color: #999;">
                        Revenue chart visualization (requires Chart.js)
                    </p>
                </div>
            </div>

            <!-- Quick Actions -->
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Quick Actions</h3>
                </div>
                <div style="display: flex; gap: 10px; flex-wrap: wrap;">
                    <button class="btn btn-primary" onclick="app.loadPage('users')">
                        <i class="fas fa-user-plus"></i> Add User
                    </button>
                    <button class="btn btn-secondary" onclick="app.loadPage('plans')">
                        <i class="fas fa-plus"></i> Create Plan
                    </button>
                    <button class="btn btn-warning" onclick="app.loadPage('sessions')">
                        <i class="fas fa-network-wired"></i> View Sessions
                    </button>
                    <button class="btn btn-outline" onclick="app.loadPage('analytics')">
                        <i class="fas fa-chart-bar"></i> View Analytics
                    </button>
                </div>
            </div>
        `;
    }
}
