# ManageMo - Quick Start Guide

## 🚀 Getting Started in 5 Minutes

### Step 1: Create the Database
Manually create the database and tables in MySQL. You'll need to:
1. Create database: `managemo`
2. Create all necessary tables with proper relationships
3. Set up foreign keys and indexes

### Step 2: Configure Database Connection
Edit `config/database.php` if needed (should work with default XAMPP settings):
```php
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASSWORD', '');  // Leave empty for XAMPP default
define('DB_NAME', 'managemo');
```

### Step 3: Set Up Users
Manually insert your admin and user accounts into the database with hashed passwords. The application will require at least one admin account to get started.

### Step 4: Login
Go to: `http://localhost/ManageMo/`

Use the credentials you created in the database.

---

## 📋 Project Structure Summary

### Core Configuration Files
- `config/database.php` - Database connection
- `config/constants.php` - Application constants
- `config/functions.php` - Helper functions & authentication

### Admin Pages
1. **Dashboard** (`admin/dashboard.php`)
   - Statistics overview
   - Campus summary
   - Recent activities

2. **Inventory** (`admin/inventory.php`)
   - Add/Edit/Delete items
   - QR code management
   - Filter by campus & status

3. **Inventory by Campus** (`admin/inventory-campus.php`)
   - Campus-level overview
   - Quick statistics

4. **Requests** (`admin/requests.php`)
   - View pending requests
   - Approve/Disapprove with notes
   - Filter by status & type

5. **Analytics** (`admin/analytics.php`)
   - Inventory statistics
   - Request metrics
   - Asset value reports
   - Top requested items

6. **Settings** (`admin/settings.php`)
   - Profile management
   - Password change
   - System configuration

### User Pages
1. **Dashboard** (`user/dashboard.php`)
   - Personal statistics
   - Quick actions
   - Recent requests

2. **Inventory** (`user/inventory.php`)
   - Browse items for campus
   - Search & filter
   - Borrow items

3. **Submit Request** (`user/requests.php`)
   - Borrow request
   - Item request
   - Service request
   - Urgency levels

4. **Borrow Records** (`user/borrow-records.php`)
   - Active borrows
   - Return history
   - Overdue tracking

5. **Settings** (`user/settings.php`)
   - Profile management
   - Activity statistics

---

## 🔑 Key Features Implemented

### Three Request Types
1. **Borrow Request** - Temporary item borrowing with return dates
2. **Item Request** - Request for new item purchase
3. **Service Request** - Request for maintenance/repair (with critical urgency support)

### Urgency Levels
- Low - Non-urgent
- Medium - Normal (default)
- High - Urgent
- Critical - Immediate action (for broken items, etc.)

### Request Status Workflow
```
Pending → Approved/Disapproved → Delivered → Returned/Completed
```

### Inventory Status
- Available
- Borrowed
- Damaged
- Maintenance
- Retired

---

## 🎯 Admin Workflows

### Add New Inventory Item
1. Go to Admin > Inventory
2. Click "Add New Item"
3. Fill in details (QR code auto-generated)
4. Submit

### Review & Approve Requests
1. Go to Admin > Requests
2. Click "View" on pending request
3. Review details
4. Click "Approve" or "Disapprove"
5. Add notes (optional)
6. Submit

### Generate QR Codes
QR codes are automatically generated when items are added. They can be:
- Viewed in the inventory list
- Downloaded via QRServer API
- Printed for physical labels

### View Analytics
Go to Admin > Analytics to see:
- Total inventory by status
- Asset value calculation
- Request statistics
- Most requested items
- Category distribution

---

## 👥 User Workflows

### Browse Inventory
1. Go to User > Inventory
2. Filter by category, status
3. View item details with QR code
4. Click "Borrow Item" if available

### Submit Request

**For Borrowing:**
1. Go to User > Submit Request
2. Select "Borrow Item"
3. Choose item & return date
4. Add reason
5. Submit

**For Service (Critical Issues):**
1. Select "Request Service"
2. Choose urgency level (Critical for broken items)
3. Describe the problem
4. Submit

**For New Items:**
1. Select "Request Item"
2. Describe item needed
3. Specify quantity
4. Submit

### Track Borrow Records
1. Go to User > Borrow Records
2. View active borrows, returned items, overdue items
3. Monitor return dates

---

## 🔒 Authentication

- Secure password hashing (BCrypt)
- Session-based authentication
- Role-based access control (Admin/User)
- Automatic login redirects
- Logout functionality

---

## 🎨 UI Features

- **Bootstrap 5** responsive design
- **FontAwesome 6** icons
- **Responsive cards** and layouts
- **Dynamic filters** and search
- **Status badges** with color coding
- **Pagination** for long lists
- **Form validation** (client & server)
- **Alert notifications** with auto-dismiss

---

## ⚠️ Important Notes

### Security
- All inputs are sanitized
- Passwords are hashed
- SQL injection protected
- XSS protection enabled
- CSRF tokens available (can add)

### Database
- Proper relationships & foreign keys
- Indexed for performance
- Activity logging enabled
- Timestamp tracking

### Future Mobile App (Skipped)
- QR code system ready for mobile scanning
- Multi-scanner support architecture
- Real-time inventory update capability

---

## 🐛 Troubleshooting

### "Connection failed" Error
- Check database credentials in `config/database.php`
- Ensure MySQL is running
- Verify database name

### Can't see inventory items
- Check user's campus_id matches item's campus_id
- Verify items exist for that campus
- Try filters without any selection

### QR codes not showing
- Need internet connection (uses external QRServer API)
- Check browser console for errors
- Try refreshing the page

---

## 📚 Additional Resources

- Helper functions: `config/functions.php`
- Styles: `css/style.css`
- JavaScript: `js/script.js`
- Full documentation: `README.md`

---

## ✅ What's Ready to Use

✅ Admin Dashboard with real-time statistics
✅ Inventory management (add/edit/delete)
✅ QR code generation (auto-generated for items)
✅ Request management (3 types, 4 urgency levels)
✅ User inventory browsing
✅ Borrow request system
✅ Service request system
✅ Analytics & reporting
✅ Audit logging
✅ User management
✅ Settings pages
✅ Responsive design
✅ Database connection ready (requires manual table setup)

## ⏭️ Future Enhancements (Mobile App - Skipped)
- Mobile app for QR code scanning
- Real-time inventory sync
- Multi-device concurrent scanning
- Offline mode

---

**Enjoy using ManageMo! 🎉**
