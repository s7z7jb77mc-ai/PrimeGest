# ═══════════════════════════════════════════════════════════════════
# PROMPT DESKTOP WINDOWS — CLAUDE CODE / PRIMEGEST
# Transformer PrimeGest web en application desktop Windows (.exe)
# avec Tauri 2 + offline-first SQLite
# ═══════════════════════════════════════════════════════════════════

Tu es un expert Tauri 2, Laravel et Vue.js 3.
Tu vas transformer PrimeGest (actuellement une app web) en application
desktop Windows native (.exe) avec mode offline-first complet.

Avant de commencer, lis ces fichiers :
1. CLAUDE.md
2. .claude/skills/SKILL_DATABASE.md
3. .claude/skills/SKILL_OFFLINE.md
4. docs/OFFLINE_FIRST_SPEC.md

---

## OBJECTIF

Produire un installateur Windows (.exe) de PrimeGest qui :
- Fonctionne 100% sans internet (offline-first SQLite)
- Se synchronise automatiquement avec primegest.app quand internet dispo
- S'installe comme une vraie application Windows (menu Démarrer, icône)
- Se met à jour automatiquement en silence
- Tourne sur Windows 10 et Windows 11 (64 bits)

---

## ARCHITECTURE CIBLE

```
PrimeGest Desktop Windows
│
├── Tauri 2 (wrapper Rust)
│   ├── Fenêtre native Windows
│   ├── Barre de titre personnalisée PrimeGest
│   ├── Icône dans la barre des tâches
│   ├── Auto-updater silencieux
│   └── Communication avec le backend local
│
├── Frontend Vue.js 3 (inchangé)
│   └── Servi par Tauri (pas de navigateur)
│
├── Backend Laravel 11 (embarqué)
│   ├── Tourne sur 127.0.0.1:8888
│   ├── Lancé automatiquement au démarrage
│   └── PHP 8.2 embarqué dans l'installateur
│
└── SQLite local
    └── %APPDATA%\PrimeGest\primegest.db
```

---

## ÉTAPE 1 — INSTALLER TAURI 2

Commence par vérifier les prérequis et installer Tauri :

```bash
# Vérifier que Rust est installé
rustc --version

# Si pas installé, afficher le message :
# "Rust n'est pas installé. L'utilisateur doit installer depuis https://rustup.rs"

# Installer les dépendances Tauri CLI
npm install --save-dev @tauri-apps/cli@^2.0.0
npm install @tauri-apps/api@^2.0.0

# Initialiser Tauri dans le projet existant
npx tauri init
```

Lors du `tauri init`, utiliser ces réponses :
- App name : `PrimeGest`
- Window title : `PrimeGest`
- Web assets path : `../public/build`
- Dev server URL : `http://127.0.0.1:8888`
- Frontend dev command : `npm run dev`
- Frontend build command : `npm run build`

---

## ÉTAPE 2 — CONFIGURATION TAURI

Créer/remplacer le fichier `src-tauri/tauri.conf.json` :

```json
{
  "productName": "PrimeGest",
  "version": "1.0.0",
  "identifier": "app.primegest.desktop",

  "build": {
    "frontendDist": "../public/build",
    "devUrl": "http://127.0.0.1:8888",
    "beforeDevCommand": "npm run dev",
    "beforeBuildCommand": "npm run build"
  },

  "app": {
    "windows": [
      {
        "title": "PrimeGest",
        "width": 1280,
        "height": 800,
        "minWidth": 1024,
        "minHeight": 600,
        "resizable": true,
        "fullscreen": false,
        "decorations": true,
        "center": true
      }
    ],
    "security": {
      "csp": null
    }
  },

  "bundle": {
    "active": true,
    "targets": ["nsis"],
    "icon": [
      "icons/32x32.png",
      "icons/128x128.png",
      "icons/128x128@2x.png",
      "icons/icon.icns",
      "icons/icon.ico"
    ],
    "windows": {
      "certificateThumbprint": null,
      "digestAlgorithm": "sha256",
      "timestampUrl": ""
    },
    "nsis": {
      "license": "LICENSE",
      "headerImage": "icons/installer-header.bmp",
      "sidebarImage": "icons/installer-sidebar.bmp",
      "installMode": "perMachine",
      "languages": ["French"],
      "displayLanguageSelector": false,
      "shortcutsEnabled": true,
      "createDesktopShortcut": true,
      "createStartMenuShortcut": true,
      "startMenuFolder": "PrimeGest"
    },
    "publisher": "PrimeGest",
    "copyright": "Copyright © 2026 PrimeGest",
    "category": "Business",
    "shortDescription": "Application de gestion commerciale pour PME",
    "longDescription": "PrimeGest est une application de gestion commerciale complète pour les petites et moyennes entreprises d'Afrique Centrale."
  },

  "plugins": {
    "updater": {
      "active": true,
      "endpoints": [
        "https://primegest.app/releases/{{target}}/{{arch}}/{{current_version}}"
      ],
      "dialog": false,
      "pubkey": ""
    },
    "shell": {
      "open": true
    }
  }
}
```

