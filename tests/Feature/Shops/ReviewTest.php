<?php

use App\Domain\KnowledgeBase\Actions\ImportPackage;
use App\Domain\KnowledgeBase\Models\Area;
use App\Domain\Settings\SettingKey;
use App\Domain\Shops\Actions\AdvanceOrder;
use App\Domain\Shops\Actions\PseudonymizeOrders;
use App\Domain\Shops\Actions\SetReviewVisibility;
use App\Domain\Shops\Actions\SubmitReview;
use App\Domain\Shops\Contracts\PublicOffers;
use App\Domain\Shops\Contracts\ShopRatings;
use App\Domain\Shops\Data\ReviewData;
use App\Domain\Shops\Models\Checkout;
use App\Domain\Shops\Models\Location;
use App\Domain\Shops\Models\Order;
use App\Domain\Shops\Models\OrderState;
use App\Domain\Shops\Models\Shop;
use App\Filament\Resources\Reviews\Pages\ListReviews as AdminListReviews;
use App\Filament\Shop\Resources\Reviews\Pages\ListReviews as ShopListReviews;
use App\Models\User;
use Carbon\CarbonInterface;
use Filament\Facades\Filament;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

function reviewOrder(User $user, Shop $shop, Location $location, OrderState $state = OrderState::PickedUp, ?CarbonInterface $pickup = null): Order
{
    $checkout = Checkout::create([
        'user_id' => $user->id, 'idempotency_key' => (string) Str::uuid(),
        'business_date' => today(), 'orders_count' => 1, 'submitted_at' => now(),
    ]);
    $order = $checkout->orders()->create([
        'shop_id' => $shop->id, 'location_id' => $location->id, 'user_id' => $user->id,
        'state' => $state, 'version' => 1, 'total_grosze' => 1500, 'finished_at' => $state->isFinal() ? now() : null,
    ]);
    if ($state === OrderState::PickedUp) {
        $order->events()->create([
            'shop_id' => $shop->id, 'order_version' => $order->version,
            'from_state' => OrderState::Ready, 'to_state' => OrderState::PickedUp, 'occurred_at' => $pickup ?? now(),
        ]);
    }

    return $order;
}

it('lets the customer open the form and submit one immediately published review after pickup', function () {
    Notification::fake();
    [$shop, $owner, $location] = openShop();
    $customer = aquarist();
    $order = reviewOrder($customer, $shop, $location, OrderState::Ready);
    $url = route('orders.review.create', $order);
    $details = route('orders.show', $order);

    $this->get($url)->assertRedirect(route('login'));
    $this->actingAs($customer)->get($details)->assertOk()->assertDontSee('Oceń sklep');
    app(AdvanceOrder::class)->handle($owner, $order, OrderState::PickedUp);

    $this->get($details)->assertOk()->assertSee('Oceń sklep')->assertSee($url);
    $this->get($url)->assertOk()->assertSee('Ocena sklepu (wymagana)')->assertSee('Komentarz (opcjonalny)');
    $this->post($url, ['rating' => '5', 'comment' => 'Sprawny odbiór.'])
        ->assertRedirect($details)->assertSessionHas('status', 'Opinia o sklepie została dodana.');
    $this->assertDatabaseHas('shop_reviews', [
        'order_id' => $order->id, 'shop_id' => $shop->id, 'customer_id' => $customer->id,
        'rating' => 5, 'comment' => 'Sprawny odbiór.', 'hidden_reason' => null,
    ]);
    $this->get($details)->assertOk()->assertDontSee('Oceń sklep');
    $this->post($url, ['rating' => 2])->assertForbidden();
    $this->assertDatabaseCount('shop_reviews', 1);
    Notification::assertNothingSent();
});

it('rejects orders without pickup, outside the review window or belonging to someone else', function (string $case, int $status) {
    [$shop, , $location] = openShop();
    $customer = aquarist();
    $order = reviewOrder(
        $case === 'obce' ? aquarist() : $customer,
        $shop,
        $location,
        $case === 'przed odbiorem' ? OrderState::Ready : OrderState::PickedUp,
        $case === 'po terminie' ? now()->subDays(31) : null,
    );
    if ($case === 'bez zdarzenia') {
        $order->events()->delete();
    }

    $url = route('orders.review.create', $order);
    $this->actingAs($customer)->get($url)->assertStatus($status);
    $this->post($url, ['rating' => 4])->assertStatus($status);
    if ($case === 'obce') {
        $this->post($url)->assertNotFound();
    }
    $this->assertDatabaseCount('shop_reviews', 0);
})->with([
    'przed odbiorem' => ['przed odbiorem', 403],
    'po terminie' => ['po terminie', 403],
    'bez zdarzenia' => ['bez zdarzenia', 403],
    'obce' => ['obce', 404],
]);

