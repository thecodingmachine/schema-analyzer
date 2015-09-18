<?php
namespace Mouf\Database\SchemaAnalyzer;

use Doctrine\DBAL\Schema\Schema;

class SchemaAnalyzerTest extends \PHPUnit_Framework_TestCase
{
    /**
     * Returns a base schema with a role and a right table.
     * No join table.
     */
    private function getBaseSchema() {
        $schema = new Schema();
        $role = $schema->createTable("role");
        $role->addColumn("id", "integer", array("unsigned" => true));
        $role->addColumn("label", "string", array("length" => 32));

        $right = $schema->createTable("right");
        $right->addColumn("id", "integer", array("unsigned" => true));
        $right->addColumn("label", "string", array("length" => 32));

        return $schema;
    }

    public function testJointureTableDetectionWith2Columns() {
        $schema = $this->getBaseSchema();

        $role_right = $schema->createTable("role_right");
        $role_right->addColumn("role_id", "integer", array("unsigned" => true));
        $role_right->addColumn("right_id", "integer", array("unsigned" => true));
        $role_right->addForeignKeyConstraint($schema->getTable('role'), array("role_id"), array("id"), array("onUpdate" => "CASCADE"));
        $role_right->addForeignKeyConstraint($schema->getTable('right'), array("right_id"), array("id"), array("onUpdate" => "CASCADE"));
        $role_right->setPrimaryKey(["role_id", "right_id"]);

        $schemaAnalyzer = new SchemaAnalyzer($schema);
        $junctionTables = $schemaAnalyzer->detectJunctionTables();

        $this->assertCount(1, $junctionTables);

        foreach ($junctionTables as $table) {
            $this->assertEquals("role_right", $table->getName());
        }
    }

    public function testJointureTableDetectionWith3ColumnsNoPrimaryKey() {
        $schema = $this->getBaseSchema();

        $role_right = $schema->createTable("role_right");
        $role_right->addColumn("role_id", "integer", array("unsigned" => true));
        $role_right->addColumn("right_id", "integer", array("unsigned" => true));
        $role_right->addColumn("label", "string", array("length" => 32));

        $role_right->addForeignKeyConstraint($schema->getTable('role'), array("role_id"), array("id"), array("onUpdate" => "CASCADE"));
        $role_right->addForeignKeyConstraint($schema->getTable('right'), array("right_id"), array("id"), array("onUpdate" => "CASCADE"));
        $role_right->setPrimaryKey(["role_id", "right_id"]);

        $schemaAnalyzer = new SchemaAnalyzer($schema);
        $junctionTables = $schemaAnalyzer->detectJunctionTables();

        $this->assertCount(0, $junctionTables);
    }

    public function testJointureTableDetectionWith3Columns() {
        $schema = $this->getBaseSchema();

        $role_right = $schema->createTable("role_right");
        $role_right->addColumn("id", "integer", array("unsigned" => true, "autoincrement" => true));
        $role_right->addColumn("role_id", "integer", array("unsigned" => true));
        $role_right->addColumn("right_id", "integer", array("unsigned" => true));

        $role_right->addForeignKeyConstraint($schema->getTable('role'), array("role_id"), array("id"), array("onUpdate" => "CASCADE"));
        $role_right->addForeignKeyConstraint($schema->getTable('right'), array("right_id"), array("id"), array("onUpdate" => "CASCADE"));
        $role_right->setPrimaryKey(["id"]);

        $schemaAnalyzer = new SchemaAnalyzer($schema);
        $junctionTables = $schemaAnalyzer->detectJunctionTables();

        $this->assertCount(1, $junctionTables);

        foreach ($junctionTables as $table) {
            $this->assertEquals("role_right", $table->getName());
        }
    }

    public function testJointureTableDetectionWith3ColumnsNoAutoincrement() {
        $schema = $this->getBaseSchema();

        $role_right = $schema->createTable("role_right");
        $role_right->addColumn("id", "integer", array("unsigned" => true, "autoincrement" => false));
        $role_right->addColumn("role_id", "integer", array("unsigned" => true));
        $role_right->addColumn("right_id", "integer", array("unsigned" => true));

        $role_right->addForeignKeyConstraint($schema->getTable('role'), array("role_id"), array("id"), array("onUpdate" => "CASCADE"));
        $role_right->addForeignKeyConstraint($schema->getTable('right'), array("right_id"), array("id"), array("onUpdate" => "CASCADE"));
        $role_right->setPrimaryKey(["id"]);

        $schemaAnalyzer = new SchemaAnalyzer($schema);
        $junctionTables = $schemaAnalyzer->detectJunctionTables();

        $this->assertCount(0, $junctionTables);
    }

    public function testJointureTableDetectionWith4Columns() {
        $schema = $this->getBaseSchema();

        $role_right = $schema->createTable("role_right");
        $role_right->addColumn("id", "integer", array("unsigned" => true, "autoincrement" => true));
        $role_right->addColumn("role_id", "integer", array("unsigned" => true));
        $role_right->addColumn("right_id", "integer", array("unsigned" => true));
        $role_right->addColumn("label", "string", array("length" => 32));

        $role_right->addForeignKeyConstraint($schema->getTable('role'), array("role_id"), array("id"), array("onUpdate" => "CASCADE"));
        $role_right->addForeignKeyConstraint($schema->getTable('right'), array("right_id"), array("id"), array("onUpdate" => "CASCADE"));
        $role_right->setPrimaryKey(["id"]);

        $schemaAnalyzer = new SchemaAnalyzer($schema);
        $junctionTables = $schemaAnalyzer->detectJunctionTables();

        $this->assertCount(0, $junctionTables);
    }

