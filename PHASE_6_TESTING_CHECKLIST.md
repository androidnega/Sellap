# Phase 6: Testing Checklist - Audit Trail System

## ✅ System Activation & Data Synchronization Testing

### 1. Seeder Testing

**Test: Run Audit Trail Seeder**
```bash
php database/seeders/run_audit_trail_seeder.php
```

**Expected Results:**
- ✅ Default alerts created for all companies
- ✅ Scheduled reports configured for all companies
- ✅ System settings added to `system_settings` table
- ✅ No errors during seeding

**Verify:**
```sql
SELECT COUNT(*) FROM alerts WHERE company_id IS NOT NULL;
SELECT COUNT(*) FROM scheduled_reports WHERE enabled = 1;
SELECT * FROM system_settings WHERE setting_key LIKE 'audit_trail%';
```

---

### 2. Live Data Fetch Testing

**Test: Unified Live Data Endpoint**
```bash
# As manager/admin
GET /api/audit-trail/data?date_range=today&module=all
```

**Expected Results:**
- ✅ Returns JSON with `success: true`
- ✅ Includes `sales`, `swaps`, `repairs`, `inventory`, `profit` data
- ✅ Includes `customer_activity` metrics
- ✅ Respects module toggles (disabled modules return no data)
- ✅ Date range calculations correct
- ✅ Response time < 2 seconds

**Test Cases:**
- [ ] `date_range=today` → Returns today's data only
- [ ] `date_range=this_week` → Returns week-to-date data
- [ ] `date_range=this_month` → Returns month-to-date data
- [ ] `date_range=last_30_days` → Returns last 30 days
- [ ] `module=sales` → Returns only sales data
- [ ] `module=swaps` → Returns only swaps data (if enabled)
- [ ] `module=repairs` → Returns only repairs data (if enabled)
- [ ] Company with no sales → Returns empty/zero values, not errors
- [ ] System admin → Returns all modules regardless of toggles

---

### 3. Module Toggle Testing

**Test: Module Visibility**
- [ ] Disable `pos_sales` module → Sales metrics hidden from UI
- [ ] Disable `swaps` module → Swap metrics hidden from UI
- [ ] Disable `repairs` module → Repair metrics hidden from UI
- [ ] Disable `products_inventory` → Inventory metrics hidden
- [ ] Re-enable modules → Metrics reappear
- [ ] Module toggle changes reflect immediately (no page refresh needed)

**Verify:**
- Check `/api/audit-trail/data` response includes only enabled modules
- Check UI cards/charts show/hide based on enabled modules
- Check export options respect module toggles

---

### 4. Real-Time Auto-Refresh Testing

**Test: Live Data Updates**
- [ ] Page loads → Initial data fetched
- [ ] Every 30 seconds → Data auto-refreshes (check Network tab)
- [ ] Metrics cards update with new values
- [ ] Charts update with new data
- [ ] No page flicker or loading indicators during refresh
- [ ] Date range filter changes → Refresh uses new range

**Performance:**
- [ ] Multiple simultaneous refreshes don't cause conflicts
- [ ] Refresh doesn't block UI interactions
- [ ] Failed refresh doesn't break the page

---

### 5. Export Integration Testing

**Test: All Export Formats**
```
GET /api/analytics/export/sales?format=csv
GET /api/analytics/export/sales?format=xlsx
GET /api/analytics/export/sales?format=pdf
GET /api/analytics/export/repairs?format=csv
GET /api/analytics/export/swaps?format=xlsx
GET /api/analytics/export/inventory?format=pdf
```

**Expected Results:**
- ✅ CSV exports download correctly
- ✅ Excel exports open in Excel/LibreOffice
- ✅ PDF exports render correctly
- ✅ File names include date stamps
- ✅ Data matches current date range filter
- ✅ All required columns included
- ✅ File sizes reasonable (< 10MB for typical datasets)

**Edge Cases:**
- [ ] Export with no data → Creates empty file with headers
- [ ] Export with large dataset → Completes without timeout
- [ ] Export during active refresh → No data corruption

---

### 6. Backup & Restore Testing

**Test: Backup Export**
```
POST /dashboard/backup/export
```

**Expected Results:**
- ✅ Backup file created in `/storage/backups/{company_id}/`
- ✅ File is ZIP format
- ✅ Contains JSON backup data
- ✅ Contains metadata.txt
- ✅ Backup record created in `backups` table
- ✅ Status = 'completed'
- ✅ File size > 0

**Test: Backup Import**
```
POST /dashboard/backup/import
```

**Expected Results:**
- ✅ Backup file validated before import
- ✅ Staging tables created correctly
- ✅ Data imported into staging
- ✅ Data merged to production (transaction-based)
- ✅ All relations preserved
- ✅ Import logged in audit_logs
- ✅ Import status tracked

**Test: Backup Verification**
- [ ] Integrity check passes for valid backup
- [ ] Integrity check fails for corrupted backup
- [ ] Download backup file → File is valid ZIP
- [ ] Extract backup → JSON structure valid

---

### 7. Data Versioning Testing

**Test: Version Creation**
- [ ] Create sale → Version entry created in `audit_versions`
- [ ] Update product → Version entry with old_data and new_data
- [ ] Delete record → Version entry with old_data only
- [ ] Version entries linked to correct company_id
- [ ] User tracking correct

**Test: Version History**
```
GET /api/analytics/versions?table=pos_sales&record_id=123
```

**Expected Results:**
- ✅ Returns version history for record
- ✅ Old_data and new_data decoded correctly
- ✅ Versions ordered by created_at DESC
- ✅ User information included

