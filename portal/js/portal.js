// Portal JavaScript
const PORTAL_API = window.location.origin + '/wifight/backend/api';

// Tab switching
function showTab(tabName) {
    // Hide all tabs
    document.querySelectorAll('.tab-content').forEach(tab => {
        tab.classList.add('hidden');
    });

    // Remove active class from all buttons
    document.querySelectorAll('.tab-button').forEach(btn => {
        btn.classList.remove('active');
    });

    // Show selected tab
    document.getElementById(tabName + 'Tab').classList.remove('hidden');
    
    // Add active class to clicked button
    event.target.classList.add('active');

    // Load data for specific tabs
    if (tabName === 'purchase') {
        loadPlans();
    }
}

// Load available plans
async function loadPlans() {
    try {
        const response = await fetch(`${PORTAL_API}/plans/list.php`);
        const data = await response.json();

        if (data.success && data.data) {
            displayPlans(data.data);
        }
    } catch (error) {
        console.error('Error loading plans:', error);
        document.getElementById('plansGrid').innerHTML = 
            '<p class="text-red-600 text-center col-span-3">Error loading plans. Please try again.</p>';
    }
}

// Display plans
function displayPlans(plans) {
    const grid = document.getElementById('plansGrid');
    
    if (plans.length === 0) {
        grid.innerHTML = '<p class="text-gray-600 text-center col-span-3">No plans available</p>';
        return;
    }

    const plansHtml = plans.map((plan, index) => `
        <div class="plan-card ${index === 1 ? 'popular' : ''}" onclick="selectPlan(${plan.id})">
            ${index === 1 ? '<div class="text-center mb-2"><span class="bg-yellow-400 text-yellow-900 px-3 py-1 rounded-full text-xs font-bold">POPULAR</span></div>' : ''}
            <h3 class="plan-name text-xl font-bold mb-2">${plan.name}</h3>
            <div class="plan-price mb-4">
                $${plan.price}
            </div>
            <ul class="plan-features mb-4">
                ${plan.duration_hours ? `<li class="plan-feature">⏱️ ${plan.duration_hours} hours</li>` : ''}
                ${plan.data_limit_mb ? `<li class="plan-feature">📊 ${plan.data_limit_mb} MB data</li>` : '<li class="plan-feature">📊 Unlimited data</li>'}
                ${plan.bandwidth_down ? `<li class="plan-feature">⚡ ${plan.bandwidth_down / 1024} Mbps speed</li>` : ''}
                <li class="plan-feature">✓ ${plan.validity_days} days validity</li>
            </ul>
            <button class="w-full bg-white text-purple-600 py-2 rounded-lg font-semibold hover:bg-gray-100 transition">
                Select Plan
            </button>
        </div>
    `).join('');

    grid.innerHTML = plansHtml;
}

// Select a plan
function selectPlan(planId) {
    // Store selected plan
    sessionStorage.setItem('selectedPlan', planId);
    
    // Redirect to payment page
    window.location.href = `payment.html?plan=${planId}`;
}

