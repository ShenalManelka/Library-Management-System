<?php
/**
 * Real-Time Date & Time Card for Dashboard Sidebar
 * Include this file in your dashboard sidebar
 * Usage: <?php include 'datetime-sidebar.php'; ?>
 */
?>

<div class="sidebar-datetime-card">
    <div class="datetime-header">
        <h3>Current Time</h3>
    </div>
    <div class="datetime-body">
        <div class="date" id="sidebar-date"><?php echo date('l, F j, Y'); ?></div>
        <div class="time-container">
            <span class="time" id="sidebar-time"><?php echo date('g:i'); ?></span>
            <span class="period" id="sidebar-period"><?php echo date('A'); ?></span>
            <span class="seconds" id="sidebar-seconds"><?php echo date('s'); ?></span>
        </div>
    </div>
    <div class="datetime-footer">
        <span class="timezone">Timezone: </span>
        <span id="sidebar-timezone"><?php echo date_default_timezone_get(); ?></span>
    </div>
</div>

<style>
    /* Sidebar Date-Time Card Styles */
    .sidebar-datetime-card {
        background: #fff;
        border-radius: 10px;
        padding: 15px;
        box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        margin: 15px 0;
        border: 1px solid #e0e0e0;
        font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, sans-serif;
    }
    
    .datetime-header {
        margin-bottom: 12px;
        padding-bottom: 10px;
        border-bottom: 1px solid #f0f0f0;
    }
    
    .datetime-header h3 {
        margin: 0;
        font-size: 16px;
        font-weight: 600;
        color: #333;
    }
    
    .datetime-body {
        margin-bottom: 12px;
    }
    
    .date {
        font-size: 14px;
        font-weight: 400;
        margin-bottom: 8px;
        color: #666;
    }
    
    .time-container {
        display: flex;
        align-items: baseline;
        gap: 4px;
    }
    
    .time {
        font-size: 24px;
        font-weight: 700;
        color: #333;
    }
    
    .period {
        font-size: 14px;
        font-weight: 500;
        color: #666;
        margin-left: 4px;
    }
    
    .seconds {
        font-size: 14px;
        font-weight: 400;
        color: #888;
        margin-left: 2px;
    }
    
    .datetime-footer {
        font-size: 12px;
        color: #888;
        padding-top: 10px;
        border-top: 1px solid #f0f0f0;
        display: flex;
        justify-content: space-between;
    }
    
    .timezone {
        font-weight: 500;
    }
    
    /* Animation for time updates */
    @keyframes highlight {
        0% { background-color: transparent; }
        50% { background-color: #f0f7ff; }
        100% { background-color: transparent; }
    }
    
    .time-update {
        animation: highlight 1s ease;
    }
</style>

<script>
    // Function to update the date and time
    function updateSidebarDateTime() {
        const now = new Date();
        
        // Update date
        const dateOptions = { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' };
        const newDate = now.toLocaleDateString('en-US', dateOptions);
        
        // Only update if changed
        if (document.getElementById('sidebar-date').textContent !== newDate) {
            document.getElementById('sidebar-date').textContent = newDate;
        }
        
        // Update time
        let hours = now.getHours();
        const minutes = now.getMinutes().toString().padStart(2, '0');
        const seconds = now.getSeconds().toString().padStart(2, '0');
        
        // Determine AM/PM
        const period = hours >= 12 ? 'PM' : 'AM';
        hours = hours % 12 || 12; // Convert to 12-hour format
        
        // Get current values for comparison
        const currentTime = document.getElementById('sidebar-time').textContent;
        const currentPeriod = document.getElementById('sidebar-period').textContent;
        const currentSeconds = document.getElementById('sidebar-seconds').textContent;
        
        // Update time if changed
        const newTime = `${hours}:${minutes}`;
        if (currentTime !== newTime) {
            document.getElementById('sidebar-time').textContent = newTime;
            document.getElementById('sidebar-time').classList.add('time-update');
            setTimeout(() => {
                document.getElementById('sidebar-time').classList.remove('time-update');
            }, 1000);
        }
        
        // Update period if changed
        if (currentPeriod !== period) {
            document.getElementById('sidebar-period').textContent = period;
        }
        
        // Always update seconds
        document.getElementById('sidebar-seconds').textContent = seconds;
    }
    
    // Initialize and update every second
    document.addEventListener('DOMContentLoaded', function() {
        updateSidebarDateTime();
        setInterval(updateSidebarDateTime, 1000);
    });
</script>