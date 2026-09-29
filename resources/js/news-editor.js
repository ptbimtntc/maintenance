import 'trix';
import 'trix/dist/trix.css';

// News bodies are plain text/paragraphs stored as sanitized HTML
// (see App\Support\HtmlSanitizer) - file attachments have no upload
// endpoint on the backend, so drag/drop or pasted files are rejected
// rather than silently producing a broken embed.
document.addEventListener('trix-file-accept', (event) => {
    event.preventDefault();
});
