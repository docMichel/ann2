/**
 * Messages Annonces.nc - Application unifiée
 * Responsive: Mobile → Tablette → Desktop
 */

const API = 'api.php';

let currentView = 'annonces';
let currentAnnonce = null;
let currentUser = null;
let currentConv = null;
let currentUserCardId = null;
let currentMode = 'accordion'; // 'accordion', '2col', '3col'

let allAnnonces = [];
let allUsers = [];

// ========== INITIALISATION ==========

document.addEventListener('DOMContentLoaded', () => {
    detectMode();
    window.addEventListener('resize', debounce(detectMode, 250));
    loadStats();
    loadAnnonces();

    // FULLSCREEN: Calcul hauteur mobile + activation auto
    if (window.innerWidth <= 599) {
        fixMobileHeight();
        window.addEventListener('resize', fixMobileHeight);
        document.addEventListener('fullscreenchange', fixMobileHeight);

        // Tentative plein écran automatique (silencieux)
        setTimeout(() => {
            document.documentElement.requestFullscreen().catch(() => {
                // Pas grave si bloqué, on continue normalement
            });
        }, 100);
    }
});

function debounce(func, wait) {
    let timeout;
    return function (...args) {
        clearTimeout(timeout);
        timeout = setTimeout(() => func.apply(this, args), wait);
    };
}

// ========== FULLSCREEN ==========

function fixMobileHeight() {
    const realHeight = window.innerHeight;
    const headerHeight = document.querySelector('.top-header').offsetHeight;
    const availableHeight = realHeight - headerHeight;
    document.documentElement.style.setProperty('--mobile-height', availableHeight + 'px');
}

function toggleFullscreen() {
    if (!document.fullscreenElement) {
        document.documentElement.requestFullscreen().catch(() => {
            showFlash('❌ Plein écran impossible');
        });
    } else {
        document.exitFullscreen();
    }
}

// ========== MODE DETECTION ==========

function detectMode() {
    const width = window.innerWidth;
    const oldMode = currentMode;

    if (width >= 900) {
        currentMode = '3col';
        document.body.classList.remove('mode-2col');
        document.body.classList.add('mode-3col');
    } else if (width >= 600) {
        currentMode = '2col';
        document.body.classList.remove('mode-3col');
        document.body.classList.add('mode-2col');
    } else {
        currentMode = 'accordion';
        document.body.classList.remove('mode-2col', 'mode-3col');
    }

    if (oldMode !== currentMode && currentView !== 'users_table') {
        applyModeLayout();
    }
}

function applyModeLayout() {
    // Mode accordéon : géré par toggleSection
    if (currentMode === 'accordion') {
        if (!currentAnnonce && !currentUser) {
            document.getElementById('section2').classList.add('hidden');
        }
        if (!currentConv) {
            document.getElementById('section3').classList.add('hidden');
        }
        return;
    }

    // Mode 2col / 3col
    document.getElementById('section1').classList.remove('hidden');
    document.getElementById('content1').classList.add('open');
    document.getElementById('header1').classList.add('active');

    // Section 2 : visible seulement si annonce ou user sélectionné
    if (currentAnnonce || currentUser) {
        document.getElementById('section2').classList.remove('hidden');
        document.getElementById('content2').classList.add('open');
        document.getElementById('header2').classList.add('active');
    } else {
        document.getElementById('section2').classList.add('hidden');
    }

    // Section 3 : visible seulement si conversation sélectionnée
    if (currentConv) {
        document.getElementById('section3').classList.remove('hidden');
        document.getElementById('content3').classList.add('open');
        document.getElementById('header3').classList.add('active');
    } else {
        document.getElementById('section3').classList.add('hidden');
    }
}
// ========== VIEW TOGGLE ==========

async function loadStats() {
    try {
        const res = await fetch(API + '?action=stats');
        const stats = await res.json();
        document.getElementById('btnAnnoncesCount').textContent = 'Annonces: ' + stats.annonces;
        document.getElementById('btnUsersCount').textContent = 'Users: ' + stats.users;
    } catch (e) {
        console.error('Stats error:', e);
    }
}

