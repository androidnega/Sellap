# Reset System Test Suite (PHASE G)

Comprehensive automated tests for the reset system functionality.

## Test Structure

- **TestHelper.php** - Utility class for test setup and teardown
- **ResetServiceTest.php** - Tests for reset service (dry-run, actual reset, system reset)
- **FileCleanupTest.php** - Tests for file deletion jobs
- **PermissionTest.php** - Tests for permission middleware and access control
- **ConcurrencyTest.php** - Tests for concurrent operations and transaction isolation
- **run_all_tests.php** - Test runner that executes all test suites

## Running Tests

### Run All Tests

```bash
php tests/run_all_tests.php
```

### Run Individual Test Suites

```bash
# Reset Service Tests
php tests/ResetServiceTest.php

# File Cleanup Tests
php tests/FileCleanupTest.php

# Permission Tests
php tests/PermissionTest.php

# Concurrency Tests
php tests/ConcurrencyTest.php
```

## Test Coverage

### 1. Dry-Run Test
- ✅ Creates test company with seeded data
- ✅ Calls `resetCompanyData(companyId, dry_run=true)`
- ✅ Asserts counts are accurate
- ✅ Verifies database unchanged

### 2. Actual Reset Test
- ✅ Seeds data then runs reset on test database
- ✅ Verifies rows removed
- ✅ Verifies preserved tables intact
- ✅ Verifies users with role `system_admin` remain
- ✅ Verifies company record preserved

### 3. System Reset Test
- ✅ Tests system-wide reset
- ✅ Verifies all companies deleted
- ✅ Verifies system_admin users preserved

### 4. File Cleanup Test
- ✅ Seeds product images
- ✅ Runs file deletion job
- ✅ Asserts files removed from disk

### 5. Concurrency Test
- ✅ Attempts two reset runs concurrently
- ✅ Ensures proper handling of multiple operations
- ✅ Verifies transaction isolation

### 6. Permission Tests
- ✅ System admin can access reset endpoints
- ✅ Non-admin users cannot trigger endpoints
- ✅ Manager cannot access
- ✅ Salesperson cannot access

## Test Database

**Important:** These tests use the actual database configured in `config/database.php`. 

⚠️ **Warning:** Tests will create and delete real data. Ensure you're using a test database or development environment.

### Recommended Setup

1. Create a separate test database:
```sql
CREATE DATABASE sellapp_test_db;
```

2. Update `config/database.php` or create a test config:
```php
define('DB_NAME', 'sellapp_test_db');
```

3. Run migrations on test database:
```bash
mysql -u root -p sellapp_test_db < database/schema.sql
mysql -u root -p sellapp_test_db < database/migrations/create_reset_system_tables.sql
```

## Test Environment

Tests require:
- PHP 8.0+
- MySQL 8.0+
- All dependencies installed via Composer
- Test database with proper schema

## Test Output

Tests provide detailed output:
- Test names and descriptions
- Initial state information
- Results after operations
- Assertion results
- Final summary with pass/fail counts

## Troubleshooting

### Common Issues

1. **Database Connection Errors**
   - Verify database credentials in `config/database.php`
   - Ensure database exists and user has permissions

2. **File Permission Errors**
   - Ensure `storage/test_files/` directory exists and is writable
   - Check file permissions: `chmod 777 storage/test_files/`

3. **Missing Classes**
   - Run `composer install` to ensure all dependencies are loaded
   - Verify autoloading is configured correctly

4. **Foreign Key Constraints**
   - Tests disable FK checks temporarily
   - Ensure cleanup happens properly

## Extending Tests

To add new tests:

1. Create a new test file in `tests/` directory
2. Extend or use `TestHelper` for common functionality
3. Add test class to `run_all_tests.php`
4. Follow existing test patterns

Example:
```php
class NewFeatureTest {
    public function testNewFeature() {
        // Setup
        $companyId = TestHelper::createTestCompany();
        
        // Execute
        // ... test code ...
        
        // Assert
        assert($condition, "Description");
        
        // Cleanup
        TestHelper::cleanupTestData();
        
        return true;
    }
    
    public function runAll() {
        $results = [];
        $results['new_feature'] = $this->testNewFeature();
        return $results;
    }
}
```

## Continuous Integration

These tests can be integrated into CI/CD pipelines:

```yaml
# Example GitHub Actions
- name: Run Tests
  run: php tests/run_all_tests.php
```

Exit codes:
- `0` = All tests passed
- `1` = One or more tests failed

