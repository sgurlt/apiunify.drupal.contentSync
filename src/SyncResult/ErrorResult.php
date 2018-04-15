<?php

namespace Drupal\drupal_content_sync\SyncResult;

/**
 *
 */
class ErrorResult extends SyncResult {
  const CODE_EXPORT_REQUEST_FAILED  = 'EXPORT_REQUEST_FAILED';
  const CODE_ENTITY_API_FAILURE     = 'ENTITY_API_FAILURE';
  const CODE_INVALID_REQUEST        = 'INVALID_REQUEST';
  const CODE_UNEXPECTED_EXCEPTION   = 'UNEXPECTED_EXCEPTION';

  /**
   *
   */
  public function __construct($code, $exception = NULL) {
    parent::__construct('error', $code, $exception);
  }

}
