# GameTracker Server List & Stats Platform

A complete, lightweight PHP & MySQL multi-game server list, live player statistics tracker, dynamic PNG banner generator, and dual-monetization platform.

![Project Screenshot](images/gt1.jpg)

---

## Script Version
v2.0.0

---

## Supported Games
* **Counter-Strike 1.6** (`cs16`)
* **Counter-Strike 2** (`cs2`)
* **Team Fortress 2** (`tf2`)
* **Left 4 Dead 2** (`l4d2`)
* **Rust** (`rust`)
* **ARK: Survival** (`ark`)
* **FiveM (GTA V)** (`fivem`)
* **DayZ** (`dayz`)
* **Garry's Mod** (`gmod`)
* **Valheim** (`valheim`)
* **Squad** (`squad`)
* **TeamSpeak 3** (`ts3`)
* **Minecraft** (`minecraft`)
* **7 Days to Die** (`7dtd`)
* **Project Zomboid** (`zomboid`)
* **ARMA 3** (`arma3`)
* **Palworld** (`palworld`)
* **Terraria** (`terraria`)
* **Unturned** (`unturned`)
* **Insurgency** (`insurgency`)

---

## Key Features
* **20-Game Visual Hub & Dedicated Game Lists (`index.php` & `games/`):** Interactive Games Hub with live online server/player counters per game plus dedicated per-game server pages (`games/cs16_list.php`, `games/rust_list.php`, etc.) with 15-per-page pagination.
* **Live Server Queries & Uptime Tracking:** Queries game servers via UDP/TCP/HTTP/REST, tracks online/offline uptime percentages, records 24-hour player history graphs, and tracks map rotation shares.
* **Visual Add-Server Wizard (`add_server.php`):** Click-to-select 20-game tile grid with automatic default port hints and manual or GeoIP auto-detected country flag selection.
* **Dynamic PNG Banners (`banner.php`):** Generates live embeddable banners in `560x95`, `350x20`, and `160x600` sizes with 6 color themes (`green`, `orange`, `blue`, `red`, `purple`, `gold`), player scale axes, 24h peak counts, and custom fallback map thumbnails (`maps/no_map.png`).
* **Global Top Players Leaderboard (`players.php`):** Tracks cumulative player kills/scores and playtime hours across all listed servers with nickname search and game filters.
* **Top 15 Voted & Community Voting (`top15.php`):** Daily cookie-throttled community voting with automatic ranking.
* **User Accounts & Ownership Verification (`profile.php`):** Member registration, email verification, password reset, custom avatars, Steam ID, Discord username, bio, and hostname-code server ownership claiming (`GT-XXXXXXXX`).
* **Dual Automated Monetization (`boost.php` & `advertise.php`):** Built-in PayPal and Debit/Credit Card checkout for **VIP Server Boosts** (pinned to the top of the server list) and **160x600 Left & Right Sidebar Sponsor Banners** with automatic expiration.
* **Full Admin Control Panel & Discord Webhooks (`admin_panel.php`):** Manage servers, VIP durations, users, PayPal REST API credentials, Discord webhook alerts, pricing tiers, ad slots, footer copyright, Terms of Service, and Privacy Policy.

---

### NOTE
* **LICENSING** I can provide a 3-day trial license so that you can test it fully.
* To get a license, you must send a ticket in our support portal located at https://support.marinkovic.biz

---

## Server Requirements
* **PHP:** 7.4 or newer (PHP 8.0 – 8.3+ recommended)
* **Database:** MySQL 5.7+ or MariaDB 10.3+
* **Required PHP Extensions:**
  * `pdo_mysql` (database storage)
  * `gd` (dynamic PNG banners and user avatars)
  * `curl` (license verification, map thumbnails, and PayPal REST API)
  * `mbstring` & `json` (UTF-8 server/player names and roster storage)
  * `sockets` / outbound UDP & TCP enabled in firewall (for live game server queries)

