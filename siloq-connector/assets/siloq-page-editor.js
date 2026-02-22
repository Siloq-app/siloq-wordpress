/**
 * Siloq AI Content Generator for WordPress Page Editor
 * Injects AI Generate button into WordPress native page editor
 */

console.log('Siloq AI: Script loaded');

// Simple immediate injection
function injectButton() {
    console.log('Siloq AI: Attempting to inject button');
    
    // Remove existing button
    if (document.querySelector('.siloq-ai-generator')) {
        document.querySelector('.siloq-ai-generator').remove();
    }
    
    // Create button container
    var buttonDiv = document.createElement('div');
    buttonDiv.className = 'siloq-ai-generator';
    buttonDiv.style.cssText = 'position: fixed; top: 50px; left: 20px; z-index: 999999; background: white; border: 2px solid #2271b1; border-radius: 8px; padding: 15px; box-shadow: 0 4px 12px rgba(0,0,0,0.15); min-width: 300px;';
    
    buttonDiv.innerHTML = 
        '<button type="button" class="siloq-generate-btn" style="background: linear-gradient(135deg, #2271b1, #135e96); border: none; color: white; display: flex; align-items: center; gap: 10px; padding: 12px 20px; font-size: 14px; font-weight: 600; border-radius: 4px; cursor: pointer; width: 100%; justify-content: center;">' +
            '<svg width="24" height="24" viewBox="0 0 100 100" xmlns="http://www.w3.org/2000/svg">' +
                '<defs>' +
                    '<linearGradient id="siloqGradient" x1="0%" y1="0%" x2="100%" y2="100%">' +
                        '<stop offset="0%" style="stop-color:#ffffff;stop-opacity:1" />' +
                        '<stop offset="100%" style="stop-color:#e0e0e0;stop-opacity:1" />' +
                    '</linearGradient>' +
                '</defs>' +
                '<circle cx="50" cy="50" r="45" fill="url(#siloqGradient)" stroke="#ffffff" stroke-width="2"/>' +
                '<text x="50" y="65" font-family="Arial, sans-serif" font-size="28" font-weight="bold" fill="#2271b1" text-anchor="middle">S</text>' +
            '</svg>' +
            '<span>AI Generate Content</span>' +
            '<span style="font-size: 12px; opacity: 0.8;">✨ Powered by Siloq</span>' +
        '</button>' +
        '<div style="margin-top: 8px; font-size: 12px; color: #666; text-align: center;">Enter a page title, then click to generate</div>';
    
    // Add to page
    document.body.appendChild(buttonDiv);
    
    // Add click handler
    buttonDiv.querySelector('.siloq-generate-btn').addEventListener('click', function() {
        generateContent();
    });
    
    console.log('Siloq AI: Button injected successfully');
}

// Try multiple times
injectButton();
setTimeout(injectButton, 1000);
setTimeout(injectButton, 3000);

// Also try when DOM is ready
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', injectButton);
} else {
    injectButton();
}

function generateContent() {
    console.log('Siloq AI: Generate button clicked');
    
    // Try to get title from various sources
    var title = '';
    var titleInput = document.querySelector('#title') || 
                    document.querySelector('.editor-post-title__input') || 
                    document.querySelector('input[name="post_title"]');
    
    if (titleInput) {
        title = titleInput.value || titleInput.textContent || '';
    }
    
    console.log('Siloq AI: Title found:', title);
    
    if (!title.trim()) {
        console.log('Siloq AI: No title found, checking WordPress data store...');
        
        // Try WordPress data store
        if (typeof wp !== 'undefined' && wp.data && wp.data.select('core/editor')) {
            try {
                var wpTitle = wp.data.select('core/editor').getEditedPostAttribute('title');
                if (wpTitle && wpTitle.trim()) {
                    title = wpTitle.trim();
                    console.log('Siloq AI: Found title in WordPress data store:', title);
                }
            } catch (e) {
                console.log('Siloq AI: WordPress data store failed:', e);
            }
        }
        
        if (!title.trim()) {
            alert('No page title detected\n\nPlease make sure you have entered a title in the page title field at the top of the editor.\n\nTroubleshooting tips:\n• Click in the title field and type your page title\n• Make sure the title field is not empty\n• Try refreshing the page and entering the title again\n\nIf you continue to see this message, please check the browser console (F12) for debugging information.');
            return;
        }
    }
    
    var button = document.querySelector('.siloq-generate-btn');
    var originalText = button.innerHTML;
    
    // Show loading
    button.innerHTML = '<span>Generating...</span>';
    button.disabled = true;
    
    // Generate content
    setTimeout(function() {
        var content = generateContentFromTitle(title);
        insertContentIntoEditor(content);
        
        // Restore button
        button.innerHTML = originalText;
        button.disabled = false;
        
        alert('Content generated successfully! Review and customize as needed.');
        
        // Also try to show Toastr.js notification if available
        if (typeof toastr !== 'undefined') {
            toastr.success('Content generated successfully! Review and customize as needed.', 'Success!');
        }
    }, 2000);
}

