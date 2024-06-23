<?php

namespace Drupal\get_blogs\Controller;

use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Drupal\Core\Database\Database;

class BlogController extends ControllerBase {

  /**
   * Get the database connection.
   *
   * @return \Drupal\Core\Database\Connection
   *   The database connection.
   */
  private function getDatabaseConnection() {
    return Database::getConnection();
  }

  /**
   * Get all blog posts.
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   The response containing the list of blogs.
   */
  public function getBlogs() {
    $connection = $this->getDatabaseConnection();
    $query = $connection->select('blog_posts', 'bp')
      ->fields('bp');
    $result = $query->execute();

    $blogs = [];
    foreach ($result as $record) {
      $blogs[] = (array) $record;
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
    $connection = $this->getDatabaseConnection();
    $record = $connection->select('blog_posts', 'bp')
      ->fields('bp')
      ->condition('id', $id)
      ->execute()
      ->fetchAssoc();

    if ($record) {
      return new JsonResponse(['message' => 'Blog post found', 'blog' => $record], 200);
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

    $connection = $this->getDatabaseConnection();
    try {
      $connection->insert('blog_posts')
        ->fields([
          'title' => $data['title'],
          'body' => $data['body'],
        ])
        ->execute();

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

    $connection = $this->getDatabaseConnection();
    $fields_to_update = array_filter($data, function ($value, $key) {
      return in_array($key, ['title', 'body']);
    }, ARRAY_FILTER_USE_BOTH);

    if (empty($fields_to_update)) {
      return new JsonResponse(['message' => 'No changes specified'], 304);
    }

    $affected_rows = $connection->update('blog_posts')
      ->fields($fields_to_update)
      ->condition('id', $id)
      ->execute();

    if ($affected_rows) {
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
    $connection = $this->getDatabaseConnection();
    $affected_rows = $connection->delete('blog_posts')
      ->condition('id', $id)
      ->execute();

    if ($affected_rows) {
      return new JsonResponse(['message' => 'Blog post deleted'], 200);
    }
    else {
      return new JsonResponse(['message' => 'Error deleting blog post'], 500);
    }
  }

}
