<?php

namespace Mouf\Database\SchemaAnalyzer;

use Doctrine\Common\Cache\ArrayCache;
use Doctrine\DBAL\Schema\Schema;

class SchemaAnalyzerTest extends \PHPUnit_Framework_TestCase
{
    /**
     * Returns a base schema with a role and a right table.
     * No join table.
     */
    private function getBaseSchema()
    {
        $schema = new Schema();
        $role = $schema->createTable('role');
        $role->addColumn('id', 'integer', array('unsigned' => true));
        $role->addColumn('label', 'string', array('length' => 32));

        $right = $schema->createTable('right');
        $right->addColumn('id', 'integer', array('unsigned' => true));
        $right->addColumn('label', 'string', array('length' => 32));

        return $schema;
    }

    private function getCompleteSchemaManager()
    {
        $schema = $this->getBaseSchema();

        $role_right = $schema->createTable('role_right');
        $role_right->addColumn('role_id', 'integer', array('unsigned' => true));
        $role_right->addColumn('right_id', 'integer', array('unsigned' => true));
        $role_right->addForeignKeyConstraint($schema->getTable('role'), array('role_id'), array('id'), array('onUpdate' => 'CASCADE'));
        $role_right->addForeignKeyConstraint($schema->getTable('right'), array('right_id'), array('id'), array('onUpdate' => 'CASCADE'));
        $role_right->setPrimaryKey(['role_id', 'right_id']);

        return new StubSchemaManager($schema);
    }

    public function testJointureTableDetectionWith2Columns()
    {
        $schemaManager = $this->getCompleteSchemaManager();

        $schemaAnalyzer = new SchemaAnalyzer($schemaManager);
        $junctionTables = $schemaAnalyzer->detectJunctionTables();

        $this->assertCount(1, $junctionTables);

        foreach ($junctionTables as $table) {
            $this->assertEquals('role_right', $table->getName());
        }
    }

    public function testJointureTableDetectionWith3ColumnsNoPrimaryKey()
    {
        $schema = $this->getBaseSchema();

        $role_right = $schema->createTable('role_right');
        $role_right->addColumn('role_id', 'integer', array('unsigned' => true));
        $role_right->addColumn('right_id', 'integer', array('unsigned' => true));
        $role_right->addColumn('label', 'string', array('length' => 32));

        $role_right->addForeignKeyConstraint($schema->getTable('role'), array('role_id'), array('id'), array('onUpdate' => 'CASCADE'));
        $role_right->addForeignKeyConstraint($schema->getTable('right'), array('right_id'), array('id'), array('onUpdate' => 'CASCADE'));
        $role_right->setPrimaryKey(['role_id', 'right_id']);

        $schemaAnalyzer = new SchemaAnalyzer(new StubSchemaManager($schema));
        $junctionTables = $schemaAnalyzer->detectJunctionTables();

        $this->assertCount(0, $junctionTables);
    }

    public function testJointureTableDetectionWith3Columns()
    {
        $schema = $this->getBaseSchema();

        $role_right = $schema->createTable('role_right');
        $role_right->addColumn('id', 'integer', array('unsigned' => true, 'autoincrement' => true));
        $role_right->addColumn('role_id', 'integer', array('unsigned' => true));
        $role_right->addColumn('right_id', 'integer', array('unsigned' => true));

        $role_right->addForeignKeyConstraint($schema->getTable('role'), array('role_id'), array('id'), array('onUpdate' => 'CASCADE'));
        $role_right->addForeignKeyConstraint($schema->getTable('right'), array('right_id'), array('id'), array('onUpdate' => 'CASCADE'));
        $role_right->setPrimaryKey(['id']);

        $schemaAnalyzer = new SchemaAnalyzer(new StubSchemaManager($schema));
        $junctionTables = $schemaAnalyzer->detectJunctionTables();

        $this->assertCount(1, $junctionTables);

        foreach ($junctionTables as $table) {
            $this->assertEquals('role_right', $table->getName());
        }
    }

