<?php
// ============================================
// KADILI NET - MikroTik Helper (v4)
// Full deployment: bridge, DHCP, hotspot, NAT
// PHP 8.3 Compatible — Graceful error handling
// ============================================

declare(strict_types=1);

require_once __DIR__ . '/../vendor/routeros/Client.php';

use RouterOS\Client;
use RouterOS\Query;

class MikroTik
{
    private const CONNECT_TIMEOUT = 8;
    private const SOCKET_TIMEOUT  = 15;

    // ──────────────────────────────────────────
    // Connection
    // ──────────────────────────────────────────

    public static function connect(array $router): ?Client
    {
        $host = trim((string)($router['host'] ?? ''));
        $port = (int)($router['port'] ?? 8728);
        $user = (string)($router['username'] ?? 'admin');
        $pass = (string)($router['password'] ?? '');

        if ($host === '') return null;

        try {
            return new Client([
                'host'           => $host,
                'user'           => $user,
                'pass'           => $pass,
                'port'           => $port,
                'timeout'        => self::CONNECT_TIMEOUT,
                'socket_timeout' => self::SOCKET_TIMEOUT,
            ]);
        } catch (\Throwable $e) {
            error_log("MikroTik::connect [{$host}:{$port}] — " . $e->getMessage());
            return null;
        }
    }

    public static function testConnection(array $router): bool
    {
        $client = self::connect($router);
        if ($client === null) return false;

        try {
            $res = $client->qr(new Query('/system/identity/print'));
            return is_array($res) && $res !== [];
        } catch (\Throwable $e) {
            return false;
        }
    }

    public static function getRouterIdentity(array $router): ?string
    {
        $client = self::connect($router);
        if ($client === null) return null;

        try {
            $res = $client->qr(new Query('/system/identity/print'));
            return isset($res[0]['name']) ? (string)$res[0]['name'] : null;
        } catch (\Throwable $e) {
            return null;
        }
    }

    public static function getResourceInfo(array $router): array
    {
        $client = self::connect($router);
        if ($client === null) return [];

        try {
            $res = $client->qr(new Query('/system/resource/print'));
            return (isset($res[0]) && is_array($res[0])) ? $res[0] : [];
        } catch (\Throwable $e) {
            return [];
        }
    }

    // ──────────────────────────────────────────
    // Step 2: Topology Scanning
    // ──────────────────────────────────────────

    public static function scanInterfaces(array $router): array
    {
        $client = self::connect($router);
        if ($client === null) return [];

        try {
            $all = $client->qr(new Query('/interface/print'));
            if (!is_array($all)) return [];

            $result = [];
            foreach ($all as $iface) {
                $name = $iface['name'] ?? '';
                $type = $iface['type'] ?? 'ether';

                // Skip virtual interfaces
                if (in_array($type, ['bridge', 'vpn', 'sstp', 'l2tp', 'pppoe-out', 'loopback'], true)) continue;
                if (str_starts_with($name, 'kadili') || str_starts_with($name, 'vpn')) continue;

                $result[] = [
                    'name'     => $name,
                    'type'     => $type,
                    'mac'      => $iface['mac-address'] ?? '',
                    'comment'  => $iface['comment'] ?? '',
                    'running'  => ($iface['running'] ?? 'false') === 'true',
                    'disabled' => ($iface['disabled'] ?? 'false') === 'true',
                ];
            }
            return $result;
        } catch (\Throwable $e) {
            error_log('MikroTik::scanInterfaces — ' . $e->getMessage());
            return [];
        }
    }

    // ──────────────────────────────────────────
    // Step 3: Auto Deployment
    // ──────────────────────────────────────────

