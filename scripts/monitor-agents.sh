#!/bin/bash

# WiFight Agent Monitoring
# Tracks agent execution, success rates, and performance

LOG_DIR="storage/logs/agents"
REPORT_FILE="docs/monitoring/agent-performance.md"

echo "# WiFight Agent Performance Report" > "$REPORT_FILE"
echo "Generated: $(date)" >> "$REPORT_FILE"
echo "" >> "$REPORT_FILE"

# Function to analyze agent logs
analyze_agent() {
    local agent=$1
    local log_file="$LOG_DIR/${agent}-$(date '+%Y%m%d').log"
    
    if [ -f "$log_file" ]; then
        local total_tasks=$(grep -c "Task" "$log_file")
        local success=$(grep -c "✓" "$log_file")
        local failures=$(grep -c "✗" "$log_file")
        local success_rate=$(( success * 100 / total_tasks ))
        
        echo "## $agent" >> "$REPORT_FILE"
        echo "- Total Tasks: $total_tasks" >> "$REPORT_FILE"
        echo "- Successful: $success" >> "$REPORT_FILE"
        echo "- Failed: $failures" >> "$REPORT_FILE"
        echo "- Success Rate: $success_rate%" >> "$REPORT_FILE"
        echo "" >> "$REPORT_FILE"
    fi
}

# Analyze all agents
for agent in database api controllers frontend security testing devops documentation; do
    analyze_agent "${agent}-agent"
done

echo "Monitoring report generated: $REPORT_FILE"