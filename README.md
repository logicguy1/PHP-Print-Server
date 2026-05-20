# PHP Print Server

A self-hosted web print server for Linux machines running CUPS. Upload PDFs, set print options, and manage users from a browser — desktop or mobile.

## Features

- Account system with admin and user roles
- Per-user print history
- Print options: copies, paper size (A4/Letter), duplex, quality
- Connects to host CUPS via socket (no CUPS inside Docker)
- Old-school desktop UI

## Requirements

- Docker + Docker Compose
- CUPS running on the host (`/var/run/cups/cups.sock` must exist)
- A configured printer in CUPS (`lpstat -p` to check)

## Quick Start

```bash
# Clone and enter the repo
git clone <repo-url>
cd PHP-Print-Server

# (Optional) Set your printer name
export PRINTER_NAME=your_printer_name

# Build and start
docker compose up -d --build

# Open in browser
http://localhost:8080
```

Default credentials: **admin** / **admin** — change immediately after first login.

## Printer Configuration

Set the `PRINTER_NAME` environment variable to match the name shown by `lpstat -p` on the host.

In `docker-compose.yml`:

```yaml
environment:
  CUPS_SERVER: /var/run/cups/cups.sock
  PRINTER_NAME: MyPrinter
```

Or export it before running `docker compose up`.

## Persistent Data

| Path (host) | Contents |
|---|---|
| `./data/` | SQLite database |
| `./app/public/uploads/` | Uploaded PDFs (temporary) |

Both are volume-mounted — data survives container restarts.

## CUPS Socket Access

The container mounts `/var/run/cups/cups.sock` from the host. If your socket is in a non-standard location, edit the volume in `docker-compose.yml`:

```yaml
volumes:
  - /path/to/cups.sock:/var/run/cups/cups.sock
```

The `www-data` user inside the container needs read/write access to the socket. If jobs fail with permission errors, add `www-data` to the `lp` group on the host:

```bash
sudo usermod -aG lp www-data
# then restart the container
```

## File Structure

```
PHP-Print-Server/
├── docker-compose.yml
├── Dockerfile
├── apache/000-default.conf
├── app/
│   ├── config.php
│   ├── includes/
│   │   ├── auth.php
│   │   ├── cups.php
│   │   ├── db.php
│   │   └── layout.php
│   └── public/
│       ├── index.php
│       ├── dashboard.php
│       ├── new_job.php
│       ├── history.php
│       ├── admin.php
│       ├── logout.php
│       ├── css/style.css
│       └── js/app.js
└── data/          (gitignored — created on first run)
```

## License

MIT
