/**
 * Messages Annonces.nc - Application unifiée
 * Responsive: Mobile → Tablette → Desktop
 */

// ========== CONFIG & STATE ==========
const API = 'api.php';

const state = {
    view: 'annonces',      // 'annonces', 'users', 'users_table'
    mode: 'accordion',     // 'accordion', '2col', '3col'
    annonce: null,         // { id, title, convCount }
    user: null,            // { id, name, convCount }
    conv: null,            // { id, title, msgCount, userId }
    userCardId: null,      // ID pour le modal
    allAnnonces: [],
    allUsers: []
};

// ========== HELPERS DOM ==========
const $ = (id) => document.getElementById(id);
const $$ = (sel) => document.querySelectorAll(sel);

function showSection(num) {
    $('section' + num).classList.remove('hidden');
    $('content' + num).classList.add('open');
    $('header' + num).classList.add('active');
}

function hideSection(num) {
    $('section' + num).classList.add('hidden');
    $('content' + num).classList.remove('open');
    $('header' + num).classList.remove('active');
}

function setSectionColor(num, color) {
    const section = $('section' + num);
    section.classList.remove('color-annonces', 'color-users', 'color-conversations', 'color-messages');
    if (color) section.classList.add('color-' + color);
}

function setContent(num, html) {
    $('content' + num).innerHTML = html;
}

function setHeader(num, { icon, title, badge, context }) {
    if (icon !== undefined && $('icon' + num)) $('icon' + num).textContent = icon;
    if (title !== undefined) $('title' + num).textContent = title;
    if (badge !== undefined) $('badge' + num).textContent = badge;
    if (context !== undefined) $('context' + num).innerHTML = context;
}

function setDescription(num, text) {
    const descEl = $('description' + num);
    const textEl = $('descriptionText' + num);
    if (!descEl || !textEl) return;

    if (text) {
        textEl.textContent = text;
        descEl.classList.remove('hidden');
        descEl.classList.add('collapsed');
    } else {
        textEl.textContent = '';
        descEl.classList.add('hidden');
    }
}

function toggleDescription(num = 2) {
    const desc = $('description' + num);
    if (desc) desc.classList.toggle('collapsed');
}

