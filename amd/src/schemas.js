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
 * HelpAI schemas interface JavaScript.
 *
 * @module     block_helpai/schemas
 * @copyright  2025 Your Name
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import Ajax from 'core/ajax';
import Notification from 'core/notification';

let courseid = 0;
let contextid = 0;

/**
 * Initialize the schemas interface.
 *
 * @param {Object} config Configuration object with courseid and contextid
 */
export const init = (config) => {
    // eslint-disable-next-line no-console
    console.log('HelpAI Schemas: init() called with config:', config);

    courseid = parseInt(config.courseid, 10);
    contextid = parseInt(config.contextid, 10);

    // eslint-disable-next-line no-console
    console.log('HelpAI Schemas: Parsed courseid=' + courseid + ', contextid=' + contextid);

    // Set up tab switching.
    setupTabSwitching();

    // Load PDFs list when schemas tab is visible.
    const schemasSection = document.querySelector('[data-section="schemas"]');
    if (schemasSection && schemasSection.classList.contains('active')) {
        loadPdfsList();
    }

    // Set up close button for schema viewer.
    const closeButton = document.getElementById('block-helpai-close-schema');
    if (closeButton) {
        closeButton.addEventListener('click', () => {
            closeSchemaViewer();
        });
    }
};

/**
 * Set up tab switching functionality.
 */
const setupTabSwitching = () => {
    const tabs = document.querySelectorAll('.block-helpai-tab');

    // eslint-disable-next-line no-console
    console.log('HelpAI Schemas: Setting up tab switching, found tabs:', tabs.length);

    tabs.forEach((tab) => {
        tab.addEventListener('click', () => {
            const targetTab = tab.dataset.tab;

            // eslint-disable-next-line no-console
            console.log('HelpAI Schemas: Tab clicked:', targetTab);

            // Remove active class from all tabs.
            tabs.forEach((t) => t.classList.remove('active'));

            // Add active class to clicked tab.
            tab.classList.add('active');

            // Hide all sections.
            const sections = document.querySelectorAll('.block-helpai-section');
            sections.forEach((section) => section.classList.remove('active'));

            // Show target section.
            const targetSection = document.querySelector(`[data-section="${targetTab}"]`);
            if (targetSection) {
                targetSection.classList.add('active');

                // Load PDFs if switching to schemas tab.
                if (targetTab === 'schemas') {
                    loadPdfsList();
                }
            }
        });
    });
};

/**
 * Load the list of PDFs in the course.
 */
const loadPdfsList = () => {
    const listContainer = document.getElementById('block-helpai-schemas-list');
    if (!listContainer) {
        return;
    }

    listContainer.innerHTML = '<div class="block-helpai-loading">' +
        M.util.get_string('loading_pdfs', 'block_helpai') + '</div>';

    Ajax.call([{
        methodname: 'block_helpai_get_course_pdfs',
        args: {
            courseid: courseid,
        },
    }])[0].then((response) => {
        if (response.pdfs && response.pdfs.length > 0) {
            renderPdfsList(response.pdfs);
        } else {
            listContainer.innerHTML = '<div class="block-helpai-no-pdfs">' +
                M.util.get_string('no_pdfs_in_course', 'block_helpai') + '</div>';
        }
        return true;
    }).catch((error) => {
        listContainer.innerHTML = '<div class="block-helpai-error">Error loading PDFs</div>';
        Notification.exception(error);
    });
};

/**
 * Render the list of PDFs with their schema status.
 *
 * @param {Array} pdfs Array of PDF objects
 */
