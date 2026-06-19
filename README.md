# HowPlatform

Monorepo for the HowPlatform suite — five interlinked subdomains sharing a JWT-cookie SSO:

| Subdomain | Framework | Container port |
|-----------|-----------|----------------|
| `auth.howplatform.net` | Laravel 12 (central SSO) | 4110 |
| `www.howplatform.net` | Laravel 12 | 4111 |
| `library.howplatform.net` | Laravel 12 | 4112 |
| `tools.howplatform.net` | Laravel 12 | 4113 |
| `diploma.howplatform.net` | CodeIgniter 4 (AI/RAG) | 4114 |

## Architecture

A single Docker stack (`docker-compose.yml`): one Apache+PHP container per app + one
MySQL 8 container, on a private network. Each app is published on `127.0.0.1:<port>`
and exposed publicly through a **Cloudflare Tunnel** (the host has no static IP).
TLS terminates at Cloudflare's edge; the origin is plain HTTP, so the apps trust
`X-Forwarded-Proto` (Laravel `trustProxies`, CI4 `proxyIPs`).

## Secrets (never committed)

`.env` files, `secure-keys/` (Firebase + Vertex JSON), DB dumps and user-uploaded
content are all gitignored and provided out-of-band on the server. Copy the
`.env.example` of each app to `.env` and fill in real values; create `./.env`
from `.env.docker.example` for compose.

## Deploy (on the server)

```bash
git clone <repo> /home/sysadmin/HowPlatform
cd /home/sysadmin/HowPlatform
cp .env.docker.example .env                 # set MYSQL_ROOT_PASSWORD
# create each <app>/public_html/.env (real secrets), and ./secure-keys/*.json
# import the 5 DB dumps + rsync diploma public/uploads & public/downloads
./deploy.sh
```

Then add the 5 ingress routes + DNS to the existing Cloudflare tunnel.
