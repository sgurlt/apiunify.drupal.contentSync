<?php
namespace Drupal\drupal_content_sync\Normalizer;
use Drupal\serialization\Normalizer\NormalizerBase;
/**
 * Converts CacheableMetadata to arrays.
 */
class CacheableMetadataNormalizer extends NormalizerBase {
  /**
   * The interface or class that this Normalizer supports.
   *
   * @var string
   */
  protected $supportedInterfaceOrClass = 'Drupal\Core\Cache\CacheableMetadata';
  /**
   * {@inheritdoc}
   */
  public function normalize($object, $format = NULL, array $context = array()) {
    return [];
  }
}
