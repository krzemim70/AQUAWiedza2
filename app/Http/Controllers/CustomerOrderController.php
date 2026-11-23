<?php

namespace App\Http\Controllers;

use App\Domain\Shops\Actions\CloseOrder;
use App\Domain\Shops\Models\Order;
use App\Domain\Shops\Policies\ReviewPolicy;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** „Moje zamówienia”: lista i szczegóły własnych zamówień; klient może wycofać tylko nowe zamówienie. */
class CustomerOrderController
{
    public function index(Request $request): View
    {
        return view('orders.index', [
            'orders' => Order::with('shop')->where('user_id', $request->user()->id)->latest('id')->get(),
        ]);
    }

    public function show(Request $request, ReviewPolicy $reviews, int $order): View
    {
        $order = Order::with(['shop', 'location', 'items', 'events'])->where('user_id', $request->user()->id)->findOrFail($order);

        return view('orders.show', [
            'order' => $order,
            'canReview' => $reviews->submit($request->user(), $order)->allowed(),
        ]);
    }

    public function close(Request $request, CloseOrder $close, int $order): RedirectResponse
    {
        $order = Order::where('user_id', $request->user()->id)->findOrFail($order);
        $close->handle($request->user(), $order, 'Wycofane przez klienta');

        return redirect()->route('orders.show', $order->id)->with('status', 'Zamówienie wycofane.');
    }
}
