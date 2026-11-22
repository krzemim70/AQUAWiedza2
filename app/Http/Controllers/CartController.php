<?php

namespace App\Http\Controllers;

use App\Domain\Shops\Actions\AcceptCartPrices;
use App\Domain\Shops\Actions\ChangeCart;
use App\Domain\Shops\Actions\ListCart;
use App\Domain\Shops\Actions\SubmitCart;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

/** Koszyk klienta: cienka warstwa nad operacjami Shops (błędy walidacji wracają do formularza). */
class CartController
{
    public function show(Request $request, ListCart $cart): View
    {
        $lines = $cart->handle($request->user());

        return view('cart.show', [
            'groups' => collect($lines)->groupBy('shopId'),
            'total' => array_sum(array_map(fn ($l) => $l->total(), $lines)),
            'blocked' => collect($lines)->contains(fn ($l) => $l->issue !== null && ! str_contains($l->issue, 'Zaakceptuj')),
            'priceChanged' => collect($lines)->contains(fn ($l) => $l->priceGrosze !== $l->priceSeenGrosze),
            'key' => (string) Str::uuid(),
        ]);
    }

    public function add(Request $request, ChangeCart $cart): RedirectResponse
    {
        $data = $request->validate(['offer_id' => ['required', 'integer'], 'qty' => ['required', 'integer', 'min:1']]);
        $cart->handle($request->user(), (int) $data['offer_id'], (int) $data['qty'], add: true);

        return redirect()->route('cart.show')->with('status', 'Dodano do koszyka.');
    }

    public function update(Request $request, ChangeCart $cart, int $offer): RedirectResponse
    {
        $data = $request->validate(['qty' => ['required', 'integer', 'min:0']]);
        $cart->handle($request->user(), $offer, (int) $data['qty']);

        return redirect()->route('cart.show');
    }

    public function remove(Request $request, ChangeCart $cart, int $offer): RedirectResponse
    {
        $cart->handle($request->user(), $offer, 0);

        return redirect()->route('cart.show')->with('status', 'Usunięto z koszyka.');
    }

    public function acceptPrices(Request $request, AcceptCartPrices $accept): RedirectResponse
    {
        $accept->handle($request->user());

        return redirect()->route('cart.show');
    }

    public function submit(Request $request, SubmitCart $submit): RedirectResponse
    {
        $data = $request->validate(['key' => ['required', 'string'], 'phone' => ['required', 'string']]);
        $submit->handle($request->user(), $data['key'], $data['phone']);

        return redirect()->route('orders.index')->with('status', 'Koszyk wysłany do sklepów. Każdy sklep zdecyduje o swoim zamówieniu.');
    }
}
