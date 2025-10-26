<?php

namespace app\components\nestjs\clients;

use yii\base\Component;

/**
 * Zakupki API Client
 * 
 * Специализированный клиент для работы с API закупок
 */
class NestjsClient extends Component
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
     * @var string Базовый URL для NestJS API
     */
    public $baseUrl = 'http://zakupki.loc/nestjs';

    /**
     * @var int Таймаут для запросов в секундах
     */
    public $timeout = 30;

    /**
     * @var int Таймаут подключения в секундах
     */
    public $connectTimeout = 10;

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
     * Получить данные о закупках
     *
     * @param array $headers Дополнительные заголовки
     * @return array Результат запроса
     */
    public function getModels($headers = [])
    {
        return $this->makeRequest('GET', '/parser', null, $headers);
    }

    /**
     * Получить статус кэша
     *
     * @param array $headers Дополнительные заголовки
     * @return array Результат запроса
     */
    public function getCacheStatus($headers = [])
    {
        return $this->makeRequest('GET', '/parser/status', null, $headers);
    }

    /**
     * Проверить health
     *
     * @param array $headers Дополнительные заголовки
     * @return array Результат запроса
     */
    public function getHealth($headers = [])
    {
        return $this->makeRequest('GET', '/health', null, $headers);
    }

    /**
     * Выполнить HTTP запрос к NestJS API
     *
     * @param string $method HTTP метод
     * @param string $endpoint Эндпоинт
     * @param mixed $data Данные для отправки
     * @param array $headers Дополнительные заголовки
     * @return array Результат запроса
     */
    public function makeRequest($method, $endpoint, $data = null, $headers = [])
    {
        $url = $this->baseUrl . $endpoint;

        // Базовые заголовки
        $defaultHeaders = [
            'Content-Type: application/json',
            'Accept: application/json'
        ];

        $allHeaders = array_merge($defaultHeaders, $headers);

        // Инициализируем cURL
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $allHeaders);
        curl_setopt($ch, CURLOPT_TIMEOUT, $this->timeout);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $this->connectTimeout);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);

        // Добавляем данные для POST/PUT запросов
        if ($data !== null) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, is_string($data) ? $data : json_encode($data));
        }

        // Выполняем запрос
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        // Обрабатываем результат
        if ($error) {
            return [
                'success' => false,
                'error' => true,
                'message' => 'cURL Error: ' . $error,
                'data' => null,
                'http_code' => 0,
                'raw_response' => null
            ];
        }

        $decodedResponse = json_decode($response, true);

        return [
            'success' => $httpCode >= 200 && $httpCode < 300,
            'error' => $httpCode >= 400,
            'message' => $httpCode >= 400 ? 'HTTP Error: ' . $httpCode : 'Success',
            'data' => $decodedResponse,
            'http_code' => $httpCode,
            'raw_response' => $response,
            'url' => $url,
            'method' => $method
        ];
    }

    /**
     * Получить информацию о последнем запросе
     *
     * @return array
     */
    public function getLastRequestInfo()
    {
        return [
            'base_url' => $this->baseUrl,
            'timeout' => $this->timeout,
            'connect_timeout' => $this->connectTimeout
        ];
    }

    /**
     * Получить данные о закупках с авторизацией
     * 
     * @return array Результат запроса
     */
    public function getZakupkiData()
    {
        $headers = $this->getAuthHeaders();
        return $this->getModels($headers);
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
        return $this->getCacheStatus($allHeaders);
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
        $statusResult = $this->getCacheStatusWithAuth();

        return [
            'cache_status' => $statusResult,
            'timestamp' => date('Y-m-d H:i:s')
        ];
    }
}