it('uses the configured review deadline and comment limit', function () {
    $this->freezeSecond();
    shopSetting(SettingKey::ShopReviews, ['window_days' => 2, 'min_to_show' => 3, 'max_length' => 8]);
    [$shop, , $location] = openShop();
    $customer = aquarist();
    $order = reviewOrder($customer, $shop, $location, pickup: now()->subDays(2));
    $url = route('orders.review.create', $order);
    $this->actingAs($customer)->get($url)->assertOk()->assertSee('maxlength="8"', false);
    $this->from($url)->post($url, ['rating' => 6, 'comment' => 'Dziewięć!'])->assertSessionHasErrors(['rating', 'comment']);
    $this->from($url)->followingRedirects()->post($url, ['rating' => 4, 'comment' => ['zły format']])
        ->assertOk()->assertSee('Wpisz komentarz jako tekst.');
    $this->travel(1)->second();
    $this->post($url, ['rating' => 4])->assertForbidden();
    $this->assertDatabaseCount('shop_reviews', 0);
});

it('shows an average at the configured threshold and publishes comments on the shop page', function () {
    app(ImportPackage::class)->handle(kbPackage());
    shopSetting(SettingKey::ShopActivation, ['threshold' => 1, 'offer_limit' => 20]);
    [$shop, $owner, $location] = openShop();
    [$other] = openShop('7740001454', 'Drugi sklep');
    $offer = publishedOffer($owner, $shop, $location, 'FN0013');
    $customer = aquarist();
    $submit = app(SubmitReview::class);
    $ratings = app(ShopRatings::class);
    $publicOffers = app(PublicOffers::class);
    $url = route('catalog.show', ['fauna', 'FN0013']);
    $comments = route('shops.reviews.index', $shop);
    $comment = 'Ocena po odbiorze <b>na miejscu</b>.';

    $submit->handle($customer, reviewOrder($customer, $shop, $location), new ReviewData(5, $comment));
    $submit->handle($customer, reviewOrder($customer, $shop, $location), new ReviewData(4));
    expect($publicOffers->forOffers([$offer->id])[$offer->id])->shopRatingAvg->toBeNull()->shopRatingCount->toBe(0);
    $this->get($url)->assertOk()->assertSee('Za mało opinii')->assertSee('Opinie wyłącznie po odebranym zamówieniu.')->assertSee($comments);
    $this->get($comments)->assertOk()->assertSee($comment)->assertDontSee($comment, false)->assertDontSee($customer->name);

    $submit->handle($customer, reviewOrder($customer, $shop, $location), new ReviewData(3));
    $summary = $ratings->forShops([$shop->id, $other->id]);
    expect($summary[$shop->id])->average->toBe(4.0)->count->toBe(3)
        ->and($summary[$other->id])->average->toBeNull()->count->toBe(0)
        ->and($publicOffers->forRecords(Area::Fauna, ['FN0013'])['FN0013'][0])->shopRatingAvg->toBe(4.0)->shopRatingCount->toBe(3);
    $this->get($url)->assertOk()->assertSee('Średnia ocena sklepu 4,0 z 5 (liczba opinii 3)')->assertDontSee('Za mało opinii');
    $this->actingAs($customer)->post(route('cart.add'), ['offer_id' => $offer->id, 'qty' => 1])->assertRedirect(route('cart.show'));
    $this->get(route('cart.show'))->assertOk()->assertSee('Średnia ocena sklepu 4,0 z 5 (liczba opinii 3)');

    shopSetting(SettingKey::ShopReviews, ['window_days' => 30, 'min_to_show' => 4, 'max_length' => 500]);
    $this->get($url)->assertOk()->assertSee('Za mało opinii')->assertDontSee('Średnia ocena sklepu 4,0');
});

