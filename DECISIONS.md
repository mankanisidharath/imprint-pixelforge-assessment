# Decisions — what we chose and why

Append-only log. Newest first.

---

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