function escapeHtml(text) {
    if (!text) return '';
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

function debounce(func, wait) {
    let timeout;
    return (...args) => {
        clearTimeout(timeout);
        timeout = setTimeout(() => func(...args), wait);
    };
}

// ========== INIT ==========
document.addEventListener('DOMContentLoaded', () => {
    detectMode();
    window.addEventListener('resize', debounce(detectMode, 250));
    loadStats();
    loadAnnonces();
    initMobileFullscreen();
});

function initMobileFullscreen() {
    if (window.innerWidth > 599) return;

    fixMobileHeight();
    window.addEventListener('resize', fixMobileHeight);
    document.addEventListener('fullscreenchange', fixMobileHeight);

    setTimeout(() => {
        document.documentElement.requestFullscreen().catch(() => { });
    }, 100);
}

function fixMobileHeight() {
    const headerHeight = document.querySelector('.top-header')?.offsetHeight || 50;
    const available = window.innerHeight - headerHeight;
    document.documentElement.style.setProperty('--mobile-height', available + 'px');
}

function toggleFullscreen() {
    if (!document.fullscreenElement) {
        document.documentElement.requestFullscreen().catch(() => showFlash('❌ Plein écran impossible'));
    } else {
        document.exitFullscreen();
    }
}

// ========== MODE DETECTION ==========
function detectMode() {
    const width = window.innerWidth;
    const oldMode = state.mode;

    if (width >= 900) {
        state.mode = '3col';
    } else if (width >= 600) {
        state.mode = '2col';
    } else {
        state.mode = 'accordion';
    }

    document.body.classList.remove('mode-2col', 'mode-3col');
    if (state.mode !== 'accordion') {
        document.body.classList.add('mode-' + state.mode);
    }

    if (oldMode !== state.mode && state.view !== 'users_table') {
        updateLayout();
    }
}

function updateLayout() {
    showSection(1);

    if (state.annonce || state.user) {
        showSection(2);
    } else {
        hideSection(2);
    }

    if (state.conv) {
        showSection(3);
    } else {
        hideSection(3);
    }
}

// ========== VIEW TOGGLE ==========
async function loadStats() {
    try {
        const res = await fetch(API + '?action=stats');
        const stats = await res.json();
        $('btnAnnoncesCount').textContent = 'Annonces: ' + stats.annonces;
        $('btnUsersCount').textContent = 'Users: ' + stats.users;
    } catch (e) {
        console.error('Stats error:', e);
    }
}

function setView(view) {
    state.view = view;
    state.annonce = null;
    state.user = null;
    state.conv = null;

    $$('.toggle-btn').forEach(b => b.classList.remove('active'));

    if (view === 'users_table') {
        $('btnUsersTable').classList.add('active');
        $('mainContainer').style.display = 'none';
        $('tableView').style.display = 'block';
        loadUsersTable();
        return;
    }

    $('mainContainer').style.display = '';
    $('tableView').style.display = 'none';
    $('btn' + (view === 'annonces' ? 'Annonces' : 'Users')).classList.add('active');

    setSectionColor(1, view);
    setHeader(1, {
        icon: view === 'annonces' ? '📋' : '👥',
        title: view === 'annonces' ? 'Annonces' : 'Utilisateurs',
        context: ''
    });
    showSection(1);

    setSectionColor(2, 'conversations');
    setHeader(2, { title: 'Conversations', badge: '0', context: '' });
    setDescription(2, '');
    hideSection(2);

    setSectionColor(3, 'messages');
    setHeader(3, { title: 'Messages', badge: '0', context: '' });
    setDescription(3, '');
    hideSection(3);

    if (view === 'annonces') {
        loadAnnonces();
    } else {
        loadUsers();
    }
}

// ========== ACCORDION NAVIGATION ==========
function toggleSection(num) {
    if (state.mode === '3col') return;

    if (state.mode === '2col') {
        toggle2ColSection(num);
        return;
    }

    toggleAccordionSection(num);
}

function toggle2ColSection(num) {
    if (num === 1 || num === 2) {
        $('section1').classList.remove('collapsed');
        $('section2').classList.remove('collapsed');
        $('section3').classList.remove('expanded');
    } else if (num === 3) {
        const isExpanded = $('section3').classList.contains('expanded');
        if (isExpanded) {
            $('section1').classList.remove('collapsed');
            $('section2').classList.remove('collapsed');
            $('section3').classList.remove('expanded');
        } else {
            $('section1').classList.add('collapsed');
            $('section2').classList.add('collapsed');
            $('section3').classList.add('expanded');
        }
    }
}

function toggleAccordionSection(num) {
    const content = $('content' + num);
    const isOpen = content.classList.contains('open');

    if (!isOpen) {
        showSection(num);
        return;
    }

    if (num === 1) {
        state.annonce = null;
        state.user = null;
        state.conv = null;

        setHeader(1, {
            title: state.view === 'annonces' ? 'Annonces' : 'Utilisateurs',
            badge: state.view === 'annonces' ? state.allAnnonces.length : state.allUsers.length,
            context: ''
        });

        hideSection(2);
        hideSection(3);

        if (state.view === 'annonces') {
            displayAnnonces(state.allAnnonces);
        } else {
            displayUsers(state.allUsers);
        }
    } else if (num === 2) {
        state.conv = null;
        hideSection(3);
    }
}

// ========== ANNONCES ==========
async function loadAnnonces() {
    setContent(1, '<div class="loading">Chargement...</div>');

    try {
        const res = await fetch(API + '?action=annonces');
        const annonces = await res.json();
        if (annonces.error) throw new Error(annonces.error);

        state.allAnnonces = annonces;
        setHeader(1, { badge: annonces.length });
        displayAnnonces(annonces);
    } catch (e) {
        setContent(1, '<div class="error-state">Erreur: ' + e.message + '</div>');
    }
}

function displayAnnonces(annonces) {
    const html = annonces.map(a => `
        <div class="item color-annonces ${state.annonce?.id === a.id ? 'selected' : ''}" 
             onclick="selectAnnonce(${a.id}, '${escapeHtml(a.title)}', ${a.conv_count})">
            <div class="item-title">${escapeHtml(a.title)}</div>
            <div class="item-meta">
                ID: ${a.id} • ${a.site || 'annonces.nc'}
                <span class="badge">${a.conv_count} conv</span>
                <span class="badge">${a.total_messages || 0} msg</span>
            </div>
        </div>
    `).join('');

    setContent(1, html || '<div class="empty-state">Aucune annonce</div>');
}

async function selectAnnonce(id, title, convCount) {
    state.annonce = { id, title, convCount };
    state.user = null;
    state.conv = null;

    displayAnnonces(state.allAnnonces);

    if (state.mode === 'accordion') {
        setHeader(1, { title, badge: convCount + ' conv', context: 'ID: ' + id });
        $('content1').classList.remove('open');
        $('header1').classList.remove('active');
    }

    setSectionColor(2, 'conversations');
    setHeader(2, {
        title: state.mode === 'accordion' ? 'Conversations' : title,
        badge: convCount,
        context: state.mode === 'accordion' ? title : 'ID: ' + id
    });
    showSection(2);
    setContent(2, '<div class="loading">Chargement...</div>');

    hideSection(3);
    setDescription(3, '');

    try {
        const res = await fetch(API + '?action=conversations&annonce_id=' + id);
        const convs = await res.json();
        if (convs.error) throw new Error(convs.error);
        displayConversations(convs);

        // Afficher description annonce
        if (convs.length > 0 && convs[0].annonce_description) {
            setDescription(2, convs[0].annonce_description);
        } else {
            setDescription(2, '');
        }
    } catch (e) {
        setContent(2, '<div class="error-state">Erreur: ' + e.message + '</div>');
    }
}

// ========== USERS ==========
async function loadUsers() {
    setContent(1, '<div class="loading">Chargement...</div>');

    try {
        const res = await fetch(API + '?action=users');
        const users = await res.json();
        if (users.error) throw new Error(users.error);

        state.allUsers = users;
        setHeader(1, { badge: users.length });
        displayUsers(users);
    } catch (e) {
        setContent(1, '<div class="error-state">Erreur: ' + e.message + '</div>');
    }
}

function displayUsers(users) {
    const html = users.map(u => {
        const displayName = u.name || u.user_name;
        const subtitle = u.name ? 'ID: ' + u.user_id + ' • ' + u.user_name : 'ID: ' + u.user_id;

        return `
            <div class="item color-users ${state.user?.id === u.user_id ? 'selected' : ''}" 
                 onclick="selectUser(${u.user_id}, '${escapeHtml(displayName)}', ${u.conv_count})">
                <div class="item-title">${escapeHtml(displayName)}</div>
                <div class="item-meta">
                    ${subtitle}
                    <span class="badge">${u.conv_count} conv</span>
                    <span class="badge">${u.total_messages || 0} msg</span>
                </div>
                <div class="item-actions">
                    <button class="item-action" onclick="event.stopPropagation(); openUserModal(${u.user_id})">⚙️ Profil</button>
                    <button class="item-action" onclick="event.stopPropagation(); openExternal(${u.user_id})">🔗 Annonces.nc</button>
                </div>
            </div>
        `;
    }).join('');

    setContent(1, html || '<div class="empty-state">Aucun utilisateur</div>');
}

async function selectUser(id, name, convCount) {
    state.user = { id, name, convCount };
    state.annonce = null;
    state.conv = null;

    displayUsers(state.allUsers);

    if (state.mode === 'accordion') {
        setHeader(1, { title: name, badge: convCount + ' conv', context: 'ID: ' + id });
        $('content1').classList.remove('open');
        $('header1').classList.remove('active');
    }

    setSectionColor(2, 'conversations');
    setHeader(2, {
        title: state.mode === 'accordion' ? 'Conversations' : name,
        badge: convCount,
        context: state.mode === 'accordion' ? name : 'ID: ' + id
    });
    showSection(2);
    setContent(2, '<div class="loading">Chargement...</div>');
    setDescription(2, '');

    hideSection(3);
    setDescription(3, '');

    try {
        const res = await fetch(API + '?action=conversations&user_id=' + id);
        const convs = await res.json();
        if (convs.error) throw new Error(convs.error);
        displayConversations(convs);
    } catch (e) {
        setContent(2, '<div class="error-state">Erreur: ' + e.message + '</div>');
    }
}

// ========== CONVERSATIONS ==========
function displayConversations(convs) {
    const html = convs.map(c => {
        const title = state.view === 'annonces'
            ? (c.user_display_name || c.user_name)
            : (c.annonce_title || 'Annonce ' + c.annonce_id);
        const meta = state.view === 'annonces' ? 'ID: ' + c.user_id : 'ID: ' + c.annonce_id;

        const actions = state.view === 'annonces' ? `
            <div class="item-actions">
                <button class="item-action" onclick="event.stopPropagation(); openUserModal(${c.user_id})">⚙️</button>
                <button class="item-action" onclick="event.stopPropagation(); openExternal(${c.user_id})">🔗</button>
            </div>
        ` : '';

        return `
            <div class="item color-conversations ${state.conv?.id === c.conversation_id ? 'selected' : ''}" 
                 onclick="selectConversation(${c.conversation_id}, '${escapeHtml(title)}', ${c.messages_count}, ${c.user_id})">
                <div class="item-title">${escapeHtml(title)}</div>
                <div class="item-meta">
                    ${meta} • ${c.last_message_date || 'Pas de date'}
                    <span class="badge">${c.messages_count} msg</span>
                </div>
                ${actions}
            </div>
        `;
    }).join('');

    setContent(2, html || '<div class="empty-state">Aucune conversation</div>');
}

async function selectConversation(id, title, msgCount, userId) {
    state.conv = { id, title, msgCount, userId };

    // Récupérer les conversations pour refresh + description
    let convs = [];
    if (state.annonce) {
        const res = await fetch(API + '?action=conversations&annonce_id=' + state.annonce.id);
        convs = await res.json();
    } else if (state.user) {
        const res = await fetch(API + '?action=conversations&user_id=' + state.user.id);
        convs = await res.json();
    }
    displayConversations(convs);

    // Trouver la conversation sélectionnée
    const selectedConv = convs.find(c => c.conversation_id === id);

    if (state.mode === 'accordion') {
        setHeader(2, { title, badge: msgCount + ' msg' });
        $('content2').classList.remove('open');
        $('header2').classList.remove('active');
    }

    if (state.mode === '2col') {
        $('section1').classList.add('collapsed');
        $('section2').classList.add('collapsed');
        $('section3').classList.add('expanded');
    }

    setSectionColor(3, 'messages');
    if (state.view === 'users') {
        $('section3').classList.add('color-users');
    }

    let contextText = state.mode === 'accordion' ? title : '';
    if (state.annonce) contextText += (contextText ? ' • ' : '') + state.annonce.title;
    if (state.user) contextText += (contextText ? ' • ' : '') + state.user.name;

    const actionButtons = `
        <span class="header-actions">
            <button class="header-action" onclick="event.stopPropagation(); openUserModal(${userId})">⚙️</button>
            <button class="header-action" onclick="event.stopPropagation(); openExternal(${userId})">🔗</button>
        </span>
    `;

    setHeader(3, {
        title: state.mode === 'accordion' ? 'Messages' : title,
        badge: msgCount,
        context: contextText + ' ' + actionButtons
    });
    showSection(3);
    setContent(3, '<div class="loading">Chargement...</div>');

    // Afficher description annonce dans section 3
    if (selectedConv?.annonce_description) {
        setDescription(3, selectedConv.annonce_description);
    } else {
        setDescription(3, '');
    }

    if (state.mode === '3col' && userId) {
        openUserModal(userId);
    }

    try {
        const res = await fetch(API + '?action=messages&conversation_id=' + id);
        let messages = await res.json();
        if (messages.error) throw new Error(messages.error);
        if (!Array.isArray(messages)) messages = [];
        displayMessages(messages);
    } catch (e) {
        setContent(3, '<div class="error-state">Erreur: ' + e.message + '</div>');
    }
}

function displayMessages(messages) {
    const html = messages.map(m => {
        let imagesHtml = '';
        if (m.images?.length > 0) {
            imagesHtml = '<div class="message-images">' +
                m.images.map(img => {
                    const src = img.local_path || img.full_url;
                    return `<img src="${src}" draggable="true" ondragstart="dragImage(event, '${src}')" onclick="openLightbox('${src}')">`;
                }).join('') + '</div>';
        }

        return `
            <div class="message ${m.from_me ? 'from-me' : 'from-them'}">
                <div class="message-text">${escapeHtml(m.message_text)}</div>
                ${imagesHtml}
                <div class="message-date">${m.message_date || ''}</div>
            </div>
        `;
    }).join('');

    setContent(3, '<div class="messages-container">' + (html || '<div class="empty-state">Aucun message</div>') + '</div>');
    $('content3').scrollTop = $('content3').scrollHeight;
}

// ========== TABLE VIEW ==========
async function loadUsersTable() {
    $('tableBody').innerHTML = '<tr><td colspan="9" class="loading">Chargement...</td></tr>';

    try {
        const res = await fetch(API + '?action=users');
        const users = await res.json();
        if (users.error) throw new Error(users.error);
        displayUsersTable(users);
    } catch (e) {
        $('tableBody').innerHTML = '<tr><td colspan="9" class="error-state">Erreur: ' + e.message + '</td></tr>';
    }
}

function displayUsersTable(users) {
    const html = users.map(u => `
        <tr data-user-id="${u.user_id}">
            <td data-label="ID">${u.user_id}</td>
            <td data-label="Photo">
                ${u.photo_url ? `<img src="${u.photo_url}" style="width:40px;height:40px;border-radius:50%;object-fit:cover;">` : '👤'}
            </td>
            <td data-label="Nom">
                <input type="text" value="${escapeHtml(u.name || '')}" onchange="updateUserField(${u.user_id}, 'name', this.value)">
            </td>
            <td data-label="Téléphone">
                <input type="text" value="${escapeHtml(u.phone || '')}" onchange="updateUserField(${u.user_id}, 'phone', this.value)">
            </td>
            <td data-label="Facebook">
                <input type="text" value="${escapeHtml(u.facebook || '')}" onchange="updateUserField(${u.user_id}, 'facebook', this.value)">
            </td>
            <td data-label="WhatsApp">
                <input type="text" value="${escapeHtml(u.whatsapp || '')}" onchange="updateUserField(${u.user_id}, 'whatsapp', this.value)">
            </td>
            <td data-label="Commentaire">
                <textarea onchange="updateUserField(${u.user_id}, 'commentaire', this.value)">${escapeHtml(u.commentaire || '')}</textarea>
            </td>
            <td data-label="Conv"><span class="badge">${u.conv_count}</span></td>
            <td data-label="Actions" class="table-actions">
                <button class="btn-table primary" onclick="goToUserConversations(${u.user_id})">📝 Voir</button>
            </td>
        </tr>
    `).join('');

    $('tableBody').innerHTML = html || '<tr><td colspan="9" class="empty-state">Aucun utilisateur</td></tr>';
}

async function updateUserField(userId, field, value) {
    try {
        await fetch(API + '?action=update_user_field', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ user_id: userId, field, value })
        });
        showFlash('✓ ' + field + ' mis à jour');
    } catch (e) {
        showFlash('❌ Erreur mise à jour');
    }
}

