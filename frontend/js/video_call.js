let videoContacts = [];
let selectedContact = null;
let jitsiApi = null;
let currentRole = '';

const contactList = document.getElementById('videoContactList');
const callTitle = document.getElementById('videoCallTitle');
const callSubtitle = document.getElementById('videoCallSubtitle');
const meetContainer = document.getElementById('videoMeetFrame');
const leaveButton = document.getElementById('videoLeaveBtn');
const messageThread = document.getElementById('messageThread');
const messageInput = document.getElementById('messageInput');
const appointmentForm = document.getElementById('appointmentForm');
const appointmentList = document.getElementById('appointmentList');
const doctorAvailabilityPanel = document.getElementById('doctorAvailabilityPanel');

function getRequestedContactId() {
    return new URLSearchParams(window.location.search).get('contact_id') || '';
}

function isMobileDevice() {
    return /Android|iPhone|iPad|iPod|Mobile/i.test(navigator.userAgent || '');
}

function getJitsiRoomUrl(roomName) {
    return `https://meet.jit.si/${encodeURIComponent(roomName)}`;
}

function isDoctorRole() {
    return currentRole === 'health worker' || currentRole === 'doctor';
}

function getInitials(name) {
    return String(name || 'U').trim().split(/\s+/).slice(0, 2).map((part) => part.charAt(0).toUpperCase()).join('') || 'U';
}

function escapeHtml(value) {
    const div = document.createElement('div');
    div.textContent = String(value ?? '');
    return div.innerHTML;
}

