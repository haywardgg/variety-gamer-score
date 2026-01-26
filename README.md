<i>This is a cut down version (v1.0) of the one hosted at VarietyGamer.win <br>
(i.e no database support, no challenge code feature or category selection, etc)</i>

# 🎮✨ Variety Gamer Score

Selects 50 of the Top Played Steam games at random and lets you test your Variety Gamer score!

🔗 <strong>**Live demo:**</strong> https://varietygamer.win/

---

## 🌟 What’s inside

- ⚡ **Fast, simple PHP** — no framework required.
- 🧠 **Steam Top list** generated locally via `steamtopimport.php` (or the `api/steam-top100.php` endpoint).
- 🖼️ **Image cache** stored in `assets/cache/steam-images/` inside this folder.

---

## 🚀 Quick start

1. **Serve the repo** with PHP (Apache/Nginx + PHP-FPM works great).
2. Generate data by running `php steamtopimport.php` (or visit `api/steam-top100.php`) to populate `data/steam-top100.json`.
3. Visit **`index.php`** in your browser (a sample `data/steam-top100.json` is included for offline use).

---

## 🧰 Dependencies (Ubuntu)

To run the site and scorecard export without errors, install:

- **PHP 8+** (CLI + web SAPI)
- **PHP mbstring extension** (scorecard tagline rendering)
- **PHP Imagick extension** (thumbnail + scorecard image processing)
- **ImageMagick** (Imagick backend)

### ✅ Ubuntu install commands

```bash
sudo apt update
sudo apt install -y \
  php \
  php-cli \
  php-curl \
  php-mbstring \
  php-imagick \
  imagemagick
sudo systemctl restart php8.3-fpm # or php8.4-fpm / php8.2-fpm / php8.1-fpm depending on your setup
sudo nginx -t && sudo systemctl reload nginx
```

> If you are using Apache, make sure `libapache2-mod-php` is installed or configure PHP-FPM for Nginx.

---

## 🔐 Securing server files (important!)

Please lock things down so the site stays safe and stable:

### ✅ Recommended permissions

- **Directories:** `755`
- **Public files:** `644`
- **Secret files (API keys):** `600`

### ✅ Writable locations (web server user only)

The web server needs **read access** to:

- `data/steam-top100.json`
- `assets/cache/steam-images/`

### ✅ Protect sensitive files

- Keep **API keys outside the web root** (as in `/var/www/secrets/apikeys.php`).
- If you must store secrets in the repo, **deny web access** via server config.
- Consider **password-protecting `build.php`** or limiting it by IP.

---

## 📁 Project map

- `index.php` → main variety score page
- `scorecard.php` → score breakdown view
- `data/steam-top100.json` → Steam Top list used by the app
- `api/steam-top100.php` → local API endpoint for the top list
- `steamtopimport.php` → generates `data/steam-top100.json` and caches images
- `assets/cache/steam-images/` → image cache used by the importer
- `assets/` → CSS, images, scorecard assets

---

## 🤗 Shoutout to all my beta testers

My Girlfriend & My family, [Assassina San](https://www.youtube.com/@ASSASSINASAN), Nomadic, [GhostShadow](https://www.twitch.tv/ghostshadow_plays) ... 

## 📜 License

This project is source-available.<br/>
Free for non-commercial use with attribution.<br/>
Commercial use requires permission.

## Commercial License

Commercial licenses are available.
Contact: hayward@proton.me or GitHub issues.

## Screenshots

<img width="545" height="673" alt="VARIETY gamer score" src="https://github.com/user-attachments/assets/eccd0ae4-549f-4c0c-8721-49ffd1ca9839" />
<img width="538" height="443" alt="VARIETY gamer score" src="https://github.com/user-attachments/assets/a9dfc2d8-64ba-42c0-8c15-a19a14668c63" />
<img width="300" height="450" alt="VARIETY gamer score" src="https://github.com/user-attachments/assets/46032b86-a3d6-4184-805d-c4d93abc102c" />
<img alt="VARIETY gamer score" src="https://github.com/user-attachments/assets/6236b8aa-acd0-4462-a241-837638dd5486" />



