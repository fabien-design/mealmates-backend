<?php

namespace App\Enums;

enum ProgressType: string
{
  case OFFERS_CREATED = 'offers_created';
  case FOOD_SAVED = 'food_saved';
  case TRANSACTIONS_COMPLETED = 'transactions_completed';
  case REVIEWS_RECEIVED = 'reviews_received';
  case REVIEWS_GIVEN = 'reviews_given';
  case CONSECUTIVE_DAYS = 'consecutive_days';
  case REFERRALS = 'referrals';
}
