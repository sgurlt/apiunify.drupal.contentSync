<?php

namespace Drupal\drupal_content_sync\Entity;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityChangedInterface;

/**
 * Provides an interface for defining Sync entity entities.
 *
 * @ingroup drupal_content_sync_meta_info
 */
interface DrupalContentSyncMetaInformationInterface extends ContentEntityInterface, EntityChangedInterface {

}
