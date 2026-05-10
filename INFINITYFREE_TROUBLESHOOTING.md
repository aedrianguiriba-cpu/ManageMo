# ManageMo on InfinityFree - Troubleshooting Guide

## Issue: Getting 404 Errors from `errors.infinityfree.net`

If CSS, JS, images show 404 errors from InfinityFree:

```
Request URL: https://errors.infinityfree.net/errors/404/
Status: 404 Not Found
Referrer: https://managemo.ct.ws/
```

This means the browser is trying to load resources but they're not found where the app expects them.

---

## Quick Diagnosis 

### Step 1: Check What BASE_URL is Being Used

1. Upload your site to InfinityFree
2. Visit: **`https://managemo.ct.ws/debug.php`**
3. Look at the **BASE_URL value** shown
4. It should be: **`/`** (root slash only)

### Step 2: Verify Files Are Actually Uploaded

Using FileZilla FTP client:
1. Connect to your InfinityFree hosting
2. Navigate to `htdocs/` folder
3. Check these exist:
   - ✓ `/css/style.css`
   - ✓ `/js/script.js`
   - ✓ `/assets/pics/logo.png`
   - ✓ `/config/constants.php`
   - ✓ `/admin/` folder with PHP files
   - ✓ `/user/` folder with PHP files

If any are missing, upload them now.

---

## Root Causes & Solutions

### ❌ Problem: BASE_URL shows `/managemo/` but should be `/`

**Cause:** The auto-detection picked up the subfolder incorrectly

**Solution A: Use .htaccess (Recommended)**

1. Create a file called `.htaccess` in your root folder with:
```
SetEnv MANAGEMO_BASE_URL "/"
```

2. Upload it to the root of your domain
3. Refresh `/debug.php` - BASE_URL should now be `/`

**Solution B: Manual Override**

Edit `config/constants.php` and change:
```php
// Change from:
define('BASE_URL', $base_path);

// To:
define('BASE_URL', '/');
```

Then upload and test.

---

### ❌ Problem: Files Return 404 Even Though BASE_URL is Correct

**Most Common Cause:** The actual files weren't uploaded or are in wrong locations

**Check in browser console:**

1. Open your site: `https://managemo.ct.ws/`
2. Press `F12` (Developer Tools)
3. Go to **Console** tab
4. Look for red errors like:
   - `Failed to load resource: the server responded with a status of 404 (Not Found)`
5. Click on the error to see which file failed
6. Check the URL path attempted

**Expected URLs:**
```
https://managemo.ct.ws/css/style.css
https://managemo.ct.ws/js/script.js
https://managemo.ct.ws/assets/pics/logo.png
```

**If seeing wrong paths:**
- BASE_URL or file paths are still incorrect
- Run `/debug.php` again and share output

**If seeing 404 but paths look right:**
- Files weren't uploaded successfully
- Folder structure is wrong
- File names have different case (Style.css vs style.css)

---

### ❌ Problem: Nothing Loads on the Page

**Possible Causes:**

1. **PHP isn't running** → Contact InfinityFree support, PHP should be enabled
2. **config/functions.php has an error** → Check `debug.php` output
3. **Files uploaded to wrong directory** → Should be root (htdocs/), not subdirectory

**Check if PHP is running:**
- Create a test file: `test.php`
```php
<?php echo "PHP is working!"; ?>
```
- Upload to root and visit `https://managemo.ct.ws/test.php`
- If you see "PHP is working!" → PHP is fine
- Delete `test.php` after testing

---

## Step-by-Step Fix Process

### If CSS/JS/Images Still Don't Load:

**Step 1: Verify File Structure**
```
Login to InfinityFree File Manager or FTP
Go to htdocs/
Confirm you see:
  ├── admin/ (folder)
  ├── css/ (folder)
  ├── js/ (folder)
  ├── assets/ (folder)
  ├── config/ (folder)
  ├── includes/ (folder)
  ├── user/ (folder)
  ├── index.php (file)
  ├── auth.php (file)
  └── debug.php (file)
```

**Step 2: Check BASE_URL**
```
Visit: https://managemo.ct.ws/debug.php
Look for "BASE_URL constant" line
Should show: / (just a forward slash)
If not, create .htaccess file with SetEnv command above
```

**Step 3: Check Network Errors**
```
Visit: https://managemo.ct.ws/
Open F12 Console
Look for red errors
Check the "Network" tab for 404s
Note the exact URLs being requested
```

**Step 4: Test Individual Files**
```
Try visiting these directly:
https://managemo.ct.ws/css/style.css
https://managemo.ct.ws/js/script.js
https://managemo.ct.ws/assets/pics/logo.png

If any return error page instead of file content:
- File doesn't exist
- Re-upload that folder
```

---

## Files to Upload to InfinityFree

Here's the  **EXACT structure** needed at InfinityFree:

```
htdocs/                          (your domain root)
├── admin/
│   ├── analytics.php
│   ├── dashboard.php
│   ├── inventory.php
│   ├── inventory-campus.php
│   ├── reports.php
│   ├── requests.php
│   ├── settings.php
│   └── users.php
├── assets/
│   ├── pics/
│   │   └── logo.png
│   ├── uploads/
│   │   └── approval_letters/
│   └── qrcodes/
├── config/
│   ├── constants.php
│   ├── data.php
│   ├── functions.php
│   └── mock_data.php
├── css/
│   └── style.css
├── database/
│   ├── erd.mmd
│   └── flowchart.mmd
├── includes/
│   ├── footer.php
│   ├── header.php
│   ├── navbar.php
│   └── topbar.php
├── js/
│   └── script.js
├── lib/
│   └── qrcode.php
├── user/
│   ├── borrow-records.php
│   ├── dashboard.php
│   ├── inventory.php
│   ├── my-requests.php
│   ├── requests.php
│   └── settings.php
├── .htaccess             (optional, for BASE_URL override)
├── auth.php
├── debug.php             (DELETE after debugging)
├── forgot-password.php
├── index.php
├── init.php
├── logout.php
├── signup.php
└── validate.php          (DELETE after debugging)
```

---

## InfinityFree Tips

### Uploading Files

**Using FileZilla:**
- Host: Copy from InfinityFree Account Panel → FTP Details
- Port: 21
- Protocol: FTP
- Connect and drag-drop folders to htdocs/

**Using InfinityFree File Manager:**
- Slower but works
- Right-click → Upload
- Can only upload files, not folders with same structure
- Extract ZIP files if uploading as ZIP

### File Permissions

InfinityFree usually handles automatically, but if you:
- Can't upload files → Contact support
- CSS/JS still don't load → Contact support about permissions

### Troubleshooting Support

If nothing above works:
1. Share the output from **`/debug.php`**
2. Share which files show as red 404 in browser console
3. Share if BASE_URL shows correct value
4. Contact InfinityFree support if files won't upload

---

## Cleanup

When everything works, **DELETE these temporary files:**
- ❌ `debug.php`
- ❌ `validate.php`
- ❌ `.htaccess` (if you created it for testing)
- ❌ `test.php` (if you created it)

These are security risks in production!

---

## Still Having Issues?

Share with me:
1. What does `debug.php` show for BASE_URL?
2. Which resources fail (CSS, JS, images)?
3. The full path shown in browser console error
4. Screenshot of Files uploaded to htdocs/

Then I can provide exact fix! 🔧
