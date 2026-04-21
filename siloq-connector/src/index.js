/**
 * Siloq Admin React App
 * Modern WordPress admin interface using @wordpress/components
 */

import { render } from '@wordpress/element';
import { createRoot } from '@wordpress/element';
import SettingsPage from './pages/SettingsPage';
import DashboardPage from './pages/DashboardPage';
import SyncPage from './pages/SyncPage';

// Mount React apps to their respective DOM nodes
document.addEventListener('DOMContentLoaded', () => {
    // Settings page
    const settingsRoot = document.getElementById('siloq-settings-root');
    if (settingsRoot) {
        const root = createRoot(settingsRoot);
        root.render(<SettingsPage />);
    }

    // Dashboard page
    const dashboardRoot = document.getElementById('siloq-dashboard-root');
    if (dashboardRoot) {
        const root = createRoot(dashboardRoot);
        root.render(<DashboardPage />);
    }

    // Sync page
    const syncRoot = document.getElementById('siloq-sync-root');
    if (syncRoot) {
        const root = createRoot(syncRoot);
        root.render(<SyncPage />);
    }
});
