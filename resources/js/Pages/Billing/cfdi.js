// ============================================================================
// Utilidades compartidas para facturas CFDI (XML).
// Usadas por el lector de XML y por la plantilla imprimible.
// ============================================================================

/**
 * Parsea el contenido de un XML de factura (CFDI 3.3 / 4.0) y devuelve
 * un objeto con la información estructurada.
 *
 * @param {string} text - Contenido del archivo XML.
 * @returns {object}
 */
export function parseCfdiXml(text) {
    const parser = new DOMParser();
    const doc = parser.parseFromString(text, 'application/xml');

    // Si el XML no es válido, DOMParser genera un elemento <parsererror>
    if (doc.querySelector('parsererror')) {
        throw new Error('El archivo no es un XML válido.');
    }

    // Indexa todos los elementos por su nombre local (sin prefijo),
    // así funciona tanto para CFDI 3.3 como 4.0.
    const map = {};
    for (const el of doc.getElementsByTagName('*')) {
        const name = el.localName;
        if (!map[name]) map[name] = [];
        map[name].push(el);
    }

    const comprobante = (map['Comprobante'] || [])[0];
    if (!comprobante) {
        throw new Error('No se encontró un comprobante CFDI en el archivo. Verifica que sea una factura electrónica.');
    }

    const attr = (el, name, fallback = null) => el ? (el.getAttribute(name) ?? fallback) : fallback;

    const emisor = (map['Emisor'] || [])[0];
    const receptor = (map['Receptor'] || [])[0];

    // Conceptos
    const conceptos = (map['Concepto'] || []).map(el => ({
        claveProdServ: attr(el, 'ClaveProdServ'),
        noIdentificacion: attr(el, 'NoIdentificacion'),
        cantidad: attr(el, 'Cantidad'),
        claveUnidad: attr(el, 'ClaveUnidad'),
        unidad: attr(el, 'Unidad'),
        descripcion: attr(el, 'Descripcion'),
        valorUnitario: attr(el, 'ValorUnitario'),
        importe: attr(el, 'Importe'),
    }));

    // Impuestos globales (el <Impuestos> hijo directo del Comprobante)
    const globalImpuestos = (map['Impuestos'] || []).find(imp => imp.parentElement?.localName === 'Comprobante');
    const traslados = [];
    const retenciones = [];

    if (globalImpuestos) {
        for (const child of globalImpuestos.children) {
            if (child.localName === 'Traslados') {
                for (const t of child.children) {
                    traslados.push({
                        impuesto: attr(t, 'Impuesto'),
                        tipoFactor: attr(t, 'TipoFactor'),
                        tasaOCuota: attr(t, 'TasaOCuota'),
                        base: attr(t, 'Base'),
                        importe: attr(t, 'Importe'),
                    });
                }
            }
            if (child.localName === 'Retenciones') {
                for (const r of child.children) {
                    retenciones.push({
                        impuesto: attr(r, 'Impuesto'),
                        importe: attr(r, 'Importe'),
                    });
                }
            }
        }
    }

    // Timbre fiscal digital (complemento)
    const timbreEl = (map['TimbreFiscalDigital'] || [])[0];

    return {
        version: attr(comprobante, 'Version'),
        serie: attr(comprobante, 'Serie'),
        folio: attr(comprobante, 'Folio', '—'),
        fecha: attr(comprobante, 'Fecha'),
        tipo: attr(comprobante, 'TipoDeComprobante'),
        formaPago: attr(comprobante, 'FormaPago'),
        metodoPago: attr(comprobante, 'MetodoPago'),
        moneda: attr(comprobante, 'Moneda', 'MXN'),
        tipoCambio: attr(comprobante, 'TipoCambio'),
        subtotal: attr(comprobante, 'SubTotal'),
        descuento: attr(comprobante, 'Descuento'),
        total: attr(comprobante, 'Total'),
        lugarExpedicion: attr(comprobante, 'LugarExpedicion'),
        noCertificado: attr(comprobante, 'NoCertificado'),
        condicionesDePago: attr(comprobante, 'CondicionesDePago'),
        emisor: {
            rfc: attr(emisor, 'Rfc'),
            nombre: attr(emisor, 'Nombre'),
            regimenFiscal: attr(emisor, 'RegimenFiscal'),
        },
        receptor: {
            rfc: attr(receptor, 'Rfc'),
            nombre: attr(receptor, 'Nombre'),
            regimenFiscal: attr(receptor, 'RegimenFiscalReceptor') || attr(receptor, 'RegimenFiscal'),
            usoCFDI: attr(receptor, 'UsoCFDI'),
            domicilioFiscal: attr(receptor, 'DomicilioFiscalReceptor') || attr(receptor, 'DomicilioFiscal'),
            numRegIdTrib: attr(receptor, 'NumRegIdTrib'),
            residenciaFiscal: attr(receptor, 'ResidenciaFiscal'),
        },
        conceptos,
        impuestos: {
            totalTrasladados: attr(globalImpuestos, 'TotalImpuestosTrasladados'),
            totalRetenidos: attr(globalImpuestos, 'TotalImpuestosRetenidos'),
            traslados,
            retenciones,
        },
        timbre: timbreEl ? {
            uuid: attr(timbreEl, 'UUID'),
            fechaTimbrado: attr(timbreEl, 'FechaTimbrado'),
            rfcProvCertif: attr(timbreEl, 'RfcProvCertif'),
            noCertificadoSAT: attr(timbreEl, 'NoCertificadoSAT'),
            selloCFD: attr(timbreEl, 'SelloCFD'),
            selloSAT: attr(timbreEl, 'SelloSAT'),
        } : null,
    };
}

