# ManageMo Data Dictionary

Database: PostgreSQL on Supabase. Source: `database/schema.sql`.
Row level security is disabled on every table because the PHP session handles access control.

**Conventions**
- PK = primary key, UQ = unique, FK (soft) = points to another table but no foreign key is enforced in the database.
- `college_id` on any table holds a `departments.abbreviation`. The department can be a college, an office or a campus.
- Descriptions marked * are inferred from the column name and surrounding code comments, not stated in the schema.

## Tables at a glance

| Table | Purpose |
|---|---|
| users | Accounts for admins and regular users |
| departments | Colleges, offices and campuses (one list) |
| inventory | One row per physical item or stock line |
| requests | Borrow, item, service and condemnation requests |
| request_items | Line items attached to a request |
| borrow_records | History of items lent out and returned |
| user_owned_items | Items a user or department already owns |
| notifications | Messages shown in the notification bell |
| app_settings | Site-wide key/value settings |

## users

| Column | Type | Null | Default | Key / Constraint | Description |
|---|---|---|---|---|---|
| id | BIGSERIAL | No | auto | PK | User ID |
| email | TEXT | No | | UQ | Login email |
| password | TEXT | No | | | BCrypt hash, never plain text |
| full_name | TEXT | No | | | Display name |
| role | TEXT | No | 'user' | admin, user | Decides which side of the system the person sees |
| college_id | TEXT | Yes | | FK (soft) departments.abbreviation | The user's department. Users with the same value share owned items, records and requests |
| phone | TEXT | Yes | | | Contact number |
| is_active | INT | No | 1 | | 1 = can log in, 0 = deactivated |
| reset_token | TEXT | Yes | | | Password reset token* |
| reset_token_expires | TIMESTAMPTZ | Yes | | | When the reset token stops working* |
| created_at | TIMESTAMPTZ | No | NOW() | | Account creation time |
| updated_at | TIMESTAMPTZ | No | NOW() | | Last change |

## departments

| Column | Type | Null | Default | Key / Constraint | Description |
|---|---|---|---|---|---|
| id | BIGSERIAL | No | auto | PK | Department ID |
| type | TEXT | No | | college, office, campus; UQ with abbreviation | What kind of row this is |
| abbreviation | TEXT | No | | UQ with type | Short code. Other tables store this value in `college_id` |
| full_name | TEXT | No | | | Full name |
| location | TEXT | Yes | | | Address. Campus rows only |
| description | TEXT | Yes | | | Short description. Campus rows only |
| is_default | BOOLEAN | No | FALSE | | TRUE = seeded row, protected from deletion. Admin-added rows are FALSE |
| created_at | TIMESTAMPTZ | Yes | NOW() | | Creation time |

## inventory

| Column | Type | Null | Default | Key / Constraint | Description |
|---|---|---|---|---|---|
| id | BIGSERIAL | No | auto | PK | Inventory ID |
| qr_code_id | TEXT | No | | UQ | Code printed on the unit's QR label |
| item_name | TEXT | No | | | Item name |
| category | TEXT | No | | | Category such as Furniture or Electronics |
| description | TEXT | Yes | | | Details |
| college_id | TEXT | Yes | | FK (soft) departments.abbreviation | Owning college, office or campus. NULL = no department tag |
| quantity | INT | No | 1 | | Units in this row |
| status | TEXT | No | 'available' | available, borrowed, requested, maintenance, damaged, condemned, disposed, owned | Current state. `owned` = permanently transferred to a user through an item request. `disposed` = junked, sold or donated after condemnation |
| location | TEXT | Yes | | | Where the item is kept |
| purchase_date | DATE | Yes | | | Date bought |
| cost | NUMERIC(12,2) | Yes | | | Purchase cost |
| condition | TEXT | Yes | | excellent, good, fair, poor | Physical condition |
| condemnation_reason | TEXT | Yes | | | Why the item was condemned |
| condemned_at | TIMESTAMPTZ | Yes | | | When it was condemned |
| condemned_by | INT | Yes | | FK (soft) users.id | Admin who condemned it |
| condemned_condition | TEXT | Yes | | | Condition recorded at condemnation* |
| disposal_notes | TEXT | Yes | | | How it was disposed of |
| disposed_at | TIMESTAMPTZ | Yes | | | When it was disposed of |
| disposed_by | INT | Yes | | FK (soft) users.id | Admin who disposed of it |
| group_id | TEXT | Yes | | | Shared by units added together in one batch |
| acquisition_mode | TEXT | No | 'borrow' | borrow, request, both | Whether users can borrow it, request it, or either* |
| model | TEXT | Yes | | | Model name |
| serial_number | TEXT | Yes | | | Manufacturer serial number |
| created_at | TIMESTAMPTZ | No | NOW() | | Creation time |

