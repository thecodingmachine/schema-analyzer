[![Latest Stable Version](https://poser.pugx.org/mouf/schema-analyzer/v/stable)](https://packagist.org/packages/mouf/schema-analyzer)
[![Latest Unstable Version](https://poser.pugx.org/mouf/schema-analyzer/v/unstable)](https://packagist.org/packages/mouf/schema-analyzer)
[![License](https://poser.pugx.org/mouf/schema-analyzer/license)](https://packagist.org/packages/mouf/schema-analyzer)
[![Scrutinizer Code Quality](https://scrutinizer-ci.com/g/thecodingmachine/schema-analyzer/badges/quality-score.png?b=1.0)](https://scrutinizer-ci.com/g/thecodingmachine/schema-analyzer/?branch=1.0)
[![Build Status](https://travis-ci.org/thecodingmachine/schema-analyzer.svg?branch=1.0)](https://travis-ci.org/thecodingmachine/schema-analyzer)
[![Coverage Status](https://coveralls.io/repos/thecodingmachine/schema-analyzer/badge.svg?branch=1.0&service=github)](https://coveralls.io/github/thecodingmachine/schema-analyzer?branch=1.0)

# Schema analyzer for DBAL

This package offer utility functions to analyze database schemas. It is built on top of Doctrine DBAL.

In this package, you will find:

- Functions to automatically detect **junction tables**
- Functions to compute the shortest path between 2 tables based on the relationships stored in the schema.

## Installation

You can install this package through Composer:

```json
{
    "require": {
        "mouf/schema-analyzer": "~1.0"
    }
}
```

The packages adheres to the [SemVer](http://semver.org/) specification, and there will be full backward compatibility
between minor versions.

## Detecting junction tables

The starting point is always a DBAL Schema. Pass the schema manager to SchemaAnalyzer, and then, simply call the functions.

```php
// $conn is the DBAL connection.
$schemaAnalyzer = new SchemaAnalyzer($conn->getSchemaManager());

// Let's detect all junctions tables
$tables = $schemaAnalyzer->detectJunctionTables();
// This will return an array of Doctrine\DBAL\Schema\Table objects
```

A **junction table** is a table:

- that has **exactly 2 foreign keys**
- that has **only 2 columns** (or **3 columns** if the one of those is an *autoincremented primary key*).

## Detecting inheritance relationship between tables

### About inheritance relationships

If a table "user" has a primary key that is also a foreign key pointing on table "contact", then table "user" is 
considered to be a child of table "contact". This is because you cannot create a row in "user" without having a row 
with the same ID in "contact".

Therefore, a "user" ID has to match a "contact", but a "contact" has not necessarily a "user" associated. 

### Detecting inheritance relationships

You can use `SchemaAnalyzer` to detect parent / child relationships.

```php
$parent = $schemaAnalyzer->getParentTable("user");
// This will return the "contact" table (as a string)

$children = $schemaAnalyzer->getChildrenTables("contact");
// This will return an array of tables whose parent is contact: ["user"]
```

## Computing the shortest path between 2 tables

Following foreign keys, the `getShortestPath` function will try to find the shortest path between 2 tables.
It will return the list of foreign keys it used to link the 2 tables.

Internals:

- Each foreign key has a *cost* of 1
- Junction tables have a *cost* of 1.5, instead of 2 (one for each foreign key)
- Foreign keys representing an inheritance relationship (i.e. foreign keys binding the primary keys of 2 tables)
  have a *cost* of 0.1

```php
// $conn is the DBAL connection.
$schemaAnalyzer = new SchemaAnalyzer($conn->getSchemaManager());

// Let's detect the shortest path between 2 tables:
$fks = $schemaAnalyzer->getShortestPath("users", "rights");
// This will return an array of Doctrine\DBAL\Schema\ForeignKeyConstraint objects
```

<div class="alert alert-info"><strong>Heads up!</strong> The shortest path is based on the <em>cost</em> of the 
foreign keys. It is perfectly possible to have several shortest paths (if several paths have the same total cost). 
If there are several shortest paths, rather than choosing one path amongst the others, SchemaAnalyzer will throw
a <code>ShortestPathAmbiguityException</code>. The exception message details all the possible shortest
paths.</div>

## Caching results

Analyzing the full data model and looking for shortest paths can take a long time. For anything that should run 
in a production environment, it is recommended to cache the result. `SchemaAnalyzer` can be passed a Doctrine cache,
along a cache prefix. The cache prefix is a string that will be used to prefix all cache keys. It is useful to 
avoid cache collisions between several databases.

Usage:

```php
// $conn is the DBAL connection.
// Let's use the ApcCache (or any other Doctrine cache...)
$cache = new ApcCache();
$schemaAnalyzer = new SchemaAnalyzer($conn->getSchemaManager(), $cache, "my_prefix");
```

## Changing the cost of the foreign keys to alter the shortest path

If you are facing an ambiguity exception or if the shortest path simply does not suit you, you can alter the 
cost of the foreign keys.

```php
$schemaAnalyzer->setForeignKeyCost($tableName, $columnName, $cost);
```

The `$cost` can be any number. Remember that the default cost for a foreign key is **1**.

SchemaAnalyzer comes with a set of default constants to help you work with costs:

- `SchemaAnalyzer::WEIGHT_IMPORTANT` (0.75) for foreign keys that should be followed in priority 
- `SchemaAnalyzer::WEIGHT_IRRELEVANT` (2) for foreign keys that should be generally avoided 
- `SchemaAnalyzer::WEIGHT_IGNORE` (Infinity) for foreign keys that should never be used as part of the shortest path

Another option is to add a cost modifier to a table. This will alter the cost of all foreign keys pointing to or
originating from this table.

```php
$schemaAnalyzer->setTableCostModifier($tableName, $cost);
```
