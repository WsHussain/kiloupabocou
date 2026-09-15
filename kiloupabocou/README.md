# KILOUPABOCOU — reference infrastructure

Local reference build of the Epitech "Kiloupabocou" Docker project, for
comparison against your own implementation. Not affiliated with or pushed to
any GitHub repo — standalone study copy, kept in its own folder separate from
the `micro-services-reference` project.

Covers the **mandatory part** of the subject: dockerizing a Symfony app
(MySQL + PHP-FPM + Nginx) and adding an ELK stack (Elasticsearch + Logstash +
Kibana + Filebeat) to watch its logs. The bonus part (Flexget/Transmission,
MediaWiki) isn't included — ask if you want it added.

## Architecture

```
                         ┌───────────────┐
                         │     mysql     │  (data volume: mysql_data)
                         └───────┬───────┘
                                 │
                         ┌───────▼───────┐        ./symfony-app (bind mount)
                         │      php      │◄───────  the actual Symfony code
                         │  (php-fpm)    │          lives on the host, not
                         └───────┬───────┘          baked into the image
                                 │ fastcgi :9000
                         ┌───────▼───────┐
      80 ──────────────► │     nginx     │
                         └───────┬───────┘
                                 │ writes access/error logs
                                 ▼
                          nginx_logs volume
                                 │
                         ┌───────▼───────┐    ┌──────────────┐    ┌────────┐
                         │   filebeat    │───►│   logstash   │───►│   es   │◄── kibana (5601)
                         └───────────────┘    └──────────────┘    └────────┘
```

- **mysql** — the Symfony app's database. Root password, a dedicated DB +
  user/password are all set via env vars (no defaults used in prod). Port
  3306 exposed, data persisted in the `mysql_data` volume.
- **php** — a custom `php:8.3-fpm` image (Dockerfile in `php/`) with
  `pdo_mysql`, `intl`, `zip`, `opcache`. The Symfony project itself is bind
  -mounted from `./symfony-app` (not copied into the image) so you can edit
  code on the host and see it live. `var/log` is a named volume
  (`symfony_logs`) so Filebeat can read it from another container.
- **nginx** — official `nginx:alpine`, port 80 exposed, vhost config in
  `nginx/conf.d/default.conf` (proxies `.php` requests to `php:9000`), logs
  written to the `nginx_logs` volume. A second vhost
  (`nginx/conf.d/kibana.conf`) reverse-proxies `kibana.kiloupabocou.lan` to
  the Kibana container — this is the PDF's Nginx-in-front-of-Kibana bonus.
- **elasticsearch / logstash / kibana / filebeat** — standard ELK stack.
  Filebeat tails the nginx and Symfony log volumes and ships to Logstash
  (`:5044`), Logstash parses and forwards to Elasticsearch, Kibana visualizes
  it. Filebeat runs `--strict.perms=false` because its config file is bind
  -mounted with host permissions rather than baked in with 600.
- All containers sit on one user-defined bridge network (`kiloupabocou`) and
  address each other by service name — the modern replacement for the
  deprecated `docker --link` the PDF mentions.

## The Symfony app

`symfony-app/` is a real, working Symfony 6.4 skeleton (created with
`composer create-project symfony/skeleton`, not hand-written), with
`symfony/orm-pack` added for Doctrine. `DATABASE_URL` is overridden by
docker-compose to point at the `mysql` service — matching the PDF's warning
to "bien modifier le .env pour que le projet utilise MySQL".

To prove the DB connection actually works, there's one entity/controller pair:

- `src/Entity/Note.php` + `src/Repository/NoteRepository.php` — a trivial
  `Note` (id, title, content, createdAt).
- `src/Controller/NoteController.php` — `GET /` (health check),
  `GET /notes` (list), `POST /notes` (create).

## Setup

### 1. Environment

```bash
cp .env.example .env
# edit .env: set real MYSQL_ROOT_PASSWORD, MYSQL_PASSWORD, APP_SECRET
```

### 2. Add the domain to your hosts file (for the Nginx vhost names)

```
127.0.0.1  kiloupabocou.lan
127.0.0.1  kibana.kiloupabocou.lan
```

### 3. Build and start everything

```bash
docker compose up -d --build
```

### 4. Run the initial Doctrine migration

```bash
docker compose exec php php bin/console doctrine:migrations:diff
docker compose exec php php bin/console doctrine:migrations:migrate --no-interaction
```

### 5. Try it

```bash
curl http://kiloupabocou.lan/
# -> {"app":"kiloupabocou","status":"ok"}

curl -X POST http://kiloupabocou.lan/notes \
     -H "Content-Type: application/json" \
     -d '{"title":"Hello","content":"First note stored in MySQL"}'

curl http://kiloupabocou.lan/notes
```

Kibana: http://kibana.kiloupabocou.lan/ (or `http://localhost:5601` directly).
Elasticsearch: `curl http://localhost:9200`.

## Correspondance avec le sujet

| Exigence du PDF | Où |
|---|---|
| Conteneur MySQL : mot de passe root changé, DB + user dédiés, port exposé, volume | `docker-compose.yml` (service `mysql`), `.env.example` |
| Conteneur PHP : volume projet, port exposé, volume logs | `docker-compose.yml` (service `php`), `php/Dockerfile` |
| Conteneur Nginx : port 80, volume config/domaine, volume logs | `docker-compose.yml` (service `nginx`), `nginx/conf.d/default.conf` |
| Projet Symfony configuré pour MySQL | `symfony-app/.env`, `config/packages/doctrine.yaml` |
| ElasticSearch : ports + volume data | `docker-compose.yml` (service `elasticsearch`) |
| Logstash : port, volume, fichier de config dédié | `docker-compose.yml` (service `logstash`), `logstash/pipeline/logstash.conf`, `logstash/config/logstash.yml` |
| Filebeat installé/configuré pour envoyer les logs à Logstash | `docker-compose.yml` (service `filebeat`), `filebeat/filebeat.yml` |
| Kibana : lié à ElasticSearch, port exposé | `docker-compose.yml` (service `kibana`) |
| Bonus Nginx + nom de domaine pour Kibana | `nginx/conf.d/kibana.conf` |
| Liaison des conteneurs via `networks` (pas `--link`) | `docker-compose.yml` (network `kiloupabocou`) |

## Not included (bonus)

- Flexget + Transmission/Deluge (auto-download of new episodes)
- MediaWiki (company wiki)

Both are optional per the PDF ("Et c'est parti pour le fun !") — say the word
if you want them added the same way as the rest.
