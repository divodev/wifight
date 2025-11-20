// WiFight ISP - Analytics Page

class AnalyticsPage {
    static render(data) {
        return `
            <h1 class="page-title">Analytics & Reports</h1>

            <!-- Revenue Trend -->
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Revenue Trend (Last 30 Days)</h3>
                </div>
                <div class="table-container">
                    <table>
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Transactions</th>
                                <th>Revenue</th>
                            </tr>
                        </thead>
                        <tbody>
                            ${data.revenue.map(item => `
                                <tr>
                                    <td>${item.date}</td>
                                    <td>${item.transaction_count}</td>
                                    <td>$${parseFloat(item.revenue).toFixed(2)}</td>
                                </tr>
                            `).join('')}
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- User Growth -->
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">User Growth (Last 30 Days)</h3>
                </div>
                <div class="table-container">
                    <table>
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>New Users</th>
                                <th>By Role</th>
                            </tr>
                        </thead>
                        <tbody>
                            ${data.users.map(item => `
                                <tr>
                                    <td>${item.date}</td>
                                    <td>${item.total}</td>
                                    <td>
                                        ${Object.entries(item.by_role)
                                            .map(([role, count]) => `<span class="badge badge-primary">${role}: ${count}</span>`)
                                            .join(' ')}
                                    </td>
                                </tr>
                            `).join('')}
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Session Statistics -->
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Session Statistics (Last 7 Days)</h3>
                </div>
                <div class="table-container">
                    <table>
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Sessions</th>
                                <th>Avg Duration (min)</th>
                                <th>Bandwidth (GB)</th>
                            </tr>
                        </thead>
                        <tbody>
                            ${data.sessions.map(item => `
                                <tr>
                                    <td>${item.date}</td>
                                    <td>${item.session_count}</td>
                                    <td>${parseFloat(item.avg_duration_minutes || 0).toFixed(2)}</td>
                                    <td>${parseFloat(item.total_bandwidth_gb || 0).toFixed(2)}</td>
                                </tr>
                            `).join('')}
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Report Generation -->
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Generate Report</h3>
                </div>
                <p style="padding: 20px;">
                    Report generation functionality available via API.
                    Use the Analytics API endpoints to generate custom reports in JSON, CSV, or HTML format.
                </p>
            </div>
        `;
    }
}
