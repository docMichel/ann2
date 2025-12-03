<?php

/**
 * Index.php avec authentification
 */

require_once __DIR__ . '/auth/auth.php';
Auth::requireAuth();

$user = Auth::getCurrentUser();
$username = explode('@', $user['email'])[0];
?>
<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=no">
    <title>Messages Annonces.nc - <?= htmlspecialchars($username) ?></title>
    <link rel="stylesheet" href="styles.css">
    <style>
        /* Ajout styles pour header étendu */
        .header-right {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-left: auto;
        }

        .btn-sync {
            padding: 8px 15px;
            background: #77DD77;
            border: none;
            color: #1a1a1a;
            font-weight: 600;
            border-radius: 6px;
            cursor: pointer;
            font-size: 13px;
            transition: all 0.2s;
            white-space: nowrap;
        }

        .btn-sync:hover {
            background: #5BC75B;
        }

        .btn-sync:disabled {
            background: #3a3a3a;
            color: #888;
            cursor: not-allowed;
        }

        .btn-sync.running {
            background: #FFB84D;
            animation: pulse 1.5s infinite;
        }

        @keyframes pulse {

            0%,
            100% {
                opacity: 1;
            }

            50% {
                opacity: 0.6;
            }
        }

        .user-badge {
            padding: 6px 12px;
            background: #3a3a3a;
            border-radius: 6px;
            color: #e0e0e0;
            font-size: 12px;
            white-space: nowrap;
        }

        .btn-logout {
            padding: 8px 15px;
            background: #3a3a3a;
            border: none;
            color: #e0e0e0;
            border-radius: 6px;
            cursor: pointer;
            font-size: 13px;
            transition: all 0.2s;
            white-space: nowrap;
        }

        .btn-logout:hover {
            background: #ff6b6b;
        }

        /* Annonces supprimées */
        .item.deleted {
            opacity: 0.6;
        }

        .item.deleted .item-title::before {
            content: "🗑️ ";
        }

        .item.deleted .item-title {
            color: #888;
            text-decoration: line-through;
        }

        @media (max-width: 768px) {
            .header-right {
                gap: 6px;
            }

            .btn-sync,
            .btn-logout {
                padding: 6px 10px;
                font-size: 12px;
            }

            .user-badge {
                display: none;
            }
        }

        @media (max-width: 599px) {
            .header-right {
                display: none;
            }
        }
    </style>
</head>

