namespace Drupal\custom_rest_module\Plugin\rest\resource;

use Drupal\rest\Plugin\ResourceBase;
use Drupal\rest\ResourceResponse;
use Drupal\Core\Session\AccountProxyInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Drupal\node\Entity\Node;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

/**
 * Provides a resource for custom REST.
 *
 * @RestResource(
 *   id = "custom_rest_resource",
 *   label = @Translation("Custom REST Resource"),
 *   uri_paths = {
 *     "canonical" = "/api/custom/{node}",
 *     "https://www.drupal.org/link-relations/create" = "/api/custom"
 *   }
 * )
 */
class CustomRestResource extends ResourceBase {
  /**
   * A current user instance.
   */
  protected AccountProxyInterface $currentUser;

  /**
   * The entity type manager.
   */
  protected EntityTypeManagerInterface $entityTypeManager;

  /**
   * Constructs a new CustomRestResource object.
   *
   * @param array $configuration
   * @param string $plugin_id
   * @param mixed $plugin_definition
   * @param array $serializer_formats
   * @param \Psr\Log\LoggerInterface $logger
   * @param \Drupal\Core\Session\AccountProxyInterface $current_user
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    array $serializer_formats,
    \Psr\Log\LoggerInterface $logger,
    AccountProxyInterface $current_user,
    EntityTypeManagerInterface $entity_type_manager
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $serializer_formats, $logger);
    $this->currentUser = $current_user;
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->getParameter('serializer.formats'),
      $container->get('logger.factory')->get('rest'),
      $container->get('current_user'),
      $container->get('entity_type.manager')
    );
  }

  /**
   * Responds to GET requests for a specific node.
   * 
   * @param int $node
   *   The ID of the node to retrieve.
   *
   * @return \Drupal\rest\ResourceResponse
   */
  public function get($node) {
    if ($node = Node::load($node)) {
      if ($node->access('view', $this->currentUser)) {
        return new ResourceResponse($node);
      }
      throw new AccessDeniedHttpException();
    }
    throw new BadRequestHttpException('Node not found');
  }

  /**
   * Responds to POST requests and saves a new node.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *
   * @return \Drupal\rest\ResourceResponse
   */
  public function post(Request $request) {
    $data = json_decode($request->getContent(), TRUE);

    if (!$this->currentUser->hasPermission('create content')) {
      throw new AccessDeniedHttpException();
    }

    $node = Node::create([
      'type' => $data['type'],
      'title' => $data['title'],
    ]);

    $node->save();

    return new ResourceResponse($node, 201);
  }

  /**
   * Responds to PATCH requests and updates an existing node.
   *
   * @param int $node_id
   * @param \Symfony\Component\HttpFoundation\Request $request
   *
   * @return \Drupal\rest\ResourceResponse
   */
  public function patch($node_id, Request $request) {
    $data = json_decode($request->getContent(), TRUE);
    $node = Node::load($node_id);

    if ($node && $node->access('update', $this->currentUser)) {
      foreach ($data as $field_name => $value) {
        if ($node->hasField($field_name)) {
          $node->set($field_name, $value);
        }
      }
      $node->save();
      return new ResourceResponse($node);
    }
    throw new BadRequestHttpException('Node not found or access denied');
  }

  /**
   * Responds to DELETE requests and deletes an existing node.
   *
   * @param int $node
   *
   * @return \Drupal\rest\ModifiedResourceResponse
   */
  public function delete($node) {
    $node = Node::load($node);
    if ($node && $node->access('delete', $this->currentUser)) {
      $node->delete();
      return new ResourceResponse(NULL, 204);
    }
    throw new BadRequestHttpException('Node not found or access denied');
  }
}
