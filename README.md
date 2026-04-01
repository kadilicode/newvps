# KADILI NET v4 — Hotspot SaaS

## What's New in v4
- ✅ **3-Step Router Wizard** (like XenFi) — auto deployment of bridge, DHCP, NAT, hotspot, DNS
- ✅ **One Router Per Account** — enforced at DB + UI level
- ✅ **Beautiful Captive Portal** — mobile-first, tabbed (Buy Package + Voucher)
- ✅ **Portal Customization** — brand color, WiFi name, footer text
- ✅ **Auto Heartbeat API** — MikroTik pings back, dashboard polls for connection
- ✅ All previous features: Beem SMS, PalmPesa, Vouchers, Packages, Withdrawals

---

## Fresh Install

### 1. Upload files to your server (PHP 8.3 + MySQL)
```
/var/www/html/kadili_net/
```

### 2. Import database
```sql
mysql -u root -p < database.sql
```

### 3. Configure `includes/config.php`
- Set `DB_HOST`, `DB_NAME`, `DB_USER`, `DB_PASS`
- Set `SITE_URL` to your domain
- Set `PALMPESA_API_KEY` from your PalmPesa account
- Set `BEEM_SMS_KEY`, `BEEM_SMS_SECRET`, `BEEM_OTP_KEY` from Beem Africa

### 4. Set admin password
Visit: `https://yourdomain.com/admin/reset_password.php?key=KadiliReset2024`

### 5. Set folder permissions
```bash
chmod -R 755 /var/www/html/kadili_net/
```

---

## Upgrading from v3

### Run database update
```sql
mysql -u root -p kadili_net < database_update.sql
```

### Replace files
Upload all new files, keeping your `includes/config.php` unchanged.

---

## Router Setup Wizard (How It Works)

### Step 1 — Remote Access
1. Reseller logs in → Routers → Start Setup Wizard
2. Enter MikroTik admin username/password and IP address
3. System generates a one-click script:
   ```
   /tool fetch url="https://kadilihotspot.online/api/router_heartbeat.php?token=XXX&router_id=YYY" mode=https; :delay 1s; /ip service set api disabled=no address=0.0.0.0/0
   ```
4. Reseller pastes this into MikroTik terminal (Winbox → New Terminal)
5. Dashboard polls every 5 seconds for connection

### Step 2 — Port Configuration
- System scans all MikroTik interfaces via API
- Reseller selects WAN port (ether1 = ISP connection)
- Configure gateway IP and subnet size (dropdown)
- All other ports go to bridge automatically

### Step 3 — Deploy
One click deploys:
- Bridge interface (`KADILI-BRIDGE`)
- IP address on bridge
- IP Pool + DHCP Server
- NAT masquerade rule
- Hotspot server profile + server
- DNS redirect (`wifi.com → gateway IP`)
- Walled Garden (PalmPesa + Beem domains)
- Default user profile (5M/5M speed limit)

---

## Captive Portal

Portal URL format:
```
https://yourdomain.com/portal/?r={reseller_id}&router={router_id}
```

### Tabs
- **Buy Package** — select plan, enter phone, STK Push via PalmPesa
- **Use Voucher** — enter voucher code to login

### Customize (Reseller Settings → Portal)
- Brand color, background color
- WiFi name displayed at top
- Footer text
- Show/hide each tab

---

## MikroTik Prerequisites
Before running the wizard, the MikroTik must have:
- Internet on **ether1** (DHCP Client from ISP — do NOT remove this)
- Admin credentials (username + password)
- API port accessible (wizard enables it automatically)

If you get `not allowed by device mode`, run in terminal:
```
/system/device-mode/update mode=advanced
```

---

## File Structure
```
kadili_net/
├── admin/          — Admin panel (manage resellers, transactions)
├── api/            — Callbacks (PalmPesa, router heartbeat, OTP)
├── cron/           — check_expiry.php (run every 5 minutes)
├── includes/       — config, db, auth, mikrotik, palmpesa, beem
├── portal/         — Captive portal (customer-facing)
├── reseller/       — Reseller dashboard
├── vendor/         — RouterOS API Client
├── database.sql    — Fresh install schema
└── database_update.sql — Upgrade from v3
```

---

## Cron Job (Required)
```
*/5 * * * * php /var/www/html/kadili_net/cron/check_expiry.php
```

---

## Support
Domain: kadilihotspot.online
# newvps
