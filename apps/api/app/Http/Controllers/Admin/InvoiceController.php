<?php

namespace App\Http\Controllers\Admin;

use App\Filament\Support\Admin;
use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Support\Rbac\ResourceScopes;
use Illuminate\Http\Request;
use Illuminate\View\View;

// Printable invoices for one or more orders (admin panel only; route registered in AdminPanelProvider).
class InvoiceController extends Controller
{
    public function __invoke(Request $request): View
    {
        $ids = array_filter(array_map('trim', explode(',', (string) $request->query('ids'))));
        abort_if(! $ids || count($ids) > 200, 422, 'Pass 1-200 order ids.');

        // RBAC: same rights and row scope as the Orders resource (e.g. Hub staff only see hub-stage orders).
        abort_unless(Admin::can('orders.view', $request->user()), 403);
        $orders = ResourceScopes::apply('orders', Order::query())->with(['items', 'user', 'payments'])->whereIn('id', $ids)->orderBy('created_at')->get();
        abort_if($orders->isEmpty(), 404);

        return view('admin.invoices', ['orders' => $orders]);
    }
}