function goToUserConversations(userId) {
    setView('users');
    setTimeout(() => {
        const user = state.allUsers.find(u => u.user_id === userId);
        if (user) selectUser(userId, user.name || user.user_name, user.conv_count);
    }, 100);
}

// ========== USER MODAL ==========
async function openUserModal(userId) {
    state.userCardId = userId;

    try {
        const res = await fetch(API + '?action=conversations&user_id=' + userId);
        const convs = await res.json();
        if (convs.error || convs.length === 0) throw new Error('Utilisateur introuvable');

        const user = convs[0];
        const photoDiv = $('modalUserPhoto');

        photoDiv.innerHTML = user.photo_url
            ? '<img src="' + user.photo_url + '">'
            : '<span class="placeholder">👤</span>';

        $('modalUserName').value = user.user_display_name || user.name || '';
        $('modalUserPhone').value = user.phone || '';
        $('modalUserFacebook').value = user.facebook || '';
        $('modalUserWhatsapp').value = user.whatsapp || '';
        $('modalUserComment').value = user.commentaire || '';
        $('modalUserMeta').textContent = 'ID: ' + user.user_id + ' • ' + user.user_name;

        $('userModal').classList.add('active');
        setupModalPhotoDragDrop();

        if (state.mode === '3col') setupModalDrag();
    } catch (e) {
        showFlash('Erreur: ' + e.message);
    }
}