function generateContentFromTitle(title) {
    var titleLower = title.toLowerCase();
    
    if (titleLower.includes('about') || titleLower.includes('company')) {
        return [
            {
                type: 'core/heading',
                attributes: {
                    content: title,
                    level: 1
                }
            },
            {
                type: 'core/paragraph',
                attributes: {
                    content: '<strong>Our Story</strong><br>Founded with a vision to transform the industry, we\'ve been dedicated to delivering exceptional solutions since our inception. Our journey began with a simple mission: to provide unparalleled value to our clients through innovation, expertise, and unwavering commitment to excellence.'
                }
            },
            {
                type: 'core/heading',
                attributes: {
                    content: 'Our Values',
                    level: 2
                }
            },
            {
                type: 'core/paragraph',
                attributes: {
                    content: '<strong>Integrity First</strong><br>We believe in transparent business practices and building lasting relationships based on trust and mutual respect.'
                }
            },
            {
                type: 'core/paragraph',
                attributes: {
                    content: '<strong>Innovation Driven</strong><br>We constantly push the boundaries of what\'s possible, embracing new technologies and methodologies to stay ahead of the curve.'
                }
            },
            {
                type: 'core/paragraph',
                attributes: {
                    content: '<strong>Customer Centric</strong><br>Your success is our success. We take the time to understand your unique needs and tailor our solutions accordingly.'
                }
            },
            {
                type: 'core/paragraph',
                attributes: {
                    content: '<strong>Quality Assured</strong><br>Every project we undertake is held to the highest standards of quality and professionalism.'
                }
            },
            {
                type: 'core/heading',
                attributes: {
                    content: 'Our Team',
                    level: 2
                }
            },
            {
                type: 'core/paragraph',
                attributes: {
                    content: 'Our diverse team of professionals brings together expertise from various domains, creating a powerhouse of knowledge and experience. From seasoned veterans to fresh talent, each member of our team is committed to delivering excellence.'
                }
            },
            {
                type: 'core/heading',
                attributes: {
                    content: 'Looking Forward',
                    level: 2
                }
            },
            {
                type: 'core/paragraph',
                attributes: {
                    content: 'As we continue to grow and evolve, our core mission remains unchanged: to be the trusted partner you can rely on for all your needs. We\'re excited about the future and the opportunities it brings to serve you better.'
                }
            },
            {
                type: 'core/separator',
                attributes: {}
            },
            {
                type: 'core/paragraph',
                attributes: {
                    content: '<em>This content was generated by Siloq AI. Feel free to customize it to match your brand voice and specific requirements.</em>'
                }
            }
        ];
    } else {
        return [
            {
                type: 'core/heading',
                attributes: {
                    content: title,
                    level: 1
                }
            },
            {
                type: 'core/paragraph',
                attributes: {
                    content: 'Welcome to your comprehensive guide on ' + title + '. This page provides detailed information, insights, and resources to help you understand and make the most of this topic.'
                }
            },
            {
                type: 'core/heading',
                attributes: {
                    content: '📋 Overview',
                    level: 2
                }
            },
            {
                type: 'core/paragraph',
                attributes: {
                    content: title + ' represents a critical component of modern business strategy. In today\'s competitive landscape, understanding and implementing effective approaches can significantly impact your success and growth trajectory.'
                }
            },
            {
                type: 'core/heading',
                attributes: {
                    content: '🔑 Key Benefits',
                    level: 2
                }
            },
            {
                type: 'core/paragraph',
                attributes: {
                    content: '<strong>Enhanced Efficiency</strong><br>Streamline your operations and achieve better results with optimized processes and methodologies.'
                }
            },
            {
                type: 'core/paragraph',
                attributes: {
                    content: '<strong>Cost Optimization</strong><br>Reduce unnecessary expenses while maintaining or improving quality through strategic resource allocation.'
                }
            },
            {
                type: 'core/paragraph',
                attributes: {
                    content: '<strong>Competitive Advantage</strong><br>Stay ahead of the competition with innovative solutions and forward-thinking approaches.'
                }
            },
            {
                type: 'core/paragraph',
                attributes: {
                    content: '<strong>Scalability</strong><br>Build systems and processes that grow with your business, ensuring long-term sustainability.'
                }
            },
            {
                type: 'core/heading',
                attributes: {
                    content: '🛠️ Implementation Strategy',
                    level: 2
                }
            },
            {
                type: 'core/list',
                attributes: {
                    ordered: false
                },
                innerBlocks: [
                    {
                        type: 'core/list-item',
                        attributes: {
                            content: '<strong>Phase 1: Assessment</strong><br>• Current state analysis<br>• Gap identification<br>• Opportunity evaluation'
                        }
                    },
                    {
                        type: 'core/list-item',
                        attributes: {
                            content: '<strong>Phase 2: Planning</strong><br>• Goal setting and KPI definition<br>• Resource allocation<br>• Timeline development'
                        }
                    },
                    {
                        type: 'core/list-item',
                        attributes: {
                            content: '<strong>Phase 3: Execution</strong><br>• Implementation of planned strategies<br>• Progress monitoring<br>• Adjustment and optimization'
                        }
                    },
                    {
                        type: 'core/list-item',
                        attributes: {
                            content: '<strong>Phase 4: Evaluation</strong><br>• Results measurement<br>• Success criteria validation<br>• Lessons learned documentation'
                        }
                    }
                ]
            },
            {
                type: 'core/heading',
                attributes: {
                    content: '📈 Success Metrics',
                    level: 2
                }
            },
            {
                type: 'core/list',
                attributes: {
                    ordered: false
                },
                innerBlocks: [
                    {
                        type: 'core/list-item',
                        attributes: {
                            content: '<strong>Performance Improvement</strong>: Measure efficiency gains and productivity increases'
                        }
                    },
                    {
                        type: 'core/list-item',
                        attributes: {
                            content: '<strong>Cost Savings</strong>: Track financial impact and ROI'
                        }
                    },
                    {
                        type: 'core/list-item',
                        attributes: {
                            content: '<strong>Customer Satisfaction</strong>: Monitor feedback and satisfaction scores'
                        }
                    },
                    {
                        type: 'core/list-item',
                        attributes: {
                            content: '<strong>Market Position</strong>: Assess competitive standing and market share'
                        }
                    }
                ]
            },
            {
                type: 'core/heading',
                attributes: {
                    content: '🤝 Partnership Opportunities',
                    level: 2
                }
            },
            {
                type: 'core/paragraph',
                attributes: {
                    content: 'We believe in collaborative success. Whether you\'re looking for consultation, implementation support, or ongoing partnership, we\'re here to help you achieve your goals.'
                }
            },
            {
                type: 'core/heading',
                attributes: {
                    content: '📞 Next Steps',
                    level: 2
                }
            },
            {
                type: 'core/paragraph',
                attributes: {
                    content: 'Ready to transform your approach to ' + title + '? Contact us today to schedule a consultation and discover how we can help you achieve your objectives.'
                }
            }
        ];
    }
}

