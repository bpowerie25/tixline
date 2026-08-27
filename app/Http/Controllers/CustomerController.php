<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\Ticket;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class CustomerController extends Controller
{
    public function index(Request $request)
    {
        $query = Customer::query()
            ->select('customers.*')
            ->selectSub(
                Ticket::selectRaw('count(*)')
                    ->whereColumn('tickets.requester_email', 'customers.email')
                    ->whereColumn('tickets.tenant_id', 'customers.tenant_id'),
                'tickets_count'
            );

        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('organization', 'like', "%{$search}%");
            });
        }

        $sort = $request->input('sort', 'created_at');
        $direction = $request->input('direction', 'desc');
        $allowedSorts = ['name', 'email', 'created_at', 'last_login_at', 'tickets_count'];

        if (in_array($sort, $allowedSorts)) {
            $query->orderBy($sort, $direction === 'asc' ? 'asc' : 'desc');
        }

        $customers = $query->paginate(25)->withQueryString();

        return Inertia::render('Customers/Index', [
            'customers' => $customers,
            'filters' => [
                'search' => $request->input('search', ''),
                'sort' => $sort,
                'direction' => $direction,
            ],
        ]);
    }

    public function show(Customer $customer)
    {
        $tickets = Ticket::where('requester_email', $customer->email)
            ->with(['assignee', 'team'])
            ->latest()
            ->paginate(25);

        return Inertia::render('Customers/Show', [
            'customer' => $customer,
            'tickets' => $tickets,
        ]);
    }
}