function closeUserModal(event) {
    if (state.mode === '3col' && event?.target.id === 'userModal') return;
    if (!event || event.target.id === 'userModal' || event.target.classList.contains('modal-close')) {
        $('userModal').classList.remove('active');
        state.userCardId = null;
    }
}

async function saveUserProfile() {
    if (!state.userCardId) return;

    const data = {
        user_id: state.userCardId,
        name: $('modalUserName').value,
        phone: $('modalUserPhone').value,
        facebook: $('modalUserFacebook').value,
        whatsapp: $('modalUserWhatsapp').value,
        commentaire: $('modalUserComment').value
    };

    try {
        await fetch(API + '?action=update_user_profile', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(data)
        });

        showFlash('✓ Profil enregistré');

        const idx = state.allUsers.findIndex(u => u.user_id == state.userCardId);
        if (idx !== -1) Object.assign(state.allUsers[idx], data);

        if (state.mode !== '3col') closeUserModal();
        if (state.view === 'users') loadUsers();
        else if (state.view === 'users_table') loadUsersTable();
    } catch (e) {
        showFlash('Erreur sauvegarde');
    }
}

// ========== MODAL DRAG ==========
function setupModalDrag() {
    const modal = document.querySelector('.modal-card');
    const header = document.querySelector('.modal-header');
    if (!modal || !header) return;

    let isDragging = false, startX, startY, modalX, modalY;

    const newHeader = header.cloneNode(true);
    header.parentNode.replaceChild(newHeader, header);

    newHeader.addEventListener('mousedown', (e) => {
        if (e.target.classList.contains('modal-close')) return;
        isDragging = true;
        startX = e.clientX;
        startY = e.clientY;
        const rect = modal.getBoundingClientRect();
        modalX = rect.left;
        modalY = rect.top;
        modal.style.transition = 'none';
        e.preventDefault();
    });

    document.addEventListener('mousemove', (e) => {
        if (!isDragging) return;
        const newX = Math.max(0, Math.min(modalX + e.clientX - startX, window.innerWidth - modal.offsetWidth));
        const newY = Math.max(0, Math.min(modalY + e.clientY - startY, window.innerHeight - modal.offsetHeight));
        modal.style.left = newX + 'px';
        modal.style.top = newY + 'px';
        modal.style.right = 'auto';
        modal.style.bottom = 'auto';
    });

    document.addEventListener('mouseup', () => {
        if (isDragging) {
            isDragging = false;
            modal.style.transition = '';
        }
    });
}