const renderPdfsList = (pdfs) => {
    const listContainer = document.getElementById('block-helpai-schemas-list');
    listContainer.innerHTML = '';

    pdfs.forEach((pdf) => {
        const pdfItem = document.createElement('div');
        pdfItem.className = 'block-helpai-pdf-item';

        // PDF name.
        const pdfName = document.createElement('div');
        pdfName.className = 'block-helpai-pdf-name';
        pdfName.textContent = pdf.name;
        pdfItem.appendChild(pdfName);

        // PDF filename (smaller).
        const pdfFilename = document.createElement('div');
        pdfFilename.className = 'block-helpai-pdf-filename';
        pdfFilename.textContent = pdf.filename;
        pdfItem.appendChild(pdfFilename);

        // Actions.
        const pdfActions = document.createElement('div');
        pdfActions.className = 'block-helpai-pdf-actions';

        if (pdf.hasschema) {
            // View schema button.
            const viewButton = document.createElement('button');
            viewButton.className = 'btn btn-sm btn-primary';
            viewButton.textContent = M.util.get_string('view_schema', 'block_helpai');
            viewButton.addEventListener('click', () => {
                viewSchema(pdf.cmid, pdf.name);
            });
            pdfActions.appendChild(viewButton);

            // Regenerate button.
            const regenButton = document.createElement('button');
            regenButton.className = 'btn btn-sm btn-secondary';
            regenButton.textContent = M.util.get_string('regenerate_schema', 'block_helpai');
            regenButton.addEventListener('click', () => {
                generateSchema(pdf.cmid, pdf.name);
            });
            pdfActions.appendChild(regenButton);
        } else {
            // Generate schema button.
            const generateButton = document.createElement('button');
            generateButton.className = 'btn btn-sm btn-primary';
            generateButton.textContent = M.util.get_string('generate_schema', 'block_helpai');
            generateButton.addEventListener('click', () => {
                generateSchema(pdf.cmid, pdf.name);
            });
            pdfActions.appendChild(generateButton);
        }

        pdfItem.appendChild(pdfActions);
        listContainer.appendChild(pdfItem);
    });
};

/**
 * Generate a schema for a PDF.
 *
 * @param {number} cmid Course module ID
 * @param {string} pdfname PDF name
 */
const generateSchema = (cmid, pdfname) => {
    const listContainer = document.getElementById('block-helpai-schemas-list');

    // Show loading indicator.
    const loadingDiv = document.createElement('div');
    loadingDiv.className = 'block-helpai-generating';
    loadingDiv.id = 'generating-' + cmid;
    loadingDiv.innerHTML = '<strong>' + pdfname + '</strong><br>' +
        M.util.get_string('generating_schema', 'block_helpai');

    // Insert at top of list.
    listContainer.insertBefore(loadingDiv, listContainer.firstChild);

    Ajax.call([{
        methodname: 'block_helpai_generate_schema',
        args: {
            courseid: courseid,
            cmid: cmid,
        },
    }])[0].then((response) => {
        // Remove loading indicator.
        const loadingElement = document.getElementById('generating-' + cmid);
        if (loadingElement) {
            loadingElement.remove();
        }

        if (response.success) {
            // Show the schema.
            displaySchema(pdfname, response.outline, response.timecreated);

            // Reload PDFs list to update status.
            setTimeout(() => {
                loadPdfsList();
            }, 500);
        } else {
            Notification.addNotification({
                message: response.error || M.util.get_string('schema_generation_failed', 'block_helpai'),
                type: 'error',
            });
        }
        return true;
    }).catch((error) => {
        // Remove loading indicator.
        const loadingElement = document.getElementById('generating-' + cmid);
        if (loadingElement) {
            loadingElement.remove();
        }
        Notification.exception(error);
    });
};

/**
 * View an existing schema.
 *
 * @param {number} cmid Course module ID
 * @param {string} pdfname PDF name
 */
const viewSchema = (cmid, pdfname) => {
    Ajax.call([{
        methodname: 'block_helpai_get_schema',
        args: {
            courseid: courseid,
            cmid: cmid,
        },
    }])[0].then((response) => {
        if (response.success) {
            displaySchema(pdfname, response.outline, response.timecreated);
        } else {
            Notification.addNotification({
                message: M.util.get_string('schema_generation_failed', 'block_helpai'),
                type: 'error',
            });
        }
        return true;
    }).catch((error) => {
        Notification.exception(error);
    });
};

/**
 * Display a schema in the viewer.
 *
 * @param {string} pdfname PDF name
 * @param {string} outline Schema content
 * @param {number} timecreated Creation timestamp
 */
