<?php

namespace app\controllers;

use app\models\Participant;
use app\models\ParticipantFilter;
use Yii;
use yii\web\Controller;
use yii\web\NotFoundHttpException;
use yii\filters\VerbFilter;

/**
 * ParticipantController управляет CRUD операциями для модели Participant
 */
class ParticipantController extends Controller
{
    /**
     * {@inheritdoc}
     */
    public function behaviors()
    {
        return [
            'verbs' => [
                'class' => VerbFilter::class,
                'actions' => [
                    'delete' => ['POST'],
                ],
            ],
        ];
    }

    /**
     * Отображает список всех участников закупок с возможностью фильтрации
     * 
     * @return string
     */
    public function actionList()
    {
        $searchModel = new ParticipantFilter();
        $dataProvider = $searchModel->search(Yii::$app->request->queryParams);

        return $this->render('list', [
            'searchModel' => $searchModel,
            'dataProvider' => $dataProvider,
        ]);
    }

    /**
     * Отображает детальную информацию об участнике
     * 
     * @param int $id ID участника
     * @return string
     * @throws NotFoundHttpException если участник не найден
     */
    public function actionView($id)
    {
        return $this->render('view', [
            'model' => $this->findModel($id),
        ]);
    }

    /**
     * Находит модель Participant по ID
     * 
     * @param int $id ID участника
     * @return Participant модель
     * @throws NotFoundHttpException если модель не найдена
     */
    protected function findModel($id)
    {
        if (($model = Participant::findOne($id)) !== null) {
            return $model;
        }

        throw new NotFoundHttpException('Запрошенный участник не найден.');
    }
}


