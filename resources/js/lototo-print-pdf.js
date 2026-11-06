/**
 * Minimal PDF writer for the LOTOTO print designs: one page per design, the page exactly the design's printed size
 * (MediaBox in points from millimetres), its picture covering the whole page. No margins, no fitting to A4: printed
 * at "Actual size" the paper shows the design at its own size. Pictures are RGB, Flate-compressed when the browser
 * offers CompressionStream.
 */

const encoder = new TextEncoder();
const MM_TO_PT = 72 / 25.4;

async function deflate(bytes) {
    if (typeof CompressionStream === 'undefined') return null;
    const stream = new Blob([bytes]).stream().pipeThrough(new CompressionStream('deflate'));

    return new Uint8Array(await new Response(stream).arrayBuffer());
}

const pt = (mm) => (mm * MM_TO_PT).toFixed(4).replace(/\.?0+$/, '');

/**
 * pages: [{ widthMm, heightMm, pixelWidth, pixelHeight, rgb: Uint8Array (pixelWidth * pixelHeight * 3) }].
 * Returns the PDF file as bytes.
 */
export async function buildPdf(pages, { title = 'LOTOTO QR' } = {}) {
    const chunks = [];
    const offsets = [];
    let length = 0;
    const push = (part) => {
        const bytes = typeof part === 'string' ? encoder.encode(part) : part;
        chunks.push(bytes);
        length += bytes.length;
    };
    const object = (number, body, stream = null) => {
        offsets[number] = length;
        push(`${number} 0 obj\n${body}\n`);
        if (stream) {
            push('stream\n');
            push(stream);
            push('\nendstream\n');
        }
        push('endobj\n');
    };

    push('%PDF-1.4\n%âãÏÓ\n');
    // 1 catalog, 2 page tree, 3 info; then per page: page, contents, image.
    const kids = pages.map((_, index) => `${4 + index * 3} 0 R`).join(' ');
    object(1, '<< /Type /Catalog /Pages 2 0 R >>');
    object(2, `<< /Type /Pages /Kids [${kids}] /Count ${pages.length} >>`);
    object(3, `<< /Title (${title.replace(/[()\\]/g, '')}) /Producer (LOTOTO) >>`);
    for (const [index, page] of pages.entries()) {
        const pageNo = 4 + index * 3;
        const width = pt(page.widthMm);
        const height = pt(page.heightMm);
        const image = await deflate(page.rgb);
        const content = encoder.encode(`q ${width} 0 0 ${height} 0 0 cm /Im0 Do Q`);
        object(pageNo, `<< /Type /Page /Parent 2 0 R /MediaBox [0 0 ${width} ${height}] /CropBox [0 0 ${width} ${height}] /Resources << /XObject << /Im0 ${pageNo + 2} 0 R >> >> /Contents ${pageNo + 1} 0 R >>`);
        object(pageNo + 1, `<< /Length ${content.length} >>`, content);
        object(pageNo + 2, `<< /Type /XObject /Subtype /Image /Width ${page.pixelWidth} /Height ${page.pixelHeight} /ColorSpace /DeviceRGB /BitsPerComponent 8${image ? ' /Filter /FlateDecode' : ''} /Length ${(image ?? page.rgb).length} >>`, image ?? page.rgb);
    }
    const xref = length;
    const count = 4 + pages.length * 3;
    push(`xref\n0 ${count}\n0000000000 65535 f \n`);
    for (let number = 1; number < count; number++) {
        push(`${String(offsets[number]).padStart(10, '0')} 00000 n \n`);
    }
    push(`trailer\n<< /Size ${count} /Root 1 0 R /Info 3 0 R >>\nstartxref\n${xref}\n%%EOF\n`);

    const out = new Uint8Array(length);
    let at = 0;
    chunks.forEach((chunk) => { out.set(chunk, at); at += chunk.length; });

    return out;
}

/** Points of a size in mm (exported for tests). */
export const toPoints = (mm) => Number(pt(mm));
