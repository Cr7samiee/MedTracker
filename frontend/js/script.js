// frontend/js/script.js

window.MedTrackerApp = {
    normalizeRole(role) {
        return (role || '').trim().toLowerCase();
    },

    getCurrentUser() {
        return {
            userId: localStorage.getItem('user_id') || '',
            role: localStorage.getItem('role') || '',
            name: localStorage.getItem('name') || 'User',
            workerCode: localStorage.getItem('worker_code') || ''
        };
    },

    getDashboardRoute(role) {
        const normalizedRole = this.normalizeRole(role);

        if (!normalizedRole) {
            return 'login.html';
        }

        if (normalizedRole === 'admin') {
            return 'admin_dashboard.html';
        }

        if (normalizedRole === 'health worker' || normalizedRole === 'doctor') {
            return 'doctor_dashboard.html';
        }

        if (normalizedRole === 'user' || normalizedRole === 'patient') {
            return 'dashboard.html';
        }

        return 'login.html';
    },

    getRoleLabel(role) {
        const normalizedRole = this.normalizeRole(role);

        if (normalizedRole === 'admin') {
            return 'Admin';
        }

        if (normalizedRole === 'health worker' || normalizedRole === 'doctor') {
            return 'Health Worker';
        }

        return 'Patient';
    },

    getPortalConfig(role) {
        const normalizedRole = this.normalizeRole(role);

        if (normalizedRole === 'admin') {
            return {
                key: 'admin',
                label: 'Admin Control',
                shortLabel: 'Admin',
                subtitle: 'System operations and account management',
                links: [
                    { href: 'admin_dashboard.html', label: 'Overview', icon: '🎛️' },
                    { href: 'admin_dashboard.html#adminUsageChartCard', label: 'Usage Analytics', icon: '📈' },
                    { href: 'admin_dashboard.html#userManagementSection', label: 'User Accounts', icon: '👥' },
                    { href: 'admin_dashboard.html#adminPrescriptionsSection', label: 'Prescription Audit', icon: '🧾' },
                    { href: 'settings.html', label: 'Account', icon: '⚙️' }
                ]
            };
        }

        if (normalizedRole === 'health worker' || normalizedRole === 'doctor') {
            return {
                key: 'health-worker',
                label: 'Healthcare Professional',
                shortLabel: 'Health Worker',
                subtitle: 'Patient queue, prescriptions, and follow-up',
                links: [
                    { href: 'doctor_dashboard.html', label: 'Overview', icon: '📊' },
                    { href: 'doctor_schedule.html', label: 'Prescription Desk', icon: '🗓️' },
                    { href: 'doctor_dashboard.html#doctorPatientSection', label: 'Patient Queue', icon: '👥' },
                    { href: 'doctor_dashboard.html#doctorAlertsSection', label: 'Alerts', icon: '🔔' },
                    { href: 'settings.html', label: 'Account', icon: '⚙️' }
                ]
            };
        }

        return {
            key: 'patient',
            label: 'Patient Portal',
            shortLabel: 'Patient',
            subtitle: 'Medication tracking and adherence',
            links: [
                { href: 'dashboard.html', label: 'Dashboard', icon: '📊' },
                { href: 'medications.html', label: 'Medications', icon: '💊' },
                { href: 'schedule.html', label: 'Schedule', icon: '🗓️' },
                { href: 'reports.html', label: 'Reports', icon: '📈' },
                { href: 'settings.html', label: 'Settings', icon: '⚙️' }
            ]
        };
    },

    renderRoleNav(container, currentHref) {
        if (!container) {
            return;
        }

        const { role } = this.getCurrentUser();
        const config = this.getPortalConfig(role);
        container.innerHTML = config.links.map((link) => {
            const isActive = currentHref === link.href;
            return `
                <a href="${link.href}" class="nav-item${isActive ? ' active' : ''}">
                    <i>${link.icon}</i>
                    <span>${link.label}</span>
                </a>
            `;
        }).join('');
    },

    protectRoute(allowedRoles) {
        const normalizedRole = this.normalizeRole(this.getCurrentUser().role);
        const allowed = (allowedRoles || []).map((role) => this.normalizeRole(role));

        if (allowed.length && !allowed.includes(normalizedRole)) {
            window.location.href = this.getDashboardRoute(normalizedRole);
            return false;
        }

        return true;
    },

    clearSession() {
        localStorage.removeItem('role');
        localStorage.removeItem('name');
        localStorage.removeItem('user_id');
        localStorage.removeItem('worker_code');
    },

    logout() {
        this.clearSession();
        window.location.href = 'login.html';
    },

    applyDynamicUserName() {
        const userName = this.getCurrentUser().name;

        document.querySelectorAll('.dynamic-user-name').forEach((el) => {
            el.textContent = userName;
        });
    }
};

document.addEventListener('DOMContentLoaded', () => {
    window.MedTrackerApp.applyDynamicUserName();

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
            document.getElementById('healthWorkerCodeGroup').style.display = 'block';
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
                    localStorage.setItem('name', result.name || 'User');
                    localStorage.setItem('user_id', result.user_id || '');
                    localStorage.setItem('worker_code', result.worker_code || '');
                    
                    setTimeout(() => {
                        window.location.href = window.MedTrackerApp.getDashboardRoute(result.role);
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
