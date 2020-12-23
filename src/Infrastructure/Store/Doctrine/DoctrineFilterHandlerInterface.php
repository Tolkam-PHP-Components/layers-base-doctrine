<?php declare(strict_types=1);

namespace Tolkam\Layers\Base\Infrastructure\Store\Doctrine;

use Doctrine\DBAL\Query\QueryBuilder;

interface DoctrineFilterHandlerInterface
{
    /**
     * @param QueryBuilder $query
     *
     * @return void
     */
    public function setQuery(QueryBuilder $query): void;
    
    /**
     * @param array|string[] $tables
     *
     * @return void
     */
    public function setTables(array $tables): void;
    
    /**
     * Sets primary table alias
     *
     * @param string $alias
     */
    public function setPrimaryAlias(string $alias): void;
}
