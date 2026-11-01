# Court desk — operational interface

Working title, not a confirmed product brand. Public landing pages remain subject to the user's mockup-first preference.

## Direction

An uncluttered community sports bulletin: the day's court schedule leads, with arrivals and payment review adjacent. Derived alternatives considered: court markings, club roster, printed fixture list, community sports bulletin, municipal recreation guide, competition score sheet, and sports newspaper. The direction seed assigned the fourth candidate (seed:ab3f4f7518fbeed1). Oversized type specimens and antique lexicon ornament do not improve daily booking clarity; retain their disciplined hierarchy and consistent alignment instead of decorative motifs.

## System

Premium court navy + ball yellow. Deep navy sidebar (`--navy-900`) beside a cool pale frame; a white working sheet lifted by one soft elevation shadow instead of a border. Navy (`--navy-700`) carries primary actions and focus; optic ball yellow (`--ball`) is the single accent, reserved for the active nav icon, avatar, logo ball, text selection, open-slot hover and the public search CTA. Never use yellow for text on white. No gradients. Tokens live at the end of `public/css/desk.css`. Self-hosted Instrument Sans with tabular figures; its OFL license ships in public/fonts. Sidebar and header share one ground. Schedule and pending payment actions carry the hierarchy. Consistent outline icons support navigation without competing with the task.

## Interaction

Staff choose a date and business, scan a horizontally scrollable court timeline, and select an open half-hour to prefill a booking. A persistent Timeline/Agenda toggle supports different working preferences. Mobile uses compact court agendas and a collapsible navigation menu. Forms group player details, court/time and payment, with a live estimate separating player rental balance from the venue's platform fee; final validation remains on the server. Status has an accessible text label. Errors stay visible with field context. No fabricated production metrics or imagery: seeded content is labeled demo data. Keyboard focus is visible, labels are explicit, and reduced motion is respected.

## Player discovery and checkout

The public concept was generated before implementation, as requested, and is retained at `.impeccable/mocks/public-concept.png`. Carry the navy/ball-yellow palette and Instrument Sans into a split editorial hero, city/venue search, actual approved venue cards and clear payment steps. The generated court photograph in `public/images/illustrative-court.webp` is explicitly illustrative; never use it as a real venue's photograph. The mockup's instant-confirmation language is intentionally corrected in working copy: confirmation follows manual verification of full funds received.

Public checkout is task-focused: date and duration first, available court/time choices, then player details and payment acknowledgement. Guest booking details are session-restricted and are never authenticated by a short reference alone. No deposits, walk-ins or self-reported paid status are supported in public checkout.

## Coach workspace

Coach tools inherit the operational navy/white sheet and Instrument Sans. A separate workspace identity and section navigation distinguish lessons, profile, weekly hours, affiliations and time off. Default to upcoming lessons across venues, with date browsing for historical notes. Coach income copy distinguishes recorded fees from actual payouts. Lesson checkout extends the established player availability pattern with an affiliated-venue selector and explicit court/coaching price breakdown. Private previews are labeled and disable checkout; no sample profile is published as a real coach.
