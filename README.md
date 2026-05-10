# ManageMo - University Asset Management System

A comprehensive PHP-based web application for managing university assets, inventory, borrowing requests, and maintenance services.

## Features

### Admin Dashboard
- **Inventory Management** (University-wide)
  - Add, edit, delete inventory items
  - Organize by campus
  - Track item conditions and status
  - Generate unique QR codes for each item

- **Request Management**
  - View and process requests from users
  - Approve or disapprove requests
  - 3 request types: Item Request, Borrow Request, Service Request
  - Urgent/Critical level support for immediate action items

- **Campus Inventory**
  - View inventory by campus
  - Campus-specific statistics

- **Analytics & Reporting**
  - Inventory statistics by status
  - Request analytics
  - Item distribution by category
  - Most requested items
  - Asset value tracking

- **Settings**
  - User profile management
  - Password management
  - System configuration

### User (Custodian) Features
- **Inventory Browsing**
  - View available items in their campus
  - Search and filter functionality
  - Item details with QR codes
  - Check item status

- **Request Management**
  - **Borrow Item**: Request to temporarily borrow an item
  - **Request Item**: Request purchase of new items
  - **Request Service**: Request maintenance/repair services
  - Track request status

- **Borrow Records**
  - Track all borrowed items
  - View expected return dates
  - Monitor overdue items

- **Settings**
  - Profile management
  - Password change
  - View account information

## System Requirements

- PHP 7.4 or higher
- MySQL 5.7 or higher
- Web server (Apache/Nginx)

## Installation & Setup

### 1. Database Setup

You will need to manually create the database and tables. Create a `managemo` database and set up all required tables with proper relationships and indexes.

### 2. Configure Database Connection

Edit `config/database.php`:
```php
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASSWORD', 'your_password');
define('DB_NAME', 'managemo');
```

### 3. Create User Accounts

Manually insert at least one admin account into the database. Use proper password hashing (BCrypt):
- Email
- Hashed password
- Full name
- Role (admin or user)
- Campus ID (for users)

### 4. Set Permissions

Ensure these directories are writable:
```bash
chmod 755 assets/
chmod 755 assets/uploads/
chmod 755 assets/qrcodes/
```

### 5. Access the Application

Open your browser and navigate to:
```
http://localhost/ManageMo/
```

## User Setup

Create your own admin and user accounts directly in the database. Ensure:
- Passwords are hashed using BCrypt
- Users have appropriate campus assignments
- Admin accounts have role set to 'admin'
- Regular users have role set to 'user'

## Project Structure

```
ManageMo/
├── admin/                    # Admin pages
│   ├── dashboard.php         # Admin dashboard
│   ├── inventory.php         # Inventory management
│   ├── inventory-campus.php  # Campus inventory overview
│   ├── requests.php          # Request management
│   ├── analytics.php         # Analytics & reports
│   └── settings.php          # Admin settings
├── user/                     # User pages
│   ├── dashboard.php         # User dashboard
│   ├── inventory.php         # Browse inventory
│   ├── requests.php          # Submit requests
│   ├── borrow-records.php    # Borrow history
│   └── settings.php          # User settings
├── config/                   # Configuration files
│   ├── database.php          # Database connection
│   ├── constants.php         # App constants
│   └── functions.php         # Helper functions
├── includes/                 # Shared templates
│   ├── header.php            # Page header/navbar
│   ├── footer.php            # Page footer
│   └── navbar.php            # Navigation menu
├── lib/                      # Libraries
│   └── qrcode.php            # QR code generation
├── css/                      # Stylesheets
│   └── style.css             # Main styles
├── js/                       # JavaScript
│   └── script.js             # Main scripts
├── assets/                   # Uploaded files & QR codes
│   ├── uploads/              # File uploads
│   └── qrcodes/              # Generated QR codes
├── database/                 # Database files (manual setup required)
├── index.php                 # Login page
├── logout.php                # Logout handler
└── README.md                 # This file
```

## Key Features Explained

### QR Code System
- Each inventory item is assigned a unique QR code
- Codes are generated using QRServer API
- Codes can be printed for physical labeling
- Future mobile app integration for scanning

### Request Types

1. **Borrow Request**: Temporary borrowing of items with return date
2. **Item Request**: Request for purchase of new items
3. **Service Request**: Request for maintenance/repair/support

### Urgency Levels
- **Low**: Non-urgent request
- **Medium**: Normal priority (default)
- **High**: Urgent attention needed
- **Critical**: Immediate action required (for items like broken AC, sink, etc.)

### Status Workflow

**Requests**:
- Pending → Approved/Disapproved → Delivered → Returned/Completed

**Inventory**:
- Available → Borrowed/Damaged/Maintenance → Available/Retired

**Borrow Records**:
- Active → Returned/Overdue

## User Roles

### Admin (Supply Department)
- Full inventory control
- Request approval/disapproval
- Analytics access
- System settings

### User (Custodian)
- Browse inventory for their campus
- Submit requests
- Track borrow records
- View request status

## Future Enhancements (Mobile App - Currently Skipped)

The following features are designed for future mobile app integration:
- QR code scanning with real-time inventory updates
- Multiple concurrent scanners support
- Delivery confirmation via QR scan
- Mobile-specific analytics

## Common Tasks

### Add New Campus

1. Go to Admin > Settings
2. View Campuses Management section
3. Manually insert into database:
```sql
INSERT INTO campuses (name, location) VALUES ('Campus Name', 'Location');
```

### Assign User to Campus

Update user record:
```sql
UPDATE users SET campus_id = 1 WHERE id = X;
```

### Generate QR Codes for Items

Use the admin inventory page to add items - QR codes are automatically generated.

## Troubleshooting

### Database Connection Error
- Check `config/database.php` credentials
- Ensure MySQL is running
- Verify database exists

###  Missing Items in Inventory
- Verify user's campus_id matches the item's campus_id
- Check campus exists in the database

### QR Code Not Displaying
- Ensure internet connection (uses external API)
- Check file upload permissions
- Review console for JavaScript errors

## Notes for Development

- All user inputs are sanitized using `sanitizeInput()`
- Passwords are hashed using BCrypt
- Sessions are used for authentication
- Activity logging implemented for audit trail
- Responsive Bootstrap 5 UI

## Support & Maintenance

For issues or feature requests, refer to:
- Configuration: `config/` directory
- Helper functions: `config/functions.php`

## License

This project is developed for university asset management purposes.

## Version

**ManageMo v1.0.0** - 2026
