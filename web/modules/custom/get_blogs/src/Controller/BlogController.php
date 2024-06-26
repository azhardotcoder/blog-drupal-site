<?php

namespace Drupal\get_blogs\Controller;

use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\node\Entity\Node;

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
   * Get all blog posts.
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   The response containing the list of blogs.
   */
  public function getBlogs() {
    // Load the blog nodes.
    $query = $this->entityTypeManager->getStorage('node')->getQuery()
      ->condition('type', 'blog')
      ->condition('status', 1)
      ->sort('created', 'DESC')
      ->accessCheck(TRUE);

    $nids = $query->execute();
    $nodes = $this->entityTypeManager->getStorage('node')->loadMultiple($nids);

    $blogs = [];
    foreach ($nodes as $node) {
      $blogs[] = [
        'id' => $node->id(),
        'title' => $node->getTitle(),
        'body' => $node->get('body')->value,
      ];
    }

    return new JsonResponse(['message' => 'List of blogs', 'blogs' => $blogs], 200);
  }

  /**
   * Get a single blog post by ID.
   *
   * @param int $id
   *   The blog post ID.
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   The response containing the blog post or an error message.
   */
  public function getBlog($id) {
    $node = $this->entityTypeManager->getStorage('node')->load($id);

    if ($node && $node->bundle() === 'blog' && $node->isPublished()) {
      $blog = [
        'id' => $node->id(),
        'title' => $node->getTitle(),
        'body' => $node->get('body')->value,
      ];
      return new JsonResponse(['message' => 'Blog post found', 'blog' => $blog], 200);
    }
    else {
      return new JsonResponse(['message' => 'Blog post not found'], 404);
    }
  }

  /**
   * Create a new blog post.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request object.
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   The response containing a success or error message.
   */
  public function createBlog(Request $request) {
    $data = json_decode($request->getContent(), TRUE);
    if (!$data || empty($data['title']) || empty($data['body'])) {
      return new JsonResponse(['message' => 'Invalid data provided'], 400);
    }

    try {
      $node = Node::create([
        'type' => 'blog',
        'title' => $data['title'],
        'body' => [
          'value' => $data['body'],
          'format' => 'basic_html',
        ],
        'status' => 1,
      ]);
      $node->save();

      return new JsonResponse(['message' => 'Blog post created'], 201);
    }
    catch (\Exception $e) {
      \Drupal::logger('get_blogs')->error($e->getMessage());
      return new JsonResponse(['message' => 'Error creating blog post: ' . $e->getMessage()], 500);
    }
  }

  /**
   * Update an existing blog post.
   *
   * @param int $id
   *   The blog post ID.
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request object.
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   The response containing a success or error message.
   */
  public function updateBlog($id, Request $request) {
    $data = json_decode($request->getContent(), TRUE);
    if (!$data) {
      return new JsonResponse(['message' => 'Invalid data provided'], 400);
    }

    $node = $this->entityTypeManager->getStorage('node')->load($id);

    if ($node && $node->bundle() === 'blog') {
      if (isset($data['title'])) {
        $node->setTitle($data['title']);
      }
      if (isset($data['body'])) {
        $node->set('body', [
          'value' => $data['body'],
          'format' => 'basic_html',
        ]);
      }
      $node->save();

      return new JsonResponse(['message' => 'Blog post updated'], 200);
    }
    else {
      return new JsonResponse(['message' => 'Blog post not found'], 404);
    }
  }

  /**
   * Delete a blog post.
   *
   * @param int $id
   *   The blog post ID.
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   The response containing a success or error message.
   */
  public function deleteBlog($id) {
    $node = $this->entityTypeManager->getStorage('node')->load($id);

    if ($node && $node->bundle() === 'blog') {
      $node->delete();
      return new JsonResponse(['message' => 'Blog post deleted'], 200);
    }
    else {
      return new JsonResponse(['message' => 'Blog post not found'], 404);
    }
  }

}
