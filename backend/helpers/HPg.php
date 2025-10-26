<?php

namespace app\helpers;

use Yii;
use yii\db\ArrayExpression;
use yii\db\Connection;
use yii\db\Expression;
use yii\db\JsonExpression;
use yii\db\Query;
use yii\helpers\ArrayHelper;

class HPg
{
    public static $db;

    /**
     * @param mixed $db
     */
    public static function setDb($db)
    {
        self::$db = $db;
    }

    /**
     * @return Connection
     */
    public static function getDb()
    {
        return self::$db ?: Yii::$app->db;
    }

    public static function getBatchSize()
    {
        return 10000;
    }


    public static function getJsonCols($table, $onlyCols = [])
    {
        if (strpos($table, 'temp_') === 0) {
            $oldTable = $table;
            $table = preg_replace('/^temp_(.*?)(_\d+)?$/', '$1', $table);
        }
        $columns = self::getDb()->schema->getTableSchema($table)?->columns ?: [];
        if ($onlyCols) {
            $columns = HArray::filterKeys($columns, $onlyCols);
        }
        $jsonCols = $arrayCols = [];
        foreach ($columns as $column) {
            if ($column->type == 'json') {
                $jsonCols[] = $column->name;
            } elseif ($column->dimension > 0) {
                $arrayCols[$column->name] = $column->dbType;
            }
        }
        return [$jsonCols, $arrayCols];
    }

    public static function normalizeRows($table, array $rows)
    {
        $rows = array_values($rows);
        $columns = array_keys($rows[0]);
        list($jsonCols, $arrayCols) = self::getJsonCols($table, $columns);
        if ($jsonCols || $arrayCols) {
            foreach ($rows as $i => $row) {
                foreach ($jsonCols as $jsonCol) {
                    if (is_array($row[$jsonCol])) {
                        $rows[$i][$jsonCol] = new JsonExpression($row[$jsonCol]);
                    }
                }
                foreach ($arrayCols as $arrayCol => $arrayType) {
                    if (is_array($row[$arrayCol])) {
//                        $isInt = is_int(HArray::first($row[$arrayCol]) ?: 0);
                        $rows[$i][$arrayCol] = new ArrayExpression($row[$arrayCol], $arrayType);
                    }
                }
            }
        }

        return $rows;
    }

    public static function batchInsert($table, $rows, $onConflict = null)
    {
        $totalCnt = count($rows);
        if (!$totalCnt) {
            return 0;
        }
        if ($totalCnt > self::getBatchSize()) {
            $chunks = array_chunk($rows, self::getBatchSize());
            $cntChunks = count($chunks);
            $totalInserted = 0;
            foreach ($chunks as $i => $chunk) {
                $cnt = count($chunk);
                $inserted = self::batchInsert($table, $chunk, $onConflict);
                $totalInserted += $inserted;
            }
            return $totalInserted;
        }

        $rows = self::normalizeRows($table, $rows);

        $command = self::getDb()->createCommand()->batchInsert($table, array_keys($rows[0]), $rows);

        if ($onConflict) {
            $command->rawSql .= $onConflict;
        }
        return $command->execute();
    }

    public static function buildCondition($condition, &$params): string
    {
        if (!$condition) {
            return '';
        }

        if (is_string($condition)) {
            return $condition;
        }

        $queryBuilder = Yii::$app->db->getQueryBuilder();
        $where = $queryBuilder->buildCondition($condition, $params);

        return $where ? '(' . $where . ')' : '';
    }

    public static function getAliasCols($columns, $alias)
    {
        $cols = [];
        foreach ($columns as $key => $column) {
            if (!is_numeric($key)) {
                $cols[$key] = $column;
            } else {
                $cols[$column] = substr_count($column, '.') ? $column : "$alias.$column";
            }
        }
        return $cols;
    }

    public static function isSimilar(string $field1, string $field2): string
    {
        return "($field1 = $field2 OR ($field1 IS NULL AND $field2 IS NULL))";
    }

    public static function createTemporaryTable($tempName, $likeTable)
    {
        $sql = <<<SQL
CREATE TEMPORARY TABLE IF NOT EXISTS $tempName AS SELECT * FROM "{$likeTable}" WHERE FALSE;
SQL;
        self::getDb()->createCommand($sql)->execute();
        self::getDb()->createCommand()->truncateTable($tempName)->execute();
    }

    public static function getAliasSet($columns, $alias = '')
    {
        $aliasCols = $alias ? self::getAliasCols($columns, $alias) : $columns;
        return '(' . implode(', ', $aliasCols) . ')';
    }

