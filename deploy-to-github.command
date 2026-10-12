#!/bin/zsh
# Double-click to push OrbitDesk to GitHub (repo: plutodx/orbitdesk).
# Create the empty repo on GitHub first (Claude will walk you through it).
cd "$(dirname "$0")"

source "$HOME/.zprofile" 2>/dev/null
source "$HOME/.zshrc" 2>/dev/null
export PATH="/opt/homebrew/bin:/usr/local/bin:/usr/bin:/bin:$PATH"

echo "==============================================="
echo "   Pushing OrbitDesk to GitHub"
echo "==============================================="

command -v git >/dev/null 2>&1 || { echo "ERROR: git is not installed."; echo; read "?Press Return to close..."; exit 1; }

# Clear any stale lock from a crashed run.
rm -f .git/index.lock 2>/dev/null

# First run: initialise the repo.
if [ ! -d .git ]; then
  git init
  git branch -M main
fi

# Make sure git knows who is committing (required, or the commit silently fails).
if [ -z "$(git config user.email)" ]; then
  git config user.email "joy@luxurysocalrealty.com"
  git config user.name "OrbitDesk"
fi

# Point at the GitHub repo if it is not set yet.
if ! git remote | grep -q "^origin$"; then
  git remote add origin https://github.com/plutodx/orbitdesk.git
fi

git add -A
git commit -m "Deploy OrbitDesk to the web ($(date '+%Y-%m-%d %H:%M'))" || echo "(nothing new to commit)"

echo
echo ">> Pushing..."
if git push -u origin main; then
  echo
  echo "SUCCESS. GitHub is updated. Now Railway can deploy it."
else
  echo
  echo "Normal push was rejected. Replacing the online code with this copy..."
  if git push -u --force origin main; then
    echo
    echo "SUCCESS (forced). GitHub is updated."
  else
    echo
    echo "PUSH FAILED. Usually this means git on this Mac is not signed in to GitHub,"
    echo "or the repo plutodx/orbitdesk has not been created yet."
    echo "Create the empty repo at https://github.com/new (name it orbitdesk), then run this again."
  fi
fi

echo
echo "After it says SUCCESS, go back to Claude and say 'pushed'."
read "?Press Return to close this window..."
