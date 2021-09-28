<?php

namespace Drupal\spotify_artists;

use Drupal\Component\Utility\UrlHelper;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\RequestException;
use Drupal\Component\Serialization\Json;

class SpotifyWebAPI {

  /**
   * The config factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $config_factory;

  /**
   * @var \Drupal\Core\Config\ImmutableConfig
   */
  protected $api_settings;

  /**
   * @var array|mixed|null
   */
  protected $api_url;

  /**
   * @var array|mixed|null
   */
  protected $account_url;

  /**
   * @var array|mixed|null
   */
  private $client_id;

  /**
   * @var array|mixed|null
   */
  private $client_secret;

  /**
   * @var bool
   */
  private $access_token;

  /**
   * @var CacheBackendInterface
   */
  protected $cache;

  /**
   * @var ClientInterface
   */
  protected $http_client;

  public function __construct(ConfigFactoryInterface $config_factory, CacheBackendInterface $cache, ClientInterface $http_client) {
    $this->config_factory = $config_factory;
    $this->api_settings = $this->config_factory->get('spotify_artists.settings');
    $this->api_url = $this->api_settings->get('api_url');
    $this->account_url = $this->api_settings->get('account_url');
    $this->client_id = $this->api_settings->get('client_id');
    $this->client_secret = $this->api_settings->get('client_secret');
    $this->http_client = $http_client;
    $this->cache = $cache;

    // load from cache if not expired.
    $dataCache = $this->cache->get('access_token');
    if (!($dataCache == FALSE || $dataCache->expire < time())) {
      $this->access_token =  $dataCache->data;
      kint($this->access_token);
    }
    else {
      $this->access_token = $this->requestCredentialsToken();
    }
  }

  /**
   * @param $url
   * @param $uri
   * @param null $options
   * @param null $parameters
   * @return array
   */
  public function getRequest($url, $uri, $options = NULL, $parameters = NULL) {
    try {
      $options['headers'] = [
        'Content-Type' => 'application/json',
        'Authorization' => 'Bearer ' . $this->access_token,
      ];

      if ($parameters) {
        $queryParams = UrlHelper::buildQuery($parameters);
        $uri = $uri . '?' . $queryParams;
      }

      $request = $this->http_client->get($url . $uri, $options);
      $response = $request->getBody()->getContents();
      $response = Json::decode($response);

      $response_code = 200;
      return [
        'response' => $response,
        'response_code' => $response_code,
      ];
    }
    catch (RequestException $e) {
      $error = [];
      $error['error'] = $e->getMessage();
      \Drupal::logger('spotify_artists')->error('<pre><code>Error:' . $error['error'] . '</code></pre>');
      $response = [
        'error' => $error['error'],
      ];
      $response_code = $e->getCode();
      $messenger = \Drupal::messenger();
      $messenger->addMessage(t('API is not responding.'), 'error');
      return [
        'response' => $response,
        'response_code' => $response_code,
      ];
    }
  }

  public function postRequest($url, $uri, $parameters, $options) {
    try {
      $options['headers'] = [
        'Content-Type' => 'application/json',
        'Authorization' => 'Bearer ' . $this->access_token,
      ];

      $queryParams = UrlHelper::buildQuery($parameters);
      $request = $this->http_client->post($url . $uri . '?' . $queryParams, $options);
      $response = $request->getBody()->getContents();
      $response = Json::decode($response);

      $response_code = 200;
      return [
        'response' => $response,
        'response_code' => $response_code,
      ];
    }
    catch (RequestException $e) {
      $error = [];
      $error['error'] = $e->getMessage();
      \Drupal::logger('spotify_artists')->error('<pre><code>Error:' . $error['error'] . '</code></pre>');
      $response = [
        'error' => $error['error'],
      ];
      $response_code = $e->getCode();
      $messenger = \Drupal::messenger();
      $messenger->addMessage(t('API is not responding.'), 'error');
      return [
        'response' => $response,
        'response_code' => $response_code,
      ];
    }
  }

  /**
   * Request an access token using the Client Credentials Flow.
   *
   * @return false|mixed The token when an access token was successfully granted, false otherwise.
   */
  public function requestCredentialsToken() {
    if (empty($this->client_secret) || empty($this->client_id)) {
      $messenger = \Drupal::messenger();
      $messenger->addMessage(t('API credentials not set.'), 'error');
      return;
    }
    $payload = base64_encode($this->client_id . ':' . $this->client_secret);

    $headers = [
      'Authorization' => 'Basic ' . $payload,
    ];

    try {
      $options = [
        'headers' => $headers,
        'form_params' => [
          'grant_type' => 'client_credentials',
        ]
      ];

      $request = $this->http_client->post($this->account_url .'/api/token', $options);
      $response = $request->getBody()->getContents();
      $response = Json::decode($response);

      if (isset($response['access_token'])) {
        $this->cache->set(
          'access_token',
          $response['access_token'],
          time() + $response->expires_in - 100
        );

        return $response['access_token'];
      }
    }
    catch (RequestException $e) {
      $error = [];
      $error['error'] = $e->getMessage();
      \Drupal::logger('spotify_artists')->warning('<pre><code>Error:' . $error['error'] . '</code></pre>');
      $messenger = \Drupal::messenger();
      $messenger->addMessage(t('API is not responding.'), 'error');
    }

    return false;
  }
}