function formatDateTime(value) {
    if (!value) return 'Time not set';
    const date = new Date(String(value).replace(' ', 'T'));
    if (Number.isNaN(date.getTime())) return value;
    return date.toLocaleString([], { month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit' });
}

function getPresenceLabel(contact) {
    if (contact.role !== 'Health Worker') {
        return contact.subtitle || 'Linked patient';
    }

    const presence = contact.presence || {};
    const status = presence.status === 'active' ? 'Active now' : 'Inactive';
    const note = presence.note ? ` - ${presence.note}` : '';
    return `${status}${note}`;
}

function renderVideoContacts() {
    if (!contactList) return;

    if (!videoContacts.length) {
        contactList.innerHTML = window.MedTrackerApp.getEmptyStateHtml({
            icon: '☎',
            title: 'No video contacts yet',
            message: 'Link with a doctor or patient first, then consultation calls will appear here.'
        });
        return;
    }

    contactList.innerHTML = videoContacts.map((contact) => {
        const isActiveDoctor = contact.role === 'Health Worker' && contact.presence?.status === 'active';
        const unread = Number(contact.unread_count || 0);
        return `
            <button type="button" class="video-contact${selectedContact?.id === contact.id ? ' active' : ''}" data-contact-id="${escapeHtml(contact.id)}">
                <span class="video-contact-avatar">${escapeHtml(getInitials(contact.name))}</span>
                <span class="video-contact-copy">
                    <strong>${escapeHtml(contact.name || 'Contact')}${unread ? ` (${unread})` : ''}</strong>
                    <small>${escapeHtml(getPresenceLabel(contact))}</small>
                </span>
                <span class="video-status-dot${isActiveDoctor ? ' active' : ''}" title="${isActiveDoctor ? 'Active' : 'Inactive'}"></span>
            </button>
        `;
    }).join('');
}

function selectVideoContact(contactId) {
    selectedContact = videoContacts.find((contact) => String(contact.id) === String(contactId)) || videoContacts[0] || null;
    renderVideoContacts();

    if (!selectedContact) {
        callTitle.textContent = 'Choose a contact';
        callSubtitle.textContent = 'Your call room will appear here.';
        leaveButton.disabled = true;
        return;
    }

    callTitle.textContent = `Video call with ${selectedContact.name || 'contact'}`;
    callSubtitle.textContent = `Room: ${selectedContact.room_name}`;
    appointmentForm.style.display = isDoctorRole() ? 'grid' : 'none';
    loadMessages();
    loadAppointments();
}

function joinSelectedCall() {
    if (!selectedContact) {
        window.MedTrackerApp.showToast('Choose a contact before starting a call.', 'warning');
        return;
    }

    if (isMobileDevice()) {
        window.open(getJitsiRoomUrl(selectedContact.room_name), '_blank', 'noopener');
        window.MedTrackerApp.showToast('Opening the video room directly for mobile.', 'info');
        return;
    }

    if (jitsiApi) jitsiApi.dispose();

    jitsiApi = new JitsiMeetExternalAPI('meet.jit.si', {
        roomName: selectedContact.room_name,
        parentNode: meetContainer,
        width: '100%',
        height: '100%',
        userInfo: { displayName: window.MedTrackerApp.getCurrentUser().name || 'MedTracker User' },
        configOverwrite: { prejoinPageEnabled: true },
        interfaceConfigOverwrite: { SHOW_JITSI_WATERMARK: false }
    });

    leaveButton.disabled = false;
}

function leaveSelectedCall() {
    if (jitsiApi) {
        jitsiApi.dispose();
        jitsiApi = null;
    }

    meetContainer.innerHTML = `
        <div class="video-placeholder">
            <h3>Call ended</h3>
            <p>Select a contact and start the call again whenever you are ready.</p>
        </div>
    `;
    leaveButton.disabled = true;
}

async function loadVideoContacts(keepSelection = false) {
    try {
        const response = await fetch('../backend/api/get_video_contacts.php');
        const result = await response.json();
        if (!result.success) throw new Error(result.message || 'Unable to load video contacts.');

        const previousId = selectedContact?.id || '';
        videoContacts = Array.isArray(result.data?.contacts) ? result.data.contacts : [];
        selectVideoContact(keepSelection ? previousId : getRequestedContactId());
    } catch (error) {
        contactList.innerHTML = window.MedTrackerApp.getEmptyStateHtml({
            icon: '!',
            title: 'Video contacts unavailable',
            message: error.message || 'Please try again after logging in.'
        });
    }
}

async function loadMessages() {
    if (!selectedContact) return;

    try {
        const response = await fetch(`../backend/api/get_consultation_messages.php?contact_id=${encodeURIComponent(selectedContact.id)}`);
        const result = await response.json();
        if (!result.success) throw new Error(result.message || 'Unable to load messages.');

        const currentUserId = window.MedTrackerApp.getCurrentUser().userId;
        const messages = Array.isArray(result.data) ? result.data : [];
        if (!messages.length) {
            messageThread.innerHTML = '<p style="color:var(--text-muted);">No messages yet. Send the first consultation note.</p>';
            return;
        }

        messageThread.innerHTML = messages.map((message) => {
            const mine = String(message.sender_id) === String(currentUserId);
            return `
                <div class="message-row${mine ? ' mine' : ''}">
                    <div class="message-bubble">
                        <strong>${escapeHtml(mine ? 'You' : (message.sender_name || selectedContact.name || 'Contact'))}</strong>
                        <div>${escapeHtml(message.message)}</div>
                        <small>${escapeHtml(formatDateTime(message.created_at))}</small>
                    </div>
                </div>
            `;
        }).join('');
        messageThread.scrollTop = messageThread.scrollHeight;
    } catch (error) {
        messageThread.innerHTML = `<p style="color:var(--red);">${escapeHtml(error.message || 'Unable to load messages.')}</p>`;
    }
}

async function sendMessage() {
    if (!selectedContact) {
        window.MedTrackerApp.showToast('Choose a contact first.', 'warning');
        return;
    }

    const message = messageInput.value.trim();
    if (!message) return;

    try {
        const response = await fetch('../backend/api/send_consultation_message.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ receiver_id: selectedContact.id, message })
        });
        const result = await response.json();
        if (!result.success) throw new Error(result.message || 'Unable to send message.');

        messageInput.value = '';
        await loadMessages();
        await loadVideoContacts(true);
    } catch (error) {
        window.MedTrackerApp.showToast(error.message || 'Unable to send message.', 'error');
    }
}

async function setDoctorPresence(status) {
    try {
        const response = await fetch('../backend/api/set_doctor_presence.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ status, note: document.getElementById('availabilityNote')?.value || '' })
        });
        const result = await response.json();
        if (!result.success) throw new Error(result.message || 'Unable to update availability.');

        window.MedTrackerApp.showToast(status === 'active' ? 'You are active for consultations.' : 'You are marked inactive.', 'success');
        await loadVideoContacts(true);
    } catch (error) {
        window.MedTrackerApp.showToast(error.message || 'Unable to update availability.', 'error');
    }
}

