// WiFight ISP - Sessions Page

class SessionsPage {
    static render(sessions) {
        return `
            <div class="card">
                <div class="card-header">
                    <h2 class="card-title">Active Sessions</h2>
                    <span class="badge badge-success">${sessions.length} Active</span>
                </div>

                <div class="table-container">
                    <table>
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>User</th>
                                <th>MAC Address</th>
                                <th>IP Address</th>
                                <th>Controller</th>
                                <th>Upload/Download</th>
                                <th>Started</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            ${sessions.length > 0 ? sessions.map(session => `
                                <tr>
                                    <td>${session.id}</td>
                                    <td>${session.username || 'N/A'}</td>
                                    <td><code>${session.mac_address}</code></td>
                                    <td>${session.ip_address || 'N/A'}</td>
                                    <td>${session.controller_name || 'N/A'}</td>
                                    <td>
                                        <i class="fas fa-arrow-up"></i> ${this.formatBytes(session.bytes_uploaded || 0)}
                                        <br>
                                        <i class="fas fa-arrow-down"></i> ${this.formatBytes(session.bytes_downloaded || 0)}
                                    </td>
                                    <td>${new Date(session.created_at).toLocaleString()}</td>
                                    <td>
                                        <button class="btn btn-sm btn-danger" onclick="SessionsPage.endSession(${session.id})">
                                            <i class="fas fa-stop"></i> End
                                        </button>
                                    </td>
                                </tr>
                            `).join('') : `
                                <tr>
                                    <td colspan="8" style="text-align: center; padding: 20px;">
                                        No active sessions
                                    </td>
                                </tr>
                            `}
                        </tbody>
                    </table>
                </div>
            </div>
        `;
    }

    static formatBytes(bytes) {
        if (bytes === 0) return '0 B';
        const k = 1024;
        const sizes = ['B', 'KB', 'MB', 'GB', 'TB'];
        const i = Math.floor(Math.log(bytes) / Math.log(k));
        return Math.round((bytes / Math.pow(k, i)) * 100) / 100 + ' ' + sizes[i];
    }

    static async endSession(id) {
        if (!confirm('Are you sure you want to end this session?')) return;

        try {
            App.showLoading();
            const response = await API.endSession(id);

            if (response.success) {
                App.showToast('Session ended successfully');
                app.loadPage('sessions');
            }
        } catch (error) {
            App.showToast('Failed to end session', 'error');
        } finally {
            App.hideLoading();
        }
    }
}