function setView(view) {
    currentView = view;

    // Reset boutons
    document.querySelectorAll('.toggle-btn').forEach(b => b.classList.remove('active'));

    if (view === 'users_table') {
        document.getElementById('btnUsersTable').classList.add('active');
        document.getElementById('mainContainer').style.display = 'none';
        document.getElementById('tableView').style.display = 'block';
        loadUsersTable();
        return;
    }

    // Vue normale (annonces ou users)
    document.getElementById('mainContainer').style.display = '';
    document.getElementById('tableView').style.display = 'none';

    if (view === 'annonces') {
        document.getElementById('btnAnnonces').classList.add('active');
    } else {
        document.getElementById('btnUsers').classList.add('active');
    }

    currentAnnonce = null;
    currentUser = null;
    currentConv = null;

    // Reset sections avec couleurs
    const section1 = document.getElementById('section1');
    section1.className = 'accordion-section color-' + view;

    document.getElementById('icon1').textContent = view === 'annonces' ? '📋' : '👥';
    document.getElementById('title1').textContent = view === 'annonces' ? 'Annonces' : 'Utilisateurs';
    document.getElementById('context1').textContent = '';
    document.getElementById('content1').classList.add('open');
    document.getElementById('header1').classList.add('active');

    const section2 = document.getElementById('section2');
    section2.className = 'accordion-section hidden color-conversations';

    document.getElementById('title2').textContent = 'Conversations';
    document.getElementById('context2').textContent = currentMode === 'accordion' ? '' : 'Sélectionnez un élément';
    document.getElementById('badge2').textContent = '0';

    const section3 = document.getElementById('section3');
    section3.className = 'accordion-section hidden color-messages';

    document.getElementById('title3').textContent = 'Messages';
    document.getElementById('context3').textContent = currentMode === 'accordion' ? '' : 'Sélectionnez une conversation';
    document.getElementById('badge3').textContent = '0';

    // Cacher sections 2 et 3 quel que soit le mode
    section2.classList.add('hidden');
    section3.classList.add('hidden');

    if (currentMode === 'accordion') {
        section2.classList.add('hidden');
        section3.classList.add('hidden');
    } else {
        document.getElementById('content2').innerHTML = '<div class="empty-state">← Sélectionnez une annonce ou un utilisateur</div>';
        document.getElementById('content3').innerHTML = '<div class="empty-state">← Sélectionnez une conversation</div>';
    }

    if (view === 'annonces') {
        loadAnnonces();
    } else {
        loadUsers();
    }
}

// ========== ACCORDION NAVIGATION ==========

function toggleSection(num) {
    if (currentMode === '2col') {
        // En mode 2col, cliquer sur section 1 ou 2 = replier section 3
        if (num === 1 || num === 2) {
            document.getElementById('section1').classList.remove('collapsed');
            document.getElementById('section2').classList.remove('collapsed');
            document.getElementById('section3').classList.remove('expanded');
        }
        // Cliquer sur section 3 = déplier section 3 et replier 1 et 2
        if (num === 3) {
            const isExpanded = document.getElementById('section3').classList.contains('expanded');
            if (isExpanded) {
                // Re-cliquer sur section 3 = tout déplier
                document.getElementById('section1').classList.remove('collapsed');
                document.getElementById('section2').classList.remove('collapsed');
                document.getElementById('section3').classList.remove('expanded');
            } else {
                // Première fois = déplier section 3
                document.getElementById('section1').classList.add('collapsed');
                document.getElementById('section2').classList.add('collapsed');
                document.getElementById('section3').classList.add('expanded');
            }
        }
        return;
    }

    if (currentMode === '3col') return;

    // Mode accordéon
    const header = document.getElementById('header' + num);
    const content = document.getElementById('content' + num);
    const isOpen = content.classList.contains('open');

    if (isOpen) {
        content.classList.remove('open');
        header.classList.remove('active');

        if (num === 1) {
            document.getElementById('title1').textContent = currentView === 'annonces' ? 'Annonces' : 'Utilisateurs';
            document.getElementById('badge1').textContent = currentView === 'annonces' ? allAnnonces.length : allUsers.length;
            document.getElementById('context1').textContent = '';
            content.classList.add('open');
            header.classList.add('active');

            document.getElementById('section2').classList.add('hidden');
            document.getElementById('section3').classList.add('hidden');
            currentAnnonce = null;
            currentUser = null;
            currentConv = null;

            if (currentView === 'annonces') {
                displayAnnonces(allAnnonces);
            } else {
                displayUsers(allUsers);
            }
        } else if (num === 2) {
            content.classList.add('open');
            header.classList.add('active');
            document.getElementById('section3').classList.add('hidden');
            currentConv = null;
        }
    } else {
        content.classList.add('open');
        header.classList.add('active');
    }
}

