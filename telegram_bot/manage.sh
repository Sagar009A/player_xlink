#!/bin/bash

# Telegram Bot Management Script
# Usage: bash manage.sh [command]

BOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
cd "$BOT_DIR"

# Colors
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Functions
function show_status() {
    echo -e "${BLUE}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
    echo -e "${BLUE}🤖 Telegram Bot Status${NC}"
    echo -e "${BLUE}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
    echo ""
    
    # Check if bot is running
    if pgrep -f "polling.php" > /dev/null; then
        PID=$(pgrep -f "polling.php")
        echo -e "${GREEN}✅ Bot is running (PID: $PID)${NC}"
    else
        echo -e "${RED}❌ Bot is not running${NC}"
    fi
    
    # Check systemd service if available
    if command -v systemctl &> /dev/null; then
        if systemctl is-active --quiet telegram-bot 2>/dev/null; then
            echo -e "${GREEN}✅ Systemd service: Active${NC}"
        elif systemctl list-unit-files | grep -q telegram-bot; then
            echo -e "${YELLOW}⚠️  Systemd service: Inactive${NC}"
        fi
    fi
    
    # Show recent logs
    if [ -f "bot.log" ]; then
        echo ""
        echo -e "${BLUE}📝 Recent Logs (last 5 lines):${NC}"
        tail -n 5 bot.log
    fi
    
    echo ""
}

function start_bot() {
    if pgrep -f "polling.php" > /dev/null; then
        echo -e "${YELLOW}⚠️  Bot is already running!${NC}"
        return
    fi
    
    echo -e "${GREEN}🚀 Starting bot in background...${NC}"
    nohup php polling.php > /dev/null 2>&1 &
    sleep 2
    
    if pgrep -f "polling.php" > /dev/null; then
        PID=$(pgrep -f "polling.php")
        echo -e "${GREEN}✅ Bot started successfully (PID: $PID)${NC}"
    else
        echo -e "${RED}❌ Failed to start bot${NC}"
    fi
}

function stop_bot() {
    if ! pgrep -f "polling.php" > /dev/null; then
        echo -e "${YELLOW}⚠️  Bot is not running${NC}"
        return
    fi
    
    echo -e "${YELLOW}🛑 Stopping bot...${NC}"
    pkill -f "polling.php"
    sleep 2
    
    if ! pgrep -f "polling.php" > /dev/null; then
        echo -e "${GREEN}✅ Bot stopped successfully${NC}"
    else
        echo -e "${RED}❌ Failed to stop bot${NC}"
        echo -e "${YELLOW}Try: kill -9 $(pgrep -f polling.php)${NC}"
    fi
}

function restart_bot() {
    echo -e "${BLUE}🔄 Restarting bot...${NC}"
    stop_bot
    sleep 2
    start_bot
}

function show_logs() {
    if [ ! -f "bot.log" ]; then
        echo -e "${RED}❌ No log file found${NC}"
        return
    fi
    
    if [ "$1" == "follow" ] || [ "$1" == "-f" ]; then
        echo -e "${BLUE}📝 Following logs (Ctrl+C to stop)...${NC}"
        tail -f bot.log
    else
        echo -e "${BLUE}📝 Last 50 log lines:${NC}"
        tail -n 50 bot.log
    fi
}

function test_bot() {
    echo -e "${BLUE}🧪 Testing bot configuration...${NC}"
    php test_bot.php
}

function setup_webhook() {
    echo -e "${BLUE}🔗 Setting up webhook...${NC}"
    php setup_webhook.php
}

function clear_logs() {
    echo -e "${YELLOW}🗑️  Clearing logs...${NC}"
    > bot.log
    rm -rf logs/*.log 2>/dev/null
    echo -e "${GREEN}✅ Logs cleared${NC}"
}

function show_help() {
    echo -e "${BLUE}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
    echo -e "${BLUE}🤖 Telegram Bot Management${NC}"
    echo -e "${BLUE}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
    echo ""
    echo "Usage: bash manage.sh [command]"
    echo ""
    echo "Commands:"
    echo "  start           Start the bot in background"
    echo "  stop            Stop the bot"
    echo "  restart         Restart the bot"
    echo "  status          Show bot status"
    echo "  logs            Show last 50 log lines"
    echo "  logs follow     Follow logs in real-time"
    echo "  test            Test bot configuration"
    echo "  webhook         Setup webhook"
    echo "  clear-logs      Clear all logs"
    echo "  help            Show this help message"
    echo ""
    echo "Examples:"
    echo "  bash manage.sh start"
    echo "  bash manage.sh logs follow"
    echo "  bash manage.sh restart"
    echo ""
}

# Main
case "$1" in
    start)
        start_bot
        ;;
    stop)
        stop_bot
        ;;
    restart)
        restart_bot
        ;;
    status)
        show_status
        ;;
    logs)
        show_logs "$2"
        ;;
    test)
        test_bot
        ;;
    webhook)
        setup_webhook
        ;;
    clear-logs)
        clear_logs
        ;;
    help|--help|-h)
        show_help
        ;;
    *)
        if [ -z "$1" ]; then
            show_help
        else
            echo -e "${RED}❌ Unknown command: $1${NC}"
            echo ""
            show_help
        fi
        exit 1
        ;;
esac
