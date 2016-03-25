<?php

namespace Mouf\Database\SchemaAnalyzer;

use Doctrine\DBAL\Schema\Schema;

class SchemaAnalyzerTableNotFoundException extends SchemaAnalyzerException
{
    public static function tableNotFound($tableName, Schema $schema, \Exception $previousException = null)
    {
        $closestTableName = '';
        $closestScore = INF;
        foreach ($schema->getTables() as $testedTable) {
            $testedTableName = $testedTable->getName();
            $l = levenshtein($testedTableName, $tableName);
            if ($l < $closestScore) {
                $closestScore = $l;
                $closestTableName = $testedTableName;
            }
        }
        return new self("Could not find table '$tableName'. Did you mean '$closestTableName'?", 0, $previousException);
    }
}
