/**
 * API utility for React components
 * Uses @wordpress/api-fetch with localized data
 */

import apiFetch from '@wordpress/api-fetch';

// Configure apiFetch with the REST URL from localized data
const configureApi = () => {
    // Get data from WordPress localized script
    const data = window.siloqReactData || {};

    // Set the REST API root
    if (data.restUrl) {
        apiFetch.use(apiFetch.createRootURLMiddleware(data.restUrl));
    }

    // Add nonce to requests
    if (data.restNonce) {
        apiFetch.use(apiFetch.createNonceMiddleware(data.restNonce));
    }

    return data;
};

// Initialize configuration
const siloqData = configureApi();

export { apiFetch, siloqData };

// Helper to get admin URLs
export const getAdminUrl = (page, params = {}) => {
    const baseUrl = siloqData.adminUrl || 'admin.php';
    const queryParams = new URLSearchParams({ page, ...params });
    return `${baseUrl}?${queryParams.toString()}`;
};

// Helper for legacy AJAX calls
export const ajaxPost = async (action, data = {}) => {
    const formData = new FormData();
    formData.append('action', action);
    formData.append('nonce', siloqData.ajaxNonce || '');

    Object.keys(data).forEach(key => {
        formData.append(key, data[key]);
    });

    const response = await fetch(siloqData.ajaxUrl || '/wp-admin/admin-ajax.php', {
        method: 'POST',
        body: formData,
    });

    return response.json();
};
