# ELEGANCE SALON - Salon Management System

A premium, professional web-based Salon Management System built with Core PHP 8+, MySQL, Bootstrap 5.3, and vanilla JavaScript.

## XAMPP Setup Instructions

### 1. Install & Start XAMPP
1. Install XAMPP (PHP 8.x) from [apachefriends.org](https://www.apachefriends.org/).
2. Open the XAMPP Control Panel.
3. Start the **Apache** and **MySQL** services.

### 2. Copy Project Files
1. Copy (or extract) the entire `saloon-pro` folder into:
   ```
   C:\xampp\htdocs\saloon-pro\
   ```
2. Verify the folder exists at:
   ```
   C:\xampp\htdocs\saloon-pro\
   ```

### 3. Import the Database
1. Open your browser and visit: `http://localhost/phpmyadmin/`
2. Click the **Import** tab at the top.
3. Click **Choose File** and select:
   ```
   C:\xampp\htdocs\saloon-pro\database\db-saloon.sql
   ```
4. Click **Go** / **Import**.
5. You should see `db-saloon` appear in the sidebar on the left.

**OR via MySQL Command Line:**
```
mysql -u root -p < C:\xampp\htdocs\saloon-pro\database\db-saloon.sql
```

### 4. Verify Database Configuration
The database connection is auto-configured in:
`config/database.php`

Default settings (XAMPP default):
- Host: `localhost`
- Database: `db-saloon`
- User: `root`
- Password: *(empty)*

### 5. Run the Application
Open your browser and visit:

```
http://localhost/saloon-pro/
```

**NOTE:** If the site does not load, check that the database was imported successfully and Apache/MySQL are running.

## Default Admin Login
- **Email:** admin@elegancesalon.com
- **Password:** admin123

*(The admin module is implemented in Part 2. Login validation works from Part 1.)*

## Folder Structure
```
saloon-pro/
│
├── index.php              (Homepage)
├── about.php              (About page)
├── services.php           (Services page)
├── gallery.php            (Gallery with lightbox & filtering)
├── team.php               (Team page)
├── contact.php            (Contact page with form)
├── login.php              (Login page)
├── book-appointment.php   (Appointment booking form)
│
├── config/
│   └── database.php       (PDO database connection)
│
├── includes/
│   ├── header.php         (HTML head + opening body)
│   ├── navbar.php         (Responsive navbar)
│   ├── footer.php         (Footer + JS includes)
│   └── functions.php      (Helper functions, auth, sanitize)
│
├── assets/
│   ├── css/style.css      (Design system + all styles)
│   ├── js/main.js         (Navbar, counters, gallery, lightbox)
│   ├── fonts/             (Custom fonts)
│   └── images/
│       ├── hero/          (Hero section images)
│       ├── about/         (About section images)
│       ├── services/      (Service card images)
│       ├── gallery/       (Gallery images)
│       ├── team/          (Team member images)
│       └── banners/       (CTA banner images)
│
├── admin/                 (Admin dashboard - Part 2)
├── user/                  (Client dashboard - Part 3)
├── stylist/               (Stylist dashboard - Part 3)
│
├── database/
│   └── db-saloon.sql      (Full database with all tables)
│
└── .htaccess              (Security + caching)
```

## Image Replacement
The site currently uses high-quality remote images from Unsplash. To use local images:
1. Download images into the appropriate folder under `assets/images/`.
2. Replace the `https://images.unsplash.com/...` URLs in the PHP files with local paths like:
   ```
   <?php echo SITE_URL; ?>/assets/images/hero/salon-hero.jpg
   ```

## Security Features
- PDO prepared statements (SQL injection protection)
- `htmlspecialchars()` output escaping (XSS protection)
- CSRF token generation & validation
- `password_hash()` / `password_verify()` for credentials
- Session-based authentication
- Input validation & sanitization

## Database Tables
- `roles`, `users`, `staff`, `clients`
- `services`, `appointments`
- `gallery`, `feedback`
- `notifications`
- `inventory`, `suppliers`
- `payments`, `invoices`, `commissions`
- `site_settings`