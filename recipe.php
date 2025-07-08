<?php
require_once 'connect.php'; // Include database connection

class Recipe {
    private $pdo;

    public function __construct($pdo) {
        $this->pdo = $pdo;
    }

    // Fetch all recipes with author, category, and tags
    public function getAll() {
        $stmt = $this->pdo->prepare("
            SELECT 
                r.*, 
                u.username AS author, 
                u.picture AS author_picture,
                c.name AS category,
                GROUP_CONCAT(t.name SEPARATOR ', ') AS tags
            FROM recipes r
            LEFT JOIN users u ON r.user_id = u.id
            LEFT JOIN categories c ON r.category_id = c.id
            LEFT JOIN recipe_tags rt ON r.id = rt.recipe_id
            LEFT JOIN tags t ON rt.tag_id = t.id
            GROUP BY r.id
            ORDER BY r.created_at DESC
        ");
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Fetch a single recipe with full details including tags
    public function getById($id) {
        // Get recipe basic info
        $stmt = $this->pdo->prepare("
            SELECT 
                r.*, 
                u.username AS author,
                u.picture AS author_picture,
                c.name AS category
            FROM recipes r
            LEFT JOIN users u ON r.user_id = u.id
            LEFT JOIN categories c ON r.category_id = c.id
            WHERE r.id = ?
        ");
        $stmt->execute([$id]);
        $recipe = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$recipe) {
            return false;
        }

        // Get tags for this recipe
        $stmt = $this->pdo->prepare("
            SELECT t.id, t.name 
            FROM tags t
            JOIN recipe_tags rt ON t.id = rt.tag_id
            WHERE rt.recipe_id = ?
        ");
        $stmt->execute([$id]);
        $recipe['tags'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return $recipe;
    }

    // Create a new recipe with optional tags
    public function create($data) {
        try {
            $this->pdo->beginTransaction();

            // Insert recipe
            $stmt = $this->pdo->prepare("
                INSERT INTO recipes (
                    title, description, ingredients, instructions, 
                    image_url, prep_time, cook_time, servings, difficulty,
                    user_id, category_id
                ) VALUES (
                    :title, :description, :ingredients, :instructions, 
                    :image_url, :prep_time, :cook_time, :servings, :difficulty,
                    :user_id, :category_id
                )
            ");
            $stmt->execute([
                ':title' => $data['title'],
                ':description' => $data['description'],
                ':ingredients' => $data['ingredients'],
                ':instructions' => $data['instructions'],
                ':image_url' => $data['image_url'] ?? null,
                ':prep_time' => $data['prep_time'] ?? null,
                ':cook_time' => $data['cook_time'] ?? null,
                ':servings' => $data['servings'] ?? null,
                ':difficulty' => $data['difficulty'] ?? 'medium',
                ':user_id' => $data['user_id'],
                ':category_id' => $data['category_id']
            ]);
            $recipeId = $this->pdo->lastInsertId();

            // Insert tags if provided
            if (!empty($data['tags'])) {
                $tagStmt = $this->pdo->prepare("
                    INSERT INTO recipe_tags (recipe_id, tag_id) 
                    VALUES (?, ?)
                ");
                foreach ($data['tags'] as $tagId) {
                    $tagStmt->execute([$recipeId, $tagId]);
                }
            }

            $this->pdo->commit();
            return $recipeId;
        } catch (Exception $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }

    // Update an existing recipe
    public function update($id, $data) {
        try {
            $this->pdo->beginTransaction();

            // Update recipe
            $stmt = $this->pdo->prepare("
                UPDATE recipes
                SET 
                    title = :title,
                    description = :description,
                    ingredients = :ingredients,
                    instructions = :instructions,
                    image_url = :image_url,
                    prep_time = :prep_time,
                    cook_time = :cook_time,
                    servings = :servings,
                    difficulty = :difficulty,
                    category_id = :category_id,
                    updated_at = CURRENT_TIMESTAMP
                WHERE id = :id
            ");
            $stmt->execute([
                ':title' => $data['title'],
                ':description' => $data['description'],
                ':ingredients' => $data['ingredients'],
                ':instructions' => $data['instructions'],
                ':image_url' => $data['image_url'] ?? null,
                ':prep_time' => $data['prep_time'] ?? null,
                ':cook_time' => $data['cook_time'] ?? null,
                ':servings' => $data['servings'] ?? null,
                ':difficulty' => $data['difficulty'] ?? 'medium',
                ':category_id' => $data['category_id'],
                ':id' => $id
            ]);

            // Update tags - first delete existing, then insert new
            $deleteStmt = $this->pdo->prepare("
                DELETE FROM recipe_tags WHERE recipe_id = ?
            ");
            $deleteStmt->execute([$id]);

            if (!empty($data['tags'])) {
                $tagStmt = $this->pdo->prepare("
                    INSERT INTO recipe_tags (recipe_id, tag_id) 
                    VALUES (?, ?)
                ");
                foreach ($data['tags'] as $tagId) {
                    $tagStmt->execute([$id, $tagId]);
                }
            }

            $this->pdo->commit();
            return true;
        } catch (Exception $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }

    // Delete multiple recipes
    public function deleteMultiple($ids) {
        try {
            $this->pdo->beginTransaction();
            
            // First delete from recipe_tags
            $placeholders = implode(',', array_fill(0, count($ids), '?'));
            $stmt = $this->pdo->prepare("
                DELETE FROM recipe_tags 
                WHERE recipe_id IN ($placeholders)
            ");
            $stmt->execute($ids);

            // Then delete from recipes
            $stmt = $this->pdo->prepare("
                DELETE FROM recipes 
                WHERE id IN ($placeholders)
            ");
            $stmt->execute($ids);

            $this->pdo->commit();
            return true;
        } catch (Exception $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }

    // Get all categories
    public function getAllCategories() {
        $stmt = $this->pdo->prepare("
            SELECT * FROM categories 
            ORDER BY name ASC
        ");
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Get all tags
    public function getAllTags() {
        $stmt = $this->pdo->prepare("
            SELECT * FROM tags 
            ORDER BY name ASC
        ");
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Check if recipe is favorited by user
    public function isFavorited($userId, $recipeId) {
        $stmt = $this->pdo->prepare("
            SELECT COUNT(*) 
            FROM favorites 
            WHERE user_id = ? AND recipe_id = ?
        ");
        $stmt->execute([$userId, $recipeId]);
        return $stmt->fetchColumn() > 0;
    }

    // Toggle favorite status
    public function toggleFavorite($userId, $recipeId) {
        if ($this->isFavorited($userId, $recipeId)) {
            $stmt = $this->pdo->prepare("
                DELETE FROM favorites 
                WHERE user_id = ? AND recipe_id = ?
            ");
            return $stmt->execute([$userId, $recipeId]);
        } else {
            $stmt = $this->pdo->prepare("
                INSERT INTO favorites (user_id, recipe_id) 
                VALUES (?, ?)
            ");
            return $stmt->execute([$userId, $recipeId]);
        }
    }
}
?>