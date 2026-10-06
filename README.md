# 🧑‍💼 HR Management System

Website + Android-ready (PWA) na HR system — **100% free stack**.

| Bahayano | Teknolohiya | Presyo |
|---|---|---|
| Database | MySQL/MariaDB (XAMPP) | ✅ Libre |
| Backend API | PHP 8 (XAMPP Apache o `php -S`) | ✅ Libre |
| Live data | Google Sheets (published CSV) | ✅ Libre |
| Realtime | Node.js + `ws` (WebSocket) | ✅ Libre |
| Frontend | React + Vite | ✅ Libre |
| Hosting (frontend) | Netlify | ✅ Libre |
| Android app | PWA → APK (PWABuilder) | ✅ Libre |

## ✨ Features

**Employee (may sariling dashboard, login gamit email + password)**
- 🕐 **Clock in/out** na may **selfie photo proof + GPS geofence** — para ma-verify na present talaga
- 🌴 **Leave requests** — submit, at makita ang status (pending/approved/rejected) nang real-time
- 📋 Sariling attendance log at profile

**HR / Admin**
- 👥 **Mag-add ng employees** (auto-generate ng employee_code + password)
- ✅ **Approve/reject leave requests** — may live notification sa employee via WebSocket
- ⏰ Attendance monitoring sa **3 methods**:
  - `app` — selfie + GPS ng employee (may `location_verified` flag at layo mula sa office)
  - `biometric` — CSV import mula sa fingerprint/face device (ZKTeco, Realand, atbp.)
  - `manual` — HR ang nag-mark mismo
- 📈 **Live Sheet** — live na data mula sa Google Spreadsheet (auto-refresh 30s + WS push)

**Realtime (WebSocket)**
- Bagong leave request → agad lumalabas sa HR dashboard
- Leave approved/rejected → agad nagpop-up sa employee
- Attendance updates → live feed

---

## 🚀 Local Setup (XAMPP)

### 1. Database
```bash
C:/xampp/mysql/bin/mysql.exe -u root < database/schema.sql
C:/xampp/mysql/bin/mysql.exe -u root < database/seed.sql
```

### 2. Backend API (port 8080)
```bash
cd backend
C:/xampp/php/php.exe -S 127.0.0.1:8080 router.php
```
*Kung Apache naman: kopyahin ang `backend/` papuntang `C:/xampp/htdocs/hr-api/` at i-access sa `http://localhost/hr-api/api/...` (may `.htaccess` na kasama).*

### 3. WebSocket server (port 8081)
```bash
cd websocket
npm install
node server.js
```

### 4. Frontend (port 5173)
```bash
cd frontend
npm install
npm run dev
```

Buksan: **http://localhost:5173**

### 🔑 Demo accounts

| Email | Password | Role |
|---|---|---|
| `hr@company.com` | `admin123` | HR/Admin |
| `juan@company.com` | `emp123` | Employee |
| `ana@company.com` | `emp123` | Employee |

> ⚠️ Palitan ang mga password na ito bago mag-deploy.

---

## 📊 Google Sheets Live Data (libre, walang API key)

1. Buksan ang Google Sheet → **File → Share → Publish to web**
2. Piliin ang sheet → **Publish** → kopyahin ang **CSV** link
3. Idagdag sa `backend/.env`:
   ```
   SHEET_CSV_URL=https://docs.google.com/spreadsheets/d/e/.../pub?output=csv
   ```
4. Titingnan sa dashboard: **Live Sheet** page (auto-refresh bawat 30s)

---

## ☁️ Deployment

### Frontend → Netlify (libre)
1. I-push ang repo sa GitHub
2. Netlify → **Add new site → Import an existing project**
3. Settings:
   - **Build command:** `npm run build`
   - **Publish directory:** `frontend/dist` *(o ilagay ang netlify.toml sa repo root — naka-configure na)*
4. **Site settings → Environment variables:**
   ```
   VITE_API_URL=https://YOUR-SERVER.com/api
   VITE_WS_URL=wss://YOUR-SERVER.com:8081
   ```
5. Idagdag ang Netlify URL sa `backend/.env` → `CORS_ORIGINS`

### Backend + WebSocket + MySQL → sariling server
Ang XAMPP stack ay hindi kayang tumakbo sa Netlify (static lang ito). Libreng options:

| Provider | Para saan | Free tier |
|---|---|---|
| **Oracle Cloud Always Free** | Buong VPS (XAMPP/LAMP + Node) | ✅ Libre habang buhay |
| **Render.com** | WebSocket server (Node) | ✅ Free (sleeps after idle) |
| **Railway / Glitch** | WebSocket server | ✅ Free trial/limited |
| **Your own PC + ngrok** | Dev/demo testing | ✅ Libre |

Para sa **production**, pinaka-practical: isang murang VPS (Oracle Cloud free o DigitalOcean ~$6/mo) na naka-install ng:
- Apache + PHP + MySQL (XAMPP/LAMP)
- Node.js para sa `websocket/server.js` (pamamagitan ng **pm2** para auto-restart)
- SSL via **Let's Encrypt** (libre) — kailangan ito para sa `wss://` at `https://`

### Android App (libre)
Ang app ay **PWA** na — installable sa Android:
1. Sa Chrome sa phone → **⋮ → Add to Home screen**
2. O i-generate ang APK: **[pwabuilder.com](https://www.pwabuilder.com)** → i-paste ang Netlify URL → download ang `.apk`/`.aab`

Lahat ng feature (camera selfie, GPS, notifications, offline shell) ay gumagana bilang PWA.

---

## 🗂️ Struktura

```
├── database/          schema.sql + seed.sql (MySQL)
├── backend/           PHP REST API (router.php, controllers, app helpers)
│   ├── .env.example   ← kopyahin bilang .env
│   └── uploads/       selfie photos ng attendance
├── frontend/          React + Vite (Netlify deploy target)
├── websocket/         Node.js ws server (live updates)
├── netlify.toml       Netlify build + SPA redirects
└── tools/             icon generator
```

## 🔐 Security Notes

- Passwords → **bcrypt** (`password_hash`)
- API auth → **JWT (HS256)**, 12 oras ang valididad
- Lahat ng SQL → **PDO prepared statements** (walang injection)
- Selfie photos → `uploads/`, naka-restrict sa image extensions lang, 6MB max
- Geofence — pwedeng i-set sa `REQUIRE_GEOFENCE=true` para **i-reject** ang clock-in sa labas ng office
- Palitan ang `JWT_SECRET` sa `backend/.env` bago i-deploy