    public function testJointureTableDetectionWith3ColumnsNoAutoincrement()
    {
        $schema = $this->getBaseSchema();

        $role_right = $schema->createTable('role_right');
        $role_right->addColumn('id', 'integer', array('unsigned' => true, 'autoincrement' => false));
        $role_right->addColumn('role_id', 'integer', array('unsigned' => true));
        $role_right->addColumn('right_id', 'integer', array('unsigned' => true));

        $role_right->addForeignKeyConstraint($schema->getTable('role'), array('role_id'), array('id'), array('onUpdate' => 'CASCADE'));
        $role_right->addForeignKeyConstraint($schema->getTable('right'), array('right_id'), array('id'), array('onUpdate' => 'CASCADE'));
        $role_right->setPrimaryKey(['id']);

        $schemaAnalyzer = new SchemaAnalyzer(new StubSchemaManager($schema));
        $junctionTables = $schemaAnalyzer->detectJunctionTables();

        $this->assertCount(0, $junctionTables);
    }

    public function testJointureTableDetectionWith4Columns()
    {
        $schema = $this->getBaseSchema();

        $role_right = $schema->createTable('role_right');
        $role_right->addColumn('id', 'integer', array('unsigned' => true, 'autoincrement' => true));
        $role_right->addColumn('role_id', 'integer', array('unsigned' => true));
        $role_right->addColumn('right_id', 'integer', array('unsigned' => true));
        $role_right->addColumn('label', 'string', array('length' => 32));

        $role_right->addForeignKeyConstraint($schema->getTable('role'), array('role_id'), array('id'), array('onUpdate' => 'CASCADE'));
        $role_right->addForeignKeyConstraint($schema->getTable('right'), array('right_id'), array('id'), array('onUpdate' => 'CASCADE'));
        $role_right->setPrimaryKey(['id']);

        $schemaAnalyzer = new SchemaAnalyzer(new StubSchemaManager($schema));
        $junctionTables = $schemaAnalyzer->detectJunctionTables();

        $this->assertCount(0, $junctionTables);
    }

    public function testJointureTableDetectionWith2ColumnsAndOnePk()
    {
        $schema = $this->getBaseSchema();

        $role_right = $schema->createTable('role_right');
        $role_right->addColumn('role_id', 'integer', array('unsigned' => true));
        $role_right->addColumn('right_id', 'integer', array('unsigned' => true));

        $role_right->addForeignKeyConstraint($schema->getTable('role'), array('role_id'), array('id'), array('onUpdate' => 'CASCADE'));
        $role_right->addForeignKeyConstraint($schema->getTable('right'), array('right_id'), array('id'), array('onUpdate' => 'CASCADE'));
        $role_right->setPrimaryKey(['role_id']);

        $schemaAnalyzer = new SchemaAnalyzer(new StubSchemaManager($schema));
        $junctionTables = $schemaAnalyzer->detectJunctionTables();

        $this->assertCount(0, $junctionTables);
    }

    public function testJointureTableDetectionWith2ColumnsWith2FkOnOneCol()
    {
        $schema = $this->getBaseSchema();
        $schema->getTable('role')->addColumn('right_id', 'integer');

        $role_right = $schema->createTable('role_right');
        $role_right->addColumn('role_id', 'integer', array('unsigned' => true));
        $role_right->addColumn('right_id', 'integer', array('unsigned' => true));

        $role_right->addForeignKeyConstraint($schema->getTable('role'), array('role_id', 'right_id'), array('id', 'right_id'), array('onUpdate' => 'CASCADE'));
        $role_right->addForeignKeyConstraint($schema->getTable('right'), array('right_id'), array('id'), array('onUpdate' => 'CASCADE'));
        $role_right->setPrimaryKey(['role_id', 'right_id']);

        $schemaAnalyzer = new SchemaAnalyzer(new StubSchemaManager($schema));
        $junctionTables = $schemaAnalyzer->detectJunctionTables();

        $this->assertCount(0, $junctionTables);
    }