## requests

| Column | Type | Null | Default | Key / Constraint | Description |
|---|---|---|---|---|---|
| id | BIGSERIAL | No | auto | PK | Request ID |
| request_number | TEXT | No | | UQ | Readable number such as REQ-00001 |
| user_id | INT | No | | FK (soft) users.id | Who submitted it |
| inventory_id | INT | Yes | | FK (soft) inventory.id | The unit requested. NULL for custom items and some services |
| group_id | TEXT | Yes | | | Links the rows created by one submission. Admin actions apply to the whole group |
| qr_code_id | TEXT | Yes | | | QR code of the requested unit |
| request_type | TEXT | No | | borrow, item, service, condemnation | `item` = procurement or acquire. `condemnation` = user asks to condemn an item they own or borrowed |
| urgency | TEXT | No | 'medium' | low, medium, high, critical | Priority set by the user |
| receiving_method | TEXT | Yes | | delivery, pickup | How the user gets the item |
| reason_for_request | TEXT | Yes | | | Why it is needed |
| service_description | TEXT | Yes | | | Problem description, or the custom item name and quantity |
| expected_return_date | DATE | Yes | | | When a borrowed item is due back |
| date_needed | DATE | Yes | | | Needed-by date for item and service requests |
| quantity_requested | INT | No | 1 | | Units asked for |
| status | TEXT | No | 'pending' | pending, approved, disapproved, delivered, completed | Where the request is in its life |
| delivery_status | TEXT | Yes | | out_for_delivery, delivered | Delivery progress after approval |
| approved_by | INT | Yes | | FK (soft) users.id | Admin who decided |
| approved_at | TIMESTAMPTZ | Yes | | | When the admin decided |
| disapproval_reason | TEXT | Yes | | | Reason given when disapproved |
| scheduled_delivery_date | DATE | Yes | | | Planned delivery date |
| created_at | TIMESTAMPTZ | No | NOW() | | Submission time |
| updated_at | TIMESTAMPTZ | No | NOW() | | Last change |

## request_items

| Column | Type | Null | Default | Key / Constraint | Description |
|---|---|---|---|---|---|
| id | BIGSERIAL | No | auto | PK | Line ID |
| request_id | INT | No | | FK (soft) requests.id | Parent request |
| inventory_id | INT | Yes | | FK (soft) inventory.id | Unit for this line |
| qr_code_id | TEXT | Yes | | | QR code of the unit |
| item_name | TEXT | No | | | Item name |
| quantity | INT | No | 1 | | Units on this line |
| created_at | TIMESTAMPTZ | No | NOW() | | Creation time |

## borrow_records

| Column | Type | Null | Default | Key / Constraint | Description |
|---|---|---|---|---|---|
| id | BIGSERIAL | No | auto | PK | Record ID |
| user_id | INT | No | | FK (soft) users.id | Borrower |
| inventory_id | INT | No | | FK (soft) inventory.id | Item borrowed |
| request_id | INT | Yes | | FK (soft) requests.id | Request that led to the loan. NULL for older records |
| borrow_date | DATE | No | | | Day the item went out |
| expected_return_date | DATE | Yes | | | Due date |
| actual_return_date | DATE | Yes | | | Day it came back. NULL while still out |
| status | TEXT | No | 'active' | active, returned, overdue | Loan state |
| notes | TEXT | Yes | | | Free-text note |
| created_at | TIMESTAMPTZ | No | NOW() | | Creation time |

