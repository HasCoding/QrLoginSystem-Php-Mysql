<?php

namespace App;

use PDO;
use Ramsey\Uuid\Uuid;
use Endroid\QrCode\Builder\Builder;
use Endroid\QrCode\Writer\PngWriter;
use Exception;

// Türkiye zaman dilimini ayarla
date_default_timezone_set('Europe/Istanbul');

class ApiController
{
    /**
     * API isteklerini handle eden ana metod
     */
    public static function handleRequest(): void
    {
        // Headers ayarla
        header('Content-Type: application/json');
        header('Access-Control-Allow-Origin: *');
        header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
        header('Access-Control-Allow-Headers: Content-Type');

        // OPTIONS isteği için
        if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
            http_response_code(200);
            exit;
        }

        $action = $_GET['action'] ?? null;

        try {
            switch ($action) {
                case 'pair':
                    if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
                        self::sendJsonResponse(['error' => 'Method Not Allowed'], 405);
                    }
                    $response = self::startSession();
                    self::sendJsonResponse($response);
                    break;

                case 'check':
                    if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
                        self::sendJsonResponse(['error' => 'Method Not Allowed'], 405);
                    }
                    $sessionId = $_GET['sessionId'] ?? '';
                    $response = self::checkSession($sessionId);
                    self::sendJsonResponse($response);
                    break;

                case 'validate':
                    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                        self::sendJsonResponse(['error' => 'Method Not Allowed'], 405);
                    }
                    // JSON veya POST data'yı al
                    $data = json_decode(file_get_contents('php://input'), true);
                    if (json_last_error() !== JSON_ERROR_NONE) {
                        $data = $_POST;
                    }
                    $response = self::validateSession($data);
                    self::sendJsonResponse($response);
                    break;

                default:
                    self::sendJsonResponse(['error' => 'Invalid action'], 400);
            }

        } catch (Exception $e) {
            self::sendJsonResponse([
                'error' => 'Internal Server Error',
                'details' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * JSON response gönder ve çık
     */
    private static function sendJsonResponse(array $data, int $statusCode = 200): void
    {
        http_response_code($statusCode);
        echo json_encode($data, JSON_UNESCAPED_UNICODE);
        exit;
    }

    /**
     * QR session başlat
     */
    public static function startSession(): array
    {
        $sessionId = Uuid::uuid4()->toString();

        // Türkiye saati ile expire time hesapla (UTC+3)
        $now = new \DateTime('now', new \DateTimeZone('Europe/Istanbul'));
        $expires = clone $now;
        $expires->add(new \DateInterval('PT90S')); // 90 saniye ekle
        $expiresAt = $expires->format('Y-m-d H:i:s');

        try {
            $pdo = Database::getInstance();
            $stmt = $pdo->prepare("INSERT INTO qr_sessions (session_id, expires_at) VALUES (?, ?)");
            $stmt->execute([$sessionId, $expiresAt]);
        } catch (\PDOException $e) {
            return [
                'error' => 'Database error',
                'details' => $e->getMessage()
            ];
        }

        // QR kod oluştur
        try {
            $result = Builder::create()
                ->writer(new PngWriter())
                ->data($sessionId)
                ->size(200)
                ->margin(10)
                ->build();

            return [
                'success' => true,
                'qrImage' => $result->getDataUri(),
                'sessionId' => $sessionId,
                'expiresIn' => 90,
                'currentTime' => $now->format('Y-m-d H:i:s'),
                'timezone' => 'Europe/Istanbul (UTC+3)'
            ];

        } catch (Exception $e) {
            return [
                'error' => 'QR code generation error',
                'details' => $e->getMessage()
            ];
        }
    }

    /**
     * Session durumunu kontrol et
     */
    public static function checkSession(string $sessionId): array
    {
        if (empty($sessionId)) {
            return [
                'status' => 'error',
                'message' => 'Session ID is required'
            ];
        }

        try {
            $pdo = Database::getInstance();
            $stmt = $pdo->prepare("
                SELECT 
                    qs.status,
                    qs.expires_at,
                    qs.user_id,
                    qs.validated_at,
                    u.name AS user_name
                FROM qr_sessions qs 
                LEFT JOIN users u ON qs.user_id = u.id 
                WHERE qs.session_id = ?
            ");
            $stmt->execute([$sessionId]);
            $session = $stmt->fetch(PDO::FETCH_ASSOC);

        } catch (\PDOException $e) {
            return [
                'status' => 'error',
                'message' => 'Database error',
                'details' => $e->getMessage()
            ];
        }

        if (!$session) {
            return ['status' => 'expired'];
        }

        // Süre kontrolü - Türkiye saati ile
        if (strtotime($session['expires_at']) < time()) {
            // Expired olarak işaretle
            try {
                $stmt = $pdo->prepare("UPDATE qr_sessions SET status = 'expired' WHERE session_id = ?");
                $stmt->execute([$sessionId]);
            } catch (\PDOException $e) {
                // Sessizce devam et
            }
            return [
                'status' => 'expired',
                'currentTime' => (new \DateTime('now', new \DateTimeZone('Europe/Istanbul')))->format('Y-m-d H:i:s')
            ];
        }

        $response = [
            'status' => $session['status'],
            'currentTime' => (new \DateTime('now', new \DateTimeZone('Europe/Istanbul')))->format('Y-m-d H:i:s')
        ];

        // Eğer validate edilmişse kullanıcı bilgilerini ekle
        if ($session['status'] === 'validated') {
            $response['userName'] = $session['user_name'] ?? 'Bilinmeyen Kullanıcı';
            $response['userId'] = $session['user_id'];
            $response['validatedAt'] = $session['validated_at'];
            $response['status'] = 'success'; // Frontend için
        }

        return $response;
    }

    /**
     * Session'ı validate et
     */
    public static function validateSession(array $data): array
    {
        $sessionId = $data['sessionId'] ?? null;
        $mobileToken = $data['mobileAuthToken'] ?? null;

        if (!$sessionId || !$mobileToken) {
            return [
                'success' => false,
                'message' => 'Session ID and mobile token are required'
            ];
        }

        try {
            $pdo = Database::getInstance();

            // Kullanıcı doğrulama
            $stmt = $pdo->prepare("SELECT id, name FROM users WHERE mobile_token = ?");
            $stmt->execute([$mobileToken]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$user) {
                return [
                    'success' => false,
                    'message' => 'Invalid mobile token'
                ];
            }

            // Türkiye saati ile validated_at
            $validatedAt = (new \DateTime('now', new \DateTimeZone('Europe/Istanbul')))->format('Y-m-d H:i:s');

            // Session kontrolü ve güncelleme
            $stmt = $pdo->prepare("
                UPDATE qr_sessions 
                SET status = 'validated', user_id = ?, validated_at = ? 
                WHERE session_id = ? AND status = 'pending' AND expires_at > NOW()
            ");
            $stmt->execute([$user['id'], $validatedAt, $sessionId]);

            if ($stmt->rowCount() === 0) {
                return [
                    'success' => false,
                    'message' => 'Session not found or expired'
                ];
            }

            return [
                'success' => true,
                'message' => 'Session validated successfully',
                'userName' => $user['name'],
                'validatedAt' => $validatedAt,
                'timezone' => 'Europe/Istanbul (UTC+3)'
            ];

        } catch (\PDOException $e) {
            return [
                'success' => false,
                'message' => 'Database error',
                'details' => $e->getMessage()
            ];
        }
    }
}

// Eğer bu dosya direkt çağrılıyorsa request'i handle et
if (basename($_SERVER['PHP_SELF']) === 'ApiController.php') {
    ApiController::handleRequest();
}
?>