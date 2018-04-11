<?php

namespace Drupal\drupal_content_sync\SyncResult;

class SuccessResult extends SyncResult {
  const CODE_SUCCESS          = 'SUCCESS';
  const CODE_HANDLER_IGNORED  = 'HANDLER_IGNORED';
  const CODE_NO_UPDATES       = 'NO_UPDATES';

  public function __construct($code=self::CODE_SUCCESS) {
    parent::__construct('status', $code, NULL );
  }
}