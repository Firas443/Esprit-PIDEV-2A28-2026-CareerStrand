<?php

class config
{
    private static ?PDO $pdo = null;
    private static string $defaultHuggingFaceFeedbackModel = 'Qwen/Qwen2.5-7B-Instruct';

    public static function getConnexion(): PDO
    {
        if (self::$pdo === null) {
            $host = getenv('DB_HOST') ?: "127.0.0.1";
            $dbName = getenv('DB_NAME') ?: "careerstrand";
            $username = getenv('DB_USER') ?: "root";
            $password = getenv('DB_PASS') ?: "";

            self::$pdo = new PDO(
                "mysql:host=$host;dbname=$dbName;charset=utf8mb4",
                $username,
                $password,
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_TIMEOUT => 3,
                ]
            );
        }

        return self::$pdo;
    }

    public static function getHuggingFaceApiKey(): string
    {
        $key = getenv('HF_TOKEN')
            ?: getenv('HUGGINGFACE_HUB_TOKEN')
            ?: getenv('HUGGINGFACE_API_KEY')
            ?: ($_ENV['HF_TOKEN'] ?? '')
            ?: ($_ENV['HUGGINGFACE_HUB_TOKEN'] ?? '')
            ?: ($_ENV['HUGGINGFACE_API_KEY'] ?? '')
            ?: ($_SERVER['HF_TOKEN'] ?? '')
            ?: ($_SERVER['HUGGINGFACE_HUB_TOKEN'] ?? '')
            ?: ($_SERVER['HUGGINGFACE_API_KEY'] ?? '');

        if (defined('HF_TOKEN') && trim((string) $key) === '') {
            $key = (string) constant('HF_TOKEN');
        }

        if (defined('HUGGINGFACE_HUB_TOKEN') && trim((string) $key) === '') {
            $key = (string) constant('HUGGINGFACE_HUB_TOKEN');
        }

        if (defined('HUGGINGFACE_API_KEY') && trim((string) $key) === '') {
            $key = (string) constant('HUGGINGFACE_API_KEY');
        }

        return trim((string) $key);
    }

    public static function getHuggingFaceFeedbackModel(): string
    {
        $model = getenv('HF_FEEDBACK_MODEL')
            ?: getenv('HF_MODEL')
            ?: getenv('HUGGINGFACE_MODEL')
            ?: ($_ENV['HF_FEEDBACK_MODEL'] ?? '')
            ?: ($_ENV['HF_MODEL'] ?? '')
            ?: ($_ENV['HUGGINGFACE_MODEL'] ?? '')
            ?: ($_SERVER['HF_FEEDBACK_MODEL'] ?? '')
            ?: ($_SERVER['HF_MODEL'] ?? '')
            ?: ($_SERVER['HUGGINGFACE_MODEL'] ?? '');

        if (defined('HF_FEEDBACK_MODEL') && trim((string) $model) === '') {
            $model = (string) constant('HF_FEEDBACK_MODEL');
        }

        if (trim((string) $model) !== '') {
            return trim((string) $model);
        }

        return self::$defaultHuggingFaceFeedbackModel;

    }
}
