<?php

namespace Drupal\get_blogs\Controller;

use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Drupal\Core\Database\Database;

class BlogController extends ControllerBase {

    private function getDatabaseConnection() {
        return Database::getConnection();
    }

    // Get all blog posts
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

    // Get a single blog post by ID
    public function getBlog($id) {
        $connection = $this->getDatabaseConnection();
        $record = $connection->select('blog_posts', 'bp')
            ->fields('bp')
            ->condition('id', $id)
            ->execute()
            ->fetchAssoc();

        if ($record) {
            return new JsonResponse(['message' => 'Blog post found', 'blog' => $record], 200);
        } else {
            return new JsonResponse(['message' => 'Blog post not found'], 404);
        }
    }

    // Create a new blog post
    public function createBlog(Request $request) {
        $data = json_decode($request->getContent(), true);
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
        } catch (\Exception $e) {
            watchdog_exception('get_blogs', $e);
            return new JsonResponse(['message' => 'Error creating blog post: ' . $e->getMessage()], 500);
        }
    }

    // Update an existing blog post
    public function updateBlog($id, Request $request) {
        $data = json_decode($request->getContent(), true);
        if (!$data) {
            return new JsonResponse(['message' => 'Invalid data provided'], 400);
        }

        $connection = $this->getDatabaseConnection();
        $fieldsToUpdate = array_filter($data, fn($value, $key) => in_array($key, ['title', 'body']), ARRAY_FILTER_USE_BOTH);

        if (empty($fieldsToUpdate)) {
            return new JsonResponse(['message' => 'No changes specified'], 304);
        }

        if ($connection->update('blog_posts')->fields($fieldsToUpdate)->condition('id', $id)->execute()) {
            return new JsonResponse(['message' => 'Blog post updated'], 200);
        } else {
            return new JsonResponse(['message' => 'Blog post not found'], 404);
        }
    }

    // Delete a blog post
    public function deleteBlog($id) {
        $connection = $this->getDatabaseConnection();
        if ($connection->delete('blog_posts')->condition('id', $id)->execute()) {
            return new JsonResponse(['message' => 'Blog post deleted'], 200);
        } else {
            return new JsonResponse(['message' => 'Error deleting blog post'], 500);
        }
    }


}
