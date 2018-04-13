<?php

namespace Drupal\drupal_content_sync\SyncResult;

/**
 *
 */
class ErrorResult extends SyncResult {
  const CODE_EXPORT_REQUEST_FAILED = 'EXPORT_REQUEST_FAILED';

  /**
   *
   */
  public function __construct($code, $exception = NULL) {
    parent::__construct('error', $code, $exception);
  }

}
