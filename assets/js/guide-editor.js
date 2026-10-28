(() => {
  'use strict';

  const editor = document.querySelector('[data-guide-editor]');
  if (!editor) return;

  const source = editor.querySelector('[data-guide-source]');
  const preview = editor.querySelector('[data-guide-preview]');

  const appendInline = (container, text) => {
    let remaining = text;
    const pattern = /\[(b|i)\](.*?)\[\/\1\]|\[url=([^\]\r\n]+)\](.*?)\[\/url\]/i;
    while (remaining) {
      const match = remaining.match(pattern);
      if (!match) {
        container.append(document.createTextNode(remaining));
        break;
      }
      container.append(document.createTextNode(remaining.slice(0, match.index)));
      if (match[1]) {
        const element = document.createElement(match[1].toLowerCase() === 'b' ? 'strong' : 'em');
        element.textContent = match[2];
        container.append(element);
      } else {
        let safeUrl = null;
        try {
          const parsed = new URL(match[3]);
          if (parsed.protocol === 'http:' || parsed.protocol === 'https:') safeUrl = parsed.href;
        } catch (error) {
          safeUrl = null;
        }
        if (safeUrl) {
          const link = document.createElement('a');
          link.href = safeUrl;
          link.target = '_blank';
          link.rel = 'noopener noreferrer';
          link.textContent = match[4];
          container.append(link);
        } else {
          container.append(document.createTextNode(match[0]));
        }
      }
      remaining = remaining.slice(match.index + match[0].length);
    }
  };

  const renderPreview = () => {
    preview.replaceChildren();
    const input = source.value.replace(/\r\n?/g, '\n');
    const codePattern = /\[code\]([\s\S]*?)\[\/code\]/gi;
    let cursor = 0;
    let match;
    const renderText = (text) => {
      const listPattern = /\[list\]([\s\S]*?)\[\/list\]/gi;
      let textCursor = 0;
      let listMatch;
      const renderParagraphs = (plainText) => plainText.split(/\n{2,}/).filter((part) => part.trim()).forEach((part) => {
        const heading = part.trim().match(/^\[h([1-3])\]([\s\S]*?)\[\/h\1\]$/i);
        const element = document.createElement(heading ? `h${Number(heading[1]) + 1}` : 'p');
        appendInline(element, heading ? heading[2] : part.trim());
        preview.append(element);
      });
      while ((listMatch = listPattern.exec(text)) !== null) {
        renderParagraphs(text.slice(textCursor, listMatch.index));
        const list = document.createElement('ul');
        listMatch[1].split(/\[\*\]/i).map((item) => item.trim()).filter(Boolean).forEach((item) => {
          const listItem = document.createElement('li');
          appendInline(listItem, item);
          list.append(listItem);
        });
        preview.append(list);
        textCursor = listMatch.index + listMatch[0].length;
      }
      renderParagraphs(text.slice(textCursor));
    };
    while ((match = codePattern.exec(input)) !== null) {
      renderText(input.slice(cursor, match.index));
      const pre = document.createElement('pre');
      const code = document.createElement('code');
      code.textContent = match[1].replace(/^\n|\n$/g, '');
      pre.append(code);
      preview.append(pre);
      cursor = match.index + match[0].length;
    }
    renderText(input.slice(cursor));
  };

  editor.querySelectorAll('[data-format-open]').forEach((button) => {
    button.addEventListener('click', () => {
      const start = source.selectionStart;
      const end = source.selectionEnd;
      const open = button.dataset.formatOpen || '';
      const close = button.dataset.formatClose || '';
      source.setRangeText(`${open}${source.value.slice(start, end)}${close}`, start, end, 'end');
      source.focus();
      renderPreview();
    });
  });
  source.addEventListener('input', renderPreview);
  renderPreview();
})();
