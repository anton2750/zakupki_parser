<?php

namespace app\components\nestjs\clients;

use Yii;

/**
 * Zakupki API Client
 * 
 * Специализированный клиент для работы с API закупок
 */
class ZakupkiClient extends NestjsClient
{
    /**
     * @var string API ключ для авторизации
     */
    public $apiKey;

    /**
     * @var string Имя заголовка для API ключа
     */
    public $apiKeyHeader = 'Authorization';

    /**
     * Конструктор
     */
    public function init()
    {
        parent::init();
        
        // Устанавливаем API ключ из конфигурации или переменных окружения
        if (empty($this->apiKey)) {
            $this->apiKey = getenv('API_KEY') ?? 'supersecret';
        }
    }

    /**
     * Получить данные о закупках с авторизацией
     * 
     * @return array Результат запроса
     */
    public function getZakupkiData()
    {
        $headers = $this->getAuthHeaders();
        return $this->getZakupki($headers);
    }

    /**
     * Получить статус кэша с авторизацией
     * 
     * @param array $headers Дополнительные заголовки
     * @return array Результат запроса
     */
    public function getCacheStatusWithAuth($headers = [])
    {
        $authHeaders = $this->getAuthHeaders();
        $allHeaders = array_merge($authHeaders, $headers);
        return parent::getCacheStatus($allHeaders);
    }

    /**
     * Получить заголовки авторизации
     * 
     * @return array
     */
    protected function getAuthHeaders()
    {
        return [
            $this->apiKeyHeader . ': Bearer ' . $this->apiKey
        ];
    }

    /**
     * Проверить доступность API
     * 
     * @return bool
     */
    public function isApiAvailable()
    {
        $result = $this->getHealth();
        return $result['success'] && $result['http_code'] === 200;
    }

    /**
     * Получить статистику API
     * 
     * @return array
     */
    public function getApiStats()
    {
        $healthResult = $this->getHealth();
        $statusResult = $this->getCacheStatusWithAuth();

        return [
            'health' => $healthResult,
            'cache_status' => $statusResult,
            'timestamp' => date('Y-m-d H:i:s')
        ];
    }
}
