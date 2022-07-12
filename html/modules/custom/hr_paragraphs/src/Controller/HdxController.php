<?php

namespace Drupal\hr_paragraphs\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Url;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\RequestOptions;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Page controller for tabs.
 */
class HdxController extends ControllerBase {

  /**
   * The HTTP client to fetch the files with.
   *
   * @var \GuzzleHttp\ClientInterface
   */
  protected $httpClient;

  /**
   * {@inheritdoc}
   */
  public function __construct(ClientInterface $http_client) {
    $this->httpClient = $http_client;
  }

  /**
   * Build active facets for Hdx.
   *
   * @param string $base_url
   *   Base URL of the page.
   * @param array<string, mixed> $filters
   *   Filters from URL.
   * @param array<string, mixed> $all_facets
   *   List of all facets.
   *
   * @return array<int, mixed>
   *   List of active facets.
   */
  public function buildHdxActiveFacets(string $base_url, array $filters, array $all_facets) : array {
    $active_facets = [];
    $yes_no_filters = $this->getHdxYesNoFilters();
    $hdx_query_filters = $this->getHdxQueryFilters();

    foreach ($filters as $key => $keywords) {
      if (is_string($keywords)) {
        $name = $filters[$key];

        if (in_array($key, $yes_no_filters) || in_array($key, $hdx_query_filters)) {
          $name = $this->getHdxFilters($key) . ': ' . $this->t('No');
          if ($keywords == '1' || $keywords == 'true') {
            $name = $this->getHdxFilters($key) . ': ' . $this->t('Yes');
          }
        }
        elseif (isset($all_facets[$key])) {
          foreach ($all_facets[$key]['items'] as $item) {
            if ($item['name'] == $name) {
              $name = $item['display_name'];
              break;
            }
          }
        }

        $title = $this->t('Remove @name', ['@name' => $name]);
        $cloned_filters = $filters;
        unset($cloned_filters[$key]);
        $active_facets[] = [
          'title' => $title,
          'url' => Url::fromUserInput($base_url, [
            'query' => [
              'filters' => $cloned_filters,
            ],
          ]),
        ];
      }
      else {
        foreach ($keywords as $index => $keyword) {
          $name = $keyword;
          if (isset($all_facets[$key])) {
            foreach ($all_facets[$key]['items'] as $item) {
              if ($item['name'] == $keyword) {
                $name = $item['display_name'];
                break;
              }
            }
          }

          $title = $this->t('Remove @name', ['@name' => $name]);
          $cloned_filters = $filters;
          unset($cloned_filters[$key][$index]);
          $active_facets[] = [
            'title' => $title,
            'url' => Url::fromUserInput($base_url, [
              'query' => [
                'filters' => $cloned_filters,
              ],
            ]),
          ];
        }
      }
    }

    return $active_facets;
  }

  /**
   * Build facets for Hdx.
   *
   * @param string $base_url
   *   Base URL of the page.
   * @param array<string, mixed> $embedded_facets
   *   Parsed facets from request.
   * @param array<string, mixed> $filters
   *   Filters from URL.
   * @param array<string, mixed> $query_filters
   *   Filters from URL.
   *
   * @return array<int, mixed>
   *   List of facets.
   */
  public function buildHdxFacets(string $base_url, array $embedded_facets, array $filters, array $query_filters) : array {
    $facets = [];
    $facet_blocks = [];

    $yes_no_filters = $this->getHdxYesNoFilters();
    $hdx_query_filters = $this->getHdxQueryFilters();

    $allowed_filters = $this->getHdxFilters();
    foreach (array_keys($allowed_filters) as $key) {
      if (isset($embedded_facets[$key])) {
        $facets[$key] = $embedded_facets[$key];
      }
    }

    foreach (array_keys($hdx_query_filters) as $key) {
      foreach ($embedded_facets['queries'] as $query_facet) {
        if ($key == $query_facet['name']) {
          $facets[$hdx_query_filters[$key]] = [
            'items' => [
              [
                'display_name' => '1',
                'name' => '1',
                'count' => $query_facet['count'],
              ],
              [
                'display_name' => '0',
                'name' => '0',
              ],
            ],
          ];
        }
      }
    }

    foreach ($facets as $name => $facet) {
      $links = [];

      if (isset($facet['items']) && count($facet['items']) > 1) {
        // Sort facets.
        uasort($facet['items'], function ($a, $b) {
          return strcmp($a['display_name'], $b['display_name']);
        });

        foreach ($facet['items'] as $term) {
          $filter = [
            $name => $term['name'],
          ];

          // Check if facet is already active.
          if (isset($filters[$name])) {
            if (is_string($filters[$name]) && $filters[$name] == $filter[$name]) {
              continue;
            }
            if (is_array($filters[$name]) && in_array($filter[$name], $filters[$name])) {
              continue;
            }
          }

          // Remove facets part of the original url.
          if (isset($query_filters[$name])) {
            if (is_string($query_filters[$name]) && $query_filters[$name] == $filter[$name]) {
              continue;
            }
            if (is_array($query_filters[$name]) && in_array($filter[$name], $query_filters[$name])) {
              continue;
            }
          }

          $title = $term['display_name'];
          if (in_array($name, $yes_no_filters) || in_array($name, $hdx_query_filters)) {
            $title = $this->t('No');
            if ($term['display_name'] == '1' || $term['display_name'] == 'true') {
              $title = $this->t('Yes');
            }
          }

          if (isset($term['count'])) {
            $title = $title . ' (' . $term['count'] . ')';
          }

          $links[] = [
            'title' => $title,
            'url' => Url::fromUserInput($base_url, [
              'query' => [
                'filters' => array_merge_recursive($filters, $filter),
              ],
            ]),
          ];
        }

        if (count($links) > 1) {
          $facet_blocks[$name] = [
            'title' => $this->getHdxFilters($name),
            'links' => $links,
          ];
        }
      }
    }

    return $facet_blocks;
  }