// ========== ANNONCES ==========

async function loadAnnonces() {
    document.getElementById('content1').innerHTML = '<div class="loading">Chargement...</div>';

    try {
        const res = await fetch(API + '?action=annonces');
        const annonces = await res.json();
        if (annonces.error) throw new Error(annonces.error);

        allAnnonces = annonces;
        document.getElementById('badge1').textContent = annonces.length;
        displayAnnonces(annonces);
    } catch (e) {
        document.getElementById('content1').innerHTML = '<div class="error-state">Erreur: ' + e.message + '</div>';
    }
}

function displayAnnonces(annonces) {
    const html = annonces.map(a => `
        <div class="item color-annonces ${currentAnnonce && currentAnnonce.id === a.id ? 'selected' : ''}" onclick="selectAnnonce(${a.id}, '${escapeHtml(a.title)}', ${a.conv_count})">
            <div class="item-title">${escapeHtml(a.title)}</div>
            <div class="item-meta">
                ID: ${a.id} • ${a.site || 'annonces.nc'}
                <span class="badge">${a.conv_count} conv</span>
                <span class="badge">${a.total_messages || 0} msg</span>
            </div>
        </div>
    `).join('');

    document.getElementById('content1').innerHTML = html || '<div class="empty-state">Aucune annonce</div>';
}

async function selectAnnonce(id, title, convCount) {
    currentAnnonce = { id, title, convCount };
    currentUser = null;
    currentConv = null;

    document.querySelectorAll('#content1 .item').forEach(i => i.classList.remove('selected'));
    const items = document.querySelectorAll('#content1 .item');
    items.forEach(i => {
        if (i.onclick.toString().includes(id)) i.classList.add('selected');
    });

    if (currentMode === '2col') {
        // En 2col : ne rien replier, juste afficher conversations
        document.getElementById('section1').classList.remove('collapsed');
        document.getElementById('section2').classList.remove('collapsed');
        document.getElementById('section3').classList.remove('expanded');
    }

    if (currentMode === 'accordion') {
        document.getElementById('title1').textContent = title;
        document.getElementById('badge1').textContent = convCount + ' conv';
        document.getElementById('context1').textContent = 'ID: ' + id;
        document.getElementById('content1').classList.remove('open');
        document.getElementById('header1').classList.remove('active');
    }

    const section2 = document.getElementById('section2');
    section2.className = 'accordion-section color-conversations';
    section2.classList.remove('hidden');

    document.getElementById('title2').textContent = currentMode === 'accordion' ? 'Conversations' : title;
    document.getElementById('badge2').textContent = convCount;
    document.getElementById('context2').textContent = currentMode === 'accordion' ? title : 'ID: ' + id;
    document.getElementById('header2').classList.add('active');
    document.getElementById('content2').innerHTML = '<div class="loading">Chargement...</div>';
    document.getElementById('content2').classList.add('open');

    /** if (currentMode === 'accordion') {
        document.getElementById('section3').classList.add('hidden');
    } else {
        const section3 = document.getElementById('section3');
        section3.className = 'accordion-section color-messages';
        section3.classList.remove('hidden');

        document.getElementById('title3').textContent = 'Messages';
        document.getElementById('context3').textContent = 'Sélectionnez une conversation';
        document.getElementById('badge3').textContent = '0';
        document.getElementById('content3').innerHTML = '<div class="empty-state">← Sélectionnez une conversation</div>';
    }
**/
    // Toujours cacher section 3 tant qu'aucune conversation n'est sélectionnée
    document.getElementById('section3').classList.add('hidden');
    try {
        const res = await fetch(API + '?action=conversations&annonce_id=' + id);
        const convs = await res.json();
        if (convs.error) throw new Error(convs.error);
        displayConversations(convs);
    } catch (e) {
        document.getElementById('content2').innerHTML = '<div class="error-state">Erreur: ' + e.message + '</div>';
    }
}

