# Deployment Notes — Assignment #3 (VPS Deployment)

Technical notes recorded while deploying the SkillSpan API from local development to a Linux server
using Docker.

- **Student:** Ahmed Saqllah — 120230723
- **Repository:** https://github.com/SkillSpan/SkillSpan-Backend-API (branch `feature/authentication`)
- **Host:** iximiuz Labs — Docker Playground, Ubuntu VM (4 vCPU / 10 GiB), Docker Engine 29.9.2,
  accessed through a browser terminal. Free tier: 1 hour of daily playtime, ephemeral disk.

---

## Deployment Summary

1. Started the playground VM and opened the web terminal.
2. Verified Git and Docker (`git --version`, `docker --version`, `docker compose version`).
3. Cloned the repository on the server (`git clone -b feature/authentication …`).
4. Created `.env` from `.env.example` and generated the application key (`make setup`).
5. Built the image (`docker compose build`) and started the stack (`docker compose up -d`).
6. Published TCP port 10000 from the provider console and verified `GET /up` from the browser.

---

## Challenges and Resolutions

### 1. Docker daemon not running

**Problem:** `docker build` failed with `Cannot connect to the Docker daemon`.
**Cause:** the Docker service was not started.
**Resolution:** started and enabled the service, then re-ran the build.

### 2. Container exited immediately after start

**Problem:** the container stopped seconds after `docker run`.
**Cause:** the entrypoint runs `php artisan migrate --force`, which fails when the database is not
reachable yet.
**Resolution:** added a MySQL container to the Compose stack with a health check and made the
application wait for it (`depends_on: condition: service_healthy`).

### 3. Application served but returned 500 errors

**Problem:** every request returned HTTP 500.
**Cause:** `APP_KEY` was empty in `.env`.
**Resolution:** generated the key through the `make setup` target, which writes
`APP_KEY=base64:…` into `.env` before the image is built.

### 4. Application not reachable from the internet

**Problem:** `curl` worked on the server but the public URL timed out.
**Cause:** the port was only bound inside the VM — the provider does not expose a port until it is
published explicitly.
**Resolution:** published port 10000 as an HTTP tab in the provider console and re-tested from a
browser.

### 5. Published port served a blank page

**Problem:** the published URL opened an empty white page, although `curl` returned the expected
HTML.
**Cause:** the port tab was opened on the default path `/`, which renders the Laravel welcome view
and does not produce a meaningful page through the proxy.
**Resolution:** created the port tab with an explicit path (`/up`), the framework health endpoint, so
the published URL returns the `Application up` page.

### 6. The free VM is temporary

**Problem:** the first deployment attempt was interrupted — the free plan gives one hour of daily
playtime, and the VM disk is ephemeral, so the instance (and the built image) is destroyed when the
session ends.
**Cause:** the free tier is designed for short-lived experiments.
**Resolution:** treated the deployment as a single run: clone, build, start, verify and capture the
evidence in one session. A full re-deployment now takes about four minutes using the commands
documented in the README.

### 7. Outbound network is filtered

**Problem:** an attempt to publish the app through a Cloudflare quick tunnel failed with
`read: connection reset by peer`.
**Cause:** the free tier restricts outbound traffic to an allow-list of domains, and
`api.trycloudflare.com` is not part of it.
**Resolution:** dropped the tunnel and used the provider's own HTTP port publishing, which needs no
outbound access.

---

## Lessons Learned

Moving the API from `localhost` to a real server was mostly about the environment, not the code. The
application itself needed no change — the work was in the things around it: the database had to be a
separate container with a health check, the application key had to exist before the first request, and
the port had to be published before anything was visible from the outside.

The biggest surprise was how much of the deployment is decided by the provider rather than by Docker:
whether the port is reachable, whether the disk survives a reboot, and whether outbound traffic is
allowed all change the procedure. Next time I would read the provider's limits (session length,
persistence, egress) before starting, plan the whole deployment as one run, and take the screenshots
as each step succeeds instead of afterwards.

A practical lesson about verification: `curl` inside the server is not proof of a working deployment.
The health endpoint has to answer through the same path a user would take — the published URL — and
that is what the final screenshot shows.
