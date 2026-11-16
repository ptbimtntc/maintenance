// Run with: node --test tests/js/lototo-print-png.test.js
import assert from 'node:assert/strict';
import test from 'node:test';
import zlib from 'node:zlib';
import { crc32, isPng, readPngInfo, setPngDpi } from '../../resources/js/lototo-print-png.js';

/** A real PNG (8-bit RGB) made here, the same chunk layout a browser's canvas.toBlob produces: IHDR, IDAT, IEND. */
function makePng(width, height, extraChunks = []) {
    const chunk = (type, data) => {
        const out = Buffer.alloc(12 + data.length);
        out.writeUInt32BE(data.length, 0);
        out.write(type, 4, 'latin1');
        data.copy(out, 8);
        out.writeUInt32BE(crc32(new Uint8Array(out.subarray(4, 8 + data.length))), 8 + data.length);

        return out;
    };
    const ihdr = Buffer.alloc(13);
    ihdr.writeUInt32BE(width, 0);
    ihdr.writeUInt32BE(height, 4);
    ihdr.set([8, 2, 0, 0, 0], 8);
    const rows = Buffer.alloc((1 + width * 3) * height);
    for (let y = 0; y < height; y++) for (let x = 0; x < width * 3; x++) rows[y * (1 + width * 3) + 1 + x] = (x * 7 + y * 13) & 255;

    return new Uint8Array(Buffer.concat([
        Buffer.from([0x89, 0x50, 0x4e, 0x47, 0x0d, 0x0a, 0x1a, 0x0a]), chunk('IHDR', ihdr), ...extraChunks.map(([t, d]) => chunk(t, d)),
        chunk('IDAT', zlib.deflateSync(rows)), chunk('IEND', Buffer.alloc(0)),
    ]));
}

const chunkList = (bytes) => {
    const list = [];
    const view = new DataView(bytes.buffer, bytes.byteOffset, bytes.byteLength);
    for (let offset = 8; offset < bytes.length;) {
        const length = view.getUint32(offset);
        const type = String.fromCharCode(...bytes.subarray(offset + 4, offset + 8));
        const crcOk = view.getUint32(offset + 8 + length) === crc32(bytes.subarray(offset + 4, offset + 8 + length));
        list.push({ type, length, crcOk, data: Buffer.from(bytes.subarray(offset + 8, offset + 8 + length)) });
        offset += 12 + length;
    }

    return list;
};

test('crc32 matches the PNG specification (the checksum of IEND is AE 42 60 82)', () => {
    assert.equal(crc32(new Uint8Array([0x49, 0x45, 0x4e, 0x44])), 0xae426082);
});

test('a PNG is recognised by its signature, anything else is refused', () => {
    assert.equal(isPng(makePng(4, 3)), true);
    assert.equal(isPng(new TextEncoder().encode('%PDF-1.4 this is a pdf, renamed to .png, long enough to pass the size check')), false);
    assert.equal(isPng(new Uint8Array(10)), false);
    assert.throws(() => setPngDpi(new TextEncoder().encode('%PDF-1.4 not a picture at all, just text that is long enough...'), 600), /Bukan file PNG/);
    assert.throws(() => readPngInfo(new Uint8Array(40)), /Bukan file PNG/);
});

test('setPngDpi stores 600 dpi as a valid pHYs chunk right after IHDR and leaves the picture alone', () => {
    const source = makePng(40, 25);
    const output = setPngDpi(source, 600);

    assert.equal(isPng(output), true);
    assert.deepEqual(readPngInfo(output), { width: 40, height: 25, dpi: 600 });
    const before = chunkList(source);
    const after = chunkList(output);
    assert.deepEqual(after.map((c) => c.type), ['IHDR', 'pHYs', 'IDAT', 'IEND']);
    assert.ok(after.every((c) => c.crcOk), 'every chunk keeps a valid checksum');
    // 600 dpi = 23622 pixels per metre, in both directions, unit metre.
    assert.deepEqual([after[1].data.readUInt32BE(0), after[1].data.readUInt32BE(4), after[1].data[8]], [23622, 23622, 1]);
    // IHDR, the compressed picture (IDAT) and IEND are byte for byte the same.
    for (const type of ['IHDR', 'IDAT', 'IEND']) {
        assert.ok(before.find((c) => c.type === type).data.equals(after.find((c) => c.type === type).data), type);
    }
    // The pixels are really there: the IDAT inflates to the rows the source had.
    assert.equal(zlib.inflateSync(after[2].data).length, (1 + 40 * 3) * 25);
});

test('setPngDpi replaces an existing resolution instead of adding a second one, and is repeatable', () => {
    const withOld = makePng(10, 10, [['pHYs', Buffer.from([0, 0, 11, 19, 0, 0, 11, 19, 1])]]); // 72 dpi
    assert.equal(readPngInfo(withOld).dpi, 72);
    const once = setPngDpi(withOld, 600);
    const twice = setPngDpi(once, 600);
    assert.equal(chunkList(once).filter((c) => c.type === 'pHYs').length, 1);
    assert.deepEqual(Array.from(once), Array.from(twice));
    assert.equal(readPngInfo(once).dpi, 600);
    assert.deepEqual(chunkList(once).map((c) => c.type), ['IHDR', 'pHYs', 'IDAT', 'IEND']);
});

test('the printed size follows from the pixels and the dpi: the 77.9 x 31.2 mm design at 600 dpi is 1840 x 737 px', () => {
    const width = Math.round((77.9 / 25.4) * 600);
    const height = Math.round((31.2 / 25.4) * 600);
    const info = readPngInfo(setPngDpi(makePng(width, height), 600));
    assert.deepEqual([info.width, info.height], [1840, 737]);
    assert.ok(Math.abs((info.width / info.dpi) * 25.4 - 77.9) < 0.1);
});
