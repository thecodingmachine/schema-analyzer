<?php

namespace Mouf\Database\SchemaAnalyzer;

use Doctrine\Common\Cache\Cache;
use Doctrine\Common\Cache\VoidCache;
use Doctrine\DBAL\Schema\AbstractSchemaManager;
use Doctrine\DBAL\Schema\ForeignKeyConstraint;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Schema\SchemaException;
use Doctrine\DBAL\Schema\Table;
use Fhaculty\Graph\Edge\Base;
use Fhaculty\Graph\Graph;
use Fhaculty\Graph\Vertex;

/**
 * This class can analyze a database model.
 * In this class you will find.
 *
 * - Functions to automatically detect **junction tables**
 * - Functions to compute the shortest path between 2 tables based on the relationships stored in the schema.
 */
class SchemaAnalyzer
{
    private static $WEIGHT_FK = 1;
    private static $WEIGHT_INHERITANCE_FK = 0.1;
    private static $WEIGHT_JOINTURE_TABLE = 1.5;

    const WEIGHT_IMPORTANT = 0.75;
    const WEIGHT_IRRELEVANT = 2;
    const WEIGHT_IGNORE = INF;

    /**
     * @var AbstractSchemaManager
     */
    private $schemaManager;

    /**
     * @var Schema
     */
    private $schema;

    /**
     * @var Cache
     */
    private $cache;

    /**
     * @var string
     */
    private $cachePrefix;

    /**
     * Nested arrays containing table => column => cost.
     *
     * @var float[][]
     */
    private $alteredCosts = [];

    /**
     * Array containing table cost.
     *
     * @var float[]
     */
    private $alteredTableCosts = [];

    /**
     * @param AbstractSchemaManager $schemaManager
     * @param Cache|null            $cache          The Doctrine cache service to use to cache results (optional)
     * @param string|null           $schemaCacheKey The unique identifier for the schema manager. Compulsory if cache is set.
     */
    public function __construct(AbstractSchemaManager $schemaManager, Cache $cache = null, $schemaCacheKey = null)
    {
        $this->schemaManager = $schemaManager;
        if (empty($schemaCacheKey) && $cache) {
            throw new SchemaAnalyzerException('You must provide a schema cache key if you configure SchemaAnalyzer with cache support.');
        }
        if ($cache) {
            $this->cache = $cache;
        } else {
            $this->cache = new VoidCache();
        }
        $this->cachePrefix = $schemaCacheKey;
    }

    /**
     * Detect all junctions tables in the schema.
     * A table is a junction table if:.
     *
     * - it has exactly 2 foreign keys
     * - it has only 2 columns (or 3 columns if the third one is an autoincremented primary key).
     *
     * If $ignoreReferencedTables is true, junctions table that are pointed to by a foreign key of another
     * table are ignored.
     *
     * @param bool $ignoreReferencedTables
     *
     * @return Table[]
     */
    public function detectJunctionTables($ignoreReferencedTables = false)
    {
        $junctionTablesKey = $this->cachePrefix.'_junctiontables_'.($ignoreReferencedTables ? 'true' : 'false');
        $junctionTables = $this->cache->fetch($junctionTablesKey);
        if ($junctionTables === false) {
            $junctionTables = array_filter($this->getSchema()->getTables(), function (Table $table) use ($ignoreReferencedTables) {
                return $this->isJunctionTable($table, $ignoreReferencedTables);
            });
            $this->cache->save($junctionTablesKey, $junctionTables);
        }

        return $junctionTables;
    }

    /**
     * Returns true if $table is a junction table.
     * I.e:.
     *
     * - it must have exactly 2 foreign keys
     * - it must have only 2 columns (or 3 columns if the third one is an autoincremented primary key).
     *
     * If $ignoreReferencedTables is true, junctions table that are pointed to by a foreign key of another
     * table are ignored.
     *
     * @param Table $table
     * @param bool  $ignoreReferencedTables
     *
     * @return bool
     */
    public function isJunctionTable(Table $table, $ignoreReferencedTables = false)
    {
        $foreignKeys = $table->getForeignKeys();
        if (count($foreignKeys) !== 2) {
            return false;
        }

        $columns = $table->getColumns();
        if (count($columns) < 2 || count($columns) > 3) {
            return false;
        }

        if ($table->hasPrimaryKey()) {
            $pkColumns = $table->getPrimaryKeyColumns();
        } else {
            $pkColumns = [];
        }

        if (count($pkColumns) === 1 && count($columns) === 2) {
            return false;
        }

        if (count($pkColumns) !== 1 && count($columns) === 3) {
            return false;
        }

        $fkColumnNames = [];
        foreach ($foreignKeys as $foreignKey) {
            $fkColumns = $foreignKey->getColumns();
            if (count($fkColumns) !== 1) {
                return false;
            }
            $fkColumnNames[$fkColumns[0]] = true;
        }

        if (count($columns) === 3) {
            // Let's check that the third column (the ID is NOT a foreign key)
            if (isset($fkColumnNames[$pkColumns[0]])) {
                return false;
            }

            // Let's check that the primary key is autoincremented
            if (!$table->getColumn($pkColumns[0])->getAutoincrement()) {
                return false;
            }
        }

        if ($ignoreReferencedTables && $this->isTableReferenced($table)) {
            return false;
        }

        return true;
    }

