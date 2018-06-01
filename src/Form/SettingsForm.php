<?php

namespace Drupal\drupal_content_sync\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Form handler for the Pool add and edit forms.
 */
class SettingsForm extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return [
      'drupal_content_sync.settings',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'settings_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    global $base_url;
    $config = $this->config('drupal_content_sync.settings');
    $form['dcs_base_url'] = array(
      '#type' => 'textfield',
      '#title' => $this->t('Base URL'),
      '#default_value' => $config->get('dcs_base_url'),
      '#description' => $this->t('By default the global base_url provided by Drupal is used for the communication between the DCS backend and Drupal. However, this setting allows you to override the base_url that should be used for the communication.
      Once this is set, all Settings must be reepxorted. This can be done by either saving them, or using <i>drush dcse</i>. Do not include a trailing slash.'),
      '#attributes' => [
        'placeholder' => $base_url
      ],
    );
    return parent::buildForm($form, $form_state);
  }
  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    parent::validateForm($form, $form_state);
  }
  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    parent::submitForm($form, $form_state);
    $this->config('drupal_content_sync.settings')
      ->set('dcs_base_url', $form_state->getValue('dcs_base_url'))
      ->save();
  }
}
