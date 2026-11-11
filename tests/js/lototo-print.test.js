// Run with: node --test tests/js/lototo-print.test.js
import assert from 'node:assert/strict';
import test from 'node:test';
import zlib from 'node:zlib';
import QRCode from 'qrcode';
import { DESIGNS, designsFor, qrCode, qrLevel, shortName } from '../../resources/js/lototo-print-designs.js';
import { buildPdf, toPoints } from '../../resources/js/lototo-print-pdf.js';

const url = 'http://localhost/safety/lototo/form?board=1&employee_id=127&lototo_number=03&signature=6f1c0a9b2e7d4c3f8a5b6e1d2c9f0a7b4e3d2c1b0a9f8e7d6c5b4a3f2e1d0c9b';
const budi = { kind: 'technician', name: 'BUDI', number: '07', photo: 'data:image/png;base64,AAAA', qr: url };
const andi = { kind: 'technician', name: '<A&B> ANDI', number: '12', photo: null, qr: `${url}&x=1` };

test('five designs with the printed sizes of Print Out.docx', () => {
    assert.deepEqual(DESIGNS.map((d) => [d.id, d.width, d.height]), [[1, 32.2, 43.5], [2, 77.9, 31.2], [3, 31.2, 16.7], [4, 40.6, 110.6], [5, 160.9, 140.7]]);
    DESIGNS.forEach((design) => {
        const svg = design.render(budi);
        assert.match(svg, new RegExp(`^<svg [^>]*width="${design.width}mm" height="${design.height}mm" viewBox="0 0 ${design.width} ${design.height}"`));
    });
});

test('employees get all five designs; Spare Locks and Tools only the designs with a QR code (2, 4, 5)', () => {
    assert.deepEqual(designsFor('technician').map((d) => d.id), [1, 2, 3, 4, 5]);
    assert.deepEqual(designsFor('supervisor').map((d) => d.id), [1, 2, 3, 4, 5]);
    assert.deepEqual(designsFor('spare').map((d) => d.id), [2, 4, 5]);
    assert.deepEqual(designsFor('tool').map((d) => d.id), [2, 4, 5]);
});

test('a template takes the record it is given, and only that record', () => {
    const forBudi = DESIGNS.map((d) => d.render(budi));
    const forAndi = DESIGNS.map((d) => d.render(andi));
    forBudi.forEach((svg) => {
        assert.match(svg, />BUDI</); assert.match(svg, />07</); assert.doesNotMatch(svg, /ANDI|>12</);
    });
    forAndi.forEach((svg) => {
        // Names are escaped, never markup.
        assert.match(svg, />&lt;A&amp;B&gt;</); assert.doesNotMatch(svg, /BUDI|<A&B>/);
        // No photo: a silhouette placeholder in the photo box, never another employee's picture.
        assert.doesNotMatch(svg, /<image /);
        assert.match(svg, /data-photo-placeholder/);
    });
    assert.equal(forBudi.filter((svg) => svg.includes('href="data:image/png;base64,AAAA"')).length, 5);
    // Budi's photo replaces the silhouette; a tool or spare without a picture keeps its box empty (no silhouette).
    forBudi.forEach((svg) => assert.doesNotMatch(svg, /data-photo-placeholder/));
    DESIGNS.filter((d) => d.kinds.includes('tool')).forEach((d) => {
        assert.doesNotMatch(d.render({ kind: 'tool', name: 'KUNCI', number: 'TL-01', photo: null, qr: url }), /data-photo-placeholder/);
        assert.doesNotMatch(d.render({ kind: 'spare', name: 'SPARE', number: 'SP-01', photo: null, qr: url }), /data-photo-placeholder/);
    });
    // The QR code is the record's own: Budi's and Andi's codes differ.
    assert.notEqual(DESIGNS[1].render(budi).match(/<path d="([^"]+)"/)[1], DESIGNS[1].render({ ...budi, qr: andi.qr }).match(/<path d="([^"]+)"/)[1]);
});

