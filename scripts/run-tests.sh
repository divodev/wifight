#!/bin/bash

# WiFight ISP System - Test Runner Script

echo "╔══════════════════════════════════════════════════════════╗"
echo "║      WiFight ISP - Test Suite Runner                    ║"
echo "╚══════════════════════════════════════════════════════════╝"
echo ""

# Colors
GREEN='\033[0;32m'
RED='\033[0;31m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# Check if test database exists
echo "Checking test database..."
mysql -u root -e "USE wifight_isp_test" 2>/dev/null

if [ $? -ne 0 ]; then
    echo -e "${YELLOW}Test database not found. Creating...${NC}"
    mysql -u root -e "CREATE DATABASE wifight_isp_test"
    mysql -u root wifight_isp_test < database/schema.sql
    echo -e "${GREEN}✓ Test database created${NC}"
else
    echo -e "${GREEN}✓ Test database exists${NC}"
fi

echo ""

# Check if PHPUnit is installed
if [ ! -f "vendor/bin/phpunit" ]; then
    echo -e "${RED}✗ PHPUnit not found${NC}"
    echo "Installing PHPUnit..."
    composer require --dev phpunit/phpunit
    echo ""
fi

# Parse arguments
SUITE=""
COVERAGE=false
FILTER=""

while [[ $# -gt 0 ]]; do
    case $1 in
        --suite)
            SUITE="$2"
            shift 2
            ;;
        --coverage)
            COVERAGE=true
            shift
            ;;
        --filter)
            FILTER="$2"
            shift 2
            ;;
        *)
            echo "Unknown option: $1"
            exit 1
            ;;
    esac
done

# Build command
CMD="vendor/bin/phpunit"

if [ ! -z "$SUITE" ]; then
    CMD="$CMD --testsuite $SUITE"
fi

if [ ! -z "$FILTER" ]; then
    CMD="$CMD --filter $FILTER"
fi

if [ "$COVERAGE" = true ]; then
    CMD="$CMD --coverage-html coverage/"
    echo "Generating code coverage report..."
    echo ""
fi

# Run tests
echo "Running tests..."
echo "Command: $CMD"
echo ""
echo "══════════════════════════════════════════════════════════"
echo ""

$CMD

EXIT_CODE=$?

echo ""
echo "══════════════════════════════════════════════════════════"

if [ $EXIT_CODE -eq 0 ]; then
    echo -e "${GREEN}✓ All tests passed!${NC}"
else
    echo -e "${RED}✗ Some tests failed${NC}"
fi

if [ "$COVERAGE" = true ]; then
    echo ""
    echo -e "${GREEN}Coverage report generated: coverage/index.html${NC}"
fi

exit $EXIT_CODE
