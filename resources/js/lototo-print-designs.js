import QRCode from 'qrcode';

/**
 * LOTOTO print designs ("Print Out.docx", Desain 1-5) as SVG templates in millimetres. Each template is filled with
 * one QR record of the board: { kind: technician | supervisor | spare | tool, name, number, photo (data URL or null),
 * qr (the record's own QR URL) }. The same SVG is the preview and, rendered at 600 dpi, the printed page, so both
 * always match. Sizes and positions were measured on the images in the DOCX (150 dpi, object sizes from the
 * document), see docs in the report; Arial Bold, pure red #FF0000 and black as in the reference.
 */

const RED = '#FF0000';
const BLACK = '#000000';
const FONT = 'Arial, Helvetica, sans-serif';
// Arial Bold: capital and figure height as a share of the font size.
const CAP = 0.716;

/**
 * The name printed for an employee: the first word of the name, or the first two words when the first is only an
 * initial (one letter, with or without a dot: "M Rizky Pratama" -> "M Rizky"). Extra spaces are ignored; an empty
 * name stays empty. The stored name is not changed.
 */
export function shortName(name) {
    const words = String(name ?? '').trim().split(/\s+/).filter(Boolean);
    if (words.length === 0) return '';

    return /^\p{L}\.?$/u.test(words[0]) && words.length > 1 ? `${words[0]} ${words[1]}` : words[0];
}

/** Name shown on a design: employees by shortName(); spare locks and tools keep their own label. */
const nameOf = (r) => (['technician', 'supervisor'].includes(r.kind) ? shortName(r.name) : r.name);

