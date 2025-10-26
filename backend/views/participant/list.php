<?php

use app\models\ParticipantFilter;
use yii\data\ActiveDataProvider;
use yii\helpers\Html;
use yii\grid\GridView;
use yii\helpers\Url;

/** @var yii\web\View $this */
/** @var ParticipantFilter $searchModel */
/** @var ActiveDataProvider $dataProvider */

$this->title = 'Участники закупок';
$this->params['breadcrumbs'][] = $this->title;
?>
<div class="participant-list">

    <h1><?= Html::encode($this->title) ?></h1>

    <?= GridView::widget([
        'dataProvider' => $dataProvider,
        'filterModel' => $searchModel,
        'columns' => [
            ['class' => 'yii\grid\SerialColumn'],
            [
                'attribute' => 'id',
                'label' => 'Number',
                'value' => fn ($model) => Html::a($model->id, ['view', 'id' => $model->id], ['class' => 'text-primary']),
                'format' => 'raw',
            ],
            [
                'attribute' => 'inn',
                'label' => 'ИНН',
                'format' => 'text',
            ],
            [
                'attribute' => 'ogrn',
                'label' => 'ОГРН',
                'format' => 'text',
            ],
            [
                'attribute' => 'kpp',
                'label' => 'КПП',
                'format' => 'text',
            ],
            [
                'attribute' => 'jur',
                'label' => 'Юридическое лицо',
                'format' => 'text',
            ],
            [
                'attribute' => 'type',
                'label' => 'Тип',
                'format' => 'text',
            ],
            [
                'attribute' => 'number',
                'label' => 'Номер',
                'format' => 'text',
            ],
            [
                'attribute' => 'case_number',
                'label' => 'Номер дела',
                'format' => 'text',
            ],
            [
                'attribute' => 'date_issue',
                'label' => 'Дата выдачи',
                'format' => 'text',
            ],
            [
                'attribute' => 'date_implement',
                'label' => 'Дата исполнения',
                'format' => 'text',
            ],
            [
                'attribute' => 'court',
                'label' => 'Суд',
                'format' => 'ntext',
                'contentOptions' => ['style' => 'max-width: 300px; white-space: normal;'],
            ],
            [
                'attribute' => 'created_at',
                'label' => 'Создан',
                'format' => ['date', 'php:Y-m-d H:i:s'],
                'filter' => false,
            ],
            [
                'attribute' => 'updated_at',
                'label' => 'Обновлен',
                'format' => ['date', 'php:Y-m-d H:i:s'],
                'filter' => false,
            ],

            [
                'class' => 'yii\grid\ActionColumn',
                'template' => '{view}',
                'buttons' => [
                    'view' => function ($url, $model, $key) {
                        return Html::a('<span class="glyphicon glyphicon-eye-open"></span>', $url, [
                            'title' => 'Просмотр',
                            'aria-label' => 'Просмотр',
                            'data-pjax' => '0',
                        ]);
                    },
                ],
            ],
        ],
        'pager' => [
            'class' => 'yii\widgets\LinkPager',
            'options' => ['class' => 'pagination'],
            'linkOptions' => ['class' => 'page-link'],
            'firstPageLabel' => 'Первая',
            'lastPageLabel' => 'Последняя',
            'prevPageLabel' => '&laquo;',
            'nextPageLabel' => '&raquo;',
            'maxButtonCount' => 10,
        ],
        'summary' => 'Показано <b>{begin}-{end}</b> из <b>{totalCount}</b> записей',
        'emptyText' => 'Участники не найдены',
        'tableOptions' => ['class' => 'table table-striped table-bordered'],
    ]); ?>

</div>

