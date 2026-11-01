# Project plan — Philippine pickleball booking marketplace

Planning baseline: 31 October 2026. Implementation started 1 November 2026. The initial local milestone includes staff authentication, business-scoped court agendas, court creation, booking inventory, fee settings, cash records, expiry, private WebP receipt conversion and manual full-payment review. See README.md for setup and current limitations. A subsequent milestone adds super-admin business onboarding, owner-managed profiles/payment instructions, approval/suspension and optimized private venue cover photos. Public approved-venue discovery, availability selection and session-protected guest InstaPay checkout are also implemented. Court editing and maintenance closures now protect both staff and public availability while retaining existing booking snapshots. Production deployment, notifications and commission settlement remain in progress.

## 1. Confirmed decisions

- Multiple independent businesses; each can have multiple branches and courts.
- Philippines, PHP, mobile-friendly web application.
- Cash and bank/wallet payments through InstaPay.
- Laravel; modest Contabo VPS with CloudPanel; Cloudflare R2 for files.
- Optimized WebP uploads for photos and receipt images.
- Revenue from a fixed PHP commission amount per eligible booking, entered and edited by the super admin. Walk-in reservations are removed. Full payment is required before confirmation. No initial amount has been supplied; configuration is required before paid platform bookings launch.
- Image mockups before coding public landing/showcase pages. Operational screens are designed directly in code.
- NextDink and Paddleville are inspiration. NextDink's public text was accessible; Paddleville's visual interface has not been reliably inspected.

Everything below is a proposed implementation unless identified as confirmed.

## 2. Launch scope

### Players

- Search published venues by city/name; filter basic facilities and court type.
- Venue profile: actual photos, address/directions, facilities, policies, prices, opening hours.
- Choose date, court, start time, and duration; see an itemized PHP total.
- Reserve with name and contact details; guest access through a secure management link. Account linking can be offered after checkout.
- Choose supported payment method, submit proof, and see explicit pending/confirmed status.
- View booking details and request cancellation or rescheduling under the saved policy.
- Share venue links and rebook. Initial discovery uses indexed filters; maps can be external direction links.

### Businesses

- Apply for a business account; platform approval before public publication.
- Manage branches, courts, opening hours, closures, peak/off-peak prices, and policies.
- Invite owner/manager/front-desk staff with branch-scoped permissions.
- Daily court calendar; record fully paid reservations using the same inventory as online checkout.
- Verify received transfers, verify full rental payments, cancel or reschedule with an audit trail.
- Payment review inbox, today's arrivals, check-in, completion, no-show recording.
- Reports: completed bookings, booked hours, available hours, occupancy, collected payments, outstanding balances, cancellations, commissions.
- Download filtered exports limited to the business's own data.

### Platform operations

- Approve, reject, and suspend businesses; moderate publication separately from existing booking servicing.
- Inspect support cases with scoped, logged access; do not expose every receipt to ordinary support roles.
- Super admin enters and edits the fixed PHP booking fee; audit changes with actor, timestamp, previous amount, and new amount. Produce venue statements from versioned commission settings.
- Track amounts billed, received, disputed, credited, and overdue.
- Platform totals must distinguish gross booking value, venue collections, commission earned, and commission actually collected.

### Deferred

Tournaments, matchmaking, leagues, memberships, native apps, internal wallets, escrow, automated split settlement, advanced POS, and multi-country support.
Open-play sessions, waitlists, promo codes, and referrals follow a stable booking pilot.

## 3. Payment and commission model

### Recommended launch mechanism — subject to confirmation

Players pay each venue directly. The platform records commission owed and invoices/reconciles venues periodically. This avoids claiming automated settlement without a provider integration. Collection leakage and manual reconciliation are explicit commercial risks.

