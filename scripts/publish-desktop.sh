#!/usr/bin/env bash
# ═══════════════════════════════════════════════════════════════════
# PrimeGest — Publication de la version desktop téléchargeable
# ───────────────────────────────────────────────────────────────────
# Construit (ou réutilise) l'installateur Windows via GitHub Actions,
# récupère l'artefact .exe et le place dans public/downloads/ pour
# qu'il soit téléchargeable depuis la page d'accueil.
#
# Usage :
#   bash scripts/publish-desktop.sh                 # build version par défaut puis publie
#   bash scripts/publish-desktop.sh 1.0.7           # build la version 1.0.7 puis publie
#   bash scripts/publish-desktop.sh --no-build      # publie le dernier build réussi sans en relancer un
#   bash scripts/publish-desktop.sh --no-build 1.0.7
#
# Prérequis : gh (GitHub CLI) authentifié — vérifier avec `gh auth status`.
# ═══════════════════════════════════════════════════════════════════

set -euo pipefail

# Répertoire du projet = parent du dossier scripts/
PROJECT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
WORKFLOW="build-desktop.yml"
ARTIFACT_NAME="PrimeGest-Windows-Setup"
DEST="$PROJECT_DIR/public/downloads/PrimeGest-Setup.exe"
TMP_DIR="$(mktemp -d /tmp/pg-win.XXXXXX)"

cd "$PROJECT_DIR"

log()  { printf '\n\033[1;34m▶ %s\033[0m\n' "$*"; }
ok()   { printf '\033[1;32m✔ %s\033[0m\n' "$*"; }
warn() { printf '\033[1;33m⚠ %s\033[0m\n' "$*" >&2; }
die()  { printf '\033[1;31m✗ %s\033[0m\n' "$*" >&2; exit 1; }

# Nettoyage du dossier temporaire en sortie
trap 'rm -rf "$TMP_DIR"' EXIT

# ── Analyse des arguments ──────────────────────────────────────────
DO_BUILD=1
VERSION="1.0.0"
for arg in "$@"; do
    case "$arg" in
        --no-build) DO_BUILD=0 ;;
        --help|-h)  sed -n '2,20p' "$0"; exit 0 ;;
        *)          VERSION="$arg" ;;
    esac
done

# ── Vérifications préalables ───────────────────────────────────────
command -v gh >/dev/null 2>&1 || die "GitHub CLI (gh) introuvable. Installer : https://cli.github.com"
gh auth status >/dev/null 2>&1 || die "gh non authentifié. Lancer : gh auth login"

# ── Étape 1 — Lancer le build (sauf --no-build) ────────────────────
if [[ "$DO_BUILD" -eq 1 ]]; then
    log "Lancement du build Windows (version $VERSION)"
    gh workflow run "$WORKFLOW" -f version="$VERSION"

    # Laisse à GitHub le temps d'enregistrer le run avant de le suivre
    sleep 8

    RUN_ID="$(gh run list --workflow="$WORKFLOW" --event workflow_dispatch \
              --limit 1 --json databaseId --jq '.[0].databaseId')"
    [[ -n "$RUN_ID" ]] || die "Impossible de récupérer l'ID du run déclenché."

    log "Suivi du build (run #$RUN_ID) — environ 5 min…"
    gh run watch "$RUN_ID" --exit-status || die "Le build a échoué. Voir : gh run view $RUN_ID --log-failed"
    ok "Build terminé avec succès"
else
    warn "Mode --no-build : réutilisation du dernier build réussi"
    RUN_ID="$(gh run list --workflow="$WORKFLOW" --status success \
              --limit 1 --json databaseId --jq '.[0].databaseId')"
    [[ -n "$RUN_ID" ]] || die "Aucun build réussi trouvé. Relancer sans --no-build."
fi

# ── Étape 2 — Télécharger l'artefact ───────────────────────────────
log "Téléchargement de l'artefact (run #$RUN_ID)"
if ! gh run download "$RUN_ID" -n "$ARTIFACT_NAME" -D "$TMP_DIR" 2>/dev/null; then
    die "Artefact '$ARTIFACT_NAME' indisponible (expiré après 30 j ?). Relancer un build sans --no-build."
fi

EXE="$(find "$TMP_DIR" -name '*.exe' -print -quit)"
[[ -n "$EXE" ]] || die "Aucun .exe dans l'artefact téléchargé."

# ── Étape 3 — Installer dans public/downloads/ ─────────────────────
log "Installation dans public/downloads/"
mkdir -p "$(dirname "$DEST")"
cp "$EXE" "$DEST"

# Vérifie que c'est bien un exécutable Windows
if ! file "$DEST" | grep -qi 'MS Windows'; then
    warn "Le fichier copié ne semble pas être un installateur Windows :"
    file "$DEST"
fi

SIZE="$(du -h "$DEST" | cut -f1)"
ok "Installateur publié : $DEST ($SIZE)"

# ── Étape 4 — Recompiler le frontend (idempotent) ──────────────────
# Nécessaire uniquement si Home.vue a changé, mais sans risque sinon.
log "Recompilation du frontend (npm run build)"
npm run build

ok "✅ Version desktop publiée — téléchargeable sur la page d'accueil"
printf '\n   Vérifier : curl -sI https://primegest.app/downloads/PrimeGest-Setup.exe | head -1\n\n'
