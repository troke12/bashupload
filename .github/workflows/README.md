# GitHub Actions - Docker Hub Setup

Workflow ini akan otomatis build dan push Docker image ke Docker Hub.

## Setup Secrets

Tambahkan secrets berikut di GitHub repository settings (Settings > Secrets and variables > Actions):

1. **DOCKER_HUB_USERNAME**: Username Docker Hub Anda
2. **DOCKER_HUB_PASSWORD**: Password atau Access Token Docker Hub Anda
3. **DOCKER_HUB_REPOSITORY** (optional): Nama repository di Docker Hub (default: `bashupload`)

## Trigger

Workflow akan berjalan otomatis pada:
- Push ke branch `main` atau `master` → tag: `latest`
- Push tag `v*.*.*` (contoh: `v1.0.0`) → tag: `1.0.0` dan `latest`
- Manual trigger via GitHub Actions UI (workflow_dispatch)

## Image Tags

- Push ke main/master: `username/bashupload:latest`
- Tag v1.0.0: `username/bashupload:1.0.0` dan `username/bashupload:latest`
- Branch lain: `username/bashupload:branch-name`

