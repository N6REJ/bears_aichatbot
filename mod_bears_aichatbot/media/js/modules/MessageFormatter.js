/**
 * Message Formatter Module
 * Handles markdown and HTML formatting for chat messages
 */
export class MessageFormatter {
  constructor() {
    this.placeholderIndex = 0;
    this.blocks = [];
    this.autoLinks = [];
    this.inlineCodes = [];
  }

  formatAnswer(text) {
    if (!text) return '';
    
    // Reset state for each formatting operation
    this.placeholderIndex = 0;
    this.blocks = [];
    this.autoLinks = [];
    this.inlineCodes = [];
    
    let out = String(text);
    
    // Extract fenced code blocks and replace with placeholders
    out = out.replace(/```([\s\S]*?)```/g, (_, code) => {
      const token = `[[[CODEBLOCK_${this.placeholderIndex++}]]]`;
      this.blocks.push(code);
      return token;
    });

    // Convert Markdown autolinks <url> to placeholders
    out = out.replace(/<((?:https?|mailto):[^\s>]+)>/g, (_, url) => {
      const token = `[[[AUTOLINK_${this.autoLinks.length}]]]`;
      this.autoLinks.push(url);
      return token;
    });

    // Handle custom knowledge tags
    out = out.replace(/<kb>([\s\S]*?)<\/kb>/gi, (_, inner) => inner);
    out = out.replace(/<\/?kb\s*\/?>(?=\s|$)/gi, '');

    // Escape HTML for remaining text
    out = this.escapeHtml(out);

    // Inline code `...` - protect with placeholders
    out = out.replace(/`([^`]+)`/g, (_, code) => {
      const token = `[[[INLINECODE_${this.inlineCodes.length}]]]`;
      this.inlineCodes.push(code);
      return token;
    });

    // Markdown links [text](url)
    out = out.replace(/\[([^\]]+)\]\((https?:\/\/[^\s)]+)\)/g, 
      '<a href="$2" target="_blank" rel="noopener noreferrer">$1</a>');

    // Autolink any remaining plain URLs
    out = this.autolink(out);

    // Restore autolink placeholders
    out = out.replace(/\[\[\[AUTOLINK_(\d+)\]\]\]/g, (_, idx) => {
      const url = this.autoLinks[Number(idx)] || '';
      const safeHref = url.replace(/\"/g, '&quot;');
      return `<a href="${safeHref}" target="_blank" rel="noopener noreferrer">${this.escapeHtml(url)}</a>`;
    });

    // Extended Markdown conversions
    out = this.unescapeMarkdownEscapes(out);
    
    // Horizontal rules
    out = out.replace(/^(?:\s*)(?:[-*_]){3,}\s*$/gm, '<hr>');
    
    // Images
    out = out.replace(/!\[([^\]]*)\]\((https?:\/\/[^\s)]+)\)/g, '<img src="$2" alt="$1">');
    
    // Strikethrough
    out = out.replace(/~~([^~]+)~~/g, '<del>$1</del>');
    
    // Headings and blockquotes
    out = this.headingify(out);
    out = this.blockquoteify(out);
    
    // Apply inline emphasis (bold/italic)
    out = this.applyInlineMd(out);

    // Restore code blocks
    out = out.replace(/\[\[\[INLINECODE_(\d+)\]\]\]/g, (_, idx) => {
      const code = this.inlineCodes[Number(idx)] || '';
      return `<code>${this.escapeHtml(code)}</code>`;
    });

    out = out.replace(/\[\[\[CODEBLOCK_(\d+)\]\]\]/g, (_, idx) => {
      const code = this.blocks[Number(idx)] || '';
      return `<pre><code>${this.escapeHtml(code)}</code></pre>`;
    });

    // Normalize escaped ordered list markers
    out = this.unescapeOrderedListMarkers(out);

    // Convert lists
    out = this.orderedListify(out);
    out = this.listify(out);

    // Convert remaining text blocks/newlines into paragraphs
    out = this.paragraphify(out);

    return out;
  }

  escapeHtml(str) {
    return String(str).replace(/[&<>"']/g, (s) => {
      switch (s) {
        case '&': return '&amp;';
        case '<': return '&lt;';
        case '>': return '&gt;';
        case '"': return '&quot;';
        case "'": return '&#39;';
        default: return s;
      }
    });
  }

  autolink(str) {
    return String(str).replace(/(https?:\/\/[^\s<]+)(?![^<]*>)/g, 
      '<a href="$1" target="_blank" rel="noopener noreferrer">$1</a>');
  }

  listify(html) {
    const lines = String(html).split(/\r?\n/);
    const out = [];
    let inList = false;
    
    for (let i = 0; i < lines.length; i++) {
      const ln = lines[i];
      if (/^\s*[-*]\s+/.test(ln)) {
        if (!inList) { 
          out.push('<ul>'); 
          inList = true; 
        }
        out.push('<li>' + ln.replace(/^\s*[-*]\s+/, '') + '</li>');
      } else {
        if (inList) { 
          out.push('</ul>'); 
          inList = false; 
        }
        out.push(ln);
      }
    }
    
    if (inList) out.push('</ul>');
    return out.join('\n');
  }

  orderedListify(html) {
    const lines = String(html).split(/\r?\n/);
    const out = [];
    let inList = false;
    
    for (let i = 0; i < lines.length; i++) {
      const ln = lines[i];
      const m = ln.match(/^\s*(\d+)[\.)]\s+(.*)$/);
      if (m) {
        if (!inList) { 
          out.push('<ol>'); 
          inList = true; 
        }
        out.push('<li>' + m[2] + '</li>');
      } else {
        if (inList) { 
          out.push('</ol>'); 
          inList = false; 
        }
        out.push(ln);
      }
    }
    
    if (inList) out.push('</ol>');
    return out.join('\n');
  }

  unescapeOrderedListMarkers(str) {
    return String(str).replace(/(\d+)\\\./g, '$1.');
  }

  unescapeMarkdownEscapes(str) {
    return String(str).replace(/\\([\\`*_~\[\](){}#+.!-])/g, '$1');
  }

  headingify(html) {
    return String(html).replace(/^\s{0,3}(#{1,6})\s+(.+)$/gm, (_, hashes, txt) => {
      const lvl = Math.min(Math.max(hashes.length, 1), 6);
      return `<h${lvl}>${txt.trim()}</h${lvl}>`;
    });
  }

  blockquoteify(html) {
    const lines = String(html).split(/\r?\n/);
    const out = [];
    let inBQ = false;
    let buf = [];
    
    const flush = () => {
      if (inBQ) {
        out.push('<blockquote>' + buf.join('<br>') + '</blockquote>');
        buf = [];
        inBQ = false;
      }
    };
    
    for (let i = 0; i < lines.length; i++) {
      const ln = lines[i];
      const t = ln.trim();
      if (/^(?:&gt;|>)\s?/.test(t)) {
        inBQ = true;
        buf.push(t.replace(/^(?:&gt;|>)\s?/, ''));
      } else {
        flush();
        out.push(ln);
      }
    }
    
    flush();
    return out.join('\n');
  }

  applyInlineMd(str) {
    let s = String(str);
    
    // Protect placeholders from being processed
    const placeholders = [];
    s = s.replace(/\[\[\[(?:CODEBLOCK|INLINECODE|AUTOLINK)_\d+\]\]\]/g, (match) => {
      placeholders.push(match);
      return `%%%PLACEHOLDER_${placeholders.length - 1}%%%`;
    });
    
    // Bold
    s = s.replace(/\*\*([^*]+)\*\*/g, '<strong>$1</strong>');
    s = s.replace(/__([^_]+)__/g, '<strong>$1</strong>');
    
    // Italic
    s = s.replace(/(\s|^)\*([^*\n]+)\*(\s|$|[.,!?;:])/g, '$1<em>$2</em>$3');
    s = s.replace(/(\s|^)_([^_\n]+)_(\s|$|[.,!?;:])/g, '$1<em>$2</em>$3');
    
    // Restore placeholders
    s = s.replace(/%%%PLACEHOLDER_(\d+)%%%/g, (_, idx) => {
      return placeholders[Number(idx)] || '';
    });
    
    return s;
  }

  paragraphify(html) {
    const lines = String(html).split(/\r?\n/);
    const out = [];
    let para = [];
    
    const isBlockLine = (ln) => 
      /^(<\s*\/?\s*(ul|ol|li|pre|code|table|thead|tbody|tr|td|th|blockquote|h[1-6]|hr)\b|<\s*\/\s*(ul|ol|pre|table|blockquote|h[1-6]|hr))/i.test(ln.trim());

    const flushPara = () => {
      if (!para.length) return;
      const content = para.join('<br>');
      out.push('<p>' + content + '</p>');
      para = [];
    };

    for (let i = 0; i < lines.length; i++) {
      const ln = lines[i];
      const trimmed = ln.trim();
      
      if (!trimmed) {
        flushPara();
        continue;
      }
      
      if (isBlockLine(trimmed)) {
        flushPara();
        out.push(trimmed);
      } else {
        para.push(trimmed);
      }
    }
    
    flushPara();
    return out.join('\n');
  }
}

// Create singleton instance
export const messageFormatter = new MessageFormatter();
