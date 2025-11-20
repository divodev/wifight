#!/bin/bash

echo "==================================="
echo "WiFight Output Validation"
echo "==================================="

ERRORS=0

# Check database files
echo "Validating database files..."
if [ ! -f "database/schema/complete-schema.sql" ]; then
    echo "✗ Missing: database/schema/complete-schema.sql"
    ERRORS=$((ERRORS + 1))
else
    echo "✓ Found: database/schema/complete-schema.sql"
    # Validate SQL syntax
    mysql --defaults-file=.my.cnf --force < database/schema/complete-schema.sql 2>&1 | grep -i "error" && ERRORS=$((ERRORS + 1))
fi

# Check PHP files
echo "Validating PHP files..."
find backend -name "*.php" | while read file; do
    php -l "$file" > /dev/null 2>&1
    if [ $? -ne 0 ]; then
        echo "✗ Syntax error in: $file"
        ERRORS=$((ERRORS + 1))
    fi
done

# Check required classes exist
REQUIRED_CLASSES=(
    "backend/utils/JWT.php"
    "backend/utils/Response.php"
    "backend/utils/Logger.php"
    "backend/utils/Validator.php"
    "backend/services/controllers/ControllerInterface.php"
    "backend/services/controllers/ControllerFactory.php"
    "backend/services/controllers/MikrotikController.php"
    "backend/services/controllers/OmadaController.php"
)

for class in "${REQUIRED_CLASSES[@]}"; do
    if [ ! -f "$class" ]; then
        echo "✗ Missing required class: $class"
        ERRORS=$((ERRORS + 1))
    else
        echo "✓ Found: $class"
    fi
done

# Run Composer validation
echo "Validating Composer..."
composer validate --no-check-publish
if [ $? -ne 0 ]; then
    ERRORS=$((ERRORS + 1))
fi

# Final report
echo "==================================="
if [ $ERRORS -eq 0 ]; then
    echo "✓ All validations passed!"
    exit 0
else
    echo "✗ $ERRORS validation error(s) found"
    exit 1
fi