// Voucher form handling
document.addEventListener('DOMContentLoaded', function() {
    const voucherForm = document.getElementById('voucherForm');
    
    if (voucherForm) {
        // Auto-format voucher code input
        const voucherInput = document.getElementById('voucherCode');
        voucherInput.addEventListener('input', function(e) {
            let value = e.target.value.toUpperCase().replace(/[^A-Z0-9]/g, '');
            
            // Add dashes
            if (value.length > 4) {
                value = value.substr(0, 4) + '-' + value.substr(4);
            }
            if (value.length > 9) {
                value = value.substr(0, 9) + '-' + value.substr(9);
            }
            
            e.target.value = value.substr(0, 14);
        });

        voucherForm.addEventListener('submit', async function(e) {
            e.preventDefault();
            
            const code = document.getElementById('voucherCode').value;
            const resultDiv = document.getElementById('voucherResult');
            
            // Validate code
            if (!validateVoucherCode(code)) {
                showMessage(resultDiv, 'Please enter a valid voucher code', 'error');
                return;
            }

            // Show loading
            const submitBtn = voucherForm.querySelector('button[type="submit"]');
            submitBtn.disabled = true;
            submitBtn.textContent = 'Validating...';

            try {
                // Validate voucher
                const response = await fetch(`${PORTAL_API}/vouchers/validate.php`, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ code })
                });

                const data = await response.json();

                if (data.success) {
                    // Redeem voucher
                    await redeemVoucher(code);
                } else {
                    showMessage(resultDiv, data.message || 'Invalid voucher code', 'error');
                }
            } catch (error) {
                showMessage(resultDiv, 'Error validating voucher. Please try again.', 'error');
            } finally {
                submitBtn.disabled = false;
                submitBtn.textContent = 'Connect Now';
            }
        });
    }

    // Email form handling
    const emailForm = document.getElementById('emailForm');
    if (emailForm) {
        emailForm.addEventListener('submit', async function(e) {
            e.preventDefault();
            
            const name = document.getElementById('userName').value;
            const email = document.getElementById('userEmail').value;
            const phone = document.getElementById('userPhone').value;

            const submitBtn = emailForm.querySelector('button[type="submit"]');
            submitBtn.disabled = true;
            submitBtn.textContent = 'Connecting...';

            try {
                // Register user for free access
                await registerFreeAccess(name, email, phone);
            } catch (error) {
                alert('Error: ' + error.message);
            } finally {
                submitBtn.disabled = false;
                submitBtn.textContent = 'Get Free Access';
            }
        });
    }
});

// Validate voucher code format
function validateVoucherCode(code) {
    const pattern = /^[A-Z0-9]{4}-[A-Z0-9]{4}-[A-Z0-9]{4}$/;
    return pattern.test(code);
}

// Redeem voucher
async function redeemVoucher(code) {
    try {
        const mac = getMacAddress(); // Get device MAC if available
        
        const response = await fetch(`${PORTAL_API}/vouchers/redeem.php`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ code, mac_address: mac })
        });

        const data = await response.json();

        if (data.success) {
            // Store voucher info
            sessionStorage.setItem('activeVoucher', JSON.stringify(data.data));
            
            // Redirect to success page
            window.location.href = 'success.html?type=voucher';
        } else {
            throw new Error(data.message || 'Failed to redeem voucher');
        }
    } catch (error) {
        throw error;
    }
}

// Register for free access
async function registerFreeAccess(name, email, phone) {
    try {
        const response = await fetch(`${PORTAL_API}/portal/authenticate.php`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ 
                name, 
                email, 
                phone,
                access_type: 'free'
            })
        });

        const data = await response.json();

        if (data.success) {
            // Store session info
            sessionStorage.setItem('activeSession', JSON.stringify(data.data));
            
            // Redirect to success page
            window.location.href = 'success.html?type=free';
        } else {
            throw new Error(data.message || 'Failed to register');
        }
    } catch (error) {
        throw error;
    }
}

// Get device MAC address (if available)
function getMacAddress() {
    // In real implementation, this would come from the network layer
    // For now, return null as browsers don't expose MAC addresses
    return null;
}

// Show message helper
function showMessage(element, message, type) {
    element.innerHTML = `
        <div class="alert alert-${type}">
            ${message}
        </div>
    `;
    element.classList.remove('hidden');

    // Auto-hide after 5 seconds
    setTimeout(() => {
        element.classList.add('hidden');
    }, 5000);
}

// Get URL parameters
function getUrlParameter(name) {
    name = name.replace(/[\[]/, '\\[').replace(/[\]]/, '\\]');
    const regex = new RegExp('[\\?&]' + name + '=([^&#]*)');
    const results = regex.exec(location.search);
    return results === null ? '' : decodeURIComponent(results[1].replace(/\+/g, ' '));
}