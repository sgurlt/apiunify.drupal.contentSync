<?php

namespace Drupal\drupal_content_sync;

use Drupal\Core\Url;
use GuzzleHttp\Exception\RequestException;

/**
 *
 */
abstract class ApiUnifyExport {
  /**
   * @var \GuzzleHttp\Client
   */
  protected $client;

  /**
   *
   */
  public function __construct() {
    $this->client = \Drupal::httpClient();
  }

  /**
   *
   */
  abstract public function export();

  /**
   * Send a request to the API Unify backend.
   * Requests will be passed to $this->>client.
   *
   * @param string $url
   * @param array $arguments
   *
   * @return bool
   */
  protected function sendEntityRequest($url, $arguments) {
    $entityId = $arguments['json']['id'];
    $method   = $this->checkEntityExists($url, $entityId) ? 'patch' : 'post';

    if ('patch' == $method) {
      $url .= '/' . $arguments['json']['id'];
    }

    // $url .= (strpos($url, '?') === FALSE ? '?' : '&') . 'async=yes';.
    try {
      $this->client->{$method}($url, $arguments);
      return TRUE;
    }
    catch (RequestException $e) {
      $messenger = \Drupal::messenger();
      $messenger->addError($e->getMessage());
      return FALSE;
    }
  }

  /**
   * @var array
   *   A list of existing entities, cached for better performance.
   */
  protected static $unifyData = [];

  /**
   * Check whether or not the given entity already exists.
   *
   * @param string $url
   * @param string $entityId
   *
   * @return bool
   */
  protected function checkEntityExists($url, $entityId) {
    if (empty(self::$unifyData[$url])) {
      self::$unifyData[$url] = $this->getEntitiesByUrl($url);
    }

    $entityIndex = array_search($entityId, self::$unifyData[$url]);
    $entityExists = (FALSE !== $entityIndex);

    return $entityExists;
  }

  /**
   * Get all entities for the given URL from the API Unify backend.
   *
   * @param string $baseUrl
   * @param array $parameters
   *
   * @return array
   */
  protected function getEntitiesByUrl($baseUrl, $parameters = []) {
    $result = [];
    $url    = $this->generateUrl($baseUrl, $parameters + ['items_per_page' => 999999]);

    $response = $this->client->get($url);
    $body     = $response->getBody()->getContents();
    $body     = json_decode($body);

    foreach ($body->items as $value) {
      if (!empty($value->id)) {
        $result[] = $value->id;
      }
    }

    return $result;
  }

  /**
   * Get a URL string from the given url with additional query parameters.
   *
   * @param $url
   * @param array $parameters
   *
   * @return string
   */
  protected function generateUrl($url, $parameters = []) {
    $resultUrl = Url::fromUri($url, [
      'query' => $parameters,
    ]);

    return $resultUrl->toUriString();
  }

}
