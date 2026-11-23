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
    case DeleteAccount = 'delete_account';
    case CompatibilityCheck = 'compatibility_check';
    case WaterHints = 'water_hints';
    case RecordWatch = 'record_watch';
    case TankPlan = 'tank_plan';
    case ReminderSuggestions = 'reminder_suggestions';
    case Calculators = 'calculators';
    case TankSupplies = 'tank_supplies';
    case QuickMeasurement = 'quick_measurement';
    case PushNotifications = 'push_notifications';
    case MeasurementTrends = 'measurement_trends';
    case PublicTogether = 'public_together';
    case CreatorOffers = 'creator_offers';
    case CatalogSpecEdit = 'catalog_spec_edit';

    /** Najniższy poziom konta, który daje funkcję (null = obsługiwane osobno). Kreator może być otwarty dla Tank ustawieniem. */
    public function minTier(): ?Tier
    {
        return match ($this) {
            self::Login, self::PublicOffers, self::Calculators, self::PublicTogether => Tier::Drop,
            self::GuidedAdvisor, self::MeasurementHistory, self::ReminderSuggestions, self::MeasurementTrends => Tier::Fishroom,
            self::DropSelection, self::ShopPanel, self::AdminPanel, self::CatalogSpecEdit => null,
            default => Tier::Tank,
        };
    }
}
