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
                    { href: 'settings.html', label: 'Settings', icon: '⚙️' }
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
                    { href: 'video_call.html', label: 'Video Call', icon: '📹' },
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
                { href: 'video_call.html', label: 'Video Call', icon: '📹' },
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

    initUiKit() {
        if (window.__medtrackerUiKitReady) {
            return;
        }

        window.__medtrackerUiKitReady = true;

        const toastStack = document.createElement('div');
        toastStack.className = 'mt-toast-stack';
        toastStack.setAttribute('aria-live', 'polite');
        toastStack.setAttribute('aria-atomic', 'false');

        const dialogRoot = document.createElement('div');
        dialogRoot.className = 'mt-dialog-root';
        dialogRoot.innerHTML = `
            <div class="mt-dialog-backdrop"></div>
            <div class="mt-dialog-panel" role="dialog" aria-modal="true" aria-labelledby="mtDialogTitle">
                <div class="mt-dialog-header">
                    <div class="mt-dialog-icon mt-dialog-icon--info" data-dialog-icon aria-hidden="true">i</div>
                    <div class="mt-dialog-copy">
                        <h3 class="mt-dialog-title" id="mtDialogTitle" data-dialog-title>Please confirm</h3>
                        <p class="mt-dialog-message" data-dialog-message></p>
                    </div>
                </div>
                <div class="mt-dialog-input-wrap" data-dialog-input-wrap hidden>
                    <input class="mt-dialog-input" data-dialog-input type="text" autocomplete="off">
                </div>
                <div class="mt-dialog-actions">
                    <button type="button" class="btn btn-secondary mt-dialog-action" data-dialog-cancel>Cancel</button>
                    <button type="button" class="btn btn-primary mt-dialog-action" data-dialog-confirm>Continue</button>
                </div>
            </div>
        `;

        document.body.appendChild(toastStack);
        document.body.appendChild(dialogRoot);

        this.toastStack = toastStack;
        this.dialogRoot = dialogRoot;
        this.dialogBackdrop = dialogRoot.querySelector('.mt-dialog-backdrop');
        this.dialogIcon = dialogRoot.querySelector('[data-dialog-icon]');
        this.dialogTitle = dialogRoot.querySelector('[data-dialog-title]');
        this.dialogMessage = dialogRoot.querySelector('[data-dialog-message]');
        this.dialogInputWrap = dialogRoot.querySelector('[data-dialog-input-wrap]');
        this.dialogInput = dialogRoot.querySelector('[data-dialog-input]');
        this.dialogCancelButton = dialogRoot.querySelector('[data-dialog-cancel]');
        this.dialogConfirmButton = dialogRoot.querySelector('[data-dialog-confirm]');

        dialogRoot.addEventListener('click', (event) => {
            if (event.target === this.dialogBackdrop || event.target.closest('[data-dialog-cancel]')) {
                this.finishDialog('cancel');
                return;
            }

            if (event.target.closest('[data-dialog-confirm]')) {
                this.finishDialog('confirm');
            }
        });

        dialogRoot.addEventListener('keydown', (event) => {
            if (!this.dialogState) {
                return;
            }

            if (event.key === 'Escape') {
                event.preventDefault();
                this.finishDialog('cancel');
                return;
            }

            if (event.key === 'Enter') {
                const target = event.target;
                if (target === this.dialogInput || target === this.dialogConfirmButton) {
                    event.preventDefault();
                    this.finishDialog('confirm');
                }
            }
        });
    },

    installNativeToastFallback() {
        if (window.__medtrackerAlertPatched) {
            return;
        }

        window.__medtrackerAlertPatched = true;
        window.alert = (message) => {
            this.showToast(message, 'info');
        };
    },

    showToast(message, variant = 'info', options = {}) {
        const text = String(message || '').trim();
        if (!text) {
            return;
        }

        this.initUiKit();

        const icons = {
            info: 'i',
            success: '✓',
            warning: '!',
            error: '!'
        };
        const titles = {
            info: 'Notice',
            success: 'Saved',
            warning: 'Check this',
            error: 'Something went wrong'
        };
        const duration = Number(options.duration || (variant === 'error' ? 4800 : 3400));
        const toast = document.createElement('div');
        toast.className = `mt-toast mt-toast--${variant}`;
        toast.innerHTML = `
            <div class="mt-toast__icon" aria-hidden="true"></div>
            <div class="mt-toast__content">
                <strong class="mt-toast__title"></strong>
                <p class="mt-toast__message"></p>
            </div>
            <button type="button" class="mt-toast__close" aria-label="Dismiss notification">&times;</button>
        `;

        toast.querySelector('.mt-toast__icon').textContent = icons[variant] || icons.info;
        toast.querySelector('.mt-toast__title').textContent = options.title || titles[variant] || titles.info;
        toast.querySelector('.mt-toast__message').textContent = text;

        const dismiss = () => {
            if (!toast.isConnected) {
                return;
            }

            toast.classList.remove('is-visible');
            toast.classList.add('is-hiding');
            window.setTimeout(() => toast.remove(), 180);
        };

        toast.querySelector('.mt-toast__close').addEventListener('click', dismiss);
        this.toastStack.appendChild(toast);
        window.requestAnimationFrame(() => toast.classList.add('is-visible'));
        window.setTimeout(dismiss, duration);
    },

    openDialog(config = {}) {
        this.initUiKit();

        if (this.dialogState) {
            this.finishDialog('cancel');
        }

        const tone = config.tone || 'info';
        const icons = {
            info: 'i',
            success: '✓',
            warning: '!',
            error: '!'
        };

        this.dialogIcon.textContent = icons[tone] || icons.info;
        this.dialogIcon.className = `mt-dialog-icon mt-dialog-icon--${tone}`;
        this.dialogTitle.textContent = config.title || (config.mode === 'prompt' ? 'Input required' : 'Please confirm');
        this.dialogMessage.textContent = String(config.message || '');
        this.dialogCancelButton.textContent = config.cancelText || 'Cancel';
        this.dialogConfirmButton.textContent = config.confirmText || (config.mode === 'prompt' ? 'Save' : 'Continue');
        this.dialogInputWrap.hidden = config.mode !== 'prompt';
        this.dialogInput.value = String(config.defaultValue ?? '');
        this.dialogInput.placeholder = String(config.placeholder || '');
        this.dialogInput.setAttribute('aria-label', config.inputLabel || 'Dialog input');
        this.dialogRoot.classList.add('is-visible');
        document.body.classList.add('mt-dialog-open');

        return new Promise((resolve) => {
            this.dialogState = {
                mode: config.mode || 'confirm',
                trim: config.trim !== false,
                resolve
            };

            window.setTimeout(() => {
                if (this.dialogState?.mode === 'prompt') {
                    this.dialogInput.focus();
                    this.dialogInput.select();
                } else {
                    this.dialogConfirmButton.focus();
                }
            }, 20);
        });
    },

    finishDialog(action) {
        if (!this.dialogState) {
            return;
        }

        const { mode, trim, resolve } = this.dialogState;
        const promptValue = trim ? this.dialogInput.value.trim() : this.dialogInput.value;
        this.dialogState = null;
        this.dialogRoot.classList.remove('is-visible');
        document.body.classList.remove('mt-dialog-open');

        resolve(mode === 'prompt' ? (action === 'confirm' ? promptValue : null) : action === 'confirm');
    },

    confirmAction(message, options = {}) {
        return this.openDialog({
            ...options,
            mode: 'confirm',
            message
        });
    },

    promptForText(message, options = {}) {
        return this.openDialog({
            ...options,
            mode: 'prompt',
            message
        });
    },

    getEmptyStateHtml(options = {}) {
        const icon = options.icon || '○';
        const title = options.title || 'Nothing here yet';
        const message = options.message || 'Content will appear here once data is available.';
        const actionLabel = options.actionLabel ? `<a class="btn btn-secondary mt-empty-state__action" href="${options.actionHref || '#'}">${options.actionLabel}</a>` : '';

        return `
            <div class="mt-empty-state">
                <div class="mt-empty-state__icon" aria-hidden="true">${icon}</div>
                <h3 class="mt-empty-state__title">${title}</h3>
                <p class="mt-empty-state__message">${message}</p>
                ${actionLabel}
            </div>
        `;
    },

    getSkeletonListHtml(options = {}) {
        const count = Math.max(1, Number(options.count || 3));
        const compactClass = options.compact ? ' mt-skeleton-card--compact' : '';

        return `
            <div class="mt-skeleton-list" aria-hidden="true">
                ${Array.from({ length: count }, () => `
                    <div class="mt-skeleton-card${compactClass}">
                        <div class="mt-skeleton-row">
                            <div class="mt-skeleton-avatar"></div>
                            <div class="mt-skeleton-meta">
                                <div class="mt-skeleton-line" style="width: 55%;"></div>
                                <div class="mt-skeleton-line" style="width: 78%;"></div>
                            </div>
                        </div>
                        <div class="mt-skeleton-line" style="width: 92%;"></div>
                        <div class="mt-skeleton-line" style="width: 68%;"></div>
                    </div>
                `).join('')}
            </div>
        `;
    },

    setupMobileSidebar() {
        const sidebar = document.querySelector('.dashboard-layout .sidebar');
        if (!sidebar || sidebar.dataset.mobileSidebarReady === 'true') {
            return;
        }

        sidebar.dataset.mobileSidebarReady = 'true';

        const button = document.createElement('button');
        button.type = 'button';
        button.className = 'sidebar-toggle';
        button.setAttribute('aria-label', 'Open navigation menu');
        button.setAttribute('aria-expanded', 'false');
        button.innerHTML = '<span></span><span></span><span></span>';

        const overlay = document.createElement('div');
        overlay.className = 'sidebar-overlay';

        const mobileQuery = window.matchMedia('(max-width: 960px)');
        const closeSidebar = () => {
            document.body.classList.remove('sidebar-open');
            button.setAttribute('aria-expanded', 'false');
            button.setAttribute('aria-label', 'Open navigation menu');
        };
        const toggleSidebar = () => {
            const isOpen = document.body.classList.toggle('sidebar-open');
            button.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
            button.setAttribute('aria-label', isOpen ? 'Close navigation menu' : 'Open navigation menu');
        };

        button.addEventListener('click', toggleSidebar);
        overlay.addEventListener('click', closeSidebar);
        sidebar.addEventListener('click', (event) => {
            if (!mobileQuery.matches || !event.target.closest('a, button')) {
                return;
            }

            closeSidebar();
        });

        const handleViewportChange = () => {
            if (!mobileQuery.matches) {
                closeSidebar();
            }
        };

        if (typeof mobileQuery.addEventListener === 'function') {
            mobileQuery.addEventListener('change', handleViewportChange);
        } else if (typeof mobileQuery.addListener === 'function') {
            mobileQuery.addListener(handleViewportChange);
        }

        document.body.appendChild(button);
        document.body.appendChild(overlay);
    },

    logout() {
        this.clearSession();
        window.location.href = 'login.html';
    },

    bindGlobalLogoutFallback() {
        if (window.__medtrackerLogoutFallbackBound) {
            return;
        }

        window.__medtrackerLogoutFallbackBound = true;
        document.addEventListener('click', async (event) => {
            const logoutButton = event.target.closest('#logoutBtn');
            if (!logoutButton) {
                return;
            }

            event.preventDefault();
            event.stopImmediatePropagation();
            if (await this.confirmAction('Are you sure you want to log out?', {
                title: 'Log out',
                confirmText: 'Log out',
                tone: 'warning'
            })) {
                this.logout();
            }
        }, true);
    },

    hydrateSettingsFallbackUi() {
        const nav = document.getElementById('settingsRoleNav');
        if (!nav) {
            return;
        }

        const currentUser = this.getCurrentUser();
        const portal = this.getPortalConfig(currentUser.role);

        if (!nav.innerHTML.trim()) {
            this.renderRoleNav(nav, 'settings.html');
        }

        const sidebarSubtitle = document.getElementById('settingsSidebarSubtitle');
        const roleLabel = document.getElementById('settingsRoleLabel');
        const accountBadge = document.getElementById('settingsAccountBadge');
        const headerTitle = document.getElementById('settingsHeaderTitle');
        const headerCopy = document.getElementById('settingsHeaderCopy');
        const supportCopy = document.getElementById('settingsSupportCopy');
        const backLink = document.getElementById('settingsBackLink');
        const quickLinks = document.getElementById('settingsQuickLinks');

        if (sidebarSubtitle) sidebarSubtitle.textContent = portal.label;
        if (roleLabel) roleLabel.textContent = portal.shortLabel;
        if (accountBadge) accountBadge.textContent = `${portal.shortLabel} Account`;
        if (headerTitle) headerTitle.textContent = `${portal.shortLabel} Account & Security`;
        if (headerCopy) headerCopy.textContent = `Manage your own sign-in details and move back into the ${portal.shortLabel.toLowerCase()} workspace without opening the wrong dashboard.`;
        if (supportCopy) supportCopy.textContent = portal.subtitle;

        if (backLink) {
            backLink.setAttribute('href', this.getDashboardRoute(currentUser.role));
            const labelSpan = backLink.querySelector('span');
            if (labelSpan) {
                labelSpan.textContent = `Back to ${portal.links[0]?.label || 'Dashboard'}`;
            }
        }

        if (quickLinks && !quickLinks.innerHTML.trim()) {
            quickLinks.innerHTML = portal.links
                .filter((link) => link.href !== 'settings.html')
                .slice(0, 3)
                .map((link) => `
                    <a class="quick-link" href="${link.href}">
                        <span>${link.icon} ${link.label}</span>
                        <span>→</span>
                    </a>
                `)
                .join('');
        }
    },

    applyDynamicUserName() {
        const userName = this.getCurrentUser().name;

        document.querySelectorAll('.dynamic-user-name').forEach((el) => {
            el.textContent = userName;
        });
    }
};

