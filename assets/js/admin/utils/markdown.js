import { marked } from 'marked';
import DOMPurify from 'dompurify';

// Enable GitHub Flavored Markdown and line breaks
marked.setOptions({
    gfm: true,
    breaks: true,
});

const defaultRenderer = new marked.Renderer();
marked.use({
    renderer: {
        table(token) {
            const html = defaultRenderer.table.call(this, token);
            const wrapped = html.replace('<table>', '<table class="botplus-table-content">');
            return `<div class="botplus-table-scroll-wrap">\n${wrapped}</div>\n`;
        },
    },
});

// Resolve the active DOMPurify instance (handles browser bundle and factory exports)
const purify = (typeof DOMPurify === 'function' && typeof window !== 'undefined' && !DOMPurify.sanitize)
    ? DOMPurify(window)
    : DOMPurify;

// Enforce target="_blank" and rel="noopener noreferrer" on all links securely
if (typeof purify?.addHook === 'function') {
    purify.addHook('afterSanitizeAttributes', function (node) {
        if ('target' in node) {
            node.setAttribute('target', '_blank');
            node.setAttribute('rel', 'noopener noreferrer');
        }
    });
}

/**
 * Parse and sanitize Markdown text into safe HTML with image heuristics.
 *
 * @param {string} content
 * @returns {string} Sanitized HTML string
 */
export const parseMarkdown = (content) => {
    if (!content || typeof content !== 'string') return '';

    try {
        // 1. Normalize linebreaks and whitespace without collapsing intentional lists
        let processed = content
            .replace(/\r\n/g, '\n')
            .replace(/\n[ \t]+\n/g, '\n\n')
            .replace(/\n{3,}/g, '\n\n')
            .trim();

        // 1.5 Plugin Inbox: Unwrap media-proxy URLs directly to source image URLs
        processed = processed.replace(
            /(?:https?:\/\/[^\/]+)?\/api\/chat\/[^\/]+\/media-proxy\?url=([^\s\)\"\']+)/gi,
            (_match, encodedUrl) => {
                try {
                    return decodeURIComponent(encodedUrl);
                } catch (_e) {
                    return encodedUrl;
                }
            }
        );

        // 2. Convert raw image URLs to markdown image syntax ![]($url)
        processed = processed.replace(
            /(?<!\]\()(?<!src=")(https?:\/\/[^\s<]+(?:\.png|\.jpe?g|\.gif|\.webp|\.svg)(?:[\?\&][^\s<]*)?)/gi,
            '![]($1)'
        );

        // 3. Convert regular markdown links pointing to images into markdown images
        processed = processed.replace(
            /(?<!\!)\[([^\]]*)\]\((https?:\/\/[^\s\)]+(?:\.png|\.jpe?g|\.gif|\.webp|\.svg)(?:[\?\&][^\s\)]*)?)\)/gi,
            '![$1]($2)'
        );

        // 4. Convert hallucinated markdown images pointing to standard web pages back into clickable links
        processed = processed.replace(/\!\[([^\]]*)\]\((https?:\/\/[^\s\)]+)\)/gi, (match, alt, url) => {
            if (/\.(png|jpe?g|gif|webp|svg)(?:[\?\&][^\s\)]*)?$/i.test(url)) {
                return match;
            }
            const linkText = alt.trim() ? alt : url;
            return `[${linkText}](${url})`;
        });

        // 5. Compile with marked and sanitize with DOMPurify
        const parsed = marked.parse(processed);
        if (typeof purify?.sanitize === 'function') {
            return purify.sanitize(parsed);
        }
        return parsed;
    } catch (_err) {
        if (typeof purify?.sanitize === 'function') {
            return purify.sanitize(content);
        }
        return content;
    }
};

