<?php

namespace app\components\nestjs\clients;

use Yii;
use yii\base\Component;

/**
 * NestJS API Client
 * 
 * Компонент для работы с NestJS API
 */
class NestjsClient extends Component
{
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
     * Получить данные о закупках
     * 
     * @param array $headers Дополнительные заголовки
     * @return array Результат запроса
     */
    public function getZakupki($headers = [])
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
     * Проверить здоровье сервиса
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
}
