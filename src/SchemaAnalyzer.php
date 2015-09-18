<?php
namespace Mouf\Database\SchemaAnalyzer;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Schema\Table;
use Fhaculty\Graph\Graph;

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
     * @var Schema
     */
    private $schema;

    /**
     * @param Schema $schema
     */
    public function __construct(Schema $schema)
    {
        $this->schema = $schema;
    }

    /**
     * Detect all junctions tables in the schema.
     * A table is a junction table if:
     * - it has exactly 2 foreign keys
     * - it has only 2 columns (or 3 columns if the third one is an autoincremented primary key)
     *
     *
     * @return Table[]
     */
    public function detectJunctionTables() {
        return array_filter($this->schema->getTables(), [$this, "isJunctionTable"]);
    }

    /**
     * Returns true if $table is a junction table.
     * I.e:
     * - it must have exactly 2 foreign keys
     * - it must have only 2 columns (or 3 columns if the third one is an autoincremented primary key)
     *
     * @param Table $table
     * @return bool
     */
    private function isJunctionTable(Table $table) {
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

        // Let's check that the third column (the ID is NOT a foreign key)
        if (count($columns) == 3 && isset($fkColumnNames[$pkColumns[0]])) {
            return false;
        }

        return true;
    }

    /**
     * Get the shortest path between 2 tables.
     *
     * @param $fromTable
     * @param $toTable
     */
    public function getShortestPath($fromTable, $toTable) {
        $graph = $this->buildSchemaGraph();
        // TODO
    }

    public function buildSchemaGraph() {
        $graph = new Graph();

        // First, let's create all the vertex
        foreach ($this->schema->getTables() as $table) {
            $graph->createVertex($table->getName());
        }

        // Then, let's create all the edges
        foreach ($this->schema->getTables() as $table) {
            foreach ($table->getForeignKeys() as $fk) {
                // Create an undirected edge, with weight = 1
                $edge = $graph->getVertex($table->getName())->createEdge($graph->getVertex($fk->getForeignTableName()));
                $edge->setWeight(self::$WEIGHT_FK);
                $edge->getAttributeBag()->setAttribute("fk", $fk);
            }
        }

        // Finally, let's add virtual edges for the junction tables
        foreach ($this->detectJunctionTables() as $junctionTable) {
            $tables = [];
            foreach ($junctionTable->getForeignKeys() as $fk) {
                $tables[] = $fk->getForeignTableName();
            }

            $edge = $graph->getVertex($tables[0]->getName())->createEdge($graph->getVertex($tables[1]->getName()));
            $edge->setWeight(self::$WEIGHT_JOINTURE_TABLE);
            $edge->getAttributeBag()->setAttribute("junction", $junctionTable);
        }

        return $graph;
    }
}
