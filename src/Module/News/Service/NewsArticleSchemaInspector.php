<?php

declare(strict_types=1);

namespace App\Module\News\Service;

use Doctrine\DBAL\Connection;

final class NewsArticleSchemaInspector
{
    private ?array $columnNames = null;

    public function __construct(
        private readonly Connection $connection,
    ) {
    }

    public function isReady(): bool
    {
        return $this->tableExists() && [] === $this->missingColumns();
    }

    /**
     * @return list<string>
     */
    public function missingColumns(): array
    {
        return array_values(array_diff([
            'id',
            'title',
            'summary',
            'body',
            'image_url',
            'maintenance_starts_at',
            'maintenance_ends_at',
            'is_published',
            'published_at',
            'is_pinned',
            'archived_at',
            'category',
            'author_id',
            'created_at',
            'updated_at',
        ], $this->columnNames()));
    }

    public function tableExists(): bool
    {
        return $this->connection->createSchemaManager()->tablesExist(['news_articles']);
    }

    /**
     * @return list<string>
     */
    private function columnNames(): array
    {
        if (null !== $this->columnNames) {
            return $this->columnNames;
        }

        if (!$this->tableExists()) {
            return $this->columnNames = [];
        }

        $columns = $this->connection->createSchemaManager()->listTableColumns('news_articles');

        return $this->columnNames = array_values(array_map(
            static fn (string $name): string => mb_strtolower($name),
            array_keys($columns),
        ));
    }
}
