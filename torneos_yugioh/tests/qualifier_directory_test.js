const fs = require('fs');
const path = require('path');
const assert = require('assert');

const root = path.resolve(__dirname, '..');
const php = fs.readFileSync(path.join(root, 'index.php'), 'utf8');
const js = fs.readFileSync(path.join(root, 'assets', 'app.js'), 'utf8');

assert(php.includes('$showQualifierDetail = $requestedQualifierCompetition !== null;'), 'El detalle solo debe abrirse con un clasificatorio solicitado.');
assert(php.includes('data-qualifier-directory'), 'Falta la pantalla inicial de clasificatorios.');
assert(php.includes('data-qualifier-detail'), 'Falta separar el detalle del directorio.');
assert(php.includes('Tabla de clasificatorios'), 'Falta la tabla exclusiva de clasificatorios.');
assert(php.includes('data-qualifier-directory-search'), 'Falta el buscador del directorio.');
assert(php.includes('data-qualifier-directory-status'), 'Falta el filtro por estado.');
assert(php.includes('data-qualifier-directory-format'), 'Falta el filtro por formato.');
assert(php.includes('data-qualifier-directory-open'), 'Falta la accion para abrir el evento seleccionado.');
assert(php.includes('data-qualifier-directory-back'), 'Falta la accion para volver al directorio.');

assert(js.includes('function applyQualifierDirectoryFilters()'), 'Los filtros del directorio no estan conectados.');
assert(js.includes('function sortQualifierDirectory(key, toggle = true)'), 'La tabla no tiene orden interactivo.');
assert(js.includes("directoryUrl.searchParams.delete('event_id')"), 'El menu debe regresar a la pantalla inicial.');
assert(js.includes("url.searchParams.set('event_id', button.dataset.qualifierDirectoryOpen || '')"), 'Ver debe abrir exactamente el clasificatorio elegido.');

console.log('OK qualifier directory');
