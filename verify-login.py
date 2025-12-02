#!/usr/bin/env python3
import sys
import json
from pathlib import Path
from playwright.sync_api import sync_playwright

SCRIPT_DIR = Path(__file__).parent
config_file = sys.argv[1]

with open(config_file) as f:
    config = json.load(f)

with open(SCRIPT_DIR / 'login.js') as f:
    login_js = f.read()

with sync_playwright() as p:
    browser = p.chromium.launch(headless=True)
    page = browser.new_page()
    page.goto('https://annonces.nc/dashboard/conversations', timeout=30000)
    
    from time import sleep
    sleep(2)
    
    config_json = json.dumps(config).replace('\\', '\\\\').replace("'", "\\'")
    page.evaluate(f"localStorage.setItem('SCRAPER_CONFIG', '{config_json}');")
    
    result = page.evaluate(login_js)
    
    browser.close()
    
    print(json.dumps(result))
    sys.exit(0 if result.get('success') else 1)