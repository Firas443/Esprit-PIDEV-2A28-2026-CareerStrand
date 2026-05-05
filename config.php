<?php

class config
{
    private static ?PDO $pdo = null;
    private static string $huggingFaceFeedbackModel = 'katanemo/Arch-Router-1.5B';

    public static function getConnexion(): PDO
    {
        if (self::$pdo === null) {
            $host = "localhost";
            $dbName = "";
            $username = "root";
            $password = "";

            self::$pdo = new PDO(
                "mysql:host=$host;dbname=$dbName;charset=utf8mb4",
                $username,
                $password,
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                ]
            );
        }

        return self::$pdo;
    }

    public static function getHuggingFaceApiKey(): string
    {
        return trim(self::$huggingFaceApiKey);
    }

    public static function getHuggingFaceFeedbackModel(): string
    {
        $model = trim(self::$huggingFaceFeedbackModel);
        return $model !== '' ? $model : 'katanemo/Arch-Router-1.5B';
    }
}