    public function testJointureTableDetectionWith2ColumnsAndNoPrimaryKey()
    {
        $schema = $this->getBaseSchema();
        $schema->getTable('role')->addColumn('right_id', 'integer');

        $role_right = $schema->createTable('role_right');
        $role_right->addColumn('role_id', 'integer', array('unsigned' => true));
        $role_right->addColumn('right_id', 'integer', array('unsigned' => true));

        $role_right->addForeignKeyConstraint($schema->getTable('role'), array('role_id'), array('id'), array('onUpdate' => 'CASCADE'));
        $role_right->addForeignKeyConstraint($schema->getTable('right'), array('right_id'), array('id'), array('onUpdate' => 'CASCADE'));

        $schemaAnalyzer = new SchemaAnalyzer(new StubSchemaManager($schema));
        $junctionTables = $schemaAnalyzer->detectJunctionTables();

        $this->assertCount(1, $junctionTables);
    }

    public function testJointureTableDetectionWith3ColumnsWithPkIsFk()
    {
        $schema = $this->getBaseSchema();

        $role_right = $schema->createTable('role_right');
        $role_right->addColumn('id', 'integer', array('unsigned' => true, 'autoincrement' => true));
        $role_right->addColumn('role_id', 'integer', array('unsigned' => true));
        $role_right->addColumn('right_id', 'integer', array('unsigned' => true));

        $role_right->addForeignKeyConstraint($schema->getTable('role'), array('id'), array('id'), array('onUpdate' => 'CASCADE'));
        $role_right->addForeignKeyConstraint($schema->getTable('right'), array('right_id'), array('id'), array('onUpdate' => 'CASCADE'));
        $role_right->setPrimaryKey(['id']);

        $schemaAnalyzer = new SchemaAnalyzer(new StubSchemaManager($schema));
        $junctionTables = $schemaAnalyzer->detectJunctionTables();

        $this->assertCount(0, $junctionTables);
    }

    public function testShortestPathInJointure()
    {
        $schema = $this->getBaseSchema();

        $role_right = $schema->createTable('role_right');
        $role_right->addColumn('role_id', 'integer', array('unsigned' => true));
        $role_right->addColumn('right_id', 'integer', array('unsigned' => true));
        $role_right->addForeignKeyConstraint($schema->getTable('role'), array('role_id'), array('id'), array('onUpdate' => 'CASCADE'));
        $role_right->addForeignKeyConstraint($schema->getTable('right'), array('right_id'), array('id'), array('onUpdate' => 'CASCADE'));
        $role_right->setPrimaryKey(['role_id', 'right_id']);

        $schemaAnalyzer = new SchemaAnalyzer(new StubSchemaManager($schema));

        $fks = $schemaAnalyzer->getShortestPath('role', 'right');

        $this->assertCount(2, $fks);
        $this->assertEquals('role_right', $fks[0]->getLocalTable()->getName());
        $this->assertEquals('role', $fks[0]->getForeignTableName());
        $this->assertEquals('role_right', $fks[1]->getLocalTable()->getName());
        $this->assertEquals('right', $fks[1]->getForeignTableName());

        $fks = $schemaAnalyzer->getShortestPath('right', 'role');

        $this->assertCount(2, $fks);
        $this->assertEquals('role_right', $fks[0]->getLocalTable()->getName());
        $this->assertEquals('right', $fks[0]->getForeignTableName());
        $this->assertEquals('role_right', $fks[1]->getLocalTable()->getName());
        $this->assertEquals('role', $fks[1]->getForeignTableName());
    }

    public function testShortestPathInLine()
    {
        $schema = $this->getBaseSchema();

        $role_right = $schema->createTable('role_right');
        $role_right->addColumn('role_id', 'integer', array('unsigned' => true));
        $role_right->addColumn('right_id', 'integer', array('unsigned' => true));
        $role_right->addForeignKeyConstraint($schema->getTable('role'), array('role_id'), array('id'), array('onUpdate' => 'CASCADE'));
        $role_right->addForeignKeyConstraint($schema->getTable('right'), array('right_id'), array('id'), array('onUpdate' => 'CASCADE'));
        $role_right->setPrimaryKey(['role_id', 'right_id']);

        $schemaAnalyzer = new SchemaAnalyzer(new StubSchemaManager($schema));

        $fks = $schemaAnalyzer->getShortestPath('role', 'role_right');

        $this->assertCount(1, $fks);
        $this->assertEquals('role_right', $fks[0]->getLocalTable()->getName());
        $this->assertEquals('role', $fks[0]->getForeignTableName());

        $fks = $schemaAnalyzer->getShortestPath('role_right', 'role');

        $this->assertCount(1, $fks);
        $this->assertEquals('role_right', $fks[0]->getLocalTable()->getName());
        $this->assertEquals('role', $fks[0]->getForeignTableName());
    }

