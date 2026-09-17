# Hosting

Production hosting details for the 4viso commercial website. **No passwords or secrets in this file** — credentials live in the Combell control panel and in the server's `.env`.

## Overview

| Item | Value |
|---|---|
| Provider | [Combell](https://www.combell.com) (shared web hosting) |
| Control panel | https://my.combell.com |
| PHP version | 8.2 (matches `composer.json` platform and `.ddev/config.yaml`) |
| Database | MySQL (local dev runs MySQL 8.0 via DDEV) |
| Webroot | `web/` |
| Repository | https://github.com/4visoaccounts/commercial-website-2026 (branch `main`) |
| Backups | Automatic, daily, by Combell |

## SSH

```bash
ssh 4visocom@ssh107.webhosting.be
```

- Authentication via SSH key. Add your public key in the Combell control panel under **SSH → SSH keys**.
- SSH access must be enabled per hosting account in the control panel.

## PHP

- Version **8.2**, set in the Combell control panel (**PHP → Version**).
- Keep it in sync with `config.platform.php` in `composer.json` and `php_version` in `.ddev/config.yaml` when upgrading.

## Database

- MySQL database managed through the Combell control panel (**Databases**).
- Connection settings are read from the server's `.env` (`CRAFT_DB_SERVER`, `CRAFT_DB_DATABASE`, `CRAFT_DB_USER`, `CRAFT_DB_PASSWORD`) — see `.env.example.production` for the expected keys.
- The database host is only reachable from within Combell's network. To connect from a local client (TablePlus, Sequel Ace, ...), use an **SSH tunnel**:

  | Setting | Value |
  |---|---|
  | SSH host | `ssh107.webhosting.be` |
  | SSH user | `4visocom` |
  | SSH auth | your SSH key |
  | DB host | see control panel (`<id>.db.webhosting.be`) |
  | DB port | `3306` |
  | DB name / user / password | see control panel or server `.env` |

  Or from the command line:

  ```bash
  ssh -N -L 3307:<db-host>:3306 4visocom@ssh107.webhosting.be
  # then connect to 127.0.0.1:3307
  ```

- Manual dump on the server:

  ```bash
  ./craft db/backup
  ```

  Output lands in `storage/backups/`.

## Backups

- Combell takes **automatic daily backups** of both files and databases.
- Restore through the control panel (**Backups → Restore**), per file/folder or per database.
- Before risky deploys (Craft updates, big project-config changes), also take a manual `./craft db/backup`.

## Deployment

Deploys run on the server over SSH, from the project root:

| Script | When to use |
|---|---|
| `./deploy.sh` | Full deploy: discards local changes on the server, pulls `main`, installs Composer + npm dependencies, builds production assets, runs migrations and applies project config |
| `./fast-deploy.sh` | Template/asset-only changes: pulls `main`, builds production assets, runs migrations and applies project config (skips dependency installs) |

```bash
ssh 4visocom@ssh107.webhosting.be
cd <project-dir>
./deploy.sh
```

Note: `deploy.sh` runs `git checkout -- .` first, so any uncommitted edits made directly on the server are lost.

## Environment

- `.env` on the server is not in git. Start from `.env.example.production`.
- Uploaded assets (`web/uploads`) and `web/cpresources` are not in git — they only exist on the server (and in Combell's backups).