const escapeXml = (text) => String(text ?? '').replace(/[&<>"']/g, (char) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&apos;' }[char]));

let measureContext = null;

/** Text width in mm for a bold font of $size mm (browser canvas; a fixed estimate where there is no canvas). */
export function textWidth(text, size, font = FONT) {
    if (typeof document !== 'undefined') {
        measureContext ??= document.createElement('canvas').getContext('2d');
        measureContext.font = `bold 100px ${font}`;
        return (measureContext.measureText(String(text)).width / 100) * size;
    }

    return String(text).length * 0.6 * size;
}

/**
 * One line of bold text, centred on $x with its baseline on $y and the capital height of the reference. A text
 * wider than $maxWidth gets a smaller size (same centre and baseline), so a long name never leaves its place.
 */
export function text(value, { x, y, cap, color = BLACK, maxWidth = Infinity, anchor = 'middle', font = FONT }) {
    let size = cap / CAP;
    const width = textWidth(value, size, font);
    if (width > maxWidth) size *= maxWidth / width;

    return `<text x="${x}" y="${y}" font-family="${escapeXml(font)}" font-weight="700" font-size="${size.toFixed(3)}" fill="${color}" text-anchor="${anchor}">${escapeXml(value)}</text>`;
}

/** Ink extent (actual glyphs, left and right of the start point) in mm of a bold text of $size mm; null without a canvas. */
function inkExtent(value, size) {
    if (typeof document === 'undefined') return null;
    measureContext ??= document.createElement('canvas').getContext('2d');
    measureContext.font = `bold 100px ${FONT}`;
    const metrics = measureContext.measureText(String(value));

    return { left: (metrics.actualBoundingBoxLeft / 100) * size, right: (metrics.actualBoundingBoxRight / 100) * size };
}

/**
 * Fixed wording of a design (DANGER, DO NOT OPERATE, ...): Arial Bold at the reference's capital height, its ink
 * (not its advance width) scaled across to the width measured on the reference and centred on $cx.
 */
export function fixedText(value, { cx, baseline, cap, width, color = BLACK }) {
    const size = cap / CAP;
    const ink = inkExtent(value, size);
    const stretch = ink ? width / (ink.left + ink.right) : 1;
    const start = ink ? (ink.left - ink.right) / 2 : 0;

    return `<text transform="translate(${cx} ${baseline}) scale(${stretch.toFixed(4)} 1)" x="${start.toFixed(4)}" font-family="${FONT}" font-weight="700" font-size="${size.toFixed(3)}" fill="${color}"${ink ? '' : ' text-anchor="middle"'}>${escapeXml(value)}</text>`;
}

/**
 * Red diagonal stripes on white between $y0 and $y1 (clipped by the caller): red bands $red mm wide (measured
 * across), one every $period mm, edges running dx/dy = $slope, one red band starting at $refX on the line $refY.
 */
export function stripes({ x0, x1, y0, y1, slope, period, red, refX, refY }) {
    const at = (y) => refX + slope * (y - refY);
    const first = Math.floor((x0 - Math.max(at(y0), at(y1)) - red) / period) - 1;
    const last = Math.ceil((x1 - Math.min(at(y0), at(y1))) / period) + 1;
    let path = '';
    for (let i = first; i <= last; i++) {
        const a = at(y0) + i * period;
        const b = at(y1) + i * period;
        path += `M${a.toFixed(3)} ${y0}H${(a + red).toFixed(3)}L${(b + red).toFixed(3)} ${y1}H${b.toFixed(3)}Z`;
    }

    return `<rect x="${x0}" y="${y0}" width="${x1 - x0}" height="${y1 - y0}" fill="#FFFFFF"/><path d="${path}" fill="${RED}"/>`;
}

/** A pill (fully round ends): white ring of $ring mm around a red body, the word inside in white. */
function dangerPill({ x, y, width, height, ring, word }) {
    return `<rect x="${x}" y="${y}" width="${width}" height="${height}" rx="${height / 2}" fill="#FFFFFF"/>`
        + `<rect x="${x + ring}" y="${y + ring}" width="${width - 2 * ring}" height="${height - 2 * ring}" rx="${(height - 2 * ring) / 2}" fill="${RED}"/>`
        + fixedText('DANGER', { cx: x + width / 2 + word.dx, baseline: y + word.top + word.cap, cap: word.cap, width: word.width, color: '#FFFFFF' });
}

/**
 * A photo filling its box like the reference (cover, centred). Without a photo (none saved, file missing or not
 * loaded) an employee's box shows a grey person silhouette of the same size and place; other records keep it empty.
 */
export function photo(src, { x, y, width, height }, placeholder = false) {
    if (!src) return placeholder ? silhouette({ x, y, width, height }) : '';

    return `<image href="${escapeXml(src)}" x="${x}" y="${y}" width="${width}" height="${height}" preserveAspectRatio="xMidYMid slice"/>`;
}

/** Grey person silhouette filling the photo box (head and shoulders, bottom aligned), drawn as vectors. */
export function silhouette({ x, y, width, height }) {
    return `<svg x="${x}" y="${y}" width="${width}" height="${height}" viewBox="0 0 80 100" preserveAspectRatio="xMidYMax slice" data-photo-placeholder="">`
        + '<rect x="-200" y="-200" width="480" height="500" fill="#E5E7EB"/>'
        + '<circle cx="40" cy="38" r="17" fill="#9CA3AF"/>'
        + '<path d="M6 100C6 75 21 61 40 61S74 75 74 100Z" fill="#9CA3AF"/>'
        + '</svg>';
}

/** Border drawn inside the edge, as the black frame of the reference images. */
export function frame(width, height, stroke) {
    return `<rect x="${stroke / 2}" y="${stroke / 2}" width="${width - stroke}" height="${height - stroke}" fill="none" stroke="${BLACK}" stroke-width="${stroke}"/>`;
}

/**
 * The record's QR code as vector modules in a $size mm square at x/y (error correction H), with the number in a
 * cleared centre as in the reference: the number in bold black, 12.1% of the code high and 13.1% wide per figure (in
 * the reference it is Arial Bold stretched about 1.46 times across), at most 36% of the code wide. Only the modules
 * under the number and one module around it are removed, so error correction H keeps the code readable.
 */
export function qrCode(url, number, { x, y, size }) {
    const { modules } = QRCode.create(url, { errorCorrectionLevel: 'H' });
    const count = modules.size;
    const unit = size / count;
    const value = String(number ?? '');
    const figure = size * 0.121;
    const labelWidth = Math.min(value.length * size * 0.131, size * 0.36);
    const centre = count / 2;
    const c0 = Math.floor(centre - labelWidth / unit / 2) - 1;
    const c1 = Math.ceil(centre + labelWidth / unit / 2) + 1;
    const r0 = Math.floor(centre - figure / unit / 2) - 1;
    const r1 = Math.ceil(centre + figure / unit / 2) + 1;
    let path = '';
    for (let row = 0; row < count; row++) {
        let start = null;
        for (let col = 0; col <= count; col++) {
            const cleared = row >= r0 && row < r1 && col >= c0 && col < c1;
            const dark = col < count && !cleared && modules.get(row, col);
            if (dark && start === null) start = col;
            if (!dark && start !== null) {
                path += `M${(x + start * unit).toFixed(4)} ${(y + row * unit).toFixed(4)}h${((col - start) * unit).toFixed(4)}v${unit.toFixed(4)}h${(-(col - start) * unit).toFixed(4)}z`;
                start = null;
            }
        }
    }
    const fontSize = figure / CAP;
    const stretch = labelWidth / Math.max(0.001, textWidth(value, fontSize));
    const label = `<text transform="translate(${(x + size / 2).toFixed(4)} ${(y + size / 2 + figure / 2).toFixed(4)}) scale(${stretch.toFixed(4)} 1)" font-family="${FONT}" font-weight="700" font-size="${fontSize.toFixed(3)}" fill="${BLACK}" text-anchor="middle">${escapeXml(value)}</text>`;

    return `<path d="${path}" fill="${BLACK}" shape-rendering="crispEdges"/>${label}`;
}

const svg = (width, height, body) => `<svg xmlns="http://www.w3.org/2000/svg" width="${width}mm" height="${height}mm" viewBox="0 0 ${width} ${height}"><rect width="${width}" height="${height}" fill="#FFFFFF"/>${body}</svg>`;

/** The inside of Desain 2 (also the identity box on the front of Desain 5). */
const design2Body = (r) => [
    frame(77.9, 31.2, 0.51),
    text(nameOf(r), { x: 15.41, y: 11.19, cap: 4.58, maxWidth: 28.6 }),
    text(r.number, { x: 15.41, y: 26.45, cap: 9.33, color: RED, maxWidth: 28.6 }),
    photo(r.photo, { x: 30.99, y: 5.09, width: 17.44, height: 22.04 }, EMPLOYEES.includes(r.kind)),
    qrCode(r.qr, r.number, { x: 51.48, y: 3.9, size: 23.88 }),
].join('');

/**
 * Desain 5: the two sides of the danger tag next to each other (image5.png, 950 x 831 px). Each side has cut top
 * corners and a black outline; the stripes of each band keep the angle, period and phase of the reference.
 */
function design5(r) {
    const e = 0.255;
    const tag = (left, right, top0, top1) => `M${top0} ${e}H${top1}L${right} 21.6V${140.7 - e}H${left}V21.6Z`;
    const front = tag(e, 80.45, 18.8, 62.67);
    const back = tag(80.45, 160.9 - e, 99.08, 142.95);
    const clip = (id, d, body) => `<clipPath id="${id}"><path d="${d}"/></clipPath><g clip-path="url(#${id})">${body}</g>`;

    const frontBody = [
        `<rect x="0" y="0" width="80.45" height="140.7" fill="#FFFFFF"/>`,
        stripes({ x0: 0, x1: 80.45, y0: 0, y1: 30.65, slope: -1.117, period: 26.93, red: 11.69, refX: 16.26, refY: 3.39 }),
        `<rect x="0" y="30.65" width="80.45" height="25.95" fill="${BLACK}"/>`,
        stripes({ x0: 0, x1: 80.45, y0: 56.6, y1: 63.3, slope: -1.115, period: 26.59, red: 11.69, refX: 9.99, refY: 57.59 }),
        stripes({ x0: 0, x1: 80.45, y0: 132.9, y1: 140.7, slope: -1.667, period: 27.35, red: 11.86, refX: 7.96, refY: 133.8 }),
        dangerPill({ x: 8.47, y: 34.72, width: 64.02, height: 16.94, ring: 2.03, word: { dx: 0.19, top: 4.58, cap: 8.21, width: 48.06 } }),
        fixedText('EQUIPMENT', { cx: 40.73, baseline: 73.17, cap: 6.1, width: 56.06 }),
        fixedText('LOCKED OUT', { cx: 40.73, baseline: 85.3, cap: 6.1, width: 61.14 }),
        fixedText('BY', { cx: 40.81, baseline: 97.22, cap: 6.1, width: 12.53 }),
        `<g transform="translate(1.35 99.76)">${design2Body(r)}</g>`,
    ].join('');
    const backBody = [
        `<rect x="80.45" y="0" width="80.45" height="140.7" fill="#FFFFFF"/>`,
        stripes({ x0: 80.45, x1: 160.9, y0: 0, y1: 50.14, slope: -0.875, period: 26.34, red: 11.52, refX: 123.98, refY: 1.69 }),
        // SAFE-D-LOCK: a white plate with a red rounded frame and red letters, on the stripes.
        `<rect x="89.6" y="25.6" width="61.48" height="17.35" rx="4" fill="#FFFFFF"/>`,
        `<rect x="91.88" y="27.56" width="56.92" height="13.48" rx="3" fill="#FFFFFF" stroke="${RED}" stroke-width="1.52"/>`,
        fixedText('SAFE-D-LOCK', { cx: 120.34, baseline: 36.75, cap: 5.08, width: 48.27, color: RED }),
        `<rect x="80.45" y="50.14" width="80.45" height="25.86" fill="${BLACK}"/>`,
        dangerPill({ x: 88.58, y: 54.37, width: 64.19, height: 16.93, ring: 2.03, word: { dx: 0.19, top: 4.23, cap: 8.21, width: 48.06 } }),
        stripes({ x0: 80.45, x1: 160.9, y0: 76.0, y1: 79.4, slope: -0.853, period: 26.42, red: 11.69, refX: 85.36, refY: 77.06 }),
        fixedText('DO NOT', { cx: 121.02, baseline: 95.55, cap: 9.3, width: 48.27 }),
        fixedText('OPERATE', { cx: 120.5, baseline: 110.3, cap: 9.3, width: 60.13 }),
        fixedText('DO NOT REMOVE THIS TAG', { cx: 120.59, baseline: 122.72, cap: 3.48, width: 65.72 }),
        fixedText('SEE REVERSE SIDE', { cx: 120.5, baseline: 128.55, cap: 3.56, width: 47.25 }),
        stripes({ x0: 80.45, x1: 160.9, y0: 132.9, y1: 140.7, slope: -1.667, period: 27.35, red: 11.86, refX: 88.58, refY: 133.63 }),
    ].join('');

    // The page itself stays white outside the tag outline, as the transparent corners of the reference.
    return svg(160.9, 140.7, [
        clip('lototo-d5-front', front, frontBody),
        clip('lototo-d5-back', back, backBody),
        `<path d="${front}" fill="none" stroke="${BLACK}" stroke-width="0.51" stroke-linejoin="miter"/>`,
        `<path d="${back}" fill="none" stroke="${BLACK}" stroke-width="0.51" stroke-linejoin="miter"/>`,
    ].join(''));
}

const EMPLOYEES = ['technician', 'supervisor'];
const ALL = ['technician', 'supervisor', 'spare', 'tool'];

/** The five designs. width/height: printed size in mm; kinds: records it is offered for. */
export const DESIGNS = [
    {
        id: 1,
        name: 'Desain 1',
        width: 32.2,
        height: 43.5,
        kinds: EMPLOYEES,
        // Number on top, photo, name below, black frame (measured on image1.png, 190 x 257 px).
        render: (r) => svg(32.2, 43.5, [
            frame(32.2, 43.5, 0.51),
            text(r.number, { x: 14.83, y: 7.96, cap: 4.57, color: RED, maxWidth: 28.6 }),
            photo(r.photo, { x: 6.1, y: 11.34, width: 18.64, height: 23.53 }, EMPLOYEES.includes(r.kind)),
            text(nameOf(r), { x: 15.93, y: 40.96, cap: 2.54, color: RED, maxWidth: 29.6 }),
        ].join('')),
    },
    {
        id: 2,
        name: 'Desain 2',
        width: 77.9,
        height: 31.2,
        kinds: ALL,
        // Name and number on the left, photo, QR with the number (image2.png, 460 x 184 px).
        render: (r) => svg(77.9, 31.2, design2Body(r)),
    },
    {
        id: 3,
        name: 'Desain 3',
        width: 31.2,
        height: 16.7,
        kinds: EMPLOYEES,
        // Photo, name and number; the framed label is 16.18 mm high on the 16.7 mm object (image3.png, 180 x 96 px,
        // its last 3 px rows are transparent).
        render: (r) => svg(31.2, 16.7, [
            `<rect x="0.26" y="0.26" width="30.68" height="15.66" fill="none" stroke="${BLACK}" stroke-width="0.52"/>`,
            photo(r.photo, { x: 1.39, y: 1.22, width: 10.92, height: 13.39 }, EMPLOYEES.includes(r.kind)),
            text(nameOf(r), { x: 21.93, y: 6.26, cap: 2.44, maxWidth: 17.4 }),
            text(r.number, { x: 21.84, y: 12.35, cap: 4.17, color: RED, maxWidth: 17.4 }),
        ].join('')),
    },
    {
        id: 4,
        name: 'Desain 4',
        width: 40.6,
        height: 110.6,
        kinds: ALL,
        // Photo, number, name and "M/C :" above a dividing line, the QR with the number below (image4.png, 240 x 653 px).
        render: (r) => svg(40.6, 110.6, [
            frame(40.6, 110.6, 0.51),
            `<rect x="0" y="63.85" width="40.6" height="0.51" fill="${BLACK}"/>`,
            photo(r.photo, { x: 6.94, y: 8.47, width: 26.39, height: 33.03 }, EMPLOYEES.includes(r.kind)),
            text(r.number, { x: 20.2, y: 50.13, cap: 4.06, color: RED, maxWidth: 37 }),
            text(nameOf(r), { x: 20.13, y: 56.06, cap: 3.56, maxWidth: 37 }),
            text('M/C :', { x: 20.13, y: 61.65, cap: 3.56 }),
            qrCode(r.activity ?? r.qr, r.number, { x: 4.91, y: 73.0, size: 32.31 }),
        ].join('')),
    },
    {
        id: 5,
        name: 'Desain 5',
        width: 160.9,
        height: 140.7,
        kinds: ALL,
        render: design5,
    },
];

export function designsFor(kind) {
    return DESIGNS.filter((design) => design.kinds.includes(kind));
}

export { ALL as KINDS };
