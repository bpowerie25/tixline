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

### Email Ingestion
- IMAP polling (scheduled every 2 minutes)
- Automatic ticket creation from inbound emails
- Thread detection (replies added to existing tickets)
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

- **Backend:** Laravel (PHP 8.2+)
- **Frontend:** Vue 3 + Inertia.js
- **Styling:** Tailwind CSS
- **Auth:** Laravel Breeze (agents), custom guard (customers), Sanctum (API)
- **Database:** SQLite (default), MySQL/PostgreSQL supported
- **Email:** IMAP via php-imap

## Requirements

- PHP 8.2+
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

## Configuration

### Email Ingestion

Add to `.env`:

```env
IMAP_HOST=imap.example.com
IMAP_PORT=993
IMAP_USERNAME=support@example.com
IMAP_PASSWORD=your-password
IMAP_ENCRYPTION=ssl
```

Run the scheduler for automatic polling:

```bash
php artisan schedule:work
```

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

MIT
