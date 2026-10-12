# ORBITDESK
### Online Jobs Portal for Virtual Assistants

ITELEC 4100: Advanced Web Applications Development
Project #1 | Object-Oriented PHP with JavaScript AJAX
Clarence Aron C. Gono | BSIT 4-1 LAB B

---

## Getting it running

You need MAMP (macOS) or XAMPP (Windows) with Apache and MySQL started.
Five minutes, tops.

**1. Drop the folder in place**

Copy the whole `orbitdesk` folder into your web root:

| | Path |
|---|---|
| MAMP (macOS) | `/Applications/MAMP/htdocs/orbitdesk` |
| XAMPP (Windows) | `C:\xampp\htdocs\orbitdesk` |

**2. Import the database**

Open phpMyAdmin. On MAMP that is usually `http://localhost:8888/phpMyAdmin`,
on XAMPP it is `http://localhost/phpmyadmin`. Click the **Import** tab, choose
`sql/orbitdesk.sql`, and hit Go. The file creates the `orbitdesk` database,
builds all five tables, and fills them with sample data. You do not need to
create the database first, the script does it.

**3. Check db/config.php**

The file ships with MAMP's defaults already set:

```php
private $port     = '8889';
private $username = 'root';
private $password = 'root';
```

On XAMPP change those to port `3306`, user `root`, and an empty password.
If MAMP shows a different MySQL port on its start page, use that number.

**4. Open the portal**

| | URL |
|---|---|
| MAMP | `http://localhost:8888/orbitdesk` |
| XAMPP | `http://localhost/orbitdesk` |

**5. Sign in**

There are two sign-in pages on purpose.

**Public sign-in** at `/orbitdesk/login.php` is for virtual assistants and
clients, with a tab for each. Admins are deliberately not listed here, the same
way a real marketplace does not put a staff login next to the customer one.

**Staff sign-in** at `/orbitdesk/admin-login.php` is the admin console door.
It is not linked from any public page and carries a noindex tag, so you have to
know the address. Bookmark it for your demo.

Every account below is listed on its own sign-in screen, so you can click a row
to fill the form instead of typing.

| Page | Email | Password | What it shows |
|---|---|---|---|
| login.php | jasmine.ocampo@gmail.com | va123 | verified VA, can apply |
| login.php | paolo.santos@gmail.com | va123 | pending VA, applying is locked |
| login.php | alicia@northgateproperty.com | client123 | verified client, can post jobs |
| login.php | owen@driftwoodtravel.com | client123 | pending client, posting is locked |
| admin-login.php | admin@orbitdesk.ph | admin123 | full admin console |
| admin-login.php | mariel@orbitdesk.ph | mod123 | full admin console |

Every seeded client uses `client123` and every seeded VA uses `va123`.

Anyone can also create their own account from **Create an account** on the
sign-in screen. New signups land in the moderation queue as pending.

If the page loads but says the database connection failed, it is almost always
the password or the port in `db/config.php`. Nothing else needs editing.

---

## What is in the box

```
orbitdesk/
├── index.php                  admin console shell, all sections and modals
├── login.php                  public sign-in, VAs and clients
├── admin-login.php            staff sign-in, not linked publicly
├── register.php               public signup for VAs and clients
├── client.php                 client portal
├── va.php                     virtual assistant portal
├── db/
│   ├── config.php             Database class, PDO connection (singleton)
│   ├── myDB.php               abstract parent class + CrudInterface
│   └── request.php            the single AJAX endpoint, switch router
├── classes/
│   ├── Admin.php              admin accounts, sign-in, moderation
│   ├── Client.php             companies posting work
│   ├── VirtualAssistant.php   the talent side
│   ├── Job.php                job postings
│   └── JobApplication.php     junction between jobs and VAs
├── assets/
│   ├── css/style.css          external stylesheet
│   ├── js/script.js           external AJAX controller
│   ├── js/login.js            sign-in script
│   └── vendor/                jQuery, Bootstrap, and fonts, bundled locally
├── sql/orbitdesk.sql          schema plus sample data
└── docs/erd.png               entity relationship diagram
```

Everything the browser needs is inside the folder. jQuery, Bootstrap, and both
webfonts are bundled in `assets/vendor/` instead of loaded from a CDN, so the
system still works and still looks right if the lab wifi drops during your
presentation. Nothing calls out to the internet.

---

## How each requirement is met

**CRUD.** Full create, read, update, and delete on all five tables, done
through the interface. No operation reloads the page.

**Table relationships.** Five tables, five foreign keys:

- `tbl_admins.id` → `tbl_clients.verified_by` (one admin verifies many clients)
- `tbl_admins.id` → `tbl_vas.verified_by` (one admin verifies many VAs)
- `tbl_clients.id` → `tbl_jobs.client_id` (one client posts many jobs)
- `tbl_jobs.id` → `tbl_applications.job_id` (one job receives many applications)
- `tbl_vas.id` → `tbl_applications.va_id` (one VA submits many applications)

`tbl_applications` is the junction table, so many VAs can apply to many jobs,
and each application carries its own status.

**OOP.** Five concepts, and you can point at each one in the code:

| Concept | Where |
|---|---|
| Encapsulation | `Database` keeps its credentials private, `myDB` keeps `$conn` protected |
| Abstraction | `myDB` is abstract, it can never be instantiated on its own |
| Inheritance | all five model classes extend `myDB` and reuse its CRUD methods |
| Polymorphism | each child overrides `select()`, `search()`, and `validate()` with its own behaviour |
| Interface | `CrudInterface` forces every model to expose the same five methods |
| Singleton | `Database::getInstance()` creates one connection and reuses it |