    /**
     * @param $table
     * @param string|array|Query $data
     * @param array $updateCols
     * @param array $options
     *
     * @return int
     * @throws \yii\db\Exception
     */
    public static function batchUpdate($table, $data, array $updateCols, array $options = []): int
    {
        if (!$data) {
            return 0;
        }

        $transaction = HArray::remove($options, 'transaction', false);
        if ($transaction) {
            return self::getDb()->transaction(fn() => self::batchUpdate($table, $data, $updateCols, $options));
        }

        $forceValue = HArray::remove($options, 'otherwise');
        if (strlen((string)$forceValue)) {
            $otherOptions = array_merge($options, [
                'inverse' => true,
                'forceValue' => $forceValue
            ]);
            $otherCnt = self::batchUpdate($table, $data, $updateCols, $otherOptions);
        }
        $inverse = ArrayHelper::remove($options, 'inverse', false); //Т.е. обновить значения не входящие в data значением $forceData;
        $totalCnt = is_array($data) ? count($data) : 0;
        $batchField = ArrayHelper::remove($options, 'batchField');
        if (!$inverse && !$batchField && $totalCnt > self::getBatchSize()) {
            $chunks = array_chunk($data, self::getBatchSize());
            $cntChunks = count($chunks);
            $totalUpdated = 0;
            foreach ($chunks as $i => $chunk) {
                $cnt = count($chunk);
                $updated = self::batchUpdate($table, $chunk, $updateCols, $options);
                $totalUpdated += $updated;
            }

            return $totalUpdated;
        }

        $indexCols = ArrayHelper::remove($options, 'indexCols', ['id']);
        $specialSets = ArrayHelper::remove($options, 'specialSets', []);
        $updateCols = array_diff(array_unique($updateCols), $indexCols);
        $joinExpression = ArrayHelper::remove($options, 'joinExpression', false); //Если нужно использовать выражение для матчинга данных с таблицей
        $cond = ArrayHelper::remove($options, 'condition');
        $checkModified = ArrayHelper::remove($options, 'check', true);
        $nullableIndex = ArrayHelper::remove($options, 'checkNulls', count($indexCols) > 1); //если не может быть null лучше прописывать checkNulls false
        $debug = ArrayHelper::remove($options, 'debug', false);
        $forceValue = $options['forceValue'] ?? null;
        $log = ArrayHelper::remove($options, 'log', false);

        $needDropTmpTable = false;
        if (is_array($data)) {
            $tempTable = "temp_{$table}_" . time();
            $needDropTmpTable = true;
            self::createTemporaryTable($tempTable, $table);

            $data = HArray::getCols($data, array_merge($updateCols, $indexCols), false);
            $inserted = self::batchInsert($tempTable, $data);
        } elseif ($data instanceof Query) {
            $tempTable = '(' . $data->createCommand()->rawSql . ')';
        } else {
            $tempTable = '(' . $data . ')';
        }

        $sets = [];
        foreach ($updateCols as $col) {
            $sets[$col] = "$col = " . (strlen((string)$forceValue) ? $forceValue : "t.$col");
        }
        foreach ($specialSets as $col => $specialSet) {
            $sets[$col] = "$col = $specialSet";
        }

        $setRows = implode(",\n", $sets);

        $conditions = [];
        if ($inverse) {
            $indexSelect = implode(', ', $indexCols);
            $indexSet = self::getAliasSet($indexCols, 'o');
            $sub = "SELECT $indexSelect FROM $tempTable t";
            $conditions[] = "($indexSet  not in  ({$sub}))";
        } elseif ($joinExpression) {
            $conditions[] = new Expression($joinExpression);
        } elseif ($nullableIndex) {
            foreach ($indexCols as $col) {
                $conditions[] = self::isSimilar("t.$col", "o.$col");
            }
        } else {
            $conditions[] = self::getAliasSet($indexCols, 'o') . ' = ' . self::getAliasSet($indexCols, 't');
        }

        if ($checkModified) {
            if (strlen((string)$forceValue)) {
                $tColsSet = '(' . implode(', ', array_fill_keys($updateCols, $forceValue)) . ')';
            } else {
                $tColsSet = self::getAliasSet($updateCols, 't');
            }
            $oColsSet = self::getAliasSet($updateCols, 'o');
            if (strlen((string)$forceValue) && $forceValue != 'null') {
                $conditions[] = "($oColsSet != $tColsSet)";
            } else {
                $conditions[] = "($oColsSet IS DISTINCT FROM $tColsSet)";
            }
        }

        $condition = implode(' AND ', $conditions);

        if ($cond) {
            $cond = self::buildCondition($cond);
            $condition .= " AND ($cond)";
        }
        $from = $inverse ? '' : "\nFROM $tempTable t";
        $updateSql = <<<SQL
UPDATE "{$table}" o SET 
$setRows {$from}
WHERE $condition
SQL;

        // Обновление батчами, для больших таблиц
        if ($batchField) {
            $_tableName = "\"$table\"";
            $_cond = $cond ?: '1=1';
            list($minBatchField, $maxBatchField) = $row = self::getDb()->createCommand("select MIN($batchField) as \"0\", MAX($batchField) as \"1\" from $_tableName o where {$_cond}")->queryOne() ?: [0, 0];
            $batchSize = self::getBatchSize();
            $from = $minBatchField ?: 0;
            $updatedCnt = 0;
            if ($maxBatchField) {
                do {
                    $from += $batchSize;
                    $partCondition = self::buildCondition(['between', 'o.' . $batchField, $from - $batchSize, $from - 1]);
                    $updatedCnt += $partCnt = self::getDb()->createCommand($updateSql . " AND {$partCondition}")->execute();
                } while ($from < $maxBatchField);
            }
        } else {
            $updatedCnt = self::getDb()->createCommand($updateSql)->execute();
        }
        $cntAll = is_array($data) ? count($data) : 0;

        if ($needDropTmpTable) {
            self::getDb()->createCommand()->dropTable($tempTable)->execute();
        }


        return $updatedCnt;
    }

