# Mitra Business Suite

> Self-hosted IT business platform — Support Ticketing · Point of Sale · Invoicing

[![PHP](https://img.shields.io/badge/PHP-8.0%2B-777BB4?style=flat-square&logo=php&logoColor=white)](https://php.net)
[![MariaDB](https://img.shields.io/badge/MariaDB-10.3%2B-003545?style=flat-square&logo=mariadb&logoColor=white)](https://mariadb.org)
[![Apache](https://img.shields.io/badge/Apache-2.4%2B-D22128?style=flat-square&logo=apache&logoColor=white)](https://httpd.apache.org)
[![License](https://img.shields.io/badge/License-Proprietary-000000?style=flat-square)](LICENSE)

Mitra is a fully integrated business management platform built for IT service companies. It combines a helpdesk ticketing system, a point-of-sale terminal, and a professional invoicing module — all in one self-hosted PHP application with no external dependencies.

Runs on shared cPanel hosting, a Raspberry Pi, or any Linux server with Apache + PHP + MySQL/MariaDB.

---

## Features

### 🎫 Support Ticketing
- Client portal with self-registration
- Full ticket lifecycle — Open → Pending → Resolved → Closed
- Priority levels: Low, Medium, High, Critical
- Agent assignment and department routing
- Internal notes (staff-only comments)
- File attachments on tickets and replies
- Email notifications (optional)

### 🛒 Point of Sale
- Touch-friendly POS terminal with product grid
- Category filtering and product search
- Live cart with quantity controls
- Cash change calculator
- 6 payment methods: Cash, Debit, Credit, e-Transfer, Cheque, Other
- Link sales directly to support tickets
- Thermal-style printable receipts
- Automatic stock deduction

### 🧾 Invoicing
- Line item builder with product catalog quick-add
- Per-line tax rates and discount support (fixed or %)
- Partial payment recording and balance tracking
- Invoice status: Draft → Sent → Paid / Partial / Overdue / Void
- Auto-status updates when balance reaches zero
- Print-ready / PDF-friendly invoice view
- Client portal for invoice viewing and downloading
- Link invoices to support tickets

### 🎨 Branding & Document Design
- Company logo upload (PNG, JPG, SVG — up to 2MB)
- 9 built-in color scheme presets
- Custom hex color pickers for header, text, and accent
- 6 document font choices
- Live preview — changes reflect instantly
- Applied to all invoices, quotes, and receipts

### 📦 Products & Services Catalog
- Product, Service, and Labour item types
- SKU, category, pricing, cost, and tax rate per item
- Optional stock quantity tracking with low-stock alerts

### 💰 Financial Reports
- Monthly revenue charts (invoiced + POS separately)
- Payment method breakdown
- Top clients by revenue
- Top products by sales
- Outstanding invoice tracker

### 👥 User Management
- Three roles: Admin, Agent, Client
- Add, disable, or delete users
- Per-user company, phone, and profile

---

## Screenshots

> _Add screenshots of your installation here_

---

## Requirements

| Requirement | Minimum | Recommended |
|-------------|---------|-------------|
| PHP | 7.4 | 8.1+ |
| MySQL / MariaDB | 5.7 / 10.3 | 8.0 / 10.6+ |
| Web Server | Apache 2.4 | Apache 2.4 with mod_rewrite |
| RAM | 512MB | 1GB+ |
| Disk | 100MB | 1GB+ (for uploads) |

**Required PHP extensions:** `pdo`, `pdo_mysql`, `json`, `mbstring`, `fileinfo`

---

## Installation

### Option A — Web Installer (Recommended)

1. **Download** and extract the repository
2. **Upload** the `mitra/` folder to your web server via FTP or File Manager
3. **Set permissions:**
   ```bash
   chmod 755 uploads/
   chmod 755 includes/
   ```
4. **Visit** `https://yourdomain.com/mitra/install.php` in your browser
5. **Fill in** your database credentials, company details, and admin password
6. **Click Install** — all database tables are created automatically
7. **Delete** `install.php` and `install_full.sql` from your server after install

### Option B — Manual Installation

1. Upload all files to your server
2. Create a MySQL/MariaDB database
3. Import `install_full.sql` via phpMyAdmin or command line:
   ```bash
   mysql -u username -p database_name < install_full.sql
   ```
4. Copy the example config:
   ```bash
   cp includes/config.example.php includes/config.php
   ```
5. Edit `includes/config.php` with your database credentials and site URL

### On a Raspberry Pi

```bash
# Install dependencies
sudo apt update
sudo apt install apache2 php php-mysql mariadb-server -y

# Enable mod_rewrite
sudo a2enmod rewrite
sudo systemctl restart apache2

# Clone into web root
cd /var/www/html
sudo git clone https://github.com/YOUR_USERNAME/mitra.git

# Set permissions
sudo chown -R www-data:www-data mitra/
sudo chmod 755 mitra/uploads/ mitra/includes/
```

Then visit `http://YOUR_PI_IP/mitra/install.php`

---

## Default Login

After installation, log in with:

```
Email:    admin@mitra.local
Password: The password you set during install
```

> **Change this immediately** after first login via Profile → Change Password

---

## Roles & Access

| Role | Tickets | POS | Invoices | Products | Settings | Reports |
|------|---------|-----|----------|----------|----------|---------|
| **Admin** | ✅ Full | ✅ Full | ✅ Full | ✅ Full | ✅ Full | ✅ Full |
| **Agent** | ✅ Full | ✅ Full | ✅ Full | ✅ Full | ❌ | ❌ |
| **Client** | Own only | ❌ | Own only | ❌ | ❌ | ❌ |

---

## File Structure

```
mitra/
├── index.php                   Login page
├── register.php                Client self-registration
├── logout.php
├── profile.php                 Profile & password change
├── download.php                Secure file download handler
├── check.php                   Diagnostic tool (delete after use)
│
├── admin/                      Staff & admin area
│   ├── dashboard.php           Combined overview with live stats
│   ├── tickets.php             All tickets with filters
│   ├── ticket.php              Ticket detail, reply, assign
│   ├── new-ticket.php          Create ticket on behalf of client
│   ├── users.php               User management
│   ├── departments.php         Department management
│   ├── reports.php             Ticket analytics
│   ├── branding.php            Logo, colors, fonts, document design
│   └── settings.php            System & POS settings
│
├── client/                     Client portal
│   ├── dashboard.php           Client overview
│   ├── tickets.php             Client ticket list
│   ├── ticket.php              View & reply to ticket
│   ├── new-ticket.php          Submit new ticket
│   └── invoices.php            Client invoice view
│
├── pos/                        POS & Billing module
│   ├── dashboard.php           POS overview & stats
│   ├── sale.php                POS terminal (interactive)
│   ├── sales.php               Sales history
│   ├── sale-view.php           Receipt view & print
│   ├── invoices.php            Invoice list
│   ├── invoice-new.php         Invoice builder
│   ├── invoice.php             Invoice detail, payments, print
│   ├── products.php            Product & service catalog
│   ├── reports.php             Financial reports
│   └── functions_pos.php       POS helper functions
│
├── includes/
│   ├── config.php              Database & site config (not in repo)
│   ├── config.example.php      Config template
│   ├── functions.php           Core functions & DB class
│   ├── header.php              Sidebar navigation
│   └── footer.php
│
├── assets/
│   ├── css/style.css           Design system stylesheet
│   ├── js/app.js               UI interactions & toast notifications
│   └── img/favicon.svg
│
└── uploads/                    User uploaded files
    └── branding/               Company logo storage
```

---

## Security

- All forms protected with CSRF tokens
- Passwords hashed with `password_hash()` (bcrypt)
- File uploads validated by MIME type and size
- Files served through `download.php` with access control
- `includes/` directory blocked from direct web access via `.htaccess`
- SQL injection prevention via PDO prepared statements
- `config.php` excluded from version control

---

## Configuration

After install, `includes/config.php` contains:

```php
define('DB_HOST',      'localhost');
define('DB_NAME',      'mitra');
define('DB_USER',      'your_db_user');
define('DB_PASS',      'your_db_password');
define('BASE_URL',     'https://yourdomain.com/mitra');
define('UPLOAD_MAX_MB', 10);
define('SESSION_NAME', 'mitra_session');
```

---

## Updating

```bash
cd /var/www/html/mitra

# Pull latest changes
git pull origin main

# Clear any cached files if needed
sudo systemctl restart apache2
```

> **Note:** Always back up your database before updating.
> `mysqldump -u user -p mitra > backup_$(date +%Y%m%d).sql`

---

## Troubleshooting

**Blank page after install**
Run `check.php` in your browser for a full system diagnostic. Most common causes:
- Wrong database credentials in `config.php`
- `pdo_mysql` PHP extension not installed — `sudo apt install php-mysql`
- `uploads/` or `includes/` directory not writable — `chmod 755`

**Can't log in to MariaDB**
```bash
sudo systemctl stop mariadb
sudo mariadbd --user=mysql --skip-grant-tables &
sleep 5
mariadb -u root
```

**Settings page blank**
Check Apache error log: `sudo tail -20 /var/log/apache2/error.log`

---

## License

Copyright © 2024 [d991d](https://github.com/d991d). All rights reserved.

This software is proprietary. You may use it for personal and commercial purposes but may not resell or redistribute it.

---

## Author

Built by **d991d**

> Mitra Business Suite — Ticketing · POS · Invoicing
