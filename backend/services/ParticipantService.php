<?php

namespace app\services;

use app\helpers\HArray;
use app\helpers\HPg;
use app\models\Participant;
use Yii;
use yii\db\Exception;

/**
 * Сервис для массового сохранения данных участников закупок
 */
class ParticipantService
{
    /**
     * Массово сохраняет данные участников в базу данных
     * 
     * @param array $data Массив данных участников из API
     * @return array Статистика сохранения ['inserted' => int, 'updated' => int, 'skipped' => int, 'errors' => array]
     */
    public function bulkSave(array $data)
    {
        $data = HArray::getCols($data, ['number', 'inn', 'ogrn', 'kpp', 'jur', 'type', 'court', 'case_number', 'date_issue', 'date_implement']);

        [$new, $updated, $same, $del] = HPg::batchUpsert(Participant::tableName(), $data, [
            'indexCols' => ['inn'],
            'time' => true,
        ]);

        return [$new, $updated, $same, $del];
    }
}