---

## Step-by-Step Installation

1. **Upload Files**
   * Extract the archive and upload all files to your web directory (e.g., `/public_html/` or `/public_html/tracker/`).
   * Ensure the directory is writable (`0755` for folders, `0644` for PHP files) so the installer can write `config.php` and store avatars/map thumbnails.

2. **Create a MySQL Database**
   * In your hosting control panel (cPanel, aaPanel, Plesk, etc.), create a new **MySQL Database** and **Database User**, and grant the user **All Privileges** on that database.

3. **Run the Web Installer (`install.php`)**
   * Open `https://yourdomain.com` in your browser.
   * Verify that all **Server Environment Checks** show **PASS**.
   * Enter your **Product License Key** (`GT-XXXX-XXXX-XXXX`), **Base URL** (including trailing slash `/`), **MySQL credentials**, and **Administrator account** details (defaults to `Admin` / `admin@admin.com` / `12345`).
   * Optionally enter your **SMTP settings** for user verification and password reset emails.
   * Click **Install GameTracker Now**.

4. **Post-Installation Security (Required)**
   * **Delete `install.php`** from your server immediately after installation completes.
   * Log in with your Admin account, open **Profile**, and change the default password (`12345`) under **Account Security**.

---

## Cron Job Setup (Recommended)
To keep server player counts, 24-hour history charts, map stats, and global player kills updated automatically in the background without slowing down page loads, add a cron job running every **3 to 5 minutes**:

```bash
*/3 * * * * curl -s "https://yourdomain.com" > /dev/null 2>&1
```

*(Replace `https://yourdomain.com` with your actual installation URL.)*

---

## Configuring PayPal, Discord Webhooks & Sidebar Ads
1. Log in as **Admin** and click **ADMIN PANEL** in the top bar.
2. Open the **PayPal & VIP Pricing** tab, enter your PayPal REST API **Client ID** and **Secret Key**, choose **Sandbox** or **Live**, and set your 7/14/30-day VIP prices.
3. Open the **Discord Webhook** tab to enable automated embed notifications when new servers are added or VIP boosts are purchased.
4. Open the **Ad Banners** tab to set 7/14/30-day prices for the Left and Right `160x600` sidebar slots or paste custom banner HTML.
5. Open the **Footer & Legal Pages** tab to customize your footer copyright text, **Terms of Service**, and **Privacy Policy**.

---

## Directory Structure
* `index.php` — 20-game interactive Games Hub with live online counters and top servers
* `list.php` — Paginated master server list, search/filters, and server detail analytics page
* `add_server.php` — Visual 20-game tile selector and server submission page
* `install.php` — One-click setup wizard (delete after installation)
* `config.php` — Generated configuration and MySQL database connection file
* `cron.php` — Background server query, uptime, and player stats updater
* `banner.php` — Dynamic GD PNG server banner generator (`560x95`, `350x20`, `160x600`)
* `top15.php` — Top 15 most voted servers leaderboard
* `players.php` — Global top players leaderboard and search
* `profile.php` — Public user profiles, avatars, and account settings
* `boost.php` & `paypal_boost.php` — VIP server boost checkout and PayPal handler
* `advertise.php` & `paypal_ad.php` — `160x600` ad slot booking and PayPal handler
* `admin_panel.php` — Complete site administration dashboard
* `terms.php` & `privacy.php` — Terms of Service and Privacy Policy pages
* `core/` — Core backend engines (`auth.php`, `servers.php`, `discord.php`, `license_check.php`)
* `includes/` — Shared header, footer, and paginated game list view (`header.php`, `footer.php`, `game_list_view.php`)
* `games/` — Dedicated per-game server list pages (`cs16_list.php` through `insurgency_list.php`)
* `avatars/` & `maps/` — Uploaded user avatars, cached map thumbnails, and custom fallback image (`no_map.png`)