    /**
     * @expectedException \Mouf\Database\SchemaAnalyzer\SchemaAnalyzerException
     */
    public function testWrongConstructor()
    {
        $schema = $this->getBaseSchema();
        new SchemaAnalyzer(new StubSchemaManager($schema), new ArrayCache());
    }

    public function testCache()
    {
        $cache = new ArrayCache();

        $schemaManager = $this->getCompleteSchemaManager();

        $schemaAnalyzer = new SchemaAnalyzer($schemaManager, $cache, 'mykey');
        $schemaAnalyzer->detectJunctionTables();

        $this->assertNotFalse($cache->fetch('mykey_schema'));
        $this->assertNotFalse($cache->fetch('mykey_junctiontables'));
        $r1 = $schemaAnalyzer->getShortestPath('role_right', 'role');
        $r2 = $schemaAnalyzer->getShortestPath('role_right', 'role');
        $this->assertTrue($r1 === $r2);

        $r1 = $this->assertNotFalse($cache->fetch('mykey_shortest_role_right```role'));
        $r2 = $this->assertNotFalse($cache->fetch('mykey_shortest_role_right```role'));
        $this->assertTrue($r1 === $r2);
    }

    public function testAmbiguityException()
    {
        $schema = $this->getBaseSchema();

        $role_right = $schema->createTable('role_right');
        $role_right->addColumn('role_id', 'integer', array('unsigned' => true));
        $role_right->addColumn('right_id', 'integer', array('unsigned' => true));
        $role_right->addForeignKeyConstraint($schema->getTable('role'), array('role_id'), array('id'), array('onUpdate' => 'CASCADE'));
        $role_right->addForeignKeyConstraint($schema->getTable('right'), array('right_id'), array('id'), array('onUpdate' => 'CASCADE'));
        $role_right->setPrimaryKey(['role_id', 'right_id']);

        $role_right2 = $schema->createTable('role_right2');
        $role_right2->addColumn('role_id', 'integer', array('unsigned' => true));
        $role_right2->addColumn('right_id', 'integer', array('unsigned' => true));
        $role_right2->addForeignKeyConstraint($schema->getTable('role'), array('role_id'), array('id'), array('onUpdate' => 'CASCADE'));
        $role_right2->addForeignKeyConstraint($schema->getTable('right'), array('right_id'), array('id'), array('onUpdate' => 'CASCADE'));
        $role_right2->setPrimaryKey(['role_id', 'right_id']);

        $schemaAnalyzer = new SchemaAnalyzer(new StubSchemaManager($schema));

        $exceptionTriggered = false;
        try {
            $schemaAnalyzer->getShortestPath('role', 'right');
        } catch (ShortestPathAmbiguityException $e) {
            $this->assertContains('role <=(role_right)=> right', $e->getMessage());
            $this->assertContains('role <=(role_right2)=> right', $e->getMessage());
            $exceptionTriggered = true;
        }
        $this->assertTrue($exceptionTriggered);

        $exceptionTriggered = false;
        try {
            $schemaAnalyzer->getShortestPath('right', 'role');
        } catch (ShortestPathAmbiguityException $e) {
            $this->assertContains('right <=(role_right)=> role', $e->getMessage());
            $this->assertContains('right <=(role_right2)=> role', $e->getMessage());
            $exceptionTriggered = true;
        }
        $this->assertTrue($exceptionTriggered);
    }

