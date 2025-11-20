#!/bin/bash

# WiFight Testing Agent - Task Execution Script
# This script runs all testing-related tasks

echo "╔═══════════════════════════════════════════════════════════════╗"
echo "║         WiFight ISP System - Testing Agent (QA)              ║"
echo "╚═══════════════════════════════════════════════════════════════╝"
echo ""

# Configuration
PROJECT_ROOT=$(pwd)
TEST_DIR="tests"
COVERAGE_DIR="storage/coverage"
LOG_DIR="storage/logs/tests"

# Colors for output
GREEN='\033[0;32m'
RED='\033[0;31m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# Create necessary directories
mkdir -p "$COVERAGE_DIR"
mkdir -p "$LOG_DIR"
mkdir -p "$TEST_DIR/Unit"
mkdir -p "$TEST_DIR/Integration"
mkdir -p "$TEST_DIR/Feature"

# Function to log with timestamp
log() {
    echo "[$(date '+%Y-%m-%d %H:%M:%S')] $1" | tee -a "$LOG_DIR/testing-agent.log"
}

# Function to display task header
task_header() {
    echo ""
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
    echo " TASK: $1"
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
}

# Task 1: Check Testing Dependencies
task_header "Checking Testing Dependencies"
log "Verifying PHPUnit and testing tools..."

if command -v vendor/bin/phpunit &> /dev/null; then
    echo -e "${GREEN}✓${NC} PHPUnit is installed"
    vendor/bin/phpunit --version
else
    echo -e "${RED}✗${NC} PHPUnit not found. Installing dependencies..."
    composer install --dev
fi

# Task 2: Validate PHPUnit Configuration
task_header "Validating PHPUnit Configuration"
log "Checking phpunit.xml configuration..."

if [ -f "phpunit.xml" ]; then
    echo -e "${GREEN}✓${NC} phpunit.xml found"
else
    echo -e "${YELLOW}⚠${NC} phpunit.xml not found"
fi

# Task 3: Run Code Style Checks
task_header "Running Code Style Checks"
log "Checking PHP code style with PHP_CodeSniffer..."

if command -v vendor/bin/phpcs &> /dev/null; then
    vendor/bin/phpcs --standard=PSR12 backend/ || echo -e "${YELLOW}⚠${NC} Code style issues found"
else
    echo -e "${YELLOW}⚠${NC} PHP_CodeSniffer not installed"
fi

# Task 4: Run Static Analysis
task_header "Running Static Analysis"
log "Running PHPStan static analysis..."

if command -v vendor/bin/phpstan &> /dev/null; then
    vendor/bin/phpstan analyse backend/ --level=5 || echo -e "${YELLOW}⚠${NC} Static analysis issues found"
else
    echo -e "${YELLOW}⚠${NC} PHPStan not installed"
fi

# Task 5: Run Unit Tests
task_header "Running Unit Tests"
log "Executing unit test suite..."

if [ -d "$TEST_DIR/Unit" ] && [ "$(ls -A $TEST_DIR/Unit 2>/dev/null)" ]; then
    vendor/bin/phpunit --testsuite=Unit --colors=always
    UNIT_RESULT=$?

    if [ $UNIT_RESULT -eq 0 ]; then
        echo -e "${GREEN}✓${NC} Unit tests passed"
    else
        echo -e "${RED}✗${NC} Unit tests failed"
    fi
else
    echo -e "${YELLOW}⚠${NC} No unit tests found in $TEST_DIR/Unit"
fi

# Task 6: Run Integration Tests
task_header "Running Integration Tests"
log "Executing integration test suite..."

if [ -d "$TEST_DIR/Integration" ] && [ "$(ls -A $TEST_DIR/Integration 2>/dev/null)" ]; then
    vendor/bin/phpunit --testsuite=Integration --colors=always
    INTEGRATION_RESULT=$?

    if [ $INTEGRATION_RESULT -eq 0 ]; then
        echo -e "${GREEN}✓${NC} Integration tests passed"
    else
        echo -e "${RED}✗${NC} Integration tests failed"
    fi
else
    echo -e "${YELLOW}⚠${NC} No integration tests found in $TEST_DIR/Integration"
