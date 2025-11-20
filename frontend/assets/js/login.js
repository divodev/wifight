// WiFight ISP - Login Page

document.addEventListener('DOMContentLoaded', () => {
    // Redirect if already logged in
    if (Auth.isAuthenticated()) {
        window.location.href = 'index.html';
        return;
    }

    const loginForm = document.getElementById('login-form');
    const errorMessage = document.getElementById('error-message');

    loginForm.addEventListener('submit', async (e) => {
        e.preventDefault();

        const email = document.getElementById('email').value;
        const password = document.getElementById('password').value;
        const remember = document.getElementById('remember').checked;

        // Hide previous error
        errorMessage.style.display = 'none';

        // Disable submit button
        const submitBtn = loginForm.querySelector('button[type="submit"]');
        submitBtn.disabled = true;
        submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Logging in...';

        try {
            const result = await Auth.login(email, password);

            if (result.success) {
                // Redirect to dashboard
                window.location.href = 'index.html';
            } else {
                showError(result.error || 'Login failed. Please try again.');
            }
        } catch (error) {
            showError(error.message || 'An error occurred. Please try again.');
        } finally {
            submitBtn.disabled = false;
            submitBtn.innerHTML = '<i class="fas fa-sign-in-alt"></i> Login';
        }
    });

    function showError(message) {
        errorMessage.textContent = message;
        errorMessage.style.display = 'block';
    }

    // Forgot password handler
    document.getElementById('forgot-password')?.addEventListener('click', (e) => {
        e.preventDefault();
        alert('Password reset functionality will be available soon.');
    });
});
