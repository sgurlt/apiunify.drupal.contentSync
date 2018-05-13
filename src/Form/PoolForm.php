<?php

namespace Drupal\drupal_content_sync\Form;

use Drupal\Core\Entity\EntityForm;
use Drupal\Core\Entity\EntityTypeManager;
use Drupal\Core\Form\FormStateInterface;
use Drupal\drupal_content_sync\ApiUnifyPoolExport;
use GuzzleHttp\Client;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Site\Settings;

/**
 * Form handler for the Pool add and edit forms.
 */
class PoolForm extends EntityForm {

  /**
   * Constructs an PoolForm object.
   *
   * @param \Drupal\Core\Entity\EntityTypeManager $entityTypeManager
   *   The entityTypeManager.
   */
  public function __construct(EntityTypeManager $entityTypeManager) {
    $this->entityTypeManager = $entityTypeManager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function form(array $form, FormStateInterface $form_state) {
    $form = parent::form($form, $form_state);

    /**
     * @var \Drupal\drupal_content_sync\Entity\Pool $pool
     */
    $pool = $this->entity;

    $form['label'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Label'),
      '#maxlength' => 255,
      '#default_value' => $pool->label(),
      '#description' => $this->t("The pool name."),
      '#required' => TRUE,
    ];
    $form['id'] = [
      '#type' => 'machine_name',
      '#default_value' => $pool->id(),
      '#machine_name' => [
        'exists' => [$this, 'exist'],
      ],
      '#disabled' => !$pool->isNew(),
    ];
    $form['backend_url'] = [
      '#type' => 'url',
      '#title' => $this->t('Drupal Content Sync URL'),
      '#default_value' => $pool->getBackendUrl(),
      '#description' => $this->t("The Drupal Content Sync backend URL."),
      '#required' => TRUE,
    ];

    // Check if the site id got set within the settings*.php.
    if (!is_null($pool->id)) {
      $config_machine_name = $pool->id;
      $dcs_settings = Settings::get('drupal_content_sync');
      if (!is_null($dcs_settings) && isset($dcs_settings[$pool->id])) {
        $site_id = $dcs_settings[$pool->id];
      }
    }
    if (!isset($config_machine_name)) {
      $config_machine_name = '<machine_name_of_the_configuration>';
    }

    $form['site_id'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Site identifier'),
      '#default_value' => $pool->getSiteId(),
      '#description' => $this->t("This identifier will be used to identify the origin of entities on other sites and is used as a machine name for identification. 
      Once connected, you cannot change this identifier anylonger. Typicall you want to use the fully qualified domain name of this website as an identifier.<br>
      The Site identifier can be overridden within your environment specific settings.php file by using <i>@settings</i>.<br>
      If you do so, you should exclude the Site identifier for this configuration from the configuration import/export by using the module <a href='https://www.drupal.org/project/config_ignore' target='_blank'>Config ignore</a>.
      The exclude could for example look like this: <i>drupal_content_sync.pool.@config_machine_name:site_id</i><br>
      <i>Hint: If this configuration is saved before the value with the settings.php got set, you need to resave this configuration once the value within the settings.php got set.</i>", [
        '@settings' => '$settings["drupal_content_sync"]["' . $config_machine_name . '"] = "my-site-identifier"',
        '@config_machine_name' => $config_machine_name,
      ]),
      '#required' => TRUE,
    ];

    // If the site id is set within the settings.php,
    // the form field is disabled.
    if (isset($site_id)) {
      $form['site_id']['#disabled'] = TRUE;
      $form['site_id']['#default_value'] = $site_id;
      $form['site_id']['#description'] = $this->t('Site identifier ist set with the environment specific settings.php file.');
    }

    return $form;
  }

  /**
   * Validate format of input fields and make sure the API Unify backend is
   * accessible to actually update it.
   *
   * @param array $form
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    parent::validateForm($form, $form_state);

    $api = $form_state->getValue('id');
    if (!preg_match('@^([a-z0-9\-]+)$@', $api)) {
      $form_state->setErrorByName('id', $this->t('Please only use letters, numbers and dashes.'));
    }
    if ($api == 'drupal' || $api == 'api-unify') {
      $form_state->setErrorByName('api', $this->t('This name is reserved.'));
    }

    $site_id = $form_state->getValue('site_id');
    if ($site_id == ApiUnifyPoolExport::POOL_SITE_ID) {
      $form_state->setErrorByName('site_id', $this->t('This name is reserved.'));
    }

    $url    = $form_state->getValue('backend_url');
    $client = new Client();
    try {
      $response = $client->get($url . '/status');
      if ($response->getStatusCode() != 200) {
        $form_state->setErrorByName('backend_url', $this->t('The backend did not respond with 200 OK. Please ask your technical contact person for support.'));
      }
    }
    catch (\Exception $e) {
      $form_state->setErrorByName('backend_url', $this->t('The backend did not respond with 200 OK. Please ask your technical contact person for support. The error messages is @message', ['@message' => $e->getMessage()]));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state) {
    $pool = $this->entity;
    $status = $pool->save();

    if ($status) {
      drupal_set_message($this->t('Saved the %label Pool.', [
        '%label' => $pool->label(),
      ]));
    }
    else {
      drupal_set_message($this->t('The %label Pool was not saved.', [
        '%label' => $pool->label(),
      ]));
    }

    $form_state->setRedirect('entity.dcs_pool.collection');
  }

  /**
   * Helper function to check whether an Pool configuration entity exists.
   */
  public function exist($id) {
    $entity = $this->entityTypeManager->getStorage('dcs_pool')->getQuery()
      ->condition('id', $id)
      ->execute();
    return (bool) $entity;
  }

}
