<?php declare(strict_types=1);

namespace Tolkam\Layers\Base\Infrastructure\Store\Doctrine;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;
use Doctrine\DBAL\Query\QueryBuilder;
use Generator;
use Tolkam\Layers\Base\Domain\Collection\SnapshotCollection;
use Tolkam\Layers\Base\Domain\Entity\Snapshot;
use Tolkam\Layers\Base\Domain\Repository\Filters;
use Tolkam\Layers\Base\Domain\Repository\Pagination;
use Tolkam\Layers\Base\Domain\Store\SnapshotStoreException;
use Tolkam\Layers\Base\Domain\Store\SnapshotStoreInterface;
use Tolkam\Layers\Base\Infrastructure\Store\FilterHandlerRegistry;
use Tolkam\Layers\Base\Infrastructure\Store\Traits\TableAwareTrait;
use Tolkam\Pagination\Paginator\DoctrineDbalCursorPaginator;
use Tolkam\Pagination\Paginator\DoctrineDbalNullPaginator;
use Tolkam\Pagination\Paginator\DoctrineDbalOffsetPaginator;
use Tolkam\Pagination\PaginatorInterface;

abstract class DoctrineSnapshotStore implements SnapshotStoreInterface
{
    use TableAwareTrait;
    
    /**
     * Default table alias
     */
    public const PRIMARY_ALIAS = 't';
    
    /**
     * @var Connection
     */
    protected Connection $connection;
    
    /**
     * @var FilterHandlerRegistry
     */
    protected FilterHandlerRegistry $filterHandlerRegistry;
    
    /**
     * @var QueryBuilder|null
     */
    private ?QueryBuilder $nextQuery = null;
    
    /**
     * @param Connection            $connection
     * @param FilterHandlerRegistry $filterHandlerRegistry
     */
    public function __construct(
        Connection $connection,
        FilterHandlerRegistry $filterHandlerRegistry
    ) {
        $this->connection = $connection;
        $this->filterHandlerRegistry = $filterHandlerRegistry;
    }
    
    /**
     * Gets primary identifier column name
     *
     * @return string
     */
    public static function identifierName(): string
    {
        return 'id';
    }
    
    /**
     * Gets primary identifier column type
     *
     * @return int
     */
    public static function identifierType(): int
    {
        return ParameterType::INTEGER;
    }
    
    /**
     * Creates empty collection
     *
     * @return SnapshotCollection
     */
    abstract public static function newCollection(): SnapshotCollection;
    
    /**
     * @inheritDoc
     */
    public function getByIds(array $values): SnapshotStoreInterface
    {
        $paramType = static::identifierType() === ParameterType::INTEGER
            ? $this->connection::PARAM_INT_ARRAY
            : $this->connection::PARAM_STR_ARRAY;
        
        $query = $this->baseSelect()
            ->andWhere(static::identifierName() . ' IN (:values)')
            ->setParameter(':values', $values, $paramType);
        
        return $this->setQuery($query);
    }
    
    /**
     * @inheritDoc
     */
    public function getAll(): SnapshotStoreInterface
    {
        return $this->setQuery($this->baseSelect());
    }
    
    /**
     * @inheritDoc
     */
    public function applyFilters(Filters $filters): SnapshotStoreInterface
    {
        foreach ($filters as $filter) {
            $handler = $this->filterHandlerRegistry->getHandler(get_class($filter));
            
            if ($handler instanceof DoctrineFilterHandlerInterface) {
                $handler->setQuery($this->getQuery());
                $handler->setTables($this->getTables());
                $handler->setPrimaryAlias(static::PRIMARY_ALIAS);
            }
            
            $handler($filter);
        }
        
        return $this;
    }
    
    /**
     * @inheritDoc
     */
    public function getResults(
        Pagination $pagination = null
    ): SnapshotCollection {
        
        $query = $this->getQuery();
        
        return static::newCollection()::create(function (SnapshotCollection $self) use (
            $query,
            $pagination
        ) {
            $paginator = $this->makePaginator($query, $pagination);
            $self->setPaginationResult($paginator->paginate());
            $this->setQuery(null);
            
            yield from $this->createSnapshots($paginator->getItems());
        });
    }
    
