/**
 * PNG output of the QR print designs. The picture comes from canvas.toBlob('image/png'), a real PNG; this module
 * adds the print resolution to it (a pHYs chunk) so a design opened or placed in a document keeps its printed size
 * (600 dpi -> 23622 pixels per metre), and has small helpers to check a file really is a PNG. Pure functions on bytes,
 * no DOM, so they are tested in Node.
 */

const SIGNATURE = [0x89, 0x50, 0x4e, 0x47, 0x0d, 0x0a, 0x1a, 0x0a];
const INCH_PER_METRE = 39.3701;

const CRC_TABLE = (() => {
    const table = new Uint32Array(256);
    for (let n = 0; n < 256; n++) {
        let c = n;
        for (let k = 0; k < 8; k++) c = c & 1 ? 0xedb88320 ^ (c >>> 1) : c >>> 1;
        table[n] = c >>> 0;
    }

    return table;
})();

/** CRC-32 as PNG chunks use it (over the chunk type and data). */
export function crc32(bytes) {
    let crc = 0xffffffff;
    for (let i = 0; i < bytes.length; i++) crc = CRC_TABLE[(crc ^ bytes[i]) & 0xff] ^ (crc >>> 8);

    return (crc ^ 0xffffffff) >>> 0;
}

/** Whether the bytes start with the 8-byte PNG signature. */
export function isPng(bytes) {
    return bytes.length > 33 && SIGNATURE.every((value, index) => bytes[index] === value);
}

function* chunks(bytes) {
    const view = new DataView(bytes.buffer, bytes.byteOffset, bytes.byteLength);
    let offset = 8;
    while (offset + 12 <= bytes.length) {
        const length = view.getUint32(offset);
        const type = String.fromCharCode(...bytes.subarray(offset + 4, offset + 8));
        yield { type, offset, length, end: offset + 12 + length };
        offset += 12 + length;
    }
}

/** Size and resolution of a PNG: { width, height, dpi (null when it has none) }. */
export function readPngInfo(bytes) {
    if (!isPng(bytes)) throw new Error('Bukan file PNG.');
    const view = new DataView(bytes.buffer, bytes.byteOffset, bytes.byteLength);
    const info = { width: view.getUint32(16), height: view.getUint32(20), dpi: null };
    for (const chunk of chunks(bytes)) {
        if (chunk.type === 'pHYs' && view.getUint8(chunk.offset + 16) === 1) {
            info.dpi = Math.round(view.getUint32(chunk.offset + 8) / INCH_PER_METRE);
        }
    }

    return info;
}

/** The PNG with its resolution set to $dpi (an existing pHYs chunk is replaced); the picture itself is untouched. */
export function setPngDpi(bytes, dpi) {
    if (!isPng(bytes)) throw new Error('Bukan file PNG.');
    const perMetre = Math.round(dpi * INCH_PER_METRE);
    const body = new Uint8Array(13);
    const view = new DataView(body.buffer);
    body.set([0x70, 0x48, 0x59, 0x73]); // "pHYs"
    view.setUint32(4, perMetre);
    view.setUint32(8, perMetre);
    body[12] = 1; // unit: metre
    const chunk = new Uint8Array(21);
    const chunkView = new DataView(chunk.buffer);
    chunkView.setUint32(0, 9);
    chunk.set(body, 4);
    chunkView.setUint32(17, crc32(body));

    // The chunk goes right after IHDR (the first chunk, 25 bytes after the signature); an old pHYs is dropped.
    const kept = [bytes.subarray(0, 33)];
    for (const found of chunks(bytes)) {
        if (found.offset < 33 || found.type === 'pHYs') continue;
        kept.push(bytes.subarray(found.offset, found.end));
    }
    kept.splice(1, 0, chunk);
    const out = new Uint8Array(kept.reduce((sum, part) => sum + part.length, 0));
    let at = 0;
    kept.forEach((part) => { out.set(part, at); at += part.length; });

    return out;
}

/** A canvas as PNG bytes at $dpi. Rejects when the browser cannot make the picture (for example a canvas too large). */
export async function canvasToPng(canvas, dpi) {
    const blob = await new Promise((resolve) => { canvas.toBlob(resolve, 'image/png'); });
    if (!blob) throw new Error('Browser tidak dapat membuat gambar PNG (ukuran terlalu besar atau memori tidak cukup).');
    const bytes = new Uint8Array(await blob.arrayBuffer());
    if (!isPng(bytes)) throw new Error('Hasil bukan file PNG yang valid.');

    return setPngDpi(bytes, dpi);
}
