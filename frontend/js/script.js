// frontend/js/script.js

document.addEventListener('DOMContentLoaded', () => {
    // ---- ROLE SELECTION TO SIGNUP LOGIC ----
    const urlParams = new URLSearchParams(window.location.search);
    const role = urlParams.get('role');

    // If on signup page, set the role based on URL param
    if (document.getElementById('signupForm') && role) {
        document.getElementById('role').value = role;
        document.getElementById('roleDisplay').textContent = role;

        // Show/Hide dynamic fields
        if (role === 'Health Worker') {
            document.getElementById('postGroup').style.display = 'block';
            document.getElementById('post').setAttribute('required', 'required');
        } else if (role === 'User') {
            document.getElementById('relationGroup').style.display = 'block';
            document.getElementById('relation').setAttribute('required', 'required');
        }
    }

    // ---- SIGNUP LOGIC ----
    const signupForm = document.getElementById('signupForm');
    if (signupForm) {
        signupForm.addEventListener('submit', async (e) => {
            e.preventDefault();
            const formData = new FormData(signupForm);
            const messageEl = document.getElementById('signupMessage');

            // Validation logic
            const email = document.getElementById('email').value;
            const password = document.getElementById('password').value;
            const phone = document.getElementById('phone').value;

            // Gmail only regex
            const emailRegex = /^[a-zA-Z0-9._%+-]+@gmail\.com$/;
            if (!emailRegex.test(email)) {
                messageEl.className = 'form-message error';
                messageEl.textContent = 'Only @gmail.com email addresses are allowed.';
                return;
            }

            // Phone validation (Nepal NTC/Ncell prefixes: 984, 985, 986, 974, 975 for NTC; 980, 981, 982 for Ncell; 10 digits total)
            const phoneRegex = /^(984|985|986|974|975|980|981|982)\d{7}$/;
            if (!phoneRegex.test(phone)) {
                messageEl.className = 'form-message error';
                messageEl.textContent = 'Please enter a valid NTC or Ncell phone number (10 digits).';
                return;
            }

            // Strict Password validation
            const passwordRegex = /^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*?&])[A-Za-z\d@$!%*?&]{8,}$/;
            if (!passwordRegex.test(password)) {
                messageEl.className = 'form-message error';
                messageEl.textContent = 'Password must be at least 8 characters long and include an uppercase letter, lowercase letter, number, and special character.';
                return;
            }

            try {
                const response = await fetch('../backend/auth/signup.php', {
                    method: 'POST',
                    body: formData
                });

                const result = await response.json();

                if (result.success) {
                    messageEl.className = 'form-message success';
                    messageEl.textContent = result.message + ' Redirecting to login...';
                    setTimeout(() => window.location.href = 'login.html', 2000);
                } else {
                    messageEl.className = 'form-message error';
                    messageEl.textContent = result.message;
                }
            } catch (error) {
                messageEl.className = 'form-message error';
                messageEl.textContent = 'Network error. Please try again.';
            }
        });
    }

    // ---- LOGIN LOGIC ----
    const loginForm = document.getElementById('loginForm');
    if (loginForm) {
        loginForm.addEventListener('submit', async (e) => {
            e.preventDefault();
            const formData = new FormData(loginForm);
            const messageEl = document.getElementById('loginMessage');

            try {
                const response = await fetch('../backend/auth/login.php', {
                    method: 'POST',
                    body: formData
                });

                const result = await response.json();

                if (result.success) {
                    messageEl.className = 'form-message success';
                    messageEl.textContent = result.message;
                    // Store user data in localStorage (simplification)
                    localStorage.setItem('role', result.role);
                    setTimeout(() => window.location.href = 'dashboard.html', 1000);
                } else {
                    messageEl.className = 'form-message error';
                    messageEl.textContent = result.message;
                }
            } catch (error) {
                messageEl.className = 'form-message error';
                messageEl.textContent = 'Network error. Please try again.';
            }
        });
    }

    // ---- FORGOT PASSWORD LOGIC ----
    const forgotPasswordForm = document.getElementById('forgotPasswordForm');
    if (forgotPasswordForm) {
        forgotPasswordForm.addEventListener('submit', async (e) => {
            e.preventDefault();
            const formData = new FormData(forgotPasswordForm);
            const messageEl = document.getElementById('forgotMessage');
            const phone = document.getElementById('forgot-phone').value;

            try {
                const response = await fetch('../backend/auth/forgot_password.php', {
                    method: 'POST',
                    body: formData
                });

                const result = await response.json();

                if (result.success) {
                    messageEl.className = 'form-message success';
                    messageEl.textContent = result.message; // In dev, we show the OTP here

                    // Show prompt and then redirect
                    setTimeout(() => {
                        window.location.href = `reset_password.html?phone=${encodeURIComponent(phone)}`;
                    }, 3000);
                } else {
                    messageEl.className = 'form-message error';
                    messageEl.textContent = result.message;
                }
            } catch (error) {
                messageEl.className = 'form-message error';
                messageEl.textContent = 'Network error. Please try again.';
            }
        });
    }

    // ---- RESET PASSWORD LOGIC ----
    const resetPasswordForm = document.getElementById('resetPasswordForm');
    if (resetPasswordForm) {
        // Populate phone from URL
        const phoneParam = urlParams.get('phone');
        if (phoneParam) {
            document.getElementById('reset-phone').value = phoneParam;
        }

        resetPasswordForm.addEventListener('submit', async (e) => {
            e.preventDefault();
            const formData = new FormData(resetPasswordForm);
            const messageEl = document.getElementById('resetMessage');

            try {
                const response = await fetch('../backend/auth/reset_password.php', {
                    method: 'POST',
                    body: formData
                });

                const result = await response.json();

                if (result.success) {
                    messageEl.className = 'form-message success';
                    messageEl.textContent = result.message + ' Redirecting to login...';
                    setTimeout(() => window.location.href = 'login.html', 2000);
                } else {
                    messageEl.className = 'form-message error';
                    messageEl.textContent = result.message;
                }
            } catch (error) {
                messageEl.className = 'form-message error';
                messageEl.textContent = 'Network error. Please try again.';
            }
        });
    }

    // ---- PASSWORD TOGGLE LOGIC ----
    document.querySelectorAll('.password-toggle').forEach(button => {
        button.addEventListener('click', function () {
            const passwordInputToggle = this.previousElementSibling;
            if (passwordInputToggle && passwordInputToggle.tagName === 'INPUT') {
                const type = passwordInputToggle.getAttribute('type') === 'password' ? 'text' : 'password';
                passwordInputToggle.setAttribute('type', type);
                this.textContent = type === 'password' ? '👁️' : '🙈';
            }
        });
    });

    // ---- PASSWORD STRENGTH LOGIC ----
    const pwInput = document.getElementById('password');
    const strengthBarContainer = document.getElementById('passwordStrengthBarContainer');
    const strengthBar = document.getElementById('passwordStrengthBar');
    const strengthText = document.getElementById('passwordStrengthText');

    if (pwInput && strengthBarContainer && strengthBar && strengthText && document.getElementById('signupForm')) {
        pwInput.addEventListener('input', () => {
            const val = pwInput.value;
            let strength = 0;

            if (val.length > 0) {
                strengthBarContainer.style.display = 'block';
            } else {
                strengthBarContainer.style.display = 'none';
                strengthText.textContent = '';
                return;
            }

            if (val.length >= 8) strength += 1;
            if (/[a-z]/.test(val)) strength += 1;
            if (/[A-Z]/.test(val)) strength += 1;
            if (/\d/.test(val)) strength += 1;
            if (/[@$!%*?&]/.test(val)) strength += 1;

            let color = '';
            let text = '';
            let width = '';

            if (strength <= 2) {
                color = '#ff4d4d'; // Red
                text = 'Weak';
                width = '20%';
            } else if (strength === 3) {
                color = '#ff9900'; // Orange
                text = 'Fair';
                width = '40%';
            } else if (strength === 4) {
                color = '#ffcc00'; // Yellow
                text = 'Good';
                width = '70%';
            } else if (strength === 5) {
                color = '#00cc44'; // Green
                text = 'Very Good';
                width = '100%';
            }

            strengthBar.style.width = width;
            strengthBar.style.backgroundColor = color;
            strengthText.textContent = text;
            strengthText.style.color = color;
        });
    }
});
