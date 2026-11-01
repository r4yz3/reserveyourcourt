# Court desk

Working title for a Philippine pickleball booking marketplace. The application now includes venue operations and an initial public discovery/guest-checkout flow. See [the project plan](docs/PROJECT-PLAN.md) for the broader marketplace scope.

## Local start

Requirements: PHP 8.3+ with the framework extensions, PDO SQLite (local) or PDO MySQL (production), and GD with WebP plus EXIF for receipt conversion. Composer installs backend dependencies. Node is only needed to build frontend assets.

```powershell
composer install
Copy-Item .env.example .env
php artisan key:generate
php artisan migrate
php artisan app:demo
php artisan serve --host=127.0.0.1 --port=8000
```

The demo command runs only in the local environment, creates an explicitly labeled sample venue with three courts and an empty schedule, and prints a generated password. It refuses to overwrite existing demo accounts. The fee intentionally starts unset: log in as the demo super admin to configure it. Demo accounts are never created by normal migrations or the production seeder.

On this workstation an isolated, checksum-verified PHP 8.5 runtime with GD is installed under `.runtime/php/`. Use `.runtime/php/php.exe` instead of `php` for Artisan and tests. The existing system PHP has no GD. The isolated runtime and local data are ignored by Git.

## Current functionality

- Public approved-venue search, venue profiles, availability selection and session-protected guest checkout.
- Guest InstaPay receipt submission with the same inventory and full-payment verification engine as staff bookings.
- Session login/logout with rate-limited login.
- Business-scoped court agendas, daily totals and payment review inbox.
- Super-admin business onboarding with existing or new owner accounts, readiness checks, approval and suspension.
- Owner-only venue details, receiving payment instructions, court creation and editing of rates, opening hours and active status. Existing booking totals and fees are preserved.
- Maintenance closures block both staff and public checkout, support half-hour boundaries and ranges up to seven days, and protect existing reservations/payment holds. Removing a closure restores availability while keeping its audit history.
- Venue cover photos resized to a maximum 1920 px edge and converted to WebP, with protected access and replacement cleanup.
- Staff-recorded online reservations; new walk-in creation is rejected. Full payment is required before confirmation.
- Half-hour inventory claims with database uniqueness, court-row transactions and idempotent booking submissions.
- Fixed super-admin booking fee versions; immutable fee snapshots for eligible reservations.
- Cash recording with idempotency, cancellation with inventory release, completion after the reserved time.
- InstaPay proof submission, private WebP processing and authorized viewing; no confirmation until explicit full-payment review.
- Timed checkout/review holds, scheduled expiry and transactional reclamation during new reservations.
- Audit records and no-store responses for the operational workspace.

Public checkout currently supports InstaPay with manual venue verification. Guest details remain accessible only within the originating browser session; email/SMS confirmations and cross-device account access are not enabled. Staff invitations, branch-level access, peak pricing, rescheduling, reminders, commission accrual/statements/settlement are subsequent milestones. Online source on the current staff form means a staff-recorded online reservation. No automated bank verification, deductions or refunds occur. The saved platform fee is an assessment snapshot, not money collected by the platform.

## Tests and formatting

```powershell
.runtime/php/php.exe artisan test --compact
.runtime/php/php.exe vendor/bin/pint --format agent
npm install --ignore-scripts
npm run build
```

The suite exercises authorization, tenant isolation, inventory conflicts, idempotency, fee snapshots, cash, receipt state transitions, expiry, HTML escaping and real WebP processing. WebP processing tests require GD. SQLite tests do not substitute for concurrent MySQL/MariaDB validation on the chosen production server.

## CloudPanel / R2 deployment preparation

- Point the site's document root to `public/`; never expose the project root.
- Set production environment, disable debug, configure the production database and secure session cookies, and run migrations through the deployment workflow.
- Install PHP GD with WebP and EXIF. Verify extensions before enabling receipt uploads.
- Configure `R2_ACCESS_KEY_ID`, `R2_SECRET_ACCESS_KEY`, `R2_ENDPOINT`, `R2_RECEIPTS_BUCKET` and `RECEIPTS_DISK=r2_receipts`. The bucket must remain private; the S3 adapter is installed. R2 credentials and live connectivity have not been tested.
- Venue images can use `MEDIA_DISK=r2_media` with `R2_MEDIA_BUCKET` configured. Keep onboarding photos private; use a separate bucket from receipts.
- Run Laravel's scheduler every minute (`php artisan schedule:run`) so held and review reservations expire. Checkout also reclaims expired inventory.
- Prepare the database queue worker and an external mail provider before enabling future notifications. Current booking flows do not send mail.
- Bypass Cloudflare shared caching for application pages and private receipts. Cache only static public assets at this stage.
- Schedule encrypted off-server database backups and prove restoration before accepting real bookings.
- Configure production accounts through a controlled onboarding process. Never run local demo data against production.

