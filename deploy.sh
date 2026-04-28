#!/usr/bin/env bash
set -euo pipefail

# ── Load config ───────────────────────────────────────────────────────────────
SCRIPT_DIR="$(cd "$(dirname "$0")" && pwd)"
ENV_FILE="$SCRIPT_DIR/.env.deploy"

if [[ ! -f "$ENV_FILE" ]]; then
  echo "Error: .env.deploy not found. Copy it and fill in your Hostinger SSH details."
  exit 1
fi

while IFS= read -r line; do
  [[ "$line" =~ ^#.*$ || -z "${line// }" ]] && continue
  key="${line%%=*}"
  val="${line#*=}"
  val="${val%%#*}"      # strip inline comments
  val="${val//[[:space:]]/}"  # strip spaces
  [[ -n "$key" && -n "$val" ]] && export "$key=$val"
done < "$ENV_FILE"

# ── Validate ──────────────────────────────────────────────────────────────────
for var in SSH_HOST SSH_PORT SSH_USER SSH_PASS REMOTE_PATH; do
  if [[ -z "${!var:-}" ]]; then
    echo "Error: $var is not set in .env.deploy"
    exit 1
  fi
done

REMOTE_THEME="$REMOTE_PATH/wp-content/themes/sb-marketing-theme"

# ── Deploy ────────────────────────────────────────────────────────────────────
echo "Deploying sb-marketing-theme → $SSH_USER@$SSH_HOST:$REMOTE_THEME"
echo "────────────────────────────────────────────────────────"

sshpass -p "$SSH_PASS" rsync \
  --archive \
  --compress \
  --human-readable \
  --itemize-changes \
  --delete \
  --exclude="*.DS_Store" \
  --exclude=".git/" \
  -e "ssh -p $SSH_PORT -o StrictHostKeyChecking=no" \
  "$SCRIPT_DIR/sb-marketing-theme/" \
  "$SSH_USER@$SSH_HOST:$REMOTE_THEME/"

echo "────────────────────────────────────────────────────────"
echo "Done."
