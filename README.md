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

## Usage

Start by instanciating 