    public static function batchUpdateOld($table, $updateCols, $indexCols, $data, $cond = null, $checkModified = true)
    {
        return self::batchUpdate($table, $data, $updateCols, [
            'indexCols' => $indexCols,
            'condition' => $cond,
            'check' => $checkModified
        ]);
    }

    public static function batchUpsert($tableName, $rows, $options = [])
    {
        $indexCols = ArrayHelper::remove($options, 'indexCols', ['id']);
        $condition = ArrayHelper::remove($options, 'condition', '');
        $deleteAttrs = ArrayHelper::remove($options, 'deleteAttrs');
        $delete = ArrayHelper::remove($options, 'delete', (bool)$deleteAttrs);
        $hasNulls = ArrayHelper::remove($options, 'hasNulls', false);
        $insert = ArrayHelper::remove($options, 'insert', true);
        $debug = ArrayHelper::remove($options, 'debug', false);
        $update = ArrayHelper::remove($options, 'update', true);
        $excludeCols = ArrayHelper::remove($options, 'excludeCols', []);
        $updatedAttrs = ArrayHelper::remove($options, 'updatedAttrs', []);
        $updateExcludedCols = ArrayHelper::remove($options, 'updateExcludedCols', []);
        $newAttrs = ArrayHelper::remove($options, 'newAttrs', []);
        $filterFunc = ArrayHelper::remove($options, 'filterFunc', null);
        $updateIf = ArrayHelper::remove($options, 'updateIf', []); //Обновлять только когда выполняется условие на атрибуты

        if (ArrayHelper::remove($options, 'time')) {
            $newAttrs = array_merge(['created_at' => HDates::long(), 'updated_at' => HDates::long()], $newAttrs);
            $updatedAttrs = array_merge(['updated_at' => HDates::long()], $updatedAttrs);
            $excludeCols = array_merge($excludeCols, ['created_at', 'updated_at']);
        }

        if ($debug) {
            $insert = false;
            $delete = false;
            $update = false;
        }

        $db = self::getDb();

        $rows = array_values($rows);

        $indexFunc = function ($row) use ($indexCols) {
            return implode(':', HArray::multiget($row, $indexCols));
        };
        $existRows = $db->useMaster(function () use ($tableName, $condition, $indexFunc, $indexCols, $db, $delete, $rows) {
            $query = (new Query())->from($tableName . ' o')->where($condition)->indexBy($indexFunc);
            if (!$delete and count($rows) < 5000) {
                $query->andWhere(['in', $indexCols, HArray::getCols($rows, $indexCols)]);
            }
            return $query->all();
        });

        if (!$rows) {
            if ($existRows && $delete) {
                $del = $db->createCommand(str_replace("\"{$tableName}\"", "\"{$tableName}\" o", $db->createCommand()->delete($tableName, ['AND', $condition, $updateIf])->rawSql))->execute();
            } else {
                $del = count($existRows);
            }
            return [0, 0, 0, $del];
        }
//        Log::profile('Exist: '.count($existRows));

        $same = $del = $skipped = 0;
        $cols = array_keys($rows[0]);
        $cmpCols = array_diff($cols, $excludeCols, $indexCols);
        $rows = ArrayHelper::index($rows, $indexFunc);
//        Log::profile('Indexed new: '.count($rows));
        $newRows = $updateRows = $skippedIds = [];
        foreach ($rows as $index => $row) {
            if (isset($existRows[$index])) {
                if ($updateIf && !HArray::filterRows([$existRows[$index]], $updateIf)) {
                    $skipped++;
                    $skippedIds[] = $index;
                    continue;
                }

                if (!HArray::eqAttrs($row, $existRows[$index], $cmpCols, $debug)
                    && (!$filterFunc || $filterFunc($existRows[$index]))
                ) {
                    $updateRows[] = $row;
                } else {
                    $same++;
                }
            } else {
                $newRows[] = $row;
            }
        }

//        Log::profile('Compared');
        $deleteRows = array_diff_key($existRows, $rows);
        if ($deleteRows && $filterFunc) {
            $deleteRows = array_filter($deleteRows, $filterFunc);
        }
        if ($deleteRows && $updateIf) {
            $deleteRows = HArray::filterRows($deleteRows, $updateIf);
        }

        if ($newAttrs && $newRows) {
            $newRows = HArray::multiset($newRows, $newAttrs);
        }

        if ($updatedAttrs && $updateRows) {
            $updateRows = HArray::multiset($updateRows, $updatedAttrs);
        }

        $transaction = $db->beginTransaction();
        try {
            if ($delete && $deleteRows) {
                if ($deleteAttrs) {
                    $delRows = HArray::multiset($deleteRows, array_merge($deleteAttrs, $updatedAttrs));
                    $del = self::batchUpdate($tableName, $delRows, array_keys($deleteAttrs), ['indexCols' => $indexCols]);
                } else {
                    $del = self::batchDelete($tableName, $indexCols, $deleteRows, $condition, $hasNulls);
                }
            } else {
                $del = count($deleteRows);
            }

            if ($insert) {
                $new = $newRows ? self::batchInsert($tableName, $newRows) : 0;
//                Log::profile('Insert: '.$new);
            } else {
                $new = count($newRows);
            }

            if ($update) {
                $updated = $updateRows ? self::batchUpdateOld($tableName, array_merge($cmpCols, array_keys($updatedAttrs), $updateExcludedCols), $indexCols, $updateRows, $condition, true) : 0;
            } else {
                $updated = count($updateRows);
            }
//            Log::profile('Update: '.$updated);
            $transaction->commit();
//            Log::profile("Commit");
        } catch (\Exception $e) {
            $transaction->rollBack();
            throw $e;
        }

        return [$new, $updated, $same, $del];
    }


