// WiFight ISP - Users Page

class UsersPage {
    static render(users) {
        return `
            <div class="card">
                <div class="card-header">
                    <h2 class="card-title">Users Management</h2>
                    <button class="btn btn-primary" onclick="UsersPage.showCreateModal()">
                        <i class="fas fa-user-plus"></i> Add User
                    </button>
                </div>

                <div class="table-container">
                    <table>
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Username</th>
                                <th>Email</th>
                                <th>Role</th>
                                <th>Status</th>
                                <th>Balance</th>
                                <th>Created</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            ${users.map(user => `
                                <tr>
                                    <td>${user.id}</td>
                                    <td>${user.username}</td>
                                    <td>${user.email}</td>
                                    <td><span class="badge badge-primary">${user.role}</span></td>
                                    <td><span class="badge badge-${user.status === 'active' ? 'success' : 'danger'}">${user.status}</span></td>
                                    <td>$${parseFloat(user.balance || 0).toFixed(2)}</td>
                                    <td>${new Date(user.created_at).toLocaleDateString()}</td>
                                    <td>
                                        <button class="btn btn-sm btn-secondary" onclick="UsersPage.viewUser(${user.id})">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                        <button class="btn btn-sm btn-warning" onclick="UsersPage.editUser(${user.id})">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <button class="btn btn-sm btn-danger" onclick="UsersPage.deleteUser(${user.id})">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </td>
                                </tr>
                            `).join('')}
                        </tbody>
                    </table>
                </div>
            </div>
        `;
    }

    static showCreateModal() {
        alert('User creation modal would open here');
    }

    static async viewUser(id) {
        try {
            const response = await API.getUser(id);
            if (response.success) {
                alert(`User Details:\n${JSON.stringify(response.data, null, 2)}`);
            }
        } catch (error) {
            App.showToast('Failed to load user details', 'error');
        }
    }

    static editUser(id) {
        alert(`Edit user ${id} - Modal would open here`);
    }

    static async deleteUser(id) {
        if (!confirm('Are you sure you want to delete this user?')) return;

        try {
            App.showLoading();
            const response = await API.deleteUser(id);

            if (response.success) {
                App.showToast('User deleted successfully');
                app.loadPage('users'); // Reload page
            }
        } catch (error) {
            App.showToast('Failed to delete user', 'error');
        } finally {
            App.hideLoading();
        }
    }
}
