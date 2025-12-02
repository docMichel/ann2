#!/usr/bin/env python3
"""
Scraper Annonces.nc - Automatisé avec Playwright
Usage:
    python3 sync.py                    # Headless (production)
    python3 sync.py --headful          # Visible (debug)
    python3 sync.py --firefox          # Utiliser Firefox au lieu de Chromium
"""

import sys
import json
import time
from pathlib import Path
from playwright.sync_api import sync_playwright, TimeoutError as PlaywrightTimeout

# ========== CONFIG ==========
SCRAPER_DIR = Path(__file__).parent
CONFIG_FILE = SCRAPER_DIR / 'scraper-config.json'
LOGIN_JS = SCRAPER_DIR / 'login.js'
SCRAPER_JS = SCRAPER_DIR / 'scraper.js'
TARGET_URL = 'https://annonces.nc/dashboard/conversations'

# ========== HELPERS ==========
def log(msg):
    print(f'[PYTHON] {msg}')

def error(msg):
    print(f'[PYTHON] ❌ {msg}', file=sys.stderr)

def load_config():
    """Charge la config depuis JSON"""
    if not CONFIG_FILE.exists():
        error(f'Config manquante: {CONFIG_FILE}')
        error('Créez-la avec: python3 edit-config.py set-creds "email" "password"')
        sys.exit(1)
    
    with open(CONFIG_FILE) as f:
        config = json.load(f)
    
    if not config.get('email') or not config.get('password'):
        error('Credentials manquants dans config')
        error('Utilisez: python3 edit-config.py set-creds "email" "password"')
        sys.exit(1)
    
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
    # Échapper les quotes et backslashes pour JavaScript
    config_escaped = config_json.replace('\\', '\\\\').replace("'", "\\'")
    
    page.evaluate(f"""
        localStorage.setItem('SCRAPER_CONFIG', '{config_escaped}');
        console.log('[PYTHON] Config injectée dans localStorage');
    """)

# ========== MAIN ==========
def run_scraper(headless=True, browser_type='chromium'):
    """
    Lance le scraper en 2 étapes : login puis scraping
    
    Args:
        headless: True = invisible, False = visible
        browser_type: 'firefox' ou 'chromium'
    """
    
    log('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━')
    log('🚀 SCRAPER ANNONCES.NC - PYTHON LAUNCHER')
    log('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━')
    
    # 1. Charger config et scripts
    log('📋 Chargement configuration...')
    config = load_config()
    log(f'   Email: {config["email"]}')
    log(f'   API: {config["apiUrl"]}')
    log(f'   Max conversations: {config["maxConversations"]}')
    
    log('📜 Chargement scripts JS...')
    login_js = load_script(LOGIN_JS)
    scraper_js = load_script(SCRAPER_JS)
    log(f'   login.js: {len(login_js)} caractères')
    log(f'   scraper.js: {len(scraper_js)} caractères')
    
    # 2. Lancer navigateur
    mode = 'HEADLESS' if headless else 'HEADFUL'
    log(f'🌐 Lancement {browser_type.upper()} ({mode})...')
    
    with sync_playwright() as p:
        # Choisir le navigateur
        if browser_type == 'firefox':
            browser = p.firefox.launch(headless=headless)
        else:    # Chromium avec désactivation CORS pour dev
            browser = p.chromium.launch(
                headless=headless,
                args=[
                    '--disable-web-security',
                    '--disable-features=IsolateOrigins,site-per-process'
                ]
            
            )
        # Créer contexte
        context = browser.new_context(
            viewport={'width': 1920, 'height': 1080},
            user_agent='Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36'
        )
        page = context.new_page()
        
        # LISTENER CONSOLE - Version correcte selon la doc Playwright
        page.on("console", lambda msg: print(msg.text))
        
        try:
            # 3. Navigation
            log(f'🔗 Navigation vers {TARGET_URL}...')
            page.goto(TARGET_URL, wait_until='domcontentloaded', timeout=30000)
            log('✅ Page chargée')
            
            # Attendre que le DOM soit stable
            time.sleep(2)
            
            # 4. Injecter config
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
                error(f'Status: {login_result.get("status")}')
                
                # Screenshot en cas d'erreur
                if headless:
                    screenshot_path = SCRAPER_DIR / 'error-login.png'
                    page.screenshot(path=str(screenshot_path))
                    log(f'📸 Screenshot sauvegardé: {screenshot_path}')
                
                return False
            
            log(f'✅ Login: {login_result.get("message")} (status: {login_result.get("status")})')
            
            # Si login a eu lieu, attendre stabilisation (redirection possible)
            if login_result.get('status') == 'logged_in':
                log('⏳ Attente stabilisation après login (5s)...')
                time.sleep(5)
                
                # Ré-injecter config au cas où (la page peut avoir été rechargée)
                log('💉 Ré-injection config (sécurité)...')
                inject_config(page, config)
            else:
                # Déjà connecté, attente courte
                log('⏳ Attente stabilisation (2s)...')
                time.sleep(2)
            
            # ========== ÉTAPE 2 : SCRAPING ==========
            log('')
            log('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━')
            log('📊 ÉTAPE 2/2 : SCRAPING')
            log('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━')
            
            scraper_result = page.evaluate(scraper_js)
            
            if not scraper_result.get('success'):
                error_type = scraper_result.get('error', 'unknown')
                error(f'Échec scraping: {error_type}')
                
                # Screenshot en cas d'erreur
                if headless:
                    screenshot_path = SCRAPER_DIR / 'error-scraper.png'
                    page.screenshot(path=str(screenshot_path))
                    log(f'📸 Screenshot sauvegardé: {screenshot_path}')
                
                return False
            
            log('')
            log('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━')
            log('✨ RÉSUMÉ')
            log('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━')
            log(f'Total conversations: {scraper_result.get("total", 0)}')
            log(f'Succès: {scraper_result.get("succeeded", 0)}')
            log(f'Échecs: {scraper_result.get("failed", 0)}')
            
            return True
            
        except PlaywrightTimeout as e:
            error(f'Timeout: {e}')
            
            if headless:
                screenshot_path = SCRAPER_DIR / 'error-timeout.png'
                page.screenshot(path=str(screenshot_path))
                log(f'📸 Screenshot sauvegardé: {screenshot_path}')
            
            return False
            
        except Exception as e:
            error(f'Erreur: {e}')
            
            if headless:
                screenshot_path = SCRAPER_DIR / 'error-exception.png'
                page.screenshot(path=str(screenshot_path))
                log(f'📸 Screenshot sauvegardé: {screenshot_path}')
            
            return False
        
        finally:
            # Fermer navigateur
            if not headless:
                log('')
                log('⏸️  Navigateur visible, appuyez sur Entrée pour fermer...')
                input()
            
            browser.close()

# ========== CLI ==========
if __name__ == '__main__':
    # Parser arguments
    headless = '--headful' not in sys.argv
    browser_type = 'firefox' if '--firefox' in sys.argv else 'chromium'
    
    # Lancer
    success = run_scraper(headless=headless, browser_type=browser_type)
    
    # Exit code
    sys.exit(0 if success else 1)