function insertContentIntoEditor(content) {
    console.log('Siloq AI: Inserting content into editor');
    
    // Try Gutenberg first
    if (typeof wp !== 'undefined' && wp.data && wp.data.select('core/editor')) {
        try {
            // Convert content array to WordPress blocks
            var blocks = content.map(function(blockData) {
                if (blockData.innerBlocks) {
                    return wp.blocks.createBlock(blockData.type, blockData.attributes, blockData.innerBlocks);
                } else {
                    return wp.blocks.createBlock(blockData.type, blockData.attributes);
                }
            });
            
            // Get current blocks
            var currentBlocks = wp.data.select('core/editor').getBlocks();
            
            if (currentBlocks.length > 0) {
                // Replace first block with our content
                wp.data.dispatch('core/editor').replaceBlocks(currentBlocks[0].clientId, blocks);
            } else {
                // Insert blocks if editor is empty
                wp.data.dispatch('core/editor').insertBlocks(blocks);
            }
            
            console.log('Siloq AI: Content inserted via Gutenberg blocks');
            return;
        } catch (e) {
            console.log('Siloq AI: Gutenberg method failed:', e);
        }
    }
    
    // Fallback: Convert blocks to HTML for other editors
    var htmlContent = '';
    content.forEach(function(block) {
        switch (block.type) {
            case 'core/heading':
                var level = block.attributes.level || 2;
                htmlContent += '<h' + level + '>' + block.attributes.content + '</h' + level + '>\n\n';
                break;
            case 'core/paragraph':
                htmlContent += '<p>' + block.attributes.content + '</p>\n\n';
                break;
            case 'core/list':
                var listType = block.attributes.ordered ? 'ol' : 'ul';
                htmlContent += '<' + listType + '>\n';
                if (block.innerBlocks) {
                    block.innerBlocks.forEach(function(item) {
                        htmlContent += '<li>' + item.attributes.content + '</li>\n';
                    });
                }
                htmlContent += '</' + listType + '>\n\n';
                break;
            case 'core/separator':
                htmlContent += '<hr>\n\n';
                break;
        }
    });
    
    // Try TinyMCE
    if (typeof tinyMCE !== 'undefined' && tinyMCE.activeEditor) {
        try {
            tinyMCE.activeEditor.setContent(htmlContent);
            console.log('Siloq AI: Content inserted via TinyMCE');
            return;
        } catch (e) {
            console.log('Siloq AI: TinyMCE method failed:', e);
        }
    }
    
    // Try textarea
    var textarea = document.querySelector('#content') || document.querySelector('textarea[name="content"]');
    if (textarea) {
        textarea.value = htmlContent;
        console.log('Siloq AI: Content inserted via textarea');
        return;
    }
    
    // Try contenteditable
    var editable = document.querySelector('[contenteditable="true"]');
    if (editable) {
        editable.innerHTML = htmlContent;
        console.log('Siloq AI: Content inserted via contenteditable');
        return;
    }
    
    console.log('Siloq AI: Could not find editor to insert content');
}

console.log('Siloq AI: Script initialization complete');