    public static function batchDelete($table, $indexCols, $rows, $condition = null, $hasNulls = false)
    {
        $totalCnt = count($rows);

        if ($totalCnt > self::getBatchSize()) {
            $chunks = array_chunk($rows, self::getBatchSize());
            $cntChunks = count($chunks);
            $totalDeleted = 0;
            foreach ($chunks as $i => $chunk) {
                $cnt = count($chunk);
                $deleted = self::batchDelete($table, $indexCols, $chunk, $condition);
                $totalDeleted += $deleted;
            }
            return $totalDeleted;
        }

        $data = HArray::getCols($rows, $indexCols);
        $condition = self::buildCondition($condition);
        if ($hasNulls) {
            $cnt = 0;
            foreach ($data as $datum) {
                $query = self::getDb()->createCommand()->delete($table, $datum);
                $condition && $query->rawSql .= ' AND ' . $condition;
                $cnt += self::getDb()
                    ->createCommand(str_replace("\"{$table}\"", "\"{$table}\" o", $query->rawSql))->execute();
            }
            return $cnt;
        } else {
            $query = self::getDb()->createCommand()->delete(
                $table,
                count($indexCols) > 1
                    ? ['in', $indexCols, $data]
                    : [$indexCols[0] => ArrayHelper::getColumn($rows, $indexCols[0])]
            );

            if ($condition) {
                $query->rawSql .= " AND $condition";
            }

            return self::getDb()->createCommand(str_replace("\"{$table}\"", "\"{$table}\" o", $query->rawSql))->execute();
        }
    }

}