#!/usr/bin/env python3
"""
Scraper Annonces.nc - Multi-utilisateurs avec Playwright
SMART SCRAPING: s'arrête après N conversations sans nouveaux messages

Usage:
    python3 sync.py --config=config/temp_user1.json          # Mode smart (défaut)
    python3 sync.py --config=config/temp_user1.json --full   # Mode complet (600 convs)
    python3 sync.py --config=config/temp_user1.json --headful
    python3 sync.py --config=config/temp_user1.json --firefox
"""

import sys
import json
import time
import argparse
from pathlib import Path
from playwright.sync_api import sync_playwright, TimeoutError as PlaywrightTimeout

# ========== PARSE ARGUMENTS ==========
parser = argparse.ArgumentParser(description='Scraper Annonces.nc')
parser.add_argument('--config', type=str, help='Fichier de configuration JSON')
parser.add_argument('--headful', action='store_true', help='Mode visible (debug)')
parser.add_argument('--firefox', action='store_true', help='Utiliser Firefox')
parser.add_argument('--full', action='store_true', help='Mode complet (désactive smart stop)')
args = parser.parse_args()

# ========== CONFIG ==========
SCRAPER_DIR = Path(__file__).parent

if args.config:
    CONFIG_FILE = Path(args.config)
else:
    CONFIG_FILE = SCRAPER_DIR / 'scraper-config.json'

LOGIN_JS = SCRAPER_DIR / 'login.js'
SCRAPER_JS = SCRAPER_DIR / 'scraper.js'
TARGET_URL = 'https://annonces.nc/dashboard/conversations'

# ========== SMART SCRAPING CONFIG ==========
SMART_STOP_ENABLED = not args.full
COLLISION_THRESHOLD = 5  # Arrêter après 5 convs sans nouveaux messages
CONVS_PER_PAGE = 25  # Nombre de conversations par page sur annonces.nc

# ========== HELPERS ==========
def get_timestamp():
    return time.strftime('%Y-%m-%d %H:%M:%S')

def log(msg):
    print(f'[{get_timestamp()}][PY] {msg}', flush=True)

def error(msg):
    print(f'[{get_timestamp()}][PY] ❌ {msg}', file=sys.stderr, flush=True)

def check_database_empty(config):
    """Vérifie si la base est vide en interrogeant l'API"""
    try:
        import requests
        
        # Construire l'URL stats
        api_base = config['apiUrl'].replace('?action=save', '?action=stats')
        db_name = config.get('db_name', 'annonces_messages_default')
        
        # Appeler l'API stats (nécessite auth, donc on utilise le header)
        # Note: l'action stats nécessite auth, on va plutôt compter sur le fichier config
        # Alternative: faire une requête simple
        
        response = requests.get(api_base, headers={'X-User-Database': db_name}, timeout=5)
        
        if response.status_code == 200:
            stats = response.json()
            msg_count = stats.get('messages', 0)
            log(f'📊 Base actuelle: {msg_count} messages')
            return msg_count == 0
        else:
            log(f'⚠️  Impossible de vérifier la base (status {response.status_code})')
            return False  # Par défaut, mode smart
            
    except Exception as e:
        log(f'⚠️  Erreur vérification base: {e}')
        return False  # Par défaut, mode smart

