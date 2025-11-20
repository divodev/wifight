// WiFight ISP - Plans Page

class PlansPage {
    static render(plans) {
        return `
            <div class="card">
                <div class="card-header">
                    <h2 class="card-title">Internet Plans</h2>
                    <button class="btn btn-primary" onclick="PlansPage.showCreateModal()">
                        <i class="fas fa-plus"></i> Create Plan
                    </button>
                </div>

                <div class="stats-grid">
                    ${plans.map(plan => `
                        <div class="stat-card">
                            <div class="stat-content">
                                <div class="stat-label">${plan.name}</div>
                                <div class="stat-value">$${parseFloat(plan.price).toFixed(2)}</div>
                                <p style="margin: 10px 0; color: #666;">
                                    ${plan.download_speed_mbps}/${plan.upload_speed_mbps} Mbps
                                    ${plan.data_limit_gb ? `• ${plan.data_limit_gb}GB` : '• Unlimited'}
                                    • ${plan.duration_days} days
                                </p>
                                <div style="display: flex; gap: 5px; flex-wrap: wrap;">
                                    <span class="badge badge-${plan.status === 'active' ? 'success' : 'danger'}">${plan.status}</span>
                                    ${plan.is_unlimited ? '<span class="badge badge-primary">Unlimited</span>' : ''}
                                </div>
                                <div style="margin-top: 10px;">
                                    <button class="btn btn-sm btn-secondary" onclick="PlansPage.editPlan(${plan.id})">
                                        <i class="fas fa-edit"></i> Edit
                                    </button>
                                    <button class="btn btn-sm btn-danger" onclick="PlansPage.deletePlan(${plan.id})">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                    `).join('')}
                </div>
            </div>
        `;
    }

    static showCreateModal() {
        alert('Plan creation modal would open here');
    }

    static editPlan(id) {
        alert(`Edit plan ${id} - Modal would open here`);
    }

    static async deletePlan(id) {
        if (!confirm('Are you sure you want to delete this plan?')) return;

        try {
            App.showLoading();
            const response = await API.deletePlan(id);

            if (response.success) {
                App.showToast('Plan deleted successfully');
                app.loadPage('plans');
            }
        } catch (error) {
            App.showToast('Failed to delete plan', 'error');
        } finally {
            App.hideLoading();
        }
    }
}
