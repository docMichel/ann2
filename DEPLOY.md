# 📦 Guide de déploiement - Messages Annonces.nc

Application web multi-utilisateurs pour gérer et scraper les messages de annonces.nc avec authentification, notifications Telegram et interface responsive.

---

## 🎯 Prérequis

### Système
- **OS** : Ubuntu 20.04+ / Debian 11+
- **RAM** : 2 GB minimum
- **Disk** : 5 GB minimum

### Logiciels requis
- **Apache 2.4+** avec mod_rewrite
- **MySQL 8.0+** ou **MariaDB 10.5+**
- **PHP 8.1+** avec extensions :
  - `php-mysql`, `php-curl`, `php-json`, `php-mbstring`
- **Python 3.9+**
- **Git**

---

## 📥 Installation des dépendances

### 1. Paquets système

```bash
# Mise à jour
sudo apt update && sudo apt upgrade -y

# Apache + MySQL + PHP
sudo apt install -y apache2 mysql-server \
  php php-mysql php-curl php-json php-mbstring \
  python3 python3-pip python3-venv git

# Activer mod_rewrite
sudo a2enmod rewrite
sudo systemctl restart apache2
```

### 2. Python et Playwright

```bash
# Créer l'environnement virtuel (dans le dossier du projet)
cd /var/www/html/ann2
python3 -m venv venv

# Activer le venv
source venv/bin/activate

# Installer Playwright
pip install playwright requests

# Installer les navigateurs Playwright
playwright install chromium
playwright install-deps
```

---

## 🚀 Déploiement initial

### 1. Cloner le dépôt

```bash
cd /var/www/html
git clone https://github.com/docMichel/ann2.git
cd ann2
```

### 2. Créer les dossiers manquants

```bash
mkdir -p config logs locks
chmod 775 config logs locks
sudo chown -R www-data:www-data config logs locks
```

### 3. Configuration MySQL

```bash
# Connexion MySQL
sudo mysql -u root

# Créer l'utilisateur root avec mot de passe (si nécessaire)
ALTER USER 'root'@'localhost' IDENTIFIED WITH mysql_native_password BY 'mysqlroot';
FLUSH PRIVILEGES;
EXIT;
```

### 4. Configurer Apache

#### Option A : Via VirtualHost dédié (recommandé)

```bash
sudo nano /etc/apache2/sites-available/ann2.conf
```

Contenu :
```apache
<VirtualHost *:80>
    ServerName ann2.votredomaine.com
    DocumentRoot /var/www/html/ann2
    
    <Directory /var/www/html/ann2>
        Options -Indexes +FollowSymLinks
        AllowOverride All
        Require all granted
    </Directory>
    
    ErrorLog ${APACHE_LOG_DIR}/ann2-error.log
    CustomLog ${APACHE_LOG_DIR}/ann2-access.log combined
</VirtualHost>
```

```bash
# Activer le site
sudo a2ensite ann2.conf
sudo systemctl reload apache2
```

#### Option B : Via alias (si plusieurs apps)

```bash
sudo nano /etc/apache2/sites-available/000-default.conf
```

Ajouter dans le `<VirtualHost>` :
```apache
Alias /ann2 /var/www/html/ann2
<Directory /var/www/html/ann2>
    Options -Indexes +FollowSymLinks
    AllowOverride All
    Require all granted
</Directory>
```

```bash
sudo systemctl reload apache2
```

### 5. Vérifier /etc/hosts

```bash
# Vérifier que localhost pointe bien sur 127.0.0.1
grep "127.0.0.1.*localhost" /etc/hosts

# Si absent, l'ajouter
echo "127.0.0.1 localhost" | sudo tee -a /etc/hosts
```

---

## ⚙️ Configuration initiale

### 1. Configuration Telegram (optionnel)

Si vous voulez les notifications Telegram :

```bash
cd /var/www/html/ann2
php init-config.php
```

