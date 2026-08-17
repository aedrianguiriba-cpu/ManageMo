# ManageMo — Entity Relationship, Data Flow & Process Diagrams

**Project:** ManageMo — Inventory & Asset Management
**Client:** Pampanga State University
**Rev:** 2.0.0
**Source:** `database/schema.sql`
**Sheets:** 1 of 3 — ERD · 2 of 3 — DFD · 3 of 3 — Flowchart

The full data model and information flow for the inventory & asset
management system — the PHP web app (Supabase/Postgres backend) and the
companion Flutter delivery-scanner app.

---

## Sheet 01 — Entity Relationship Diagram

The seven live tables in the Supabase database. `request_items` is defined
in `schema.sql` but does not exist in the running database (confirmed by a
direct query) — it's dead code, so it's excluded here. Departments' two
dashed lines are **not** real foreign keys: `college_id` on Users and
Inventory is a free-text abbreviation (e.g. `"CCS"`) matched against
`Departments.abbreviation` in application code (`getMainCampusColleges()`),
with no DB constraint enforcing it.

```mermaid
%%{init: {'theme':'base', 'themeVariables': {
  'primaryColor':'#123E63',
  'primaryBorderColor':'#8FD1EF',
  'primaryTextColor':'#EAF6FD',
  'lineColor':'#8FD1EF',
  'tertiaryColor':'#0E3A5F',
  'fontFamily':'ui-monospace, SFMono-Regular, Consolas, monospace',
  'fontSize':'13px'
}, 'er': {'entityPadding':18, 'minEntityWidth':160}}}%%
erDiagram
    CAMPUSES ||--o{ USERS : "based at"
    CAMPUSES ||--o{ INVENTORY : "located at"
    CAMPUSES ||--o{ USER_OWNED_ITEMS : "located at"
    USERS ||--o{ REQUESTS : "submits"
    USERS ||--o{ BORROW_RECORDS : "borrows as"
    USERS ||--o{ USER_OWNED_ITEMS : "owns"
    INVENTORY ||--o{ REQUESTS : "requested item"
    INVENTORY ||--o{ BORROW_RECORDS : "borrowed unit"
    REQUESTS ||--o| BORROW_RECORDS : "fulfilled by"
    DEPARTMENTS |o..o{ USERS : "college_id ↔ abbreviation (soft match, no FK)"
    DEPARTMENTS |o..o{ INVENTORY : "college_id ↔ abbreviation (soft match, no FK)"

    USERS {
        bigint id PK
        text email UK
        text password
        text full_name
        text role
        int campus_id FK
        text college_id
        text phone
        int is_active
    }
    INVENTORY {
        bigint id PK
        text qr_code_id UK
        text item_name
        text category
        int campus_id FK
        text college_id
        int quantity
        text status
        text condition
        text condemnation_reason
        text group_id
    }
    REQUESTS {
        bigint id PK
        text request_number UK
        int user_id FK
        int inventory_id FK
        text group_id
        text qr_code_id
        text request_type
        text urgency
        text status
        text delivery_status
        int approved_by FK
        date expected_return_date
    }
    BORROW_RECORDS {
        bigint id PK
        int user_id FK
        int inventory_id FK
        int request_id FK
        date borrow_date
        date expected_return_date
        date actual_return_date
        text status
    }
    USER_OWNED_ITEMS {
        bigint id PK
        text qr_code_id UK
        int user_id FK
        text item_name
        text category
        int campus_id FK
        text condition
        text group_id
    }
    CAMPUSES {
        bigint id PK
        text name
        text location
        boolean is_default
    }
    DEPARTMENTS {
        bigint id PK
        text type
        text abbreviation UK
        text full_name
        boolean is_default
    }
```

**Legend**

| Notation | Meaning |
|---|---|
| `PK` | primary key |
| `FK` | foreign key |
| `UK` | unique key |
| `\|\|--o{` | one-to-many, "many" side optional |
| `\|\|--o\|` | one-to-zero-or-one |
| dashed line | logical/soft reference, not a DB-enforced foreign key |

> `REQUESTS.inventory_id` is nullable — a submitted "Item" request with no
> catalog match has no inventory row until an admin approves it (custom
> items only get counted in inventory on approval). `REQUESTS.approved_by`
> is also a foreign key back to Users (the reviewing admin) — its
> relationship line is omitted above since Users↔Requests is already drawn
> once; the FK is still listed in the Requests attribute box.

---

## Sheet 02 — Data Flow Diagram (Level 1)