    public static function deployHotspot(
        array  $router,
        string $wanIface,
        array  $lanIfaces,
        string $bridgeName,
        string $gatewayIp,
        string $subnetSize,
        string $hotspotDns,
        string $businessName
    ): array {
        $log    = [];
        $client = self::connect($router);
        if ($client === null) {
            return ['success' => false, 'log' => ['❌ Cannot connect to router API.']];
        }

        try {
            $prefix   = (int)ltrim($subnetSize, '/');
            $network  = self::ipAndMask($gatewayIp, $prefix);
            $poolStart = self::calcPoolStart($gatewayIp, $prefix);
            $poolEnd   = self::calcPoolEnd($gatewayIp, $prefix);
            $poolName  = 'kadili-pool';
            $dhcpName  = 'kadili-dhcp';

            // 1. Create Bridge
            $log[] = '🔨 Creating bridge ' . $bridgeName . '...';
            self::runSafe($client, (new Query('/interface/bridge/add'))
                ->equal('name', $bridgeName)
                ->equal('comment', 'KADILI NET Bridge'));

            // 2. Add LAN ports to bridge
            foreach ($lanIfaces as $iface) {
                $log[] = '🔗 Adding ' . $iface . ' to bridge...';
                self::runSafe($client, (new Query('/interface/bridge/port/add'))
                    ->equal('interface', $iface)
                    ->equal('bridge', $bridgeName));
            }

            // 3. Assign Gateway IP
            $log[] = '🌐 Assigning gateway IP ' . $gatewayIp . $subnetSize . '...';
            self::runSafe($client, (new Query('/ip/address/add'))
                ->equal('address', $gatewayIp . $subnetSize)
                ->equal('interface', $bridgeName)
                ->equal('comment', 'KADILI Gateway'));

            // 4. Create IP Pool
            $log[] = '📦 Creating IP pool (' . $poolStart . '-' . $poolEnd . ')...';
            self::runSafe($client, (new Query('/ip/pool/add'))
                ->equal('name', $poolName)
                ->equal('ranges', $poolStart . '-' . $poolEnd));

            // 5. DHCP Network
            $log[] = '📡 Setting up DHCP network...';
            self::runSafe($client, (new Query('/ip/dhcp-server/network/add'))
                ->equal('address', $network . $subnetSize)
                ->equal('gateway', $gatewayIp)
                ->equal('dns-server', $gatewayIp . ',8.8.8.8')
                ->equal('comment', 'KADILI DHCP Network'));

            // 6. DHCP Server
            self::runSafe($client, (new Query('/ip/dhcp-server/add'))
                ->equal('name', $dhcpName)
                ->equal('interface', $bridgeName)
                ->equal('address-pool', $poolName)
                ->equal('disabled', 'no')
                ->equal('lease-time', '1h'));

            // 7. NAT
            $log[] = '🔄 Setting up NAT masquerade on ' . $wanIface . '...';
            self::runSafe($client, (new Query('/ip/firewall/nat/add'))
                ->equal('chain', 'srcnat')
                ->equal('out-interface', $wanIface)
                ->equal('action', 'masquerade')
                ->equal('comment', 'KADILI NAT'));

            // 8. Hotspot Profile
            $log[] = '☁️ Setting up hotspot profile...';
            self::runSafe($client, (new Query('/ip/hotspot/profile/add'))
                ->equal('name', 'kadili-hsprof')
                ->equal('hotspot-address', $gatewayIp)
                ->equal('dns-name', $hotspotDns)
                ->equal('html-directory', 'kadili-portal')
                ->equal('login-by', 'http-chap,http-pap')
                ->equal('use-radius', 'no'));

            // 9. Hotspot Server
            $log[] = '🔥 Starting hotspot server...';
            self::runSafe($client, (new Query('/ip/hotspot/add'))
                ->equal('name', 'kadili-hotspot')
                ->equal('interface', $bridgeName)
                ->equal('profile', 'kadili-hsprof')
                ->equal('addresses-per-mac', '2')
                ->equal('disabled', 'no'));

            // 10. Default User Profile
            self::runSafe($client, (new Query('/ip/hotspot/user/profile/add'))
                ->equal('name', 'default')
                ->equal('rate-limit', '5M/5M')
                ->equal('shared-users', '1'));

            // 11. Walled Garden
            $log[] = '🏰 Setting up walled garden...';
            foreach (['palmpesa.drmlelwa.co.tz', 'apisms.beem.africa', 'kadilihotspot.online'] as $host) {
                self::runSafe($client, (new Query('/ip/hotspot/walled-garden/add'))
                    ->equal('dst-host', $host)
                    ->equal('action', 'allow'));
            }

            // 12. DNS
            $log[] = '🌍 Configuring DNS for ' . $hotspotDns . '...';
            self::runSafe($client, (new Query('/ip/dns/set'))->equal('allow-remote-requests', 'yes'));
            self::runSafe($client, (new Query('/ip/dns/static/add'))
                ->equal('name', $hotspotDns)
                ->equal('address', $gatewayIp)
                ->equal('comment', 'KADILI Captive DNS'));

            $log[] = '✅ Deployment complete! Hotspot is live on ' . $bridgeName . '.';
            return ['success' => true, 'log' => $log];

        } catch (\Throwable $e) {
            $log[] = '❌ Error: ' . $e->getMessage();
            error_log('MikroTik::deployHotspot — ' . $e->getMessage());
            return ['success' => false, 'log' => $log];
        }
    }

