document.addEventListener('DOMContentLoaded', function() {
    const loginForm = document.querySelector('.login-form');
    const emailInput = document.getElementById('email');
    const passwordInput = document.getElementById('password');
    const loginBtn = document.querySelector('.login-btn');

    // Focus on first empty field
    if (!emailInput.value) {
        emailInput.focus();
    } else if (!passwordInput.value) {
        passwordInput.focus();
    }

    // Add client-side validation
    loginForm.addEventListener('submit', function(e) {
        const email = emailInput.value.trim();
        const password = passwordInput.value.trim();
        
        // Clear previous error styling
        emailInput.classList.remove('error');
        passwordInput.classList.remove('error');
        
        let hasError = false;
        
        if (!email) {
            emailInput.classList.add('error');
            showFieldError(emailInput, 'Email is required');
            hasError = true;
        } else if (!isValidEmail(email)) {
            emailInput.classList.add('error');
            showFieldError(emailInput, 'Please enter a valid email address');
            hasError = true;
        }
        
        if (!password) {
            passwordInput.classList.add('error');
            showFieldError(passwordInput, 'Password is required');
            hasError = true;
        }
        
        if (hasError) {
            e.preventDefault();
            return false;
        }
        
        // Show loading state
        loginBtn.innerHTML = 'Signing in...';
        loginBtn.disabled = true;
    });

    // Real-time email validation
    emailInput.addEventListener('blur', function() {
        const email = this.value.trim();
        if (email && !isValidEmail(email)) {
            this.classList.add('error');
            showFieldError(this, 'Please enter a valid email address');
        } else {
            this.classList.remove('error');
            hideFieldError(this);
        }
    });

    // Remove error styling when user starts typing
    emailInput.addEventListener('input', function() {
        this.classList.remove('error');
        hideFieldError(this);
    });

    passwordInput.addEventListener('input', function() {
        this.classList.remove('error');
        hideFieldError(this);
    });

    // Enter key handling
    emailInput.addEventListener('keypress', function(e) {
        if (e.key === 'Enter') {
            passwordInput.focus();
        }
    });

    passwordInput.addEventListener('keypress', function(e) {
        if (e.key === 'Enter') {
            loginForm.submit();
        }
    });

    // Helper functions
    function isValidEmail(email) {
        const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        return emailRegex.test(email);
    }

    function showFieldError(field, message) {
        hideFieldError(field); // Remove existing error first
        
        const errorDiv = document.createElement('div');
        errorDiv.className = 'field-error';
        errorDiv.textContent = message;
        
        field.parentNode.appendChild(errorDiv);
    }

    function hideFieldError(field) {
        const existingError = field.parentNode.querySelector('.field-error');
        if (existingError) {
            existingError.remove();
        }
    }

    // Auto-hide server error messages after 5 seconds
    const errorMessage = document.querySelector('.error-message');
    if (errorMessage) {
        setTimeout(function() {
            errorMessage.style.opacity = '0';
            setTimeout(function() {
                errorMessage.style.display = 'none';
            }, 300);
        }, 5000);
    }
});