<?php

namespace App\Enums;

enum BadgeType: string
{
  case OFFER_CREATED = 'offer_created';
  case FOOD_SAVED = 'food_saved';
  case TRANSACTIONS_COMPLETED = 'transactions_completed';
  case REVIEWS_RECEIVED = 'reviews_received';
  case REVIEWS_GIVEN = 'reviews_given';
  case ACCOUNT_AGE = 'account_age';
  case CONSECUTIVE_DAYS = 'consecutive_days';
  case REFERRALS = 'referrals';
}