// ============================== FORMATEO ==============================

export function formatMoney(value, currency = 'MXN') {
    const num = parseFloat(value);
    if (isNaN(num)) return '$0.00';
    const formatted = num.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    return `$${formatted} ${currency}`;
}

export function formatQuantity(value) {
    const num = parseFloat(value);
    if (isNaN(num)) return value ?? '—';
    return Number.isInteger(num) ? num.toString() : num.toFixed(2);
}

export function formatRate(value) {
    const num = parseFloat(value);
    if (isNaN(num)) return value ?? '';
    // Ej. 0.160000 -> 16%
    return `${(num * 100).toLocaleString('en-US', { maximumFractionDigits: 4 })}%`;
}

export function formatDate(value, locale = 'es-MX') {
    if (!value) return '—';
    const date = new Date(value);
    if (isNaN(date.getTime())) return value;
    return date.toLocaleString(locale, {
        month: '2-digit', day: '2-digit', year: 'numeric',
        hour: '2-digit', minute: '2-digit', hour12: true,
    });
}

/**
 * Construye la URL de verificación del SAT que codifica el QR de una factura.
 * Formato oficial: https://verificacfdi.facturaelectronica.sat.gob.mx/
 *   ?id=<UUID>&re=<RFC Emisor>&rr=<RFC Receptor>&tt=<Total>&fe=<últimos 8 del SelloCFD>
 *
 * @param {object} xmlData - Objeto devuelto por parseCfdiXml().
 * @returns {string|null} URL lista para generar el QR, o null si faltan datos.
 */
export function buildSatQrUrl(xmlData) {
    const uuid = xmlData?.timbre?.uuid;
    const re = xmlData?.emisor?.rfc;
    const rr = xmlData?.receptor?.rfc;
    const tt = xmlData?.total;
    const fe = xmlData?.timbre?.selloCFD ? xmlData.timbre.selloCFD.slice(-8) : '';

    if (!uuid || !re || !rr || !tt || !fe) return null;

    const params = new URLSearchParams({
        id: uuid,
        re,
        rr,
        tt: String(tt),
        fe,
    });

    return `https://verificacfdi.facturaelectronica.sat.gob.mx/?${params.toString()}`;
}

// ============================ MONTO CON LETRA ============================

const ONES = ['', 'ONE', 'TWO', 'THREE', 'FOUR', 'FIVE', 'SIX', 'SEVEN', 'EIGHT', 'NINE', 'TEN',
    'ELEVEN', 'TWELVE', 'THIRTEEN', 'FOURTEEN', 'FIFTEEN', 'SIXTEEN', 'SEVENTEEN', 'EIGHTEEN', 'NINETEEN'];
const TENS = ['', '', 'TWENTY', 'THIRTY', 'FORTY', 'FIFTY', 'SIXTY', 'SEVENTY', 'EIGHTY', 'NINETY'];
const SCALES = ['', 'THOUSAND', 'MILLION', 'BILLION', 'TRILLION'];

/**
 * Convierte un número entero (>= 0) a su representación en letras en inglés.
 * Ej: 1495 -> "ONE THOUSAND FOUR HUNDRED NINETY-FIVE"
 */
export function numberToWordsEn(num) {
    if (num === 0) return 'ZERO';
    if (num < 0) return 'MINUS ' + numberToWordsEn(Math.abs(num));

    let n = Math.floor(Math.abs(num));
    let words = '';
    let scale = 0;

    while (n > 0) {
        const chunk = n % 1000;
        if (chunk !== 0) {
            let chunkWords = '';
            if (chunk >= 100) {
                chunkWords += ONES[Math.floor(chunk / 100)] + ' HUNDRED';
                if (chunk % 100 !== 0) chunkWords += ' ';
            }
            const rest = chunk % 100;
            if (rest < 20) {
                chunkWords += ONES[rest];
            } else {
                chunkWords += TENS[Math.floor(rest / 10)];
                if (rest % 10 !== 0) chunkWords += '-' + ONES[rest % 10];
            }
            words = `${chunkWords} ${SCALES[scale]} ${words}`.trim();
        }
        n = Math.floor(n / 1000);
        scale++;
    }

    return words.trim();
}

/**
 * Devuelve el total en letras (con centavos y moneda), para el "Importe con letra".
 * El "AND" se coloca justo después de la escala mayor (THOUSAND, MILLION, etc.).
 * Ej: amountToWords(1495.45, 'USD')
 *   -> "ONE THOUSAND AND FOUR HUNDRED NINETY-FIVE 45/100 US DOLLARS"
 */
export function amountToWords(total, currency = 'USD') {
    const amount = Math.abs(parseFloat(total) || 0);
    const integerPart = Math.floor(amount);
    const centsPart = Math.round((amount - integerPart) * 100);

    let integerWords = numberToWordsEn(integerPart) || 'ZERO';
    const centsStr = String(centsPart).padStart(2, '0');

    const currencyName = String(currency || 'USD').toUpperCase() === 'MXN'
        ? 'MEXICAN PESOS'
        : 'US DOLLARS';

    // Inserta "AND" justo después de la escala mayor (THOUSAND, MILLION, BILLION, TRILLION)
    // y elimina el "AND" que se ponía antes de los centavos.
    integerWords = integerWords
        .replace(/^(.*?\b(?:THOUSAND|MILLION|BILLION|TRILLION)\b)(.*)$/, '$1 AND$2')
        .trim();

    return `${integerWords} ${centsStr}/100 ${currencyName}`;
}