async function createAppointment(event) {
    event.preventDefault();
    if (!selectedContact || !isDoctorRole()) return;

    const scheduledAt = document.getElementById('appointmentTime').value;
    const note = document.getElementById('appointmentNote').value.trim();
    if (!scheduledAt) {
        window.MedTrackerApp.showToast('Choose appointment date and time.', 'warning');
        return;
    }

    try {
        const response = await fetch('../backend/api/create_video_appointment.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ patient_id: selectedContact.id, scheduled_at: scheduledAt, note })
        });
        const result = await response.json();
        if (!result.success) throw new Error(result.message || 'Unable to create appointment.');

        document.getElementById('appointmentTime').value = '';
        document.getElementById('appointmentNote').value = '';
        const summary = result.notification_summary ? ` ${result.notification_summary}` : '';
        window.MedTrackerApp.showToast(`Video appointment set.${summary}`, 'success');
        await loadAppointments();
    } catch (error) {
        window.MedTrackerApp.showToast(error.message || 'Unable to set appointment.', 'error');
    }
}

async function loadAppointments() {
    if (!selectedContact) return;

    try {
        const response = await fetch(`../backend/api/get_video_appointments.php?contact_id=${encodeURIComponent(selectedContact.id)}`);
        const result = await response.json();
        if (!result.success) throw new Error(result.message || 'Unable to load appointments.');

        const appointments = Array.isArray(result.data) ? result.data : [];
        if (!appointments.length) {
            appointmentList.innerHTML = '<p style="color:var(--text-muted);">No video appointments set yet.</p>';
            return;
        }

        appointmentList.innerHTML = appointments.map((appointment) => `
            <div class="appointment-item">
                <strong>${escapeHtml(formatDateTime(appointment.scheduled_at))}</strong>
                <small>${escapeHtml(appointment.note || 'Video consultation')}</small>
            </div>
        `).join('');
    } catch (error) {
        appointmentList.innerHTML = `<p style="color:var(--red);">${escapeHtml(error.message || 'Unable to load appointments.')}</p>`;
    }
}

document.addEventListener('DOMContentLoaded', () => {
    currentRole = window.MedTrackerApp.normalizeRole(window.MedTrackerApp.getCurrentUser().role);
    if (!window.MedTrackerApp.protectRoute(['Health Worker', 'Doctor', 'User', 'Patient'])) return;

    window.MedTrackerApp.renderRoleNav(document.getElementById('videoRoleNav'), 'video_call.html');
    document.getElementById('videoRoleLabel').textContent = isDoctorRole() ? 'Healthcare Professional' : 'Patient Portal';
    doctorAvailabilityPanel.style.display = isDoctorRole() ? 'block' : 'none';
    appointmentForm.style.display = isDoctorRole() ? 'grid' : 'none';

    contactList?.addEventListener('click', (event) => {
        const button = event.target.closest('[data-contact-id]');
        if (button) selectVideoContact(button.dataset.contactId);
    });
    document.getElementById('videoJoinBtn')?.addEventListener('click', joinSelectedCall);
    leaveButton?.addEventListener('click', leaveSelectedCall);
    document.getElementById('messageSendBtn')?.addEventListener('click', sendMessage);
    messageInput?.addEventListener('keydown', (event) => {
        if (event.key === 'Enter') {
            event.preventDefault();
            sendMessage();
        }
    });
    document.getElementById('setActiveBtn')?.addEventListener('click', () => setDoctorPresence('active'));
    document.getElementById('setInactiveBtn')?.addEventListener('click', () => setDoctorPresence('inactive'));
    appointmentForm?.addEventListener('submit', createAppointment);

    loadVideoContacts();
    window.setInterval(() => {
        if (selectedContact) loadVideoContacts(true);
    }, 8000);
});
