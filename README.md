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

## Computing the shortest path between 2 tables

Following foreign keys, the `getShortestPath` function will try to find the shortest path between 2 tables.
It will return the list of foreign keys it used to link the 2 tables.

Internals:

- Each foreign key has a *cost* of 1
- Junction tables have a cost of 1.5, instead of 2 (one for each foreign key)

```php
// $conn is the DBAL connection.
$schemaAnalyzer = new SchemaAnalyzer($conn->getSchemaManager());

// Let's detect the shortest path between 2 tables:
$fks = $schemaAnalyzer->getShortestPath("users", "rights");
// This will return an array of Doctrine\DBAL\Schema\ForeignKeyConstraint objects
```

// TODO: Ambiguity exception!