it('lets only an administrator hide with a reason and restore a review, with an audit history', function () {
    shopSetting(SettingKey::ShopActivation, ['threshold' => 1, 'offer_limit' => 20]);
    shopSetting(SettingKey::ShopReviews, ['window_days' => 30, 'min_to_show' => 1, 'max_length' => 500]);
    app(ImportPackage::class)->handle(kbPackage());
    [$shop, $owner, $location] = openShop();
    publishedOffer($owner, $shop, $location, 'FN0013');
    $customer = aquarist();
    $review = app(SubmitReview::class)->handle($customer, reviewOrder($customer, $shop, $location), new ReviewData(1, 'Komentarz do ukrycia.'));
    $moderation = app(SetReviewVisibility::class);
    $admin = User::factory()->admin()->create();
    $url = route('shops.reviews.index', $shop);

    expect(fn () => $moderation->handle($owner, $review, false, 'Nie dotyczy zakupu'))->toThrow(AuthorizationException::class)
        ->and(fn () => $moderation->handle($admin, $review, false, '  '))->toThrow(ValidationException::class);
    $this->get($url)->assertOk()->assertSee('Komentarz do ukrycia.')->assertSee('liczba opinii 1');

    Filament::setCurrentPanel('admin');
    $this->actingAs($admin, 'admin');
    Livewire::test(AdminListReviews::class)->assertCanSeeTableRecords([$review])
        ->callTableAction('hide', $review, ['reason' => 'Nie dotyczy zakupu'])->assertHasNoTableActionErrors();
    expect($review->refresh()->hidden_reason)->toBe('Nie dotyczy zakupu');
    $this->assertDatabaseHas('activity_log', ['log_name' => 'shop_reviews', 'event' => 'hidden', 'subject_id' => $review->id]);
    $this->get($url)->assertOk()->assertDontSee('Komentarz do ukrycia.')->assertSee('Za mało opinii');

    Livewire::test(AdminListReviews::class)->callTableAction('restore', $review)->assertHasNoTableActionErrors();
    expect($review->refresh()->hidden_reason)->toBeNull();
    $this->assertDatabaseHas('activity_log', ['log_name' => 'shop_reviews', 'event' => 'restored', 'subject_id' => $review->id]);
    $this->get($url)->assertOk()->assertSee('Komentarz do ukrycia.')->assertSee('liczba opinii 1');
});

it('shows the shop its own reviews without moderation actions or hidden comments', function () {
    [$shop, $owner, $location] = openShop();
    [$other, , $otherLocation] = openShop('7740001454', 'Drugi sklep');
    $customer = aquarist();
    $submit = app(SubmitReview::class);
    $published = $submit->handle($customer, reviewOrder($customer, $shop, $location), new ReviewData(4, 'Widoczna treść.'));
    $hidden = $submit->handle($customer, reviewOrder($customer, $shop, $location), new ReviewData(3, 'Ukryta treść.'));
    $foreign = $submit->handle($customer, reviewOrder($customer, $other, $otherLocation), new ReviewData(5, 'Treść dla drugiego sklepu.'));
    app(SetReviewVisibility::class)->handle(User::factory()->admin()->create(), $hidden, false, 'Zawiera dane osobowe');

    inShopPanel($shop, $owner);
    Livewire::test(ShopListReviews::class)->assertCanSeeTableRecords([$published, $hidden])->assertCanNotSeeTableRecords([$foreign])
        ->assertSee('Widoczna treść.')->assertDontSee('Ukryta treść.')->assertDontSee('Ukryj opinię')->assertDontSee('Przywróć opinię');
    $this->get(route('filament.shop.resources.opinie.index', ['tenant' => $other]))->assertNotFound();
});

it('exports only the customer reviews and anonymizes them on account deletion even after order pseudonymization', function () {
    [$shop, , $location] = openShop();
    $customer = aquarist();
    $other = aquarist();
    $order = reviewOrder($customer, $shop, $location);
    $submit = app(SubmitReview::class);
    $review = $submit->handle($customer, $order, new ReviewData(4, 'Moja opinia.'));
    $foreign = $submit->handle($other, reviewOrder($other, $shop, $location), new ReviewData(2, 'Cudza opinia.'));

    $order->update(['pseudonymize_at' => now()->subSecond()]);
    expect(app(PseudonymizeOrders::class)->handle())->toBe(1);
    expect($order->fresh()->user_id)->toBeNull();
    expect($review->fresh())->order_id->toBeNull()->customer_id->toBe($customer->id);
    $this->actingAs($customer)->post(route('account.privacy.export'))->assertOk()
        ->assertJsonCount(1, 'reviews')->assertJsonPath('reviews.0.id', $review->id)
        ->assertJsonPath('reviews.0.rating', 4)->assertJsonPath('reviews.0.comment', 'Moja opinia.')
        ->assertDontSee('Cudza opinia.');

    $this->delete(route('account.privacy.delete'), ['current_password' => 'password', 'confirmation' => 'USUŃ'])
        ->assertRedirect(route('login'));
    $this->assertDatabaseMissing('users', ['id' => $customer->id]);
    $this->assertDatabaseHas('shop_reviews', [
        'id' => $review->id, 'rating' => 4, 'shop_id' => $shop->id, 'customer_id' => null, 'order_id' => null,
        'comment' => null, 'hidden_reason' => null,
    ]);
    expect($foreign->fresh())->customer_id->toBe($other->id)->comment->toBe('Cudza opinia.');
});