  /**
   * Build Hdx parameters.
   *
   * @param int $offset
   *   Offset for the search.
   * @param int $limit
   *   Number of items to return.
   * @param array<string, mixed> $query_filters
   *   Filters from the original URL.
   *
   * @return array<string, mixed>
   *   Search parameters.
   */
  public function buildHdxParameters(int $offset, int $limit, array $query_filters) : array {
    $filter_to_facet = [
      'ext_subnational' => 'subnational',
      'ext_geodata' => 'has_geodata',
      'ext_requestdata' => 'extras_is_requestdata_type',
      'ext_quickcharts' => 'has_quickcharts',
      'ext_showcases' => 'has_showcases',
    ];

    $filter_to_query = [
      'ext_hxl' => '{!key=hxl} vocab_Topics:hxl',
      'ext_sadd' => '{!key=sadd} vocab_Topics:"sex and age disaggregated data - sadd"',
      'ext_cod' => '{!key=cod} vocab_Topics:"common operational dataset - cod"',
    ];

    $parameters = [
      'q' => '',
      'fq' => '+dataset_type:dataset -extras_archived:true',
      'fq_list' => [],
      'facet.query' => [],
      'facet.field' => [
        'groups',
        'res_format',
        'organization',
        'vocab_Topics',
        'license_id',
      ],
      'facet.limit' => 250,
      'sort' => 'score desc, if(gt(last_modified,review_date),last_modified,review_date) desc',
      'start' => $offset,
      'rows' => $limit,
    ];

    foreach ($filter_to_facet as $facet) {
      $parameters['facet.field'][] = $facet;
    }

    foreach ($filter_to_query as $facet) {
      $parameters['facet.query'][] = $facet;
    }

    // Pasted filters from URL are using OR.
    foreach ($query_filters as $key => $values) {
      switch ($key) {
        case 'q':
        case 'sort':
          $parameters[$key] = $values;
          break;

        case 'groups':
        case 'res_format':
        case 'organization':
        case 'vocab_Topics':
        case 'license_id':
          if (is_array($values)) {
            $parameters['fq_list'][] = $key . ':"' . implode('" OR "', $values) . '"';
          }
          else {
            $parameters['fq_list'][] = $key . ':"' . $values . '"';
          }
          break;

        case 'ext_subnational':
        case 'ext_geodata':
        case 'ext_requestdata':
        case 'ext_quickcharts':
        case 'ext_showcases':
        case 'ext_administrative_divisions':
        case 'ext_hxl':
        case 'ext_sadd':
        case 'ext_cod':
          $parameters[$key] = $values;
          break;
      }
    }

    return $parameters;
  }

