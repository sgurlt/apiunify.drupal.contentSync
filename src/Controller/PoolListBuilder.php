<?php

namespace Drupal\drupal_content_sync\Controller;

use Drupal\Core\Config\Entity\ConfigEntityListBuilder;
use Drupal\Core\Entity\EntityInterface;

/**
 * Provides a listing of Pool.
 */
class PoolListBuilder extends ConfigEntityListBuilder {

  /**
   * {@inheritdoc}
   */
  public function buildHeader() {
    $header['name'] = $this->t('Name');
    $header['id'] = $this->t('Machine name');
    $header['site_id'] = $this->t('Site identifier');
    $header['backend_url'] = $this->t('Drupal Content Sync URL');
    return $header + parent::buildHeader();
  }

  /**
   * {@inheritdoc}
   */
  public function buildRow(EntityInterface $entity) {
    /**
     * @var \Drupal\drupal_content_sync\Entity\Pool $entity
     */
    $row['name'] = $entity->label();
    $row['id'] = $entity->id();
    $row['site_id'] = $entity->site_id;
    $row['backend_url'] = $entity->backend_url;

    // You probably want a few more properties here...
    return $row + parent::buildRow($entity);
  }

}