    private static function runSafe(Client $client, Query $query): void
    {
        try {
            $client->query($query)->read();
        } catch (\Throwable $e) {
            error_log('MikroTik::runSafe — ' . $e->getMessage());
        }
    }

    // ──────────────────────────────────────────
    // Hotspot Users & Vouchers
    // ──────────────────────────────────────────

    public static function addHotspotUser(array $router, array $userData): bool
    {
        $client = self::connect($router);
        if ($client === null) return false;

        try {
            $query = (new Query('/ip/hotspot/user/add'))
                ->equal('name',     (string)($userData['username'] ?? ''))
                ->equal('password', (string)($userData['password'] ?? ''))
                ->equal('profile',  (string)($userData['profile']  ?? 'default'))
                ->equal('comment',  (string)($userData['comment']  ?? 'KADILI NET'));

            if (!empty($userData['limit-uptime'])) {
                $query->equal('limit-uptime', (string)$userData['limit-uptime']);
            }

            $client->query($query)->read();
            return true;
        } catch (\Throwable $e) {
            return false;
        }
    }

    public static function removeHotspotUser(array $router, string $username): bool
    {
        $client = self::connect($router);
        if ($client === null) return false;

        try {
            $users = $client->query('/ip/hotspot/user/print', [['name', '=', $username]])->read();
            if (empty($users) || !isset($users[0]['.id'])) return false;

            $client->query(
                (new Query('/ip/hotspot/user/remove'))->equal('.id', (string)$users[0]['.id'])
            )->read();

            return true;
        } catch (\Throwable $e) {
            return false;
        }
    }

    public static function getActiveUsers(array $router): array
    {
        $client = self::connect($router);
        if ($client === null) return [];

        try {
            $res = $client->qr(new Query('/ip/hotspot/active/print'));
            return is_array($res) ? $res : [];
        } catch (\Throwable $e) {
            return [];
        }
    }

    public static function getProfiles(array $router): array
    {
        $client = self::connect($router);
        if ($client === null) return [];

        try {
            $res = $client->qr(new Query('/ip/hotspot/user/profile/print'));
            return is_array($res) ? $res : [];
        } catch (\Throwable $e) {
            return [];
        }
    }

    public static function addVoucher(array $router, string $code, string $profile, int $durationSeconds): bool
    {
        $client = self::connect($router);
        if ($client === null) return false;

        try {
            $hours = max(1, (int)ceil($durationSeconds / 3600));
            $client->query(
                (new Query('/ip/hotspot/user/add'))
                    ->equal('name',         $code)
                    ->equal('password',     $code)
                    ->equal('profile',      $profile)
                    ->equal('limit-uptime', $hours . 'h')
                    ->equal('comment',      'KADILI NET Voucher')
            )->read();
            return true;
        } catch (\Throwable $e) {
            return false;
        }
    }

    public static function loginUser(array $router, string $ip, string $username, string $password): bool
    {
        $client = self::connect($router);
        if ($client === null) return false;

        try {
            $client->query(
                (new Query('/ip/hotspot/active/login'))
                    ->equal('ip',       $ip)
                    ->equal('user',     $username)
                    ->equal('password', $password)
            )->read();
            return true;
        } catch (\Throwable $e) {
            return false;
        }
    }

    // ──────────────────────────────────────────
    // Subnet Utilities
    // ──────────────────────────────────────────

    private static function ipAndMask(string $ip, int $prefix): string
    {
        return long2ip(ip2long($ip) & (0xffffffff << (32 - $prefix)));
    }

    private static function calcPoolStart(string $ip, int $prefix): string
    {
        $net = ip2long($ip) & (0xffffffff << (32 - $prefix));
        return long2ip($net + 2);
    }

    private static function calcPoolEnd(string $ip, int $prefix): string
    {
        $net  = ip2long($ip) & (0xffffffff << (32 - $prefix));
        $mask = ~(0xffffffff << (32 - $prefix)) & 0xffffffff;
        return long2ip(($net | $mask) - 1);
    }
}
