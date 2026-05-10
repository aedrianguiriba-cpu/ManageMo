# BASE_URL Usage Guide

## Overview
The `BASE_URL` constant is now available in all PHP files throughout the application. It automatically detects your application's root path and works seamlessly on both localhost and production servers.

## How It Works

### Automatic Detection
`BASE_URL` is defined in `config/constants.php` and automatically detects:
- **Local development**: `/managemo/` (when running on localhost)
- **Production servers**: `/` (when deployed to root domain)

### Access from Any File

**Root level files** (index.php, auth.php, logout.php, etc.):
```php
<?php
require_once 'config/functions.php';
// BASE_URL is now available
echo BASE_URL; // /managemo/ or /
?>
```

**Subdirectory files** (admin/*.php, user/*.php):
```php
<?php
require_once dirname(__DIR__) . '/config/functions.php';
// BASE_URL is now available
echo BASE_URL; // /managemo/ or /
?>
```

**Alternative method** - Use init.php:
```php
<?php
require_once __DIR__ . '/init.php';
// BASE_URL and all functions are available
?>
```

## Usage Examples

### HTML Links and Resources
```html
<!-- CSS -->
<link rel="stylesheet" href="<?php echo BASE_URL; ?>css/style.css">

<!-- JavaScript -->
<script src="<?php echo BASE_URL; ?>js/script.js"></script>

<!-- Images -->
<img src="<?php echo BASE_URL; ?>assets/pics/logo.png" alt="Logo">

<!-- Navigation Links -->
<a href="<?php echo BASE_URL; ?>admin/dashboard.php">Dashboard</a>
<a href="<?php echo BASE_URL; ?>user/inventory.php">Inventory</a>
```

### PHP Redirects
```php
// Login redirect
header('Location: ' . BASE_URL . 'index.php');

// Dashboard redirect
header('Location: ' . BASE_URL . 'admin/dashboard.php');

// Logout redirect
header('Location: ' . BASE_URL . 'index.php');
```

### File Uploads
```php
// Store files using BASE_URL (relative to document root)
$upload_dir = BASE_URL . 'assets/uploads/';
$qrcode_dir = BASE_URL . 'assets/qrcodes/';
```

## File Structure

```
ManageMo/
├── init.php                    ← Central initialization file
├── config/
│   ├── constants.php          ← BASE_URL defined here
│   ├── functions.php          ← Requires constants.php
│   └── data.php
├── includes/                   ← Shared templates
│   ├── header.php            ← Uses BASE_URL for CSS/JS
│   ├── navbar.php            ← Uses BASE_URL for links
│   ├── topbar.php            ← Uses BASE_URL for links
│   └── footer.php            ← Uses BASE_URL for JS
├── admin/                      ← Uses dirname(__DIR__) to access configs
│   ├── dashboard.php
│   ├── inventory.php
│   └── ...
├── user/                       ← Uses dirname(__DIR__) to access configs
│   ├── dashboard.php
│   ├── inventory.php
│   └── ...
├── css/style.css
├── js/script.js
└── assets/
    ├── pics/
    ├── uploads/
    └── qrcodes/
```

## No More 404 Errors

All resources now load correctly:
- ✅ CSS files load from `BASE_URL . css/style.css`
- ✅ JavaScript files load from `BASE_URL . js/script.js`
- ✅ Images load from `BASE_URL . assets/pics/...`
- ✅ Navigation links work from any file/folder
- ✅ Both localhost and production domains supported

## Testing

Test with these URLs:
- **Local**: http://localhost/managemo/
- **Production**: https://managemo.ct.ws/

Both will work automatically with BASE_URL!
