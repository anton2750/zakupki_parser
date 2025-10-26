<?php

namespace app\controllers;

use app\components\nestjs\clients\ZakupkiClient;
use app\services\ParticipantService;
use Yii;
use yii\web\Controller;

class ParserController extends Controller
{

    public function actionHealth()
    {
        $zakupkiClient = new ZakupkiClient();

        $apiStats = $zakupkiClient->getApiStats();

        return $this->render('health', [
            'apiStats' => $apiStats,
            'client' => $zakupkiClient
        ]);
    }

    public function actionFetch()
    {
        // Создаем экземпляр клиента
        $zakupkiClient = new ZakupkiClient();

        $response = $zakupkiClient->getZakupkiData();

        if (!$response['success']) {
            Yii::$app->session->setFlash('error', 'Ошибка получения данных: ' . $response['message']);
            return $this->goBack();
        }

        $data = $response['data'] ?? [];

        if (empty($data)) {
            Yii::$app->session->setFlash('warning', 'Получен пустой массив данных');
            return $this->goBack();
        }

        $service = new ParticipantService();
        
        try {
            [$new, $updated, $same, $del] = $service->bulkSave($data);
            
            // Формируем сообщение с результатами
            $message = sprintf(
                'Данные успешно обработаны! Добавлено: %d, Обновлено: %d, Без изменений: %d, Удалили: %d',
                $new,
                $updated,
                $same,
                $del,
            );

            Yii::$app->session->setFlash('success', $message);
            
        } catch (\Exception $e) {
            Yii::error('Критическая ошибка при сохранении данных: ' . $e->getMessage(), __METHOD__);
            Yii::$app->session->setFlash('error', 'Критическая ошибка при сохранении данных: ' . $e->getMessage());
        }

        return $this->goBack();
    }

}