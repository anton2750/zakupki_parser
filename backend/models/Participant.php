<?php

namespace app\models;

use Yii;
use yii\db\ActiveRecord;

/**
 * This is the model class for table "participant".
 *
 * @property int $id
 * @property string|null $number
 * @property string|null $inn
 * @property string|null $ogrn
 * @property string|null $kpp
 * @property string|null $jur
 * @property string|null $type
 * @property string|null $court
 * @property string|null $case_number
 * @property string|null $date_issue
 * @property string|null $date_implement
 * @property int $created_at
 * @property int $updated_at
 */
class Participant extends ActiveRecord
{


    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return 'participant';
    }

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['number', 'inn', 'ogrn', 'kpp', 'jur', 'type', 'court', 'case_number', 'date_issue', 'date_implement'], 'default', 'value' => null],
            [['court'], 'string'],
            [['created_at', 'updated_at'], 'required'],
            [['created_at', 'updated_at'], 'default', 'value' => null],
            [['created_at', 'updated_at'], 'string' ],
            [['number', 'inn', 'ogrn', 'kpp', 'jur', 'type', 'case_number', 'date_issue', 'date_implement'], 'string', 'max' => 255],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function attributeLabels()
    {
        return [
            'id' => 'ID',
            'number' => 'Number',
            'inn' => 'Inn',
            'ogrn' => 'Ogrn',
            'kpp' => 'Kpp',
            'jur' => 'Jur',
            'type' => 'Type',
            'court' => 'Court',
            'case_number' => 'Case Number',
            'date_issue' => 'Date Issue',
            'date_implement' => 'Date Implement',
            'created_at' => 'Created At',
            'updated_at' => 'Updated At',
        ];
    }

}
