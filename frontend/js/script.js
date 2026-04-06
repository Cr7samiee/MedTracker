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

            // Strict Password validation (individual checks to avoid encoding issues)
            const pwErrors = [];
            if (password.length < 8) pwErrors.push('at least 8 characters');
            if (!/[a-z]/.test(password)) pwErrors.push('a lowercase letter');
            if (!/[A-Z]/.test(password)) pwErrors.push('an uppercase letter');
            if (!/[0-9]/.test(password)) pwErrors.push('a number');
            if (!/[^a-zA-Z0-9]/.test(password)) pwErrors.push('a special character (e.g. @$!%*?&)');
            if (pwErrors.length > 0) {
                messageEl.className = 'form-message error';
                messageEl.textContent = 'Password must include: ' + pwErrors.join(', ') + '.';
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
                    
                    setTimeout(() => {
                        if (result.role === 'Admin') {
                            window.location.href = 'admin_dashboard.html';
                        } else if (result.role === 'Health Worker') {
                            window.location.href = 'doctor_dashboard.html';
                        } else {
                            window.location.href = 'dashboard.html'; // Patient
                        }
                    }, 1000);
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
    const segments = document.querySelectorAll('.strength-segment');
    const strengthText = document.getElementById('passwordStrengthText');

    if (pwInput && segments.length === 5 && strengthText && document.getElementById('signupForm')) {
        pwInput.addEventListener('input', () => {
            const val = pwInput.value;
            let strength = 0;

            if (val.length === 0) {
                segments.forEach(seg => seg.style.backgroundColor = '#e0e0e0');
                strengthText.textContent = 'Enter a password to see strength';
                strengthText.style.color = '#888';
                return;
            }

            if (val.length >= 8) strength += 1;
            if (/[a-z]/.test(val)) strength += 1;
            if (/[A-Z]/.test(val)) strength += 1;
            if (/\d/.test(val)) strength += 1;
            if (/[@$!%*?&]/.test(val)) strength += 1;

            let color = '';
            let text = '';
            let activeSegments = 0;

            if (strength <= 1) {
                color = '#ff4d4d'; // Red
                text = 'Very Weak';
                activeSegments = 1;
            } else if (strength === 2) {
                color = '#ff8533'; // Orange-Red
                text = 'Weak';
                activeSegments = 2;
            } else if (strength === 3) {
                color = '#ffcc00'; // Yellow
                text = 'Fair';
                activeSegments = 3;
            } else if (strength === 4) {
                color = '#99cc00'; // Light Green
                text = 'Good';
                activeSegments = 4;
            } else if (strength === 5) {
                color = '#00cc44'; // Green
                text = 'Very Good';
                activeSegments = 5;
            }

            segments.forEach((seg, index) => {
                if (index < activeSegments) {
                    seg.style.backgroundColor = color;
                } else {
                    seg.style.backgroundColor = '#e0e0e0';
                }
            });

            strengthText.textContent = text;
            strengthText.style.color = color;
        });
    }
});
