# Quick Upload Checklist for InfinityFree

## ✅ Before You Upload — Verify This Locally

On your computer in `c:\xampp\htdocs\ManageMo\`, check you have:

### Folders (must exist):
- [ ] `admin/` — with 8 PHP files
- [ ] `user/` — with 6 PHP files  
- [ ] `config/` — with 4 PHP files
- [ ] `css/` — with `style.css`
- [ ] `js/` — with `script.js`
- [ ] `assets/` — with `pics/`, `uploads/`, `qrcodes/` subfolders
- [ ] `assets/pics/` — with `logo.png`
- [ ] `includes/` — with 4 PHP files
- [ ] `lib/` — with `qrcode.php`

### Root Files (must exist):
- [ ] `index.php`
- [ ] `auth.php`
- [ ] `signup.php`
- [ ] `logout.php`
- [ ] `forgot-password.php`
- [ ] `config/constants.php`
- [ ] `config/functions.php`
- [ ] `.htaccess`

---

## 🚀 Upload to InfinityFree

### Using FileZilla:

1. **Open FileZilla**
   - Host: `ftp.managemo.ct.ws` (from your InfinityFree account)
   - Username: Your FTP username
   - Password: Your FTP password
   - Port: 21
   - Click **Connect**

2. **Navigate to the right folder**
   - Left side: Find your local `ManageMo` folder
   - Right side: You should be in `/` (domain root)
   - This is where you'll upload

3. **Upload Everything**
   - Select **ALL** files and folders from your local ManageMo
   - **Right-click → Upload**
   - Make sure to include:
     - `admin/` folder (entire folder)
     - `user/` folder (entire folder)
     - `css/` folder (entire folder)
     - `js/` folder (entire folder)
     - `assets/` folder (entire folder with subfolders)
     - `config/` folder (entire folder)
     - `includes/` folder (entire folder)
     - `lib/` folder (entire folder)
     - All root `.php` files
     - `.htaccess` file (important!)

4. **Verify Upload**
   - Wait for upload to complete
   - Refresh FileZilla file list on right side
   - You should see all the folders listed

### If Uploading Files One-by-One:

1. Right-click each folder → Upload
2. Then upload root `.php` files
3. Then upload `.htaccess` file

---

## ✅ After Upload — Run Diagnostics

1. Visit: `https://managemo.ct.ws/debug.php`
2. Check the report:
   - ✓ All folders should show "Yes"
   - ✓ All files should show "Yes"
   - ✓ BASE_URL should show "/"
3. If anything shows "No":
   - It's missing — upload it from your local copy
   - Refresh and verify again

---

## 🧪 Test the Site

1. Visit: `https://managemo.ct.ws/`
2. Open browser **F12** (Console)
3. Check for red errors (should be none)
4. Test login with:
   - Email: `admin@university.edu`
   - Password: `admin123`
5. If login works → Everything is correct!

---

## ❌ If Login Page Doesn't Load

**Most Likely Causes:**

1. **CSS/JS not loading** → Files weren't uploaded
   - Check `/css/style.css` exists via debug.php
   - Re-upload `css/` and `js/` folders

2. **Logo not showing** → Assets folder missing
   - Check `/assets/pics/logo.png` exists
   - Re-upload entire `assets/` folder

3. **Page shows errors** → PHP config issue
   - Check if PHP is enabled on your hosting
   - Contact InfinityFree support if PHP not working

---

## Cleanup

When everything works:
1. **Delete these files from your server:**
   - `debug.php` — for diagnostics only
   - `validate.php` — for validation only  
   - `.htaccess` — if you want (optional)

2. **Keep everything else**

---

## Still Having Issues?

If files still won't load after uploading:

1. Run debug.php and check output
2. Take a screenshot of missing items
3. Tell me:
   - What shows as "Exists: No" in debug.php?
   - What does BASE_URL show?
   - Any error messages?

Then I can provide exact fix! 🔧
