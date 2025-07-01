<?php

namespace App\Enums;

enum NotificationType: string
{
  case MESSAGE = 'message';
  case OFFER = 'offer';
  case TRANSACTION = 'transaction';
  case REVIEW = 'review';
  case BADGE = 'badge';
  case CREDIT = 'credit';
  case SYSTEM = 'system';
}