Entrer :
- **Bot Token** : obtenu via [@BotFather](https://t.me/botfather)
- **Chat ID** : obtenu via [@userinfobot](https://t.me/userinfobot)

### 2. Créer le premier utilisateur

```bash
# L'utilisateur demande d'abord l'accès via l'interface web
# Ensuite, approuver depuis le serveur :

cd /var/www/html/ann2
php approve-user.php email@example.com
```

Cela va :
- ✅ Créer la base de données utilisateur
- ✅ Initialiser le schéma (tables annonces, users, conversations, messages)
- ✅ Activer le compte

---

## 🔐 Workflow utilisateur

### Première connexion

1. **Demande d'accès** : L'utilisateur va sur `https://votre-domaine.com/ann2` et entre ses credentials **annonces.nc**
2. **Vérification** : Le système vérifie que les credentials sont valides sur annonces.nc
3. **Notification admin** : L'admin reçoit une notification Telegram (si configuré)
4. **Approbation admin** : L'admin exécute `php approve-user.php email@example.com`
5. **Connexion** : L'utilisateur peut maintenant se connecter

### Utilisateurs suivants

Même processus. Chaque utilisateur a sa propre base de données isolée.

---

## 🤖 Configuration du scraper

Le scraper peut tourner en deux modes :

### Mode SMART (par défaut)
- S'arrête après 5 conversations consécutives sans nouveaux messages
- Rapide pour les mises à jour régulières
- **Cas d'usage** : Scraping quotidien/horaire

### Mode FULL
- Scrape toutes les conversations (jusqu'à `maxConversations` défini dans config)
- Activé automatiquement si la base est vide
- **Cas d'usage** : Premier scraping ou récupération complète

### Lancement manuel

Via l'interface web (bouton "🔄 Récupérer") ou en CLI :

```bash
cd /var/www/html/ann2
source venv/bin/activate

# Mode smart (défaut)
python3 sync.py --config=config/temp_username.json

# Mode full
python3 sync.py --config=config/temp_username.json --full

# Mode headful (debug)
python3 sync.py --config=config/temp_username.json --headful
```

### Logs

```bash
# Logs de scraping
tail -f /var/www/html/ann2/logs/username_sync.log

# Logs API
tail -f /var/www/html/ann2/logs/api_annonces_messages_username.log
```

---

## 🌐 Exposition publique (Tailscale Funnel)

Si vous utilisez Tailscale, vous pouvez exposer l'app publiquement :

```bash
# Installer Tailscale (si pas déjà fait)
curl -fsSL https://tailscale.com/install.sh | sh

# Exposer Apache en public
sudo tailscale funnel --bg 80

# Vérifier
tailscale funnel status
```

Votre app sera accessible à :
```
https://votre-machine.tail<xxxxx>.ts.net/ann2
```

**Arrêter le funnel :**
```bash
sudo tailscale funnel --off 80
```

---

## 🔧 Maintenance

### Mise à jour du code

```bash
cd /var/www/html/ann2

# Sauvegarder les configs
cp config/users.json /tmp/users.json.backup

# Pull les changements
git stash  # Si modifications locales
git pull

# Restaurer les configs
cp /tmp/users.json.backup config/users.json

# Permissions
sudo chown -R www-data:www-data config logs locks
```

### Ajout d'un utilisateur

```bash
# 1. L'utilisateur fait une demande via l'interface web
# 2. Approuver :
php approve-user.php newemail@example.com
```

### Suppression d'un utilisateur

```bash
# 1. Supprimer la base de données
mysql -u root -pmysqlroot -e "DROP DATABASE annonces_messages_username;"

# 2. Éditer config/users.json et retirer l'utilisateur
```

### Nettoyage des logs

```bash
cd /var/www/html/ann2

# Nettoyer les logs de plus de 7 jours
find logs/ -name "*.log" -mtime +7 -delete

# Ou vider tous les logs
truncate -s 0 logs/*.log
```

### Backup

```bash
# Backup des bases de données
mysqldump -u root -pmysqlroot --all-databases > /backup/ann2-$(date +%Y%m%d).sql

# Backup des configs
tar czf /backup/ann2-config-$(date +%Y%m%d).tar.gz config/
```

---

## 🐛 Dépannage

### Problème : "No such file or directory" (MySQL)

**Cause** : Socket MySQL introuvable

**Solution** : Déjà corrigé dans le code (utilise `127.0.0.1` au lieu de `localhost`)

### Problème : Erreur 500 sur l'API

```bash
# Vérifier les logs Apache
sudo tail -f /var/log/apache2/error.log

# Vérifier les permissions
ls -la /var/www/html/ann2/config
ls -la /var/www/html/ann2/logs
```

### Problème : Scraper ne démarre pas

```bash
# Vérifier que Playwright est installé
source venv/bin/activate
python3 -c "import playwright; print('OK')"

# Réinstaller si nécessaire
playwright install chromium
playwright install-deps
```

### Problème : Base de données non créée

```bash
# Vérifier la connexion MySQL
mysql -u root -pmysqlroot -e "SHOW DATABASES;"

# Créer manuellement si besoin
mysql -u root -pmysqlroot < schema_update.sql
```

---

## 📊 Structure des fichiers

```
ann2/
├── config/                  # Configurations (ignoré par git)
│   ├── users.json          # Utilisateurs approuvés
│   ├── pending_users.json  # Demandes en attente
│   └── temp_*.json         # Configs temporaires scraper
├── logs/                    # Logs (ignoré par git)
│   ├── api_*.log
│   └── *_sync.log
├── locks/                   # Locks des scrapers (ignoré par git)
├── auth/                    # Système d'authentification
│   ├── auth.php
│   ├── login.php
│   └── logout.php
├── venv/                    # Environnement Python (ignoré par git)
├── api.php                  # API REST backend
├── index.php                # Interface web principale
├── sync.php                 # Launcher scraper (via web)
├── sync.py                  # Scraper Python/Playwright
├── db-manager.php           # Gestion bases de données
├── telegram-notify.php      # Notifications Telegram
├── approve-user.php         # CLI : approuver utilisateurs
└── schema_update.sql        # Schéma base de données
```

---

## 🔒 Sécurité

### Points importants

1. **Credentials MySQL** : Dans `config.php`, changez le mot de passe root
2. **Telegram** : Ne partagez jamais votre bot token
3. **HTTPS** : Utilisez un certificat SSL (Let's Encrypt ou Tailscale Funnel)
4. **Permissions** : `config/`, `logs/`, `locks/` = 775 avec owner `www-data`

### Recommandations

- Utilisez un mot de passe fort pour MySQL
- Activez le firewall (`ufw`) et n'ouvrez que les ports nécessaires
- Utilisez Tailscale ou un VPN au lieu d'exposer directement sur Internet
- Faites des backups réguliers des bases de données

---

## 📞 Support

Pour tout problème :

1. Vérifier les logs (`logs/api_*.log`, `logs/*_sync.log`)
2. Vérifier les permissions (`sudo chown -R www-data:www-data ...`)
3. Vérifier Apache (`sudo systemctl status apache2`)
4. Vérifier MySQL (`sudo systemctl status mysql`)

---

## 📝 Changelog

- **v2.0** : Multi-utilisateurs avec authentification
- **v1.0** : Version initiale mono-utilisateur

---

**🎉 Votre installation est maintenant complète !**

Accédez à `https://votre-domaine.com/ann2` et connectez-vous avec vos credentials annonces.nc.