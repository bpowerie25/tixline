<?php

use App\Http\Controllers\AgentController;
use App\Http\Controllers\MailConfigController;
use App\Http\Controllers\AttachmentController;
use App\Http\Controllers\CannedResponseController;
use App\Http\Controllers\CommentController;
use App\Http\Controllers\CustomerPortalController;
use App\Http\Controllers\DepartmentController;
use App\Http\Controllers\FormController;
use App\Http\Controllers\InboundEmailController;
use App\Http\Controllers\KbController;
use App\Http\Controllers\LabelController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\PublicTicketController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\SlaPolicyController;
use App\Http\Controllers\TeamController;
use App\Http\Controllers\TenantController;
use App\Http\Controllers\TicketController;
use App\Http\Controllers\WorkflowController;
use App\Models\Ticket;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return Inertia::render('Welcome', [
        'canLogin' => Route::has('login'),
        'canRegister' => Route::has('register'),
    ]);
});

// Public ticket submission
Route::get('/submit', [PublicTicketController::class, 'create'])->name('submit.create');
Route::post('/submit', [PublicTicketController::class, 'store'])->middleware('throttle:10,1')->name('submit.store');

// Inbound email webhook
Route::post('/inbound/email', [InboundEmailController::class, 'webhook'])
    ->name('inbound.email')
    ->middleware('throttle:120,1')
    ->withoutMiddleware([VerifyCsrfToken::class]);

// Public Knowledge Base
Route::prefix('kb')->name('kb.')->group(function () {
    Route::get('/', [KbController::class, 'portal'])->name('portal');
    Route::get('/search', [KbController::class, 'search'])->name('search');
    Route::get('/{category:slug}', [KbController::class, 'portalCategory'])->name('category');
    Route::get('/{category:slug}/{article:slug}', [KbController::class, 'portalArticle'])->name('article');
});

// Dashboard
Route::get('/dashboard', function () {
    $user = auth()->user();
    $baseQuery = fn () => $user->visibleTicketsQuery();

    return Inertia::render('Dashboard', [
        'stats' => [
            'open' => $baseQuery()->where('status', 'open')->count(),
            'pending' => $baseQuery()->where('status', 'pending')->count(),
            'resolved_today' => $baseQuery()->where('status', 'resolved')
                ->whereDate('resolved_at', today())->count(),
            'total' => $baseQuery()->count(),
        ],
        'recentTickets' => $baseQuery()->with(['assignee', 'team'])
            ->latest()->take(10)->get(),
    ]);
})->middleware(['auth', 'verified'])->name('dashboard');

// Attachment download — requires auth (agent, customer, or sanctum)
Route::get('/attachments/{attachment}/download', [AttachmentController::class, 'download'])
    ->name('attachments.download');

// Authenticated routes
Route::middleware('auth')->group(function () {
    Route::get('/help', function () {
        return Inertia::render('Help');
    })->name('help.index');

    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    // Tickets — all agents can view (scoped) and create; policy handles update/delete
    Route::resource('tickets', TicketController::class);
    Route::post('/tickets/{ticket}/comments', [CommentController::class, 'store'])->name('tickets.comments.store');

    // Canned Responses — all agents can view and use
    Route::resource('canned-responses', CannedResponseController::class)->except(['create', 'show', 'edit']);
    Route::get('/api/canned-responses', [CannedResponseController::class, 'forTicket'])->name('canned-responses.list');

    // Reports — team leads and above
    Route::get('/reports', [ReportController::class, 'index'])->middleware('role:team_lead,group_manager')->name('reports.index');

    // Admin-only routes
    Route::middleware('role:admin')->group(function () {
        // Teams
        Route::resource('teams', TeamController::class)->except(['create', 'show', 'edit']);

        // Labels
        Route::resource('labels', LabelController::class)->except(['create', 'show', 'edit']);

        // Workflows
        Route::resource('workflows', WorkflowController::class)->except(['create', 'show', 'edit']);

        // Forms
        Route::get('/forms/create', [FormController::class, 'create'])->name('forms.create');
        Route::resource('forms', FormController::class)->except(['create', 'edit']);

        // SLA Policies
        Route::resource('sla-policies', SlaPolicyController::class)->except(['create', 'show', 'edit']);

        // Tenants (Skinning)
        Route::get('/tenants/create', [TenantController::class, 'create'])->name('tenants.create');
        Route::resource('tenants', TenantController::class)->except(['create', 'edit']);

        // Agents
        Route::resource('agents', AgentController::class)->except(['create', 'show', 'edit']);

        // Mail Configuration
        Route::get('/settings/mail', [MailConfigController::class, 'index'])->name('mail-config.index');
        Route::post('/settings/mail', [MailConfigController::class, 'store'])->name('mail-config.store');
        Route::post('/settings/mail/test', [MailConfigController::class, 'test'])->name('mail-config.test');
        Route::post('/settings/mail/test-imap', [MailConfigController::class, 'testImap'])->name('mail-config.test-imap');

        // Departments
        Route::resource('departments', DepartmentController::class)->except(['create', 'show', 'edit']);
    });

    // Knowledge Base Admin — team leads and above
    Route::middleware('role:team_lead,group_manager')->prefix('admin/kb')->name('kb.admin.')->group(function () {
        Route::get('/', [KbController::class, 'index'])->name('index');
        Route::get('/create', [KbController::class, 'create'])->name('create');
        Route::post('/', [KbController::class, 'store'])->name('store');
        Route::get('/{kbArticle}', [KbController::class, 'show'])->name('show');
        Route::put('/{kbArticle}', [KbController::class, 'update'])->name('update');
        Route::delete('/{kbArticle}', [KbController::class, 'destroy'])->name('destroy');
        Route::post('/categories', [KbController::class, 'storeCategory'])->name('categories.store');
        Route::put('/categories/{category}', [KbController::class, 'updateCategory'])->name('categories.update');
        Route::delete('/categories/{category}', [KbController::class, 'destroyCategory'])->name('categories.destroy');
    });
});

// Customer Portal
Route::prefix('portal')->name('portal.')->group(function () {
    Route::get('/login', [CustomerPortalController::class, 'showLogin'])->name('login');
    Route::post('/login', [CustomerPortalController::class, 'login'])->name('login.submit');
    Route::get('/register', [CustomerPortalController::class, 'showRegister'])->name('register');
    Route::post('/register', [CustomerPortalController::class, 'register'])->middleware('throttle:5,1')->name('register.submit');
    Route::post('/logout', [CustomerPortalController::class, 'logout'])->name('logout');

    Route::middleware('auth:customer')->group(function () {
        Route::get('/', [CustomerPortalController::class, 'tickets'])->name('tickets');
        Route::post('/tickets', [CustomerPortalController::class, 'createTicket'])->name('tickets.store');
        Route::get('/tickets/{ticket}', [CustomerPortalController::class, 'showTicket'])->name('ticket');
        Route::post('/tickets/{ticket}/reply', [CustomerPortalController::class, 'replyToTicket'])->name('ticket.reply');
    });
});

require __DIR__.'/auth.php';