function setupModalPhotoDragDrop() {
    const photoDiv = $('modalUserPhoto');
    const newPhotoDiv = photoDiv.cloneNode(true);
    photoDiv.parentNode.replaceChild(newPhotoDiv, photoDiv);

    newPhotoDiv.addEventListener('dragover', (e) => {
        e.preventDefault();
        newPhotoDiv.style.borderColor = 'var(--color-users)';
        newPhotoDiv.style.background = '#454545';
    });

    newPhotoDiv.addEventListener('dragleave', () => {
        newPhotoDiv.style.borderColor = '';
        newPhotoDiv.style.background = '';
    });

    newPhotoDiv.addEventListener('drop', async (e) => {
        e.preventDefault();
        newPhotoDiv.style.borderColor = '';
        newPhotoDiv.style.background = '';

        const photoUrl = e.dataTransfer.getData('text/plain');
        if (!photoUrl || !state.userCardId) return;

        newPhotoDiv.innerHTML = '<img src="' + photoUrl + '">';

        try {
            await fetch(API + '?action=update_user_photo', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ user_id: state.userCardId, photo_url: photoUrl })
            });
            showFlash('✓ Photo mise à jour');

            const idx = state.allUsers.findIndex(u => u.user_id == state.userCardId);
            if (idx !== -1) state.allUsers[idx].photo_url = photoUrl;
            if (state.view === 'users') displayUsers(state.allUsers);
        } catch (e) {
            showFlash('❌ Erreur sauvegarde photo');
        }
    });
}

// ========== UTILITIES ==========
function dragImage(event, src) {
    event.dataTransfer.setData('text/plain', src);
}

function openExternal(userId) {
    window.open('https://annonces.nc/dashboard/conversations?find_user=' + userId, 'annonces_nc');
    showFlash('🔍 Recherche Utilisateur ' + userId + '...');
}

function openLightbox(src) {
    $('lightboxImg').src = src;
    $('lightbox').classList.add('active');
}

function closeLightbox() {
    $('lightbox').classList.remove('active');
}

function showFlash(message, duration = 2000) {
    if (message.includes('❌') || message.includes('Erreur')) duration = 10000;
    const flash = document.createElement('div');
    flash.className = 'flash';
    flash.textContent = message;
    $('flashContainer').appendChild(flash);
    setTimeout(() => flash.remove(), duration);
}