    /**
     * Returns true if the table $table is referenced by another table.
     *
     * @param Table $table
     *
     * @return bool
     */
    private function isTableReferenced(Table $table)
    {
        $tableName = $table->getName();
        foreach ($this->getSchema()->getTables() as $tableIter) {
            foreach ($tableIter->getForeignKeys() as $fk) {
                if ($fk->getForeignTableName() === $tableName) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Get the shortest path between 2 tables.
     *
     * @param string $fromTable
     * @param string $toTable
     *
     * @return \Doctrine\DBAL\Schema\ForeignKeyConstraint[]
     *
     * @throws SchemaAnalyzerException
     */
    public function getShortestPath($fromTable, $toTable)
    {
        return $this->fromCache($this->cachePrefix.'_shortest_'.$fromTable.'```'.$toTable, function () use ($fromTable, $toTable) {
            return $this->getShortestPathWithoutCache($fromTable, $toTable);
        });
    }

    /**
     * Get the shortest path between 2 tables.
     *
     * @param string $fromTable
     * @param string $toTable
     *
     * @return \Doctrine\DBAL\Schema\ForeignKeyConstraint[]
     *
     * @throws SchemaAnalyzerException
     */
    private function getShortestPathWithoutCache($fromTable, $toTable)
    {
        $this->checkTableExists($fromTable);
        $this->checkTableExists($toTable);

        $graph = $this->buildSchemaGraph();

        try {
            $predecessors = MultiDijkstra::findShortestPaths($graph->getVertex($fromTable), $graph->getVertex($toTable));
            $edges = MultiDijkstra::getCheapestPathFromPredecesArray($graph->getVertex($fromTable), $graph->getVertex($toTable), $predecessors);
        } catch (MultiDijkstraAmbiguityException $e) {
            // If there is more than 1 short path, let's display this.
            $paths = MultiDijkstra::getAllPossiblePathsFromPredecesArray($graph->getVertex($fromTable), $graph->getVertex($toTable), $predecessors);
            $msg = $this->getAmbiguityExceptionMessage($paths, $graph->getVertex($fromTable), $graph->getVertex($toTable));
            throw new ShortestPathAmbiguityException($msg);
        }

        $foreignKeys = [];

        $currentTable = $fromTable;

        foreach ($edges as $edge) {
            /* @var $edge Base */

            if ($fk = $edge->getAttribute('fk')) {
                /* @var $fk ForeignKeyConstraint */
                $foreignKeys[] = $fk;
                if ($fk->getForeignTableName() == $currentTable) {
                    $currentTable = $fk->getLocalTable()->getName();
                } else {
                    $currentTable = $fk->getForeignTableName();
                }
            } elseif ($junctionTable = $edge->getAttribute('junction')) {
                /* @var $junctionTable Table */
                $junctionFks = array_values($junctionTable->getForeignKeys());
                // We need to order the 2 FKs. The first one is the one that has a common point with the current table.
                $fk = $junctionFks[0];
                if ($fk->getForeignTableName() == $currentTable) {
                    $foreignKeys[] = $fk;
                    $foreignKeys[] = $junctionFks[1];
                } else {
                    $foreignKeys[] = $junctionFks[1];
                    $foreignKeys[] = $fk;
                }
            } else {
                // @codeCoverageIgnoreStart
                throw new SchemaAnalyzerException('Unexpected edge. We should have a fk or a junction attribute.');
                // @codeCoverageIgnoreEnd
            }
        }

        return $foreignKeys;
    }

    private function checkTableExists($tableName)
    {
        try {
            $this->getSchema()->getTable($tableName);
        } catch (SchemaException $e) {
            throw SchemaAnalyzerTableNotFoundException::tableNotFound($tableName, $this->schema, $e);
        }
    }

    private function buildSchemaGraph()
    {
        $graph = new Graph();

        // First, let's create all the vertex
        foreach ($this->getSchema()->getTables() as $table) {
            $graph->createVertex($table->getName());
        }

        // Then, let's create all the edges
        foreach ($this->getSchema()->getTables() as $table) {
            $fks = $this->removeDuplicates($table->getForeignKeys());
            foreach ($fks as $fk) {
                // Create an undirected edge, with weight = 1
                $edge = $graph->getVertex($table->getName())->createEdge($graph->getVertex($fk->getForeignTableName()));
                if (isset($this->alteredCosts[$fk->getLocalTable()->getName()][implode(',', $fk->getLocalColumns())])) {
                    $cost = $this->alteredCosts[$fk->getLocalTable()->getName()][implode(',', $fk->getLocalColumns())];
                } elseif ($this->isInheritanceRelationship($fk)) {
                    $cost = self::$WEIGHT_INHERITANCE_FK;
                } else {
                    $cost = self::$WEIGHT_FK;
                }
                if (isset($this->alteredTableCosts[$fk->getLocalTable()->getName()])) {
                    $cost *= $this->alteredTableCosts[$fk->getLocalTable()->getName()];
                }

                $edge->setWeight($cost);
                $edge->getAttributeBag()->setAttribute('fk', $fk);
            }
        }

        // Finally, let's add virtual edges for the junction tables
        foreach ($this->detectJunctionTables() as $junctionTable) {
            $tables = [];
            foreach ($junctionTable->getForeignKeys() as $fk) {
                $tables[] = $fk->getForeignTableName();
            }

            $edge = $graph->getVertex($tables[0])->createEdge($graph->getVertex($tables[1]));
            $cost = self::$WEIGHT_JOINTURE_TABLE;
            if (isset($this->alteredTableCosts[$junctionTable->getName()])) {
                $cost *= $this->alteredTableCosts[$junctionTable->getName()];
            }
            $edge->setWeight($cost);
            $edge->getAttributeBag()->setAttribute('junction', $junctionTable);
        }

        return $graph;
    }

    /**
     * Remove duplicate foreign keys (assumes that all foreign yes are from the same local table).
     *
     * @param ForeignKeyConstraint[] $foreignKeys
     * @return ForeignKeyConstraint[]
     */
    private function removeDuplicates(array $foreignKeys)
    {
        $fks = [];
        foreach ($foreignKeys as $foreignKey) {
            $fks[implode('__`__', $foreignKey->getLocalColumns())] = $foreignKey;
        }

        return array_values($fks);
    }

    /**
     * Returns the schema (from the schema manager or the cache if needed).
     *
     * @return Schema
     */
    private function getSchema()
    {
        if ($this->schema === null) {
            $schemaKey = $this->cachePrefix.'_schema';
            $this->schema = $this->cache->fetch($schemaKey);
            if (empty($this->schema)) {
                $this->schema = $this->schemaManager->createSchema();
                $this->cache->save($schemaKey, $this->schema);
            }
        }

        return $this->schema;
    }

    /**
     * Returns the full exception message when an ambiguity arises.
     *
     * @param Base[][] $paths
     * @param Vertex   $startVertex
     */
    private function getAmbiguityExceptionMessage(array $paths, Vertex $startVertex, Vertex $endVertex)
    {
        $textPaths = [];
        $i = 1;
        foreach ($paths as $path) {
            $textPaths[] = 'Path '.$i.': '.$this->getTextualPath($path, $startVertex);
            ++$i;
        }

        $msg = sprintf("There are many possible shortest paths between table '%s' and table '%s'\n\n",
            $startVertex->getId(), $endVertex->getId());

        $msg .= implode("\n\n", $textPaths);

        return $msg;
    }

    /**
     * Returns the textual representation of the path.
     *
     * @param Base[] $path
     * @param Vertex $startVertex
     */
    private function getTextualPath(array $path, Vertex $startVertex)
    {
        $currentVertex = $startVertex;
        $currentTable = $currentVertex->getId();

        $textPath = $currentTable;

        foreach ($path as $edge) {
            /* @var $fk ForeignKeyConstraint */
            if ($fk = $edge->getAttribute('fk')) {
                if ($fk->getForeignTableName() == $currentTable) {
                    $currentTable = $fk->getLocalTable()->getName();
                    $isForward = false;
                } else {
                    $currentTable = $fk->getForeignTableName();
                    $isForward = true;
                }

                $columns = implode(',', $fk->getLocalColumns());

                $textPath .= ' '.(!$isForward ? '<' : '');
                $textPath .= '--('.$columns.')--';
                $textPath .= ($isForward ? '>' : '').' ';
                $textPath .= $currentTable;
            } elseif ($junctionTable = $edge->getAttribute('junction')) {
                /* @var $junctionTable Table */
                $junctionFks = array_values($junctionTable->getForeignKeys());
                // We need to order the 2 FKs. The first one is the one that has a common point with the current table.
                $fk = $junctionFks[0];
                if ($fk->getForeignTableName() == $currentTable) {
                    $currentTable = $junctionFks[1]->getForeignTableName();
                } else {
                    $currentTable = $fk->getForeignTableName();
                }
                $textPath .= ' <=('.$junctionTable->getName().')=> '.$currentTable;
            } else {
                // @codeCoverageIgnoreStart
                throw new SchemaAnalyzerException('Unexpected edge. We should have a fk or a junction attribute.');
                // @codeCoverageIgnoreEnd
            }
        }

        return $textPath;
    }

    /**
     * Sets the cost of a foreign key.
     *
     * @param string $tableName
     * @param string $columnName
     * @param float  $cost
     *
     * @return $this
     */
    public function setForeignKeyCost($tableName, $columnName, $cost)
    {
        $this->alteredCosts[$tableName][$columnName] = $cost;
    }

    /**
     * Sets the cost modifier of a table.
     *
     * @param string $tableName
     * @param float  $cost
     *
     * @return $this
     */
    public function setTableCostModifier($tableName, $cost)
    {
        $this->alteredTableCosts[$tableName] = $cost;
    }

    /**
     * Sets the cost modifier of all tables at once.
     *
     * @param array<string, float> $tableCosts The key is the table name, the value is the cost modifier.
     */
    public function setTableCostModifiers(array $tableCosts)
    {
        $this->alteredTableCosts = $tableCosts;
    }

    /**
     * Sets the cost of all foreign keys at once.
     *
     * @param array<string, array<string, float>> $fkCosts First key is the table name, second key is the column name, the value is the cost.
     */
    public function setForeignKeyCosts(array $fkCosts)
    {
        $this->alteredCosts = $fkCosts;
    }

    /**
     * Returns true if this foreign key represents an inheritance relationship,
     * i.e. if this foreign key is based on a primary key.
     *
     * @param ForeignKeyConstraint $fk
     *
     * @return true
     */
    private function isInheritanceRelationship(ForeignKeyConstraint $fk)
    {
        if (!$fk->getLocalTable()->hasPrimaryKey()) {
            return false;
        }
        $fkColumnNames = $fk->getLocalColumns();
        $pkColumnNames = $fk->getLocalTable()->getPrimaryKeyColumns();

        sort($fkColumnNames);
        sort($pkColumnNames);

        return $fkColumnNames == $pkColumnNames;
    }

    /**
     * If this table is pointing to a parent table (if its primary key is a foreign key pointing on another table),
     * this function will return the pointed table.
     * This function will return null if there is no parent table.
     *
     * @param string $tableName
     *
     * @return ForeignKeyConstraint|null
     */
    public function getParentRelationship($tableName)
    {
        return $this->fromCache($this->cachePrefix.'_parent_'.$tableName, function () use ($tableName) {
            return $this->getParentRelationshipWithoutCache($tableName);
        });
    }

    /**
     * If this table is pointing to a parent table (if its primary key is a foreign key pointing on another table),
     * this function will return the pointed table.
     * This function will return null if there is no parent table.
     *
     * @param string $tableName
     *
     * @return ForeignKeyConstraint|null
     */
    private function getParentRelationshipWithoutCache($tableName)
    {
        $table = $this->getSchema()->getTable($tableName);
        foreach ($table->getForeignKeys() as $fk) {
            if ($this->isInheritanceRelationship($fk)) {
                return $fk;
            }
        }

        return;
    }

    /**
     * If this table is pointed by children tables (if other child tables have a primary key that is also a
     * foreign key to this table), this function will return the list of child tables.
     * This function will return an empty array if there are no children tables.
     *
     * @param string $tableName
     *
     * @return ForeignKeyConstraint[]
     */
    public function getChildrenRelationships($tableName)
    {
        return $this->fromCache($this->cachePrefix.'_children_'.$tableName, function () use ($tableName) {
            return $this->getChildrenRelationshipsWithoutCache($tableName);
        });
    }

    /**
     * If this table is pointed by children tables (if other child tables have a primary key that is also a
     * foreign key to this table), this function will return the list of child tables.
     * This function will return an empty array if there are no children tables.
     *
     * @param string $tableName
     *
     * @return ForeignKeyConstraint[]
     */
    private function getChildrenRelationshipsWithoutCache($tableName)
    {
        $schema = $this->getSchema();
        $children = [];
        foreach ($schema->getTables() as $table) {
            if ($table->getName() === $tableName) {
                continue;
            }
            $fks = $this->removeDuplicates($table->getForeignKeys());
            foreach ($fks as $fk) {
                if ($fk->getForeignTableName() === $tableName && $this->isInheritanceRelationship($fk)) {
                    $children[] = $fk;
                }
            }
        }

        return $children;
    }

    /**
     * Returns an item from cache or computes it using $closure and puts it in cache.
     *
     * @param string   $key
     * @param callable $closure
     *
     * @return mixed
     */
    private function fromCache($key, callable $closure)
    {
        $item = $this->cache->fetch($key);
        if ($item === false) {
            $item = $closure();
            $this->cache->save($key, $item);
        }

        return $item;
    }
}