---

## ÉTAPE 3 — ICÔNES WINDOWS

Créer le dossier `src-tauri/icons/` et générer les icônes :

```bash
# Générer toutes les tailles d'icônes depuis une image source 512x512
npx tauri icon src-tauri/icons/source-512x512.png
```

L'icône source doit être :
- Format PNG
- 512×512 pixels minimum
- Fond transparent ou bleu PrimeGest (#1A56A0)
- Logo PrimeGest centré (le carré bleu avec la grille 2x2)

---

## ÉTAPE 4 — LANCEMENT DU BACKEND LARAVEL LOCAL

Tauri doit démarrer PHP + Laravel au lancement de l'app.

Créer `src-tauri/src/main.rs` avec la logique de démarrage :

```rust
// src-tauri/src/main.rs

#![cfg_attr(not(debug_assertions), windows_subsystem = "windows")]

use std::process::{Command, Child};
use std::sync::Mutex;
use tauri::Manager;

struct PhpServer(Mutex<Option<Child>>);

fn start_laravel(app_data_dir: &str) -> Child {
    // Chemin vers PHP embarqué
    let php_bin = if cfg!(debug_assertions) {
        "php".to_string()
    } else {
        format!("{}\\php\\php.exe", std::env::current_exe()
            .unwrap()
            .parent()
            .unwrap()
            .display())
    };

    Command::new(&php_bin)
        .args([
            "-S", "127.0.0.1:8888",
            "-t", "public",
            "server.php",
        ])
        .current_dir(get_laravel_path())
        .spawn()
        .expect("Impossible de démarrer le serveur Laravel")
}

fn get_laravel_path() -> String {
    if cfg!(debug_assertions) {
        // En développement : dossier du projet
        std::env::current_dir().unwrap().to_str().unwrap().to_string()
    } else {
        // En production : dans les ressources Tauri
        format!("{}\\resources\\laravel",
            std::env::current_exe().unwrap().parent().unwrap().display())
    }
}

fn main() {
    tauri::Builder::default()
        .manage(PhpServer(Mutex::new(None)))
        .setup(|app| {
            let app_data_dir = app.path()
                .app_data_dir()
                .unwrap()
                .to_str()
                .unwrap()
                .to_string();

            // Démarrer Laravel en arrière-plan
            let child = start_laravel(&app_data_dir);
            *app.state::<PhpServer>().0.lock().unwrap() = Some(child);

            // Attendre que Laravel soit prêt (max 10 secondes)
            let mut ready = false;
            for _ in 0..20 {
                std::thread::sleep(std::time::Duration::from_millis(500));
                if reqwest::blocking::get("http://127.0.0.1:8888/api/health").is_ok() {
                    ready = true;
                    break;
                }
            }

            if !ready {
                eprintln!("Laravel n'a pas démarré dans les délais");
            }

            Ok(())
        })
        .on_window_event(|window, event| {
            if let tauri::WindowEvent::CloseRequested { .. } = event {
                // Arrêter PHP proprement à la fermeture
                if let Some(mut child) = window.state::<PhpServer>().0.lock().unwrap().take() {
                    let _ = child.kill();
                }
            }
        })
        .run(tauri::generate_context!())
        .expect("Erreur au lancement de PrimeGest");
}
```

Ajouter `reqwest` dans `src-tauri/Cargo.toml` :

```toml
[dependencies]
tauri = { version = "2", features = ["updater", "shell-open"] }
reqwest = { version = "0.11", features = ["blocking"] }

[build-dependencies]
tauri-build = { version = "2", features = [] }
```

---

## ÉTAPE 5 — CONFIGURATION LARAVEL POUR LE DESKTOP

Modifier `.env` pour le mode desktop Windows :

```env
# ── Mode Desktop Windows ─────────────────────────────────────────
APP_NAME=PrimeGest
APP_ENV=desktop
APP_KEY=base64:VOTRE_CLE
APP_DEBUG=false
APP_URL=http://127.0.0.1:8888
APP_TIMEZONE=Africa/Lubumbashi
APP_LOCALE=fr

# ── SQLite local (offline-first) ─────────────────────────────────
DB_CONNECTION=sqlite
# Le chemin sera défini dynamiquement via AppServiceProvider

# ── Pas de Redis en desktop — tout en fichier ─────────────────────
CACHE_STORE=file
SESSION_DRIVER=file
QUEUE_CONNECTION=sync

# ── Email désactivé en local (sync via cloud) ─────────────────────
MAIL_MAILER=log

# ── Stockage local ────────────────────────────────────────────────
FILESYSTEM_DISK=local

# ── Cloud pour la synchronisation ─────────────────────────────────
CLOUD_API_URL=https://primegest.app/api/v1/sync
CLOUD_HEALTH_URL=https://primegest.app/api/health

# ── Logs ─────────────────────────────────────────────────────────
LOG_CHANNEL=daily
LOG_LEVEL=error
LOG_DAYS=7
```

Modifier `app/Providers/AppServiceProvider.php` pour gérer
le chemin SQLite dynamiquement selon Windows :

```php
public function boot(): void
{
    // Définir le chemin SQLite dans %APPDATA%\PrimeGest\
    $sqlitePath = $this->getSqlitePath();

    // Créer le dossier si inexistant
    if (!file_exists(dirname($sqlitePath))) {
        mkdir(dirname($sqlitePath), 0755, true);
    }

    // Reconfigurer la connexion SQLite
    config(['database.connections.sqlite.database' => $sqlitePath]);

    // Démarrer le SyncWorker au boot
    if (config('app.env') === 'desktop' && !app()->runningInConsole()) {
        \App\Jobs\SyncWorker::dispatch()->delay(now()->addSeconds(15));
    }
}

private function getSqlitePath(): string
{
    // Windows : %APPDATA%\PrimeGest\primegest.db
    $appData = getenv('APPDATA');
    if ($appData) {
        return $appData . DIRECTORY_SEPARATOR . 'PrimeGest' . DIRECTORY_SEPARATOR . 'primegest.db';
    }

    // Fallback
    return storage_path('app/primegest.db');
}
```

---

## ÉTAPE 6 — AUTO-UPDATER

Configurer les mises à jour automatiques silencieuses.

Créer `resources/js/composables/useUpdater.ts` :

```typescript
// Vérifier et appliquer les mises à jour en silence
import { onMounted } from 'vue'
import { check } from '@tauri-apps/plugin-updater'
import { relaunch } from '@tauri-apps/plugin-process'

export function useUpdater() {
    onMounted(async () => {
        try {
            const update = await check()

            if (update?.available) {
                console.log(`Mise à jour disponible : ${update.version}`)

                // Télécharger et installer en arrière-plan
                await update.downloadAndInstall()

                // Redémarrer l'application
                await relaunch()
            }
        } catch (error) {
            // Silencieux — pas d'internet ou serveur indisponible
            console.log('Vérification mise à jour ignorée:', error)
        }
    })
}
```

Appeler dans `App.vue` :

```vue
<script setup lang="ts">
import { useUpdater } from '@/composables/useUpdater'
import { useSyncStore } from '@/stores/sync'

// Vérifier les mises à jour au démarrage
useUpdater()

// Démarrer la surveillance de la sync
const sync = useSyncStore()
</script>
```

---

## ÉTAPE 7 — INDICATEUR DE STATUT DANS LA BARRE DE TITRE

Modifier le titre de la fenêtre pour afficher le statut de sync :

```typescript
// resources/js/composables/useTauriTitle.ts
import { watch } from 'vue'
import { getCurrentWindow } from '@tauri-apps/api/window'
import { useSyncStore } from '@/stores/sync'

export function useTauriTitle() {
    const sync  = useSyncStore()
    const win   = getCurrentWindow()

    watch(() => sync.status, (status) => {
        const icons: Record<string, string> = {
            synced:  '✓',
            pending: '⏳',
            syncing: '↑',
            offline: '✗',
        }
        win.setTitle(`PrimeGest  ${icons[status] ?? ''}`)
    })
}
```

---

## ÉTAPE 8 — SCRIPTS DE BUILD

Ajouter dans `package.json` :

```json
{
  "scripts": {
    "tauri:dev":   "tauri dev",
    "tauri:build": "tauri build",
    "tauri:build:windows": "tauri build --target x86_64-pc-windows-msvc"
  }
}
```

Commandes pour builder l'installateur Windows :

```bash
# Build complet — produit un .exe dans src-tauri/target/release/bundle/nsis/
npm run tauri:build

# Le fichier produit sera :
# src-tauri/target/release/bundle/nsis/PrimeGest_1.0.0_x64-setup.exe
```

---

## ÉTAPE 9 — STRUCTURE FINALE DU PROJET DESKTOP

Après implémentation, la structure doit ressembler à :

```
primegest/
├── src-tauri/                  ← Nouveau dossier Tauri
│   ├── src/
│   │   └── main.rs             ← Code Rust (lancement Laravel)
│   ├── icons/                  ← Icônes Windows
│   │   ├── icon.ico            ← Icône principale
│   │   ├── 32x32.png
│   │   ├── 128x128.png
│   │   └── source-512x512.png
│   ├── Cargo.toml              ← Dépendances Rust
│   └── tauri.conf.json         ← Configuration Tauri
│
├── resources/js/
│   ├── composables/
│   │   ├── useUpdater.ts       ← Nouveau : auto-updater
│   │   └── useTauriTitle.ts    ← Nouveau : titre fenêtre
│   └── App.vue                 ← Modifié : appel useUpdater
│
├── app/Providers/
│   └── AppServiceProvider.php  ← Modifié : chemin SQLite Windows
│
└── .env.desktop                ← Nouveau : config mode desktop
```

---

## CHECKLIST FINALE

Avant de livrer, vérifier :

```
□ npx tauri dev fonctionne sans erreur
□ La fenêtre s'ouvre avec le titre "PrimeGest"
□ Laravel répond sur http://127.0.0.1:8888/api/health
□ SQLite se crée dans %APPDATA%\PrimeGest\primegest.db
□ La sync offline fonctionne (couper internet, faire des ventes, reconnecter)
□ npm run tauri:build produit un .exe
□ L'installateur .exe s'installe sur Windows 10/11 sans erreur
□ L'icône PrimeGest apparaît dans le menu Démarrer
□ L'icône PrimeGest apparaît sur le bureau
□ La mise à jour automatique est configurée
□ La fermeture de l'app arrête proprement le serveur PHP
```

---

## ORDRE D'IMPLÉMENTATION

Réalise les étapes dans cet ordre exact. Après chaque étape,
indique ce qui a été créé/modifié et attends ma confirmation
avant de passer à l'étape suivante.

1. Vérifier les prérequis (Rust, Node.js, PHP)
2. Installer Tauri et créer la structure de base
3. Créer tauri.conf.json
4. Créer main.rs avec le lancement de Laravel
5. Créer Cargo.toml avec les dépendances Rust
6. Modifier AppServiceProvider pour le chemin SQLite Windows
7. Créer .env.desktop
8. Créer useUpdater.ts et useTauriTitle.ts
9. Modifier App.vue
10. Ajouter les scripts dans package.json
11. Générer les icônes
12. Tester avec npx tauri dev
13. Builder l'installateur avec npm run tauri:build
14. Teste tauri offline

Commence par l'étape 1 maintenant.
