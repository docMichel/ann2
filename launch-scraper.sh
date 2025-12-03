#!/bin/bash

#
# SCRAPER LAUNCHER - Portable Mac/Ubuntu
# 
# Usage: ./launch-scraper.sh CONFIG_FILE LOG_FILE LOCK_FILE
#

set -e  # Exit on error

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
CONFIG_FILE="$1"
LOG_FILE="$2"
LOCK_FILE="$3"

# ========== VALIDATION ==========

if [ -z "$CONFIG_FILE" ] || [ -z "$LOG_FILE" ] || [ -z "$LOCK_FILE" ]; then
    echo "Usage: $0 CONFIG_FILE LOG_FILE LOCK_FILE" >&2
    exit 1
fi

if [ ! -f "$CONFIG_FILE" ]; then
    echo "❌ Config file not found: $CONFIG_FILE" >&2
    exit 1
fi

# ========== LOGGING ==========

log() {
    echo "[$(date '+%Y-%m-%d %H:%M:%S')] $1" | tee -a "$LOG_FILE"
}

log_error() {
    echo "[$(date '+%Y-%m-%d %H:%M:%S')] ❌ ERROR: $1" | tee -a "$LOG_FILE" >&2
}

# ========== FIND PYTHON ==========

find_python() {
    # Try venv first
    if [ -x "$SCRIPT_DIR/venv/bin/python" ]; then
        echo "$SCRIPT_DIR/venv/bin/python"
        return 0
    fi
    
    # Fallback to system python
    if command -v python3 &> /dev/null; then
        echo "python3"
        return 0
    fi
    
    if command -v python &> /dev/null; then
        echo "python"
        return 0
    fi
    
    return 1
}

PYTHON_BIN=$(find_python)
if [ $? -ne 0 ]; then
    log_error "No Python found (tried venv/bin/python, python3, python)"
    exit 1
fi

log "🐍 Python: $PYTHON_BIN"

# ========== CHECK DEPENDENCIES ==========

log "🔍 Checking dependencies..."

if ! $PYTHON_BIN -c "import playwright" 2>/dev/null; then
    log_error "playwright module not found"
    log "💡 Install with: pip install playwright && playwright install chromium"
    exit 1
fi

log "✅ Dependencies OK"

# ========== LAUNCH SCRAPER ==========

log "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
log "🚀 LAUNCHING SCRAPER"
log "   Config: $(basename $CONFIG_FILE)"
log "   Log: $(basename $LOG_FILE)"
log "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"

# Launch in background with nohup (survive parent death)
cd "$SCRIPT_DIR"
nohup $PYTHON_BIN sync.py --config="$CONFIG_FILE" >> "$LOG_FILE" 2>&1 &
PID=$!

# Save PID to lock file
echo $PID > "$LOCK_FILE"

log "✅ Scraper started with PID: $PID"
log "📊 Monitor logs: tail -f $LOG_FILE"
log "🛑 Stop scraper: kill $PID"

# Verify process started
sleep 2
if ! kill -0 $PID 2>/dev/null; then
    log_error "Process died immediately after launch"
    exit 1
fi

log "✅ Process running"

exit 0