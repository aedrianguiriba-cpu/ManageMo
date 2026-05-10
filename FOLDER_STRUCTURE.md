# ManageMo - Complete Folder Structure

This is the **exact folder structure** required for ManageMo to work on localhost, staging, and production servers.

## Root Directory Structure

```
ManageMo/
├── 📄 index.php                    (Login/Auth page)
├── 📄 auth.php                     (Auth endpoint)
├── 📄 signup.php                   (Signup page)
├── 📄 logout.php                   (Logout handler)
├── 📄 forgot-password.php          (Password reset)
├── 📄 init.php                     (Central initialization)
├── 📄 debug.php                    (Debug info - delete after use)
│
├── 📁 config/                      (Configuration & functions)
│   ├── constants.php               (Constants & BASE_URL definition)
│   ├── functions.php               (Core functions)
│   ├── data.php                    (Mock database data)
│   └── mock_data.php               (Test data)
│
├── 📁 includes/                    (Shared templates)
│   ├── header.php                  (HTML head + navigation CSS/JS links)
│   ├── navbar.php                  (Sidebar navigation)
│   ├── topbar.php                  (Top user menu)
│   └── footer.php                  (Footer + JS includes)
│
├── 📁 admin/                       (Admin pages - requires auth)
│   ├── dashboard.php
│   ├── inventory.php
│   ├── inventory-campus.php
│   ├── analytics.php
│   ├── reports.php
│   ├── requests.php
│   ├── users.php
│   └── settings.php
│
├── 📁 user/                        (User pages - requires auth)
│   ├── dashboard.php
│   ├── inventory.php
│   ├── requests.php
│   ├── my-requests.php
│   ├── borrow-records.php
│   └── settings.php
│
├── 📁 css/                         (Stylesheets)
│   └── style.css                   (Main stylesheet)
│
├── 📁 js/                          (JavaScript)
│   └── script.js                   (Main scripts)
│
├── 📁 lib/                         (Libraries)
│   └── qrcode.php                  (QR code library)
│
├── 📁 assets/                      (Static assets)
│   ├── pics/                       (Images & logos)
│   │   └── logo.png                (ManageMo logo)
│   ├── uploads/                    (User uploads - writable)
│   │   └── approval_letters/       (Approval documents)
│   └── qrcodes/                    (Generated QR codes - writable)
│
├── 📁 database/                    (Database documentation)
│   ├── erd.mmd                     (Entity relationship diagram)
│   └── flowchart.mmd               (System flowchart)
│
├── 📄 DEMO_ACCOUNTS.txt            (Test credentials)
├── 📄 README.md                    (Project documentation)
├── 📄 QUICKSTART.md                (Setup guide)
└── 📄 SYSTEM_DOCUMENTATION.txt     (System details)
```

---

## Deployment Checklist

### ✅ Before Uploading to Server

- [ ] All PHP files are present (index.php, auth.php, etc.)
- [ ] All folders exist with correct names (lowercase: admin/, user/, config/, etc.)
- [ ] CSS file exists: `css/style.css`
- [ ] JavaScript file exists: `js/script.js`
- [ ] Logo image exists: `assets/pics/logo.png`
- [ ] QR code library exists: `lib/qrcode.php`
- [ ] Config files exist: `config/constants.php`, `config/functions.php`, etc.

### ✅ Folder Permissions (if needed)

These folders should be **writable** by the web server:
- `assets/uploads/` (for approval letters)
- `assets/uploads/approval_letters/` (subdirectory)
- `assets/qrcodes/` (for QR code generation)
- `config/` (already should be, contains functions.php which is read-only)

### ✅ Files to Delete Before Going Live

- [ ] `debug.php` - Debugging information (security risk in production)
- [ ] `User Role Management-2026-04-15-183043.pdf` - Temporary file
- [ ] Any `.DS_Store` or `Thumbs.db` files

---

## How It Works on Different Servers

### Local Development (XAMPP/WAMP)
```
http://localhost/managemo/
├── BASE_URL = /managemo/
├── Resources load from: /managemo/css/style.css
└── Works ✓
```

### Production (InfinityFree / Root Domain)
```
https://managemo.ct.ws/
├── BASE_URL = /
├── Resources load from: /css/style.css
└── Works ✓
```

### Production (Subdirectory)
```
https://mydomain.com/managemo/
├── BASE_URL = /managemo/
├── Resources load from: /managemo/css/style.css
└── Works ✓
```

**The BASE_URL is automatically detected** in `config/constants.php`, so the same code works everywhere!

---

## Verifying Your Structure is Correct

### Method 1: Use the Debug Page
1. Upload your site to the server
2. Visit: `https://yourdomain.com/debug.php`
3. Check if all files show as "✓ Found"
4. Verify BASE_URL is correct
5. Delete debug.php when done

### Method 2: Manual Check
SSH into your server and run:
```bash
# From the root directory
ls -la css/
ls -la js/
ls -la assets/pics/logo.png
ls -la config/
ls -la admin/
ls -la user/
```

All should exist and be readable.

---

## Common Issues & Solutions

### ❌ CSS/JS/Images Return 404

**Cause:** Files not uploaded or in wrong folder

**Solution:**
1. Use an FTP client (like FileZilla)
2. Upload entire ManageMo folder to your domain
3. Verify structure matches this guide
4. Run `debug.php` to confirm

### ❌ Resources load from wrong path

**Cause:** BASE_URL calculation wrong

**Solution:**
1. Run `debug.php` and check BASE_URL value
2. If wrong, email me the output
3. I'll fix the BASE_URL detection

### ❌ Login page shows but can't interact

**Cause:** JavaScript or CSS not loading

**Solution:**
1. Open browser DevTools (F12)
2. Check Console tab for 404 errors
3. Check which resources are missing
4. Ensure files are uploaded to correct folders

---

## Upload Instructions (Using FTP)

1. **Using FileZilla:**
   - Host: Your FTP host
   - Username/Password: From your hosting provider
   - Connect
   - Drag entire `ManageMo` folder to remote server
   - Ensure folder structure matches above

2. **Using Web Hosting Control Panel:**
   - Upload each folder maintaining structure
   - DO NOT flatten the folders
   - Keep folder names lowercase

3. **Using SSH/Terminal:**
   ```bash
   scp -r /path/to/ManageMo/* user@host:/public_html/
   # Or if subdirectory:
   scp -r /path/to/ManageMo/* user@host:/public_html/managemo/
   ```

---

## Testing After Upload

1. **Test Login Page:**
   ```
   https://yourdomain.com/
   ```
   - Logo should load
   - CSS styling should work
   - No 404 errors in console

2. **Test Login:**
   - Email: `admin@university.edu`
   - Password: `admin123`

3. **Check Browser Console (F12):**
   - Should have NO 404 errors
   - Should have NO warnings for missing resources

---

## Need Help?

If resources still don't load:
1. Run `/debug.php` and share the output
2. Share which resources are 404 (CSS/JS/images)
3. Share the correct domain the app is deployed to
4. I'll provide exact fix
