#!/bin/bash

# Exit on any error
set -e

# Store current directory
CURRENT_DIR=$(pwd)

# Function to log messages
log() {
    echo "[$(date '+%Y-%m-%d %H:%M:%S')] $1"
}

# Function to check if Magento is installed
check_magento() {
    if [ ! -f "bin/magento" ]; then
        log "Error: Magento installation not found in current directory"
        exit 1
    fi
}

# Function to take backup
take_backup() {
    log "Taking backup of GraphQL Performance module..."
    if [ -d "app/code/Sterk/GraphQlPerformance" ]; then
        tar -czf graphql_performance_backup_$(date '+%Y%m%d_%H%M%S').tar.gz app/code/Sterk/GraphQlPerformance
    fi
}

# Function to update the module
update_module() {
    log "Updating GraphQL Performance module..."
    
    # Remove existing module files
    if [ -d "app/code/Sterk/GraphQlPerformance" ]; then
        rm -rf app/code/Sterk/GraphQlPerformance
    fi
    
    # Create module directory if it doesn't exist
    mkdir -p app/code/Sterk/GraphQlPerformance
    
    # Download and extract the new version
    curl -L https://github.com/veysiyildiz/magento2-graphql-performance/archive/v1.1.3.tar.gz | tar xz --strip-components=1 -C app/code/Sterk/GraphQlPerformance
    
    log "Module files updated successfully"
}

# Function to update Magento
update_magento() {
    log "Updating Magento..."
    
    php bin/magento maintenance:enable
    php bin/magento cache:flush
    php bin/magento setup:upgrade
    php bin/magento setup:di:compile
    php bin/magento setup:static-content:deploy -f
    php bin/magento cache:flush
    php bin/magento maintenance:disable
    
    log "Magento updated successfully"
}

# Main execution
main() {
    log "Starting update process..."
    
    # Check if we're in a Magento directory
    check_magento
    
    # Take backup
    take_backup
    
    # Update module
    update_module
    
    # Update Magento
    update_magento
    
    log "Update completed successfully!"
}

# Run main function
main
