<?php
// ============================================
// KADILI NET - PalmPesa Payment Helper
// ============================================

class PalmPesa {

    public static function initiate(array $data, string $apiKey = null): array {
        $apiKey = $apiKey ?? PALMPESA_API_KEY;

        $payload = [
            'name'           => $data['name'] ?? 'Customer',
            'email'          => $data['email'] ?? 'customer@kadilihotspot.online',
            'phone'          => self::formatPhone($data['phone']),
            'amount'         => (int)$data['amount'],
            'transaction_id' => $data['transaction_id'] ?? uniqid('KDL'),
            'address'        => $data['address'] ?? 'Tanzania',
            'postcode'       => $data['postcode'] ?? '00000',
            'callback_url'   => $data['callback_url'] ?? SITE_URL . '/api/palmpesa_callback.php',
        ];

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL            => PALMPESA_ENDPOINT,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => json_encode($payload),
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_HTTPHEADER     => [
                'Authorization: Bearer ' . $apiKey,
                'Content-Type: application/json',
                'Accept: application/json',
            ],
        ]);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $result = json_decode($response, true);
        return [
            'success'  => $httpCode === 200,
            'order_id' => $result['order_id'] ?? null,
            'message'  => $result['message'] ?? 'Unknown error',
            'raw'      => $result,
        ];
    }

    public static function formatPhone(string $phone): string {
        $phone = preg_replace('/\D/', '', $phone);
        if (strlen($phone) === 9) $phone = '0' . $phone;
        if (substr($phone, 0, 3) === '255') $phone = '0' . substr($phone, 3);
        return $phone;
    }
}
