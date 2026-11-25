# Immigration Queue Management System (IQMS)

A complete, responsive, role-based queue management system for an immigration office, built with **pure PHP 8.2 (OOP) + PDO + MySQL + Bootstrap 5 + AJAX (Fetch) + SweetAlert2 + Font Awesome + Chart.js**. No frameworks (no Laravel / CodeIgniter).

---

## 1. Features

| Role | Capabilities |
|---|---|
| **Admin** | Dashboard with statistics & charts · manage officer/admin accounts (CRUD) · manage immigration services · manage counters · view all applicants · view/monitor all queues · issue queue on behalf · daily & monthly reports · CSV export · system activity logs · settings · **reset day queue numbers** |
| **Officer** | Personal dashboard · register new applicants · search by passport / ID number · generate queue numbers · assign to counters · **Call Next / Recall / Mark Serving / Complete / Skip / Cancel** · queue history |
| **Applicant (public, no account)** | Enter name + passport/ID + service → instant queue ticket · check current status, position & estimated wait · live public display screen (auto-refreshing AJAX) |

Queue numbers are auto-generated per service prefix (`P001`, `V001`, `W001`…), unique per service per operating date, and reset daily (open queues are closed on day reset).

---

## 2. Folder structure

```
Immigration_Queue/
├── index.php                  # entry: routes to admin or officer dashboard (or login)
├── logout.php                 # secure logout
├── database.sql               # complete schema + seed data (import into MySQL)
├── config/
│   └── config.php             # DB credentials, session bootstrap, autoloads core classes
├── core/
│   ├── Database.php           # PDO singleton + prepared-statement helpers
│   ├── Security.php           # CSRF, XSS-escape, JSON APIs, RBAC guards, audit logging
│   ├── Auth.php               # password_hash/verify, session login/logout, role checks
│   └── QueueManager.php      # queue numbering engine + all status transitions
├── parts/
│   ├── layout-top.php         # shared Bootstrap sidebar/topbar layout (admin & officer)
│   └── layout-bottom.php
├── admin/                     # role: admin
│   ├── dashboard.php  officers.php  services.php  counters.php
│   ├── queues.php  queue-detail.php  applicants.php
│   ├── reports.php  activity.php  settings.php
├── officer/                   # role: officer
│   ├── dashboard.php  new-applicant.php  queue-ops.php  my-queues.php
├── public/                    # public pages (no login required)
│   ├── login.php              # staff sign-in
│   ├── ticket.php             # applicant queue ticket
│   └── display.php            # office TV / monitor queue display (auto-refresh)
├── api/                       # JSON AJAX endpoints
│   ├── queue-issue.php  queue-status.php  display-feed.php
│   ├── queue-action.php  register-applicant.php  applicants.php  applicants-all.php
│   ├── queues.php  queue-logs.php  stats.php  reports.php
│   ├── officers.php  users-list.php  services.php  counters.php
│   ├── settings.php  activity.php  reset-day.php
└── assets/
    ├── css/style.css
    └── js/app.js              # shared IQMS helpers (fetch+CSRF, SweetAlert, sidebar)
```

---

## 3. Database schema

Import `database.sql` into MySQL 8 / MariaDB:

```
users               (PK id; UK username; role admin|officer; bcrypt password)
applicants          (PK id; UK passport_number; UK id_number; FK registered_by_user)
immigration_services(PK id; UK code(2) queue prefix; UK name; est_minutes; active)
counters            (PK id; UK counter_number; status available|busy|closed)
queues              (PK id; UNIQUE(service_id, operating_date, queue_number);
                     FK service/applicant/counter/officer;
                     status waiting|called|serving|completed|skipped|cancelled)
queue_logs          (PK id; FK queue_id, user_id; action + details + timestamp)
activity_logs       (PK id; user, action, details, IP, timestamp)
system_settings     (PK setting_key; value)
```

Seed data: 1 admin + 3 officers, 8 services, 6 counters, default settings.

---

## 4. Install (XAMPP, local)

1. Copy the project to `C:\xampp\htdocs\Immigration_Queue` (already done here).
2. Start **Apache** and **MySQL** from the XAMPP Control Panel.
   - ⚠️ If a separate **MySQL 8.0 service** is already holding port 3306, start that one instead of the XAMPP MariaDB service, or stop the Windows `MySQL80` service first.
