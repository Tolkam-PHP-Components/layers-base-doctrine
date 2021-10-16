<?php declare(strict_types=1);

namespace Tolkam\Layers\Base\Infrastructure\Store\Doctrine;

use Doctrine\DBAL\Query\QueryBuilder;

abstract class DoctrineFilterHandler implements DoctrineFilterHandlerInterface
{
    /**
     * @var QueryBuilder
     */
    private QueryBuilder $query;
    
    /**
     * @var array|string[]
     */
    private array $tables;
    
    /**
     * @var string
     */
    private string $primaryAlias;
    
    /**
     * @inheritDoc
     */
    public function setQuery(QueryBuilder $query): void
    {
        $this->query = $query;
    }
    
    /**
     * @inheritDoc
     */
    public function setTables(array $tables): void
    {
        $this->tables = $tables;
    }
    
    /**
     * @inheritDoc
     */
    public function setPrimaryAlias(string $alias): void
    {
        $this->primaryAlias = $alias;
    }
    
    /**
     * Gets the query
     *
     * @return QueryBuilder
     */
    protected function getQuery(): QueryBuilder
    {
        return $this->query;
    }
    
    /**
     * Gets the tables
     *
     * @return array|string[]
     * @noinspection PhpDocSignatureInspection
     */
    protected function getTables(): array
    {
        return $this->tables;
    }
    
    /**
     * Gets the primary table alias
     *
     * @return string
     */
    public function getPrimaryAlias(): string
    {
        return $this->primaryAlias;
    }
    
    /**
     * Adds primary alias to the column name
     *
     * @param string $column
     *
     * @return string
     */
    public function makeAliased(string $column): string
    {
        return $this->primaryAlias
            ? $this->primaryAlias . '.' . $column
            : $column;
    }
}
