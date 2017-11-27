<?php

namespace Drupal\drupal_content_sync\Form;

use Drupal\Core\Entity\EntityConfirmFormBase;
use Drupal\Core\Url;
use Drupal\Core\Form\FormStateInterface;

/**
 * Builds the form to delete an DrupalContentSync.
 */

class DrupalContentSyncDeleteForm extends EntityConfirmFormBase {

  /**
   * {@inheritdoc}
   */
  public function getQuestion() {
    return $this->t('Are you sure you want to delete %name?', array('%name' => $this->entity->label()));
  }

  /**
   * {@inheritdoc}
   */
  public function getCancelUrl() {
    return new Url('entity.drupal_content_sync.collection');
  }

  /**
   * {@inheritdoc}
   */
  public function getConfirmText() {
    return $this->t('Delete');
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $links = \Drupal::entityTypeManager()->getStorage('menu_link_content')
      ->loadByProperties(['link__uri' => 'internal:/admin/content/drupal_content_synchronization/' . $this->entity->id()]);

    if ($link = reset($links)) {
      $link->delete();
      menu_cache_clear_all();
    }

    $this->entity->delete();
    drupal_set_message($this->t('A synchronization %label has been deleted.', array('%label' => $this->entity->label())));

    $form_state->setRedirectUrl($this->getCancelUrl());
  }
}
