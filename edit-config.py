#!/usr/bin/env python3
"""
Gestion de la configuration scraper
Usage:
    python3 edit-config.py show
    python3 edit-config.py set-creds "email@example.com" "password"
    python3 edit-config.py set-api "http://hub:8090/annonces/api.php?action=save"
    python3 edit-config.py set-max-convs 100
    python3 edit-config.py set-max-pages 5
    python3 edit-config.py set-timeout modal 2000
    python3 edit-config.py set-selector convList ".my-custom-selector"
    python3 edit-config.py export
    python3 edit-config.py import config.json
    python3 edit-config.py reset
"""

import sys
import json
from pathlib import Path

CONFIG_FILE = Path(__file__).parent / 'scraper-config.json'

DEFAULT_CONFIG = {
    "email": "",
    "password": "",
    "apiUrl": "http://localhost/ann2/api.php?action=save",
    "maxPages": 2,
    "maxConversations": 30,
    "timeouts": {
        "modal": 1500,
        "input": 200,
        "submit": 300,
        "loginSuccess": 5000,
        "xhrTimeout": 3000,
        "annonceModal": 1500,
        "betweenConvs": 300,
        "loadMore": 1000,
        "images": 500
    },
    "selectors": {
        "loginModal": "mat-dialog-container annonces-login",
        "loginEmail": "input[type=\"email\"]",
        "loginPassword": "input[type=\"password\"]",
        "loginSubmit": "button[type=\"submit\"]",
        "convList": ".conversations__sidebar__content > .clickable",
        "convTitle": ".text-dark.text-sm",
        "convUser": ".font-weight-normal.position-relative",
        "voirPlus": ".conversations__sidebar__content button.rounded-pill",
        "annonceBtn": "button.btn-primary.ml-2",
        "annonceDesc": ".mat-dialog-container .card-body .pre-wrap.text-justify",
        "annonceBadge": ".mat-dialog-container .badge.badge-light.text-sm",
        "annonceClose": ".mat-dialog-container .text-2x",
        "images": ".chat-content annonces-image img"
    }
}

def load_config():
    """Charge config ou crée si inexistante"""
    if not CONFIG_FILE.exists():
        print(f"⚠️  Config inexistante, création avec valeurs par défaut...")
        with open(CONFIG_FILE, 'w') as f:
            json.dump(DEFAULT_CONFIG, f, indent=2)
        print(f"✅ Config créée: {CONFIG_FILE}")
        return DEFAULT_CONFIG
    
    with open(CONFIG_FILE) as f:
        return json.load(f)

def save_config(config):
    """Sauvegarde config"""
    with open(CONFIG_FILE, 'w') as f:
        json.dump(config, f, indent=2)

def show_config():
    """Affiche la config"""
    config = load_config()
    print("━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━")
    print("📋 CONFIGURATION SCRAPER")
    print("━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━")
    print(json.dumps(config, indent=2))
    print("━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━")

def set_credentials(email, password):
    """Définit email et password"""
    config = load_config()
    config['email'] = email
    config['password'] = password
    save_config(config)
    print(f"✅ Credentials mis à jour")
    print(f"   Email: {email}")
    print(f"   Password: {'*' * len(password)}")

def set_api_url(url):
    """Définit l'URL de l'API"""
    config = load_config()
    config['apiUrl'] = url
    save_config(config)
    print(f"✅ API URL: {url}")

def set_max_conversations(max_convs):
    """Définit le nombre max de conversations"""
    config = load_config()
    config['maxConversations'] = int(max_convs)
    save_config(config)
    print(f"✅ Max conversations: {max_convs}")

def set_max_pages(max_pages):
    """Définit le nombre max de pages"""
    config = load_config()
    config['maxPages'] = int(max_pages)
    save_config(config)
    print(f"✅ Max pages: {max_pages}")

def set_timeout(timeout_name, value):
    """Définit un timeout spécifique"""
    config = load_config()
    
    # Vérifier que le timeout existe
    if timeout_name not in config['timeouts']:
        print(f"❌ Timeout '{timeout_name}' inconnu")
        print(f"Timeouts disponibles: {', '.join(config['timeouts'].keys())}")
        return
    
    config['timeouts'][timeout_name] = int(value)
    save_config(config)
    print(f"✅ Timeout '{timeout_name}': {value}ms")

def set_selector(selector_name, value):
    """Définit un sélecteur CSS spécifique"""
    config = load_config()
    
    # Vérifier que le sélecteur existe
    if selector_name not in config['selectors']:
        print(f"❌ Sélecteur '{selector_name}' inconnu")
        print(f"Sélecteurs disponibles: {', '.join(config['selectors'].keys())}")
        return
    
    config['selectors'][selector_name] = value
    save_config(config)
    print(f"✅ Sélecteur '{selector_name}': {value}")

