<?php

namespace App\Domain\IdentityAccess\Models;

/** Wiersze macierzy dostępu (model domenowy, sekcja 2). Każde miejsce bramkujące pyta o jedną z tych funkcji. */
enum Feature: string
{
    case Login = 'login';
    case DropSelection = 'drop_selection';
    case TankRanking = 'tank_ranking';
    case TankHitDescription = 'tank_hit_description';
    case GuidedAdvisor = 'guided_advisor';
    case TankView = 'tank_view';
    case TankAdd = 'tank_add';
    case MeasurementWrite = 'measurement_write';
    case MeasurementHistory = 'measurement_history';
    case Redfield = 'redfield';
    case PublicOffers = 'public_offers';
    case ShopCart = 'shop_cart';
    case ShopPanel = 'shop_panel';
    case AdminPanel = 'admin_panel';
    case OwnData = 'own_data';

    /** Najniższy poziom konta, który daje funkcję (null = obsługiwane osobno). Kreator może być otwarty dla Tank ustawieniem. */
    public function minTier(): ?Tier
    {
        return match ($this) {
            self::Login, self::PublicOffers => Tier::Drop,
            self::GuidedAdvisor, self::MeasurementHistory => Tier::Fishroom,
            self::DropSelection, self::ShopPanel, self::AdminPanel => null,
            default => Tier::Tank,
        };
    }
}
