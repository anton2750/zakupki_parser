<?php

namespace app\models;

use yii\base\Model;
use yii\data\ActiveDataProvider;

class ParticipantFilter extends Participant
{
    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['id'], 'integer'],
            [['number', 'inn', 'ogrn', 'kpp', 'jur', 'type', 'court', 'case_number', 'date_issue', 'date_implement', 'created_at', 'updated_at'], 'string', 'max' => 255],
        ];
    }

    /**
     * @param array $params параметры поиска
     *
     * @return ActiveDataProvider
     */
    public function search($params)
    {
        $query = Participant::find();

        // Настройка data provider
        $dataProvider = new ActiveDataProvider([
            'query' => $query,
            'pagination' => [
                'pageSize' => 20,
            ],
            'sort' => [
                'defaultOrder' => [
                    'created_at' => SORT_DESC,
                ]
            ],
        ]);

        $this->load($params);

        if (!$this->validate()) {
            return $dataProvider;
        }

        // Фильтрация по точным совпадениям
        $query->andFilterWhere([
            'id' => $this->id,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ]);

        // Фильтрация по частичным совпадениям (LIKE)
        $query->andFilterWhere(['ilike', 'number', $this->number])
            ->andFilterWhere(['ilike', 'inn', $this->inn])
            ->andFilterWhere(['ilike', 'ogrn', $this->ogrn])
            ->andFilterWhere(['ilike', 'kpp', $this->kpp])
            ->andFilterWhere(['ilike', 'jur', $this->jur])
            ->andFilterWhere(['ilike', 'type', $this->type])
            ->andFilterWhere(['ilike', 'court', $this->court])
            ->andFilterWhere(['ilike', 'case_number', $this->case_number])
            ->andFilterWhere(['ilike', 'date_issue', $this->date_issue])
            ->andFilterWhere(['ilike', 'date_implement', $this->date_implement]);

        return $dataProvider;
    }
}