- Confirmed: super admin controls the fixed PHP booking fee; business owners and staff cannot edit it. Proposed MVP: one platform-wide fee, without per-business overrides unless requested. Validate a non-negative centavo amount; an unset fee is not silently treated as zero. Explicit zero can represent a deliberate fee waiver.
- Version commission settings and snapshot the fee amount, setting version, booking source, with each booking. Proposed default: future fee changes do not alter existing bookings.
- Confirmed: walk-in reservations are no longer supported. Preserve historical records and saved fees; do not create new walk-in reservations.
- Assign online reservation source on the server. Reject walk-in creation, including direct requests. Keep historical source values for audit purposes.
- Proposed chargeable event: completed booking; cancellations/no-shows/refunds require a commercial decision before launch.
- Commission unit: one eligible reservation, regardless of rental price or duration. Deposits and balance payments belong to the same booking and do not create additional fees. Eligibility for discounts, complimentary bookings, and other exceptions remains open.
- Do not invent a fee amount or treat manually marked payments as verified provider transactions.
- Use an append-only ledger with adjustments/reversals; record external invoice/payment references and dispute history.
- Draft statements include booking references, eligible booking count, fixed fee per booking, accrued amount, adjustments, and balance. Do not represent these as compliant tax invoices until requirements are established.
- Decide how staff phone/other bookings, complimentary bookings, discounts, reschedules, and partial refunds affect commission.
- Paid refunds go back through the venue; platform records the decision and evidence, not a fictitious automatic refund.

### Customer payment flow

1. Server validates price, policy, and availability and atomically acquires inventory.
2. For transfers, show the venue's provider-issued QR or verified bank details and exact amount.
3. Customer transfers in their own bank/wallet application, then submits reference, amount, and image proof.
4. Venue checks actual funds received; screenshot submission alone never marks payment paid.
5. Confirmation requires verified full payment. Cash is confirmed only with the exact full rental amount recorded; InstaPay remains unconfirmed until full funds are verified.

Proposed defaults for pilot validation: 15-minute checkout hold; proof submitted before expiry enters a separate review reservation with a clearly displayed two-hour deadline, bounded by the booking start. Outside staffed verification hours, disclose the next review window or disable transfer checkout for near-term slots. These timings are proposals, not promises.

Late payment or rejected proof never resurrects an expired slot automatically. Record an exception, check inventory again, and let staff offer another slot or arrange a refund. Cash reservations require full payment at creation. No unpaid or partially paid cash reservation may be confirmed.

## 4. Booking correctness

- Separate booking lifecycle from payment lifecycle and commission lifecycle.
- Booking: held, awaiting review, confirmed, completed, cancelled, expired, no-show.
- Payment attempts: submitted, verified, rejected; derive unpaid/partial/paid/refund-due/refunded from ledger amounts.
- Commission entries: pending, accrued, reversed; statement settlement tracked independently.
- A booking concerns one court and one continuous interval in the MVP. Multi-court baskets are deferred.
- Store money as integer centavos and timestamps in UTC; display Asia/Manila with explicit date/time.
- Proposed 30-minute inventory units; venues choose allowed durations in multiples of that unit.
- Use database transactions, unique court/time-unit inventory claims, and deterministic lock ordering. Expiration, closures, payment approval, and rescheduling use the same allocation service.
- Expiry is enforced during booking transactions as well as scheduled cleanup, so a delayed scheduler does not block courts forever.
- Rescheduling acquires replacement inventory before releasing the original, within one transaction.
- Snapshot price components, cancellation terms, full-payment rules, and commission agreement version.
- Idempotency prevents repeated checkout clicks or retried jobs from creating duplicate bookings, payments, or commission entries.
- Block overlap with closures and reject invalid/past times. Admin overrides remain audited and cannot double-book inventory.

## 5. Architecture and data boundaries

Proposed application: Laravel with Blade templates, Livewire for calendar/booking interactions, a shared relational database, and database-backed jobs. Build frontend assets locally or in CI; production serves compiled files without a Node runtime service.

Core records: users, businesses, memberships, branches, courts, opening rules, closures, price rules, bookings, booking items, inventory claims, payment attempts, payment ledger entries, media, commission agreements, commission entries, statements, statement payments, notifications, audit events.