<body>
    <!-- Header avec toggle + contrôles utilisateur -->
    <header class="top-header">
        <div class="toggle-view">
            <button class="toggle-btn active" id="btnAnnonces" onclick="setView('annonces')">
                📋 <span id="btnAnnoncesCount">Annonces</span>
            </button>
            <button class="toggle-btn" id="btnUsers" onclick="setView('users')">
                👥 <span id="btnUsersCount">Users</span>
            </button>
            <button class="toggle-btn" id="btnUsersTable" onclick="setView('users_table')">
                📊 Édition Users
            </button>
        </div>

        <div class="header-right">
            <button class="btn-sync" id="btnSync" onclick="launchScraper()">
                🔄 Récupérer
            </button>
            <span class="user-badge">👤 <?= htmlspecialchars($username) ?></span>
            <button class="btn-logout" onclick="logout()">Déco</button>
        </div>

        <button class="fullscreen-btn" id="fullscreenBtn" onclick="toggleFullscreen()">⛶</button>
    </header>

    <!-- Container accordéon (identique à l'original) -->
    <main class="accordion-container" id="mainContainer">
        <!-- Section 1: Annonces/Users -->
        <section class="accordion-section" id="section1">
            <div class="accordion-header active" id="header1" onclick="toggleSection(1)">
                <div class="header-content">
                    <div class="header-title">
                        <span class="header-icon" id="icon1">📋</span>
                        <span id="title1">Annonces</span>
                        <span class="badge" id="badge1">...</span>
                    </div>
                    <div class="header-context" id="context1"></div>
                </div>
                <span class="accordion-arrow">▼</span>
            </div>
            <div class="accordion-content open" id="content1">
                <div class="loading">Chargement...</div>
            </div>
        </section>

        <!-- Section 2: Conversations -->
        <section class="accordion-section hidden" id="section2">
            <div class="accordion-header active" id="header2" onclick="toggleSection(2)">
                <div class="header-content">
                    <div class="header-title">
                        <span class="header-icon">💬</span>
                        <span id="title2">Conversations</span>
                        <span class="badge" id="badge2">0</span>
                    </div>
                    <div class="header-context" id="context2"></div>
                </div>
                <span class="accordion-arrow">▼</span>
            </div>
            <div class="accordion-content open" id="content2"></div>
        </section>

        <!-- Section 3: Messages -->
        <section class="accordion-section hidden" id="section3">
            <div class="accordion-header active" id="header3" onclick="toggleSection(3)">
                <div class="header-content">
                    <div class="header-title">
                        <span class="header-icon">📝</span>
                        <span id="title3">Messages</span>
                        <span class="badge" id="badge3">0</span>
                    </div>
                    <div class="header-context" id="context3"></div>
                </div>
                <span class="accordion-arrow">▼</span>
            </div>
            <div class="accordion-content open" id="content3"></div>
        </section>
    </main>

    <!-- Vue tableau Users (identique à l'original) -->
    <div class="table-view" id="tableView" style="display: none;">
        <table class="users-table">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Photo</th>
                    <th>Nom</th>
                    <th>Téléphone</th>
                    <th>Facebook</th>
                    <th>WhatsApp</th>
                    <th>Commentaire</th>
                    <th>Conv</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody id="tableBody">
                <tr>
                    <td colspan="9" class="loading">Chargement...</td>
                </tr>
            </tbody>
        </table>
    </div>

    <!-- Lightbox pour images -->
    <div class="lightbox" id="lightbox" onclick="closeLightbox()">
        <span class="lightbox-close">&times;</span>
        <img id="lightboxImg" src="">
    </div>

    <!-- Flash messages -->
    <div class="flash-container" id="flashContainer"></div>

    <!-- User card modal -->
    <div class="modal-overlay" id="userModal" onclick="closeUserModal(event)">
        <div class="modal-card" onclick="event.stopPropagation()">
            <div class="modal-header">
                <span>👤 Profil utilisateur</span>
                <span class="modal-close" onclick="closeUserModal()">&times;</span>
            </div>
            <div class="modal-body">
                <div class="user-photo" id="modalUserPhoto">
                    <span class="placeholder">👤</span>
                </div>
                <input type="text" id="modalUserName" placeholder="Nom...">
                <input type="text" id="modalUserPhone" placeholder="Téléphone...">
                <input type="text" id="modalUserFacebook" placeholder="Facebook URL...">
                <input type="text" id="modalUserWhatsapp" placeholder="WhatsApp...">
                <textarea id="modalUserComment" placeholder="Commentaire..."></textarea>
                <div class="modal-meta" id="modalUserMeta"></div>
                <button class="btn-save" onclick="saveUserProfile()">💾 Enregistrer</button>
            </div>
        </div>
    </div>


    <!-- Logs modal SIMPLE -->
    <div id="logsModal">
        <div id="logsModalInner">
            <div id="logsModalHeader">
                <span id="logsModalTitle">📊 Logs du scraper</span>
                <button id="logsModalClose">×</button>
            </div>
            <div id="logsContent"></div>
        </div>
    </div>


    <script src="app.js"></script>
    <script>
        // ========== SCRAPER ==========

        async function launchScraper() {
            const btn = document.getElementById('btnSync');

            if (!confirm('Lancer la récupération des messages ?\n\nCela peut prendre plusieurs minutes.')) {
                return;
            }

            btn.disabled = true;
            btn.classList.add('running');
            btn.textContent = '⏳ En cours...';

            try {
                const res = await fetch('sync.php', {
                    method: 'POST'
                });
                const result = await res.json();

                if (result.status === 'started') {
                    showFlash('✅ Scraper lancé en arrière-plan');

                    // Ouvrir le modal de logs
                    openLogsModal();

                    // Vérifier le statut périodiquement
                    checkScraperStatus();
                } else {
                    throw new Error(result.message || 'Erreur inconnue');
                }
            } catch (e) {
                showFlash('❌ Erreur: ' + e.message, 10000);
                btn.disabled = false;
                btn.classList.remove('running');
                btn.textContent = '🔄 Récupérer';
            }
        }

        function checkScraperStatus() {
            const interval = setInterval(async () => {
                try {
                    const res = await fetch('sync-status.php');
                    const result = await res.json();

                    if (result.status === 'idle') {
                        clearInterval(interval);

                        const btn = document.getElementById('btnSync');
                        btn.disabled = false;
                        btn.classList.remove('running');
                        btn.textContent = '🔄 Récupérer';

                        showFlash('✅ Synchronisation terminée');

                        // Recharger les données
                        if (currentView === 'annonces') {
                            loadAnnonces();
                        } else if (currentView === 'users') {
                            loadUsers();
                        }
                    }
                } catch (e) {
                    console.error('Erreur check status:', e);
                }
            }, 10000); // Vérifier toutes les 10 secondes
        }

        // ========== LOGS MODAL - REFAIT DE ZÉRO ==========

        let logsEventSource = null;

        function openLogsModal() {
            const modal = document.getElementById('logsModal');
            const content = document.getElementById('logsContent');

            content.innerHTML = '<div class="log-line">🔌 Connexion...</div>';
            modal.classList.add('active');

            startLogsStream();
        }

        function closeLogsModal() {
            const modal = document.getElementById('logsModal');
            modal.classList.remove('active');

            if (logsEventSource) {
                logsEventSource.close();
                logsEventSource = null;
            }
        }

        function addLogLine(text, type = '') {
            const content = document.getElementById('logsContent');
            const line = document.createElement('div');
            line.className = 'log-line ' + type;
            line.textContent = text;
            content.appendChild(line);
            content.scrollTop = content.scrollHeight;
        }

        function startLogsStream() {
            if (logsEventSource) {
                logsEventSource.close();
            }

            logsEventSource = new EventSource('stream-logs.php');

            logsEventSource.addEventListener('connected', () => {
                addLogLine('✅ Connecté au stream');
            });

            logsEventSource.addEventListener('log', (e) => {
                const data = JSON.parse(e.data);
                addLogLine(data.line);
            });

            logsEventSource.addEventListener('info', (e) => {
                const data = JSON.parse(e.data);
                addLogLine('ℹ️  ' + data.message);
            });

            logsEventSource.addEventListener('error', (e) => {
                if (e.data) {
                    const data = JSON.parse(e.data);
                    addLogLine('❌ ' + data.message, 'error');
                }
            });

            logsEventSource.addEventListener('complete', (e) => {
                const data = JSON.parse(e.data);
                addLogLine('✅ ' + data.message, 'success');
                setTimeout(() => {
                    closeLogsModal();
                }, 3000);
            });

            logsEventSource.onerror = () => {
                addLogLine('❌ Erreur connexion stream', 'error');
            };
        }

        // Event listeners pour fermer le modal
        document.addEventListener('DOMContentLoaded', () => {
            const modal = document.getElementById('logsModal');
            const closeBtn = document.getElementById('logsModalClose');

            if (closeBtn) {
                closeBtn.addEventListener('click', closeLogsModal);
            }

            if (modal) {
                modal.addEventListener('click', (e) => {
                    if (e.target.id === 'logsModal') {
                        closeLogsModal();
                    }
                });
            }
        });
    </script>
</body>

</html>