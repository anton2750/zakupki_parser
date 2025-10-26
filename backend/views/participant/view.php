<?php

use app\models\Participant;
use yii\helpers\Html;
use yii\widgets\DetailView;

/** @var yii\web\View $this */
/** @var Participant $model */

$this->title = 'Участник: ' . $model->id;
$this->params['breadcrumbs'][] = ['label' => 'Участники закупок', 'url' => ['list']];
$this->params['breadcrumbs'][] = $this->title;
?>
<div class="participant-view">

    <h1><?= Html::encode($this->title) ?></h1>

    <p>
        <?= Html::a('« Вернуться к списку', ['list'], ['class' => 'btn btn-default']) ?>
    </p>

    <?= DetailView::widget([
        'model' => $model,
        'attributes' => [
            'id',
            [
                'attribute' => 'number',
                'label' => 'Номер',
            ],
            [
                'attribute' => 'inn',
                'label' => 'ИНН',
            ],
            [
                'attribute' => 'ogrn',
                'label' => 'ОГРН',
            ],
            [
                'attribute' => 'kpp',
                'label' => 'КПП',
            ],
            [
                'attribute' => 'jur',
                'label' => 'Юридическое лицо',
            ],
            [
                'attribute' => 'type',
                'label' => 'Тип',
            ],
            [
                'attribute' => 'court',
                'label' => 'Суд',
                'format' => 'ntext',
            ],
            [
                'attribute' => 'case_number',
                'label' => 'Номер дела',
            ],
            [
                'attribute' => 'date_issue',
                'label' => 'Дата выдачи',
            ],
            [
                'attribute' => 'date_implement',
                'label' => 'Дата исполнения',
            ],
            [
                'attribute' => 'created_at',
                'label' => 'Создан',
                'format' => ['date', 'php:Y-m-d H:i:s'],
            ],
            [
                'attribute' => 'updated_at',
                'label' => 'Обновлен',
                'format' => ['date', 'php:Y-m-d H:i:s'],
            ],
        ],
    ]) ?>

</div>