    public function testAmbiguityExceptionWithNoJointure()
    {
        $schema = $this->getBaseSchema();
        $right = $schema->getTable('right');
        $right->addColumn('role_id', 'integer', array('unsigned' => true));
        $right->addForeignKeyConstraint($schema->getTable('role'), array('role_id'), array('id'), array('onUpdate' => 'CASCADE'));

        $right->addColumn('role_id2', 'integer', array('unsigned' => true));
        $right->addForeignKeyConstraint($schema->getTable('role'), array('role_id2'), array('id'), array('onUpdate' => 'CASCADE'));

        $schemaAnalyzer = new SchemaAnalyzer(new StubSchemaManager($schema));

        $exceptionTriggered = false;
        try {
            $schemaAnalyzer->getShortestPath('role', 'right');
        } catch (ShortestPathAmbiguityException $e) {
            $this->assertContains('role <--(role_id)-- right', $e->getMessage());
            $this->assertContains('role <--(role_id2)-- right', $e->getMessage());
            $exceptionTriggered = true;
        }
        $this->assertTrue($exceptionTriggered);

        $exceptionTriggered = false;
        try {
            $schemaAnalyzer->getShortestPath('right', 'role');
        } catch (ShortestPathAmbiguityException $e) {
            $this->assertContains('right --(role_id)--> role', $e->getMessage());
            $this->assertContains('right --(role_id2)--> role', $e->getMessage());
            $exceptionTriggered = true;
        }
        $this->assertTrue($exceptionTriggered);
    }

    public function testAmbiguityExceptionWithNoJointureAndModifiedWeight()
    {
        $schema = $this->getBaseSchema();
        $right = $schema->getTable('right');
        $right->addColumn('role_id', 'integer', array('unsigned' => true));
        $right->addForeignKeyConstraint($schema->getTable('role'), array('role_id'), array('id'), array('onUpdate' => 'CASCADE'));

        $right->addColumn('role_id2', 'integer', array('unsigned' => true));
        $right->addForeignKeyConstraint($schema->getTable('role'), array('role_id2'), array('id'), array('onUpdate' => 'CASCADE'));

        $schemaAnalyzer = new SchemaAnalyzer(new StubSchemaManager($schema));
        $schemaAnalyzer->setForeignKeyCost('right', 'role_id', SchemaAnalyzer::WEIGHT_IMPORTANT);

        $fks = $schemaAnalyzer->getShortestPath('role', 'right');

        $this->assertCount(1, $fks);
        $this->assertEquals('right', $fks[0]->getLocalTable()->getName());
        $this->assertEquals('role', $fks[0]->getForeignTableName());
    }

    public function testAmbiguityExceptionWithJointureAndModifiedWeight()
    {
        $schema = $this->getBaseSchema();

        $role_right = $schema->createTable('role_right');
        $role_right->addColumn('role_id', 'integer', array('unsigned' => true));
        $role_right->addColumn('right_id', 'integer', array('unsigned' => true));
        $role_right->addForeignKeyConstraint($schema->getTable('role'), array('role_id'), array('id'), array('onUpdate' => 'CASCADE'));
        $role_right->addForeignKeyConstraint($schema->getTable('right'), array('right_id'), array('id'), array('onUpdate' => 'CASCADE'));
        $role_right->setPrimaryKey(['role_id', 'right_id']);

        $role_right2 = $schema->createTable('role_right2');
        $role_right2->addColumn('role_id', 'integer', array('unsigned' => true));
        $role_right2->addColumn('right_id', 'integer', array('unsigned' => true));
        $role_right2->addForeignKeyConstraint($schema->getTable('role'), array('role_id'), array('id'), array('onUpdate' => 'CASCADE'));
        $role_right2->addForeignKeyConstraint($schema->getTable('right'), array('right_id'), array('id'), array('onUpdate' => 'CASCADE'));
        $role_right2->setPrimaryKey(['role_id', 'right_id']);

        $schemaAnalyzer = new SchemaAnalyzer(new StubSchemaManager($schema));
        $schemaAnalyzer->setTableCostModifier('role_right2', SchemaAnalyzer::WEIGHT_IRRELEVANT);

        $fks = $schemaAnalyzer->getShortestPath('role', 'right');

        $this->assertCount(2, $fks);
        $this->assertEquals('role_right', $fks[0]->getLocalTable()->getName());
        $this->assertEquals('role', $fks[0]->getForeignTableName());
        $this->assertEquals('role_right', $fks[1]->getLocalTable()->getName());
        $this->assertEquals('right', $fks[1]->getForeignTableName());
    }