  /**
   * Allowed filters.
   *
   * @param string $key
   *   Optional filter key.
   *
   * @return string|array<string, string>|bool
   *   Filter label or all filters.
   */
  public function getHdxFilters($key = NULL) {
    $filters = [
      'groups' => $this->t('Groups'),
      'res_format' => $this->t('Formats'),
      'organization' => $this->t('Organizations'),
      'vocab_Topics' => $this->t('Tags'),
      'license_id' => $this->t('Licenses'),
      'subnational' => $this->t('Sub-national'),
      'has_geodata' => $this->t('Geodata'),
      'extras_is_requestdata_type' => $this->t('HDX connect'),
      'has_quickcharts' => $this->t('Quickcharts'),
      'has_showcases' => $this->t('Showcases'),
      'cod' => $this->t('CODs'),
      'ext_cod' => $this->t('CODs'),
    ];

    if ($key) {
      if (array_key_exists($key, $filters)) {
        return $filters[$key];
      }
      else {
        return FALSE;
      }
    }
    else {
      return $filters;
    }
  }

  /**
   * Yes/No filters.
   *
   * @return array<string>
   *   All yes/no filters.
   */
  protected function getHdxYesNoFilters() {
    $filters = [
      'subnational',
      'has_geodata',
      'extras_is_requestdata_type',
      'has_quickcharts',
      'has_showcases',
      'cod',
    ];

    return $filters;
  }

  /**
   * Query filters.
   *
   * @return array<string>
   *   All query filters.
   */
  public function getHdxQueryFilters() {
    $filters = [
      'cod' => 'ext_cod',
    ];

    return $filters;
  }

  /**
   * Execute Hdx query.
   *
   * @param array<string, mixed> $parameters
   *   Search parameters.
   *
   * @return array<string, mixed>
   *   Raw results.
   */
  public function executeHdxQuery(array $parameters) : array {
    $endpoint = 'https://data.humdata.org/api/3/action/package_search';
    /** @var \Psr\Http\Message\ResponseInterface $response */
    $response = NULL;

    try {
      $response = $this->httpClient->request(
        'POST',
        $endpoint,
        [
          RequestOptions::JSON => $parameters,
        ]
      );
    }
    catch (RequestException $exception) {
      if ($exception->getCode() === 404) {
        throw new NotFoundHttpException();
      }
    }
    $body = $response->getBody() . '';
    $results = json_decode($body, TRUE);

    return $results['result'];
  }

  /**
   * Build Hdx objects.
   *
   * @param array<string, mixed> $results
   *   Raw results.
   *
   * @return array<string, mixed>
   *   Structured results.
   */
  public function buildHdxObjects(array $results) : array {
    $data = [];

    foreach ($results['results'] as $row) {
      $id = $row['id'];
      $data[$id] = [
        'id' => $id,
        'url' => 'https://data.humdata.org/dataset/' . $row['name'],
        'title' => $row['title'],
        'body' => check_markup($row['notes'] ?? '', 'markdown'),
        'date_changed' => $row['review_date'] ?? $row['metadata_modified'],
        'organization' => $row['organization']['name'],
        'organization_img' => $row['organization']['image_url'],
        'primary_country' => $row['groups'][0]['title'],
        'countries' => $row['groups'],
        'format' => 'Dataset',
        'sources' => 'HDX',
      ];

      if (isset($row['groups'])) {
        $groups = [];
        foreach ($row['groups'] as $group) {
          $groups[] = $group['display_name'];
        }
        $data[$id]['groups'] = implode(', ', $groups);
      }

      if (isset($row['resources'])) {
        $files = [];
        foreach ($row['resources'] as $file) {
          $files[] = [
            'url' => $file['download_url'],
            'filename' => $file['name'] ?? '',
            'description' => $file['description'] ?? '',
            'format' => $file['description'] ?? '',
          ];
        }
        $data[$id]['files'] = $files;
      }
    }

    return $data;
  }

  /**
   * Parse Hdx URL.
   *
   * @param string $url
   *   Full URL.
   *
   * @return array<string, mixed>
   *   Parsed query string.
   */
  public function parseHdxUrl(string $url) : array {
    $path = parse_url($url, PHP_URL_PATH);
    $query = parse_url($url, PHP_URL_QUERY);

    if ($path === '/dataset') {
      return hr_paragraphs_parse_str($query);
    }

    if (strpos($path, '/group/') === 0) {
      $parts = explode('/', $path);
      $filters = [
        'groups' => array_pop($parts),
      ];
      return array_merge_recursive($filters, hr_paragraphs_parse_str($query));
    }

    return [];
  }

}
