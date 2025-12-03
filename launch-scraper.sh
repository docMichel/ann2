#!/bin/bash
set -e

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
CONFIG_FILE="$1"
LOG_FILE="$2"
LOCK_FILE="$3"

if [ -z "$CONFIG_FILE" ] || [ -z "$LOG_FILE" ] || [ -z "$LOCK_FILE" ]; then
    echo "Usage: $0 CONFIG_FILE LOG_FILE LOCK_FILE" >&2
    exit 1
fi

if [ ! -f "$CONFIG_FILE" ]; then
    echo "❌ Config file not found: $CONFIG_FILE" >&2
    exit 1
fi

log() {
    echo "[$(date '+%Y-%m-%d %H:%M:%S')][BASH] $1" >> "$LOG_FILE"
}

find_python() {
    if [ -x "$SCRIPT_DIR/venv/bin/python" ]; then
        echo "$SCRIPT_DIR/venv/bin/python"
        return 0
    fi
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
    log "❌ No Python found"
    exit 1
fi

log "Python: $PYTHON_BIN"

if ! $PYTHON_BIN -c "import playwright" 2>/dev/null; then
    log "❌ playwright module not found"
    exit 1
fi

log "Dependencies OK"
log "Lancement scraper..."

cd "$SCRIPT_DIR"
nohup $PYTHON_BIN -u sync.py --config="$CONFIG_FILE" >> "$LOG_FILE" 2>&1 &
PID=$!

echo $PID > "$LOCK_FILE"
log "Scraper PID: $PID"

sleep 2
if ! kill -0 $PID 2>/dev/null; then
    log "❌ Process died immediately"
    exit 1
fi

log "Process running"
exit 0