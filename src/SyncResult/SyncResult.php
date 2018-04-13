<?php

namespace Drupal\drupal_content_sync\SyncResult;

/**
 *
 */
class SyncResult {
  public $category;
  public $code;
  public $exception;

  /**
   *
   */
  public function __construct($category, $code, $exception = NULL) {
    $this->category  = $category;
    $this->code      = $code;
    $this->exception = $exception;
  }

  /**
   *
   */
  public function successful() {
    return $this->category == 'status';
  }

  /**
   *
   */
  public function failed() {
    return $this->category == 'error';
  }

}
