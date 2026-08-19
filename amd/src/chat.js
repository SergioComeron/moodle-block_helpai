// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * HelpAI chat interface JavaScript.
 *
 * @module     block_helpai/chat
 * @copyright  2025–2026 Sergio Comerón
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import Ajax from 'core/ajax';
import Notification from 'core/notification';

let courseid = 0;
let contextid = 0;

/**
 * Initialize the chat interface.
 *
 * @param {Object} config Configuration object with courseid and contextid
 */
export const init = (config) => {
    courseid = parseInt(config.courseid, 10);
    contextid = parseInt(config.contextid, 10);

    const sendButton = document.getElementById('block-helpai-send');
    const input = document.getElementById('block-helpai-input');

    if (sendButton && input) {
        sendButton.addEventListener('click', () => {
            sendMessage();
        });

        input.addEventListener('keypress', (e) => {
            if (e.key === 'Enter' && !e.shiftKey) {
                e.preventDefault();
                sendMessage();
            }
        });
    }

    // Add event listener for clear history button.
    const clearButton = document.getElementById('block-helpai-clear');
    if (clearButton) {
        clearButton.addEventListener('click', () => {
            clearHistory();
        });
    }

    // Add event listeners for quick action buttons.
    const quickActions = document.querySelectorAll('.block-helpai-quick-action');
    quickActions.forEach((button) => {
        button.addEventListener('click', () => {
            const question = button.dataset.question;
            if (question) {
                input.value = question;
                input.focus();
            }
        });
    });

    // Load chat history.
    loadHistory();
};

/**
 * Send a message to the AI.
 */
const sendMessage = () => {
    const input = document.getElementById('block-helpai-input');
    const question = input.value.trim();

    if (!question) {
        return;
    }

    // Add user message to chat.
    addMessage(question, 'user');

    // Clear input.
    input.value = '';

    // Show loading indicator.
    addLoadingMessage();

    // Call AJAX to process question.
    Ajax.call([{
        methodname: 'block_helpai_process_question',
        args: {
            courseid: courseid,
            question: question,
        },
    }])[0].then((response) => {
        removeLoadingMessage();

        if (response.success) {
            addMessage(response.message, 'assistant');

            // Add PDF links if available.
            if (response.pdfs && response.pdfs.length > 0) {
                addPdfLinks(response.pdfs);
            }
        } else {
            addMessage(response.message, 'error');
        }

        return true;
    }).catch((error) => {
        removeLoadingMessage();
        Notification.exception(error);
    });
};

/**
 * Add a message to the chat.
 *
 * @param {string} text Message text
 * @param {string} type Message type (user, assistant, error)
 */
const addMessage = (text, type) => {
    const messagesContainer = document.getElementById('block-helpai-messages');
    const messageDiv = document.createElement('div');
    messageDiv.className = `block-helpai-message block-helpai-message-${type}`;

    // For assistant messages, process markdown-like formatting.
    if (type === 'assistant') {
        messageDiv.innerHTML = formatMessage(text);
    } else {
        messageDiv.textContent = text;
    }

    messagesContainer.appendChild(messageDiv);
    messagesContainer.scrollTop = messagesContainer.scrollHeight;
};

/**
 * Format message text with basic markdown support.
 *
 * @param {string} text The text to format
 * @return {string} HTML formatted text
 */
