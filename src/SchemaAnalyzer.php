<?php

namespace Mouf\Database\SchemaAnalyzer;

use Doctrine\Common\Cache\Cache;
use Doctrine\Common\Cache\VoidCache;
use Doctrine\DBAL\Schema\AbstractSchemaManager;
use Doctrine\DBAL\Schema\ForeignKeyConstraint;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Schema\Table;
use Fhaculty\Graph\Edge\Base;
use Fhaculty\Graph\Graph;
use Graphp\Algorithms\ShortestPath\Dijkstra;

/**
 * This class can analyze a database model.
 * In this class you will find:
 *
 * - Functions to automatically detect **junction tables**
 * - Functions to compute the shortest path between 2 tables based on the relationships stored in the schema.
 */
class SchemaAnalyzer
{
    private static $WEIGHT_FK = 1;
    private static $WEIGHT_JOINTURE_TABLE = 1.5;

    /**
     * @var AbstractSchemaManager
     */
    private $schemaManager;

    /**
     * @var Schema
     */
    private $schema;

    /**
     * @var Cache|null
     */
    private $cache;

    /**
     * @var string
     */
    private $cachePrefix;

    /**
     * @param AbstractSchemaManager $schemaManager
     * @param Cache|null $cache The Doctrine cache service to use to cache results (optional)
     * @param string|null $schemaCacheKey The unique identifier for the schema manager. Compulsory if cache is set.
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
     * A table is a junction table if:
     * - it has exactly 2 foreign keys
     * - it has only 2 columns (or 3 columns if the third one is an autoincremented primary key).
     *
     *
     * @return Table[]
     */
    public function detectJunctionTables()
    {
        $junctionTablesKey = $this->cachePrefix."_junctiontables";
        $junctionTables = $this->cache->fetch($junctionTablesKey);
        if ($junctionTables === false) {
            $junctionTables = array_filter($this->getSchema()->getTables(), [$this, 'isJunctionTable']);
            $this->cache->save($junctionTablesKey, $junctionTables);
        }
        return $junctionTables;
    }

    /**
     * Returns true if $table is a junction table.
     * I.e:
     * - it must have exactly 2 foreign keys
     * - it must have only 2 columns (or 3 columns if the third one is an autoincremented primary key).
     *
     * @param Table $table
     *
     * @return bool
     */
    private function isJunctionTable(Table $table)
    {
        $foreignKeys = $table->getForeignKeys();
        if (count($foreignKeys) != 2) {
            return false;
        }

        $columns = $table->getColumns();
        if (count($columns) < 2 || count($columns) > 3) {
            return false;
        }

        $pkColumns = $table->getPrimaryKeyColumns();

        if (count($pkColumns) == 1 && count($columns) == 2) {
            return false;
        }

        if (count($pkColumns) != 1 && count($columns) == 3) {
            return false;
        }

        $fkColumnNames = [];
        foreach ($foreignKeys as $foreignKey) {
            $fkColumns = $foreignKey->getColumns();
            if (count($fkColumns) != 1) {
                return false;
            }
            $fkColumnNames[$fkColumns[0]] = true;
        }

        if (count($columns) == 3) {
            // Let's check that the third column (the ID is NOT a foreign key)
            if (isset($fkColumnNames[$pkColumns[0]])) {
                return false;
            }

            // Let's check that the primary key is autoincremented
            if (!$table->getColumn($pkColumns[0])->getAutoincrement()) {
                return false;
            }
        }

        return true;
    }

    /**
     * Get the shortest path between 2 tables.
     *
     * @param string $fromTable
     * @param string $toTable
     * @return \Doctrine\DBAL\Schema\ForeignKeyConstraint[]
     * @throws SchemaAnalyzerException
     */
    public function getShortestPath($fromTable, $toTable)
    {
        $cacheKey = $this->cachePrefix."_shortest_".$fromTable."```".$toTable;
        $path = $this->cache->fetch($cacheKey);
        if ($path === false) {
            $path = $this->getShortestPathWithoutCache($fromTable, $toTable);
            $this->cache->save($cacheKey, $path);
        }
        return $path;
    }

    /**
     * Get the shortest path between 2 tables.
     *
     * @param string $fromTable
     * @param string $toTable
     * @return \Doctrine\DBAL\Schema\ForeignKeyConstraint[]
     * @throws SchemaAnalyzerException
     */
    private function getShortestPathWithoutCache($fromTable, $toTable)
    {
        $graph = $this->buildSchemaGraph();

        $dijkstra = new Dijkstra($graph->getVertex($fromTable));
        $walk = $dijkstra->getWalkTo($graph->getVertex($toTable));

        $foreignKeys = [];

        $currentTable = $fromTable;

        foreach ($walk->getEdges() as $edge) {
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

    private function buildSchemaGraph()
    {
        $graph = new Graph();

        // First, let's create all the vertex
        foreach ($this->getSchema()->getTables() as $table) {
            $graph->createVertex($table->getName());
        }

        // Then, let's create all the edges
        foreach ($this->getSchema()->getTables() as $table) {
            foreach ($table->getForeignKeys() as $fk) {
                // Create an undirected edge, with weight = 1
                $edge = $graph->getVertex($table->getName())->createEdge($graph->getVertex($fk->getForeignTableName()));
                $edge->setWeight(self::$WEIGHT_FK);
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
            $edge->setWeight(self::$WEIGHT_JOINTURE_TABLE);
            $edge->getAttributeBag()->setAttribute('junction', $junctionTable);
        }

        return $graph;
    }

    /**
     * Returns the schema (from the schema manager or the cache if needed)
     * @return Schema
     */
    private function getSchema() {
        if ($this->schema === null) {
            $schemaKey = $this->cachePrefix."_schema";
            $this->schema = $this->cache->fetch($schemaKey);
            if (empty($this->schema)) {
                $this->schema = $this->schemaManager->createSchema();
                $this->cache->save($schemaKey, $this->schema);
            }
        }
        return $this->schema;
    }

}
