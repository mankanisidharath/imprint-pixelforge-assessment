# Decisions — what we chose and why

Append-only log. Newest first.

---

## 18 Nov 2026 — Exercise the integrated provider lifecycle

Use signed HTTP webhook requests, a synchronous submission queue, fake provider HTTP responses, and the real settler in end-to-end tests. The early-webhook case delivers the event while the provider POST is in flight, exercising the race at the actual integration boundary. Migration checks use disposable in-memory SQLite to avoid touching a configured database.

---

## 18 Nov 2026 — Integrate submission with shared generation options

Preserve the signed webhook acceptance test and both controller test sets while integrating submission. Request validation uses the existing shared generation options, keeping accepted values and limits consistent with the creation page and factory.

## 18 Nov 2026 — Integrate the reviewed session history by fast-forward

Local `main` was an ancestor of the reviewed session branch. The remaining shared-code refactor and documentation were committed on `feat/pixelforge-shared-contracts`, then integrated into local `main` by fast-forward. This preserves the money and settlement commits together with their reviewed follow-up changes.

The integration check passed all 80 tests using isolated in-memory SQLite, Pint, targeted ESLint, and the production frontend build. The existing TypeScript type-resolution issue remains documented. Remote publication is a separate pending step.

## 18 Nov 2026 — Apply markup with integer arithmetic and upward nanodollar rounding

The billing rule adds the configured integer markup percentage to actual provider cost and rounds upward to a whole nanodollar. For a 25% markup, a provider cost of 101 nano produces a final charge of 127 nano.

The whole-hundreds split is an implementation choice that keeps intermediate calculations in PHP's integer range. Direct multiplication by 125 can overflow before division by 100 even when the final amount fits. Multiplication by a decimal percentage would use floating-point arithmetic and violate the integer-money contract.

Estimate bounds describe uncertainty in text and reasoning token counts before completion. Settlement calculates the recorded final charge from actual usage and the generation's fixed rates. Balance debiting remains a future phase.

## 18 Nov 2026 — Share Pixelforge definitions at their owning boundaries

Token names and the billable-key list live in `App\Features\Pixelforge\TokenType`. `GenerationOptions` owns supported sizes, qualities, defaults, and request limits. Existing services and controllers consume these classes from their established namespaces.

Keeping copies in each consumer would allow costing, settlement, and generation defaults to drift. The Pixelforge client also creates each request through one method so authentication and timeout settings have one owner. Shared navigation modules own link definitions used by multiple Vue components.

Navigation components consume the shared `NavItem` type and its `href` field. This corrects the separate `url` definition in `NavMain`, which disagreed with the links supplied by the sidebar.

Test payloads and assertions use literal provider field names to independently detect contract mismatches. Signed-webhook setup lives in `tests/Pest.php` and is available when either webhook test file runs alone.

## 17 Nov 2026 — Keep the Laravel 13 upgrade blocked until a secure release is available

**Decided:** Keep the installed Laravel 12/Inertia 2 dependency lock while documenting Laravel 13/Inertia 3 as the target stack.
**Rejected:** Disabling Composer security blocking or ignoring the reported advisory IDs.
**Why:** All currently available Laravel 13 releases are blocked by active Composer security advisories. The installed Pest Laravel plugin also does not support Laravel 13.

## 17 Nov 2026 — Preserve Pixelforge contracts and adopt feature-first boundaries for new work

**Decided:** Keep the established Pixelforge class names in their current Laravel directories and reserve `app/Features` for future feature-owned behavior.
**Rejected:** Moving current classes into a new feature namespace during parallel implementation.
**Why:** The existing names and signatures are contracts for concurrent work. Models remain central in `app/Models`.

## 17 Nov 2026 — Use main with short-lived feature and fix branches

**Decided:** Use `main` as the only long-lived branch and create work branches as `feat/<slug>` or `fix/<slug>`.
**Rejected:** A `develop` integration branch and required CI gates.
**Why:** This is an assessment project with a single deployment branch and no mandated CI provider.