// ========== USERS ==========

async function loadUsers() {
    document.getElementById('content1').innerHTML = '<div class="loading">Chargement...</div>';

    try {
        const res = await fetch(API + '?action=users');
        const users = await res.json();
        if (users.error) throw new Error(users.error);

        allUsers = users;
        document.getElementById('badge1').textContent = users.length;
        displayUsers(users);
    } catch (e) {
        document.getElementById('content1').innerHTML = '<div class="error-state">Erreur: ' + e.message + '</div>';
    }
}

function displayUsers(users) {
    const html = users.map(u => {
        const displayName = u.name || u.user_name;
        const subtitle = u.name ? 'ID: ' + u.user_id + ' • ' + u.user_name : 'ID: ' + u.user_id;
        const isSelected = currentUser && currentUser.id === u.user_id;

        return `
            <div class="item color-users ${isSelected ? 'selected' : ''}" onclick="selectUser(${u.user_id}, '${escapeHtml(displayName)}', ${u.conv_count})">
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

    document.getElementById('content1').innerHTML = html || '<div class="empty-state">Aucun utilisateur</div>';
}

async function selectUser(id, name, convCount) {
    currentUser = { id, name, convCount };
    currentAnnonce = null;
    currentConv = null;

    if (currentMode === '2col') {
        // En 2col : ne rien replier, juste afficher conversations
        document.getElementById('section1').classList.remove('collapsed');
        document.getElementById('section2').classList.remove('collapsed');
        document.getElementById('section3').classList.remove('expanded');
    }

    displayUsers(allUsers);

    if (currentMode === 'accordion') {
        document.getElementById('title1').textContent = name;
        document.getElementById('badge1').textContent = convCount + ' conv';
        document.getElementById('context1').textContent = 'ID: ' + id;
        document.getElementById('content1').classList.remove('open');
        document.getElementById('header1').classList.remove('active');
    }

    const section2 = document.getElementById('section2');
    section2.className = 'accordion-section color-conversations';
    section2.classList.remove('hidden');

    document.getElementById('title2').textContent = currentMode === 'accordion' ? 'Conversations' : name;
    document.getElementById('badge2').textContent = convCount;
    document.getElementById('context2').textContent = currentMode === 'accordion' ? name : 'ID: ' + id;
    document.getElementById('header2').classList.add('active');
    document.getElementById('content2').innerHTML = '<div class="loading">Chargement...</div>';
    document.getElementById('content2').classList.add('open');
    /*
        if (currentMode === 'accordion') {
            document.getElementById('section3').classList.add('hidden');
        } else {
            const section3 = document.getElementById('section3');
            section3.className = 'accordion-section color-messages';
            section3.classList.remove('hidden');
    
            document.getElementById('title3').textContent = 'Messages';
            document.getElementById('context3').textContent = 'Sélectionnez une conversation';
            document.getElementById('badge3').textContent = '0';
            document.getElementById('content3').innerHTML = '<div class="empty-state">← Sélectionnez une conversation</div>';
        }
    */
    // Toujours cacher section 3 tant qu'aucune conversation n'est sélectionnée
    document.getElementById('section3').classList.add('hidden');
    try {
        const res = await fetch(API + '?action=conversations&user_id=' + id);
        const convs = await res.json();
        if (convs.error) throw new Error(convs.error);
        displayConversations(convs);
    } catch (e) {
        document.getElementById('content2').innerHTML = '<div class="error-state">Erreur: ' + e.message + '</div>';
    }
}

// ========== CONVERSATIONS ==========

function displayConversations(convs) {
    const html = convs.map(c => {
        const title = currentView === 'annonces'
            ? (c.user_display_name || c.user_name)
            : (c.annonce_title || 'Annonce ' + c.annonce_id);
        const meta = currentView === 'annonces'
            ? 'ID: ' + c.user_id
            : 'ID: ' + c.annonce_id;
        const isSelected = currentConv && currentConv.id === c.conversation_id;

        const actions = currentView === 'annonces' ? `
            <div class="item-actions">
                <button class="item-action" onclick="event.stopPropagation(); openUserModal(${c.user_id})">⚙️</button>
                <button class="item-action" onclick="event.stopPropagation(); openExternal(${c.user_id})">🔗</button>
            </div>
        ` : '';

        return `
            <div class="item color-conversations ${isSelected ? 'selected' : ''}" onclick="selectConversation(${c.conversation_id}, '${escapeHtml(title)}', ${c.messages_count}, ${c.user_id})">
                <div class="item-title">${escapeHtml(title)}</div>
                <div class="item-meta">
                    ${meta} • ${c.last_message_date || 'Pas de date'}
                    <span class="badge">${c.messages_count} msg</span>
                </div>
                ${actions}
            </div>
        `;
    }).join('');

    document.getElementById('content2').innerHTML = html || '<div class="empty-state">Aucune conversation</div>';
}

async function selectConversation(id, title, msgCount, userId) {
    currentConv = { id, title, msgCount, userId };

    if (currentAnnonce) {
        const res = await fetch(API + '?action=conversations&annonce_id=' + currentAnnonce.id);
        const convs = await res.json();
        displayConversations(convs);
    } else if (currentUser) {
        const res = await fetch(API + '?action=conversations&user_id=' + currentUser.id);
        const convs = await res.json();
        displayConversations(convs);
    }

    if (currentMode === 'accordion') {
        document.getElementById('title2').textContent = title;
        document.getElementById('badge2').textContent = msgCount + ' msg';
        document.getElementById('content2').classList.remove('open');
        document.getElementById('header2').classList.remove('active');
    }

    // Mode 2col : replier sections 1 et 2, déplier section 3
    const section3 = document.getElementById('section3');

    if (currentMode === '2col') {
        document.getElementById('section1').classList.add('collapsed');
        document.getElementById('section2').classList.add('collapsed');
        section3.classList.add('expanded');
    }

    // Réinitialiser les classes et ajouter les bonnes
    section3.className = 'accordion-section color-messages';
    if (currentView === 'users') {
        section3.classList.add('color-users');
    }
    if (currentMode === '2col') {
        section3.classList.add('expanded');
    }
    section3.classList.remove('hidden');

    document.getElementById('title3').textContent = currentMode === 'accordion' ? 'Messages' : title;
    document.getElementById('badge3').textContent = msgCount;

    let contextText = currentMode === 'accordion' ? title : '';
    if (currentAnnonce) contextText += (contextText ? ' • ' : '') + currentAnnonce.title;
    if (currentUser) contextText += (contextText ? ' • ' : '') + currentUser.name;

    const actionButtons = `
        <span class="header-actions">
            <button class="header-action" onclick="event.stopPropagation(); openUserModal(${userId})">⚙️</button>
            <button class="header-action" onclick="event.stopPropagation(); openExternal(${userId})">🔗</button>
        </span>
    `;

    document.getElementById('context3').innerHTML = contextText + ' ' + actionButtons;
    document.getElementById('header3').classList.add('active');
    document.getElementById('content3').innerHTML = '<div class="loading">Chargement...</div>';
    document.getElementById('content3').classList.add('open');

    // Mode 3col : ouvrir automatiquement la carte utilisateur
    if (currentMode === '3col' && userId) {
        openUserModal(userId);
    }

    try {
        const res = await fetch(API + '?action=messages&conversation_id=' + id);
        let messages = await res.json();
        if (messages.error) throw new Error(messages.error);
        if (!Array.isArray(messages)) messages = [];
        displayMessages(messages);
    } catch (e) {
        document.getElementById('content3').innerHTML = '<div class="error-state">Erreur: ' + e.message + '</div>';
    }
}

function displayMessages(messages) {
    const html = messages.map(m => {
        let imagesHtml = '';
        if (m.images && m.images.length > 0) {
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

    document.getElementById('content3').innerHTML =
        '<div class="messages-container">' + (html || '<div class="empty-state">Aucun message</div>') + '</div>';

    const content = document.getElementById('content3');
    content.scrollTop = content.scrollHeight;
}

// ========== DRAG IMAGE ==========

function dragImage(event, src) {
    event.dataTransfer.setData('text/plain', src);
}

// ========== TABLE VIEW (Users Edition) ==========

async function loadUsersTable() {
    document.getElementById('tableBody').innerHTML = '<tr><td colspan="9" class="loading">Chargement...</td></tr>';

    try {
        const res = await fetch(API + '?action=users');
        const users = await res.json();
        if (users.error) throw new Error(users.error);

        displayUsersTable(users);
    } catch (e) {
        document.getElementById('tableBody').innerHTML = '<tr><td colspan="9" class="error-state">Erreur: ' + e.message + '</td></tr>';
    }
}

function displayUsersTable(users) {
    const html = users.map(u => `
        <tr data-user-id="${u.user_id}">
            <td data-label="ID">${u.user_id}</td>
            <td data-label="Photo">
                ${u.photo_url ? `<img src="${u.photo_url}" style="width: 40px; height: 40px; border-radius: 50%; object-fit: cover;">` : '👤'}
            </td>
            <td data-label="Nom">
                <input type="text" value="${escapeHtml(u.name || '')}" 
                       onchange="updateUserField(${u.user_id}, 'name', this.value)">
            </td>
            <td data-label="Téléphone">
                <input type="text" value="${escapeHtml(u.phone || '')}" 
                       onchange="updateUserField(${u.user_id}, 'phone', this.value)">
            </td>
            <td data-label="Facebook">
                <input type="text" value="${escapeHtml(u.facebook || '')}" 
                       onchange="updateUserField(${u.user_id}, 'facebook', this.value)">
            </td>
            <td data-label="WhatsApp">
                <input type="text" value="${escapeHtml(u.whatsapp || '')}" 
                       onchange="updateUserField(${u.user_id}, 'whatsapp', this.value)">
            </td>
            <td data-label="Commentaire">
                <textarea onchange="updateUserField(${u.user_id}, 'commentaire', this.value)">${escapeHtml(u.commentaire || '')}</textarea>
            </td>
            <td data-label="Conv">
                <span class="badge">${u.conv_count}</span>
            </td>
            <td data-label="Actions" class="table-actions">
                <button class="btn-table primary" onclick="goToUserConversations(${u.user_id})">📝 Voir</button>
            </td>
        </tr>
    `).join('');

    document.getElementById('tableBody').innerHTML = html || '<tr><td colspan="9" class="empty-state">Aucun utilisateur</td></tr>';
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
    // Retour à la vue normale sur cet utilisateur
    setView('users');
    setTimeout(() => {
        const user = allUsers.find(u => u.user_id === userId);
        if (user) {
            selectUser(userId, user.name || user.user_name, user.conv_count);
        }
    }, 100);
}

// ========== USER MODAL ==========

async function openUserModal(userId) {
    currentUserCardId = userId;

    try {
        const res = await fetch(API + '?action=conversations&user_id=' + userId);
        const convs = await res.json();
        if (convs.error || convs.length === 0) throw new Error('Utilisateur introuvable');

        const user = convs[0];

        const photoDiv = document.getElementById('modalUserPhoto');
        if (user.photo_url) {
            photoDiv.innerHTML = '<img src="' + user.photo_url + '">';
        } else {
            photoDiv.innerHTML = '<span class="placeholder">👤</span>';
        }

        document.getElementById('modalUserName').value = user.user_display_name || user.name || '';
        document.getElementById('modalUserPhone').value = user.phone || '';
        document.getElementById('modalUserFacebook').value = user.facebook || '';
        document.getElementById('modalUserWhatsapp').value = user.whatsapp || '';
        document.getElementById('modalUserComment').value = user.commentaire || '';
        document.getElementById('modalUserMeta').textContent = 'ID: ' + user.user_id + ' • ' + user.user_name;

        document.getElementById('userModal').classList.add('active');

        // Activer le drag & drop sur la photo du modal
        setupModalPhotoDragDrop();

        // Activer le drag du modal en mode 3col
        if (currentMode === '3col') {
            setupModalDrag();
        }
    } catch (e) {
        showFlash('Erreur: ' + e.message);
    }
}

// ========== DRAG MODAL (déplacer la fenêtre) ==========

function setupModalDrag() {
    const modal = document.querySelector('.modal-card');
    const header = document.querySelector('.modal-header');

    if (!modal || !header) return;

    let isDragging = false;
    let startX = 0;
    let startY = 0;
    let modalX = 0;
    let modalY = 0;

    // Nettoyer les anciens listeners
    const newHeader = header.cloneNode(true);
    header.parentNode.replaceChild(newHeader, header);

    newHeader.addEventListener('mousedown', (e) => {
        // Ne pas drag si on clique sur le bouton fermer
        if (e.target.classList.contains('modal-close')) return;

        isDragging = true;
        startX = e.clientX;
        startY = e.clientY;

        // Récupérer la position actuelle
        const rect = modal.getBoundingClientRect();
        modalX = rect.left;
        modalY = rect.top;

        modal.style.transition = 'none';
        e.preventDefault();
    });

    document.addEventListener('mousemove', (e) => {
        if (!isDragging) return;

        const deltaX = e.clientX - startX;
        const deltaY = e.clientY - startY;

        const newX = modalX + deltaX;
        const newY = modalY + deltaY;

        // Limites de l'écran
        const maxX = window.innerWidth - modal.offsetWidth;
        const maxY = window.innerHeight - modal.offsetHeight;

        const finalX = Math.max(0, Math.min(newX, maxX));
        const finalY = Math.max(0, Math.min(newY, maxY));

        modal.style.left = finalX + 'px';
        modal.style.top = finalY + 'px';
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

// ========== DRAG & DROP PHOTO MODAL ==========

function setupModalPhotoDragDrop() {
    const photoDiv = document.getElementById('modalUserPhoto');

    // Nettoyer les anciens listeners
    const newPhotoDiv = photoDiv.cloneNode(true);
    photoDiv.parentNode.replaceChild(newPhotoDiv, photoDiv);

    newPhotoDiv.addEventListener('dragover', (e) => {
        e.preventDefault();
        e.stopPropagation();
        newPhotoDiv.style.borderColor = 'var(--color-users)';
        newPhotoDiv.style.background = '#454545';
    });

    newPhotoDiv.addEventListener('dragleave', (e) => {
        e.preventDefault();
        e.stopPropagation();
        newPhotoDiv.style.borderColor = '';
        newPhotoDiv.style.background = '';
    });

    newPhotoDiv.addEventListener('drop', async (e) => {
        e.preventDefault();
        e.stopPropagation();
        newPhotoDiv.style.borderColor = '';
        newPhotoDiv.style.background = '';

        const photoUrl = e.dataTransfer.getData('text/plain');

        if (photoUrl && currentUserCardId) {
            // Mettre à jour visuellement
            newPhotoDiv.innerHTML = '<img src="' + photoUrl + '">';

            // Sauvegarder en BDD
            try {
                await fetch(API + '?action=update_user_photo', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ user_id: currentUserCardId, photo_url: photoUrl })
                });

                showFlash('✓ Photo mise à jour');

                // Mettre à jour dans allUsers
                const userIndex = allUsers.findIndex(u => u.user_id == currentUserCardId);
                if (userIndex !== -1) {
                    allUsers[userIndex].photo_url = photoUrl;
                    // Rafraîchir l'affichage si on est en vue users
                    if (currentView === 'users') {
                        displayUsers(allUsers);
                    }
                }
            } catch (error) {
                console.error('Erreur update photo:', error);
                showFlash('❌ Erreur sauvegarde photo');
            }
        }
    });
}

function closeUserModal(event) {
    // En mode 3col, empêcher la fermeture en cliquant sur l'overlay
    // Ne fermer que via le bouton X
    if (currentMode === '3col' && event && event.target.id === 'userModal') {
        return;
    }

    // Autres modes : fermer normalement
    if (!event || event.target.id === 'userModal' || event.target.classList.contains('modal-close')) {
        document.getElementById('userModal').classList.remove('active');
        currentUserCardId = null;
    }
}

async function saveUserProfile() {
    if (!currentUserCardId) return;

    const name = document.getElementById('modalUserName').value;
    const phone = document.getElementById('modalUserPhone').value;
    const facebook = document.getElementById('modalUserFacebook').value;
    const whatsapp = document.getElementById('modalUserWhatsapp').value;
    const commentaire = document.getElementById('modalUserComment').value;

    try {
        await fetch(API + '?action=update_user_profile', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                user_id: currentUserCardId,
                name,
                phone,
                facebook,
                whatsapp,
                commentaire
            })
        });

        showFlash('✓ Profil enregistré');

        // Mettre à jour allUsers
        const userIndex = allUsers.findIndex(u => u.user_id == currentUserCardId);
        if (userIndex !== -1) {
            allUsers[userIndex].name = name;
            allUsers[userIndex].phone = phone;
            allUsers[userIndex].facebook = facebook;
            allUsers[userIndex].whatsapp = whatsapp;
            allUsers[userIndex].commentaire = commentaire;
        }

        // Rafraîchir l'affichage si nécessaire
        if (currentMode !== '3col') {
            closeUserModal();
        }

        if (currentView === 'users') {
            loadUsers();
        } else if (currentView === 'users_table') {
            loadUsersTable();
        }
    } catch (e) {
        showFlash('Erreur sauvegarde');
    }
}

// ========== EXTERNAL LINK ==========

function openExternal(userId) {
    window.open('https://annonces.nc/dashboard/conversations?find_user=' + userId, 'annonces_nc');
    showFlash('🔍 Recherche Utilisateur ' + userId + '...');
}

// ========== LIGHTBOX ==========

function openLightbox(src) {
    document.getElementById('lightboxImg').src = src;
    document.getElementById('lightbox').classList.add('active');
}

function closeLightbox() {
    document.getElementById('lightbox').classList.remove('active');
}

// ========== FLASH MESSAGE ==========

function showFlash(message, duration = 2000) {
    console.log("FLASH " + message);
    if (message.includes('❌') || message.includes('Erreur')) {
        duration = 10000;
    }
    const flash = document.createElement('div');
    flash.className = 'flash';
    flash.textContent = message;
    document.getElementById('flashContainer').appendChild(flash);
    setTimeout(() => flash.remove(), duration);
}

// ========== UTILS ==========

function escapeHtml(text) {
    if (!text) return '';
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}