3. Import the schema (pick your credentials):
   ```
   mysql -u root -p < C:\xampp\htdocs\Immigration_Queue\database.sql
   ```
   or via phpMyAdmin: import `database.sql` → creates database `iqms`.
4. Edit `config/config.php` to match your MySQL credentials:
   ```php
   define('DB_HOST', '127.0.0.1');
   define('DB_PORT', '3306');
   define('DB_NAME', 'iqms');
   define('DB_USER', 'root');
   define('DB_PASS', '...');
   ```
   (On stock XAMPP MariaDB the password is normally empty.)
5. Open `http://localhost/Immigration_Queue/`.

### Default logins

| Account | Username | Password |
|---|---|---|
| Admin | `admin` | `Admin@123` |
| Officer | `officer1` / `officer2` / `officer3` | `Officer@123` |

> **Change these immediately** via *Admin → Users → edit → new password*.

---

## 5. Key pages

| URL | Description |
|---|---|
| `/Immigration_Queue/public/login.php` | Staff sign-in |
| `/Immigration_Queue/admin/dashboard.php` | Admin dashboard (stat cards + 4 Chart.js charts) |
| `/Immigration_Queue/officer/queue-ops.php` | **Queue console** — Call Next / Recall / Serving / Complete / Skip / Cancel |
| `/Immigration_Queue/public/ticket.php` | Applicant queue ticket (public, no account) |
| `/Immigration_Queue/public/display.php` | Office display screen (auto-refreshes; TV-ready, no PII shown) |
| `/Immigration_Queue/admin/reports.php` | 6 report types, each exportable to CSV |

---

## 6. Security implemented

- `password_hash()` (bcrypt) / `password_verify()` for all credentials
- PHP session auth with `session_regenerate_id(true)` on login; secure logout destroys session + cookie
- **Role-based access control** — every page and API endpoint guards its role (`admin`, `officer`, public)
- **CSRF** — session token checked on all POSTs (forms via hidden field; AJAX via `X-CSRF-Token` header)
- **SQL injection** — 100% PDO prepared statements (native, `EMULATE_PREPARES=false`)
- **XSS** — all output escaped with `htmlspecialchars(ENT_QUOTES)`
- **Public display & ticket screens expose no personal data** (only queue numbers, counters, statuses)
- Audit trail: `activity_logs` (logins, CRUD, resets, settings) + `queue_logs` (every queue transition)
- Duplicate queue numbers impossible: `UNIQUE(service_id, operating_date, queue_number)` + next-serial lookup
- Daily 5-ticket limit per identification number to prevent abuse

---

## 7. Queue workflow (state machine)

```
issue ──► waiting ──► called ──► serving ──► completed
              │           │          │
              │           ├──────────┴──► (recall: serving → called)
              │           │
              ├──► skipped
              └──► cancelled   (any open state; frees counter if assigned)
```

- `call_next` picks the **oldest waiting** queue for the service and binds it to the chosen counter (counter → busy).
- `complete` / `cancel` release the counter (→ available).
- **Day reset** (`api/reset-day.php`, admin): closes all open queues as cancelled; new-day numbering automatically starts at `001` because serials are scoped to `operating_date`.

---

## 8. Testing performed

- `php -l` lint: **all files pass**.
- **24-step CLI smoke test** (`.agnes\smoke_test.php`): issue P001/P002/P003/V001/W001 → duplicate prevention → call next → counter busy → mark serving → recall → complete → counter freed → skip → cancel+reason → queue_logs written → status counts → display data → day reset → reports → average wait/service → officer personal stats. **All pass.**
- **HTTP test** (`.agnes\http_test.php`) against local Apache: public pages 200, `display-feed` returns 8 services, public `queue-issue` issues P001, `queue-status` returns waiting (no PII), protected APIs return 401 without session, `admin/dashboard.php` 302→login, login form carries CSRF token.

---

## 9. Customization

- **Services**: add via *Admin → Services* (2-letter code = queue prefix).
- **Counters**: add via *Admin → Counters*.
- **Display refresh rate / office name / default service minutes**: *Admin → Settings* (stored in `system_settings`).
- **Timezone**: `config/config.php` → `date_default_timezone_set(...)` (affects "today" boundary for daily reset).
