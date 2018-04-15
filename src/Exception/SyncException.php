<?php

namespace Drupal\drupal_content_sync\Exception;

class SyncException extends \Exception {
  public $errorCode;
  public $parentException;

  const CODE_ENTITY_API_FAILURE     = 'ENTITY_API_FAILURE';
  const CODE_UNEXPECTED_EXCEPTION   = 'UNEXPECTED_EXCEPTION';
  const CODE_EXPORT_REQUEST_FAILED  = 'EXPORT_REQUEST_FAILED';
  const CODE_INVALID_IMPORT_REQUEST = 'INVALID_REQUEST';

  public function __construct($errorCode,$parentException=NULL) {
    parent::__construct($errorCode);

    $this->errorCode        = $errorCode;
    $this->parentException  = $parentException;
  }
}