const displaySchema = (pdfname, outline, timecreated) => {
    const viewer = document.getElementById('block-helpai-schema-viewer');
    const listContainer = document.getElementById('block-helpai-schemas-list');
    const titleElement = document.getElementById('block-helpai-schema-title');
    const dateElement = document.getElementById('block-helpai-schema-date');
    const contentElement = document.getElementById('block-helpai-schema-content');

    if (!viewer || !titleElement || !dateElement || !contentElement) {
        return;
    }

    // Set title.
    titleElement.textContent = M.util.get_string('schema_for', 'block_helpai').replace('{$a}', pdfname);

    // Set date.
    const date = new Date(timecreated * 1000);
    const dateString = date.toLocaleDateString() + ' ' + date.toLocaleTimeString();
    dateElement.textContent = M.util.get_string('generated_on', 'block_helpai').replace('{$a}', dateString);

    // Set content - convert markdown to HTML.
    contentElement.innerHTML = formatMarkdown(outline);

    // Hide list and show viewer.
    listContainer.style.display = 'none';
    viewer.style.display = 'block';
};

/**
 * Close the schema viewer.
 */
const closeSchemaViewer = () => {
    const viewer = document.getElementById('block-helpai-schema-viewer');
    const listContainer = document.getElementById('block-helpai-schemas-list');

    if (viewer && listContainer) {
        viewer.style.display = 'none';
        listContainer.style.display = 'block';
    }
};

/**
 * Format markdown text to HTML.
 *
 * @param {string} text Markdown text
 * @return {string} HTML formatted text
 */
const formatMarkdown = (text) => {
    // Escape HTML first.
    let formatted = text
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');

    // Process line by line.
    const lines = formatted.split('\n');
    const output = [];
    let inList = false;
    let listType = null;

    for (let i = 0; i < lines.length; i++) {
        let line = lines[i];

        // Headers.
        if (line.match(/^###\s+/)) {
            if (inList) {
                output.push(`</${listType}>`);
                inList = false;
            }
            line = '<h5>' + line.replace(/^###\s+/, '') + '</h5>';
        } else if (line.match(/^##\s+/)) {
            if (inList) {
                output.push(`</${listType}>`);
                inList = false;
            }
            line = '<h4>' + line.replace(/^##\s+/, '') + '</h4>';
        } else if (line.match(/^#\s+/)) {
            if (inList) {
                output.push(`</${listType}>`);
                inList = false;
            }
            line = '<h3>' + line.replace(/^#\s+/, '') + '</h3>';
        }
        // Numbered lists.
        else if (line.match(/^\d+\.\s+/)) {
            if (!inList || listType !== 'ol') {
                if (inList) {
                    output.push(`</${listType}>`);
                }
                output.push('<ol>');
                inList = true;
                listType = 'ol';
            }
            line = '<li>' + line.replace(/^\d+\.\s+/, '') + '</li>';
        }
        // Bullet lists.
        else if (line.match(/^[-*]\s+/)) {
            if (!inList || listType !== 'ul') {
                if (inList) {
                    output.push(`</${listType}>`);
                }
                output.push('<ul>');
                inList = true;
                listType = 'ul';
            }
            line = '<li>' + line.replace(/^[-*]\s+/, '') + '</li>';
        }
        // Empty lines.
        else if (line.trim() === '') {
            if (inList) {
                output.push(`</${listType}>`);
                inList = false;
                listType = null;
            }
            line = '<br>';
        }
        // Regular paragraph.
        else if (line.trim() !== '') {
            if (inList) {
                output.push(`</${listType}>`);
                inList = false;
                listType = null;
            }
            line = '<p>' + line + '</p>';
        }

        output.push(line);
    }

    // Close any open list.
    if (inList) {
        output.push(`</${listType}>`);
    }

    formatted = output.join('\n');

    // Bold and italic.
    formatted = formatted.replace(/\*\*([^*]+?)\*\*/g, '<strong>$1</strong>');
    formatted = formatted.replace(/__([^_]+?)__/g, '<strong>$1</strong>');
    formatted = formatted.replace(/\*([^*<]+?)\*/g, '<em>$1</em>');
    formatted = formatted.replace(/_([^_<]+?)_/g, '<em>$1</em>');

    return formatted;
};
