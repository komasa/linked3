# Linked3 AI — Smoke Test Checklist

> **Version**: v27.6.2
> **Purpose**: Post-deployment verification of critical user-facing flows

---

## Pre-conditions

- [ ] Plugin activated without fatal error
- [ ] Dashboard page loads (`/wp-admin/admin.php?page=linked3-dashboard`)
- [ ] No PHP warnings/notices on dashboard

---

## 1. AI Configuration

- [ ] Settings page loads: Linked3 → Settings → AI Configuration
- [ ] Can enter API key and save
- [ ] "Test Connection" returns success
- [ ] Model list populates after sync

## 2. Genesis (Content Generation)

- [ ] Genesis page loads: Linked3 → Genesis
- [ ] Single article generation completes
- [ ] Multi-article generation starts and shows progress
- [ ] Job polling works (start job → poll → complete)
- [ ] Seed DNA: generate → list → export works
- [ ] V9 pipeline: stage1 + stage2 complete without error
- [ ] Server diagnostic returns results

## 3. Content Writer

- [ ] Create Center page loads
- [ ] Long-form article generation completes
- [ ] SEO meta auto-generation works
- [ ] Section-by-section generation works

## 4. SEO Module

- [ ] SEO dashboard loads
- [ ] Keyword research: batch generation works
- [ ] Push logs visible
- [ ) Sitemap generation works (if enabled)

## 5. Book Factory

- [ ] Book dashboard loads
- [ ] Create new book project
- [ ] Step 1 (outline) generates successfully
- [ ] Async runner picks up job
- [ ] Draft files created in uploads directory

## 6. Diagram

- [ ] Diagram page loads
- [ ] Chart outline generation works
- [ ] Chart segment generation works

## 7. Dashboard Widgets

- [ ] All dashboard tabs load without error
- [ ] Tab: Create Center — loads, 8 structure types visible
- [ ] Tab: Meta Lever — 17 compound levers visible
- [ ] Tab: V18 — loads
- [ ] Tab: Style Library — loads

## 8. AutoGPT

- [ ] AutoGPT dashboard loads
- [ ] Cron job visible in Tools → Cron Events
- [ ] Manual trigger works

## 9. Billing (if enabled)

- [ ] Subscription management loads
- [ ] Quota display correct
- [ ] Payment flow works (test mode)

## 10. Security

- [ ] All AJAX endpoints require nonce
- [ ] All AJAX endpoints require capability check
- [ ] No SQL injection warnings in error log
- [ ] Security headers present (check via browser dev tools)

---

## Error Log Check

After completing all smoke tests:

1. Check `wp-content/uploads/linked3-logs/` for errors
2. Check `wp-content/debug.log` for PHP errors
3. Check server error log for 500/501 errors

**Expected**: No fatal errors, no uncaught exceptions

---

## Performance Check

- [ ] Dashboard loads in < 3 seconds
- [ ] Article generation completes in < 60 seconds
- [ ] No memory exhaustion errors
- [ ] No slow query warnings (> 2 seconds)

---

## Regression Notes

### Known Issues (v27.6.2)

1. **GenesisPatchV1006**: @deprecated but still referenced — will be inlined in next release
2. **BookFactory**: @deprecated but still referenced by BookAsyncRunner — migration planned
3. **High-CC functions**: 94 functions with CC > 15 remain — incremental refactoring ongoing
4. **AjaxNonceGuard**: Middleware class exists but not yet wired up — planned for next release

### Recent Changes

- 41 commits in v27.6.2
- ~5200 lines net reduction
- 4 files deleted (deprecated templates + router)
- 3 new files (tests, coverage tools)
- All 35 OS aliases repaired
- EventBus migration: 29 dispatch/subscribe calls
- Nonce: MetaLeverFitnessTracker patched
- Complexity: CloudTemplateFactory 211→20 lines