**Test: Rollback**
- [ ] Rollback to previous version → Data restored
- [ ] Rollback creates new version entry
- [ ] Rollback logged in audit_logs
- [ ] Access control enforced (company-scoped)

---

### 8. Integrity Dashboard Testing

**Test: Integrity Metrics**
```
GET /api/analytics/integrity
```

**Expected Results:**
- ✅ Returns last backup date
- ✅ Returns backup count
- ✅ Returns backup integrity status
- ✅ Returns restorable records count
- ✅ Returns scheduled reports status
- ✅ All metrics accurate

**UI Testing:**
- [ ] Integrity cards display correctly
- [ ] Status badges color-coded correctly
- [ ] Missing backup shows "Never"
- [ ] Failed integrity shows warning icon

---

### 9. Scheduled Reports Testing

**Test: Report Scheduler Worker**
```bash
php app/Workers/report_scheduler.php
```

**Expected Results:**
- ✅ Reads scheduled_reports table
- ✅ Executes due reports
- ✅ Generates export files
- ✅ Updates next_run timestamp
- ✅ Creates backup files (for backup type reports)
- ✅ Logs errors without crashing

**Test: Snapshot Worker**
```bash
php app/Workers/audit_snapshot_worker.php daily
php app/Workers/audit_snapshot_worker.php weekly
```

**Expected Results:**
- ✅ Creates snapshot files in `/storage/audit_snapshots/{company_id}/`
- ✅ Snapshot contains all analytics data
- ✅ Old snapshots cleaned up (keeps last 30 days/12 weeks)
- ✅ Files are valid JSON

---

### 10. Auto-Sync on Login Testing

**Test: Login Sync**
1. Login as manager
2. Check audit_logs for `audit_trail.synced` event
3. Check smart_recommendations for new entries

**Expected Results:**
- ✅ Sync triggered on manager/admin login
- ✅ Recommendations generated
- ✅ Audit log entry created
- ✅ Login not blocked if sync fails
- ✅ Sync completes in background

---

### 11. Cross-Company Benchmarking Testing (Admin Only)

**Test: Benchmarks Access**
```
GET /dashboard/admin/benchmarks
GET /api/admin/benchmarks
```

**Expected Results:**
- ✅ Only system_admin can access
- ✅ Manager/admin → 403 Forbidden
- ✅ Data is anonymized (no company IDs exposed)
- ✅ Charts render correctly
- ✅ Top performers table displays
- ✅ Percentile calculations accurate

**Data Anonymization:**
- [ ] Company IDs removed from responses
- [ ] Company labels assigned (Company #1, Company #2)
- [ ] No identifiable information leaked
- [ ] Aggregate metrics calculated correctly

---

### 12. Performance Testing

**Test: Load Performance**
- [ ] Page load time < 3 seconds
- [ ] API response time < 2 seconds
- [ ] Chart rendering < 1 second
- [ ] Export generation < 5 seconds for 1000 records
- [ ] Backup creation < 30 seconds for typical company

**Test: Concurrent Users**
- [ ] Multiple managers viewing analytics simultaneously
- [ ] No database locking issues
- [ ] No memory leaks during long sessions
- [ ] Auto-refresh doesn't cause conflicts

---

### 13. Error Handling Testing

**Test: Error Scenarios**
- [ ] Database connection failure → Graceful error message
- [ ] Invalid date range → Defaults to safe range
- [ ] Missing module data → Returns empty object, not error
- [ ] File upload error → Clear error message
- [ ] Export failure → User notified, no partial file
- [ ] Network timeout → Retry or fallback

---

### 14. Security Testing

**Test: Access Control**
- [ ] Manager can only access own company data
- [ ] System admin can access all companies
- [ ] Unauthenticated requests → 401
- [ ] Cross-company access attempts → 403
- [ ] File upload validation (type, size)
- [ ] SQL injection prevention in filters

**Test: Data Isolation**
- [ ] Company A cannot see Company B's data
- [ ] Backups are company-scoped
- [ ] Audit logs filtered by company_id
- [ ] Recommendations company-specific

---

### 15. Integration Testing

**Test: Full Workflow**
1. Manager logs in → Auto-sync triggered
2. Views audit trail → All metrics load
3. Changes date range → Data updates
4. Generates recommendation → Saved to database
5. Creates backup → File saved, metadata recorded
6. Exports report → File downloads
7. Views integrity dashboard → All metrics accurate

**Expected Results:**
- ✅ All steps complete without errors
- ✅ Data flows correctly between components
- ✅ UI updates reflect backend changes
- ✅ Audit trail records all actions

---

## 📋 Quick Test Script

```bash
# 1. Run seeder
php database/seeders/run_audit_trail_seeder.php

# 2. Test live data endpoint (as manager)
curl -H "Cookie: sellapp_token=YOUR_TOKEN" \
  http://localhost/api/audit-trail/data?date_range=today

# 3. Test export
curl -H "Cookie: sellapp_token=YOUR_TOKEN" \
  http://localhost/api/analytics/export/sales?format=csv \
  -o test_export.csv

# 4. Run snapshot worker
php app/Workers/audit_snapshot_worker.php daily

# 5. Run report scheduler
php app/Workers/report_scheduler.php
```

---

## 🎯 Success Criteria

All tests should pass:
- ✅ No PHP errors or warnings
- ✅ All API endpoints return valid JSON
- ✅ UI displays correctly for all scenarios
- ✅ Module toggles work as expected
- ✅ Exports generate valid files
- ✅ Backups can be restored successfully
- ✅ Performance acceptable (< 3s page load)
- ✅ Security controls enforced

---

## 📝 Notes

- Run tests in development environment first
- Use test company with sample data
- Verify database state before and after each test
- Check error logs for unexpected messages
- Monitor performance metrics during testing

