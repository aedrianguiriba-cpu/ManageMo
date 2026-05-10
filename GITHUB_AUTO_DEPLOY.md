# GitHub Auto-Deployment Setup Guide

## ✅ What's Been Created

A GitHub Actions workflow (`.github/workflows/deploy.yml`) has been created that will:
- Automatically run when you **push to the `main` branch**
- Upload all changed files to InfinityFree via FTP
- Complete in about 1-2 minutes

---

## 🔐 Add GitHub Secrets (Required)

Your deployment workflow needs your FTP credentials stored as **GitHub Secrets** for security.

### Step 1: Go to GitHub Repository Settings

1. Open your repository: `https://github.com/aedrianguiriba-cpu/ManageMo`
2. Click **Settings** (top right)
3. Left sidebar → **Secrets and variables** → **Actions**

### Step 2: Create Three Secrets

Click **"New repository secret"** and add these three:

**Secret 1:**
- Name: `FTP_HOST`
- Value: `ftpupload.net`

**Secret 2:**
- Name: `FTP_USERNAME`
- Value: `if0_41687091`

**Secret 3:**
- Name: `FTP_PASSWORD`
- Value: `pugspugs1234`

After adding all three, the page should look like:
```
✓ FTP_HOST
✓ FTP_USERNAME
✓ FTP_PASSWORD
```

---

## 🚀 Test the Deployment

### Method 1: Push a Small Change
1. Make a small change locally (e.g., edit this file)
2. Commit: `git add . && git commit -m "test deployment"`
3. Push: `git push origin main`
4. Go to **Actions** tab on GitHub
5. Watch the workflow run in real-time

### Method 2: Manual Trigger (No Changes Needed)
1. Go to your repo → **Actions** tab
2. Select **"Deploy to InfinityFree"**
3. Click **"Run workflow"** → **Run workflow**
4. Watch it deploy

---

## ✨ How It Works

Every time you:
```bash
git push origin main
```

GitHub Actions automatically:
1. Checks out your code
2. Connects to InfinityFree via FTP
3. Uploads all changed files
4. Updates your live website

---

## 📋 What Gets Deployed

All files in your repository will be synced to `htdocs/`:
- ✅ PHP files
- ✅ CSS/JS assets
- ✅ Config files
- ✅ `.htaccess`
- ✅ Everything else

---

## ⚙️ How to Modify the Workflow

If you need to change which branch triggers deployment, edit `.github/workflows/deploy.yml`:

```yaml
on:
  push:
    branches: [main]    # Change to [master] or [develop] etc.
```

---

## ⚠️ Important Notes

1. **FTP Credentials are Safe** - They're encrypted in GitHub and never shown in logs
2. **Initial Sync** - The first deployment uploads everything. Subsequent pushes only upload changed files.
3. **Ignored Files** - By default, `.git/` and `.github/workflows/` are uploaded (GitHub Actions are needed, git files are harmless)
4. **Rollback** - If something breaks, push a fixed version immediately to deploy it

---

## 🧪 Troubleshooting

### If deployment fails:

1. Go to **Actions** tab → See failed workflow
2. Click the failed job to see error messages
3. Common issues:
   - **Wrong FTP credentials** - Double-check the Secrets match your InfinityFree account
   - **FTP server down** - Try again in a minute
   - **Permission issues** - Contact InfinityFree support

### Check Deployment Status:

1. Go to **Actions** tab
2. See all past deployments and their status
3. Click any deployment to see details

---

## 💡 Optional: Exclude Files from Deployment

To prevent certain files from being uploaded, add to `.github/workflows/deploy.yml`:

```yaml
- name: Deploy to InfinityFree via FTP
  uses: SamKirkland/FTP-Deploy-Action@v4.3.4
  with:
    server: ${{ secrets.FTP_HOST }}
    username: ${{ secrets.FTP_USERNAME }}
    password: ${{ secrets.FTP_PASSWORD }}
    local-dir: ./
    server-dir: ./
    exclude: |
      **/.git*
      **/node_modules/**
      **/.env
      **/README.md
```

---

## ✅ You're All Set!

Once you add the three GitHub Secrets, your deployment is ready. Just push to `main` and watch the magic happen! 🎉
