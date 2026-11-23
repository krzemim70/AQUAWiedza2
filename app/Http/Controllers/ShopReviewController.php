<?php

namespace App\Http\Controllers;

use App\Domain\Settings\SettingKey;
use App\Domain\Settings\Settings;
use App\Domain\Shops\Actions\ListShopReviews;
use App\Domain\Shops\Actions\SubmitReview;
use App\Domain\Shops\Contracts\ShopRatings;
use App\Domain\Shops\Data\ReviewData;
use App\Domain\Shops\Models\Order;
use App\Domain\Shops\Models\Shop;
use App\Domain\Shops\Policies\ReviewPolicy;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ShopReviewController
{
    public function create(Request $request, Order $order, ReviewPolicy $policy, Settings $settings): View
    {
        $policy->submit($request->user(), $order)->authorize();

        return view('orders.review', [
            'order' => $order,
            'maxLength' => (int) $settings->get(SettingKey::ShopReviews)['max_length'],
        ]);
    }

    public function store(Request $request, int $order, SubmitReview $submit): RedirectResponse
    {
        $order = Order::where('user_id', $request->user()->id)->findOrFail($order);
        $data = $request->validate([
            'rating' => ['required', 'integer'],
            'comment' => ['nullable', 'string'],
        ], [
            'rating.required' => 'Wybierz ocenę od 1 do 5.',
            'rating.integer' => 'Wybierz ocenę od 1 do 5.',
            'comment.string' => 'Wpisz komentarz jako tekst.',
        ]);
        $submit->handle($request->user(), $order, new ReviewData((int) $data['rating'], $data['comment'] ?? null));

        return to_route('orders.show', $order->id)->with('status', 'Opinia o sklepie została dodana.');
    }

    public function index(Shop $shop, ListShopReviews $reviews, ShopRatings $ratings): View
    {
        return view('shops.reviews', [
            'shop' => $shop,
            'reviews' => $reviews->handle($shop),
            'rating' => $ratings->forShops([$shop->id])[$shop->id],
        ]);
    }
}