test('the QR code is drawn module by module from the record URL with error correction H, number in the centre', () => {
    const svg = qrCode(url, '07', { x: 0, y: 0, size: 23.88 });
    const { modules } = QRCode.create(url, { errorCorrectionLevel: 'H' });
    const unit = 23.88 / modules.size;
    // Every module outside the centre label that is dark in the code is drawn (spot check of the finder pattern).
    assert.match(svg, new RegExp(`M0\\.0000 0\\.0000h${(7 * unit).toFixed(4)}`));
    assert.match(svg, /<text [^>]*>07<\/text>$/);
    // H (not a lighter level) decides the module size.
    assert.ok(QRCode.create(url, { errorCorrectionLevel: 'M' }).modules.size < modules.size);
});

test('the PDF has one page per design at its exact printed size, the picture unchanged', async () => {
    const page = (widthMm, heightMm, w, h, value) => ({ widthMm, heightMm, pixelWidth: w, pixelHeight: h, rgb: new Uint8Array(w * h * 3).fill(value) });
    const pdf = await buildPdf([page(32.2, 43.5, 4, 5, 7), page(160.9, 140.7, 3, 2, 200)], { title: 'Test' });
    const text = Buffer.from(pdf).toString('latin1');
    assert.match(text, /^%PDF-1\.4/);
    assert.match(text, /\/Count 2/);
    const boxes = [...text.matchAll(/\/MediaBox \[0 0 ([\d.]+) ([\d.]+)\]/g)].map((m) => [Number(m[1]), Number(m[2])]);
    assert.deepEqual(boxes, [[toPoints(32.2), toPoints(43.5)], [toPoints(160.9), toPoints(140.7)]]);
    assert.ok(Math.abs(boxes[0][0] - 91.2756) < 0.001 && Math.abs(boxes[1][1] - 398.8346) < 0.001);
    // The picture covers the whole page, no margin or scaling.
    assert.match(text, /q 91\.2756 0 0 123\.3071 0 0 cm \/Im0 Do Q/);
    // Cross-reference offsets point at their objects.
    const xref = Number(text.match(/startxref\n(\d+)/)[1]);
    const entries = text.slice(xref).match(/(\d{10}) 00000 n/g).map((e) => Number(e.slice(0, 10)));
    entries.forEach((offset, index) => assert.ok(text.startsWith(`${index + 1} 0 obj`, offset), `object ${index + 1}`));
    // Image data inflates back to the exact pixels.
    const streams = [...text.matchAll(/\/Subtype \/Image [^>]*\/Length (\d+) >>\nstream\n/g)];
    const first = streams[0];
    const start = first.index + first[0].length;
    const data = zlib.inflateSync(pdf.slice(start, start + Number(first[1])));
    assert.deepEqual([...data], [...new Uint8Array(4 * 5 * 3).fill(7)]);
});

test('designs show the short name: first word, or two words when the first is an initial', () => {
    const cases = [
        ['Budi Santoso', 'Budi'], ['Ahmad Fauzi Rahman', 'Ahmad'], ['A Santoso', 'A Santoso'], ['M Rizky Pratama', 'M Rizky'],
        ['Siti Nurhaliza', 'Siti'], ['  Budi   Santoso  ', 'Budi'], ['M.  Rizky Pratama', 'M. Rizky'], ['Siti', 'Siti'], ['A', 'A'],
        ['', ''], ['   ', ''], [null, ''], [undefined, ''], ['BUDI SANTOSO', 'BUDI'], ['M RIZKY PRATAMA', 'M RIZKY'],
    ];
    cases.forEach(([name, expected]) => assert.equal(shortName(name), expected, JSON.stringify(name)));

    // Every design that prints an employee name prints the short one; the full name never appears.
    const rizky = { kind: 'technician', name: 'M RIZKY PRATAMA', number: '09', photo: null, qr: url };
    DESIGNS.forEach((d) => {
        const svg = d.render(rizky);
        assert.match(svg, />M RIZKY</, `Desain ${d.id}`);
        assert.doesNotMatch(svg, /PRATAMA/, `Desain ${d.id}`);
    });
    // Spare locks and tools keep their own label.
    assert.match(DESIGNS[1].render({ kind: 'tool', name: 'KUNCI TORSI BESAR', number: 'TL-01', photo: null, qr: url }), />KUNCI TORSI BESAR</);
});

