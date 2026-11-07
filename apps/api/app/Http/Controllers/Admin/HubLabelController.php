<?php

namespace App\Http\Controllers\Admin;

use App\Filament\Support\FulfilmentAccess;
use App\Http\Controllers\Controller;
use App\Models\Fulfilment\HubBox;
use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/** Printable 100×150 mm box labels (Code 128 as inline SVG) for the Kolkata hub. GET /hub/labels?orders=SR-A,SR-B */
class HubLabelController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $user = $request->user();
        if (! $user?->is_admin) {
            abort(redirect('/admin/login'));
        }
        abort_unless(FulfilmentAccess::hub(), 403);
        $ids = array_slice(array_filter(array_map(fn ($s) => strtoupper(trim($s)), explode(',', (string) $request->query('orders')))), 0, 100);
        $boxes = HubBox::query()->whereIn('order_id', $ids)->orderBy('order_id')->orderBy('id')->get();
        $orders = Order::query()->whereIn('id', $ids)->get()->keyBy('id');

        return response()->view('fulfilment.labels', ['boxes' => $boxes, 'orders' => $orders])->header('Content-Type', 'text/html; charset=utf-8');
    }
}