def list_timeouts():
    """Liste tous les timeouts disponibles"""
    config = load_config()
    print("━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━")
    print("⏱️  TIMEOUTS DISPONIBLES")
    print("━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━")
    for name, value in config['timeouts'].items():
        print(f"  {name}: {value}ms")
    print("━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━")
    print(f"Modifier: python edit-config.py set-timeout <nom> <valeur>")

def list_selectors():
    """Liste tous les sélecteurs disponibles"""
    config = load_config()
    print("━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━")
    print("🎯 SÉLECTEURS CSS DISPONIBLES")
    print("━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━")
    for name, value in config['selectors'].items():
        print(f"  {name}:")
        print(f"    {value}")
    print("━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━")
    print(f"Modifier: python edit-config.py set-selector <nom> \"<valeur>\"")

def reset_config():
    """Reset la config aux valeurs par défaut"""
    save_config(DEFAULT_CONFIG)
    print("🔄 Config réinitialisée aux valeurs par défaut")
    show_config()

def export_config():
    """Exporte la config"""
    config = load_config()
    print("📤 EXPORT CONFIG:")
    print(json.dumps(config, indent=2))

def import_config(filepath):
    """Importe une config depuis un fichier"""
    import_path = Path(filepath)
    if not import_path.exists():
        print(f"❌ Fichier inexistant: {filepath}")
        return
    
    with open(import_path) as f:
        config = json.load(f)
    
    save_config(config)
    print(f"📥 Config importée depuis {filepath}")
    show_config()

def show_help():
    """Affiche l'aide"""
    print("""
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
📚 AIDE - GESTION CONFIG SCRAPER
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

AFFICHAGE:
  python edit-config.py show              Afficher la config complète
  python edit-config.py list-timeouts     Lister tous les timeouts
  python edit-config.py list-selectors    Lister tous les sélecteurs

CONFIGURATION DE BASE:
  python edit-config.py set-creds "email@example.com" "password"
  python edit-config.py set-api "http://hub/annonces/api.php?action=save"
  python edit-config.py set-max-convs 100
  python edit-config.py set-max-pages 5

TIMEOUTS (en millisecondes):
  python edit-config.py set-timeout modal 2000
  python edit-config.py set-timeout loginSuccess 10000
  
  Timeouts disponibles:
    - modal, input, submit, loginSuccess
    - xhrTimeout, annonceModal, betweenConvs
    - loadMore, images

SÉLECTEURS CSS:
  python edit-config.py set-selector convList ".my-custom-selector"
  python edit-config.py set-selector loginModal "my-login-modal"
  
  Sélecteurs disponibles:
    - loginModal, loginEmail, loginPassword, loginSubmit
    - convList, convTitle, convUser, voirPlus
    - annonceBtn, annonceDesc, annonceBadge, annonceClose
    - images

IMPORT/EXPORT:
  python edit-config.py export                 Exporter la config en JSON
  python edit-config.py import config.json     Importer une config

RESET:
  python edit-config.py reset                  Réinitialiser aux défauts

━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
    """)

if __name__ == '__main__':
    if len(sys.argv) < 2:
        show_help()
        sys.exit(1)
    
    cmd = sys.argv[1]
    
    if cmd == 'show':
        show_config()
    elif cmd == 'list-timeouts':
        list_timeouts()
    elif cmd == 'list-selectors':
        list_selectors()
    elif cmd == 'set-creds' and len(sys.argv) == 4:
        set_credentials(sys.argv[2], sys.argv[3])
    elif cmd == 'set-api' and len(sys.argv) == 3:
        set_api_url(sys.argv[2])
    elif cmd == 'set-max-convs' and len(sys.argv) == 3:
        set_max_conversations(sys.argv[2])
    elif cmd == 'set-max-pages' and len(sys.argv) == 3:
        set_max_pages(sys.argv[2])
    elif cmd == 'set-timeout' and len(sys.argv) == 4:
        set_timeout(sys.argv[2], sys.argv[3])
    elif cmd == 'set-selector' and len(sys.argv) == 4:
        set_selector(sys.argv[2], sys.argv[3])
    elif cmd == 'export':
        export_config()
    elif cmd == 'import' and len(sys.argv) == 3:
        import_config(sys.argv[2])
    elif cmd == 'reset':
        reset_config()
    else:
        print("❌ Commande invalide")
        show_help()
        sys.exit(1)