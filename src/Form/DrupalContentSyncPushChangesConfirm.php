<?php

namespace Drupal\drupal_content_sync\Form;

use Drupal\Core\Entity\EntityManagerInterface;
use Drupal\Core\Form\ConfirmFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\Core\TempStore\PrivateTempStoreFactory;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Drupal\drupal_content_sync\Entity\DrupalContentSync;

/**
 * Provides a node deletion confirmation form.
 *
 * @internal
 */
class DrupalContentSyncPushChangesConfirm extends ConfirmFormBase {

  /**
   * The tempstore factory.
   *
   * @var \Drupal\Core\TempStore\PrivateTempStoreFactory
   */
  protected $tempStoreFactory;

  /**
   * The node storage.
   *
   * @var \Drupal\Core\Entity\EntityStorageInterface
   */
  protected $storage;

  /**
   * The nodes to push.
   *
   * @var array
   */
  protected $nodes;

  /**
   * Constructs a DeleteMultiple form object.
   *
   * @param \Drupal\Core\TempStore\PrivateTempStoreFactory $temp_store_factory
   *   The tempstore factory.
   * @param \Drupal\Core\Entity\EntityManagerInterface $manager
   *   The entity manager.
   */
  public function __construct(PrivateTempStoreFactory $temp_store_factory, EntityManagerInterface $manager) {
    $this->tempStoreFactory = $temp_store_factory;
    $this->storage = $manager->getStorage('node');
    $this->nodes = $this->tempStoreFactory->get('node_drupal_content_sync_push_changes_confirm')->get('nodes');
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('tempstore.private'),
      $container->get('entity.manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'node_drupal_content_sync_push_changes_confirm';
  }

  /**
   * {@inheritdoc}
   */
  public function getQuestion() {
    return 'Are you sure you want to push this content? Depending on the amount and complexity of it, this action may take a while.';
  }

  /**
   * {@inheritdoc}
   */
  public function getCancelUrl() {
    return new Url('system.admin_content');
  }

  /**
   * {@inheritdoc}
   */
  public function getConfirmText() {
    return t('Push');
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    if (empty($this->nodes)) {
      return new RedirectResponse($this->getCancelUrl()->setAbsolute()->toString());
    }

    $items = [];
    foreach ($this->nodes as $node) {
      $items[$node->id()] = $node->label();
    }

    $form['nodes'] = [
      '#theme' => 'item_list',
      '#items' => $items,
    ];
    $form = parent::buildForm($form, $form_state);

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    if ($form_state->getValue('confirm')) {

      /** @var \Drupal\node\NodeInterface[] $nodes */
      foreach ($this->nodes as $node) {
        _drupal_content_sync_export_entity(
          $node,
          DrupalContentSync::EXPORT_MANUALLY,
          DrupalContentSync::ACTION_CREATE
        );
      }

      drupal_set_message('Pushed @count content.', ['@count' => count($this->nodes)]);
      $this->logger('drupal_content_sync')->notice('Pushed @count content.', ['@count' => count($this->nodes)]);
      $this->tempStoreFactory->get('node_drupal_content_sync_push_changes_confirm')->delete('nodes');
    }
    $form_state->setRedirect('system.admin_content');
  }

}