No production deployment has been made. Commission accrual rules, cancellation/no-show treatment and venue settlement remain open product decisions.

## Public flow

The homepage and `/courts` show approved non-demo venues with active courts. Draft, suspended and demo venues are excluded. Public photo access requires the same eligibility. The local demo remains private, so an empty public directory is expected until a real business is onboarded and approved.

Guests choose a free time, acknowledge full payment, and receive a 15-minute checkout hold. An optimized private receipt moves the hold into manual review; it never confirms payment by itself. Only authorized venue staff can verify the exact full amount. Guests cannot select cash, walk-ins, paid status or platform fees. Keep this distinction in all customer-facing copy.

Guest references are identifiers, not access tokens. Access also requires the originating session's random capability, whose hash is stored with the booking. Losing the browser session requires contacting the venue. Public pages are conservatively served with no-store headers at this stage; CDN caching of public media can be configured separately without exposing receipts.

## Weather rescheduling

Staff open a confirmed booking and choose **Move reservation** for rain or an unplayable court. Only fully paid confirmed bookings qualify. The replacement must be a future available half-hour slot on an active court at the same venue; original duration, paid price, receipt, reference and platform fee stay unchanged, including moves to a higher-priced indoor court. The original slot is released atomically when the replacement is saved. Conflicts, stale forms and failures preserve the original reservation.

Owners may also close the original court through a chosen time in the same operation. Existing reservations and holds prevent conflicting closures; move those separately first. Schedule history records old/new times, courts, reason and actor. Players see the schedule and reason through their existing private reservation session; staff notes remain private. Agree on the change with the player and contact them manually. No weather forecast integration, SMS or email is sent automatically.

The weather rescheduling form includes a court/date availability picker for the next 90 days. It shows starts that fit the original duration, excludes closures and active bookings/payment holds, and permits partial overlap with the booking being moved. Previewing slots does not release or change the current reservation; availability is rechecked transactionally on save. Public booking and staff rescheduling share the availability calculation.

Staff can open **Weather & court issues** from the court schedule to review a court and an affected time window (up to seven days). Outdoor courts are selected first by default. The paginated list shows overlapping confirmed bookings and unexpired payment holds, distinguishes paid bookings eligible for moving from payment issues, and links owners to court closures. Reviewing the list is read-only. Rescheduled bookings leave the list when they no longer overlap the selected window.

## Coaching: first release

Coaches create an account at `/coach/register`, or add a profile to an existing signed-in account at `/coach`. The dedicated coach portal has upcoming/day lesson lists, a public bio and specialties, a PHP hourly coaching rate, seven-day recurring hours, dated time off, venue affiliation requests, and private per-lesson progress notes and attendance. Coach-only sign-ins open this portal. These accounts receive no venue staff membership automatically, and cannot access unrelated bookings or payment receipts.

Super admins approve coach profiles at `/admin/coaches`; venue owners approve affiliations under **Venue coaches**. Both approvals, an accepting profile with a positive rate and weekly hours, and an approved non-demo venue with payment instructions and active courts are required for public booking. An approved affiliation currently permits all active courts at that venue. Suspending an affiliation or coach stops new bookings without deleting existing lessons. Pausing new lesson bookings does not prevent venue staff from arranging a weather replacement for an existing paid lesson.

Players use `/coaches` to select an approved coach, affiliated venue, 30/60/90/120-minute private lesson and court time. Both resources are held in one booking. Coach conflicts are checked across every affiliated venue. Court rental and coaching fees are stored separately in centavos; the fixed platform fee applies once using the existing fee setting. The venue collects the full combined total via InstaPay, using the existing optimized private WebP receipt workflow and manual exact-amount verification. The 15-minute hold and payment-review deadline protect both resources. Expiration or cancellation releases them. Weather rescheduling retains the original coach and price and requires shared availability.

Coach attendance is recorded after the lesson ends. Coaching notes are visible only to the assigned coach, not to the player or venue staff. Recorded coaching fees are not a payout ledger: coach settlement and refunds must be arranged and reconciled with the venue. No automatic coach payouts, group lessons, packages, recurring bookings, travel buffers or notifications are implemented in this release.

A private, checkout-disabled player-page preview is available from the coach portal. It may show the coach's own approved demo affiliation while that venue stays excluded from the public directory. Local preview account: `coach@court.test`, password `CourtDesk-Demo-Only-2026!`; created only by the local-only `CoachDemoSeeder`. Do not run demo seeding in production.

The coach portal also includes a searchable **Students** roster and a paginated progress history across venues. Roster entries group eligible fully paid lessons by booking name and contact, without creating or claiming verified player identities. Changed names/contacts remain separate; shared details must be clarified with the player. Only the assigned coach can read the history and progress notes. Cancelled/pending bookings remain visible in an established student’s history, but do not count as eligible paid lessons. The local coach demo seeder adds one explicitly labelled completed sample lesson and progress note for review; it records no real transaction.
