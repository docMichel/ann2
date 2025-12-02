<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=no">
    <title>Messages Annonces.nc</title>
    <link rel="stylesheet" href="styles.css">
</head>

<body>
    <!-- Header avec toggle -->
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
        <button class="fullscreen-btn" id="fullscreenBtn"
            onclick="toggleFullscreen()">⛶</button>
    </header>

    <!-- Container accordéon (vue normale) -->
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

    <!-- Vue tableau Users (cachée par défaut) -->
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

    <script src="app.js"></script>
</body>

</html>


thomas: 936193