test('Desain 4 of an employee opens the activity page; the other designs and spare/tool records keep their QR', () => {
    const activity = 'http://localhost/safety/lototo/127?board=1';
    const record = { ...budi, activity };
    const pathOf = (svg) => [...svg.matchAll(/<path d="([^"]+)"/g)].map((m) => m[1]).join('|');
    const d4 = DESIGNS.find((d) => d.id === 4);
    const qrPart = (u) => qrCode(u, '07', { x: 4.91, y: 73.0, size: 32.31 });
    assert.ok(d4.render(record).includes(qrPart(activity)));
    assert.ok(!d4.render(record).includes(qrPart(url)));
    // Designs 2 and 5 still carry the form QR, unchanged by the activity URL.
    [2, 5].forEach((id) => {
        const d = DESIGNS.find((x) => x.id === id);
        assert.equal(pathOf(d.render(record)), pathOf(d.render(budi)), `Desain ${id}`);
    });
    // A spare lock or tool without an activity page keeps its own QR on Desain 4.
    const spare = { kind: 'spare', name: 'SPARE', number: 'SP-01', photo: null, qr: url };
    assert.ok(d4.render(spare).includes(qrCode(url, 'SP-01', { x: 4.91, y: 73.0, size: 32.31 })));
});

test('Desain 2 and 5 pick the lightest error correction that still recovers the number cut-out; Desain 4 keeps H', () => {
    const host = 'https://maintenance.ptbimtntc-academy.com';
    const signature = 'a'.repeat(64);
    const employee = `${host}/safety/lototo/form?board=12&employee_id=1234&lototo_number=66&signature=${signature}`;
    const supervisor = `${host}/safety/lototo/form?board=12&employee_id=1301&lototo_number=101&signature=${signature}`;
    const tool = `${host}/safety/lototo/tools/15/form?board=12`;
    // Long signed URLs: M (larger modules); a short tool URL, where the cut-out is a bigger share: Q.
    assert.deepEqual([qrLevel(employee, '66', 23.88, true), qrLevel(supervisor, '101', 23.88, true), qrLevel(tool, 'TL-15', 23.88, true)], ['M', 'M', 'Q']);
    // Without adaptive the level stays H.
    assert.equal(qrLevel(employee, '66', 23.88), 'H');
    // So the employee QR of Desain 2 has larger modules than before: 53 instead of 73 per side.
    const sizeOf = (level) => QRCode.create(employee, { errorCorrectionLevel: level }).modules.size;
    assert.deepEqual([sizeOf('M'), sizeOf('H')], [53, 73]);
    const record = { kind: 'technician', name: 'BUDI', number: '66', photo: null, qr: employee, activity: `${host}/safety/lototo/1234?board=12` };
    const d2 = DESIGNS.find((d) => d.id === 2).render(record);
    const d5 = DESIGNS.find((d) => d.id === 5).render(record);
    assert.ok(d2.includes(qrCode(employee, '66', { x: 51.48, y: 3.9, size: 23.88, adaptive: true })));
    assert.ok(d5.includes(qrCode(employee, '66', { x: 51.48, y: 3.9, size: 23.88, adaptive: true })));
    // Desain 4 is unchanged: its activity QR with H.
    assert.ok(DESIGNS.find((d) => d.id === 4).render(record).includes(qrCode(record.activity, '66', { x: 4.91, y: 73.0, size: 32.31 })));
    // A record without a URL never becomes an empty QR.
    assert.throws(() => qrCode('', '66', { x: 0, y: 0, size: 23.88, adaptive: true }), /needs a URL/);
});
