<?php


namespace Drupal\spotify_artists\Controller;

use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Config\ConfigFactory;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\spotify_artists\SpotifyWebAPI;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Class ArtistsController
 * @package Drupal\spotify_artists\Controller
 */
class ArtistsController extends ControllerBase implements ContainerInjectionInterface {

  /**
   * @var SpotifyWebAPI
   */
  protected $spotify_web_api;

  /**
   * @var ConfigFactory
   */
  protected $config_factory;

  /**
   * @var CacheBackendInterface
   */
  protected $cache;

  /**
   * ArtistsController constructor.
   * @param SpotifyWebAPI $spotify_web_api
   * @param ConfigFactory $config_factory
   */
  public function __construct(SpotifyWebAPI $spotify_web_api, ConfigFactory $config_factory, CacheBackendInterface $cache) {
    $this->spotify_web_api = $spotify_web_api;
    $this->config_factory = $config_factory;
    $this->cache = $cache;
  }

  /**
   * @param ContainerInterface $container
   * @return ArtistsController|static
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('spotify_artists.web_api'),
      $container->get('config.factory'),
      $container->get('cache.default')
    );
  }

  /**
   * @param $id
   * @return array
   */
  public function getArtist($id) {
    // load from cache if not expired.
    $dataCache = $this->cache->get('artist_' . $id);
    if (!($dataCache == FALSE || $dataCache->expire < time())) {
      $artist =  $dataCache->data;
    }
    else {
      $spotify_config = $this->config_factory->get('spotify_artists.settings');

      $uri = '/v1/artists/' . $id;

      $response = $this->spotify_web_api->getRequest($spotify_config->get('api_url'),  $uri);
      $artist = $response['response'];
      $this->cache->set(
        'artist_' . $id,
        $response['response'],
        time() + 3600
      );
    }

    return [
      '#theme' => 'artist__details',
      '#content' => $artist,
    ];
  }
}