## user_owned_items

| Column | Type | Null | Default | Key / Constraint | Description |
|---|---|---|---|---|---|
| id | BIGSERIAL | No | auto | PK | Record ID |
| qr_code_id | TEXT | Yes | | UQ | QR code of the owned item |
| user_id | INT | No | | FK (soft) users.id | Owner |
| item_name | TEXT | No | | | Item name |
| category | TEXT | No | | | Category |
| description | TEXT | Yes | | | Details |
| year_owned | INT | Yes | | | Year the user got it |
| college_id | TEXT | Yes | | FK (soft) departments.abbreviation | Department the item belongs to |
| quantity | INT | No | 1 | | Units owned |
| condition | TEXT | Yes | | | Condition. Free text here, unlike inventory |
| notes | TEXT | Yes | | | Free-text note |
| purchase_date | DATE | Yes | | | Date bought |
| group_id | TEXT | Yes | | | Shared by units recorded together |
| created_at | TIMESTAMPTZ | No | NOW() | | Creation time |

## notifications

| Column | Type | Null | Default | Key / Constraint | Description |
|---|---|---|---|---|---|
| id | BIGSERIAL | No | auto | PK | Notification ID |
| user_id | INT | No | | FK (soft) users.id, indexed | Recipient |
| title | TEXT | No | | | Headline |
| message | TEXT | Yes | | | Body text |
| type | TEXT | No | 'info' | info, success, warning, danger | Controls the style |
| link | TEXT | Yes | | | Page opened when clicked |
| is_read | BOOLEAN | No | FALSE | | TRUE once read |
| created_at | TIMESTAMPTZ | No | NOW() | | Time sent |

## app_settings

| Column | Type | Null | Default | Key / Constraint | Description |
|---|---|---|---|---|---|
| key | TEXT | No | | PK | Setting name |
| value | TEXT | No | | | Setting value |
| updated_at | TIMESTAMPTZ | No | NOW() | | Last change |

Known keys:
- `terminology`: `default` shows Borrow and Request. `alt` shows Transferrable and Procurement.
- `borrow_catalog_page_size`: how many items the borrow catalog shows per page. This key is set from Admin Settings and is not in the seed script.

## Relationships

| From | To | Meaning |
|---|---|---|
| requests.user_id | users.id | Who asked |
| requests.approved_by | users.id | Admin who decided |
| requests.inventory_id | inventory.id | Unit requested |
| request_items.request_id | requests.id | Lines of a request |
| request_items.inventory_id | inventory.id | Unit on a line |
| borrow_records.user_id | users.id | Borrower |
| borrow_records.inventory_id | inventory.id | Item lent |
| borrow_records.request_id | requests.id | Request behind the loan |
| user_owned_items.user_id | users.id | Owner |
| notifications.user_id | users.id | Recipient |
| inventory.condemned_by, disposed_by | users.id | Admin who acted |
| users / inventory / user_owned_items .college_id | departments.abbreviation | Affiliation. A text match, not an enforced key |

None of these are database foreign keys. The application is responsible for keeping them consistent.

## Notes

- There is no `campuses` table. Campuses are `departments` rows with `type = 'campus'`.
- `inventory.campus_id`, `users.campus_id` and `user_owned_items.campus_id` were removed. `college_id` is the only ownership field.
- Most ID columns that point at other tables are `INT` while the primary keys are `BIGSERIAL`. This works for current data sizes but the types do not match.
- Seed data in the schema file includes four demo users and sample inventory, requests and borrow records.