def load_config():
    """Charge la config depuis JSON"""
    if not CONFIG_FILE.exists():
        error(f'Config manquante: {CONFIG_FILE}')
        sys.exit(1)
    
    with open(CONFIG_FILE) as f:
        config = json.load(f)
    
    if not config.get('email') or not config.get('password'):
        error('Credentials manquants dans config')
        sys.exit(1)
    
    # Extraire db_name pour la vérification
    db_name = config.get('db_name')
    if not db_name and args.config:
        username = Path(args.config).stem.replace('temp_', '')
        db_name = f'annonces_messages_{username}'
        config['db_name'] = db_name
    
    # Décider du mode : --full explicite OU base vide
    force_full = args.full
    
    if not force_full:
        # Vérifier si la base est vide
        is_empty = check_database_empty(config)
        if is_empty:
            log('🆕 Base vide détectée → MODE FULL automatique')
            force_full = True
    
    # Ajouter config smart scraping
    config['smartStop'] = not force_full
    config['collisionThreshold'] = COLLISION_THRESHOLD
    
    # Calculer maxPages automatiquement selon le mode
    if force_full:
        # Mode full : calculer le nombre de clics "Voir plus" nécessaires
        max_convs = config.get('maxConversations', 600)
        config['maxPages'] = max(1, (max_convs // CONVS_PER_PAGE))  # 600/25 = 24 pages
        config['maxConversations'] = max_convs
        log(f'🔄 MODE FULL: {config["maxPages"]} pages pour {max_convs} conversations')
    else:
        # Mode smart : quelques pages suffisent (on s'arrête aux collisions)
        config['maxPages'] = config.get('maxPages', 5)  # 5 pages = 125 convs max
        config['maxConversations'] = config.get('maxConversations', 200)
        log(f'🧠 MODE SMART: max {config["maxPages"]} pages ({config["maxPages"] * CONVS_PER_PAGE} convs), arrêt après {COLLISION_THRESHOLD} collisions')
    
    return config

def load_script(script_path):
    """Charge un fichier JS"""
    if not script_path.exists():
        error(f'Script manquant: {script_path}')
        sys.exit(1)
    
    with open(script_path) as f:
        return f.read()

def inject_config(page, config):
    """Injecte la config dans localStorage du navigateur"""
    config_json = json.dumps(config)
    config_escaped = config_json.replace('\\', '\\\\').replace("'", "\\'")
    
    page.evaluate(f"""
        localStorage.setItem('SCRAPER_CONFIG', '{config_escaped}');
        console.log('[PYTHON] Config injectée dans localStorage');
    """)

def send_telegram_notification(config, stats):
    """Envoie une notification Telegram de fin de scraping"""
    try:
        import requests
        import socket
        
        users_config_file = SCRAPER_DIR / 'config' / 'users.json'
        if not users_config_file.exists():
            return
        
        with open(users_config_file) as f:
            users_config = json.load(f)
        
        bot_token = users_config.get('telegram_bot_token')
        if not bot_token:
            return
        
        # Trouver le chat_id de l'utilisateur correspondant
        chat_id = None
        for user in users_config.get('users', []):
            if user.get('email') == config['email'] or user.get('annonces_email') == config['email']:
                chat_id = user.get('telegram_chat_id')
                break
        
        if not chat_id:
            chat_id = users_config.get('admin_telegram_chat_id')
        
        if not chat_id:
            return
        
        # Construire le message
        hostname = socket.gethostname()
        mode = "COMPLET" if args.full else "SMART"
        stop_reason = stats.get('stop_reason', 'fin normale')
        
        message = f"✅ <b>Scraping {mode} terminé</b>\n\n"
        message += f"📍 Source: <code>{hostname}</code>\n\n"
        message += f"📊 <b>Résumé:</b>\n"
        message += f"  • Conversations: {stats.get('total', 0)}\n"
        message += f"  • Nouveaux msgs: {stats.get('total_new_messages', 0)}\n"
        message += f"  • Succès: {stats.get('succeeded', 0)}\n"
        message += f"  • Échecs: {stats.get('failed', 0)}\n"
        message += f"  • Arrêt: {stop_reason}\n"
        message += f"\n⏰ {time.strftime('%d/%m/%Y à %H:%M')}"
        
        url = f"https://api.telegram.org/bot{bot_token}/sendMessage"
        data = {
            'chat_id': chat_id,
            'text': message,
            'parse_mode': 'HTML'
        }
        
        requests.post(url, data=data, timeout=10)
        log('📱 Notification Telegram envoyée')
        
    except Exception as e:
        log(f'⚠️  Erreur notification Telegram: {e}')

# ========== MAIN ==========
def run_scraper(headless=True, browser_type='chromium'):
    """Lance le scraper en 2 étapes : login puis scraping"""
    
    log('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━')
    log('🚀 SCRAPER ANNONCES.NC - PYTHON LAUNCHER')
    log('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━')
    
    log('📋 Chargement configuration...')
    config = load_config()
    log(f'   Email: {config["email"]}')
    log(f'   API: {config["apiUrl"]}')
    log(f'   Max conversations: {config["maxConversations"]}')
    log(f'   Smart stop: {config["smartStop"]}')
    if config["smartStop"]:
        log(f'   Collision threshold: {config["collisionThreshold"]}')
    
    db_name = config.get('db_name')
    if not db_name:
        if args.config:
            username = Path(args.config).stem.replace('temp_', '')
            db_name = f'annonces_messages_{username}'
        else:
            db_name = 'annonces_messages_default'
    
    log(f'   Database: {db_name}')
    
    log('📜 Chargement scripts JS...')
    login_js = load_script(LOGIN_JS)
    scraper_js = load_script(SCRAPER_JS)
    
    # Modifier scraper.js pour ajouter le header X-User-Database
    scraper_js_modified = scraper_js.replace(
        "headers: { 'Content-Type': 'application/json' }",
        f"headers: {{ 'Content-Type': 'application/json', 'X-User-Database': '{db_name}' }}"
    )
    
    mode = 'HEADLESS' if headless else 'HEADFUL'
    log(f'🌐 Lancement {browser_type.upper()} ({mode})...')
    
    with sync_playwright() as p:
        if browser_type == 'firefox':
            browser = p.firefox.launch(headless=headless)
        else:
            browser = p.chromium.launch(
                headless=headless,
                args=['--disable-web-security', '--disable-features=IsolateOrigins,site-per-process']
            )
        
        context = browser.new_context(
            viewport={'width': 1920, 'height': 1080},
            user_agent='Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36',
            storage_state=None
        )
        page = context.new_page()
        
        def on_console(msg):
            ts = time.strftime('%Y-%m-%d %H:%M:%S')
            text = msg.text
            if text.startswith('[2'):
                print(text, flush=True)
            elif text.startswith('[LOGIN]') or text.startswith('[SCRAPER]') or text.startswith('[PYTHON]'):
                print(f'[{ts}]{text}', flush=True)
            else:
                print(f'[{ts}][JS] {text}', flush=True)

        page.on("console", on_console)
        
        try:
            log(f'🔗 Navigation vers {TARGET_URL}...')
            page.goto(TARGET_URL, wait_until='domcontentloaded', timeout=30000)
            log('✅ Page chargée')
            time.sleep(2)
            
            log('💉 Injection config dans localStorage...')
            inject_config(page, config)
            
            # ========== ÉTAPE 1 : LOGIN ==========
            log('')
            log('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━')
            log('🔐 ÉTAPE 1/2 : LOGIN')
            log('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━')
            
            login_result = page.evaluate(login_js)
            
            if not login_result.get('success'):
                error(f'Échec login: {login_result.get("message")}')
                if headless:
                    screenshot_path = SCRAPER_DIR / f'error-login-{db_name}.png'
                    page.screenshot(path=str(screenshot_path))
                return False
            
            log(f'✅ Login: {login_result.get("message")}')
            
            if login_result.get('status') == 'logged_in':
                log('⏳ Attente stabilisation après login (5s)...')
                time.sleep(5)
                log('💉 Ré-injection config...')
                inject_config(page, config)
            else:
                time.sleep(2)
            
            # ========== ÉTAPE 2 : SCRAPING ==========
            log('')
            log('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━')
            log('📊 ÉTAPE 2/2 : SCRAPING')
            log('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━')
            
            scraper_result = page.evaluate(scraper_js_modified)
            
            if not scraper_result.get('success'):
                error(f'Échec scraping: {scraper_result.get("error")}')
                if headless:
                    screenshot_path = SCRAPER_DIR / f'error-scraper-{db_name}.png'
                    page.screenshot(path=str(screenshot_path))
                return False
            
            log('')
            log('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━')
            log('✨ RÉSUMÉ')
            log('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━')
            log(f'Total conversations: {scraper_result.get("total", 0)}')
            log(f'Nouveaux messages: {scraper_result.get("total_new_messages", 0)}')
            log(f'Succès: {scraper_result.get("succeeded", 0)}')
            log(f'Échecs: {scraper_result.get("failed", 0)}')
            log(f'Arrêt: {scraper_result.get("stop_reason", "fin normale")}')
            
            send_telegram_notification(config, scraper_result)
            
            return True
            
        except PlaywrightTimeout as e:
            error(f'Timeout: {e}')
            if headless:
                screenshot_path = SCRAPER_DIR / f'error-timeout-{db_name}.png'
                page.screenshot(path=str(screenshot_path))
            return False
            
        except Exception as e:
            error(f'Erreur: {e}')
            if headless:
                screenshot_path = SCRAPER_DIR / f'error-exception-{db_name}.png'
                page.screenshot(path=str(screenshot_path))
            return False
        
        finally:
            if not headless:
                log('⏸️  Appuyez sur Entrée pour fermer...')
                input()
            browser.close()

# ========== CLI ==========
if __name__ == '__main__':
    headless = not args.headful
    browser_type = 'firefox' if args.firefox else 'chromium'
    
    success = run_scraper(headless=headless, browser_type=browser_type)
    
    if args.config and Path(args.config).stem.startswith('temp_'):
        try:
            Path(args.config).unlink()
            log(f'🧹 Config temporaire supprimée: {args.config}')
        except:
            pass
    
    sys.exit(0 if success else 1)