- Global player identity; business-scoped customer relationships. No business can browse a player's activity elsewhere.
- Tenant-owned rows carry business identifiers; enforce scoped queries, authorization policies, relationship checks, and suitable composite foreign keys.
- Apply the same isolation to queued jobs, exports, receipt URLs, caches, and reporting.
- Platform administration is a distinct role with explicit privileges and logged access.
- Protect guest booking links with high-entropy tokens, expiration/revocation, and restricted actions; hide tokens and sensitive URLs from logs.
- Public availability is advisory; checkout rechecks the authoritative database.
- Keep Redis, WebSockets, search servers, microservices, and Workers out of the initial critical path.

## 6. Upload and WebP pipeline

These are initial engineering limits, to be validated with representative Philippine wallet/bank receipts.

- Accept JPEG, PNG, and WebP images initially. Proposed limit: 10 MB input and 20 megapixels per image. HEIC support depends on tested conversion support; explain unsupported formats clearly.
- Browser performs orientation correction, resizing, and WebP conversion when supported, with visible progress and a fallback for unsupported browsers.
- Server validates actual format, decoded dimensions, tenant ownership, output size, and decode limits. Do not trust filenames, MIME headers, or browser conversion.
- Venue photos: proposed maximum 1920 px long edge, WebP quality around 80; produce 480/960/1920 variants only as needed. Never upscale.
- Receipt images: proposed maximum 2400 px long edge, quality around 92; use lossless WebP when necessary for legible text. Target 300–800 KB where feasible; readability wins over a fixed byte target.
- Avoid repeatedly re-encoding an already valid optimized WebP. Decode/check safely and re-encode only when policy requires it.
- Reject unreadable proof with an actionable retry path. Verify amounts, reference digits, timestamps, and QR scanning on a receipt test set; do not claim automatic OCR/payment verification.
- Strip unnecessary metadata, retain necessary audit timestamps in application records, and use random storage keys.
- Persist only accepted optimized output; remove temporary source files after successful storage. On processing failure, clean up safely and allow retry.
- Public venue media and private receipt media use separate storage policies. Private proof access requires authorization and short-lived links.
- Any direct upload uses private quarantine first; unvalidated objects cannot become public. Bound processing concurrency to protect the VPS.
- PDF receipts are excluded from initial upload scope pending user confirmation; never silently rasterize or discard pages.
- Define receipt retention/deletion policy before launch; keep financial audit records separate from image retention.

## 7. Infrastructure and operational budget

- Contabo + CloudPanel: Nginx, compatible PHP-FPM, MySQL/MariaDB, scheduler, and one supervised queue worker initially.
- Confirm actual CPU/RAM/disk, OS support, PHP extensions, database version, and WebP support before provisioning choices are locked.
- Cloudflare: DNS/proxy and static-asset caching. Public R2 media behind an appropriate production custom domain; private proofs through authorized temporary links.
- Bypass shared cache for checkout, availability, dashboards, authentication, and receipts. Cache public descriptive content only with explicit invalidation.
- R2 Standard free allowance currently includes 10 GB-month, 1 million Class A and 10 million Class B operations monthly; egress is free. Overages are billable, so no zero-cost promise.
- Use an external transactional email provider. SMS is optional and budgeted separately; do not assume Cloudflare supplies outbound transactional mail.
- Compress media, paginate lists, index business/court/date queries, and avoid continuous polling on inactive screens.
- Separate production/staging configuration and storage. Serve Laravel's public directory only; keep debug off and secrets outside source control.
- Encrypted database backups off-server, independent retention, restore drill, log rotation, disk/queue/error/backup monitoring.
- Establish a measured pilot load target after actual venue/court counts and VPS specs are known. No unsupported concurrent-user capacity claim.

## 8. UX and design workflow

- Public discovery and venue pages prioritize real court photography, location, transparent prices, and visible booking entry points.
- Booking prioritizes date/time selection, total price, hold deadline, payment instructions, and clear confirmation state.
- Staff screens prioritize today's court calendar, arrivals, balances, and unverified transfers. Use a mobile agenda and a wider-screen court/time grid.
- Platform admin prioritizes venue onboarding, support, and commission reconciliation.
- Inventory all real assets before choosing imagery. Use labeled sample content in prototypes, never invented venue/customer claims.
- Impeccable: durable product context, UX planning, edge cases, and later interface critique.
- Taste Skill: distinct visual concepts for public pages and a separate restrained approach for operational screens.
- Awesome DESIGN.md: reference collection; no brand identity selected yet.
- Web Design Guidelines: implementation audit for accessibility, forms, keyboard use, responsive behavior, and reduced motion.
- Image mockup first for public pages, as selected by the user; then implement and compare at mobile/tablet/desktop widths. Mockups do not substitute for usable calendar/payment interactions.
- Playwright MCP: validate flows and capture visual evidence when available in the active session; registration alone is not a completed browser test.

