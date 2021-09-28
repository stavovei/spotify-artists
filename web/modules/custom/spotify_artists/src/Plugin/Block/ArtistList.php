<?php


namespace Drupal\spotify_artists\Plugin\Block;


use Drupal\Core\Block\BlockBase;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Config\ConfigFactory;
use Drupal\Core\Form\FormStateInterface;
use Drupal\spotify_artists\SpotifyWebAPI;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;


/**
 * Provides an 'artists' block.
 *
 * @Block(
 *   id = "artists_block",
 *   admin_label = @Translation("Artists List"),
 * )
 */
class ArtistList extends BlockBase implements ContainerFactoryPluginInterface {

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

  public function __construct(array $configuration, $plugin_id, $plugin_definition, SpotifyWebAPI $spotify_web_api, ConfigFactory $config_factory, CacheBackendInterface $cache) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->spotify_web_api = $spotify_web_api;
    $this->config_factory = $config_factory;
    $this->cache = $cache;
  }

  /**
   * @param ContainerInterface $container
   * @param array $configuration
   * @param $plugin_id
   * @param $plugin_definition
   * @return static
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('spotify_artists.web_api'),
      $container->get('config.factory'),
      $container->get('cache.default')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return [] + parent::defaultConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  public function blockForm($form, FormStateInterface $form_state) {
    $form['no_artists'] = [
      '#type' => 'number',
      '#title' => $this->t('No of artists'),
      '#default_value' => $this->configuration['no_artists'],
      '#min' => 0,
      '#max' => 20,
    ];

    return $form;
  }

  public function blockValidate($form, FormStateInterface $form_state) {
    if ($form_state->getValue('no_artists') > 20) {
      $form_state->setErrorByName('no_artists', $this->t('A maximum of 20 artists is allowed.'));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function blockSubmit($form, FormStateInterface $form_state) {
    $this->configuration['no_artists'] = $form_state->getValue('no_artists');
  }

  /**
   * {@inheritdoc}
   */
  public function build() {
    $build = [];
    $build['#theme'] = 'artists__listing';

    $spotify_config = $this->config_factory->get('spotify_artists.settings');

    $artists_string = $spotify_config->get('artists_ids');

    $dataCache = $this->cache->get('artists_list_' . $this->configuration['no_artists']);
    if (!($dataCache == FALSE || $dataCache->expire < time())) {
      $artists =  $dataCache->data;
    }
    else {
      $artist_ids = explode(',', $artists_string);

      $temp_artists = [];

      // if the list of predefined artists is less than 20, then don't try to get more.
      $limit = 1;
      if (count($artist_ids) < $this->configuration['no_artists']) {
        $limit = count($artist_ids);
      }
      else {
        $limit = $this->configuration['no_artists'];
      }

      for ($i = 0; $i < $limit; $i++) {
        $temp_artists[] = $artist_ids[$i];
      }

      $artists_string = implode(',', $temp_artists);

      $parameters = [
        'ids' => $artists_string
      ];

      $uri = '/v1/artists';

      $response = $this->spotify_web_api->getRequest($spotify_config->get('api_url'),  $uri, [], $parameters);
      $artists = $response['response']['artists'];

      $this->cache->set(
        'artists_list_' . $this->configuration['no_artists'],
        $response['response'],
        time() + 3600
      );
    }

    $build['#content']['artists'] = $artists;

    return $build;
  }
}