fi

# Task 7: Run Feature Tests
task_header "Running Feature Tests"
log "Executing feature test suite..."

if [ -d "$TEST_DIR/Feature" ] && [ "$(ls -A $TEST_DIR/Feature 2>/dev/null)" ]; then
    vendor/bin/phpunit --testsuite=Feature --colors=always
    FEATURE_RESULT=$?

    if [ $FEATURE_RESULT -eq 0 ]; then
        echo -e "${GREEN}✓${NC} Feature tests passed"
    else
        echo -e "${RED}✗${NC} Feature tests failed"
    fi
else
    echo -e "${YELLOW}⚠${NC} No feature tests found in $TEST_DIR/Feature"
fi

# Task 8: Generate Code Coverage Report
task_header "Generating Code Coverage Report"
log "Creating code coverage report..."

if command -v vendor/bin/phpunit &> /dev/null; then
    vendor/bin/phpunit --coverage-html="$COVERAGE_DIR" --coverage-text || echo -e "${YELLOW}⚠${NC} Cannot generate coverage (xdebug may not be installed)"

    if [ -f "$COVERAGE_DIR/index.html" ]; then
        echo -e "${GREEN}✓${NC} Coverage report generated: $COVERAGE_DIR/index.html"
    fi
fi

# Task 9: Run Security Tests
task_header "Running Security Tests"
log "Checking for common security vulnerabilities..."

echo "Checking for SQL injection vulnerabilities..."
grep -r "mysql_query\|mysqli_query" backend/ 2>/dev/null && echo -e "${RED}✗${NC} Found unsafe queries" || echo -e "${GREEN}✓${NC} No unsafe queries found"

echo "Checking for XSS vulnerabilities..."
grep -r "echo.*\$_GET\|echo.*\$_POST\|print.*\$_GET\|print.*\$_POST" backend/ 2>/dev/null && echo -e "${RED}✗${NC} Potential XSS found" || echo -e "${GREEN}✓${NC} No obvious XSS patterns"

echo "Checking for hardcoded credentials..."
grep -ri "password.*=.*['\"].*['\"]" backend/ --include="*.php" 2>/dev/null | grep -v "password_hash\|password_verify" && echo -e "${RED}✗${NC} Potential hardcoded credentials" || echo -e "${GREEN}✓${NC} No hardcoded credentials found"

# Task 10: Generate Test Report
task_header "Generating Test Summary Report"
log "Creating test summary..."

REPORT_FILE="$LOG_DIR/test-report-$(date '+%Y%m%d-%H%M%S').txt"

cat > "$REPORT_FILE" << EOFREPORT
═══════════════════════════════════════════════════════════════
WiFight ISP System - Test Report
Generated: $(date '+%Y-%m-%d %H:%M:%S')
═══════════════════════════════════════════════════════════════

TEST SUITE RESULTS:
----------------------------------
Unit Tests:        ${UNIT_RESULT:-N/A}
Integration Tests: ${INTEGRATION_RESULT:-N/A}
Feature Tests:     ${FEATURE_RESULT:-N/A}

COVERAGE:
----------------------------------
Report Location: $COVERAGE_DIR/index.html

LOGS:
----------------------------------
Test Log: $LOG_DIR/testing-agent.log

NEXT STEPS:
----------------------------------
1. Review failed tests (if any)
2. Increase test coverage to 80%+
3. Add missing integration tests
4. Run security audit
5. Update test documentation

═══════════════════════════════════════════════════════════════
EOFREPORT

cat "$REPORT_FILE"

echo ""
echo -e "${GREEN}✓${NC} Test report saved to: $REPORT_FILE"
echo ""
log "Testing agent execution completed"

# Exit with appropriate code
if [ ${UNIT_RESULT:-1} -eq 0 ] && [ ${INTEGRATION_RESULT:-1} -eq 0 ] && [ ${FEATURE_RESULT:-1} -eq 0 ]; then
    echo -e "${GREEN}═══ All Tests Passed ═══${NC}"
    exit 0
else
    echo -e "${YELLOW}═══ Some Tests Failed - Review Results Above ═══${NC}"
    exit 1
fi
