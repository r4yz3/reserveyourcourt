# Pickleball booking platform

<!-- impeccable:product-schema 1 -->

## Platform

web

## Stack

Confirmed: Laravel/PHP, following the recommended lightweight CloudPanel approach. Initial implementation: Laravel 13, Blade, plain CSS and small progressive JavaScript, SQLite for local development, and an installed S3 adapter for private Cloudflare R2 receipt storage. Production MySQL/MariaDB and database-backed jobs are configured framework options; Livewire has not been added.

## Users

- Players in the Philippines finding courts and making reservations.
- Multiple independent pickleball businesses managing venues, courts, customers, staff, and bookings.
- Platform operators managing business onboarding and the marketplace.
- Coaches managing their lessons, weekly hours, time off, venue affiliations and private lesson notes.

## Product Purpose

Help pickleball businesses attract customers and simplify daily operations. Booking is the primary feature. Success means reliable reservations, less scheduling work, and more completed bookings.

## Operating Context

- Philippines; PHP currency; display times in Asia/Manila.
- Customers pay cash or use participating wallets/banks through InstaPay.
- Low-budget Contabo VPS managed with CloudPanel; actual resources remain unknown.
- Use Cloudflare storage and appropriate free services to reduce origin work.
- Confirmed business model: a fixed PHP commission amount per eligible booking, entered and edited by the super admin. Walk-in reservations are removed. New reservations require full rental payment before confirmation; InstaPay remains pending until actual full receipt is verified. Proposed default: fee changes affect new bookings only; existing bookings retain their saved fee. Chargeable event, collection mechanism, tax treatment, cancellation handling, and treatment of other staff-entered bookings remain undecided.

## Capabilities and Constraints

- Multiple businesses on one platform with separate operational data and permissions.
- Booking and business management are core; venue discovery supports customer acquisition.
- Optimize uploaded venue images and receipt images and convert them to WebP, preserving receipt readability.
- Previously discussed PDF handling is a proposal, not a confirmed requirement.
- Payment integration/provider and automatic verification are not selected.
- No measured capacity, launch court count, or production server specification exists yet.

## Brand Commitments

Inspiration: https://www.nextdink.com/ and https://paddleville.vercel.app/. Product name, logo, palette, and typography remain open. These are references, not permission to copy branding or assets.

## Evidence on Hand

The repository began empty. A local venue-operations milestone now exists with explicitly labeled sample venue/court/booking records. No real venue inventory, photographs, customer testimonials, operating metrics, or production credentials have been supplied. Do not present sample records as production evidence.

## Product Principles

1. Booking correctness before feature breadth.
2. Keep each business's data private and enforce access on the server.
3. Make pricing, payment status, and confirmation status explicit.
4. Make mobile booking and front-desk work simple.
5. Keep infrastructure modest and expand based on measurement.

## Coaching release

Private coaching lessons reserve an approved coach and an affiliated court together. The venue collects the full coaching-plus-court price through the existing InstaPay/manual verification workflow. Coach profiles require platform approval; affiliations require venue-owner approval and do not grant venue staff access. Coach payout settlement is manual. Group lessons, packages and automatic payouts remain later work.
