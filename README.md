# ManageMo — PSU Asset Management System

A PHP-based web application for managing university assets, inventory, borrow requests, and maintenance services across all Pampanga State University campuses. No database required — all data is stored in flat PHP/JSON files.

---

## Tech Stack

- **PHP 7.4+** — server-side logic, no framework
- **Bootstrap 5.3** — responsive layout
- **Font Awesome 6.4** — icons
- **Chart.js 4.4** — dashboard graphs (CDN)
- **Flat-file storage** — `config/data.php` + `config/departments_custom.json`

---

## Features

### Admin

| Feature | Description |
|---|---|
| Dashboard | KPI cards, line/doughnut charts, campus summary table, recent requests |
| Inventory | Add, edit, delete items; QR code generation; filter by campus/category/status |
| Campus Inventory | Per-campus stats, college/office breakdowns, inventory modal |
| **Department Manager** | Add/delete colleges and offices (Main Campus only) and campuses via UI |
| Requests | Approve/disapprove borrow, item, and service requests |
| Analytics | Status breakdowns, category distribution, most-requested items |
| Reports | Printable summaries |
| Users | View and manage user accounts |
| Settings | Profile and password management |

### User (Custodian)

| Feature | Description |
|---|---|
| Dashboard | Campus KPI cards, request summary, recent requests table |
| Inventory | Browse campus items, filter by category/status |
| Submit Request | Borrow, item, or service request with urgency level |
| Track Requests | View all submitted requests and their statuses |
| Borrow Records | Active borrows, overdue items, return history |
| Settings | Profile and password management |

---

## Installation

### Requirements
- PHP 7.4 or higher
- Apache or Nginx (XAMPP recommended for local)
- No database needed

### Steps

1. Clone or copy the project into your web server root (e.g. `htdocs/ManageMo`)
2. Ensure `config/` is writable (for `departments_custom.json`)
3. Open your browser:
   ```
   http://localhost/ManageMo/
   ```

### Default Accounts

| Role | Email | Password |
|---|---|---|
| Admin | admin@university.edu | Admin@123 |
| User | user@university.edu | User@123 |

---

## Project Structure

```
ManageMo/
├── admin/
│   ├── dashboard.php          # Admin dashboard with charts
│   ├── inventory.php          # Full inventory management
│   ├── inventory-campus.php   # Campus overview + department manager
│   ├── campus-detail.php      # Single campus detail view
│   ├── requests.php           # Request approval/management
│   ├── analytics.php          # Charts and analytics
│   ├── reports.php            # Printable reports
│   ├── users.php              # User management
│   └── settings.php           # Admin settings
│
├── user/
│   ├── dashboard.php          # User dashboard
│   ├── inventory.php          # Browse campus inventory
│   ├── requests.php           # Submit new request
│   ├── my-requests.php        # Track submitted requests
│   ├── borrow-records.php     # Borrow history
│   └── settings.php           # User settings
│
├── config/
│   ├── constants.php          # BASE_URL and app constants
│   ├── functions.php          # Auth, session, and helper functions
│   ├── data.php               # All mock data + CRUD helpers
│   ├── mock_data.php          # Supplemental mock data
│   └── departments_custom.json # User-added colleges, offices, campuses
│
├── includes/
│   ├── header.php             # HTML head + Bootstrap/FA links
│   ├── navbar.php             # Sidebar navigation + toggle logic
│   ├── topbar.php             # Top bar with user avatar
│   └── footer.php             # Bootstrap JS + closing tags
│
├── css/
│   └── style.css              # Global design system (sidebar, cards, badges)
│
├── assets/
│   ├── pics/                  # Logo, background images
│   ├── uploads/               # File uploads
│   └── qrcodes/               # Generated QR codes
│
├── index.php                  # Split-panel login page
└── logout.php                 # Session destroy + redirect
```

---

## Data Layer

All application data lives in `config/data.php` as PHP arrays. No SQL or ORM is used.

### Key functions

| Function | Returns |
|---|---|
| `getUsers()` | All user accounts |
| `getCampuses()` / `getAllCampuses()` | All campuses (defaults + custom) |
| `getInventory()` | All inventory items |
| `getRequests()` | All borrow/item/service requests |
| `getBorrowRecords()` | All borrow history records |
| `getUserOwnedItems()` | Items tracked as user-owned |
| `getMainCampusColleges()` | Main Campus colleges (defaults + custom) |
| `getMainCampusOffices()` | Main Campus offices (defaults + custom) |

### Custom persistence (`departments_custom.json`)

User-added colleges, offices, and campuses are saved here and merged with defaults at runtime. Default entries (8 campuses, 8 colleges, 14 offices) cannot be deleted through the UI.

```json
{
    "colleges": { "CON": "College of Nursing (CON)" },
    "offices":  { "OAR": "Office of Alumni Relations (OAR)" },
    "campuses": [
        { "id": 9, "name": "Magalang Campus", "location": "Magalang, Pampanga", "description": "", "colleges": [] }
    ]
}
```

---

## Design System

- **Primary color**: `#8B0000` (maroon)
- **Font**: System UI stack (`-apple-system`, `Segoe UI`, etc.)
- **Cards**: White background, `1px solid #e5e7eb` border, 8px radius
- **KPI cards**: Colored full border matching icon color
- **Sidebar active**: Maroon background (`#8B0000`), white text
- **No gradients, no glassmorphism** on internal pages
- **Login page**: Split-panel — blurred/dark background image left, white form right

CSS variables defined in `css/style.css`:
```css
--primary:   #8B0000;
--primary-dk:#6B0000;
--border:    #e5e7eb;
--bg:        #ffffff;
--bg-muted:  #f7f7f7;
--text:      #111111;
```

---

## Request Workflow

```
Submitted (pending) → Approved / Disapproved → Delivered → Returned
```

### Request Types
- **Borrow** — temporary use with expected return date
- **Item** — request procurement of new items
- **Service** — request maintenance or repair

### Urgency Levels
`Low` → `Medium` → `High` → `Critical`

---

## Inventory Statuses
`available` → `borrowed` / `maintenance` / `damaged` / `requested`

---

## Campus & Department Management

Go to **Admin → By Campus → Manage Departments**:

- **Colleges tab** — Add/delete colleges for Main Campus (abbreviation + full name)
- **Offices tab** — Add/delete administrative offices for Main Campus
- **Campuses tab** — Add/delete PSU campuses (name, location, description)

> Colleges and Offices are scoped to **Main Campus only**. Other campuses do not have sub-department breakdowns.

---

## QR Codes

Each inventory item is assigned a unique QR code ID (e.g. `QR-A1B2C3D4E5`). Codes are rendered via the QRServer API and can be printed for physical asset labeling.

---

## Version

**ManageMo v1.0.0** — Pampanga State University, 2026