## 9. Delivery phases and exit gates

1. Product decisions and UX: finalize commission/payment rules, route map, wireframes, and public-page mockup. Exit: no unspecified booking/commission behavior required to build.
2. Foundation: Laravel setup, authentication, tenant membership/permissions, schema, storage, queue, CI, and deployment skeleton. Exit: cross-business access denied in meaningful automated tests.
3. Inventory engine: prices, opening rules, closures, holds, fully paid reservations, rescheduling, expiry. Exit: simultaneous requests cannot allocate the same court/time; retries and scheduler delays behave correctly.
4. Player booking and payments: discovery, venue pages, checkout, proof uploads, manual verification, confirmations. Exit: cash and transfer paths work end to end, including upload failures and late payments.
5. Business operations and commission: staff calendar, check-in, reports, commission ledger, statements and reconciliation. Exit: totals reconcile across completion, discount, cancellation and refund cases.
6. Pilot hardening: authorized receipt access, mobile/accessibility checks, restore drill, deployment rehearsal, load tests against actual server. Exit: pilot venues can operate without developer intervention for routine bookings.
7. Growth iteration: measure discovery-to-booking conversion, repeat booking, off-peak occupancy, verification delay, no-shows and overdue commission; choose open play/promotions/waitlists from evidence.

No fixed delivery dates are promised before scope and working availability are known.

## 10. Required verification before pilot

- Tenant isolation in HTTP endpoints, jobs, exports, media, reports and guest links.
- True concurrent booking requests against the production database engine; closures, expiry and rescheduling race cases.
- Money/commission calculations, setting snapshots, duplicate retries, rejected partial payments, refunds and adjustments. Verify walk-in creation is rejected, only super admins can change fees, fee changes preserve existing snapshots, and confirmation requires full payment.
- Oversized/corrupt/disguised uploads, orientation, receipt legibility, WebP decoding and QR scanning where present.
- Private proof URLs expire; public CDN cannot serve receipt content.
- End-to-end mobile booking, staff cash booking, manual transfer approval, cancellation, and statement reconciliation.
- Queue failure recovery, mail retry behavior, backup restore, and deployment rollback/migration strategy.

## 11. Open decisions

Blocking the booking/payment build: commission chargeable event; treatment of staff phone/other bookings, cancellations, no-shows and refunds; venue billing frequency and payment method; review deadlines and staffing hours. The super admin will configure the actual fixed fee before launch; walk-in reservations are removed and full payment before confirmation is confirmed. Platform-wide fee scope and prospective fee changes are proposed defaults.

Before design: product name and any real brand/venue assets. Use a clearly labeled working title meanwhile.

Before pilot: VPS specs, pilot venue/court counts, transactional mail provider, initial city coverage, privacy/retention terms and business agreements. PDF receipts remain optional and unconfirmed.

## 12. Source references checked

- NextDink public product and booking information: https://www.nextdink.com/ and https://www.nextdink.com/courts
- Paddleville reference (visual inspection still pending): https://paddleville.vercel.app/
- Laravel deployment and supported server requirements: https://laravel.com/docs/deployment
- Cloudflare R2 current pricing: https://developers.cloudflare.com/r2/pricing/
- Cloudflare caching behavior: https://developers.cloudflare.com/cache/get-started/
- PPMI explanation of InstaPay and merchant QR payments: https://www.philpayments.org.ph/instapay

## 13. Tools and plugins

Installed: Impeccable, Taste Skill, Web Design Guidelines; Awesome DESIGN.md downloaded locally; Playwright MCP registered. Cloudflare plugin search returned no matching listing. No extra plugin is necessary for this planning phase. The plan does not claim a live browser audit, server deployment, or payment integration has been performed.