const formatMessage = (text) => {
    // Escape HTML first to prevent XSS.
    let formatted = text
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');

    // Convert markdown bold (**text**) to <strong> - do this BEFORE single asterisks.
    formatted = formatted.replace(/\*\*([^*]+?)\*\*/g, '<strong>$1</strong>');
    formatted = formatted.replace(/__([^_]+?)__/g, '<strong>$1</strong>');

    // Convert markdown italic (*text*) to <em> - but avoid already processed text.
    formatted = formatted.replace(/\*([^*<]+?)\*/g, '<em>$1</em>');
    formatted = formatted.replace(/_([^_<]+?)_/g, '<em>$1</em>');

    // Convert line breaks to <br> - but do this BEFORE processing lists.
    const lines = formatted.split('\n');
    const processedLines = [];

    for (let i = 0; i < lines.length; i++) {
        const line = lines[i].trim();
        if (line.startsWith('- ')) {
            // This is a list item.
            processedLines.push('<li>' + line.substring(2) + '</li>');
        } else if (line === '') {
            // Empty line - add a break.
            processedLines.push('<br>');
        } else {
            // Regular line.
            processedLines.push(line + '<br>');
        }
    }

    formatted = processedLines.join('');

    // Wrap consecutive list items in <ul>.
    formatted = formatted.replace(/(<li>.*?<\/li>)+/g, (match) => {
        return '<ul>' + match + '</ul>';
    });

    // Clean up multiple consecutive <br> tags.
    formatted = formatted.replace(/(<br>){2,}/g, '<br><br>');

    return formatted;
};

/**
 * Add PDF links to the chat.
 *
 * @param {Array} pdfs Array of PDF objects
 */
const addPdfLinks = (pdfs) => {
    const messagesContainer = document.getElementById('block-helpai-messages');
    const linksDiv = document.createElement('div');
    linksDiv.className = 'block-helpai-pdf-links';

    const title = document.createElement('div');
    title.className = 'block-helpai-pdf-links-title';
    title.textContent = 'Referenced PDFs:';
    linksDiv.appendChild(title);

    pdfs.forEach((pdf) => {
        const link = document.createElement('a');
        link.href = M.cfg.wwwroot + '/mod/resource/view.php?id=' + pdf.cmid;
        link.className = 'block-helpai-pdf-link';
        link.textContent = '📄 ' + pdf.name;
        link.target = '_blank';

        const linkWrapper = document.createElement('div');
        linkWrapper.appendChild(link);
        linksDiv.appendChild(linkWrapper);
    });

    messagesContainer.appendChild(linksDiv);
    messagesContainer.scrollTop = messagesContainer.scrollHeight;
};

/**
 * Add a loading indicator.
 */
const addLoadingMessage = () => {
    const messagesContainer = document.getElementById('block-helpai-messages');
    const loadingDiv = document.createElement('div');
    loadingDiv.className = 'block-helpai-message block-helpai-message-loading';
    loadingDiv.id = 'block-helpai-loading';
    loadingDiv.textContent = 'Thinking...';

    messagesContainer.appendChild(loadingDiv);
    messagesContainer.scrollTop = messagesContainer.scrollHeight;
};

/**
 * Remove the loading indicator.
 */
const removeLoadingMessage = () => {
    const loadingDiv = document.getElementById('block-helpai-loading');
    if (loadingDiv) {
        loadingDiv.remove();
    }
};

/**
 * Load chat history from server.
 */
const loadHistory = () => {
    Ajax.call([{
        methodname: 'block_helpai_get_history',
        args: {
            courseid: courseid,
        },
    }])[0].then((response) => {
        if (response.history && response.history.length > 0) {
            response.history.forEach((item) => {
                addMessage(item.message, item.role);
            });
        }
        return true;
    }).catch((error) => {
        // eslint-disable-next-line no-console
        console.error('Error loading history:', error);
    });
};

/**
 * Clear chat history.
 */
const clearHistory = () => {
    if (!confirm(M.util.get_string('confirmclearhistory', 'block_helpai'))) {
        return;
    }

    Ajax.call([{
        methodname: 'block_helpai_clear_history',
        args: {
            courseid: courseid,
        },
    }])[0].then((response) => {
        if (response.success) {
            // Clear the messages container.
            const messagesContainer = document.getElementById('block-helpai-messages');
            messagesContainer.innerHTML = '';

            // Show success message.
            addMessage(M.util.get_string('historycleared', 'block_helpai'), 'assistant');
        }
        return true;
    }).catch((error) => {
        Notification.exception(error);
    });
};
