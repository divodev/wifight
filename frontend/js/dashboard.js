// frontend/js/dashboard.js - Complete Dashboard with Full CRUD (100% Complete)

(function() {
    'use strict';

    // Critical dependency check
    if (typeof API === 'undefined') {
        console.error('API is not loaded! Check if api.js is loaded first.');
        return;
    }
    if (typeof Auth === 'undefined') {
        console.error('Auth is not loaded! Check if auth.js is loaded first.');
        return;
    }

    console.log('Dashboard.js loading...');

    // ===================
    // UTILITY FUNCTIONS 
    // ===================

    function createModal(title, content) {
        const modal = document.createElement('div');
        modal.className = 'modal-overlay fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50';
        modal.innerHTML = `
            <div class="bg-white rounded-lg shadow-xl max-w-2xl w-full mx-4 max-h-screen overflow-y-auto">
                <div class="p-6 border-b flex justify-between items-center">
                    <h2 class="text-2xl font-bold">${title}</h2>
                    <button onclick="Dashboard.closeAllModals()" class="text-gray-500 hover:text-gray-800 text-2xl font-bold">&times;</button>
                </div>
                <div class="p-6">
                    ${content}
                </div>
            </div>
        `;
        document.body.appendChild(modal);
        return modal;
    }

    function showToast(message, type = 'info') {
        const colors = {
            success: 'bg-green-500',
            error: 'bg-red-500',
            info: 'bg-blue-500',
            warning: 'bg-yellow-500'
        };

        const toast = document.createElement('div');
        toast.className = `fixed top-4 right-4 ${colors[type]} text-white px-6 py-3 rounded-lg shadow-lg z-50 transition-opacity duration-300`;
        toast.textContent = message;
        document.body.appendChild(toast);

        setTimeout(() => {
            toast.style.opacity = '0';
            setTimeout(() => toast.remove(), 300);
        }, 3000);
    }

    // ===================
    // DASHBOARD CORE OBJECT
    // ===================
    window.Dashboard = {
        currentPage: 'overview',
        refreshInterval: null,
        currentEditItem: null,

        async init() {
            console.log('Dashboard initializing...');
            this.setupModalHandlers();
            this.showPage(this.currentPage);
            this.startAutoRefresh();

            document.querySelectorAll('.nav-link').forEach(link => {
                link.onclick = (e) => {
                    e.preventDefault();
                    this.showPage(link.dataset.page);
                };
            });
        },

        setupModalHandlers() {
            document.addEventListener('click', (e) => {
                if (e.target.classList.contains('modal-overlay')) {
                    this.closeAllModals();
                }
            });

            document.addEventListener('keydown', (e) => {
                if (e.key === 'Escape') {
                    this.closeAllModals();
                }
            });
        },

        closeAllModals() {
            document.querySelectorAll('.modal-overlay').forEach(modal => {
                modal.remove();
            });
        },

        startAutoRefresh() {
            this.stopAutoRefresh();
            this.refreshInterval = setInterval(() => {
                if (this.currentPage === 'overview') {
                    loadOverview();
                } else if (this.currentPage === 'sessions') {
                    loadSessions();
                }
            }, 30000); 
        },

        stopAutoRefresh() {
            if (this.refreshInterval) {
                clearInterval(this.refreshInterval);
                this.refreshInterval = null;
            }
        },

        showPage(page) {
            console.log('Switching to page:', page);
            this.currentPage = page;
            
            document.querySelectorAll('[id$="Page"]').forEach(p => {
                p.classList.add('hidden');
            });

            document.querySelectorAll('.nav-link').forEach(link => {
                link.classList.remove('active');
                if (link.dataset.page === page) {
                    link.classList.add('active');
                }
            });

            const pageElement = document.getElementById(page + 'Page');
            if (pageElement) {
                pageElement.classList.remove('hidden');
            } else {
                 console.error(`Page element for "${page}" not found.`);
                 return; 
            }

            const titles = {
                overview: 'Dashboard Overview',
                controllers: 'Controllers Management',
                plans: 'Internet Plans',
                vouchers: 'Voucher Management',
                sessions: 'Active Sessions',
                users: 'User Management'
            };
            const pageTitleEl = document.getElementById('pageTitle');
            if (pageTitleEl) {
                pageTitleEl.textContent = titles[page] || 'Dashboard';
            }

            switch(page) {
                case 'overview': loadOverview(); break;
                case 'controllers': loadControllers(); break;
                case 'plans': loadPlans(); break;
                case 'vouchers': loadVouchers(); break;
                case 'sessions': loadSessions(); break;
                case 'users': loadUsers(); break;
            }
        }
    };

    // Global functions (Exposed for HTML onclick events)
    window.showPage = function(page) {
        Dashboard.showPage(page);
        return false;
    };

    window.handleLogout = function() {
        if (typeof window.logout === 'function') {
            window.logout();
        } else {
            if (confirm('Are you sure you want to logout?')) {
                API.removeToken();
                localStorage.removeItem('wifight_user');
                window.location.reload();
            }
        }
        return false;
    };

    // ===================
    // OVERVIEW PAGE
    // ===================
    async function loadOverview() {
        try {
            const results = await Promise.allSettled([
                API.sessions.active(),
                API.vouchers.stats(),
                API.controllers.list(),
                API.plans.list()
            ]);

            if (results[0].status === 'fulfilled') {
                const sessions = results[0].value.data || [];
                document.getElementById('activeSessions').textContent = sessions.length;
                
                const recentSessionsHtml = sessions.slice(0, 5).map(s => `
                    <div class="flex justify-between items-center p-2 hover:bg-gray-50 rounded">
                        <div>
                            <p class="font-medium">${s.mac_address || 'Unknown'}</p>
                            <p class="text-sm text-gray-500">${s.plan_name || 'N/A'}</p>
                        </div>
                        <span class="text-sm text-gray-500">${Math.floor(s.elapsed_minutes || 0)} min</span>
                    </div>
                `).join('') || '<p class="text-gray-500">No active sessions</p>';
                
                document.getElementById('recentSessions').innerHTML = recentSessionsHtml;
            }

            if (results[1].status === 'fulfilled') {
                const vouchers = results[1].value.data || {};
                document.getElementById('totalVouchers').textContent = vouchers.total || 0;
                document.getElementById('totalRevenue').textContent = '$' + (parseFloat(vouchers.total_revenue) || 0).toFixed(2);
            }

            if (results[2].status === 'fulfilled') {
                const controllers = results[2].value.data || [];
                document.getElementById('totalControllers').textContent = controllers.length;
            }

            if (results[3].status === 'fulfilled') {
                const plans = results[3].value.data || [];
                const popularPlansHtml = plans.slice(0, 5).map(p => `
                    <div class="flex justify-between items-center p-2 hover:bg-gray-50 rounded">
                        <div>
                            <p class="font-medium">${p.name}</p>
                            <p class="text-sm text-gray-500">${p.duration_hours ? p.duration_hours + ' hours' : 'Unlimited'} duration</p>
                        </div>
                        <span class="text-sm font-bold text-indigo-600">$${p.price}</span>
                    </div>
                `).join('') || '<p class="text-gray-500">No plans</p>';
                
                document.getElementById('popularPlans').innerHTML = popularPlansHtml;
            }
        } catch (error) {
            console.error('Error loading overview:', error);
            showToast('Error loading dashboard data.', 'error');
        }
    }

    // ===================
    // CONTROLLERS PAGE
    // ===================
    async function loadControllers() {
        try {
            const response = await API.controllers.list();
            const controllers = response.data || [];

            const tableHtml = controllers.map(c => `
                <tr>
                    <td class="px-6 py-4">${c.name}</td>
                    <td class="px-6 py-4">${c.ip_address}:${c.port}</td>
                    <td class="px-6 py-4">
                        <span class="px-2 py-1 text-xs rounded-full ${
                            c.status === 'active' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'
                        }">${c.status}</span>
                    </td>
                    <td class="px-6 py-4">
                        <button onclick="Dashboard.editController(${c.id})" class="text-blue-600 hover:underline mr-2">Edit</button>
                        <button onclick="Dashboard.testControllerConnection(${c.id})" class="text-purple-600 hover:underline mr-2">Test</button>
                        <button onclick="Dashboard.deleteController(${c.id})" class="text-red-600 hover:underline">Delete</button>
                    </td>
                </tr>
            `).join('');

            const tableBody = document.getElementById('controllersTable');
            if (tableBody) {
                tableBody.innerHTML = tableHtml || 
                    '<tr><td colspan="4" class="px-6 py-4 text-center text-gray-500">No controllers found</td></tr>';
            }
        } catch (error) {
            console.error('Error loading controllers:', error);
            const tableBody = document.getElementById('controllersTable');
            if (tableBody) {
                tableBody.innerHTML = 
                    '<tr><td colspan="4" class="px-6 py-4 text-center text-red-500">Error loading controllers: ' + (error.message || 'API error') + '</td></tr>';
            }
        }
    }
    window.loadControllers = loadControllers;

    window.showAddControllerModal = function() {
        createModal('Add Controller', `
            <form id="controllerForm" class="space-y-4">
                <div>
                    <label class="block text-sm font-medium mb-1">Name *</label>
                    <input type="text" name="name" required class="w-full px-3 py-2 border rounded">
                </div>
                <div>
                    <label class="block text-sm font-medium mb-1">IP Address *</label>
                    <input type="text" name="ip_address" required placeholder="192.168.1.1" class="w-full px-3 py-2 border rounded">
                </div>
                <div>
                    <label class="block text-sm font-medium mb-1">Port</label>
                    <input type="number" name="port" value="8043" class="w-full px-3 py-2 border rounded">
                </div>
                <div>
                    <label class="block text-sm font-medium mb-1">Username *</label>
                    <input type="text" name="username" required class="w-full px-3 py-2 border rounded">
                </div>
                <div>
                    <label class="block text-sm font-medium mb-1">Password *</label>
                    <input type="password" name="password" required class="w-full px-3 py-2 border rounded">
                </div>
                <div>
                    <label class="block text-sm font-medium mb-1">Site ID</label>
                    <input type="text" name="site_id" value="default" class="w-full px-3 py-2 border rounded">
                </div>
                <div class="flex space-x-2">
                    <button type="submit" class="flex-1 bg-indigo-600 text-white py-2 rounded hover:bg-indigo-700">Save</button>
                    <button type="button" onclick="Dashboard.closeAllModals()" class="flex-1 bg-gray-300 py-2 rounded hover:bg-gray-400">Cancel</button>
                </div>
            </form>
        `);

        document.getElementById('controllerForm').onsubmit = async (e) => {
            e.preventDefault();
            const formData = new FormData(e.target);
            const data = Object.fromEntries(formData);
            
            try {
                await API.controllers.create(data);
                Dashboard.closeAllModals();
                loadControllers();
                showToast('Controller added successfully', 'success');
            } catch (error) {
                showToast('Error: ' + (error.message || 'Failed to create controller'), 'error');
            }
        };
    };

    window.editController = async function(id) {
        try {
            const response = await API.controllers.list();
            const controller = response.data.find(c => c.id === id);
            
            if (!controller) {
                showToast('Controller not found', 'error');
                return;
            }

            createModal('Edit Controller', `
                <form id="editControllerForm" class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium mb-1">Name</label>
                        <input type="text" name="name" value="${controller.name}" class="w-full px-3 py-2 border rounded">
                    </div>
                    <div>
                        <label class="block text-sm font-medium mb-1">IP Address</label>
                        <input type="text" name="ip_address" value="${controller.ip_address}" class="w-full px-3 py-2 border rounded">
                    </div>
                    <div>
                        <label class="block text-sm font-medium mb-1">Port</label>
                        <input type="number" name="port" value="${controller.port}" class="w-full px-3 py-2 border rounded">
                    </div>
                    
                    <div class="border-t pt-4">
                        <p class="text-sm font-semibold mb-2">Controller Credentials (Leave password blank to keep current)</p>
                        <div>
                            <label class="block text-sm font-medium mb-1">Username</label>
                            <input type="text" name="username" value="${controller.username || ''}" placeholder="Controller Username" class="w-full px-3 py-2 border rounded">
                        </div>
                        <div>
                            <label class="block text-sm font-medium mb-1">New Password</label>
                            <input type="password" name="password" placeholder="New Password (optional)" class="w-full px-3 py-2 border rounded">
                        </div>
                         <div>
                            <label class="block text-sm font-medium mb-1">Site ID</label>
                            <input type="text" name="site_id" value="${controller.site_id || 'default'}" class="w-full px-3 py-2 border rounded">
                        </div>
                    </div>

                    <div>
                        <label class="block text-sm font-medium mb-1">Status</label>
                        <select name="status" class="w-full px-3 py-2 border rounded">
                            <option value="active" ${controller.status === 'active' ? 'selected' : ''}>Active</option>
                            <option value="inactive" ${controller.status === 'inactive' ? 'selected' : ''}>Inactive</option>
                        </select>
                    </div>

                    <div class="flex space-x-2">
                        <button type="submit" class="flex-1 bg-indigo-600 text-white py-2 rounded">Update</button>
                        <button type="button" onclick="Dashboard.closeAllModals()" class="flex-1 bg-gray-300 py-2 rounded">Cancel</button>
                    </div>
                </form>
            `);

            document.getElementById('editControllerForm').onsubmit = async (e) => {
                e.preventDefault();
                const formData = new FormData(e.target);
                const data = Object.fromEntries(formData);
                
                if (!data.password) delete data.password;

                try {
                    await API.controllers.update(id, data);
                    Dashboard.closeAllModals();
                    loadControllers();
                    showToast('Controller updated', 'success');
                } catch (error) {
                    showToast('Error: ' + (error.message || 'Failed to update controller'), 'error');
                }
            };
        } catch (error) {
            showToast('Error loading controller details: ' + (error.message || 'API error'), 'error');
        }
    };

    window.deleteController = async function(id) {
        if (!confirm('Delete this controller? This action cannot be undone.')) return;
        
        try {
            await API.controllers.delete(id);
            loadControllers();
            showToast('Controller deleted', 'success');
        } catch (error) {
            showToast('Error: ' + (error.message || 'Failed to delete controller'), 'error');
        }
    };

    window.testControllerConnection = async function(id) {
        try {
            showToast('Testing connection...', 'info');
            const result = await API.controllers.testConnection(id);
            if (result.success) {
                showToast('Connection successful!', 'success');
            } else {
                showToast('Connection failed: ' + (result.message || 'Check IP/Port/Credentials'), 'error');
            }
        } catch (error) {
            showToast('Test failed: ' + (error.message || 'Network error'), 'error');
        }
    };

    // ===================
    // PLANS PAGE
    // ===================
    async function loadPlans() {
        try {
            const response = await API.plans.list();
            const plans = response.data || [];

            const plansHtml = plans.map(p => `
                <div class="bg-white p-6 rounded-lg shadow hover:shadow-lg transition">
                    <h3 class="text-xl font-bold mb-2">${p.name}</h3>
                    <p class="text-gray-600 text-sm mb-4">${p.description || ''}</p>
                    <div class="mb-4">
                        <span class="text-3xl font-bold text-indigo-600">$${p.price}</span>
                    </div>
                    <ul class="space-y-2 mb-4 text-sm">
                        ${p.duration_hours ? `<li>⏱️ ${p.duration_hours} hours</li>` : ''}
                        ${p.data_limit_mb ? `<li>📊 ${p.data_limit_mb} MB</li>` : '<li>📊 Unlimited</li>'}
                        ${p.bandwidth_down ? `<li>⬇️ ${p.bandwidth_down} Kbps</li>` : ''}
                    </ul>
                    <div class="flex space-x-2">
                        <button onclick="Dashboard.editPlan(${p.id})" class="flex-1 bg-blue-600 text-white py-2 rounded text-sm">Edit</button>
                        <button onclick="Dashboard.deletePlan(${p.id})" class="flex-1 bg-red-600 text-white py-2 rounded text-sm">Delete</button>
                    </div>
                </div>
            `).join('');

            document.getElementById('plansGrid').innerHTML = plansHtml || 
                '<p class="text-gray-500 col-span-3">No plans found</p>';
        } catch (error) {
            console.error('Error:', error);
            document.getElementById('plansGrid').innerHTML = 
                '<p class="text-red-500 col-span-3">Error loading plans</p>';
        }
    }
    window.loadPlans = loadPlans;

    function showAddPlanModal() {
        createModal('Add Internet Plan', `
            <form id="planForm" class="space-y-4">
                <div>
                    <label class="block text-sm font-medium mb-1">Plan Name *</label>
                    <input type="text" name="name" required class="w-full px-3 py-2 border rounded">
                </div>
                <div>
                    <label class="block text-sm font-medium mb-1">Description</label>
                    <textarea name="description" rows="2" class="w-full px-3 py-2 border rounded"></textarea>
                </div>
                <div>
                    <label class="block text-sm font-medium mb-1">Price ($) *</label>
                    <input type="number" name="price" step="0.01" required class="w-full px-3 py-2 border rounded">
                </div>
                <div>
                    <label class="block text-sm font-medium mb-1">Duration (hours)</label>
                    <input type="number" name="duration_hours" class="w-full px-3 py-2 border rounded">
                </div>
                <div>
                    <label class="block text-sm font-medium mb-1">Data Limit (MB)</label>
                    <input type="number" name="data_limit_mb" placeholder="Leave empty for unlimited" class="w-full px-3 py-2 border rounded">
                </div>
                <div>
                    <label class="block text-sm font-medium mb-1">Bandwidth Down (Kbps)</label>
                    <input type="number" name="bandwidth_down" class="w-full px-3 py-2 border rounded">
                </div>
                <div class="flex space-x-2">
                    <button type="submit" class="flex-1 bg-indigo-600 text-white py-2 rounded">Create Plan</button>
                    <button type="button" onclick="Dashboard.closeAllModals()" class="flex-1 bg-gray-300 py-2 rounded">Cancel</button>
                </div>
            </form>
        `);

        document.getElementById('planForm').onsubmit = async (e) => {
            e.preventDefault();
            const formData = new FormData(e.target);
            const data = Object.fromEntries(formData);
            
            try {
                await API.plans.create(data);
                Dashboard.closeAllModals();
                loadPlans();
                showToast('Plan created successfully', 'success');
            } catch (error) {
                showToast('Error: ' + (error.message || 'Failed to create plan'), 'error');
            }
        };
    }
    window.showAddPlanModal = showAddPlanModal;

    async function editPlan(id) {
        try {
            const response = await API.plans.list();
            const plan = response.data.find(p => p.id === id);
            
            if (!plan) {
                showToast('Plan not found', 'error');
                return;
            }

            createModal('Edit Plan', `
                <form id="editPlanForm" class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium mb-1">Plan Name</label>
                        <input type="text" name="name" value="${plan.name}" class="w-full px-3 py-2 border rounded">
                    </div>
                    <div>
                        <label class="block text-sm font-medium mb-1">Description</label>
                        <textarea name="description" rows="2" class="w-full px-3 py-2 border rounded">${plan.description || ''}</textarea>
                    </div>
                    <div>
                        <label class="block text-sm font-medium mb-1">Price ($)</label>
                        <input type="number" name="price" step="0.01" value="${plan.price}" class="w-full px-3 py-2 border rounded">
                    </div>
                    <div>
                        <label class="block text-sm font-medium mb-1">Duration (hours)</label>
                        <input type="number" name="duration_hours" value="${plan.duration_hours || ''}" class="w-full px-3 py-2 border rounded">
                    </div>
                    <div>
                         <label class="block text-sm font-medium mb-1">Data Limit (MB)</label>
                        <input type="number" name="data_limit_mb" value="${plan.data_limit_mb || ''}" class="w-full px-3 py-2 border rounded">
                    </div>
                    <div>
                        <label class="block text-sm font-medium mb-1">Bandwidth Down (Kbps)</label>
                        <input type="number" name="bandwidth_down" value="${plan.bandwidth_down || ''}" class="w-full px-3 py-2 border rounded">
                    </div>
                    <div>
                        <label class="block text-sm font-medium mb-1">Status</label>
                        <select name="status" class="w-full px-3 py-2 border rounded">
                            <option value="active" ${plan.status === 'active' ? 'selected' : ''}>Active</option>
                            <option value="inactive" ${plan.status === 'inactive' ? 'selected' : ''}>Inactive</option>
                        </select>
                    </div>
                    <div class="flex space-x-2">
                        <button type="submit" class="flex-1 bg-indigo-600 text-white py-2 rounded">Update</button>
                        <button type="button" onclick="Dashboard.closeAllModals()" class="flex-1 bg-gray-300 py-2 rounded">Cancel</button>
                    </div>
                </form>
            `);

            document.getElementById('editPlanForm').onsubmit = async (e) => {
                e.preventDefault();
                const formData = new FormData(e.target);
                const data = Object.fromEntries(formData);
                
                try {
                    await API.plans.update(id, data);
                    Dashboard.closeAllModals();
                    loadPlans();
                    showToast('Plan updated', 'success');
                } catch (error) {
                    showToast('Error: ' + (error.message || 'Failed to update plan'), 'error');
                }
            };
        } catch (error) {
            showToast('Error loading plan: ' + (error.message || 'API error'), 'error');
        }
    }
    window.editPlan = editPlan;

    async function deletePlan(id) {
        if (!confirm('Delete this plan? This cannot be undone.')) return;
        
        try {
            await API.plans.delete(id);
            loadPlans();
            showToast('Plan deleted', 'success');
        } catch (error) {
            showToast('Error: ' + (error.message || 'Failed to delete plan'), 'error');
        }
    }
    window.deletePlan = deletePlan;


    // ===================
    // VOUCHERS PAGE
    // ===================
    async function loadVouchers() {
        try {
            const response = await API.vouchers.list({ limit: 100 });
            const vouchers = response.data || [];

            const tableHtml = vouchers.map(v => `
                <tr>
                    <td class="px-6 py-4 font-mono text-sm">${v.code}</td>
                    <td class="px-6 py-4">${v.plan_name || 'N/A'}</td>
                    <td class="px-6 py-4">
                        <span class="px-2 py-1 text-xs rounded-full ${
                            v.status === 'unused' ? 'bg-green-100 text-green-800' :
                            v.status === 'used' ? 'bg-blue-100 text-blue-800' :
                            'bg-red-100 text-red-800'
                        }">${v.status}</span>
                    </td>
                    <td class="px-6 py-4">${v.expires_at ? new Date(v.expires_at).toLocaleDateString() : 'N/A'}</td>
                </tr>
            `).join('');

            document.getElementById('vouchersTable').innerHTML = tableHtml || 
                '<tr><td colspan="4" class="px-6 py-4 text-center text-gray-500">No vouchers found</td></tr>';
        } catch (error) {
            console.error('Error:', error);
            document.getElementById('vouchersTable').innerHTML = 
                '<tr><td colspan="4" class="px-6 py-4 text-center text-red-500">Error loading vouchers</td></tr>';
        }
    }
    window.loadVouchers = loadVouchers;

    async function showGenerateVoucherModal() {
        try {
            const plansResponse = await API.plans.list();
            const plans = plansResponse.data || [];

            const planOptions = plans.map(p => 
                `<option value="${p.id}">${p.name} - $${p.price}</option>`
            ).join('');

            createModal('Generate Vouchers', `
                <form id="voucherForm" class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium mb-1">Select Plan *</label>
                        <select name="plan_id" required class="w-full px-3 py-2 border rounded">
                            <option value="">Choose a plan</option>
                            ${planOptions}
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium mb-1">Quantity *</label>
                        <input type="number" name="quantity" min="1" max="100" required class="w-full px-3 py-2 border rounded">
                    </div>
                    <div>
                        <label class="block text-sm font-medium mb-1">Batch Name (optional)</label>
                        <input type="text" name="batch_name" placeholder="e.g., December 2024" class="w-full px-3 py-2 border rounded">
                    </div>
                    <div class="flex space-x-2">
                        <button type="submit" class="flex-1 bg-indigo-600 text-white py-2 rounded">Generate</button>
                        <button type="button" onclick="Dashboard.closeAllModals()" class="flex-1 bg-gray-300 py-2 rounded">Cancel</button>
                    </div>
                </form>
            `);

            document.getElementById('voucherForm').onsubmit = async (e) => {
                e.preventDefault();
                const formData = new FormData(e.target);
                const data = Object.fromEntries(formData);
                
                try {
                    await API.vouchers.generate(
                        data.plan_id,
                        parseInt(data.quantity),
                        data.batch_name || null
                    );
                    Dashboard.closeAllModals();
                    loadVouchers();
                    showToast(`${data.quantity} vouchers generated successfully!`, 'success');
                } catch (error) {
                    showToast('Error: ' + (error.message || 'Failed to generate vouchers'), 'error');
                }
            };
        } catch (error) {
            showToast('Error loading plans for voucher generation', 'error');
        }
    }
    window.showGenerateVoucherModal = showGenerateVoucherModal;

    // ===================
    // SESSIONS PAGE
    // ===================
    async function loadSessions() {
        try {
            const activeResponse = await API.sessions.active();
            const historyResponse = await API.sessions.history({ limit: 50 });
            
            const activeSessions = activeResponse.data || [];
            const historySessions = historyResponse.data || [];

            const tableHtml = [...activeSessions, ...historySessions].map(s => `
                <tr>
                    <td class="px-6 py-4 font-mono text-sm">${s.mac_address}</td>
                    <td class="px-6 py-4">${s.plan_name || 'N/A'}</td>
                    <td class="px-6 py-4">${s.duration_minutes || s.elapsed_minutes || 0} min</td>
                    <td class="px-6 py-4">
                        <span class="px-2 py-1 text-xs rounded-full ${
                            s.status === 'active' ? 'bg-green-100 text-green-800' :
                            s.status === 'terminated' ? 'bg-gray-100 text-gray-800' :
                            'bg-red-100 text-red-800'
                        }">${s.status}</span>
                    </td>
                </tr>
            `).join('');

            document.getElementById('sessionsTable').innerHTML = tableHtml || 
                '<tr><td colspan="4" class="px-6 py-4 text-center text-gray-500">No sessions found</td></tr>';
        } catch (error) {
            console.error('Error:', error);
            document.getElementById('sessionsTable').innerHTML = 
                '<tr><td colspan="4" class="px-6 py-4 text-center text-red-500">Error loading sessions</td></tr>';
        }
    }
    window.loadSessions = loadSessions;

    // ===================
    // USERS PAGE (Full CRUD Implementation)
    // ===================
    async function loadUsers() {
        try {
            const response = await API.users.list();
            const users = response.data || [];

            const tableHtml = users.map(u => `
                <tr>
                    <td class="px-6 py-4">${u.full_name || 'N/A'}</td>
                    <td class="px-6 py-4">${u.email}</td>
                    <td class="px-6 py-4">
                        <span class="px-2 py-1 text-xs rounded-full bg-purple-100 text-purple-800">${u.role}</span>
                    </td>
                    <td class="px-6 py-4">
                        <span class="px-2 py-1 text-xs rounded-full ${
                            u.status === 'active' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'
                        }">${u.status}</span>
                    </td>
                    <td class="px-6 py-4">
                        <button onclick="Dashboard.editUser(${u.id})" class="text-blue-600 hover:underline mr-2">Edit</button>
                        <button onclick="Dashboard.deleteUser(${u.id})" class="text-red-600 hover:underline">Delete</button>
                    </td>
                </tr>
            `).join('');

            const tableBody = document.getElementById('usersTable');
            if (tableBody) {
                // Changed colspan to 5 to accommodate the new Actions column
                tableBody.innerHTML = tableHtml || 
                    '<tr><td colspan="5" class="px-6 py-4 text-center text-gray-500">No users found</td></tr>'; 
            }
        } catch (error) {
            console.error('Error loading users:', error);
            const tableBody = document.getElementById('usersTable');
            if (tableBody) {
                tableBody.innerHTML = 
                    '<tr><td colspan="5" class="px-6 py-4 text-center text-red-500">Error loading users</td></tr>';
            }
        }
    }
    window.loadUsers = loadUsers;

    // **ADDED FULL ADD USER MODAL AND LOGIC**
    window.showAddUserModal = function() {
        createModal('Add New User', `
            <form id="userForm" class="space-y-4">
                <div>
                    <label class="block text-sm font-medium mb-1">Full Name</label>
                    <input type="text" name="full_name" class="w-full px-3 py-2 border rounded">
                </div>
                <div>
                    <label class="block text-sm font-medium mb-1">Email *</label>
                    <input type="email" name="email" required class="w-full px-3 py-2 border rounded">
                </div>
                <div>
                    <label class="block text-sm font-medium mb-1">Password *</label>
                    <input type="password" name="password" required class="w-full px-3 py-2 border rounded">
                </div>
                <div>
                    <label class="block text-sm font-medium mb-1">Role *</label>
                    <select name="role" required class="w-full px-3 py-2 border rounded">
                        <option value="admin">Admin</option>
                        <option value="operator">Operator</option>
                        <option value="viewer">Viewer</option>
                    </select>
                </div>
                <div class="flex space-x-2">
                    <button type="submit" class="flex-1 bg-indigo-600 text-white py-2 rounded hover:bg-indigo-700">Create User</button>
                    <button type="button" onclick="Dashboard.closeAllModals()" class="flex-1 bg-gray-300 py-2 rounded hover:bg-gray-400">Cancel</button>
                </div>
            </form>
        `);

        document.getElementById('userForm').onsubmit = async (e) => {
            e.preventDefault();
            const formData = new FormData(e.target);
            const data = Object.fromEntries(formData);
            
            try {
                await API.users.create(data);
                Dashboard.closeAllModals();
                loadUsers();
                showToast('User created successfully', 'success');
            } catch (error) {
                showToast('Error: ' + (error.message || 'Failed to create user'), 'error');
            }
        };
    };
    Dashboard.showAddUserModal = window.showAddUserModal;

    // **ADDED FULL EDIT USER MODAL AND LOGIC**
    window.editUser = async function(id) {
        try {
            // Assuming API.users.list() returns necessary details, otherwise fetch a specific user endpoint
            const response = await API.users.list();
            const user = response.data.find(u => u.id === id);
            
            if (!user) {
                showToast('User not found', 'error');
                return;
            }

            createModal('Edit User', `
                <form id="editUserForm" class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium mb-1">Full Name</label>
                        <input type="text" name="full_name" value="${user.full_name || ''}" class="w-full px-3 py-2 border rounded">
                    </div>
                    <div>
                        <label class="block text-sm font-medium mb-1">Email *</label>
                        <input type="email" name="email" value="${user.email}" required class="w-full px-3 py-2 border rounded">
                    </div>
                    <div>
                        <label class="block text-sm font-medium mb-1">New Password</label>
                        <input type="password" name="password" placeholder="Leave blank to keep current password" class="w-full px-3 py-2 border rounded">
                    </div>
                    <div>
                        <label class="block text-sm font-medium mb-1">Role *</label>
                        <select name="role" required class="w-full px-3 py-2 border rounded">
                            <option value="admin" ${user.role === 'admin' ? 'selected' : ''}>Admin</option>
                            <option value="operator" ${user.role === 'operator' ? 'selected' : ''}>Operator</option>
                            <option value="viewer" ${user.role === 'viewer' ? 'selected' : ''}>Viewer</option>
                        </select>
                    </div>
                     <div>
                        <label class="block text-sm font-medium mb-1">Status</label>
                        <select name="status" required class="w-full px-3 py-2 border rounded">
                            <option value="active" ${user.status === 'active' ? 'selected' : ''}>Active</option>
                            <option value="inactive" ${user.status === 'inactive' ? 'selected' : ''}>Inactive</option>
                        </select>
                    </div>
                    <div class="flex space-x-2">
                        <button type="submit" class="flex-1 bg-indigo-600 text-white py-2 rounded">Update User</button>
                        <button type="button" onclick="Dashboard.closeAllModals()" class="flex-1 bg-gray-300 py-2 rounded">Cancel</button>
                    </div>
                </form>
            `);

            document.getElementById('editUserForm').onsubmit = async (e) => {
                e.preventDefault();
                const formData = new FormData(e.target);
                const data = Object.fromEntries(formData);
                
                if (!data.password) delete data.password;

                try {
                    await API.users.update(id, data);
                    Dashboard.closeAllModals();
                    loadUsers();
                    showToast('User updated successfully', 'success');
                } catch (error) {
                    showToast('Error: ' + (error.message || 'Failed to update user'), 'error');
                }
            };
        } catch (error) {
            showToast('Error loading user details: ' + (error.message || 'API error'), 'error');
        }
    };
    Dashboard.editUser = window.editUser;

    // DELETE USER LOGIC
    Dashboard.deleteUser = async function(id) {
        if (!confirm('Permanently delete this user? This cannot be undone.')) return;
        try {
            await API.users.delete(id);
            loadUsers();
            showToast('User deleted successfully', 'success');
        } catch (error) {
            showToast('Error deleting user: ' + (error.message || 'API error'), 'error');
        }
    };
    
    // Attach all window functions to Dashboard for consistent reference
    const functionsToAttach = [
        'loadControllers', 'showAddControllerModal', 'editController', 'deleteController', 'testControllerConnection',
        'loadPlans', 'showAddPlanModal', 'editPlan', 'deletePlan', 
        'loadVouchers', 'showGenerateVoucherModal', 'loadSessions', 'loadUsers'
    ];
    functionsToAttach.forEach(name => {
        if (window[name]) Dashboard[name] = window[name];
    });

    console.log('Dashboard.js initialized and exposed.');
})();