How a request moves through the system end to end — from submission,
through admin review and dispatch, to delivery confirmation by either an
admin clicking "Mark Delivered" or the requester scanning the item's QR
code in the mobile app.

```mermaid
flowchart LR
    REQ["Requester (User role)"]
    ADM["Admin / Custodian"]
    EMAIL["Email / SMTP (external)"]

    nAuth("1.0 Authenticate")
    nSubmit("2.0 Submit Request")
    nApprove("3.0 Review and Approve")
    nDeliver("4.0 Dispatch and Confirm Delivery")
    nInventory("5.0 Manage and Retire Inventory")
    nReports("6.0 Analytics and Reports")

    D1[("D1 - Users")]
    D2[("D2 - Inventory")]
    D3[("D3 - Requests")]
    D4[("D4 - Borrow Records")]
    D5[("D5 - Campuses and Departments")]

    REQ --> nAuth
    ADM --> nAuth
    nAuth -->|verify| D1
    D1 -->|result| nAuth

    REQ -->|"borrow / item / service"| nSubmit
    nSubmit -->|"check availability"| D2
    D2 -->|"reserve unit"| nSubmit
    nSubmit --> D3

    ADM -->|"approve / disapprove"| nApprove
    nApprove --> D3
    D3 --> nApprove
    nApprove -->|"create row for custom item"| D2
    nApprove --> EMAIL
    EMAIL -.->|delivery email| REQ

    ADM -->|"mark delivered"| nDeliver
    REQ -->|"scan QR (mobile app)"| nDeliver
    nDeliver --> D3
    D3 --> nDeliver
    nDeliver --> D2
    nDeliver --> D4

    ADM -->|"add / edit / condemn / dispose"| nInventory
    nInventory --> D2
    D2 --> nInventory
    nInventory --> D5

    ADM --> nReports
    D2 -.-> nReports
    D3 -.-> nReports
    D4 -.-> nReports
    D5 -.-> nReports
    nReports -->|"charts and KPIs"| ADM
```

**Legend**

| Shape | Meaning |
|---|---|
| rectangle | External entity — a person or system outside ManageMo |
| circle | Process — a numbered transformation of data |
| cylinder | Data store — a table at rest |
| solid line | write / read-write flow |
| dotted line | read-only flow |

> Process 4.0 is the one place the web app and mobile app converge: both
> "Mark Delivered" (admin) and a QR scan (requester, per unit) run the
> identical delivery-confirmation logic against the same Requests /
> Inventory / Borrow Records stores. Dotted lines mark read-only flows
> (Process 6.0 reading each store for dashboards; the email notice, which
> leaves the system rather than writing to a store).

---

## Sheet 03 — Request Lifecycle Flowchart

Unlike the DFD (which shows data moving between processes and stores), this
is the actual step-by-step path a single request takes, including its two
real decision points: does the admin approve it, and is it a borrow request
(which continues on as an active loan) versus an item/service request
(which ends at "Delivered").

```mermaid
flowchart TD
    START(["Start: user wants to borrow, request, or receive service on an item"])
    A["Log in"]
    B["Choose request type: Borrow, Item, or Service"]
    C["Fill out and submit request"]
    D{"Admin approves?"}
    E["Status: Disapproved"]
    ENDA(["End"])
    F["Status: Approved"]
    G["Admin marks Out for Delivery"]
    H["Confirm delivery: requester scans QR, or admin marks delivered"]
    J{"Request type is Borrow?"}
    K["Create borrow record; inventory unit set to Borrowed"]
    ENDBORROW(["End: item borrowed (see Borrow Status Flow for return)"])
    L["Status: Delivered"]
    ENDDONE(["End"])

    START --> A --> B --> C --> D
    D -- No --> E --> ENDA
    D -- Yes --> F --> G --> H --> J
    J -- Yes --> K --> ENDBORROW
    J -- No --> L --> ENDDONE
```

**Legend**

| Shape | Meaning |
|---|---|
| stadium (rounded ends) | Start / End terminator |
| rectangle | A step or state change |
| diamond | Decision point — exactly one branch is taken |

> Reaching "End: item borrowed" isn't the end of that unit's story — it
> later moves through `active → returned` or `active → overdue`, tracked
> separately in Borrow Records (see the Borrow Status Flow in
> `SYSTEM_DOCUMENTATION.txt`). This flowchart stops there to keep the
> request-submission path readable on its own.

---

*Generated from the live schema + current application logic, not a static spec. — ManageMo v2.0.0*