    /**
     * Sets the query
     *
     * @param QueryBuilder|null $query
     *
     * @return self
     */
    protected function setQuery(?QueryBuilder $query): self
    {
        $this->nextQuery = $query;
        
        return $this;
    }
    
    /**
     * @return QueryBuilder
     */
    protected function getQuery(): QueryBuilder
    {
        if (!$this->nextQuery) {
            throw new SnapshotStoreException('Query was not set yet');
        }
        
        return $this->nextQuery;
    }
    
    /**
     * Creates snapshot from retrieved row
     *
     * @param array $row
     *
     * @return Snapshot|mixed
     */
    protected function createSnapshot(array $row): Snapshot
    {
        $collection = static::newCollection();
        
        /** @var Snapshot $snapshotClass */
        $snapshotClass = $collection::itemType();
        
        return $snapshotClass::fromArray($row);
    }
    
    /**
     * Creates snapshots from retrieved rows
     *
     * @param Generator $rows
     *
     * @return Generator
     */
    protected function createSnapshots(Generator $rows): Generator
    {
        while ($rows->valid()) {
            $row = $rows->current();
            $key = $row[static::identifierName()] ?? $rows->key();
            
            yield $key => $this->createSnapshot($row);
            $rows->next();
        }
    }
    
    /**
     * @param array|string|null $columns
     * @param string|null       $alias
     *
     * @return QueryBuilder
     */
    protected function baseSelect(
        $columns = null,
        string $alias = self::PRIMARY_ALIAS
    ): QueryBuilder {
        return $this->connection->createQueryBuilder()
            ->select($columns ?? $alias . '.*')
            ->from($this->getTable(), $alias);
    }
    
    /**
     * Creates paginator from pagination configuration
     *
     * @param QueryBuilder    $queryBuilder
     * @param Pagination|null $pagination
     *
     * @return PaginatorInterface
     */
    private function makePaginator(
        QueryBuilder $queryBuilder,
        Pagination $pagination = null
    ): PaginatorInterface {
        
        if (!$pagination) {
            return new DoctrineDbalNullPaginator($queryBuilder);
        }
        
        if ($pagination->isCursorPagination()) {
            $paginator = new DoctrineDbalCursorPaginator($queryBuilder);
            
            $paginator->setMaxResults($pagination->maxResults);
            $paginator->setAfter($pagination->nextCursor);
            $paginator->setBefore($pagination->previousCursor);
            
            if ($pagination->reverseResults) {
                $paginator->reverseResults();
            }
        }
        else {
            $paginator = new DoctrineDbalOffsetPaginator(
                $queryBuilder,
                $pagination->currentCursor,
                $pagination->maxResults
            );
        }
        
        if ($primarySortProp = $pagination->primarySortProp) {
            $paginator->setPrimarySort($primarySortProp, $pagination->primaryOrder);
        }
        
        if ($backupProp = $pagination->backupSortProp) {
            $paginator->setBackupSort($backupProp, $pagination->backupOrder);
        }
        
        return $paginator;
    }
    
    /**
     * @param string $table
     * @param array  $data
     * @param array  $where
     * @param array  $types
     *
     * @return int
     */
    protected function insertOrUpdate(
        string $table,
        array $data,
        array $where,
        array $types = []
    ): int {
        
        $query = $this->baseSelect('`' . implode('`, `', array_keys($where)) . '`');
        
        foreach ($where as $k => $v) {
            $query->andWhere("`$k` = :$k")
                ->setParameter(":$k", $v);
        }
        
        if ($query->execute()->rowCount() === 0) {
            $affected = $this->connection->insert($table, $data, $types);
        }
        else {
            $affected = $this->connection->update($table, $data, $where, $types);
        }
        
        return $affected;
    }
}
