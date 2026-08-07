# Tixline

An open-source helpdesk and customer support platform built with Laravel, Inertia.js, and Vue 3. A modern alternative to tools like SupportBee and UVdesk.

## Features

### Ticketing
- Shared team inbox with search, filters, and pagination
- Ticket assignment to agents or teams (manual + auto)
- Priority levels (low, normal, high, urgent)
- Labels and categorization
- Threaded conversation view with replies and internal notes
- Canned responses with variable interpolation
- SLA tracking with breach/warning indicators

### Workflow Engine
- Rule-based automation triggered on ticket creation, update, or assignment
- Condition builder (field matching with operators: equals, contains, starts with, etc.)
- Actions: assign to agent, assign to team, round-robin distribution, set priority, set status, add label
- Priority ordering for workflow execution

### Forms
- Custom form builder with drag-to-reorder fields
- Field types: text, textarea, select, checkbox, radio, email, number, date, file
- Conditional field visibility (show/hide fields based on other field values)
- Multiple forms for different request types

### Knowledge Base
- Article management with categories
- Rich text content with draft/published workflow
- Public-facing portal with search
- View tracking

### Customer Portal
- Customer registration and login (separate auth guard)
- Ticket submission, tracking, and reply
- Ticket history view

### Inbound Email
- **No stored credentials** — no IMAP passwords, no third-party access tokens
- Two ingestion methods:
  - **Postfix pipe**: `| /path/to/artisan support:process-email` — Postfix delivers directly to the app
  - **HTTP webhook**: `POST /inbound/email` — HMAC-SHA256 authenticated endpoint for forwarding services
- Automatic ticket creation from inbound emails
- Thread detection via ticket reference in subject line (e.g. `Re: [TKT-000001] ...`)
- Fallback thread matching by requester email + subject
- Spam filtering:
  - Domain/email allowlist and blocklist
  - SpamAssassin header detection (X-Spam-Status, X-Spam-Score)
  - Rate limiting per sender

### Reporting
- Ticket volume trends over time
- Status and priority breakdowns
- Agent performance metrics (assigned, resolved, avg response time)
- Source breakdown (email, web, API)
- Configurable time periods (7, 30, 90 days)

### REST API
- Token-based authentication (Laravel Sanctum)
- Full ticket CRUD at `/api/v1/tickets`
- Comment creation
- Filtering by status, priority, team, agent

### Multi-Tenant Skinning
- Per-client branding: logo, favicon, colors, fonts
- Custom CSS injection
- Custom domain support
- Subdomain-based tenant resolution
- Live preview in the admin editor
- Theme applied via CSS custom properties

## Tech Stack

- **Backend:** Laravel (PHP 8.3+)
- **Frontend:** Vue 3 + Inertia.js
- **Styling:** Tailwind CSS
- **Auth:** Laravel Breeze (agents), custom guard (customers), Sanctum (API)
- **Database:** SQLite (default), MySQL/PostgreSQL supported
- **Inbound Email:** Postfix pipe or HTTP webhook (no stored credentials)

## Requirements

- PHP 8.3+
- Composer
- Node.js 18+
- npm

## Installation

```bash
# Clone the repository
git clone https://github.com/bpowerie25/tixline.git
cd tixline

# Install dependencies
composer install
npm install

# Environment setup
cp .env.example .env
php artisan key:generate

# Database
php artisan migrate

# (Optional) Seed with demo data
php artisan db:seed

# Build assets
npm run build

# Start the server
php artisan serve
```

## Demo Accounts (after seeding)

| Role | Email | Password |
|------|-------|----------|
| Admin | admin@example.com | password |
| Agent | sarah@example.com | password |
| Agent | marcus@example.com | password |
| Agent | emily@example.com | password |

## Queue Worker (Required for Inbound Email)

Inbound email processing is asynchronous via Laravel's database queue. The job (`ProcessInboundEmailJob`) retries 3 times with backoff [30s, 120s, 300s] on failure.

**You must run a queue worker** for inbound emails to be processed:

```bash
# Development
php artisan queue:work --tries=3

# Production (use Supervisor or systemd)
# See https://laravel.com/docs/queues#supervisor-configuration
```

Example Supervisor config:

```ini
[program:tixline-worker]
process_name=%(program_name)s_%(process_num)02d
command=php /path/to/artisan queue:work database --sleep=3 --tries=3 --max-time=3600
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
numprocs=2
user=www-data
redirect_stderr=true
stdout_logfile=/var/log/tixline-worker.log
```

Without a queue worker, inbound emails will be stored in `inbound_emails` but never processed into tickets.

## Configuration

### Inbound Email

Generate a webhook secret and add to `.env`:

```env
INBOUND_WEBHOOK_SECRET=your-random-secret-here
```

**Option A: Postfix pipe transport** (recommended for self-hosted)

Add to your Postfix `transport` or `aliases`:

```
support: |"/path/to/your/app/artisan support:process-email"
```

**Option B: HTTP webhook** (for forwarding services or custom setups)

The webhook uses HMAC-SHA256 signature verification. The signature covers both the timestamp and the request body to prevent replay attacks:

```
Signature = HMAC-SHA256(timestamp + "." + body, secret)
```

```bash
TIMESTAMP=$(date +%s)
PAYLOAD='{"from_email":"customer@example.com","from_name":"Customer","subject":"Help!","body":"<p>...</p>"}'
SIGNATURE=$(echo -n "${TIMESTAMP}.${PAYLOAD}" | openssl dgst -sha256 -hmac 'your-secret' | awk '{print $2}')

curl -X POST https://your-app.com/inbound/email \
  -H "Content-Type: application/json" \
  -H "X-Webhook-Signature: ${SIGNATURE}" \
  -H "X-Webhook-Timestamp: ${TIMESTAMP}" \
  -d "${PAYLOAD}"
```

The timestamp must be within 5 minutes of the server's clock.

### Spam Filter

```env
SPAM_ALLOWLIST=acme.com,partner.org
SPAM_BLOCKLIST=spamdomain.com,spammer@example.com
SPAM_SCORE_THRESHOLD=5.0
SPAM_MAX_PER_HOUR=10
```

### Multi-Tenant

```env
TENANT_BASE_DOMAIN=tixline.yoursite.com
```

Tenants are resolved by:
1. Custom domain match
2. Subdomain match (e.g., `acme.tixline.yoursite.com`)
3. Authenticated user's tenant association

## Key URLs

| URL | Description |
|-----|-------------|
| `/dashboard` | Agent dashboard |
| `/tickets` | Ticket inbox |
| `/workflows` | Workflow automation rules |
| `/forms` | Form builder |
| `/reports` | Reporting dashboard |
| `/tenants` | Tenant/branding management |
| `/sla-policies` | SLA policy configuration |
| `/canned-responses` | Canned response templates |
| `/admin/kb` | Knowledge base admin |
| `/submit` | Public ticket submission |
| `/kb` | Public knowledge base |
| `/portal` | Customer portal |
| `/api/v1/tickets` | REST API |

## License

AGPL-3.0-or-later — see [LICENSE](LICENSE) for details.
