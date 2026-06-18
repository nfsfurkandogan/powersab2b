<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\CustomerCardRequest;
use App\Models\Dealer;
use App\Models\Order;
use App\Models\PosSession;
use App\Models\ReturnRequest;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AdminDashboardOverviewController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $user = $request->user();
        if (! $user?->hasRole('admin')) {
            abort(Response::HTTP_FORBIDDEN);
        }

        $recentUsers = User::query()
            ->select(['id', 'dealer_id', 'selected_customer_id', 'name', 'email', 'phone', 'is_active', 'created_at'])
            ->with([
                'dealer:id,code,name',
                'selectedCustomer:id,code,name',
                'roles:id,slug,name',
            ])
            ->orderByDesc('is_active')
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->get()
            ->map(fn (User $row): array => [
                'id' => (int) $row->id,
                'name' => $row->name,
                'email' => $row->email,
                'phone' => $row->phone,
                'is_active' => (bool) $row->is_active,
                'created_at' => $row->created_at?->toJSON(),
                'dealer' => [
                    'id' => $row->dealer?->id,
                    'code' => $row->dealer?->code,
                    'name' => $row->dealer?->name,
                ],
                'selected_customer' => [
                    'id' => $row->selectedCustomer?->id,
                    'code' => $row->selectedCustomer?->code,
                    'title' => $row->selectedCustomer?->name,
                ],
                'roles' => $row->roles
                    ->map(fn (Role $role): array => [
                        'id' => (int) $role->id,
                        'name' => $role->name,
                        'slug' => $role->slug,
                    ])
                    ->values()
                    ->all(),
            ])
            ->values()
            ->all();

        $roleBreakdown = Role::query()
            ->withCount('users')
            ->orderByDesc('users_count')
            ->orderBy('name')
            ->get()
            ->map(fn (Role $role): array => [
                'slug' => $role->slug,
                'name' => $role->name,
                'count' => (int) $role->users_count,
            ])
            ->values()
            ->all();

        $recentDealers = Dealer::query()
            ->select(['id', 'code', 'name', 'email', 'phone', 'is_active', 'created_at'])
            ->withCount(['users', 'customers'])
            ->orderByDesc('is_active')
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->get()
            ->map(fn (Dealer $dealer): array => [
                'id' => (int) $dealer->id,
                'code' => $dealer->code,
                'name' => $dealer->name,
                'email' => $dealer->email,
                'phone' => $dealer->phone,
                'is_active' => (bool) $dealer->is_active,
                'users_count' => (int) $dealer->users_count,
                'customers_count' => (int) $dealer->customers_count,
                'created_at' => $dealer->created_at?->toJSON(),
            ])
            ->values()
            ->all();

        $pendingCustomerCardRequests = CustomerCardRequest::query()
            ->select([
                'id',
                'dealer_id',
                'requested_by_user_id',
                'request_no',
                'company_name',
                'contact_name',
                'phone',
                'status',
                'created_at',
            ])
            ->with(['dealer:id,code,name', 'requestedBy:id,name'])
            ->whereIn('status', [
                CustomerCardRequest::STATUS_SUBMITTED,
                CustomerCardRequest::STATUS_REVIEWING,
            ])
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->limit(6)
            ->get()
            ->map(fn (CustomerCardRequest $row): array => [
                'id' => (int) $row->id,
                'request_no' => $row->request_no,
                'company_name' => $row->company_name,
                'contact_name' => $row->contact_name,
                'phone' => $row->phone,
                'status' => $row->status,
                'created_at' => $row->created_at?->toJSON(),
                'dealer' => [
                    'id' => $row->dealer?->id,
                    'code' => $row->dealer?->code,
                    'name' => $row->dealer?->name,
                ],
                'requested_by' => [
                    'id' => $row->requestedBy?->id,
                    'name' => $row->requestedBy?->name,
                ],
            ])
            ->values()
            ->all();

        $pendingReturns = ReturnRequest::query()
            ->select([
                'id',
                'dealer_id',
                'customer_id',
                'requested_by_user_id',
                'request_no',
                'request_type',
                'status',
                'reason_code',
                'quantity',
                'created_at',
            ])
            ->with(['dealer:id,code,name', 'customer:id,code,name', 'requestedBy:id,name'])
            ->whereIn('status', [
                ReturnRequest::STATUS_SUBMITTED,
                ReturnRequest::STATUS_REVIEWING,
            ])
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->limit(6)
            ->get()
            ->map(fn (ReturnRequest $row): array => [
                'id' => (int) $row->id,
                'request_no' => $row->request_no,
                'request_type' => $row->request_type,
                'status' => $row->status,
                'reason_code' => $row->reason_code,
                'quantity' => (int) $row->quantity,
                'created_at' => $row->created_at?->toJSON(),
                'dealer' => [
                    'id' => $row->dealer?->id,
                    'code' => $row->dealer?->code,
                    'name' => $row->dealer?->name,
                ],
                'customer' => [
                    'id' => $row->customer?->id,
                    'code' => $row->customer?->code,
                    'title' => $row->customer?->name,
                ],
                'requested_by' => [
                    'id' => $row->requestedBy?->id,
                    'name' => $row->requestedBy?->name,
                ],
            ])
            ->values()
            ->all();

        $warehouseReadyOrders = Order::query()
            ->select([
                'id',
                'dealer_id',
                'customer_id',
                'order_no',
                'status',
                'currency',
                'grand_total',
                'approved_at',
            ])
            ->with(['customer:id,code,name', 'dealer:id,code,name'])
            ->where('status', 'approved')
            ->orderByDesc('approved_at')
            ->orderByDesc('id')
            ->limit(6)
            ->get()
            ->map(fn (Order $row): array => [
                'id' => (int) $row->id,
                'order_no' => $row->order_no,
                'status' => $row->status,
                'currency' => $row->currency,
                'grand_total' => number_format((float) $row->grand_total, 2, '.', ''),
                'approved_at' => $row->approved_at?->toJSON(),
                'dealer' => [
                    'id' => $row->dealer?->id,
                    'code' => $row->dealer?->code,
                    'name' => $row->dealer?->name,
                ],
                'customer' => [
                    'id' => $row->customer?->id,
                    'code' => $row->customer?->code,
                    'title' => $row->customer?->name,
                ],
            ])
            ->values()
            ->all();

        $openPosSessions = PosSession::query()
            ->select([
                'id',
                'cashbox_id',
                'opened_by',
                'opened_at',
                'opening_cash',
                'status',
            ])
            ->with([
                'cashbox:id,code,name',
                'openedBy:id,dealer_id,name,email',
                'openedBy.dealer:id,code,name',
            ])
            ->where('status', 'open')
            ->orderByDesc('opened_at')
            ->orderByDesc('id')
            ->limit(6)
            ->get()
            ->map(fn (PosSession $session): array => [
                'id' => (int) $session->id,
                'status' => $session->status,
                'opened_at' => $session->opened_at?->toJSON(),
                'opening_cash' => number_format((float) $session->opening_cash, 2, '.', ''),
                'cashbox' => [
                    'id' => $session->cashbox?->id,
                    'code' => $session->cashbox?->code,
                    'name' => $session->cashbox?->name,
                ],
                'opened_by' => [
                    'id' => $session->openedBy?->id,
                    'name' => $session->openedBy?->name,
                    'email' => $session->openedBy?->email,
                ],
                'dealer' => [
                    'id' => $session->openedBy?->dealer?->id,
                    'code' => $session->openedBy?->dealer?->code,
                    'name' => $session->openedBy?->dealer?->name,
                ],
            ])
            ->values()
            ->all();

        return response()->json([
            'summary' => [
                'users_total' => User::query()->count(),
                'active_users_total' => User::query()->where('is_active', true)->count(),
                'dealers_total' => Dealer::query()->count(),
                'active_dealers_total' => Dealer::query()->where('is_active', true)->count(),
                'customers_total' => Customer::query()->count(),
                'active_customers_total' => Customer::query()->where('is_active', true)->count(),
                'open_pos_sessions_total' => PosSession::query()->where('status', 'open')->count(),
                'warehouse_ready_orders_total' => Order::query()->where('status', 'approved')->count(),
                'pending_customer_card_requests_total' => CustomerCardRequest::query()
                    ->whereIn('status', [CustomerCardRequest::STATUS_SUBMITTED, CustomerCardRequest::STATUS_REVIEWING])
                    ->count(),
                'pending_return_requests_total' => ReturnRequest::query()
                    ->whereIn('status', [ReturnRequest::STATUS_SUBMITTED, ReturnRequest::STATUS_REVIEWING])
                    ->count(),
            ],
            'role_breakdown' => $roleBreakdown,
            'recent_users' => $recentUsers,
            'recent_dealers' => $recentDealers,
            'pending_customer_card_requests' => $pendingCustomerCardRequests,
            'pending_return_requests' => $pendingReturns,
            'warehouse_ready_orders' => $warehouseReadyOrders,
            'open_pos_sessions' => $openPosSessions,
        ]);
    }
}
