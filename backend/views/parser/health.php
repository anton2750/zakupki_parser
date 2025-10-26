<?php

/** @var yii\web\View $this */
/** @var array $apiStats */

use yii\helpers\Html;
use yii\helpers\VarDumper;

$this->title = "Парсер закупок";
?>
<div class="site-index">
    <h1><?= Html::encode($this->title) ?></h1>

    <div class="panel panel-default">
        <div class="panel-heading">
            <h3>Статистика API</h3>
        </div>
        <div class="panel-body">
            <?php if (!empty($apiStats)): ?>
                <pre><?= VarDumper::dumpAsString($apiStats, 10, true) ?></pre>
            <?php else: ?>
                <p class="text-muted">Нет данных</p>
            <?php endif; ?>
        </div>
    </div>
</div>