**AJAX and jQuery.** Every single operation goes through `$.ajax` to
`db/request.php`, including sign-in and sign-out. The search box is debounced,
so it waits 300ms after your last keystroke instead of hitting the server on
every letter.

**Bootstrap plus external CSS and JS.** Bootstrap 5 handles the grid and the
modals. `assets/css/style.css` and `assets/js/script.js` are external files,
as required. There is no inline styling or inline script anywhere except the
one line in `index.php` that passes the signed-in admin to JavaScript.

**MySQL.** InnoDB tables with real foreign key constraints and cascade rules,
not just matching column names.

---

## The three account types

**Virtual assistant** (`va.php`) browses open roles from verified clients,
applies with a cover note and rate, tracks every application, withdraws the
ones they change their mind about, and edits their own profile.

**Client** (`client.php`) posts jobs, edits and deletes them, reads every
applicant on their own postings, and moves people through pending,
shortlisted, hired, and rejected.

**Admin** (`index.php`, reached through `admin-login.php`) runs the console:
the moderation queue, plus full CRUD across all five tables. The admin door is
kept off the public sign-in page on purpose. Hiding the page is not the actual
security though, that comes from the session check: opening `index.php` while
signed in as a client or a VA redirects you straight back out.

Each type gets its own landing page and its own JavaScript file, and the
router checks the account type on every single request. A client cannot call
an admin action by guessing its name, because `requireUser('admin')` reads the
type from the session, not from the form.

Two rules are worth demonstrating, since they are easy to ask about:

**Ownership is checked server side.** When a client saves a job, the
`client_id` is taken from the session, not from the submitted form. If you
edit the form in devtools to post someone else's ID, it is ignored. Same on
the VA side: `va_id` comes from the session when applying. Editing, deleting,
or viewing applicants on a job you do not own returns an error instead of
data.

**Pending accounts are limited.** A pending client or VA can sign in and look
around, but posting and applying are blocked until an admin verifies them.
That is enforced in `requireVerified()` on the server, not just hidden in the
interface. Sign in as one of the pending demo accounts to show it.

---

## The admin layer

This is the part worth demonstrating, since it is what your instructor asked
for.

Clients and virtual assistants do not just appear on the marketplace. Every new
profile is created with a status of **pending** and lands in the moderation
queue. An admin reviews it and either verifies or rejects it. When they decide,
the system stamps `verified_by` and `verified_at` on the profile, so every
review traces back to the admin who made it. You can see this on the Clients
and Virtual assistants screens under the "Reviewed by" column.

Only verified clients can be picked when posting a job, and only verified VAs
appear in the application form. That is enforced server side in
`Client::verifiedOptions()` and `VirtualAssistant::verifiedOptions()`, not just
hidden in the interface.

The **Moderation** screen is where this happens. The sidebar badge shows how
many profiles are waiting.

---

## A 90 second demo path

1. Sign in as the admin. Point at the dashboard: the numbers are live SQL
   aggregates, not hardcoded.
2. Go to **Job postings**. Type "bookkeeping" in the search box. Results filter
   as you type and the page never reloads. Open the browser network tab if you
   want to prove it.
3. Click the applicant count on any card. The drawer lists everyone who applied
   through a three-table JOIN. Change someone's status from the dropdown, it
   saves instantly.
4. Post a job with the + button. Submit it empty first to show the server side
   validation, then fill it in properly.
5. Go to **Moderation**. Verify a pending profile. Jump to **Virtual
   assistants** and show that your name is now in the "Reviewed by" column.
6. Delete the job you created. The confirmation warns that its applications go
   with it, because the foreign key cascades.

---

## Questions you will probably get

**"Where is the OOP, exactly?"**
`db/myDB.php`. It is an abstract class that implements `CrudInterface` and
holds every shared CRUD method. The five classes in `classes/` extend it. A
child like `Job` only writes the query parts that are specific to jobs, the
insert, update, and delete logic is inherited.

**"Why is there only one PHP file handling requests?"**
`db/request.php` is a front controller. Every AJAX call posts an `action`, a
switch statement routes it to the right method on the right object. It keeps
routing in one readable place instead of scattering twenty small PHP files
around the project.

**"How are you preventing SQL injection?"**
Every query runs through `myDB::run()`, which uses PDO prepared statements with
bound parameters. Values never get concatenated into SQL. Emulated prepares are
turned off in `config.php`, so the statements are prepared by MySQL itself.

**"What happens if I delete a client with jobs on it?"**
The foreign keys use `ON DELETE CASCADE`, so the jobs go, and the applications
on those jobs go too. The confirmation dialog says so before you commit.

**"Are the passwords stored as plain text?"**
No. `Admin::insert()` runs `password_hash()` before the row is written, and
sign-in uses `password_verify()`. The password column never gets sent to the
browser, `Admin::select()` lists the columns explicitly and leaves it out.

**"What stops an admin from locking everyone out?"**
An admin cannot delete the account they are currently signed in with. The check
is in `db/request.php` under `delete_admin`, and it compares the submitted ID
against the one in the session. Accounts can also be set to inactive, and
`Admin::authenticate()` refuses to sign in an inactive account.

**"Why does the search wait before running?"**
It is debounced by 300ms in `script.js`. Without it, typing a nine letter word
fires nine queries. With it, you get one.

---

## Sample data

4 admins, 10 clients, 14 virtual assistants, 16 job postings, and 26
applications spread across pending, shortlisted, hired, and rejected. Three VA
profiles and two client profiles are left pending on purpose so you have
something to verify during the demo.
