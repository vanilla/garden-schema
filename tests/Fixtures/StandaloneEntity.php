<?php
/**
 * @author Adam Charron <adam@vanillaforums.com>
 * @copyright 2009-2024 Vanilla Forums Inc.
 * @license MIT
 */

namespace Garden\Schema\Tests\Fixtures;

use Garden\Schema\EntityInterface;
use Garden\Schema\EntityTrait;
use Garden\Schema\Schema;
use Garden\Schema\SchemaVariant;

/**
 * A standalone implementation of EntityInterface that doesn't extend Entity.
 *
 * This demonstrates that classes with existing parent classes can implement
 * the EntityInterface and work with the schema system using EntityTrait.
 */
class StandaloneEntity extends SomeExistingBaseClass implements EntityInterface {
    use EntityTrait;

    private static ?Schema $schema = null;
    private static ?Schema $fragmentSchema = null;

    public int $id;
    public string $name;
    public string $description = '';

    public static function getSchema(?\BackedEnum $variant = null): Schema {
        $variant ??= SchemaVariant::Full;

        if ($variant === SchemaVariant::Fragment) {
            if (self::$fragmentSchema === null) {
                self::$fragmentSchema = new Schema([
                    'type' => 'object',
                    'properties' => [
                        'id' => ['type' => 'integer'],
                        'name' => ['type' => 'string'],
                    ],
                    'required' => ['id', 'name'],
                ]);
            }
            return self::$fragmentSchema;
        }

        if (self::$schema === null) {
            self::$schema = new Schema([
                'type' => 'object',
                'properties' => [
                    'id' => ['type' => 'integer'],
                    'name' => ['type' => 'string'],
                    'description' => ['type' => 'string', 'default' => ''],
                ],
                'required' => ['id', 'name'],
            ]);
        }
        return self::$schema;
    }

    public static function fromValidated(array $clean, ?\BackedEnum $variant = null): static {
        $entity = new static();
        $entity->id = $clean['id'];
        $entity->name = $clean['name'];
        $entity->description = $clean['description'] ?? '';
        return $entity;
    }

    public function toArray(?\BackedEnum $variant = null): array {
        $effectiveVariant = $variant ?? $this->serializationVariant;

        if ($effectiveVariant === SchemaVariant::Fragment) {
            return [
                'id' => $this->id,
                'name' => $this->name,
            ];
        }

        return [
            'id' => $this->id,
            'name' => $this->name,
            'description' => $this->description,
        ];
    }

    /**
     * Clear cached schemas (for testing).
     */
    public static function clearCache(): void {
        self::$schema = null;
        self::$fragmentSchema = null;
    }
}
