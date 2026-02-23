/**
 * Siloq AI Content Generator - Vanilla JavaScript Implementation
 */

(function() {
    'use strict';

    // Global state
    let state = {
        isOpen: false,
        isGenerating: false,
        currentJob: null,
        generatedContent: [],
        activeTab: 'generate',
        preferences: {},
        notification: null
    };

    // WordPress data
    let wpData = {};

    // Initialize when DOM is ready
    function init() {
        // Get WordPress data from global variable
        wpData = window.siloqAI || {};
        
        if (!wpData.postId) {
            console.error('Siloq: Missing required data');
            return;
        }

        // Set default preferences
        state.preferences = wpData.preferences || {
            tone: 'professional',
            length: 'medium',
            includeFAQ: true,
            includeCTA: true,
            includeInternalLinks: true,
            targetKeywords: [],
            customInstructions: ''
        };

        // Create UI
        createUI();
        
        // Add event listeners
        addEventListeners();
    }

    function createUI() {
        // Create main container
        const container = document.createElement('div');
        container.id = 'siloq-ai-generator';
        container.className = 'siloq-ai-generator';
        container.innerHTML = getMainHTML();
        
        // Create trigger button
        const trigger = document.createElement('div');
        trigger.className = 'siloq-ai-generator-trigger';
        trigger.innerHTML = getTriggerHTML();
        
        // Add to page
        document.body.appendChild(trigger);
        document.body.appendChild(container);
    }

    function getTriggerHTML() {
        return `
            <button class="siloq-ai-trigger-button" onclick="siloqAI.toggle()">
                <span class="dashicons dashicons-ai-alt"></span>
                <span>AI Content Generator</span>
            </button>
        `;
    }

    function getMainHTML() {
        return `
            <div class="siloq-ai-header">
                <div class="siloq-ai-title">
                    <span class="dashicons dashicons-ai-alt"></span>
                    <h3>AI Content Generator</h3>
                </div>
                <button class="siloq-ai-close" onclick="siloqAI.toggle()">
                    <span class="dashicons dashicons-no-alt"></span>
                </button>
            </div>
            
            <div id="siloq-ai-notification" class="siloq-ai-notification" style="display: none;"></div>
            
            <div class="siloq-ai-tabs">
                <button class="siloq-ai-tab ${state.activeTab === 'generate' ? 'active' : ''}" onclick="siloqAI.setTab('generate')">
                    Generate
                </button>
                <button class="siloq-ai-tab ${state.activeTab === 'preview' ? 'active' : ''}" onclick="siloqAI.setTab('preview')">
                    Preview <span id="siloq-preview-count"></span>
                </button>
                <button class="siloq-ai-tab ${state.activeTab === 'settings' ? 'active' : ''}" onclick="siloqAI.setTab('settings')">
                    Settings
                </button>
            </div>
            
            <div class="siloq-ai-content">
                ${getTabContent()}
            </div>
        `;
    }

    function getTabContent() {
        switch (state.activeTab) {
            case 'generate':
                return getGenerateTabHTML();
            case 'preview':
                return getPreviewTabHTML();
            case 'settings':
                return getSettingsTabHTML();
            default:
                return getGenerateTabHTML();
        }
    }

    function getGenerateTabHTML() {
        return `
            <div class="siloq-ai-generate-tab">
                <div class="siloq-ai-prompt">
                    <h4>Generate AI Content</h4>
                    <p>Create high-quality content using AI powered by Siloq.</p>
                    
                    <div class="siloq-ai-preferences">
                        <div class="siloq-ai-preference-group">
                            <label>Tone</label>
                            <select id="siloq-tone" onchange="siloqAI.updatePreference('tone', this.value)">
                                <option value="professional" ${state.preferences.tone === 'professional' ? 'selected' : ''}>Professional</option>
                                <option value="casual" ${state.preferences.tone === 'casual' ? 'selected' : ''}>Casual</option>
                                <option value="friendly" ${state.preferences.tone === 'friendly' ? 'selected' : ''}>Friendly</option>
                                <option value="authoritative" ${state.preferences.tone === 'authoritative' ? 'selected' : ''}>Authoritative</option>
                            </select>
                        </div>
                        
                        <div class="siloq-ai-preference-group">
                            <label>Length</label>
                            <select id="siloq-length" onchange="siloqAI.updatePreference('length', this.value)">
                                <option value="short" ${state.preferences.length === 'short' ? 'selected' : ''}>Short (200-300 words)</option>
                                <option value="medium" ${state.preferences.length === 'medium' ? 'selected' : ''}>Medium (400-600 words)</option>
                                <option value="long" ${state.preferences.length === 'long' ? 'selected' : ''}>Long (700+ words)</option>
                            </select>
                        </div>
                        
                        <div class="siloq-ai-preference-group">
                            <label>
                                <input type="checkbox" id="siloq-faq" ${state.preferences.includeFAQ ? 'checked' : ''} onchange="siloqAI.updatePreference('includeFAQ', this.checked)">
                                Include FAQ Section
                            </label>
                        </div>
                        
                        <div class="siloq-ai-preference-group">
                            <label>
                                <input type="checkbox" id="siloq-cta" ${state.preferences.includeCTA ? 'checked' : ''} onchange="siloqAI.updatePreference('includeCTA', this.checked)">
                                Include Call-to-Action
                            </label>
                        </div>
                    </div>
                    
                    <button class="siloq-ai-generate-button" onclick="siloqAI.generateContent()" ${state.isGenerating ? 'disabled' : ''}>
                        ${state.isGenerating ? '<span class="siloq-ai-spinner"></span> Generating...' : '<span class="dashicons dashicons-ai-alt"></span> Generate Content'}
                    </button>
                </div>
            </div>
        `;
    }

    function getPreviewTabHTML() {
        if (state.isGenerating && state.currentJob) {
            return `
                <div class="siloq-ai-generating">
                    <div class="siloq-ai-progress">
                        <span class="siloq-ai-spinner"></span>
                        <p>Generating content...</p>
                        <small>Estimated time: ${state.currentJob.estimated_time} seconds</small>
                    </div>
                </div>
            `;
        } else if (state.generatedContent.length > 0) {
            return `
                <div class="siloq-ai-generated-content">
                    <div class="siloq-ai-content-actions">
                        <button class="button button-primary" onclick="siloqAI.insertContent('append')">Append to Content</button>
                        <button class="button" onclick="siloqAI.insertContent('replace')">Replace Content</button>
                        <button class="button" onclick="siloqAI.insertContent('draft')">Create as Draft</button>
                    </div>
                    
                    <div class="siloq-ai-sections">
                        ${state.generatedContent.map(section => `
                            <div class="siloq-ai-section" data-section-id="${section.id}">
                                <div class="siloq-ai-section-header">
                                    <h4>${section.title}</h4>
                                    <div class="siloq-ai-section-actions">
                                        <span class="siloq-ai-word-count">${section.word_count} words</span>
                                        <button class="button button-small" onclick="siloqAI.regenerateSection('${section.id}')">
                                            <span class="dashicons dashicons-update"></span>
                                            Regenerate
                                        </button>
                                    </div>
                                </div>
                                <div class="siloq-ai-section-content">${section.content}</div>
                            </div>
                        `).join('')}
                    </div>
                </div>
            `;
        } else {
            return `
                <div class="siloq-ai-empty">
                    <p>No content generated yet. Go to the Generate tab to create content.</p>
                </div>
            `;
        }
    }

    function getSettingsTabHTML() {
        return `
            <div class="siloq-ai-settings-tab">
                <div class="siloq-ai-setting-group">
                    <h4>Target Keywords</h4>
                    <textarea id="siloq-keywords" placeholder="Enter keywords separated by commas..." onchange="siloqAI.updateKeywords(this.value)">${state.preferences.targetKeywords.join(', ')}</textarea>
                </div>
                
                <div class="siloq-ai-setting-group">
                    <h4>Custom Instructions</h4>
                    <textarea id="siloq-instructions" placeholder="Add any specific instructions for the AI..." onchange="siloqAI.updatePreference('customInstructions', this.value)">${state.preferences.customInstructions}</textarea>
                </div>
                
                <div class="siloq-ai-setting-group">
                    <label>
                        <input type="checkbox" id="siloq-internal-links" ${state.preferences.includeInternalLinks ? 'checked' : ''} onchange="siloqAI.updatePreference('includeInternalLinks', this.checked)">
                        Include Internal Links
                    </label>
                </div>
            </div>
        `;
    }

    function addEventListeners() {
        // Add keyboard shortcuts
        document.addEventListener('keydown', function(e) {
            if (e.ctrlKey && e.shiftKey && e.key === 'A') {
                e.preventDefault();
                toggle();
            }
            if (e.key === 'Escape' && state.isOpen) {
                toggle();
            }
        });
    }

    // Public API
    window.siloqAI = {
        toggle: function() {
            state.isOpen = !state.isOpen;
            const container = document.getElementById('siloq-ai-generator');
            const trigger = document.querySelector('.siloq-ai-generator-trigger');
            
            if (state.isOpen) {
                container.style.display = 'block';
                trigger.style.display = 'none';
            } else {
                container.style.display = 'none';
                trigger.style.display = 'block';
            }
        },
        
        setTab: function(tab) {
            state.activeTab = tab;
            updateUI();
        },
        
        updatePreference: function(key, value) {
            state.preferences[key] = value;
        },
        
        updateKeywords: function(value) {
            state.preferences.targetKeywords = value.split(',').map(k => k.trim()).filter(k => k);
        },
        
        generateContent: async function() {
            if (!wpData.postId) {
                showNotification('error', 'No post ID available');
                return;
            }

            state.isGenerating = true;
            state.activeTab = 'preview';
            updateUI();

            try {
                const response = await fetch(wpData.ajaxUrl, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: new URLSearchParams({
                        action: 'siloq_ai_generate_content',
                        post_id: wpData.postId.toString(),
                        preferences: JSON.stringify(state.preferences),
                        nonce: wpData.nonce
                    })
                });

                const result = await response.json();

                if (result.success) {
                    state.currentJob = {
                        id: result.data.job_id,
                        status: 'processing',
                        estimated_time: result.data.estimated_time
                    };
                    showNotification('success', 'Content generation started');
                    checkJobStatus(result.data.job_id);
                } else {
                    showNotification('error', result.data.message || 'Generation failed');
                    state.isGenerating = false;
                    updateUI();
                }
            } catch (error) {
                showNotification('error', 'Network error occurred');
                state.isGenerating = false;
                updateUI();
            }
        },
        
        insertContent: async function(insertMode) {
            if (state.generatedContent.length === 0) {
                showNotification('error', 'No content to insert');
                return;
            }

            try {
                const content = state.generatedContent.map(section => section.content).join('\n\n');
                
                const response = await fetch(wpData.ajaxUrl, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: new URLSearchParams({
                        action: 'siloq_ai_insert_content',
                        post_id: wpData.postId.toString(),
                        content: content,
                        insert_mode: insertMode,
                        nonce: wpData.nonce
                    })
                });

                const result = await response.json();

                if (result.success) {
                    showNotification('success', result.data.message);
                    if (insertMode === 'draft' && result.data.new_post_id) {
                        window.open(result.data.edit_url, '_blank');
                    }
                } else {
                    showNotification('error', result.data.message || 'Insert failed');
                }
            } catch (error) {
                showNotification('error', 'Network error occurred');
            }
        },
        
        regenerateSection: async function(sectionId) {
            try {
                const response = await fetch(wpData.ajaxUrl, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: new URLSearchParams({
                        action: 'siloq_ai_regenerate_section',
                        section_id: sectionId,
                        section_type: state.generatedContent.find(s => s.id === sectionId)?.type || '',
                        nonce: wpData.nonce
                    })
                });

                const result = await response.json();

                if (result.success) {
                    state.generatedContent = state.generatedContent.map(section => 
                        section.id === sectionId 
                            ? { ...section, content: result.data.content }
                            : section
                    );
                    updateUI();
                    showNotification('success', 'Section regenerated');
                } else {
                    showNotification('error', result.data.message || 'Regeneration failed');
                }
            } catch (error) {
                showNotification('error', 'Network error occurred');
            }
        }
    };

    function checkJobStatus(jobId) {
        setTimeout(async () => {
            try {
                const response = await fetch(wpData.ajaxUrl, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: new URLSearchParams({
                        action: 'siloq_ai_get_content_preview',
                        job_id: jobId,
                        nonce: wpData.nonce
                    })
                });

                const result = await response.json();

                if (result.success) {
                    if (result.data.status === 'completed') {
                        state.currentJob = null;
                        state.generatedContent = result.data.content.sections || [];
                        state.isGenerating = false;
                        updateUI();
                        showNotification('success', 'Content generation completed!');
                    } else {
                        state.currentJob = { ...state.currentJob, status: result.data.status };
                        checkJobStatus(jobId);
                    }
                } else {
                    showNotification('error', 'Failed to check job status');
                    state.isGenerating = false;
                    updateUI();
                }
            } catch (error) {
                showNotification('error', 'Network error occurred');
                state.isGenerating = false;
                updateUI();
            }
        }, 3000);
    }

    function showNotification(type, message) {
        state.notification = { type, message };
        const notificationEl = document.getElementById('siloq-ai-notification');
        if (notificationEl) {
            notificationEl.className = `siloq-ai-notification siloq-ai-notification-${type}`;
            notificationEl.textContent = message;
            notificationEl.style.display = 'block';
            
            setTimeout(() => {
                notificationEl.style.display = 'none';
            }, 5000);
        }
    }

    function updateUI() {
        const container = document.getElementById('siloq-ai-generator');
        if (container) {
            container.innerHTML = getMainHTML();
            
            // Update preview count
            const countEl = document.getElementById('siloq-preview-count');
            if (countEl && state.generatedContent.length > 0) {
                countEl.textContent = `(${state.generatedContent.length})`;
            }
        }
    }

    // Initialize when DOM is ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }

})();