    public function testJointureTableDetectionWith2ColumnsAndOnePk() {
        $schema = $this->getBaseSchema();

        $role_right = $schema->createTable("role_right");
        $role_right->addColumn("role_id", "integer", array("unsigned" => true));
        $role_right->addColumn("right_id", "integer", array("unsigned" => true));

        $role_right->addForeignKeyConstraint($schema->getTable('role'), array("role_id"), array("id"), array("onUpdate" => "CASCADE"));
        $role_right->addForeignKeyConstraint($schema->getTable('right'), array("right_id"), array("id"), array("onUpdate" => "CASCADE"));
        $role_right->setPrimaryKey(["role_id"]);

        $schemaAnalyzer = new SchemaAnalyzer($schema);
        $junctionTables = $schemaAnalyzer->detectJunctionTables();

        $this->assertCount(0, $junctionTables);
    }

    public function testJointureTableDetectionWith2ColumnsWith2FkOnOneCol() {
        $schema = $this->getBaseSchema();
        $schema->getTable('role')->addColumn('right_id', 'integer');

        $role_right = $schema->createTable("role_right");
        $role_right->addColumn("role_id", "integer", array("unsigned" => true));
        $role_right->addColumn("right_id", "integer", array("unsigned" => true));

        $role_right->addForeignKeyConstraint($schema->getTable('role'), array("role_id", "right_id"), array("id", "right_id"), array("onUpdate" => "CASCADE"));
        $role_right->addForeignKeyConstraint($schema->getTable('right'), array("right_id"), array("id"), array("onUpdate" => "CASCADE"));
        $role_right->setPrimaryKey(["role_id", "right_id"]);

        $schemaAnalyzer = new SchemaAnalyzer($schema);
        $junctionTables = $schemaAnalyzer->detectJunctionTables();

        $this->assertCount(0, $junctionTables);
    }

    public function testJointureTableDetectionWith3ColumnsWithPkIsFk() {
        $schema = $this->getBaseSchema();

        $role_right = $schema->createTable("role_right");
        $role_right->addColumn("id", "integer", array("unsigned" => true, "autoincrement" => true));
        $role_right->addColumn("role_id", "integer", array("unsigned" => true));
        $role_right->addColumn("right_id", "integer", array("unsigned" => true));

        $role_right->addForeignKeyConstraint($schema->getTable('role'), array("id"), array("id"), array("onUpdate" => "CASCADE"));
        $role_right->addForeignKeyConstraint($schema->getTable('right'), array("right_id"), array("id"), array("onUpdate" => "CASCADE"));
        $role_right->setPrimaryKey(["id"]);

        $schemaAnalyzer = new SchemaAnalyzer($schema);
        $junctionTables = $schemaAnalyzer->detectJunctionTables();

        $this->assertCount(0, $junctionTables);
    }

    public function testShortestPathInJointure() {
        $schema = $this->getBaseSchema();

        $role_right = $schema->createTable("role_right");
        $role_right->addColumn("role_id", "integer", array("unsigned" => true));
        $role_right->addColumn("right_id", "integer", array("unsigned" => true));
        $role_right->addForeignKeyConstraint($schema->getTable('role'), array("role_id"), array("id"), array("onUpdate" => "CASCADE"));
        $role_right->addForeignKeyConstraint($schema->getTable('right'), array("right_id"), array("id"), array("onUpdate" => "CASCADE"));
        $role_right->setPrimaryKey(["role_id", "right_id"]);

        $schemaAnalyzer = new SchemaAnalyzer($schema);

        $fks = $schemaAnalyzer->getShortestPath("role", "right");

        $this->assertCount(2, $fks);
        $this->assertEquals("role_right", $fks[0]->getLocalTable()->getName());
        $this->assertEquals("role", $fks[0]->getForeignTableName());
        $this->assertEquals("role_right", $fks[1]->getLocalTable()->getName());
        $this->assertEquals("right", $fks[1]->getForeignTableName());

        $fks = $schemaAnalyzer->getShortestPath("right", "role");

        $this->assertCount(2, $fks);
        $this->assertEquals("role_right", $fks[0]->getLocalTable()->getName());
        $this->assertEquals("right", $fks[0]->getForeignTableName());
        $this->assertEquals("role_right", $fks[1]->getLocalTable()->getName());
        $this->assertEquals("role", $fks[1]->getForeignTableName());
    }

    public function testShortestPathInLine() {
        $schema = $this->getBaseSchema();

        $role_right = $schema->createTable("role_right");
        $role_right->addColumn("role_id", "integer", array("unsigned" => true));
        $role_right->addColumn("right_id", "integer", array("unsigned" => true));
        $role_right->addForeignKeyConstraint($schema->getTable('role'), array("role_id"), array("id"), array("onUpdate" => "CASCADE"));
        $role_right->addForeignKeyConstraint($schema->getTable('right'), array("right_id"), array("id"), array("onUpdate" => "CASCADE"));
        $role_right->setPrimaryKey(["role_id", "right_id"]);

        $schemaAnalyzer = new SchemaAnalyzer($schema);

        $fks = $schemaAnalyzer->getShortestPath("role", "role_right");

        $this->assertCount(1, $fks);
        $this->assertEquals("role_right", $fks[0]->getLocalTable()->getName());
        $this->assertEquals("role", $fks[0]->getForeignTableName());

        $fks = $schemaAnalyzer->getShortestPath("role_right", "role");

        $this->assertCount(1, $fks);
        $this->assertEquals("role_right", $fks[0]->getLocalTable()->getName());
        $this->assertEquals("role", $fks[0]->getForeignTableName());
    }
}

