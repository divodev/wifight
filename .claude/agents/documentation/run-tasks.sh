#!/bin/bash

# WiFight Documentation Agent - Task Execution Script

echo "╔═══════════════════════════════════════════════════════════════╗"
echo "║    WiFight ISP System - Documentation Agent (Tech Writer)    ║"
echo "╚═══════════════════════════════════════════════════════════════╝"
echo ""

PROJECT_ROOT=$(pwd)
DOCS_DIR="docs"
LOG_DIR="storage/logs/documentation"

GREEN='\033[0;32m'
RED='\033[0;31m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m'

mkdir -p "$LOG_DIR"
mkdir -p "$DOCS_DIR"/{api,deployment,development,user}

log() {
    echo "[$(date '+%Y-%m-%d %H:%M:%S')] $1" | tee -a "$LOG_DIR/documentation-agent.log"
}

task_header() {
    echo ""
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
    echo " TASK: $1"
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
}

task_header "Checking Documentation Structure"
log "Verifying documentation directories..."

dirs=("docs/api" "docs/deployment" "docs/development" "docs/user")
for dir in "${dirs[@]}"; do
    if [ -d "$dir" ]; then
        echo -e "${GREEN}✓${NC} $dir exists"
    else
        mkdir -p "$dir"
        echo -e "${YELLOW}⚠${NC} Created $dir"
    fi
done

task_header "Scanning Codebase for Documentation"
log "Analyzing project structure..."

echo "Backend files:"
find backend -name "*.php" -type f | wc -l

echo "Frontend files:"
find frontend -name "*.js" -o -name "*.html" -o -name "*.css" 2>/dev/null | wc -l || echo "0"

echo "Database files:"
find database -name "*.sql" -type f 2>/dev/null | wc -l || echo "0"

task_header "Generating API Documentation"
log "Creating API endpoint documentation..."

if [ -d "backend/api/v1" ]; then
    echo -e "${GREEN}✓${NC} API endpoints found"
    echo "Documented endpoints will be listed in docs/api/ENDPOINTS.md"
else
    echo -e "${YELLOW}⚠${NC} No API endpoints found"
fi

task_header "Checking Existing Documentation"
log "Scanning for existing docs..."

doc_files=$(find docs -name "*.md" -type f 2>/dev/null | wc -l)
echo "Found $doc_files markdown documentation files"

if [ -f "README.md" ]; then
    echo -e "${GREEN}✓${NC} README.md exists"
else
    echo -e "${YELLOW}⚠${NC} README.md missing"
fi

if [ -f "CHANGELOG.md" ]; then
    echo -e "${GREEN}✓${NC} CHANGELOG.md exists"
else
    echo -e "${YELLOW}⚠${NC} CHANGELOG.md will be created"
fi

task_header "Analyzing Code Comments"
log "Checking PHPDoc coverage..."

php_files=$(find backend -name "*.php" -type f | wc -l)
files_with_phpdoc=$(grep -rl "\/\*\*" backend --include="*.php" 2>/dev/null | wc -l)

if [ $php_files -gt 0 ]; then
    coverage=$((files_with_phpdoc * 100 / php_files))
    echo "PHPDoc coverage: $coverage% ($files_with_phpdoc/$php_files files)"
    
    if [ $coverage -ge 80 ]; then
        echo -e "${GREEN}✓${NC} Good documentation coverage"
    else
        echo -e "${YELLOW}⚠${NC} Documentation coverage needs improvement"
    fi
fi

task_header "Generating Documentation Index"
log "Creating documentation index..."

cat > docs/README.md << 'EOFDOC'
# WiFight ISP System - Documentation

Welcome to the WiFight ISP System documentation. This comprehensive guide covers everything from installation to advanced usage.

## Documentation Structure

### For Developers
- [Getting Started](development/GETTING_STARTED.md) - Quick start guide for developers
- [Architecture](development/ARCHITECTURE.md) - System architecture overview
- [API Reference](api/ENDPOINTS.md) - Complete API documentation
- [Database Schema](development/DATABASE.md) - Database structure and relationships
- [Testing Guide](development/TESTING.md) - How to write and run tests
- [Contributing](development/CONTRIBUTING.md) - Contribution guidelines