    public function testAmbiguityExceptionWithJointureAndModifiedWeight2()
    {
        $schema = $this->getBaseSchema();

        $role_right = $schema->createTable('role_right');
        $role_right->addColumn('role_id', 'integer', array('unsigned' => true));
        $role_right->addColumn('right_id', 'integer', array('unsigned' => true));
        $role_right->addForeignKeyConstraint($schema->getTable('role'), array('role_id'), array('id'), array('onUpdate' => 'CASCADE'));
        $role_right->addForeignKeyConstraint($schema->getTable('right'), array('right_id'), array('id'), array('onUpdate' => 'CASCADE'));
        $role_right->setPrimaryKey(['role_id', 'right_id']);

        $role_right2 = $schema->createTable('role_right2');
        $role_right2->addColumn('role_id', 'integer', array('unsigned' => true));
        $role_right2->addColumn('right_id', 'integer', array('unsigned' => true));
        $role_right2->addForeignKeyConstraint($schema->getTable('role'), array('role_id'), array('id'), array('onUpdate' => 'CASCADE'));
        $role_right2->addForeignKeyConstraint($schema->getTable('right'), array('right_id'), array('id'), array('onUpdate' => 'CASCADE'));
        $role_right2->setPrimaryKey(['role_id', 'right_id']);

        $schemaAnalyzer = new SchemaAnalyzer(new StubSchemaManager($schema));
        $schemaAnalyzer->setTableCostModifiers(['role_right2' => SchemaAnalyzer::WEIGHT_IRRELEVANT]);
        $schemaAnalyzer->setForeignKeyCosts([]);

        $fks = $schemaAnalyzer->getShortestPath('role', 'right');

        $this->assertCount(2, $fks);
        $this->assertEquals('role_right', $fks[0]->getLocalTable()->getName());
        $this->assertEquals('role', $fks[0]->getForeignTableName());
        $this->assertEquals('role_right', $fks[1]->getLocalTable()->getName());
        $this->assertEquals('right', $fks[1]->getForeignTableName());
    }

    public function testInheritanceRelationship()
    {
        $schema = new Schema();
        $contact = $schema->createTable('contact');
        $contact->addColumn('id', 'integer', array('unsigned' => true));
        $contact->addColumn('name', 'string', array('length' => 32));
        $contact->setPrimaryKey(['id']);

        $user = $schema->createTable('user');
        $user->addColumn('id', 'integer', array('unsigned' => true));
        $user->addColumn('contact_id', 'integer', array('unsigned' => true));
        $user->addColumn('login', 'string', array('length' => 32));
        $user->setPrimaryKey(['id']);

        $user->addForeignKeyConstraint($contact, array('id'), array('id'), array('onUpdate' => 'CASCADE'));
        $user->addForeignKeyConstraint($contact, array('contact_id'), array('id'), array('onUpdate' => 'CASCADE'));

        $schemaAnalyzer = new SchemaAnalyzer(new StubSchemaManager($schema));

        // No ambiguity exception should be thrown because we go through inheritance relationship first.
        $fks = $schemaAnalyzer->getShortestPath('user', 'contact');

        $this->assertCount(1, $fks);
        $this->assertEquals('id', $fks[0]->getLocalColumns()[0]);

        $this->assertEquals('contact', $schemaAnalyzer->getParentRelationship('user')->getForeignTableName());
        $this->assertNull($schemaAnalyzer->getParentRelationship('contact'));

        $this->assertEquals('user', $schemaAnalyzer->getChildrenRelationships('contact')[0]->getLocalTableName());
        $this->assertEquals([], $schemaAnalyzer->getChildrenRelationships('user'));
    }
}
