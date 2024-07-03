namespace Drupal\custom_rest_module\Plugin\rest\resource;

use Drupal\rest\Plugin\ResourceBase;
use Drupal\rest\ResourceResponse;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Serializer\SerializerInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Psr\Log\LoggerInterface;

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
   * The serializer service.
   */
  protected $serializer;

  /**
   * A current user instance.
   */
  protected $currentUser;

  /**
   * The entity type manager.
   */
  protected $entityTypeManager;

  /**
   * Constructs a new CustomRestResource object.
   *
   * @param array $configuration
   * @param string $plugin_id
   * @param mixed $plugin_definition
   * @param array $serializer_formats
   * @param LoggerInterface $logger
   * @param SerializerInterface $serializer
   * @param AccountProxyInterface $current_user
   * @param EntityTypeManagerInterface $entity_type_manager
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    array $serializer_formats,
    LoggerInterface $logger,
    SerializerInterface $serializer,
    AccountProxyInterface $current_user,
    EntityTypeManagerInterface $entity_type_manager
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $serializer_formats, $logger);
    $this->serializer = $serializer;
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
      $container->get('serializer'),
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
    $node = $this->entityTypeManager->getStorage('node')->load($node);
    if ($node) {
      if ($node->access('view', $this->currentUser)) {
        $data = $this->serializer->serialize($node, 'json');
        return new ResourceResponse(json_decode($data), 200);
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
    $data = $this->serializer->deserialize($request->getContent(), 'array', 'json');

    if (!$this->currentUser->hasPermission('create content')) {
      throw new AccessDeniedHttpException();
    }

    $node = $this->entityTypeManager->getStorage('node')->create([
      'type' => $data['type'],
      'title' => $data['title'],
    ]);

    $node->save();

    $response_data = $this->serializer->serialize($node, 'json');
    return new ResourceResponse(json_decode($response_data), 201);
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
    $data = $this->serializer->deserialize($request->getContent(), 'array', 'json');
    $node = $this->entityTypeManager->getStorage('node')->load($node_id);

    if ($node && $node->access('update', $this->currentUser)) {
      foreach ($data as $field_name => $value) {
        if ($node->hasField($field_name)) {
          $node->set($field_name, $value);
        }
      }
      $node->save();
      $response_data = $this->serializer->serialize($node, 'json');
      return new ResourceResponse(json_decode($response_data), 200);
    }
    throw new BadRequestHttpException('Node not found or access denied');
  }

  /**
   * Responds to DELETE requests and deletes an existing node.
   *
   * @param int $node
   *
   * @return \Drupal\rest\ResourceResponse
   */
  public function delete($node) {
    $node = $this->entityTypeManager->getStorage('node')->load($node);
    if ($node && $node->access('delete', $this->currentUser)) {
      $node->delete();
      return new ResourceResponse(NULL, 204);
    }
    throw new BadRequestHttpException('Node not found or access denied');
  }
}