### For Operations
- [Installation Guide](deployment/INSTALLATION.md) - Complete installation instructions
- [Docker Deployment](deployment/DOCKER.md) - Containerized deployment
- [Configuration](deployment/CONFIGURATION.md) - Environment and system configuration
- [Backup & Recovery](deployment/BACKUP.md) - Backup procedures and disaster recovery
- [Monitoring](deployment/MONITORING.md) - System monitoring and health checks

### For Users
- [Admin Guide](user/ADMIN_GUIDE.md) - Administrator documentation
- [Reseller Guide](user/RESELLER_GUIDE.md) - Reseller portal guide
- [User Guide](user/USER_GUIDE.md) - End-user documentation
- [FAQ](user/FAQ.md) - Frequently asked questions
- [Troubleshooting](user/TROUBLESHOOTING.md) - Common issues and solutions

## Quick Links

- **Project Repository**: [GitHub](https://github.com/yourusername/wifight-isp-system)
- **Issue Tracker**: [GitHub Issues](https://github.com/yourusername/wifight-isp-system/issues)
- **License**: MIT License

## Getting Help

If you need help:
1. Check the [FAQ](user/FAQ.md)
2. Search [existing issues](https://github.com/yourusername/wifight-isp-system/issues)
3. Create a [new issue](https://github.com/yourusername/wifight-isp-system/issues/new)

## Contributing

We welcome contributions! Please read our [Contributing Guide](development/CONTRIBUTING.md) to get started.
EOFDOC

echo -e "${GREEN}✓${NC} Created docs/README.md"

task_header "Documentation Statistics"
log "Generating documentation report..."

REPORT_FILE="$LOG_DIR/documentation-report-$(date '+%Y%m%d-%H%M%S').txt"

cat > "$REPORT_FILE" << EOFREPORT
═══════════════════════════════════════════════════════════════
WiFight ISP System - Documentation Report
Generated: $(date '+%Y-%m-%d %H:%M:%S')
═══════════════════════════════════════════════════════════════

PROJECT STATISTICS:
----------------------------------
PHP Files: $php_files
PHPDoc Coverage: $coverage%
Markdown Docs: $doc_files

DOCUMENTATION STRUCTURE:
----------------------------------
API Documentation: $([ -d "docs/api" ] && echo "✓ Ready" || echo "✗ Missing")
Deployment Docs: $([ -d "docs/deployment" ] && echo "✓ Ready" || echo "✗ Missing")
Developer Docs: $([ -d "docs/development" ] && echo "✓ Ready" || echo "✗ Missing")
User Docs: $([ -d "docs/user" ] && echo "✓ Ready" || echo "✗ Missing")

MAIN DOCUMENTATION FILES:
----------------------------------
README.md: $([ -f "README.md" ] && echo "✓ Exists" || echo "✗ Missing")
CHANGELOG.md: $([ -f "CHANGELOG.md" ] && echo "✓ Exists" || echo "✗ Missing")
CLAUDE.md: $([ -f "CLAUDE.md" ] && echo "✓ Exists" || echo "✗ Missing")
docs/README.md: $([ -f "docs/README.md" ] && echo "✓ Exists" || echo "✗ Missing")

NEXT STEPS:
----------------------------------
1. Review generated documentation
2. Update API endpoint documentation
3. Complete deployment guides
4. Add code examples and diagrams
5. Review and update PHPDoc comments
6. Generate OpenAPI specification

═══════════════════════════════════════════════════════════════
EOFREPORT

cat "$REPORT_FILE"

echo ""
echo -e "${GREEN}✓${NC} Documentation report saved to: $REPORT_FILE"
echo ""
log "Documentation agent execution completed"

echo -e "${BLUE}═══ Documentation Generation Complete ═══${NC}"