document.addEventListener('DOMContentLoaded', () => {
    window.MedTrackerApp.initUiKit();
    window.MedTrackerApp.installNativeToastFallback();
    window.MedTrackerApp.setupMobileSidebar();
    window.MedTrackerApp.applyDynamicUserName();
    window.MedTrackerApp.bindGlobalLogoutFallback();
    window.MedTrackerApp.hydrateSettingsFallbackUi();

    // ---- ROLE SELECTION TO SIGNUP LOGIC ----
    const urlParams = new URLSearchParams(window.location.search);
    const role = urlParams.get('role');

    // If on signup page, set the role based on URL param
    if (document.getElementById('signupForm') && role) {
        document.getElementById('role').value = role;
        document.getElementById('roleDisplay').textContent = role;
        document.getElementById('genderGroup').style.display = 'block';
        document.getElementById('gender').setAttribute('required', 'required');

        // Show/Hide dynamic fields
        if (role === 'Health Worker') {
            document.getElementById('postGroup').style.display = 'block';
            document.getElementById('post').setAttribute('required', 'required');
        } else if (role === 'User') {
            document.getElementById('relationGroup').style.display = 'block';
            document.getElementById('relation').setAttribute('required', 'required');
            document.getElementById('dobGroup').style.display = 'block';
            document.getElementById('dob').setAttribute('required', 'required');
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
