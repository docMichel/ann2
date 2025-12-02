# Messages Annonces.nc - Application Unifiée

## 🎯 Architecture

Application responsive unifiée pour gérer les messages des annonces.nc avec 3 modes d'affichage automatiques :
- **Mobile (< 600px)** : Vue accordéon 1 colonne
- **Tablette (600-899px)** : Vue 2 colonnes avec réduction possible
- **Desktop (≥ 900px)** : Vue 3 colonnes fixes

## 📁 Structure des fichiers

```
annonces/
├── index.html          # Interface HTML unifiée
├── styles.css          # Styles CSS isolés et responsive
├── app.js             # JavaScript unifié
├── api.php            # API REST backend
├── schema_update.sql  # Mise à jour BDD
└── README.md          # Cette documentation
```

## 🚀 Installation

### 1. Mise à jour de la base de données

```bash
mysql -u root -p annonces_messages < schema_update.sql
```

Cela ajoutera les nouveaux champs :
- `phone` : Numéro de téléphone
- `facebook` : URL du profil Facebook
- `whatsapp` : Numéro WhatsApp

### 2. Déploiement des fichiers

Remplacez les anciens fichiers par :
- `index.html` (remplace messages.html et mobile.html)
- `styles.css` (nouveau fichier CSS isolé)
- `app.js` (remplace messages.js et messages-mobile.js)
- `api.php` (mise à jour)

### 3. Configuration

Vérifiez la configuration dans `api.php` :

```php
$dbConfig = [
    'host' => 'localhost',
    'dbname' => 'annonces_messages',
    'user' => 'root',
    'password' => 'mysqlroot'
];
```

## 🎨 Fonctionnalités

### 3 modes de vue

1. **📋 Annonces** : Navigation par annonces → conversations → messages
2. **👥 Users** : Navigation par utilisateurs → conversations → messages
3. **📊 Édition Users** : Vue tableau pour édition rapide de tous les users

### Vue Édition Users

La nouvelle vue tableau permet de :
- ✏️ Éditer rapidement tous les champs (nom, téléphone, réseaux sociaux, commentaire)
- 📊 Voir en un coup d'œil tous les utilisateurs
- 🔍 Accéder directement aux conversations d'un user via le bouton "📝 Voir"
- 💾 Sauvegarde automatique à chaque modification de champ

### Champs disponibles

Pour chaque utilisateur :
- **ID** : Identifiant unique
- **Photo** : Photo de profil (drag & drop depuis les messages)
- **Nom** : Nom personnalisé
- **Username** : Nom d'utilisateur annonces.nc
- **Téléphone** : Numéro de téléphone
- **Facebook** : URL du profil Facebook
- **WhatsApp** : Numéro WhatsApp
- **Commentaire** : Notes libres
- **Conv** : Nombre de conversations

### Modal utilisateur

Accessible via les boutons ⚙️ :
- Édition complète du profil
- Photo de profil
- Tous les champs (nom, téléphone, réseaux, commentaire)
- Sauvegarde rapide

## 📱 Responsive

L'application s'adapte automatiquement :

| Largeur | Mode | Colonnes | Navigation |
|---------|------|----------|------------|
| < 600px | Accordéon | 1 | Dépliage progressif |
| 600-899px | 2 colonnes | 2 | Messages plein écran possible |
| ≥ 900px | Desktop | 3 | Toutes visibles simultanément |

## 🔧 API Endpoints

### GET

- `?action=stats` : Statistiques globales
- `?action=annonces` : Liste des annonces
- `?action=users` : Liste des utilisateurs
- `?action=conversations&annonce_id=X` : Conversations d'une annonce
- `?action=conversations&user_id=X` : Conversations d'un user
- `?action=messages&conversation_id=X` : Messages d'une conversation
- `?action=conversation_detail&conversation_id=X` : Détails d'une conversation

### POST

- `?action=save` : Enregistrer une conversation (depuis le scraper)
- `?action=update_user_profile` : Mettre à jour un profil complet
- `?action=update_user_field` : Mettre à jour un champ spécifique
- `?action=update_user_photo` : Mettre à jour la photo
- `?action=update_user_comment` : Mettre à jour le commentaire

## 🎯 Workflow typique

### Navigation par annonces

1. Cliquer sur "📋 Annonces"
2. Sélectionner une annonce
3. Voir les conversations liées
4. Sélectionner une conversation
5. Voir les messages

### Navigation par users

1. Cliquer sur "👥 Users"
2. Sélectionner un utilisateur
3. Voir toutes ses conversations (groupées par annonce)
4. Sélectionner une conversation
5. Voir les messages

### Édition rapide

1. Cliquer sur "📊 Édition Users"
2. Vue tableau de tous les users
3. Éditer directement les champs (sauvegarde auto)
4. Cliquer sur "📝 Voir" pour accéder aux conversations

## 🔗 Intégrations

### Lien externe annonces.nc

Le bouton **🔗 Annonces.nc** ouvre la recherche directe de l'utilisateur sur annonces.nc :
```
https://annonces.nc/dashboard/conversations?find_user={user_id}
```

### Lightbox images

Cliquer sur une image dans les messages pour l'afficher en plein écran.

## 🐛 Debug

Les logs sont enregistrés dans `api_debug.log` :

```bash
tail -f api_debug.log
```

## ⚡ Performance

- Sauvegarde automatique des champs (vue tableau)
- Cache des données users/annonces
- Requêtes SQL optimisées avec agrégations
- Chargement progressif des contenus

## 🔐 Sécurité

- Whitelist des champs éditables (`updateUserField`)
- Protection CORS configurée
- Validation des inputs côté serveur
- Échappement HTML côté client

## 📝 Notes de migration

**Depuis l'ancienne version** :
- ✅ Toutes les fonctionnalités conservées
- ✅ Vue mobile améliorée
- ✅ Vue desktop modernisée
- ✅ Nouveaux champs utilisateurs
- ✅ Vue tableau d'édition
- ✅ CSS isolé et maintenable

**Breaking changes** :
- Aucun ! L'API reste compatible avec les scrapers existants.

## 🎨 Personnalisation

Pour modifier les couleurs, éditer `styles.css` :

```css
/* Couleur principale */
.toggle-btn.active,
.badge {
    background: #4a9eff; /* Bleu par défaut */
}

/* Thème sombre */
body {
    background: #1a1a1a;
    color: #e0e0e0;
}
```

## 📞 Support

Pour toute question ou amélioration, contactez l'équipe de développement.