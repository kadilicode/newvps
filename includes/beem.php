<?php
// ============================================
// KADILI NET - Beem SMS & OTP Helper
// ============================================

class BeemSMS {
    
    // Send bulk SMS
    public static function send(string $phone, string $message): array {
        $smsEnabled = DB::setting('sms_enabled');
        if (!$smsEnabled) return ['success' => false, 'message' => 'SMS disabled'];

        $apiKey = DB::setting('beem_sms_api_key') ?? BEEM_SMS_KEY;
        $secret = DB::setting('beem_sms_secret') ?? BEEM_SMS_SECRET;
        $senderId = DB::setting('sender_id') ?? BEEM_SENDER_ID;

        // Format phone to 255XXXXXXXXX
        $phone = self::formatPhone($phone);

        $payload = [
            'source_addr' => $senderId,
            'schedule_time' => '',
            'encoding' => '0',
            'message' => $message,
            'recipients' => [
                ['recipient_id' => time(), 'dest_addr' => $phone]
            ]
        ];

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL            => 'https://apisms.beem.africa/v1/send',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => json_encode($payload),
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
            CURLOPT_HTTPHEADER     => [
                'Content-Type: application/json',
                'Authorization: Basic ' . base64_encode("$apiKey:$secret"),
            ],
        ]);
        $response = curl_exec($ch);
        curl_close($ch);

        $data = json_decode($response, true);
        return [
            'success' => isset($data['successful']) && $data['successful'] === true,
            'data' => $data
        ];
    }

    // Send OTP via Beem OTP API
    public static function sendOTP(string $phone): array {
        $otpEnabled = DB::setting('otp_enabled');
        if (!$otpEnabled) {
            // Fallback: generate local OTP
            return self::generateLocalOTP($phone);
        }

        $apiKey = BEEM_OTP_KEY;
        $secret = BEEM_OTP_SECRET;
        $phone = self::formatPhone($phone);

        $payload = [
            'source_addr' => BEEM_SENDER_ID,
            'schedule_time' => '',
            'encoding' => '0',
            'message' => 'Nambari yako ya uthibitisho wa KADILI NET ni: {pin}. Itatumika kwa dakika 5.',
            'recipients' => [
                ['recipient_id' => time(), 'dest_addr' => $phone]
            ]
        ];

        // Generate code locally and save to DB
        return self::generateLocalOTP($phone);
    }

    public static function generateLocalOTP(string $phone, string $purpose = 'registration'): array {
        $code = str_pad(random_int(1000, 9999), 4, '0', STR_PAD_LEFT);
        $expires = date('Y-m-d H:i:s', strtotime('+5 minutes'));

        // Invalidate old codes
        DB::query("UPDATE otp_codes SET used=1 WHERE phone=? AND purpose=? AND used=0", [$phone, $purpose]);

        DB::query(
            "INSERT INTO otp_codes (phone, code, purpose, expires_at) VALUES (?,?,?,?)",
            [$phone, $code, $purpose, $expires]
        );

        // Send via SMS
        $msg = "Nambari yako ya uthibitisho wa KADILI NET ni: $code. Itatumika kwa dakika 5.";
        $smsResult = self::sendRaw($phone, $msg);

        return ['success' => true, 'code' => $code, 'sms' => $smsResult];
    }

    public static function verifyOTP(string $phone, string $code, string $purpose = 'registration'): bool {
        $row = DB::fetch(
            "SELECT id FROM otp_codes WHERE phone=? AND code=? AND purpose=? AND used=0 AND expires_at > NOW() ORDER BY id DESC LIMIT 1",
            [$phone, $code, $purpose]
        );
        if ($row) {
            DB::query("UPDATE otp_codes SET used=1 WHERE id=?", [$row['id']]);
            return true;
        }
        return false;
    }

    public static function sendRaw(string $phone, string $message): array {
        $apiKey = BEEM_SMS_KEY;
        $secret = BEEM_SMS_SECRET;
        $phone = self::formatPhone($phone);

        $payload = [
            'source_addr' => BEEM_SENDER_ID,
            'schedule_time' => '',
            'encoding' => '0',
            'message' => $message,
            'recipients' => [['recipient_id' => time(), 'dest_addr' => $phone]]
        ];

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL            => 'https://apisms.beem.africa/v1/send',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => json_encode($payload),
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
            CURLOPT_HTTPHEADER     => [
                'Content-Type: application/json',
                'Authorization: Basic ' . base64_encode("$apiKey:$secret"),
            ],
        ]);
        $res = curl_exec($ch);
        curl_close($ch);
        return json_decode($res, true) ?? [];
    }

    public static function formatPhone(string $phone): string {
        $phone = preg_replace('/\D/', '', $phone);
        if (strlen($phone) === 9) $phone = '255' . $phone;
        if (substr($phone, 0, 1) === '0') $phone = '255' . substr($phone, 1);
        return $phone;
    }

    public static function checkBalance(): ?float {
        $apiKey = BEEM_SMS_KEY;
        $secret = BEEM_SMS_SECRET;

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL            => 'https://apisms.beem.africa/public/v1/vendors/balance',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_HTTPHEADER     => [
                'Content-Type: application/json',
                'Authorization: Basic ' . base64_encode("$apiKey:$secret"),
            ],
        ]);
        $res = curl_exec($ch);
        curl_close($ch);
        $data = json_decode($res, true);
        return $data['data']['credit_balance'] ?? null;
    }
}
