<?php

namespace Drupal\my_blog_module\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\node\NodeInterface;

class BlogController extends ControllerBase {

  /**
   * The entity type manager service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Constructs a new BlogController.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager service.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager) {
    $this->entityTypeManager = $entity_type_manager;
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
   * Returns a list of blog nodes.
   *
   * @return array
   *   A render array.
   */
  public function listBlogs() {
    // Load the blog nodes with access checking enabled.
    $query = $this->entityTypeManager->getStorage('node')->getQuery()
      ->condition('type', 'blog')
      ->condition('status', 1)
      ->sort('created', 'DESC')
      ->accessCheck(TRUE);

    $nids = $query->execute();

    // Load the nodes.
    $nodes = $this->entityTypeManager->getStorage('node')->loadMultiple($nids);

    // Create a render array for the blogs.
    $items = [];
    foreach ($nodes as $node) {
      $items[] = [
        '#type' => 'markup',
        '#markup' => $node->toLink()->toString(),
      ];
    }

    return [
      '#theme' => 'item_list',
      '#items' => $items,
      '#title' => $this->t('Blog Posts'),
    ];
  }

}
