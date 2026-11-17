<?php
declare(strict_types=1);

// The Reports module was retired; native statistics and entity PDFs are independent.
if ((string)($_GET['api'] ?? '') === 'reports' ||
    (isset($_GET['export']) && (string)($_GET['api'] ?? '') !== 'statistics' && (string)$_GET['export'] !== 'entity_report')) {
    http_response_code(410);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    echo json_encode(['ok' => false, 'message' => 'El módulo de Reportes fue eliminado.'], JSON_UNESCAPED_UNICODE);
    exit;
}

require_once __DIR__ . '/includes/deck_builder.php';
if ((string)($_GET['api'] ?? '') === 'deck_builder' && in_array((string)($_GET['op'] ?? ''), ['search','image','detail'], true)) ygo_card_catalog_api();

require_once dirname(__DIR__) . '/store-lib.php';
if ((string)($_GET['api'] ?? '') === 'deck_data') { require_once __DIR__.'/includes/decks/data-http.php'; ygo_data_http(); }
if ((string)($_GET['api'] ?? '') === 'deck_attributes') { require_once __DIR__.'/includes/decks/attributes-http.php'; ygo_deck_attributes_http(); }
if ((string)($_GET['api'] ?? '') === 'combo_creator') { require_once __DIR__.'/includes/decks/combo-creator-http.php'; ygo_combo_http(); }
if ((string)($_GET['api'] ?? '') === 'news') { require_once __DIR__.'/includes/news_http.php'; ygo_news_http(); }
require_once __DIR__ . '/includes/catalogs.php';
require_once __DIR__.'/includes/decks/public-metadata.php';
require_once __DIR__ . '/includes/turns.php';
require_once __DIR__ . '/includes/competitive_intelligence.php';
require_once __DIR__ . '/includes/simple_pdf.php';
require_once __DIR__ . '/includes/deck_analysis_baselines.php';
require_once __DIR__ . '/includes/schedule_capacity.php';
require_once __DIR__ . '/includes/seasons.php';
require_once __DIR__ . '/includes/league_configuration.php';
require_once __DIR__ . '/includes/fixed_calendar.php';
require_once __DIR__ . '/includes/event_models.php';
require_once __DIR__ . '/includes/library.php';
require_once __DIR__ . '/includes/match_editor.php';
require_once __DIR__ . '/includes/completion_times.php';
require_once __DIR__ . '/includes/ui_labels.php';
require_once __DIR__ . '/includes/event_audit.php';
require_once __DIR__ . '/includes/event_logo.php';
require_once __DIR__ . '/includes/event_progress.php';

sx_start();
date_default_timezone_set('America/Bogota');
const YGO_AUTO_SEED_COMPETITIONS = false;
const YGO_AUTO_SEED_MATCHES = false;
const YGO_AUTO_SEED_DIVISIONS = false;

$classificationDecks = [
    [1, 'Kewl Tune', 'Dominio competitivo actual'],
    [2, 'Branded', 'Rendimiento y longevidad excepcional'],
    [3, 'Vanquish Soul K9', 'Dominante en 2025-26'],
    [4, 'Mitsurugi Pure', 'Altisima presencia reciente'],
    [5, 'Elfnote', 'Top meta actual'],
    [6, 'Chaos Ritual', 'Ritual actual de alto nivel'],
    [7, 'Dracotail', 'Excelente rendimiento reciente'],
    [8, 'DoomZ', 'Fuerte crecimiento y Top Cuts 2026'],
    [9, 'Ryzeal', 'Dominante durante 2024-25'],
    [10, 'Maliss', 'Resultados importantes 2024-26'],
    [11, 'Tearlaments', 'Uno de los mejores historiales'],
    [12, 'Snake-Eyes', 'Dominio extraordinario en 2024'],
    [13, 'Fire King', 'Gran rendimiento en era Snake-Eye'],
    [14, 'Unchained', 'Domino buena parte de 2023'],
    [15, 'Purrely', 'Excelente 2023, menor peso actual'],
    [16, 'Swordsoul', 'Historico excelente'],
    [17, 'Sky Striker', 'Gran longevidad y resurgimiento 2026'],
    [18, 'DoomZ Artmage', 'Potencial alto'],
    [19, 'White Forest Azamina', 'Buenos resultados 2024-25'],
    [20, 'Kashtira Pure', 'Ex-Tier alto, aun competitivo'],
    [21, 'Demonherrero Bystial', 'Motor potente'],
    [22, 'Cyber Dragon K9', 'K9 eleva mucho su techo'],
    [23, 'Invoked', 'Historial excelente'],
    [24, 'Mathmech', 'Muy buenos resultados'],
    [25, 'Infernoble', 'Top Cuts serios'],
    [26, 'Memento', 'Presencia reciente limitada'],
    [27, 'Exosister', 'Counter-meta solido'],
    [28, 'Salamangreat', 'Longevidad y Top Cuts'],
    [29, 'K9 Pure', 'Buen motor'],
    [30, 'Kashtira Demonherrero', 'Potencial alto'],
    [31, 'Resonator RDA Horus', 'Buen techo'],
    [32, 'Crystron', 'Variante pura'],
    [33, 'Vanquish Soul Zoodiac', 'Inferior a VS K9'],
    [34, 'Exodia', 'Irregular pero explosivo'],
    [35, 'Blue Dragon', 'Historial limitado'],
    [36, 'Dark World', 'Potente segun entorno'],
    [37, 'Resonator Red Dragon Archfiend', 'Rogue solido'],
    [38, 'Traptrix Ragnaraika', 'Capaz'],
    [39, 'Cyber Dragon Pure', 'Historico'],
    [40, 'Six Samurai', 'Alto techo combo'],
    [41, 'Barrera de Hielo', 'Poco Top Cut moderno'],
    [42, 'Volcanic', 'Menor rendimiento'],
    [43, 'Horus', 'Motor flexible agregado'],
];

$divisionDefinitions = [
    'TCG_AVANZADO' => ['name' => 'TCG Avanzado', 'short' => 'TCG Avanzado', 'cup' => 'Copa TCG Avanzado', 'accent' => '#ffd36a', 'description' => 'Formato principal competitivo con banlist avanzada y mazos actuales.', 'size' => 43, 'offset' => 0],
    'OCG' => ['name' => 'OCG', 'short' => 'OCG', 'cup' => 'Copa OCG', 'accent' => '#9c6cff', 'description' => 'Formato oriental para listas, tecnologia y estrategias OCG.', 'size' => 0, 'offset' => 43],
    'GOAT' => ['name' => 'Primera Division GOAT', 'short' => 'GOAT', 'cup' => 'Copa GOAT', 'accent' => '#36d8ff', 'description' => 'Primera division de la Liga GOAT para partidas retro y control de recursos.', 'size' => 0, 'offset' => 43],
    'EDISON' => ['name' => 'Edison', 'short' => 'Edison', 'cup' => 'Copa Edison', 'accent' => '#38f29b', 'description' => 'Formato Edison con identidad historica y pool competitivo dedicado.', 'size' => 0, 'offset' => 43],
    'SPEED_DUEL' => ['name' => 'Speed Duel', 'short' => 'Speed Duel', 'cup' => 'Copa Speed Duel', 'accent' => '#ff7a59', 'description' => 'Formato rapido para duelos agiles, rondas cortas y eventos dinamicos.', 'size' => 0, 'offset' => 43],
    'GENESIS' => ['name' => 'Genesis', 'short' => 'Genesis', 'cup' => 'Copa Genesis', 'accent' => '#6de0c2', 'description' => 'Formato Genesis para propuestas nuevas, decks en evolucion y torneos especiales.', 'size' => 0, 'offset' => 43],
];

$divisions = [];
foreach ($divisionDefinitions as $code => $definition) {
    $divisions[$code] = $definition;
    $divisions[$code]['decks'] = array_slice($classificationDecks, $definition['offset'], $definition['size']);
}

$qualifierRules = [
    'Se juega antes de amistosos, liga regular, copas, mundial y Copa Eternal.',
    'Participan todos los decks registrados.',
    'Cada deck debe tener minimo 3 combates.',
    'Los 3 combates deben ser contra decks diferentes.',
    'El ranking final del clasificatorio rellena las 6 categorias oficiales.',
    'Los 43 decks base registrados pertenecen a TCG Avanzado.',
    'Los demas formatos existen para eventos especificos y solo muestran decks cargados para ese formato.',
];

$catalogGroups = ygo_default_catalog_groups();
$competitionTypeOptions = ygo_catalog_competition_type_options($catalogGroups['competition_type']);
$eventRulesetOptions = ygo_catalog_label_options($catalogGroups['ruleset']);
$eventModelOptions = ygo_event_model_options();

function ygo_event_model_options(): array
{
    return [
        'competition_system' => [
            'manual' => 'Manual / personalizado',
            'free_for_all' => 'Battle Royale / Free-for-All (3-4 jugadores)',
            'swiss' => 'Suizo',
            'swiss_top_cut' => 'Suizo + Top Cut',
            'single_elimination' => 'Eliminacion directa',
            'double_elimination' => 'Doble eliminacion',
            'round_robin' => 'Round Robin',
            'double_round_robin' => 'Round Robin ida y vuelta',
            'groups' => 'Fase de grupos',
            'groups_knockout' => 'Grupos + eliminacion',
            'league_points' => 'Liga por puntos',
            'playoffs' => 'Playoffs',
            'top_cut' => 'Top Cut',
            'bracket' => 'Bracket / llave',
            'flights' => 'Flights',
            'ladder' => 'Ladder',
            'king_of_the_hill' => 'King of the Hill',
            'gauntlet' => 'Gauntlet',
            'series' => 'Serie / final',
            'season_playoffs' => 'Temporada + playoffs',
            'custom' => 'Custom',
        ],
        'access_type' => [
            'OPEN' => 'Open',
            'INVITATIONAL' => 'Invitational',
            'QUALIFIER' => 'Clasificatorio',
            'LAST_CHANCE_QUALIFIER' => 'Last Chance Qualifier',
            'CUSTOM' => 'Custom',
        ],
        'participation' => [
            'INDIVIDUAL_1V1' => 'Individual 1v1',
            'FREE_FOR_ALL' => 'Individual multijugador (3-4)',
            'TAG_2V2' => 'Tag 2v2',
            'TEAM_3V3' => 'Equipos 3v3',
            'CUSTOM_TEAM' => 'Equipo personalizado',
        ],
        'construction' => [
            'CONSTRUCTED' => 'Constructed',
            'SEALED' => 'Sealed',
            'DRAFT' => 'Draft',
            'STRUCTURE_DECK' => 'Structure Deck',
            'DIGITAL' => 'Digital',
            'CUSTOM' => 'Custom',
        ],
        'environment' => [
            'PRESENCIAL' => 'Presencial',
            'ONLINE' => 'Online',
            'REMOTE_DUEL' => 'Remote Duel',
            'HYBRID' => 'Hibrido',
        ],
        'level' => [
            'CASUAL' => 'Casual',
            'BEGINNER' => 'Beginner',
            'ACADEMY' => 'Academy',
            'LOCAL' => 'Local',
            'COMPETITIVE' => 'Competitivo',
            'OFFICIAL' => 'Oficial',
            'PREMIER' => 'Premier',
            'CHAMPIONSHIP' => 'Championship',
            'WORLD' => 'Mundial',
            'PROMOTIONAL' => 'Promocional',
            'EXHIBITION' => 'Exhibicion',
            'SPECIAL' => 'Especial',
        ],
        'event_flow' => [
            'STANDALONE' => 'Independiente',
            'QUALIFIES_TO_EVENT' => 'Clasifica a otro evento',
            'RECEIVES_FROM_EVENT' => 'Recibe clasificados',
            'SEASON' => 'Temporada',
            'SEASON_PLAYOFF' => 'Playoff de temporada',
            'MULTI_STAGE' => 'Multifase',
            'CHAMPIONSHIP_PATH' => 'Ruta a campeonato',
            'CUSTOM' => 'Custom',
        ],
        'participant_source' => [
            'MANUAL' => 'Manual',
            'OPEN_REGISTRATION' => 'Registro abierto',
            'RANKING' => 'Ranking',
            'PREVIOUS_EVENT' => 'Evento previo',
            'QUALIFIER' => 'Clasificatorio',
            'LEAGUE' => 'Liga',
            'CUP' => 'Copa',
            'INVITATION' => 'Invitacion',
            'DEFENDING_CHAMPION' => 'Campeon defensor',
            'DIVISION' => 'Division',
            'CUSTOM' => 'Custom',
        ],
        'advancement_rule' => [
            'FINAL_STANDINGS' => 'Tabla final, sin avance interno',
            'SOURCE_ADMISSION' => 'Plazas configuradas en el evento destino',
            'MANUAL_ENTRIES' => 'Seleccion manual de participantes de entrada',
            'GROUP_TOP_K' => 'Cupos por grupo y luego ganador de la llave',
            'MANUAL_SELECTION' => 'Seleccion manual',
            'TOP_N' => 'Top N',
            'TOP_PERCENTAGE' => 'Porcentaje de plazas por fuente',
            'MINIMUM_POINTS' => 'Puntos minimos',
            'GROUP_POSITION' => 'Posicion de grupo',
            'WINNER_ONLY' => 'Solo ganador',
            'RUNNER_UP' => 'Subcampeon',
            'RANKING_THRESHOLD' => 'Corte por ranking',
            'BEST_RECORD' => 'Mejor record',
            'CUSTOM' => 'Custom',
        ],
        'draw_policy' => [
            'ALLOWED' => 'Empate permitido',
            'NOT_ALLOWED' => 'Empate no permitido',
            'EXTRA_DUEL' => 'Duelo extra',
            'EXTRA_MATCH' => 'Match extra',
            'SUDDEN_DEATH' => 'Muerte subita',
            'USE_TIEBREAKER' => 'Usar desempate',
            'MANUAL_DECISION' => 'Decision manual',
            'CUSTOM' => 'Custom',
        ],
        'ranking_impact' => [
            'NONE' => 'No afecta ranking',
            'FULL' => 'Impacto completo',
            'REDUCED' => 'Impacto reducido',
            'BONUS' => 'Bonificacion',
            'CUSTOM' => 'Custom',
        ],
        'deck_policy' => [
            'NO_REGISTRATION' => 'Sin registro de deck',
            'OPTIONAL_REGISTRATION' => 'Registro opcional',
            'REQUIRED_REGISTRATION' => 'Registro requerido',
            'DECK_LOCK_EVENT' => 'Deck bloqueado por evento',
            'DECK_LOCK_PHASE' => 'Deck bloqueado por fase',
            'DECK_LOCK_ROUND' => 'Deck bloqueado por ronda',
            'ALLOW_DECK_CHANGE' => 'Permite cambio de deck',
            'MULTIPLE_DECKS_ALLOWED' => 'Multiples decks permitidos',
            'CUSTOM' => 'Custom',
        ],
        'phase_structure' => [
            'NONE' => 'Sin fases',
            'GROUPS' => 'Grupos',
            'REGULAR_SEASON' => 'Temporada regular',
            'PLAYOFFS' => 'Playoffs',
            'TOP_CUT' => 'Top Cut',
            'SEMIFINAL' => 'Semifinal',
            'FINAL' => 'Final',
            'GRAND_FINAL' => 'Grand Final',
            'GROUPS_TOP_CUT_FINAL' => 'Grupos + Top Cut + Final',
            'SEASON_PLAYOFF_FINAL' => 'Temporada + Playoffs + Final',
            'CUSTOM' => 'Custom',
        ],
    ];
}

function ygo_allowed_execution_models_for_type(string $type): array
{
    $map = [
        'qualifier' => ['manual', 'swiss', 'swiss_top_cut', 'top_cut', 'flights', 'ladder', 'custom'],
        'tournament' => ['gauntlet', 'round_robin', 'double_round_robin', 'league_points', 'manual', 'swiss', 'swiss_top_cut', 'single_elimination', 'double_elimination', 'groups', 'groups_knockout', 'top_cut', 'bracket', 'flights', 'custom'],
        'league' => ['groups', 'swiss', 'swiss_top_cut', 'manual', 'round_robin', 'double_round_robin', 'league_points', 'season_playoffs', 'playoffs', 'custom'],
        'cup' => ['series', 'swiss', 'swiss_top_cut', 'manual', 'single_elimination', 'double_elimination', 'groups_knockout', 'top_cut', 'bracket', 'playoffs', 'custom'],
        'world' => ['manual', 'groups', 'groups_knockout', 'swiss_top_cut', 'top_cut', 'bracket', 'single_elimination', 'custom'],
        'final' => ['manual', 'series', 'single_elimination', 'top_cut', 'playoffs', 'custom'],
        'friendly' => ['series', 'manual', 'swiss', 'round_robin', 'single_elimination', 'king_of_the_hill', 'gauntlet', 'custom'],
    ];
    return $map[$type] ?? ['free_for_all', 'manual', 'swiss', 'swiss_top_cut', 'single_elimination', 'double_elimination', 'round_robin', 'double_round_robin', 'groups', 'groups_knockout', 'league_points', 'playoffs', 'top_cut', 'bracket', 'flights', 'ladder', 'king_of_the_hill', 'gauntlet', 'series', 'season_playoffs', 'custom'];
}

function ygo_event_template_type(string $code): string
{
    foreach (ygo_event_available_templates() as $template) {
        if ((string) ($template['code'] ?? '') === $code) {
            return (string) ($template['type'] ?? '');
        }
    }
    return '';
}

function ygo_ruleset_version_catalog(): array
{
    $items = [
        ['rule', 'OCG', 'Original Rules', '1999', '1999-01-01'],
        ['rule', 'OCG', 'Expert Rules', '1999', '1999-01-01'],
        ['rule', 'OCG', 'New Expert Rules', '2000', '2000-01-01'],
        ['rule', 'TCG', 'TCG Classic Rules / New Expert Rules Era', '2002', '2002-01-01'],
        ['rule', 'OCG / TCG', 'Master Rule 1', '2008', '2008-01-01'],
        ['rule', 'OCG / TCG', 'Master Rule 2', '2011', '2011-01-01'],
        ['rule', 'OCG', 'Master Rule 3', '21/03/2014', '2014-03-21'],
        ['rule', 'TCG', 'Master Rule 3', '2014', '2014-01-01'],
        ['rule', 'OCG', 'New Master Rule / Master Rule 4', '25/03/2017', '2017-03-25'],
        ['rule', 'TCG', 'Master Rule 4', '2017', '2017-01-01'],
        ['rule', 'OCG / TCG', 'Master Rule April 2020 Revision', '01/04/2020', '2020-04-01'],
        ['rule', 'TCG', '2021 Rules Update', '2021', '2021-01-01'],
        ['rule', 'TCG', 'Time Wizard Rules', '2022', '2022-01-01'],
        ['rule', 'TCG', 'Time Travel Rules', '2025', '2025-01-01'],
        ['rule', 'TCG', 'Genesys Rules', '2025', '2025-01-01'],
        ['rule', 'OCG / TCG', 'Current Master Rule Framework', '2020-Actualidad', '9999-12-31'],
    ];
    foreach ([
        ['TCG May 2026', '18/05/2026', '2026-05-18'],
        ['TCG February 2026', '02/02/2026', '2026-02-02'],
        ['TCG October 2025', '27/10/2025', '2025-10-27'],
        ['TCG September 2025', 'Septiembre 2025', '2025-09-01'],
        ['TCG April 2025', 'Abril 2025', '2025-04-01'],
        ['TCG December 2024', 'Diciembre 2024', '2024-12-01'],
        ['TCG September 2024', 'Septiembre 2024', '2024-09-01'],
        ['TCG April 2024', 'Abril 2024', '2024-04-01'],
        ['TCG January 2024', 'Enero 2024', '2024-01-01'],
        ['TCG September 2023', 'Septiembre 2023', '2023-09-01'],
        ['TCG June 2023', 'Junio 2023', '2023-06-01'],
        ['TCG February 2023', 'Febrero 2023', '2023-02-01'],
        ['TCG December 2022', 'Diciembre 2022', '2022-12-01'],
        ['TCG October 2022', 'Octubre 2022', '2022-10-01'],
        ['TCG May 2022', 'Mayo 2022', '2022-05-01'],
        ['TCG February 2022', 'Febrero 2022', '2022-02-01'],
        ['TCG October 2021', 'Octubre 2021', '2021-10-01'],
        ['TCG July 2021', 'Julio 2021', '2021-07-01'],
        ['TCG March 2021', 'Marzo 2021', '2021-03-01'],
        ['TCG December 2020', 'Diciembre 2020', '2020-12-01'],
        ['TCG September 2020', 'Septiembre 2020', '2020-09-01'],
        ['TCG June 2020', 'Junio 2020', '2020-06-01'],
        ['TCG April 2020', 'Abril 2020', '2020-04-01'],
        ['TCG January 2020', 'Enero 2020', '2020-01-01'],
        ['TCG October 2019', '14/10/2019', '2019-10-14'],
        ['TCG July 2019', 'Julio 2019', '2019-07-01'],
        ['TCG April 2019', 'Abril 2019', '2019-04-01'],
        ['TCG January 2019', 'Enero 2019', '2019-01-01'],
        ['TCG December 2018', 'Diciembre 2018', '2018-12-01'],
        ['TCG September 2018', 'Septiembre 2018', '2018-09-01'],
        ['TCG May 2018', 'Mayo 2018', '2018-05-01'],
        ['TCG February 2018', 'Febrero 2018', '2018-02-01'],
        ['TCG November 2017', 'Noviembre 2017', '2017-11-01'],
        ['TCG September 2017', 'Septiembre 2017', '2017-09-01'],
        ['TCG June 2017', 'Junio 2017', '2017-06-01'],
        ['TCG March 2017', 'Marzo 2017', '2017-03-01'],
        ['TCG August 2016', 'Agosto 2016', '2016-08-01'],
        ['TCG April 2016', 'Abril 2016', '2016-04-01'],
        ['TCG November 2015', 'Noviembre 2015', '2015-11-01'],
        ['TCG July 2015', 'Julio 2015', '2015-07-01'],
        ['TCG April 2015', 'Abril 2015', '2015-04-01'],
        ['TCG January 2015', 'Enero 2015', '2015-01-01'],
        ['TCG October 2014', 'Octubre 2014', '2014-10-01'],
        ['TCG July 2014', 'Julio 2014', '2014-07-01'],
        ['TCG April 2014', '01/04/2014', '2014-04-01'],
        ['TCG January 2014', 'Enero 2014', '2014-01-01'],
        ['TCG September 2013', '01/09/2013', '2013-09-01'],
        ['TCG March 2013', '01/03/2013', '2013-03-01'],
        ['TCG September 2012', '01/09/2012', '2012-09-01'],
        ['TCG March 2012', '01/03/2012', '2012-03-01'],
        ['TCG September 2011', '01/09/2011', '2011-09-01'],
        ['TCG March 2011', '01/03/2011', '2011-03-01'],
        ['TCG September 2010', '01/09/2010', '2010-09-01'],
        ['TCG March 2010', '01/03/2010', '2010-03-01'],
        ['TCG September 2009', '01/09/2009', '2009-09-01'],
        ['TCG March 2009', '01/03/2009', '2009-03-01'],
        ['TCG September 2008', '01/09/2008', '2008-09-01'],
        ['TCG March 2008', '01/03/2008', '2008-03-01'],
        ['TCG September 2007', '01/09/2007', '2007-09-01'],
        ['TCG March 2007', '01/03/2007', '2007-03-01'],
        ['TCG September 2006', '01/09/2006', '2006-09-01'],
        ['TCG March 2006', '01/03/2006', '2006-03-01'],
        ['TCG September 2005', '01/09/2005', '2005-09-01'],
        ['TCG March 2005', '01/03/2005', '2005-03-01'],
        ['TCG September 2004', 'Septiembre 2004', '2004-09-01'],
        ['TCG March 2004', 'Marzo 2004', '2004-03-01'],
        ['TCG September 2003', 'Septiembre 2003', '2003-09-01'],
        ['TCG January 2003', 'Enero 2003', '2003-01-01'],
        ['TCG May 2001', 'Mayo 2001', '2001-05-01'],
        ['TCG November 2000', 'Noviembre 2000', '2000-11-01'],
        ['TCG April 2000', 'Abril 2000', '2000-04-01'],
        ['TCG August 1999', 'Agosto 1999', '1999-08-01'],
    ] as $row) {
        $items[] = ['banlist', 'TCG', $row[0], $row[1], $row[2]];
    }
    foreach ([
        ['OCG July 2026', '01/07/2026', '2026-07-01'], ['OCG April 2026', '01/04/2026', '2026-04-01'], ['OCG January 2026', '01/01/2026', '2026-01-01'],
        ['OCG October 2025', '01/10/2025', '2025-10-01'], ['OCG July 2025', '01/07/2025', '2025-07-01'], ['OCG April 2025', '01/04/2025', '2025-04-01'], ['OCG January 2025', '01/01/2025', '2025-01-01'],
        ['OCG October 2024', '01/10/2024', '2024-10-01'], ['OCG July 2024', '01/07/2024', '2024-07-01'], ['OCG April 2024', '01/04/2024', '2024-04-01'], ['OCG January 2024', '01/01/2024', '2024-01-01'],
        ['OCG October 2023', '01/10/2023', '2023-10-01'], ['OCG July 2023', '01/07/2023', '2023-07-01'], ['OCG April 2023', '01/04/2023', '2023-04-01'], ['OCG January 2023', '01/01/2023', '2023-01-01'],
        ['OCG October 2022', '01/10/2022', '2022-10-01'], ['OCG July 2022', '01/07/2022', '2022-07-01'], ['OCG April 2022', '01/04/2022', '2022-04-01'], ['OCG January 2022', '01/01/2022', '2022-01-01'],
        ['OCG October 2021', '01/10/2021', '2021-10-01'], ['OCG July 2021', '01/07/2021', '2021-07-01'], ['OCG April 2021', '01/04/2021', '2021-04-01'], ['OCG January 2021', '01/01/2021', '2021-01-01'],
        ['OCG October 2020', '01/10/2020', '2020-10-01'], ['OCG July 2020', '01/07/2020', '2020-07-01'], ['OCG April 2020', '01/04/2020', '2020-04-01'], ['OCG January 2020', '01/01/2020', '2020-01-01'],
        ['OCG October 2019', '01/10/2019', '2019-10-01'], ['OCG July 2019', '01/07/2019', '2019-07-01'], ['OCG April 2019', '01/04/2019', '2019-04-01'], ['OCG January 2019', '01/01/2019', '2019-01-01'],
        ['OCG October 2018', '01/10/2018', '2018-10-01'], ['OCG July 2018', '01/07/2018', '2018-07-01'], ['OCG April 2018', '01/04/2018', '2018-04-01'], ['OCG January 2018', '01/01/2018', '2018-01-01'],
        ['OCG October 2017', '01/10/2017', '2017-10-01'], ['OCG July 2017', '01/07/2017', '2017-07-01'], ['OCG April 2017', '01/04/2017', '2017-04-01'], ['OCG January 2017', '01/01/2017', '2017-01-01'],
        ['OCG October 2016', '01/10/2016', '2016-10-01'], ['OCG July 2016', '01/07/2016', '2016-07-01'], ['OCG April 2016', '01/04/2016', '2016-04-01'], ['OCG January 2016', '01/01/2016', '2016-01-01'],
        ['OCG October 2015', '01/10/2015', '2015-10-01'], ['OCG July 2015', '01/07/2015', '2015-07-01'], ['OCG April 2015', '01/04/2015', '2015-04-01'], ['OCG January 2015', '01/01/2015', '2015-01-01'],
        ['OCG October 2014', '01/10/2014', '2014-10-01'], ['OCG July 2014', '01/07/2014', '2014-07-01'], ['OCG April 2014', '01/04/2014', '2014-04-01'], ['OCG February 2014', '01/02/2014', '2014-02-01'],
        ['OCG November 2013', '01/11/2013', '2013-11-01'], ['OCG September 2013', '01/09/2013', '2013-09-01'], ['OCG March 2013', '01/03/2013', '2013-03-01'],
        ['OCG September 2012', '01/09/2012', '2012-09-01'], ['OCG March 2012', '01/03/2012', '2012-03-01'],
        ['OCG September 2011', '01/09/2011', '2011-09-01'], ['OCG March 2011', '01/03/2011', '2011-03-01'],
        ['OCG September 2010', '01/09/2010', '2010-09-01'], ['OCG March 2010', '01/03/2010', '2010-03-01'],
        ['OCG September 2009', '01/09/2009', '2009-09-01'], ['OCG March 2009', '01/03/2009', '2009-03-01'],
        ['OCG September 2008', '01/09/2008', '2008-09-01'], ['OCG March 2008', '01/03/2008', '2008-03-01'],
        ['OCG September 2007', '01/09/2007', '2007-09-01'], ['OCG March 2007', '01/03/2007', '2007-03-01'],
        ['OCG September 2006', '01/09/2006', '2006-09-01'], ['OCG March 2006', '01/03/2006', '2006-03-01'],
        ['OCG September 2005', '01/09/2005', '2005-09-01'], ['OCG March 2005', '01/03/2005', '2005-03-01'],
        ['OCG September 2004', '01/09/2004', '2004-09-01'], ['OCG March 2004', '01/03/2004', '2004-03-01'],
        ['OCG October 2003', '15/10/2003', '2003-10-15'], ['OCG July 2003', '01/07/2003', '2003-07-01'], ['OCG April 2003', '10/04/2003', '2003-04-10'], ['OCG January 2003', '01/01/2003', '2003-01-01'],
        ['OCG May 2002', '01/05/2002', '2002-05-01'], ['OCG January 2002', '01/01/2002', '2002-01-01'],
        ['OCG May 2001', '28/05/2001', '2001-05-28'], ['OCG January 2001', '15/01/2001', '2001-01-15'],
        ['OCG November 2000', '01/11/2000', '2000-11-01'], ['OCG August 2000', '15/08/2000', '2000-08-15'], ['OCG July 2000', '15/07/2000', '2000-07-15'], ['OCG May 2000', '15/05/2000', '2000-05-15'], ['OCG April 2000', '01/04/2000', '2000-04-01'],
        ['OCG 1999', '1999', '1999-01-01'],
    ] as $row) {
        $items[] = ['banlist', 'OCG', $row[0], $row[1], $row[2]];
    }
    usort($items, static fn(array $a, array $b): int => strcmp($b[4], $a[4]) ?: strcmp($b[2], $a[2]));
    return array_map(static function (array $item): array {
        return [
            'category' => $item[0],
            'system' => $item[1],
            'version' => $item[2],
            'date_label' => $item[3],
            'sort_date' => $item[4],
            'value' => strtoupper(preg_replace('/[^A-Za-z0-9]+/', '_', $item[1] . '_' . $item[2] . '_' . $item[4])),
        ];
    }, $items);
}

function ygo_event_prompt_templates(): array
{
    $rows = [
        ['clasificatorio_divisiones_3_duelos', 'Clasificatorio | Suizo 3 duelos | Ranking para divisiones', 'Suizo', 'Individual 1v1', 'Constructed', 'TCG Advanced', 'Presencial', 'Competitivo'],
        ['torneo_local', 'Torneo Local', 'Suizo', 'Individual 1v1', 'Constructed', 'TCG Advanced', 'Presencial', 'Local'],
        ['liga', 'Liga', 'Round Robin / Suizo acumulativo', 'Individual 1v1', 'Constructed', 'TCG Advanced', 'Presencial', 'Local'],
        ['copa', 'Copa', 'Grupos + Eliminacion / Suizo + Top Cut', 'Individual 1v1', 'Constructed', 'TCG Advanced', 'Presencial', 'Competitivo'],
        ['clasificatorio', 'Clasificatorio', 'Suizo + Top Cut', 'Individual 1v1', 'Constructed', 'TCG Advanced', 'Presencial', 'Competitivo'],
        ['regional_qualifier', 'Regional Qualifier', 'Suizo + Clasificacion', 'Individual 1v1', 'Constructed', 'TCG Advanced', 'Presencial', 'Oficial'],
        ['national_qualifier', 'National Qualifier', 'Suizo + Top Cut', 'Individual 1v1', 'Constructed', 'TCG Advanced', 'Presencial', 'Oficial'],
        ['wcq_world_championship_qualifier', 'WCQ - World Championship Qualifier', 'Suizo + Top Cut', 'Individual 1v1', 'Constructed', 'TCG Advanced', 'Presencial', 'Premier'],
        ['ycs_yu_gi_oh_championship_series', 'YCS - Yu-Gi-Oh! Championship Series', 'Suizo + Top Cut', 'Individual 1v1', 'Constructed', 'TCG Advanced', 'Presencial', 'Premier'],
        ['team_ycs', 'Team YCS', 'Suizo + Top Cut', 'Equipos 3v3', 'Constructed', 'TCG Advanced', 'Presencial', 'Premier'],
        ['world_championship', 'World Championship', 'Rondas + Eliminacion', 'Individual 1v1', 'Constructed', 'Reglamento Mundial', 'Presencial', 'Mundial'],
        ['ots_championship', 'OTS Championship', 'Suizo + Top Cut', 'Individual 1v1', 'Constructed', 'TCG Advanced', 'Presencial', 'Oficial'],
        ['ots_tournament_series', 'OTS Tournament Series', 'Suizo + Top Cut', 'Equipos 3v3', 'Constructed', 'TCG Advanced', 'Presencial', 'Oficial'],
        ['ycs_vip_qualifier', 'YCS VIP Qualifier', 'Suizo / Eliminacion', 'Individual 1v1', 'Constructed', 'TCG Advanced', 'Presencial', 'Clasificatorio'],
        ['dragon_duel', 'Dragon Duel', 'Suizo', 'Individual 1v1', 'Constructed', 'TCG Advanced', 'Presencial', 'Juvenil'],
        ['dragon_duel_championship', 'Dragon Duel Championship', 'Suizo + Top Cut', 'Individual 1v1', 'Constructed', 'TCG Advanced', 'Presencial', 'Juvenil'],
        ['remote_duel_regional', 'Remote Duel Regional', 'Suizo', 'Individual 1v1', 'Constructed', 'TCG Advanced', 'Remoto', 'Oficial'],
        ['remote_duel_ycs', 'Remote Duel YCS', 'Suizo + Top Cut', 'Individual 1v1', 'Constructed', 'TCG Advanced', 'Remoto', 'Premier'],
        ['genesys_regional_qualifier', 'Genesys Regional Qualifier', 'Suizo + Clasificacion', 'Individual 1v1', 'Constructed', 'Genesys', 'Presencial', 'Oficial'],
        ['genesys_championship', 'Genesys Championship', 'Suizo + Top Cut', 'Individual 1v1', 'Constructed', 'Genesys', 'Presencial', 'Oficial'],
        ['yugioh_day', 'Yu-Gi-Oh! Day', 'Suizo / Evento especial', 'Individual 1v1', 'Constructed', 'TCG Advanced', 'Presencial', 'Promocional'],
        ['core_booster_premiere', 'Core Booster Premiere!', 'Evento de estreno', 'Individual 1v1', 'Sealed / Constructed', 'Reglas especiales', 'Presencial', 'Promocional'],
        ['world_championship_celebration', 'World Championship Celebration', 'Suizo / Especial', 'Individual 1v1', 'Constructed', 'TCG Advanced', 'Presencial', 'Celebracion'],
        ['attack_of_the_giant_card', 'Attack of the Giant Card!!', 'Suizo / Eliminacion', 'Individual 1v1', 'Constructed', 'TCG Advanced', 'Presencial', 'Evento publico'],
        ['win_a_mat', 'Win-A-Mat', 'Eliminacion directa', 'Individual 1v1', 'Constructed', 'TCG Advanced', 'Presencial', 'Evento publico'],
        ['public_event_playoff', 'Public Event Playoff', 'Eliminacion directa', 'Individual 1v1', 'Constructed', 'TCG Advanced', 'Presencial', 'Evento publico'],
        ['structure_deck_tournament', 'Structure Deck Tournament', 'Eliminacion / Flight', 'Individual 1v1', 'Structure Deck', 'Reglas del evento', 'Presencial', 'Especial'],
        ['charity_tournament', 'Charity Tournament', 'Suizo', 'Individual 1v1', 'Constructed', 'TCG Advanced', 'Presencial', 'Especial'],
        ['team_3v3', '3 vs 3 Team Dueling', 'Suizo / Eliminacion', 'Equipos 3v3', 'Constructed', 'TCG Advanced', 'Presencial', 'Equipos'],
        ['tag_duel', 'Tag Duel', 'Suizo / Eliminacion', 'Equipos 2v2', 'Constructed', 'Tag Duel', 'Presencial', 'Alternativo'],
        ['rivalry_of_warlords', 'Rivalry of Warlords', 'Suizo / Eliminacion', 'Individual 1v1', 'Constructed', 'Rivalry of Warlords', 'Presencial', 'Alternativo'],
        ['time_wizard', 'Time Wizard', 'Suizo / Eliminacion', 'Individual 1v1', 'Constructed', 'Historico', 'Presencial', 'Alternativo'],
        ['time_travel', 'Time Travel', 'Suizo / Eliminacion', 'Individual 1v1', 'Constructed', 'Historico con reglas modernas', 'Presencial', 'Alternativo'],
        ['goat_format_tournament', 'GOAT Format Tournament', 'Suizo / Eliminacion', 'Individual 1v1', 'Constructed', 'GOAT 2005', 'Presencial', 'Historico'],
        ['edison_format_tournament', 'Edison Format Tournament', 'Suizo / Eliminacion', 'Individual 1v1', 'Constructed', 'Edison 2010', 'Presencial', 'Historico'],
        ['heart_of_the_underdog', 'Heart of the Underdog', 'Suizo', 'Individual 1v1', 'Constructed', 'Heart of the Underdog', 'Presencial', 'Alternativo'],
        ['common_charity', 'Common Charity', 'Suizo', 'Individual 1v1', 'Constructed', 'Common Charity', 'Presencial', 'Alternativo'],
        ['deck_master', 'Deck Master', 'Suizo', 'Individual 1v1', 'Constructed', 'Deck Master', 'Presencial', 'Alternativo'],
        ['pegasus_challenge', 'Pegasus Challenge', 'Suizo / Reglas especiales', 'Individual 1v1', 'Constructed', 'Pegasus Challenge', 'Presencial', 'Alternativo'],
        ['speed_duel_tournament', 'Speed Duel Tournament', 'Suizo', 'Individual 1v1', 'Constructed', 'Speed Duel', 'Presencial', 'Alternativo'],
        ['speed_duel_main_event', 'Speed Duel Main Event', 'Suizo + Top Cut', 'Individual 1v1', 'Constructed', 'Speed Duel', 'Presencial', 'Competitivo'],
        ['master_duel_tournament', 'Master Duel Tournament', 'Suizo / Eliminacion', 'Individual 1v1', 'Digital', 'Master Duel', 'Online', 'Digital'],
        ['duel_links_tournament', 'Duel Links Tournament', 'Suizo / Eliminacion', 'Individual 1v1', 'Digital', 'Duel Links', 'Online', 'Digital'],
        ['sealed_deck_tournament', 'Sealed Deck Tournament', 'Suizo / Eliminacion', 'Individual 1v1', 'Sealed', 'Limitado', 'Presencial', 'Especial'],
        ['draft_tournament', 'Draft Tournament', 'Draft + Suizo / Eliminacion', 'Individual 1v1', 'Draft', 'Limitado', 'Presencial', 'Especial'],
        ['casual_tournament', 'Casual Tournament', 'Suizo / Libre', 'Individual 1v1', 'Constructed', 'Formato configurable', 'Presencial', 'Casual'],
        ['exhibition_match', 'Exhibition Match', 'Partida unica / Serie', 'Individual / Equipos', 'Constructed', 'Formato configurable', 'Presencial', 'Exhibicion'],
        ['invitational', 'Invitational', 'Suizo + Top Cut / Eliminacion', 'Individual 1v1', 'Constructed', 'Formato configurable', 'Presencial', 'Especial'],
        ['open_tournament', 'Open Tournament', 'Suizo + Top Cut', 'Individual 1v1', 'Constructed', 'TCG Advanced', 'Presencial', 'Competitivo'],
        ['championship', 'Championship', 'Suizo + Top Cut', 'Individual 1v1', 'Constructed', 'TCG Advanced', 'Presencial', 'Competitivo'],
        ['finals', 'Finals', 'Eliminacion directa', 'Individual 1v1', 'Constructed', 'Segun evento', 'Presencial', 'Final'],
        ['playoffs', 'Playoffs', 'Eliminacion directa', 'Individual / Equipos', 'Constructed', 'Segun evento', 'Presencial', 'Fase'],
        ['top_cut', 'Top Cut', 'Eliminacion directa', 'Individual / Equipos', 'Constructed', 'Segun evento', 'Presencial', 'Fase'],
        ['last_chance_qualifier', 'Last Chance Qualifier', 'Eliminacion / Suizo', 'Individual 1v1', 'Constructed', 'Segun evento', 'Presencial', 'Clasificatorio'],
        ['flight_tournament', 'Flight Tournament', 'Eliminacion directa', 'Individual 1v1', 'Constructed', 'Segun evento', 'Presencial', 'Mini torneo'],
        ['single_elimination_tournament', 'Single Elimination Tournament', 'Eliminacion directa', 'Individual 1v1', 'Constructed', 'Configurable', 'Presencial', 'Generico'],
        ['double_elimination_tournament', 'Double Elimination Tournament', 'Doble eliminacion', 'Individual 1v1', 'Constructed', 'Configurable', 'Presencial', 'Generico'],
        ['round_robin_tournament', 'Round Robin Tournament', 'Todos contra todos', 'Individual 1v1', 'Constructed', 'Configurable', 'Presencial', 'Generico'],
        ['double_round_robin', 'Double Round Robin', 'Todos contra todos ida y vuelta', 'Individual 1v1', 'Constructed', 'Configurable', 'Presencial', 'Generico'],
        ['group_stage_tournament', 'Group Stage Tournament', 'Fase de grupos', 'Individual / Equipos', 'Constructed', 'Configurable', 'Presencial', 'Generico'],
        ['groups_knockout', 'Groups + Knockout', 'Grupos + Eliminacion', 'Individual / Equipos', 'Constructed', 'Configurable', 'Presencial', 'Generico'],
        ['swiss_tournament', 'Swiss Tournament', 'Suizo', 'Individual 1v1', 'Constructed', 'Configurable', 'Presencial', 'Generico'],
        ['swiss_top_cut', 'Swiss + Top Cut', 'Suizo + Eliminacion', 'Individual 1v1', 'Constructed', 'Configurable', 'Presencial', 'Generico'],
        ['league_table', 'League Table', 'Tabla por puntos', 'Individual / Equipos', 'Constructed', 'Configurable', 'Presencial', 'Liga'],
        ['bracket_tournament', 'Bracket Tournament', 'Llave eliminatoria', 'Individual / Equipos', 'Constructed', 'Configurable', 'Presencial', 'Generico'],
        ['best_of_1_event', 'Best of 1 Event', 'Suizo / Eliminacion', 'Individual 1v1', 'Constructed', 'Configurable', 'Presencial / Online', 'Generico'],
        ['best_of_3_event', 'Best of 3 Event', 'Suizo / Eliminacion', 'Individual 1v1', 'Constructed', 'TCG estandar', 'Presencial / Online', 'Generico'],
        ['match_play', 'Match Play', 'Suizo / Eliminacion', 'Individual 1v1', 'Constructed', 'TCG', 'Presencial', 'Competitivo'],
        ['team_league', 'Team League', 'Round Robin / Suizo', 'Equipos', 'Constructed', 'Configurable', 'Presencial', 'Liga'],
        ['team_cup', 'Team Cup', 'Grupos + Eliminacion', 'Equipos', 'Constructed', 'Configurable', 'Presencial', 'Copa'],
        ['team_championship', 'Team Championship', 'Suizo + Top Cut', 'Equipos', 'Constructed', 'Configurable', 'Presencial', 'Campeonato'],
        ['online_league', 'Online League', 'Round Robin / Suizo', 'Individual 1v1', 'Digital / Constructed', 'Configurable', 'Online', 'Liga'],
        ['online_cup', 'Online Cup', 'Suizo + Top Cut / Eliminacion', 'Individual 1v1', 'Digital', 'Configurable', 'Online', 'Copa'],
        ['online_qualifier', 'Online Qualifier', 'Suizo + Top Cut', 'Individual 1v1', 'Digital', 'Configurable', 'Online', 'Clasificatorio'],
        ['ranking_tournament', 'Ranking Tournament', 'Suizo / Liga', 'Individual 1v1', 'Constructed', 'Configurable', 'Presencial', 'Ranking'],
        ['ladder', 'Ladder', 'Clasificacion continua', 'Individual 1v1', 'Constructed', 'Configurable', 'Presencial / Online', 'Ranking'],
        ['king_of_the_hill', 'King of the Hill', 'Ganador permanece', 'Individual 1v1', 'Constructed', 'Configurable', 'Presencial', 'Casual'],
        ['gauntlet', 'Gauntlet', 'Serie contra multiples rivales', 'Individual 1v1', 'Constructed', 'Configurable', 'Presencial', 'Especial'],
        ['knockout_cup', 'Knockout Cup', 'Eliminacion directa', 'Individual / Equipos', 'Constructed', 'Configurable', 'Presencial', 'Copa'],
        ['group_league', 'Group League', 'Round Robin por grupos', 'Individual / Equipos', 'Constructed', 'Configurable', 'Presencial', 'Liga'],
        ['season_tournament', 'Season Tournament', 'Liga + Playoffs', 'Individual / Equipos', 'Constructed', 'Configurable', 'Presencial', 'Temporada'],
        ['regular_season', 'Regular Season', 'Tabla / Round Robin', 'Individual / Equipos', 'Constructed', 'Configurable', 'Presencial', 'Temporada'],
        ['postseason', 'Postseason', 'Playoffs', 'Individual / Equipos', 'Constructed', 'Configurable', 'Presencial', 'Temporada'],
        ['grand_final', 'Grand Final', 'Final unica / Best of X', 'Individual / Equipos', 'Constructed', 'Segun evento', 'Presencial', 'Final'],
        ['super_cup', 'Super Cup', 'Campeones enfrentados', 'Individual / Equipos', 'Constructed', 'Configurable', 'Presencial', 'Especial'],
        ['champions_cup', 'Champions Cup', 'Grupos + Eliminacion', 'Individual / Equipos', 'Constructed', 'Configurable', 'Presencial', 'Copa'],
        ['masters_tournament', 'Masters Tournament', 'Invitacional / Suizo + Top', 'Individual 1v1', 'Constructed', 'Configurable', 'Presencial', 'Especial'],
        ['all_star_event', 'All-Star Event', 'Exhibicion / Equipos', 'Individual / Equipos', 'Constructed', 'Reglas especiales', 'Presencial', 'Exhibicion'],
        ['rookie_tournament', 'Rookie Tournament', 'Suizo', 'Individual 1v1', 'Constructed', 'Configurable', 'Presencial', 'Principiantes'],
        ['beginner_tournament', 'Beginner Tournament', 'Suizo / Casual', 'Individual 1v1', 'Constructed', 'Configurable', 'Presencial', 'Principiantes'],
        ['academy_tournament', 'Academy Tournament', 'Suizo / Liga', 'Individual 1v1', 'Constructed', 'Configurable', 'Presencial', 'Formacion'],
    ];
    return array_map(static function (array $row): array {
        [$code, $label, $system, $participation, $construction, $rules, $environment, $level] = $row;
        $haystack = strtolower($label . ' ' . $system . ' ' . $participation . ' ' . $rules . ' ' . $level);
        $typeByTemplate = [
            'torneo_local' => 'tournament',
            'liga' => 'league',
            'copa' => 'cup',
            'clasificatorio' => 'qualifier',
            'clasificatorio_divisiones_3_duelos' => 'qualifier',
            'regional_qualifier' => 'qualifier',
            'national_qualifier' => 'qualifier',
            'wcq_world_championship_qualifier' => 'qualifier',
            'ycs_yu_gi_oh_championship_series' => 'tournament',
            'team_ycs' => 'tournament',
            'world_championship' => 'world',
            'ots_championship' => 'tournament',
            'ots_tournament_series' => 'tournament',
            'ycs_vip_qualifier' => 'qualifier',
            'dragon_duel' => 'tournament',
            'dragon_duel_championship' => 'tournament',
            'remote_duel_regional' => 'qualifier',
            'remote_duel_ycs' => 'tournament',
            'genesys_regional_qualifier' => 'qualifier',
            'genesys_championship' => 'tournament',
            'yu_gi_oh_day' => 'special',
            'core_booster_premiere' => 'special',
            'world_championship_celebration' => 'special',
            'attack_of_the_giant_card' => 'special',
            'win_a_mat' => 'tournament',
            'public_event_playoff' => 'tournament',
            'structure_deck_tournament' => 'tournament',
            'charity_tournament' => 'special',
            'team_3v3' => 'tournament',
            'tag_duel' => 'tournament',
            'rivalry_of_warlords' => 'tournament',
            'time_wizard' => 'tournament',
            'time_travel' => 'tournament',
            'goat_format_tournament' => 'tournament',
            'edison_format_tournament' => 'tournament',
            'heart_of_the_underdog' => 'tournament',
            'common_charity' => 'tournament',
            'deck_master' => 'tournament',
            'pegasus_challenge' => 'special',
            'speed_duel_tournament' => 'tournament',
            'speed_duel_main_event' => 'tournament',
            'master_duel_tournament' => 'tournament',
            'duel_links_tournament' => 'tournament',
            'sealed_deck_tournament' => 'tournament',
            'draft_tournament' => 'tournament',
            'casual_tournament' => 'friendly',
            'exhibition_match' => 'friendly',
            'invitational' => 'tournament',
            'open_tournament' => 'tournament',
            'championship' => 'tournament',
            'finals' => 'final',
            'last_chance_qualifier' => 'qualifier',
            'flight_tournament' => 'tournament',
            'team_championship' => 'tournament',
            'online_qualifier' => 'qualifier',
            'ranking_tournament' => 'tournament',
            'season_tournament' => 'league',
            'grand_final' => 'final',
            'super_cup' => 'cup',
            'champions_cup' => 'cup',
            'masters_tournament' => 'tournament',
            'all_star_event' => 'special',
            'rookie_tournament' => 'tournament',
            'beginner_tournament' => 'tournament',
            'academy_tournament' => 'tournament',
        ];
        $type = $typeByTemplate[$code] ?? (str_contains($haystack, 'liga') || str_contains($haystack, 'league') || str_contains($haystack, 'round robin') || str_contains($haystack, 'season') || str_contains($haystack, 'temporada') ? 'league'
            : (str_contains($haystack, 'copa') || str_contains($haystack, 'cup') ? 'cup'
            : (str_contains($haystack, 'clasific') || str_contains($haystack, 'qualifier') || str_contains($haystack, 'ranking') || str_contains($haystack, 'ladder') ? 'qualifier'
            : (str_contains($haystack, 'world') || str_contains($haystack, 'mundial') ? 'world'
            : (str_contains($haystack, 'final') || str_contains($haystack, 'playoff') || str_contains($haystack, 'top cut') ? 'final'
            : (str_contains($haystack, 'casual') || str_contains($haystack, 'exhibition') || str_contains($haystack, 'exhibicion') ? 'friendly' : 'tournament'))))));
        $ruleset = str_contains($haystack, 'master duel') ? 'master_duel'
            : (str_contains($haystack, 'speed duel') ? 'speed_duel'
            : (str_contains($haystack, 'edison') ? 'edison'
            : (str_contains($haystack, 'goat') ? 'goat'
            : (str_contains($haystack, 'genesys') ? 'genesis'
            : (str_contains($haystack, 'ocg') && !str_contains($haystack, 'tcg') ? 'ocg'
            : (str_contains($haystack, 'tcg') ? 'tcg_advanced' : 'custom'))))));
        $matchFormat = str_contains($haystack, '3v3') ? '3v3' : (str_contains($haystack, '2v2') ? '2v2' : '1v1');
        if (str_contains($haystack, 'doble eliminacion') || str_contains($haystack, 'double elimination')) {
            $executionModel = 'double_elimination';
        } elseif ((str_contains($haystack, 'grupos') || str_contains($haystack, 'groups')) && (str_contains($haystack, 'eliminacion') || str_contains($haystack, 'knockout'))) {
            $executionModel = 'groups_knockout';
        } elseif (str_contains($haystack, 'fase de grupos') || str_contains($haystack, 'group stage')) {
            $executionModel = 'groups';
        } elseif ((str_contains($haystack, 'suizo') || str_contains($haystack, 'swiss')) && (str_contains($haystack, 'top cut') || str_contains($haystack, 'eliminacion'))) {
            $executionModel = 'swiss_top_cut';
        } elseif (str_contains($haystack, 'suizo') || str_contains($haystack, 'swiss')) {
            $executionModel = 'swiss';
        } elseif (str_contains($haystack, 'ida y vuelta') || str_contains($haystack, 'double round robin')) {
            $executionModel = 'double_round_robin';
        } elseif (str_contains($haystack, 'round robin') || str_contains($haystack, 'todos contra todos') || str_contains($haystack, 'tabla por puntos')) {
            $executionModel = 'round_robin';
        } elseif (str_contains($haystack, 'liga + playoffs') || str_contains($haystack, 'temporada') || str_contains($haystack, 'season')) {
            $executionModel = 'season_playoffs';
        } elseif (str_contains($haystack, 'ladder') || str_contains($haystack, 'clasificacion continua')) {
            $executionModel = 'ladder';
        } elseif (str_contains($haystack, 'king of the hill') || str_contains($haystack, 'ganador permanece')) {
            $executionModel = 'king_of_the_hill';
        } elseif (str_contains($haystack, 'gauntlet')) {
            $executionModel = 'gauntlet';
        } elseif (str_contains($haystack, 'flight')) {
            $executionModel = 'flights';
        } elseif (str_contains($haystack, 'playoff') || str_contains($haystack, 'top cut') || str_contains($haystack, 'bracket') || str_contains($haystack, 'llave') || str_contains($haystack, 'knockout') || str_contains($haystack, 'eliminacion')) {
            $executionModel = 'single_elimination';
        } elseif (str_contains($haystack, 'partida unica') || str_contains($haystack, 'serie') || str_contains($haystack, 'campeones enfrentados')) {
            $executionModel = 'series';
        } else {
            $executionModel = 'manual';
        }
        $schedule = in_array($executionModel, ['round_robin', 'double_round_robin', 'league_table'], true) ? 'auto_round_robin'
            : (in_array($executionModel, ['swiss', 'swiss_top_cut'], true) ? 'auto_swiss'
            : (in_array($executionModel, ['single_elimination', 'double_elimination', 'groups_knockout', 'groups', 'flights', 'season_playoffs'], true) ? 'auto_knockout' : 'manual'));
        $participationCode = $matchFormat === '3v3' ? 'TEAM_3V3' : ($matchFormat === '2v2' ? 'TAG_2V2' : 'INDIVIDUAL_1V1');
        $constructionCode = str_contains($haystack, 'sealed') ? 'SEALED' : (str_contains($haystack, 'draft') ? 'DRAFT' : (str_contains($haystack, 'structure deck') ? 'STRUCTURE_DECK' : (str_contains($haystack, 'digital') || str_contains($haystack, 'master duel') || str_contains($haystack, 'duel links') ? 'DIGITAL' : 'CONSTRUCTED')));
        $environmentCode = str_contains($haystack, 'remote') || str_contains($haystack, 'remoto') ? 'REMOTE_DUEL' : (str_contains($haystack, 'online') ? 'ONLINE' : 'PRESENCIAL');
        $levelCode = $type === 'world' ? 'WORLD' : ($type === 'friendly' ? 'EXHIBITION' : (str_contains($haystack, 'premier') ? 'PREMIER' : (str_contains($haystack, 'oficial') || str_contains($haystack, 'official') ? 'OFFICIAL' : (str_contains($haystack, 'principiante') || str_contains($haystack, 'beginner') ? 'BEGINNER' : (str_contains($haystack, 'academy') || str_contains($haystack, 'formacion') ? 'ACADEMY' : ($type === 'special' ? 'SPECIAL' : 'COMPETITIVE'))))));
        $eventFlow = $type === 'qualifier' ? 'QUALIFIES_TO_EVENT' : ($type === 'league' ? 'SEASON' : ($type === 'final' ? 'RECEIVES_FROM_EVENT' : ($type === 'world' ? 'CHAMPIONSHIP_PATH' : 'STANDALONE')));
        $participantSource = $type === 'final' || $type === 'world' ? 'PREVIOUS_EVENT' : ($type === 'league' ? 'DIVISION' : 'MANUAL');
        $accessType = str_contains($haystack, 'last chance') ? 'LAST_CHANCE_QUALIFIER' : ($type === 'qualifier' ? 'QUALIFIER' : (str_contains($haystack, 'invit') ? 'INVITATIONAL' : 'OPEN'));
        $advancementRule = $type === 'cup' || $type === 'final' ? 'WINNER_ONLY' : ($type === 'qualifier' || $type === 'world' ? 'TOP_N' : 'MANUAL_SELECTION');
        $drawPolicy = in_array($type, ['cup', 'final', 'world'], true) ? 'NOT_ALLOWED' : 'USE_TIEBREAKER';
        $rankingImpact = $type === 'friendly' ? 'NONE' : ($type === 'world' ? 'BONUS' : 'FULL');
        $deckPolicy = in_array($ruleset, ['draft', 'sealed'], true) ? 'REQUIRED_REGISTRATION' : 'OPTIONAL_REGISTRATION';
        $phaseStructure = str_contains($haystack, 'grupos') && (str_contains($haystack, 'top cut') || str_contains($haystack, 'final')) ? 'GROUPS_TOP_CUT_FINAL'
            : (str_contains($haystack, 'temporada') || str_contains($haystack, 'playoff') ? 'SEASON_PLAYOFF_FINAL'
            : (str_contains($haystack, 'top cut') ? 'TOP_CUT'
            : (str_contains($haystack, 'final') ? 'FINAL'
            : (str_contains($haystack, 'grupo') ? 'GROUPS' : 'NONE'))));
        $template = [
            'code' => $code,
            'label' => $label,
            'type' => $type,
            'execution_model' => $executionModel,
            'system' => $system,
            'participation' => $participation,
            'construction' => $construction,
            'rules' => $rules,
            'environment' => $environment,
            'level' => $level,
            'ruleset' => $ruleset,
            'match_format' => $matchFormat,
            'participants_per_side' => $matchFormat === '3v3' ? 3 : ($matchFormat === '2v2' ? 2 : 1),
            'schedule' => $schedule,
            'deck_pool' => $type === 'league' ? 'division' : 'all_active',
            'participation_code' => $participationCode,
            'construction_code' => $constructionCode,
            'environment_code' => $environmentCode,
            'level_code' => $levelCode,
            'event_flow' => $eventFlow,
            'participant_source' => $participantSource,
            'access_type' => $accessType,
            'advancement_rule' => $advancementRule,
            'draw_policy' => $drawPolicy,
            'ranking_impact' => $rankingImpact,
            'deck_policy' => $deckPolicy,
            'phase_structure' => $phaseStructure,
            'format' => strtolower($system . ' | ' . $construction . ' | ' . $rules),
            'notes' => "Tipo de evento: {$label}\nModalidad de competencia: {$system}\nTipo de acceso: {$accessType}\nModelo de ejecucion: {$executionModel}\nParticipacion: {$participation}\nConstruccion: {$construction}\nReglas: {$rules}\nEntorno: {$environment}\nNivel: {$level}",
        ];
        if ($code === 'clasificatorio_divisiones_3_duelos') {
            $template = array_merge($template, [
                'execution_model' => 'swiss',
                'system' => 'Suizo 3 duelos por deck',
                'schedule' => 'auto_swiss',
                'deck_pool' => 'all_active',
                'event_flow' => 'QUALIFIES_TO_EVENT',
                'participant_source' => 'OPEN_REGISTRATION',
                'access_type' => 'OPEN',
                'advancement_rule' => 'RANKING_THRESHOLD',
                'draw_policy' => 'USE_TIEBREAKER',
                'ranking_impact' => 'FULL',
                'deck_policy' => 'DECK_LOCK_EVENT',
                'phase_structure' => 'NONE',
                'best_of' => 3,
                'qualifier_duels_per_deck' => 3,
                'qualifier_matches_per_day' => 1,
                'notes' => "Clasificatorio para crear divisiones.\nParticipan todos los decks activos.\nCada deck juega maximo 3 duelos BO3.\nEl sistema debe evitar repetir rival/equipo al programar.\nEl ranking final sirve como base para rangos de division.",
            ]);
        }
        return ygo_correct_template_contract($template);
    }, $rows);
}

function ygo_ruleset_version_option_label(array $versionOption): string
{
    $system = trim((string) (($versionOption['ruleset_label'] ?? '') ?: ($versionOption['system'] ?? '')));
    $version = trim((string) (($versionOption['version_label'] ?? '') ?: ($versionOption['version'] ?? '')));
    if ($system === '') {
        return $version;
    }
    if ($version === '' || stripos($version, $system) === 0) {
        return $version !== '' ? $version : $system;
    }
    return $system . ' - ' . $version;
}

$eventDeckPoolOptions = ygo_catalog_label_options($catalogGroups['deck_pool']);
$competitionStatusOptions = ygo_catalog_label_options($catalogGroups['competition_status']);
$matchStatusOptions = ygo_catalog_label_options($catalogGroups['match_status']);
$schedulePatternOptions = ygo_catalog_label_options($catalogGroups['schedule_pattern']);
$useCases = [
    ['CU-01', 'A A', '2-0', 'Gana A', 'Dos victorias consecutivas'],
    ['CU-02', 'B B', '0-2', 'Gana B', 'Dos victorias consecutivas'],
    ['CU-03', 'E E', '0-0', 'Empate', 'Dos empates consecutivos'],
    ['CU-04', 'A B B', '1-2', 'Gana B', 'B cierra en D3'],
    ['CU-05', 'B A A', '2-1', 'Gana A', 'A cierra en D3'],
    ['CU-06', 'A B A B A', '3-2', 'Gana A', 'D5 desempata 2-2'],
];

function buildFixtures(array $decks): array
{
    $names = array_map(static fn(array $deck): string => $deck[1], $decks);
    if (count($names) % 2 === 1) {
        $names[] = 'DESCANSO';
    }
    $rounds = count($names) - 1;
    $half = count($names) / 2;
    $rotation = $names;
    $fixture = [];

    for ($round = 1; $round <= $rounds; $round++) {
        $matches = [];
        $rest = '';
        for ($i = 0; $i < $half; $i++) {
            $a = $rotation[$i];
            $b = $rotation[count($rotation) - 1 - $i];
            if ($a === 'DESCANSO' || $b === 'DESCANSO') {
                $rest = $a === 'DESCANSO' ? $b : $a;
                continue;
            }
            $matches[] = ['mesa' => count($matches) + 1, 'a' => $a, 'b' => $b, 'estado' => 'PENDIENTE'];
        }
        $fixture[] = ['ronda' => $round, 'descansa' => $rest, 'matches' => $matches];
        $fixed = array_shift($rotation);
        $last = array_pop($rotation);
        array_unshift($rotation, $fixed, $last);
    }
    return $fixture;
}

function evaluate_duel_server(array $values, int $bestOf = 3): array
{
    return ygo_evaluate_sequence($values, $bestOf);
}

function ygo_evaluate_duel_with_rule(PDO $pdo, int $matchId, array $values): array
{
    $stmt=$pdo->prepare('SELECT best_of FROM sx_ygo_matches WHERE id=? LIMIT 1');
    $stmt->execute([$matchId]);
    $bestOf=max(1,min(5,(int)($stmt->fetchColumn() ?: 3)));
    $ruleId=ygo_rule_id_for_match($pdo,$matchId);
    $conditions=[];
    if($ruleId>0){
        $stmt=$pdo->prepare("SELECT * FROM sx_ygo_game_rule_conditions WHERE game_rule_id=? AND active=1 AND operator='sequence_equals' ORDER BY sort_order,id");
        $stmt->execute([$ruleId]);$conditions=$stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    return ygo_evaluate_sequence($values,$bestOf,$conditions);
}

function ygo_competition_for_match(PDO $pdo, int $matchId): array
{
    $stmt = $pdo->prepare('
        SELECT c.*
        FROM sx_ygo_matches m
        JOIN sx_ygo_competitions c ON c.id = m.competition_id AND c.active = 1
        WHERE m.id = ?
        LIMIT 1
    ');
    $stmt->execute([$matchId]);
    return $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
}

function ygo_apply_event_model_to_result(PDO $pdo, int $matchId, array $evaluation, array $currentValues = []): array
{
    $competition = ygo_competition_for_match($pdo, $matchId);
    if (!$competition) {
        return $evaluation + ['model_effects' => []];
    }
    $effects = [];
    $drawPolicy = strtoupper((string) (($competition['draw_policy'] ?? '') ?: 'USE_TIEBREAKER'));
    $matchStmt = $pdo->prepare('SELECT phase FROM sx_ygo_matches WHERE id = ? LIMIT 1');
    $matchStmt->execute([$matchId]);
    $matchPhase = strtolower((string) ($matchStmt->fetchColumn() ?: ''));
    $competitionType = strtolower((string) (($competition['type'] ?? '') ?: ''));
    $executionModel = strtolower((string) (($competition['execution_model'] ?? '') ?: 'manual'));
    $workflow=ygo_workflow_load($pdo,$competition);
    $workflowTable=$workflow!==null && in_array($matchPhase,['swiss','groups','league'],true);
    $playedValues = [];
    foreach (array_slice($currentValues, 0, 5) as $value) {
        $value = (string) $value;
        if ($value === '') {
            break;
        }
        $playedValues[] = $value;
    }
    $playedCount = count($playedValues);
    $isGroupOrLeaguePhase = in_array($matchPhase, ['group', 'groups', 'league', 'round_robin', 'swiss', 'qualifier'], true)
        || in_array($executionModel, ['groups', 'round_robin', 'double_round_robin', 'league_points', 'swiss'], true)
        || in_array($competitionType, ['league', 'qualifier'], true);
    $isEliminationPhase = in_array($competitionType, ['cup', 'final'], true)
        || in_array($matchPhase, ['cup', 'final', 'top_cut', 'playoff', 'world', 'knockout', 'elimination'], true)
        || in_array($executionModel, ['single_elimination', 'double_elimination', 'top_cut', 'bracket', 'series', 'playoffs'], true);
    if (!$workflowTable && (in_array($competitionType, ['cup', 'final', 'world'], true)
        || in_array($matchPhase, ['cup', 'final', 'top_cut', 'playoff', 'world'], true)
        || in_array($executionModel, ['single_elimination', 'double_elimination', 'top_cut', 'bracket', 'series'], true))) {
        $drawPolicy = 'NOT_ALLOWED';
    }
    if($workflowTable){$isGroupOrLeaguePhase=true;$isEliminationPhase=false;}
    elseif($workflow!==null){$isGroupOrLeaguePhase=false;$isEliminationPhase=true;$drawPolicy='NOT_ALLOWED';}
    if ($isGroupOrLeaguePhase && !$isEliminationPhase && $drawPolicy === 'NOT_ALLOWED') {
        $effects[] = 'draw_policy:PHASE_ALLOWS_TABLE_DRAW';
    }
    if (($evaluation['status'] ?? '') === 'FINALIZADA'
        && in_array(($evaluation['winner'] ?? null), ['A', 'B'], true)
        && $playedCount === 4
        && in_array($drawPolicy, ['EXTRA_DUEL', 'EXTRA_MATCH', 'SUDDEN_DEATH', 'USE_TIEBREAKER'], true)
        && !$isGroupOrLeaguePhase) {
        $evaluation['status'] = 'REQUIERE D5';
        $evaluation['winner'] = null;
        $evaluation['validation'] = 'La modalidad requiere D5/desempate antes de cerrar este match.';
        $effects[] = 'd5_required:elimination_or_tiebreak';
    }
    if (($evaluation['winner'] ?? null) === 'E' && in_array($drawPolicy, ['NOT_ALLOWED', 'EXTRA_DUEL', 'EXTRA_MATCH', 'SUDDEN_DEATH', 'USE_TIEBREAKER'], true)) {
        $labels = [
            'NOT_ALLOWED' => 'Empate no permitido por la politica del evento',
            'EXTRA_DUEL' => 'Empate requiere duelo extra',
            'EXTRA_MATCH' => 'Empate requiere match extra',
            'SUDDEN_DEATH' => 'Empate requiere muerte subita',
            'USE_TIEBREAKER' => 'Empate requiere desempate',
        ];
        $evaluation['status'] = $drawPolicy === 'NOT_ALLOWED' ? 'INVALID' : 'REQUIERE DESEMPATE';
        $evaluation['winner'] = null;
        $evaluation['validation'] = $labels[$drawPolicy] ?? 'Empate requiere resolucion';
        $effects[] = 'draw_policy:' . $drawPolicy;
    }
    if (($evaluation['winner'] ?? null) === 'E' && $drawPolicy === 'ALLOWED' && ($competitionType === 'final' || $matchPhase === 'final')) {
        $evaluation['status'] = 'INVALID';
        $evaluation['winner'] = null;
        $evaluation['validation'] = 'Una final no puede cerrarse empatada.';
        $effects[] = 'final_draw_blocked';
    }
    $rankingImpact = strtoupper((string) (($competition['ranking_impact'] ?? '') ?: 'FULL'));
    $level = strtoupper((string) (($competition['level'] ?? '') ?: 'COMPETITIVE'));
    if ($rankingImpact === 'NONE' || in_array($level, ['CASUAL', 'BEGINNER', 'ACADEMY', 'PROMOTIONAL', 'EXHIBITION'], true)) {
        $effects[] = 'ranking_impact:NONE';
    }
    if (strtoupper((string) (($competition['deck_policy'] ?? '') ?: 'OPTIONAL_REGISTRATION')) === 'REQUIRED_REGISTRATION') {
        $effects[] = 'deck_policy:REQUIRED_REGISTRATION';
    }
    $evaluation['model_effects'] = $effects;
    return $evaluation;
}

function ygo_rule_id_for_match(PDO $pdo, int $matchId): int
{
    $stmt = $pdo->prepare("
        SELECT m.phase, m.game_rule_id AS match_rule_id, c.game_rule_id AS competition_rule_id
        FROM sx_ygo_matches m
        LEFT JOIN sx_ygo_competitions c ON c.id = m.competition_id AND c.active = 1
        WHERE m.id = ?
        LIMIT 1
    ");
    $stmt->execute([$matchId]);
    $match = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
    if (!$match) {
        return 0;
    }

    foreach (['match_rule_id', 'competition_rule_id'] as $column) {
        $ruleId = (int) ($match[$column] ?? 0);
        if ($ruleId > 0) {
            $ruleStmt = $pdo->prepare('SELECT id FROM sx_ygo_game_rules WHERE id = ? AND active = 1 LIMIT 1');
            $ruleStmt->execute([$ruleId]);
            $validRuleId = (int) ($ruleStmt->fetchColumn() ?: 0);
            if ($validRuleId > 0) {
                return $validRuleId;
            }
        }
    }

    $phase = (string) ($match['phase'] ?? '');
    $seasonId = ygo_current_season_id($pdo);
    $scopes = $phase === 'friendly'
        ? ['all_matches', 'friendly', 'match', 'general']
        : ['all_matches', 'match', 'general'];
    $placeholders = implode(',', array_fill(0, count($scopes), '?'));
    $fallbackStmt = $pdo->prepare("
        SELECT id
        FROM sx_ygo_game_rules
        WHERE season_id = ?
          AND active = 1
          AND scope IN ($placeholders)
        ORDER BY FIELD(scope, $placeholders), priority_order, id
        LIMIT 1
    ");
    $fallbackStmt->execute([$seasonId, ...$scopes, ...$scopes]);
    return (int) ($fallbackStmt->fetchColumn() ?: 0);
}

function ygo_default_game_rule_id(PDO $pdo, int $seasonId): ?int
{
    $stmt = $pdo->prepare("
        SELECT id
        FROM sx_ygo_game_rules
        WHERE season_id = ?
          AND active = 1
          AND scope IN ('event', 'all_matches', 'general')
        ORDER BY FIELD(scope, 'event', 'all_matches', 'general'), priority_order, id
        LIMIT 1
    ");
    $stmt->execute([$seasonId]);
    $ruleId = (int) ($stmt->fetchColumn() ?: 0);
    return $ruleId > 0 ? $ruleId : null;
}

function ygo_event_game_rule_id(PDO $pdo, int $seasonId, string $competitionType, string $executionModel): ?int
{
    return ygo_default_game_rule_id($pdo, $seasonId);
}

function ygo_valid_game_rule_id(PDO $pdo, int $seasonId, int $postedRuleId, ?int $fallbackRuleId = null, string $competitionType = 'tournament', string $executionModel = 'manual'): ?int
{
    if ($postedRuleId > 0) {
        $stmt = $pdo->prepare('SELECT id FROM sx_ygo_game_rules WHERE id = ? AND season_id = ? AND active = 1 LIMIT 1');
        $stmt->execute([$postedRuleId, $seasonId]);
        $ruleId = (int) ($stmt->fetchColumn() ?: 0);
        if ($ruleId > 0) {
            return $ruleId;
        }
    }
    return $fallbackRuleId ?: ygo_event_game_rule_id($pdo, $seasonId, $competitionType, $executionModel);
}

function ygo_valid_match_game_rule_id(PDO $pdo, int $seasonId, int $postedRuleId, string $phase): ?int
{
    if ($postedRuleId <= 0) {
        return null;
    }
    $allowedScopes = $phase === 'friendly'
        ? ['all_matches', 'friendly', 'match', 'general']
        : ['all_matches', 'match', 'general'];
    $placeholders = implode(',', array_fill(0, count($allowedScopes), '?'));
    $stmt = $pdo->prepare("
        SELECT id
        FROM sx_ygo_game_rules
        WHERE id = ?
          AND season_id = ?
          AND active = 1
          AND scope IN ($placeholders)
        LIMIT 1
    ");
    $stmt->execute([$postedRuleId, $seasonId, ...$allowedScopes]);
    $ruleId = (int) ($stmt->fetchColumn() ?: 0);
    return $ruleId > 0 ? $ruleId : null;
}

function ygo_can_edit(): bool
{
    return true;
}

function ygo_column_exists(PDO $pdo, string $table, string $column): bool
{
    $stmt = $pdo->prepare("
        SELECT COUNT(*)
        FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = ?
          AND COLUMN_NAME = ?
    ");
    $stmt->execute([$table, $column]);
    return (int) $stmt->fetchColumn() > 0;
}

function ygo_add_column(PDO $pdo, string $table, string $column, string $definition): void
{
    if (!ygo_column_exists($pdo, $table, $column)) {
        $pdo->exec("ALTER TABLE $table ADD COLUMN $column $definition");
    }
}

function ygo_table_exists(PDO $pdo, string $table): bool
{
    $stmt = $pdo->prepare("
        SELECT COUNT(*)
        FROM information_schema.TABLES
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = ?
    ");
    $stmt->execute([$table]);
    return (int) $stmt->fetchColumn() > 0;
}

function ygo_index_exists(PDO $pdo, string $table, string $index): bool
{
    $stmt = $pdo->prepare("
        SELECT COUNT(*)
        FROM information_schema.STATISTICS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = ?
          AND INDEX_NAME = ?
    ");
    $stmt->execute([$table, $index]);
    return (int) $stmt->fetchColumn() > 0;
}

function ygo_drop_index_if_exists(PDO $pdo, string $table, string $index): void
{
    if (ygo_index_exists($pdo, $table, $index)) {
        $pdo->exec("ALTER TABLE $table DROP INDEX $index");
    }
}

function ygo_default_settings(): array
{
    return [
        'allow_early_results' => '0',
        'allow_overdue_results' => '1',
        'result_date_guard' => '1',
        'alert_days_ahead' => '3',
        'valid_weekdays' => '1,2,3,4,5,6,0',
        'allowed_start_time' => '10:00',
        'allowed_end_time' => '22:00',
        'max_matches_per_day' => '3',
        'allow_special_days' => '0',
        'special_days' => '',
        'venues' => 'Station X Tulua',
        'stats_minimum_sample' => '3',
        'tier_sample_minimum' => '3',
        'best_debut_min_matches' => '3',
        'meta_presence_weight' => '50',
        'tier_zero_presence' => '35',
        'tier_zero_winrate' => '65',
        'tier_one_score' => '70',
        'tier_two_score' => '45',
        'elo_k_factor' => '32',
        'live_refresh_seconds' => '15',
        'notification_minutes_before' => '30',
    ];
}

function ygo_default_ui_labels(): array
{
    return [
        ['route.dashboard', 'Inicio / Dashboard', 'Temporada editable y estado general de la plataforma.'],
        ['route.buscar', 'Buscar', 'Busca decks, equipos, eventos, rondas y pantallas.'],
        ['route.clasificatorio', 'Clasificatorio', 'Fase previa con duelos, ranking y programacion oficial.'],
        ['route.divisiones', 'Divisiones', 'CRUD de divisiones y agrupacion de decks.'],
        ['route.calendario', 'Calendario', 'Agenda de eventos, jornadas y combates programados.'],
        ['route.resultados', 'Resultados', 'Registro validado de resultados por reglas persistidas.'],
        ['route.tabla', 'Tablas', 'Posiciones calculadas desde resultados reales.'],
        ['route.deck', 'Decks', 'Listado y filtros de decks guardados. La ficha se abre en una pantalla aparte.'],
        ['route.deck_ficha', 'Ficha del deck', 'Detalle, estadisticas, cartas, eventos y edicion del deck seleccionado.'],
        ['route.equipos', 'Equipos', 'Listado y filtros de equipos guardados. La ficha se abre en una pantalla aparte.'],
        ['route.equipo_ficha', 'Ficha del equipo', 'Crear, editar, asociar decks sin equipo y quitar decks asociados.'],
        ['route.estadisticas', 'Estadisticas y tops', 'Lectura competitiva calculada desde eventos, decks, equipos y resultados.'],
        ['route.torneos', 'Eventos competitivos', 'Tabla general de torneos, copas, ligas, finales y clasificatorios.'],
        ['route.crear_evento', 'Crear evento', 'Ficha para crear o editar eventos competitivos.'],
        ['route.copas', 'Copas', 'Gestion y seguimiento de copas y llaves.'],
        ['route.mundial', 'Mundial', 'Vista de mundial, grupos y eliminatorias.'],
        ['route.herramienta_lp', 'Calculadora LP', 'Herramienta de puntos de vida para duelos estilo YUGIOH Neuron.'],
        ['route.reglamento', 'Reglas custom', 'CRUD de reglas y condiciones que valida la plataforma al registrar resultados.'],
        ['route.reglas', 'Reglas', 'Administracion de reglas custom.'],
        ['route.gestion', 'Configuracion', 'Administracion central de eventos, formatos, reglas, calendario, resultados, decks y equipos.'],
        ['gestion.command_title', 'Centro de mando', 'Gestiona el flujo completo: clasificatorio vivo, divisiones oficiales, calendario y resultados.'],
        ['gestion.settings_title', 'Reglas de ejecucion de duelos', 'Controla si se pueden registrar resultados fuera de la fecha programada. Se guarda en MySQL por temporada.'],
        ['gestion.labels_title', 'Titulos y opciones dinamicas', 'Edita textos visibles y catálogos de opciones persistidos en base de datos.'],
    ];
}

function ygo_settings(PDO $pdo): array
{
    $seasonId = ygo_current_season_id($pdo);
    $settings = ygo_default_settings();
    $stmt = $pdo->prepare('SELECT setting_key, setting_value FROM sx_ygo_settings WHERE season_id = ?');
    $stmt->execute([$seasonId]);
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $settings[(string) $row['setting_key']] = (string) ($row['setting_value'] ?? '');
    }
    return $settings;
}

function ygo_save_setting(PDO $pdo, string $key, string $value): void
{
    $seasonId = ygo_current_season_id($pdo);
    $pdo->prepare('
        INSERT INTO sx_ygo_settings (season_id, setting_key, setting_value)
        VALUES (?, ?, ?)
        ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)
    ')->execute([$seasonId, $key, $value]);
}

function ygo_ensure_default_settings(PDO $pdo): void
{
    $seasonId = ygo_current_season_id($pdo);
    $stmt = $pdo->prepare('INSERT IGNORE INTO sx_ygo_settings (season_id, setting_key, setting_value) VALUES (?, ?, ?)');
    foreach (ygo_default_settings() as $key => $value) {
        $stmt->execute([$seasonId, $key, $value]);
    }
}

function ygo_ensure_default_ui_labels(PDO $pdo): void
{
    $seasonId = ygo_current_season_id($pdo);
    $stmt = $pdo->prepare('
        INSERT INTO sx_ygo_ui_labels (season_id, label_key, title, subtitle, active)
        VALUES (?, ?, ?, ?, 1)
        ON DUPLICATE KEY UPDATE label_key = label_key
    ');
    foreach (ygo_default_ui_labels() as $label) {
        $stmt->execute([$seasonId, $label[0], $label[1], $label[2]]);
    }
}

function ygo_ui_labels(PDO $pdo): array
{
    $seasonId = ygo_current_season_id($pdo);
    $stmt = $pdo->prepare('SELECT * FROM sx_ygo_ui_labels WHERE season_id IN (0, ?) ORDER BY label_key');
    $stmt->execute([$seasonId]);
    $labels = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $labels[(string) $row['label_key']] = [
            'id' => (int) $row['id'],
            'key' => (string) $row['label_key'],
            'title' => (string) ($row['title'] ?? ''),
            'subtitle' => (string) ($row['subtitle'] ?? ''),
            'active' => (int) ($row['active'] ?? 1),
        ];
    }
    foreach (ygo_default_ui_labels() as $label) {
        if (!isset($labels[$label[0]])) {
            $labels[$label[0]] = ['id' => 0, 'key' => $label[0], 'title' => $label[1], 'subtitle' => $label[2], 'active' => 1];
        }
    }
    ksort($labels);
    return $labels;
}

function ygo_setting_enabled(array $settings, string $key): bool
{
    return in_array((string) ($settings[$key] ?? '0'), ['1', 'true', 'yes', 'on'], true);
}

function ygo_validate_match_result_date(PDO $pdo, int $matchId): array
{
    $settings = ygo_settings($pdo);
    if (!ygo_setting_enabled($settings, 'result_date_guard')) {
        return ['ok' => true, 'state' => 'sin_guardia', 'message' => 'Guardia de fecha desactivada.'];
    }
    $stmt = $pdo->prepare('SELECT id, competition_id, scheduled_on, starts_at, status FROM sx_ygo_matches WHERE id = ? LIMIT 1');
    $stmt->execute([$matchId]);
    $match = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$match) {
        return ['ok' => false, 'state' => 'no_existe', 'message' => 'El duelo no existe en base de datos.'];
    }
    if (!empty($match['competition_id'])) {
        $gate = ygo_competition_gate($pdo, (int) $match['competition_id']);
        if (!$gate['can_execute']) {
            return [
                'ok' => false,
                'state' => 'competencia_bloqueada',
                'message' => $gate['message'],
                'dependencies' => $gate['dependencies'],
            ];
        }
    }
    $scheduledOn = (string) ($match['scheduled_on'] ?? '');
    if ($scheduledOn === '') {
        return ['ok' => false, 'state' => 'sin_fecha', 'message' => 'No se puede registrar resultado: el duelo no tiene fecha programada.'];
    }
    $today = (new DateTimeImmutable('today'))->format('Y-m-d');
    if ($scheduledOn > $today && !ygo_setting_enabled($settings, 'allow_early_results')) {
        return ['ok' => false, 'state' => 'fecha_futura', 'message' => 'No se puede ejecutar un duelo antes de su fecha programada. Activa "permitir adelantar duelos" si necesitas hacerlo.'];
    }
    if ($scheduledOn < $today && !ygo_setting_enabled($settings, 'allow_overdue_results')) {
        return ['ok' => false, 'state' => 'vencido_bloqueado', 'message' => 'El duelo esta vencido y la configuracion no permite registrar resultados atrasados.'];
    }
    return [
        'ok' => true,
        'state' => $scheduledOn < $today ? 'vencido' : ($scheduledOn === $today ? 'hoy' : 'adelantado_permitido'),
        'message' => 'Fecha validada.',
    ];
}

function ygo_validate_match_schedule(PDO $pdo, int $matchId, ?string $scheduledOn, ?string $startsAt, ?string $venue = null, array $participantIds = []): array
{
    $settings = ygo_settings($pdo);
    $date = trim((string) $scheduledOn);
    if ($date === '') {
        return ['ok' => true, 'state' => 'sin_fecha', 'message' => 'Sin fecha programada.'];
    }
    $parsed = DateTimeImmutable::createFromFormat('Y-m-d', $date);
    if (!$parsed || $parsed->format('Y-m-d') !== $date) {
        return ['ok' => false, 'state' => 'fecha_invalida', 'message' => 'La fecha programada no es valida.'];
    }
    $deckQuery=$pdo->prepare('SELECT deck_a_id,deck_b_id,deck_c_id,deck_d_id,deck_e_id,deck_f_id FROM sx_ygo_matches WHERE id=?');$deckQuery->execute([$matchId]);
    $deckRow=$participantIds?:$deckQuery->fetch(PDO::FETCH_ASSOC);
    if(!$deckRow && $matchId===0) { $deckRow=[];foreach(['a','b','c','d','e','f'] as $side)$deckRow[]=(int)($_POST['manual_deck_'.$side.'_id'] ?? 0); }
    $ffaCount=0;
    if(ygo_table_exists($pdo,'sx_ygo_ffa_matches')){
        $fq=$pdo->prepare('SELECT state_json,status FROM sx_ygo_ffa_matches WHERE scheduled_on=?');$fq->execute([$date]);
        foreach($fq->fetchAll(PDO::FETCH_ASSOC) as $f){if($f['status']!=='FINALIZADA')$ffaCount++;if(array_intersect(array_filter(array_map('intval',array_values($deckRow?:[]))),array_column(json_decode($f['state_json'],true)['players'],'deck_id')))return ['ok'=>false,'state'=>'deck_ocupado','message'=>'Un deck ya participa en una mesa multijugador en esta fecha.'];}
    }
    if($deckRow){$notBefore=ygo_deck_available_from($pdo,array_map('intval',array_values($deckRow)));if($notBefore!=='' && $date<$notBefore)return ['ok'=>false,'state'=>'deck_no_disponible','message'=>'Uno de los decks solo puede jugar desde '.$notBefore.'.'];}
    if($deckRow){
        $ids=array_values(array_filter(array_map('intval',array_values($deckRow))));
        if($ids){
            $other=$pdo->prepare('SELECT deck_a_id,deck_b_id,deck_c_id,deck_d_id,deck_e_id,deck_f_id FROM sx_ygo_matches WHERE scheduled_on=? AND id<>?');$other->execute([$date,$matchId]);
            foreach($other->fetchAll(PDO::FETCH_ASSOC) as $busy) if(array_intersect($ids,array_map('intval',array_values($busy))))return ['ok'=>false,'state'=>'deck_ocupado','message'=>'Un deck ya tiene otro combate en esta fecha.'];
        }
    }
    $weekday = $parsed->format('w');
    $validWeekdays = array_filter(array_map('trim', explode(',', (string) ($settings['valid_weekdays'] ?? ''))), static fn(string $value): bool => $value !== '');
    $specialDays = array_filter(array_map('trim', preg_split('/[\s,]+/', (string) ($settings['special_days'] ?? '')) ?: []), static fn(string $value): bool => $value !== '');
    $allowedByWeekday = !$validWeekdays || in_array($weekday, $validWeekdays, true);
    $allowedBySpecial = ygo_setting_enabled($settings, 'allow_special_days') && in_array($date, $specialDays, true);
    if (!$allowedByWeekday && !$allowedBySpecial) {
        return ['ok' => false, 'state' => 'dia_no_permitido', 'message' => 'El dia seleccionado no esta habilitado para jugar duelos.'];
    }
    $time = trim((string) $startsAt);
    if ($time !== '') {
        $time = substr($time, 0, 5);
        $start = substr((string) ($settings['allowed_start_time'] ?? '00:00'), 0, 5);
        $end = substr((string) ($settings['allowed_end_time'] ?? '23:59'), 0, 5);
        if ($start !== '' && $time < $start) {
            return ['ok' => false, 'state' => 'hora_temprana', 'message' => 'La hora esta antes del horario permitido.'];
        }
        if ($end !== '' && $time > $end) {
            return ['ok' => false, 'state' => 'hora_tardia', 'message' => 'La hora esta despues del horario permitido.'];
        }
    }
    $maxPerDay = max(1, (int) ($settings['max_matches_per_day'] ?? 3));
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM sx_ygo_matches WHERE scheduled_on = ? AND id <> ? AND status <> 'FINALIZADA'");
    $stmt->execute([$date, $matchId]);
    if ((int) $stmt->fetchColumn()+$ffaCount >= $maxPerDay && !ygo_schedule_can_displace_qualifier($pdo, $matchId, $date)) {
        return ['ok' => false, 'state' => 'capacidad_dia', 'message' => 'Ese dia ya alcanzo el maximo de combates configurado.'];
    }
    $venueValue = trim((string) $venue);
    $venues = array_filter(array_map('trim', preg_split('/[\r\n,]+/', (string) ($settings['venues'] ?? '')) ?: []), static fn(string $value): bool => $value !== '');
    if ($venueValue !== '' && $venues) {
        $normalizedVenues = array_map(static fn(string $value): string => strtolower($value), $venues);
        if (!in_array(strtolower($venueValue), $normalizedVenues, true)) {
            return ['ok' => false, 'state' => 'sede_no_permitida', 'message' => 'La sede seleccionada no esta en la lista de sedes disponibles.'];
        }
    }
    return ['ok' => true, 'state' => 'programable', 'message' => 'Fecha programable segun reglas.'];
}

function ygo_competition_dependencies(PDO $pdo): array
{
    $stmt = $pdo->prepare("
        SELECT dep.*, target.name AS competition_name, target.type AS competition_type,
               required.name AS depends_on_name, required.type AS depends_on_type,
               required.status AS depends_on_status
        FROM sx_ygo_competition_dependencies dep
        JOIN sx_ygo_competitions target ON target.id = dep.competition_id
        JOIN sx_ygo_competitions required ON required.id = dep.depends_on_competition_id
        WHERE dep.season_id = ? AND dep.active = 1
        ORDER BY target.name, required.name
    ");
    $stmt->execute([ygo_current_season_id($pdo)]);
    $grouped = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $grouped[(int) $row['competition_id']][] = $row;
    }
    return $grouped;
}

function ygo_competition_result_count(PDO $pdo, int $competitionId): int
{
    $stmt = $pdo->prepare("
        SELECT COUNT(*)
        FROM sx_ygo_matches m
        JOIN sx_ygo_match_results r ON r.match_id = m.id
        WHERE m.competition_id = ? AND r.result IS NOT NULL AND r.result <> ''
    ");
    $stmt->execute([$competitionId]);
    return (int) $stmt->fetchColumn();
}

function ygo_competition_honor_count(PDO $pdo, int $competitionId): int
{
    if (!ygo_table_exists($pdo, 'sx_ygo_honors')) {
        return 0;
    }
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM sx_ygo_honors WHERE competition_id = ?');
    $stmt->execute([$competitionId]);
    return (int) $stmt->fetchColumn();
}

function ygo_competition_division_count(PDO $pdo, int $competitionId): int
{
    if (!ygo_column_exists($pdo, 'sx_ygo_divisions', 'source_qualifier_id')) {
        return 0;
    }
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM sx_ygo_divisions WHERE source_qualifier_id = ? AND active = 1');
    $stmt->execute([$competitionId]);
    return (int) $stmt->fetchColumn();
}

function ygo_competition_dependency_satisfied(PDO $pdo, array $dependency, bool $completed, bool $hasResults, int $resultCount): bool
{
    $condition = (string) ($dependency['condition_type'] ?: 'completed_with_results');
    $requiredId = (int) $dependency['depends_on_competition_id'];
    if ($condition === 'completed') {
        return $completed;
    }
    if ($condition === 'has_results') {
        return $hasResults;
    }
    if ($condition === 'top_n') {
        $take = max(1, (int) ($dependency['take_count'] ?? 0) ?: 4);
        return $completed && count(ygo_competition_standings($pdo, $requiredId)) >= $take;
    }
    if ($condition === 'winner_to_final') {
        return $completed && (ygo_competition_honor_count($pdo, $requiredId) > 0 || count(ygo_competition_standings($pdo, $requiredId)) > 0);
    }
    if ($condition === 'defending_champion') {
        return ygo_competition_honor_count($pdo, $requiredId) > 0;
    }
    if ($condition === 'creates_divisions') {
        return $completed && ygo_competition_division_count($pdo, $requiredId) > 0;
    }
    return $completed && $hasResults;
}

function ygo_competition_gate(PDO $pdo, int $competitionId, bool $refresh=false): array
{
    static $cache = [];
    if($refresh)unset($cache[$competitionId]);
    if (isset($cache[$competitionId])) {
        return $cache[$competitionId];
    }
    $season = $pdo->prepare('SELECT s.* FROM sx_ygo_seasons s JOIN sx_ygo_competitions c ON c.season_id=s.id WHERE c.id=?');
    $season->execute([$competitionId]);
    $row = $season->fetch(PDO::FETCH_ASSOC);
    if (!$row || in_array($row['status'], ['completed','archived'], true) || (!empty($row['ends_on']) && $row['ends_on'] < date('Y-m-d'))) {
        return $cache[$competitionId] = ['can_execute'=>false,'message'=>'Temporada finalizada: evento cerrado, disponible solo para consulta.','dependencies'=>[]];
    }
    $q=$pdo->prepare('SELECT * FROM sx_ygo_competitions WHERE id=?');$q->execute([$competitionId]);$target=$q->fetch(PDO::FETCH_ASSOC);
    if($target && (ygo_admission_required($target) || ygo_admission_load($pdo,$target)!==null)){$admission=ygo_admission_view($pdo,$target);if(!$admission['ok'])return $cache[$competitionId]=['can_execute'=>false,'message'=>$admission['message'],'dependencies'=>[]];}
    $allDependencies = ygo_competition_dependencies($pdo);
    $dependencies = $allDependencies[$competitionId] ?? [];
    if (!$dependencies) {
        return $cache[$competitionId] = [
            'can_execute' => true,
            'message' => 'Sin prerrequisitos.',
            'dependencies' => [],
        ];
    }
    $blocked = [];
    $resolved = [];
    foreach ($dependencies as $dependency) {
        $requiredId = (int) $dependency['depends_on_competition_id'];
        $condition = (string) ($dependency['condition_type'] ?: 'completed_with_results');
        $status = strtolower((string) ($dependency['depends_on_status'] ?? 'draft'));
        $completed = in_array($status, ['completed', 'finalizada', 'finalizado', 'closed', 'archived'], true);
        $resultCount = ygo_competition_result_count($pdo, $requiredId);
        $hasResults = $resultCount > 0;
        $ok = ygo_competition_dependency_satisfied($pdo, $dependency, $completed, $hasResults, $resultCount);
        $item = [
            'id' => (int) $dependency['id'],
            'depends_on_competition_id' => $requiredId,
            'name' => (string) $dependency['depends_on_name'],
            'type' => (string) $dependency['depends_on_type'],
            'status' => (string) $dependency['depends_on_status'],
            'condition_type' => $condition,
            'take_count' => (int) ($dependency['take_count'] ?? 0),
            'result_count' => $resultCount,
            'ok' => $ok,
        ];
        $resolved[] = $item;
        if (!$ok) {
            $blocked[] = $item;
        }
    }
    return $cache[$competitionId] = [
        'can_execute' => !$blocked,
        'message' => !$blocked ? 'Prerrequisitos cumplidos.' : 'Evento bloqueado: primero debe cumplirse ' . implode(', ', array_map(static fn(array $item): string => $item['name'], $blocked)) . '.',
        'dependencies' => $resolved,
    ];
}

function ygo_ruleset_division_code(string $ruleset): ?string
{
    return [
        'tcg_advanced' => 'TCG_AVANZADO',
        'master_duel' => 'TCG_AVANZADO',
        'ocg' => 'OCG',
        'goat' => 'GOAT',
        'rush_duel' => 'RUSH_DUEL',
        'edison' => 'EDISON',
        'speed_duel' => 'SPEED_DUEL',
        'genesis' => 'GENESIS',
    ][$ruleset] ?? null;
}

function ygo_slugify(string $value): string
{
    $value = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value) ?: $value;
    $value = strtolower(preg_replace('/[^a-zA-Z0-9]+/', '-', $value) ?: '');
    return trim($value, '-') ?: 'evento';
}

function ygo_normalize_status_key(string $status): string
{
    $status = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $status) ?: $status;
    $status = strtolower(trim($status));
    $status = preg_replace('/[^a-z0-9]+/', '_', $status) ?: '';
    return trim($status, '_');
}

function ygo_event_status_labels(): array
{
    return [
        'pending_configuration' => 'Pendiente configuracion',
        'pending_event' => 'Programado',
        'active' => 'Activo',
        'completed' => 'Finalizado',
        'closed' => 'Cerrado por temporada',
        'cancelled' => 'Anulado',
    ];
}

function ygo_event_status_class(string $status): string
{
    $status = ygo_normalize_status_key($status);
    return [
        'draft' => 'state-pending-config',
        'pending_configuration' => 'state-pending-config',
        'pendiente_configuracion' => 'state-pending-config',
        'pending_event' => 'state-pending-event',
        'pendiente_evento' => 'state-pending-event',
        'active' => 'state-active',
        'activo' => 'state-active',
        'completed' => 'state-completed',
        'finalized' => 'state-completed',
        'finalizado' => 'state-completed',
        'finalizada' => 'state-completed',
        'closed' => 'state-completed',
        'cancelled' => 'state-cancelled',
        'canceled' => 'state-cancelled',
        'anulado' => 'state-cancelled',
        'anulada' => 'state-cancelled',
    ][$status] ?? 'state-pending-config';
}

function ygo_event_status_label(string $status): string
{
    $status = ygo_normalize_status_key($status);
    return [
        'draft' => 'Pendiente configuracion',
        'pending_configuration' => 'Pendiente configuracion',
        'pendiente_configuracion' => 'Pendiente configuracion',
        'pending_event' => 'Programado',
        'pendiente_evento' => 'Pendiente evento',
        'active' => 'Activo',
        'activo' => 'Activo',
        'completed' => 'Finalizado',
        'finalized' => 'Finalizado',
        'finalizado' => 'Finalizado',
        'finalizada' => 'Finalizado',
        'closed' => 'Cerrado por temporada',
        'cancelled' => 'Anulado',
        'canceled' => 'Anulado',
        'anulado' => 'Anulado',
        'anulada' => 'Anulado',
    ][$status] ?? ($status !== '' ? str_replace('_', ' ', $status) : 'Pendiente configuracion');
}

function ygo_record_competition_status_history(PDO $pdo, int $seasonId, int $competitionId, ?string $fromStatus, string $toStatus, string $action, array $payload = []): void
{
    if ($competitionId <= 0 || trim($toStatus) === '') {
        return;
    }
    $from = $fromStatus !== null ? ygo_normalize_status_key($fromStatus) : null;
    $to = ygo_normalize_status_key($toStatus);
    if ($from !== null && $from === $to) {
        return;
    }
    $pdo->prepare('
        INSERT INTO sx_ygo_competition_status_history (season_id, competition_id, from_status, to_status, action, payload)
        VALUES (?, ?, ?, ?, ?, ?)
    ')->execute([
        $seasonId,
        $competitionId,
        $from,
        $to,
        $action,
        json_encode($payload, JSON_UNESCAPED_UNICODE),
    ]);
}

function ygo_competition_ready_to_finish(PDO $pdo, int $competitionId): array
{
    $workflowQuery=$pdo->prepare('SELECT * FROM sx_ygo_competitions WHERE id=?');$workflowQuery->execute([$competitionId]);$workflowCompetition=$workflowQuery->fetch(PDO::FETCH_ASSOC);
    if($workflowCompetition){$workflow=ygo_workflow_load($pdo,$workflowCompetition);if($workflow!==null && empty($workflow['complete']))return ['ok'=>false,'total'=>count($workflow['history'])+count($workflow['pending']??[]),'finalized'=>count($workflow['history']),'pending'=>count($workflow['pending']??[]),'reason'=>'Completa el reglamento mediante Crear siguiente fase; terminar una ronda no termina el evento.'];}
    $stmt = $pdo->prepare("
        SELECT
            COUNT(*) AS total_matches,
            SUM(CASE WHEN status = 'FINALIZADA' THEN 1 ELSE 0 END) AS finalized_matches
        FROM sx_ygo_matches
        WHERE competition_id = ?
    ");
    $stmt->execute([$competitionId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
    $total = (int) ($row['total_matches'] ?? 0);
    $finalized = (int) ($row['finalized_matches'] ?? 0);
    return [
        'ok' => $total > 0 && $total === $finalized,
        'total' => $total,
        'finalized' => $finalized,
        'pending' => max(0, $total - $finalized),
    ];
}

function ygo_persist_competition_standings(PDO $pdo, int $seasonId, int $competitionId, string $reason = 'current'): void
{
    if ($competitionId <= 0) {
        return;
    }
    $phaseStmt = $pdo->prepare('SELECT DISTINCT phase FROM sx_ygo_matches WHERE competition_id = ? ORDER BY phase');
    $phaseStmt->execute([$competitionId]);
    $snapshotScopes = ['current' => null];
    foreach (array_filter(array_map('strval', $phaseStmt->fetchAll(PDO::FETCH_COLUMN))) as $phase) {
        $snapshotScopes['phase:' . $phase] = $phase;
    }
    foreach ($snapshotScopes as $snapshotKey => $phase) {
        $standings = ygo_competition_standings($pdo, $competitionId, $phase);
        if (!$standings) {
            $pdo->prepare('DELETE FROM sx_ygo_standing_snapshots WHERE competition_id = ? AND snapshot_key = ?')
                ->execute([$competitionId, $snapshotKey]);
            continue;
        }
        $maxRoundStmt = $phase === null
            ? $pdo->prepare('SELECT COALESCE(MAX(round_number), 0) FROM sx_ygo_matches WHERE competition_id = ?')
            : $pdo->prepare('SELECT COALESCE(MAX(round_number), 0) FROM sx_ygo_matches WHERE competition_id = ? AND phase = ?');
        $maxRoundStmt->execute($phase === null ? [$competitionId] : [$competitionId, $phase]);
        $roundNumber = (int) $maxRoundStmt->fetchColumn();
        $upsert = $pdo->prepare('
            INSERT INTO sx_ygo_standing_snapshots
                (season_id, competition_id, snapshot_key, round_number, deck_id, position, points, played, wins, draws, losses, duels_won, duels_lost, difference, payload)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE
                round_number=VALUES(round_number),
                position=VALUES(position),
                points=VALUES(points),
                played=VALUES(played),
                wins=VALUES(wins),
                draws=VALUES(draws),
                losses=VALUES(losses),
                duels_won=VALUES(duels_won),
                duels_lost=VALUES(duels_lost),
                difference=VALUES(difference),
                payload=VALUES(payload)
        ');
        $seenDeckIds = [];
        foreach ($standings as $index => $row) {
            $deckId = (int) ($row['id'] ?? 0);
            if ($deckId <= 0) {
                continue;
            }
            $seenDeckIds[] = $deckId;
            $upsert->execute([
                $seasonId,
                $competitionId,
                $snapshotKey,
                $roundNumber,
                $deckId,
                $index + 1,
                (int) ($row['pts'] ?? 0),
                (int) ($row['pj'] ?? 0),
                (int) ($row['pg'] ?? 0),
                (int) ($row['pe'] ?? 0),
                (int) ($row['pp'] ?? 0),
                (int) ($row['cg'] ?? 0),
                (int) ($row['cp'] ?? 0),
                (int) ($row['dp'] ?? 0),
                json_encode(['reason' => $reason, 'phase' => $phase, 'row' => $row], JSON_UNESCAPED_UNICODE),
            ]);
        }
        if ($seenDeckIds) {
            $placeholders = implode(',', array_fill(0, count($seenDeckIds), '?'));
            $pdo->prepare("DELETE FROM sx_ygo_standing_snapshots WHERE competition_id = ? AND snapshot_key = ? AND deck_id NOT IN ($placeholders)")
                ->execute(array_merge([$competitionId, $snapshotKey], $seenDeckIds));
        }
    }
}

function ygo_backfill_missing_standing_snapshots(PDO $pdo, int $seasonId): int
{
    if (!ygo_table_exists($pdo, 'sx_ygo_standing_snapshots')) {
        return 0;
    }
    $stmt = $pdo->prepare("
        SELECT c.id
        FROM sx_ygo_competitions c
        WHERE c.season_id = ? AND c.active = 1
          AND EXISTS (
              SELECT 1
              FROM sx_ygo_matches m
              JOIN sx_ygo_match_results r ON r.match_id = m.id
              WHERE m.competition_id = c.id AND r.result IS NOT NULL AND r.result <> ''
          )
          AND NOT EXISTS (
              SELECT 1
              FROM sx_ygo_standing_snapshots s
              WHERE s.competition_id = c.id AND s.snapshot_key = 'current'
          )
        ORDER BY c.id
        LIMIT 12
    ");
    $stmt->execute([$seasonId]);
    $ids = array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
    foreach ($ids as $competitionId) {
        ygo_persist_competition_standings($pdo, $seasonId, $competitionId, 'backfill_missing_snapshot');
    }
    return count($ids);
}

function ygo_rebuild_competition_calendar_events_from_matches(PDO $pdo, int $seasonId, int $competitionId, string $eventType): int
{
    if ($competitionId <= 0) {
        return 0;
    }
    $competitionStmt = $pdo->prepare('SELECT id, division_id, name, type FROM sx_ygo_competitions WHERE id = ? AND season_id = ? AND active = 1 LIMIT 1');
    $competitionStmt->execute([$competitionId, $seasonId]);
    $competition = $competitionStmt->fetch(PDO::FETCH_ASSOC);
    if (!$competition) {
        return 0;
    }
    $pdo->prepare('UPDATE sx_ygo_events SET active = 0 WHERE season_id = ? AND competition_id = ? AND event_type = ?')
        ->execute([$seasonId, $competitionId, $eventType]);
    $rows = $pdo->prepare("
        SELECT scheduled_on, MIN(starts_at) AS starts_at, COUNT(*) AS match_count, MIN(venue) AS venue
        FROM sx_ygo_matches
        WHERE competition_id = ? AND scheduled_on IS NOT NULL
        GROUP BY scheduled_on
        ORDER BY scheduled_on
    ");
    $rows->execute([$competitionId]);
    $insert = $pdo->prepare('
        INSERT INTO sx_ygo_events (season_id, competition_id, division_id, code, title, event_type, status, event_date, starts_at, venue, notes, active)
        VALUES (?, ?, ?, ?, ?, ?, "scheduled", ?, ?, ?, ?, 1)
        ON DUPLICATE KEY UPDATE title=VALUES(title), status=VALUES(status), event_date=VALUES(event_date), starts_at=VALUES(starts_at), venue=VALUES(venue), notes=VALUES(notes), active=1
    ');
    $created = 0;
    foreach ($rows->fetchAll(PDO::FETCH_ASSOC) as $index => $row) {
        $journey = $index + 1;
        $insert->execute([
            $seasonId,
            $competitionId,
            $competition['division_id'] !== null ? (int) $competition['division_id'] : null,
            'competition-' . $competitionId . '-' . $eventType . '-jornada-' . $journey,
            (string) ($competition['name'] ?? 'Evento') . ' - Jornada ' . $journey,
            $eventType,
            (string) $row['scheduled_on'],
            $row['starts_at'] ?: null,
            (string) ($row['venue'] ?: 'Station X Tulua'),
            (int) $row['match_count'] . ' combates programados desde duelos persistidos.',
        ]);
        $created++;
    }
    return $created;
}

function ygo_persist_competition_calendar_event(PDO $pdo, int $seasonId, int $competitionId): void
{
    if ($competitionId <= 0) {
        return;
    }
    $pdo->prepare("UPDATE sx_ygo_events SET active = 0 WHERE season_id = ? AND competition_id = ? AND code = ?")
        ->execute([$seasonId, $competitionId, 'competition-' . $competitionId . '-inicio']);
}

function ygo_is_pending_event_qualifier_candidate(array $competition): bool
{
    if ((string) ($competition['type'] ?? '') !== 'qualifier') {
        return false;
    }
    $status = ygo_normalize_status_key((string) ($competition['status'] ?? ''));
    if (in_array($status, ['pending_event', 'pendiente_evento'], true)) {
        return true;
    }
    $selectedDeckIds = $competition['selected_deck_ids'] ?? [];
    $selectedDeckCount = is_array($selectedDeckIds) ? count($selectedDeckIds) : 0;
    $deckCount = max($selectedDeckCount, (int) ($competition['deck_pool_count'] ?? 0));
    return $status === 'pending_configuration' && $deckCount >= 4;
}

function ygo_deck_delete_blocker(PDO $pdo, int $deckId): ?array
{
    if ($deckId <= 0) {
        return null;
    }
    $blockedStatuses = [
        'pending_configuration',
        'pendiente_configuracion',
        'pending_event',
        'pendiente_evento',
        'active',
        'activo',
        'completed',
        'finalizado',
        'finalizada',
        'cancelled',
        'canceled',
        'anulado',
        'anulada',
    ];
    $placeholders = implode(',', array_fill(0, count($blockedStatuses), '?'));
    $competitionStmt = $pdo->prepare("
        SELECT c.id, c.name, c.status, 'seleccionado en evento' AS source
        FROM sx_ygo_competition_decks cd
        JOIN sx_ygo_competitions c ON c.id = cd.competition_id
        WHERE cd.deck_id = ? AND cd.active = 1 AND c.active = 1
          AND LOWER(REPLACE(c.status, ' ', '_')) IN ($placeholders)
        ORDER BY FIELD(LOWER(REPLACE(c.status, ' ', '_')), 'active', 'activo', 'pending_event', 'pendiente_evento', 'pending_configuration', 'pendiente_configuracion', 'completed', 'finalizado', 'finalizada', 'cancelled', 'canceled', 'anulado', 'anulada'), c.id DESC
        LIMIT 1
    ");
    $competitionStmt->execute(array_merge([$deckId], $blockedStatuses));
    $competition = $competitionStmt->fetch(PDO::FETCH_ASSOC);
    if ($competition) {
        return $competition;
    }

    $matchStmt = $pdo->prepare("
        SELECT c.id, COALESCE(c.name, CONCAT('Combate #', m.id)) AS name, COALESCE(c.status, m.status) AS status, 'programado en combate' AS source
        FROM sx_ygo_matches m
        LEFT JOIN sx_ygo_competitions c ON c.id = m.competition_id
        WHERE ? IN (m.deck_a_id, m.deck_b_id, m.deck_c_id, m.deck_d_id, m.deck_e_id, m.deck_f_id)
          AND m.status <> 'CANCELADO'
          AND (
            c.id IS NULL
            OR (c.active = 1 AND LOWER(REPLACE(c.status, ' ', '_')) IN ($placeholders))
          )
        ORDER BY m.id DESC
        LIMIT 1
    ");
    $matchStmt->execute(array_merge([$deckId], $blockedStatuses));
    $match = $matchStmt->fetch(PDO::FETCH_ASSOC);
    return $match ?: null;
}

function ygo_generate_competition_code(PDO $pdo, int $seasonId, string $type, string $ruleset, string $name, int $ignoreId = 0): string
{
    $rows = $pdo->prepare("SELECT code FROM sx_ygo_competitions WHERE season_id = ? AND code LIKE 'YU-%' AND id <> ?");
    $rows->execute([$seasonId, $ignoreId]);
    $max = 0;
    foreach ($rows->fetchAll(PDO::FETCH_COLUMN) as $code) {
        if (preg_match('/^YU-(\d+)$/', (string) $code, $match)) {
            $max = max($max, (int) $match[1]);
        }
    }
    $counter = $max + 1;
    $stmt = $pdo->prepare('SELECT id FROM sx_ygo_competitions WHERE season_id = ? AND code = ? AND id <> ? LIMIT 1');
    while (true) {
        $candidate = 'YU-' . str_pad((string) $counter, 2, '0', STR_PAD_LEFT);
        $stmt->execute([$seasonId, $candidate, $ignoreId]);
        if (!$stmt->fetchColumn()) {
            return $candidate;
        }
        $counter++;
    }
}

function ygo_ensure_sequential_competition_codes(PDO $pdo, int $seasonId): int
{
    $rows = $pdo->prepare('SELECT id, code FROM sx_ygo_competitions WHERE season_id = ? ORDER BY id');
    $rows->execute([$seasonId]);
    $competitions = $rows->fetchAll(PDO::FETCH_ASSOC);
    $used = [];
    $max = 0;
    foreach ($competitions as $competition) {
        $code = (string) ($competition['code'] ?? '');
        if (preg_match('/^YU-(\d+)$/', $code, $match)) {
            $number = (int) $match[1];
            $max = max($max, $number);
            $used['YU-' . str_pad((string) $number, 2, '0', STR_PAD_LEFT)] = true;
        } elseif ($code !== '') {
            $used[$code] = true;
        }
    }

    $updated = 0;
    $counter = $max + 1;
    $update = $pdo->prepare('UPDATE sx_ygo_competitions SET code = ? WHERE id = ? AND season_id = ?');
    foreach ($competitions as $competition) {
        $currentCode = (string) ($competition['code'] ?? '');
        if (preg_match('/^YU-\d{2,}$/', $currentCode)) {
            continue;
        }
        do {
            $candidate = 'YU-' . str_pad((string) $counter, 2, '0', STR_PAD_LEFT);
            $counter++;
        } while (isset($used[$candidate]));
        $used[$candidate] = true;
        $update->execute([$candidate, (int) $competition['id'], $seasonId]);
        $updated++;
    }
    return $updated;
}

function ygo_store_event_logo_upload(): string
{
    $file = $_FILES['competition_logo'] ?? null;
    if (!is_array($file) || (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return '';
    }
    if ((int) ($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK || !is_uploaded_file((string) ($file['tmp_name'] ?? ''))) {
        return '';
    }
    $mime = mime_content_type((string) $file['tmp_name']) ?: '';
    $extensions = ['image/png' => 'png', 'image/jpeg' => 'jpg', 'image/webp' => 'webp', 'image/gif' => 'gif'];
    if (!isset($extensions[$mime])) {
        return '';
    }
    $dir = __DIR__ . '/assets/uploads/events';
    if (!is_dir($dir)) {
        mkdir($dir, 0775, true);
    }
    $name = 'event-' . date('YmdHis') . '-' . bin2hex(random_bytes(4)) . '.' . $extensions[$mime];
    $target = $dir . '/' . $name;
    if (!move_uploaded_file((string) $file['tmp_name'], $target)) {
        return '';
    }
    return './assets/uploads/events/' . $name;
}

function ygo_media_src(string $url): string
{
    $url = trim($url);
    if ($url === '') {
        return '';
    }
    if (preg_match('#^(?:https?:)?//#i', $url) || str_starts_with($url, 'data:')) {
        return $url;
    }
    $url = str_replace('\\', '/', $url);
    $url = preg_replace('#^/?(?:domains/[^/]+/public_html/)?yugioh/#i', './', $url) ?? $url;
    $url = preg_replace('#^/?public_html/yugioh/#i', './', $url) ?? $url;
    if (str_starts_with($url, '/yugioh/')) {
        return '.' . substr($url, strlen('/yugioh'));
    }
    if (str_starts_with($url, 'assets/')) {
        return './' . $url;
    }
    if (str_starts_with($url, '/assets/')) {
        return '.' . $url;
    }
    return $url;
}

function ygo_store_team_logo_upload(int $teamId): string
{
    $file = $_FILES['team_logo'] ?? null;
    if ($teamId <= 0 || !is_array($file) || (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return '';
    }
    if ((int) ($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK || !is_uploaded_file((string) ($file['tmp_name'] ?? ''))) {
        return '';
    }
    $mime = mime_content_type((string) $file['tmp_name']) ?: '';
    $extensions = ['image/png' => 'png', 'image/jpeg' => 'jpg', 'image/webp' => 'webp', 'image/gif' => 'gif'];
    if (!isset($extensions[$mime])) {
        return '';
    }
    $folder = 'team-' . $teamId;
    $dir = __DIR__ . '/assets/uploads/teams/' . $folder;
    if (!is_dir($dir)) {
        mkdir($dir, 0775, true);
    }
    $name = 'team-' . date('YmdHis') . '-' . bin2hex(random_bytes(4)) . '.' . $extensions[$mime];
    $target = $dir . '/' . $name;
    if (!move_uploaded_file((string) $file['tmp_name'], $target)) {
        return '';
    }
    return './assets/uploads/teams/' . $folder . '/' . $name;
}

function ygo_store_deck_logo_upload(int $deckId): string
{
    $file = $_FILES['deck_logo'] ?? null;
    if ($deckId <= 0 || !is_array($file) || (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return '';
    }
    if ((int) ($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK || !is_uploaded_file((string) ($file['tmp_name'] ?? ''))) {
        return '';
    }
    $mime = mime_content_type((string) $file['tmp_name']) ?: '';
    $extensions = ['image/png' => 'png', 'image/jpeg' => 'jpg', 'image/webp' => 'webp', 'image/gif' => 'gif'];
    if (!isset($extensions[$mime])) {
        return '';
    }
    $folder = 'deck-' . $deckId;
    $dir = __DIR__ . '/assets/uploads/decks/' . $folder;
    if (!is_dir($dir)) {
        mkdir($dir, 0775, true);
    }
    $name = 'deck-' . date('YmdHis') . '-' . bin2hex(random_bytes(4)) . '.' . $extensions[$mime];
    $target = $dir . '/' . $name;
    if (!move_uploaded_file((string) $file['tmp_name'], $target)) {
        return '';
    }
    return './assets/uploads/decks/' . $folder . '/' . $name;
}

function ygo_store_division_logo_upload(int $divisionId): string
{
    $file = $_FILES['division_logo'] ?? null;
    if ($divisionId <= 0 || !is_array($file) || (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return '';
    }
    if ((int) ($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK || !is_uploaded_file((string) ($file['tmp_name'] ?? ''))) {
        return '';
    }
    $mime = mime_content_type((string) $file['tmp_name']) ?: '';
    $extensions = ['image/png' => 'png', 'image/jpeg' => 'jpg', 'image/webp' => 'webp', 'image/gif' => 'gif'];
    if (!isset($extensions[$mime])) {
        return '';
    }
    $folder = 'division-' . $divisionId;
    $dir = __DIR__ . '/assets/uploads/divisions/' . $folder;
    if (!is_dir($dir)) {
        mkdir($dir, 0775, true);
    }
    $name = 'division-' . date('YmdHis') . '-' . bin2hex(random_bytes(4)) . '.' . $extensions[$mime];
    $target = $dir . '/' . $name;
    if (!move_uploaded_file((string) $file['tmp_name'], $target)) {
        return '';
    }
    return './assets/uploads/divisions/' . $folder . '/' . $name;
}

function ygo_event_logo_gallery(): array
{
    static $gallery = null;
    if ($gallery !== null) {
        return $gallery;
    }
    $typeDirs = [
        'qualifier' => ['clasificatorio'],
        'tournament' => ['torneo'],
        'league' => ['ligas'],
        'cup' => ['copa'],
        'world' => ['division1'],
        'final' => ['division2'],
        'special' => ['division3'],
        'friendly' => ['division4'],
    ];
    $base = __DIR__ . '/assets/imagenes eventos';
    $gallery = [];
    foreach ($typeDirs as $type => $dirs) {
        $items = [];
        foreach ($dirs as $dir) {
            $path = $base . '/' . $dir;
            $files = [];
            if (is_dir($path)) {
                foreach (scandir($path) ?: [] as $file) {
                    if (preg_match('/\.(png|jpe?g|webp|gif)$/i', $file)) {
                        $files[] = $file;
                    }
                }
            }
            sort($files, SORT_NATURAL | SORT_FLAG_CASE);
            foreach ($files as $file) {
                $items[] = [
                    'url' => './assets/imagenes eventos/' . $dir . '/' . basename((string) $file),
                    'label' => 'Logo ' . (count($items) + 1),
                ];
            }
        }
        $gallery[$type] = $items;
    }
    return $gallery;
}

function ygo_valid_event_gallery_logo(string $url, string $type): string
{
    $url = trim($url);
    if ($url === '') {
        return '';
    }
    $gallery = ygo_event_logo_gallery();
    $allowed = $gallery[$type] ?? [];
    foreach ($allowed as $item) {
        if (($item['url'] ?? '') === $url) {
            return $url;
        }
    }
    return '';
}

function ygo_competition_defaults(string $type, string $ruleset, string $name): array
{
    $preset = [
        'qualifier' => ['suizo clasificatorio', '1v1', 5, 1, 'auto_swiss', 'all_active', 'Dos victorias consecutivas o ventaja al cierre D4/D5', 'Menor marcador al cierre del match', 'D5 empatado o dos empates consecutivos'],
        'tournament' => ['torneo competitivo', '1v1', 5, 1, 'manual', 'all_active', 'Victoria por match segun reglamento', 'Derrota por match segun reglamento', 'Empate segun reglamento'],
        'league' => ['liga todos contra todos', '1v1', 5, 1, 'auto_round_robin', 'division', 'Victoria por match segun D1-D5', 'Derrota por match segun D1-D5', 'Empate valido solo cuando el reglamento lo permita'],
        'cup' => ['eliminacion directa', '1v1', 5, 1, 'auto_knockout', 'qualified', 'Ganar el match para avanzar de ronda', 'Perder el match elimina o envia a llave secundaria', 'Empate genera desempate completo'],
        'world' => ['grupos y eliminacion directa', '1v1', 5, 1, 'manual', 'top_cut', 'Victoria suma en grupos o avanza en llave', 'Derrota suma en grupos o elimina en llave', 'Empate permitido en grupos; desempate en eliminatoria'],
        'final' => ['final unica', '1v1', 5, 1, 'manual', 'top_cut', 'Ganar la serie final', 'Perder la serie final', 'Empate fuerza desempate completo'],
        'friendly' => ['amistoso rotativo', '1v1', 5, 1, 'friendly_rotation', 'all_active', 'Resultado amistoso registrado en historial', 'Resultado amistoso registrado en historial', 'Empate amistoso permitido'],
        'special' => ['evento especial editable', '1v1', 5, 1, 'manual', 'manual', 'Condicion definida por el evento', 'Condicion definida por el evento', 'Condicion definida por el evento'],
    ][$type] ?? ['evento editable', '1v1', 5, 1, 'manual', 'manual', 'Condicion definida por el evento', 'Condicion definida por el evento', 'Condicion definida por el evento'];

    return [
        'format' => $preset[0],
        'match_format' => $preset[1],
        'best_of' => $preset[2],
        'participants_per_side' => $preset[3],
        'schedule_strategy' => $preset[4],
        'deck_pool' => $preset[5],
        'victory_condition' => $preset[6],
        'defeat_condition' => $preset[7],
        'draw_condition' => $preset[8],
        'rules' => $name . ' usa reglamento ' . strtoupper(str_replace('_', ' ', $ruleset)) . ' y reglas deportivas editables por evento.',
    ];
}

function ygo_team_name_for_deck(string $deckName, ?string $archetype = null): string
{
    return ygo_team_profile_for_deck($deckName, $archetype)['name'];
}

function ygo_team_profile_for_deck(string $deckName, ?string $archetype = null): array
{
    $base = trim((string) ($archetype ?: $deckName));
    $base = preg_replace('/\s+/', ' ', $base) ?: 'Duel';
    $normalized = strtolower($base);
    $profiles = [
        'kewl tune' => ['Neon Pulse Syndicate', 'NRS', '#21f6ff', '#ffde59', 'NR'],
        'branded' => ['Crimson Exiles', 'CAL', '#dc2626', '#f7d154', 'CA'],
        'vanquish soul k9' => ['Soulhound Vanguard', 'SHK', '#2563eb', '#f97316', 'K9'],
        'mitsurugi pure' => ['Crimson Shogunate', 'MSG', '#ef4444', '#111827', 'MS'],
        'elfnote' => ['Ethereal Symphony', 'ENS', '#22c55e', '#f0abfc', 'ES'],
        'chaos ritual' => ['Abyssal Ascendants', 'CHA', '#7c3aed', '#facc15', 'CH'],
        'dracotail' => ['Drakecourt Dominion', 'DTD', '#0891b2', '#f59e0b', 'DD'],
        'doomz' => ['Graveborn Revenants', 'DZR', '#4b5563', '#a855f7', 'DZ'],
        'ryzeal' => ['Nova Reactor', 'RZO', '#facc15', '#2563eb', 'RZ'],
        'maliss' => ['Darknet Protocol', 'MLP', '#ec4899', '#111827', 'MP'],
        'tearlaments' => ['Abyssal Elegy', 'TAB', '#0ea5e9', '#312e81', 'TA'],
        'snake-eyes' => ['Crimson-Eyed Serpents', 'SCE', '#ef4444', '#f97316', 'SE'],
        'fire king' => ['Inferno Crown', 'IFK', '#f97316', '#fde047', 'IK'],
        'unchained' => ['Broken Chain Legion', 'UCD', '#991b1b', '#64748b', 'UD'],
        'purrely' => ['Celestial Companions', 'PNV', '#f9a8d4', '#38bdf8', 'PN'],
        'swordsoul' => ['Celestial Blades', 'SSD', '#60a5fa', '#e5e7eb', 'SD'],
        'sky striker' => ['Aerial Valkyries', 'SSV', '#3b82f6', '#ef4444', 'SV'],
        'doomz artmage' => ['Arcane Deathcallers', 'DZA', '#9333ea', '#f59e0b', 'DA'],
        'white forest azamina' => ['Frostveil Covenant', 'AFW', '#e0f2fe', '#22c55e', 'AF'],
        'kashtira pure' => ['Astral Conquerors', 'KSC', '#dc2626', '#111827', 'KC'],
        'demonherrero bystial' => ['Infernal Forgelords', 'BBS', '#f97316', '#1f2937', 'BB'],
        'cyber dragon k9' => ['Chrome Hound Unit', 'CHK', '#06b6d4', '#d1d5db', 'CK'],
        'invoked' => ['Arcane Summoners', 'IVA', '#8b5cf6', '#f8fafc', 'IA'],
        'mathmech' => ['Binary Singularity', 'MTS', '#14b8a6', '#facc15', 'MS'],
        'infernoble' => ['Flamebound Paladins', 'INO', '#dc2626', '#fbbf24', 'IO'],
        'memento' => ['Deathbound Archive', 'MMR', '#64748b', '#c084fc', 'MM'],
        'exosister' => ['Sacred Exorcist Order', 'EXC', '#f472b6', '#ffffff', 'EC'],
        'salamangreat' => ['Solar Inferno', 'SGI', '#ef4444', '#f97316', 'SI'],
        'k9 pure' => ['Blackwatch Unit', 'KBO', '#111827', '#38bdf8', 'K9'],
        'kashtira demonherrero' => ['Voidforge Legion', 'KFM', '#b91c1c', '#f97316', 'KF'],
        'resonator rda horus' => ['Crimson Sun Tyrants', 'HCR', '#b91c1c', '#facc15', 'HR'],
        'crystron' => ['Quantum Sentinels', 'CRN', '#38bdf8', '#94a3b8', 'CN'],
        'vanquish soul zoodiac' => ['Zodiac Brawlers', 'VZC', '#ef4444', '#22c55e', 'VZ'],
        'exodia' => ['Fivefold Ascension', 'FBO', '#d97706', '#fef3c7', 'FO'],
        'blue dragon' => ['Azure Sovereigns', 'ADL', '#2563eb', '#bfdbfe', 'AD'],
        'dark world' => ['Underworld Renegades', 'DWO', '#1f2937', '#a855f7', 'DW'],
        'resonator red dragon archfiend' => ['Scarlet Tyrants', 'RDD', '#dc2626', '#111827', 'RD'],
        'traptrix ragnaraika' => ['Thornveil Brood', 'TVS', '#16a34a', '#f472b6', 'TS'],
        'cyber dragon pure' => ['Ironfang Rebellion', 'CDA', '#06b6d4', '#e5e7eb', 'CD'],
        'six samurai' => ['Last Shogun Clan', 'SSC', '#dc2626', '#facc15', 'SC'],
        'barrera de hielo' => ['Glacial Wardens', 'FRB', '#38bdf8', '#ffffff', 'FB'],
        'volcanic' => ['Ashen Cataclysm', 'VLE', '#ef4444', '#f97316', 'VE'],
        'horus' => ['Sunborn Dynasty', 'HSE', '#f59e0b', '#fde68a', 'HS'],
    ];
    $profile = $profiles[$normalized] ?? [$base . ' Club', strtoupper(substr(preg_replace('/[^A-Za-z0-9]/', '', $base), 0, 3)) ?: 'YMD', '#36d8ff', '#ffd25f', strtoupper(substr($base, 0, 2))];
    return [
        'name' => $profile[0],
        'code' => $profile[1],
        'primary' => $profile[2],
        'secondary' => $profile[3],
        'symbol' => $profile[4],
    ];
}

function ygo_find_or_create_team(PDO $pdo, string $teamName, ?array $profile = null): int
{
    $stmt = $pdo->prepare('SELECT id FROM sx_ygo_teams WHERE name = ? LIMIT 1');
    $stmt->execute([$teamName]);
    $teamId = (int) $stmt->fetchColumn();
    if ($teamId > 0) {
        $pdo->prepare('UPDATE sx_ygo_teams SET active = 1 WHERE id = ?')->execute([$teamId]);
        if ($profile) {
            $pdo->prepare('UPDATE sx_ygo_teams SET short_code=?, primary_color=?, secondary_color=?, symbol=? WHERE id=?')
                ->execute([$profile['code'], $profile['primary'], $profile['secondary'], $profile['symbol'], $teamId]);
        }
        return $teamId;
    }

    $pdo->prepare("INSERT INTO sx_ygo_teams (name, city, short_code, primary_color, secondary_color, symbol, active) VALUES (?, 'Tulua', ?, ?, ?, ?, 1)")
        ->execute([$teamName, $profile['code'] ?? null, $profile['primary'] ?? null, $profile['secondary'] ?? null, $profile['symbol'] ?? null]);
    return (int) $pdo->lastInsertId();
}

function ygo_sync_deck_teams(PDO $pdo): int
{
    $rows = $pdo->query("
        SELECT id, name, archetype, team_id
        FROM sx_ygo_decks
        WHERE active = 1
        ORDER BY seed_rank, id
    ")->fetchAll(PDO::FETCH_ASSOC);

    $updated = 0;
    $update = $pdo->prepare('UPDATE sx_ygo_decks SET team_id = ? WHERE id = ?');
    foreach ($rows as $row) {
        if (!empty($row['team_id'])) {
            continue;
        }
        $profile = ygo_team_profile_for_deck((string) $row['name'], (string) ($row['archetype'] ?? ''));
        $teamId = ygo_find_or_create_team($pdo, $profile['name'], $profile);
        $update->execute([$teamId, (int) $row['id']]);
        $updated++;
    }

    $legacyTeamNames = ['Station X Titans', 'Tulua Duel Team', 'Meta Lab', 'Rogue Masters', 'Duel Academy'];
    $archive = $pdo->prepare("
        UPDATE sx_ygo_teams t
        SET t.active = 0
        WHERE t.name = ?
          AND NOT EXISTS (
              SELECT 1
              FROM sx_ygo_decks d
              WHERE d.team_id = t.id
                AND d.active = 1
          )
    ");
    foreach ($legacyTeamNames as $teamName) {
        $archive->execute([$teamName]);
    }
    $pdo->exec("
        UPDATE sx_ygo_teams t
        SET t.active = 0
        WHERE t.name LIKE 'Tulu% Duel Team'
          AND NOT EXISTS (
              SELECT 1
              FROM sx_ygo_decks d
              WHERE d.team_id = t.id
                AND d.active = 1
          )
    ");
    $pdo->exec("
        UPDATE sx_ygo_teams t
        SET t.active = 0
        WHERE t.name LIKE 'Equipo %'
          AND NOT EXISTS (
              SELECT 1
              FROM sx_ygo_decks d
              WHERE d.team_id = t.id
                AND d.active = 1
          )
    ");

    return $updated;
}

function ensure_ygo_tables(PDO $pdo): void
{
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS sx_ygo_seasons (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(120) NOT NULL,
            slug VARCHAR(80) NOT NULL UNIQUE,
            status VARCHAR(30) NOT NULL DEFAULT 'draft',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS sx_ygo_divisions (
            id INT AUTO_INCREMENT PRIMARY KEY,
            season_id INT NOT NULL,
            code VARCHAR(40) NOT NULL,
            name VARCHAR(120) NOT NULL,
            cup_name VARCHAR(120) NOT NULL,
            tier_order INT NOT NULL,
            deck_count INT NOT NULL DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY ux_ygo_division (season_id, code)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS sx_ygo_teams (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(160) NOT NULL,
            city VARCHAR(120) NULL,
            captain VARCHAR(120) NULL,
            contact VARCHAR(120) NULL,
            logo_url VARCHAR(500) NULL,
            active TINYINT(1) NOT NULL DEFAULT 1,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY ux_ygo_team_name (name)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS sx_ygo_decks (
            id INT AUTO_INCREMENT PRIMARY KEY,
            division_id INT NOT NULL,
            seed_rank INT NOT NULL,
            name VARCHAR(160) NOT NULL,
            reason VARCHAR(255) NULL,
            active TINYINT(1) NOT NULL DEFAULT 1,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY ux_ygo_deck (division_id, name)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    ygo_add_column($pdo, 'sx_ygo_decks', 'team_id', 'INT NULL AFTER division_id');
    ygo_add_column($pdo, 'sx_ygo_decks', 'archetype', 'VARCHAR(120) NULL AFTER name');
    ygo_add_column($pdo, 'sx_ygo_decks', 'style', 'VARCHAR(120) NULL AFTER archetype');
    ygo_add_column($pdo, 'sx_ygo_decks', 'key_cards', 'TEXT NULL AFTER reason');
    ygo_add_column($pdo, 'sx_ygo_decks', 'variations', 'TEXT NULL AFTER key_cards');
    ygo_add_column($pdo, 'sx_ygo_decks', 'notes', 'TEXT NULL AFTER variations');
    ygo_add_column($pdo, 'sx_ygo_decks', 'cover_url', 'VARCHAR(500) NULL AFTER notes');
    ygo_add_column($pdo, 'sx_ygo_decks', 'side_deck', 'TEXT NULL AFTER key_cards');
    ygo_add_column($pdo, 'sx_ygo_decks', 'logo_url', 'VARCHAR(500) NULL AFTER cover_url');
    ygo_add_column($pdo, 'sx_ygo_decks', 'active', 'TINYINT(1) NOT NULL DEFAULT 1 AFTER reason');
    try {
        $pdo->exec('ALTER TABLE sx_ygo_decks MODIFY division_id INT NULL');
    } catch (Throwable $error) {
        // Some shared hosts may block MODIFY when metadata is locked; existing installs continue with current schema.
    }
    ygo_add_column($pdo, 'sx_ygo_teams', 'short_code', 'VARCHAR(12) NULL AFTER name');
    ygo_add_column($pdo, 'sx_ygo_teams', 'primary_color', 'VARCHAR(30) NULL AFTER logo_url');
    ygo_add_column($pdo, 'sx_ygo_teams', 'secondary_color', 'VARCHAR(30) NULL AFTER primary_color');
    ygo_add_column($pdo, 'sx_ygo_teams', 'symbol', 'VARCHAR(24) NULL AFTER secondary_color');
    ygo_add_column($pdo, 'sx_ygo_teams', 'active', 'TINYINT(1) NOT NULL DEFAULT 1 AFTER logo_url');
    ygo_add_column($pdo, 'sx_ygo_divisions', 'accent', 'VARCHAR(30) NULL AFTER deck_count');
    ygo_add_column($pdo, 'sx_ygo_divisions', 'description', 'TEXT NULL AFTER accent');
    ygo_add_column($pdo, 'sx_ygo_divisions', 'starts_on', 'DATE NULL AFTER description');
    ygo_add_column($pdo, 'sx_ygo_divisions', 'ends_on', 'DATE NULL AFTER starts_on');
    ygo_add_column($pdo, 'sx_ygo_divisions', 'promotion_slots', 'INT NOT NULL DEFAULT 2 AFTER ends_on');
    ygo_add_column($pdo, 'sx_ygo_divisions', 'relegation_slots', 'INT NOT NULL DEFAULT 2 AFTER promotion_slots');
    ygo_add_column($pdo, 'sx_ygo_divisions', 'promotion_to_division_id', 'INT NULL AFTER relegation_slots');
    ygo_add_column($pdo, 'sx_ygo_divisions', 'relegation_to_division_id', 'INT NULL AFTER promotion_to_division_id');
    ygo_add_column($pdo, 'sx_ygo_divisions', 'league_competition_id', 'INT NULL AFTER relegation_to_division_id');
    ygo_add_column($pdo, 'sx_ygo_divisions', 'source_qualifier_id', 'INT NULL AFTER league_competition_id');
    ygo_add_column($pdo, 'sx_ygo_divisions', 'rank_from', 'INT NULL AFTER source_qualifier_id');
    ygo_add_column($pdo, 'sx_ygo_divisions', 'rank_to', 'INT NULL AFTER rank_from');
    ygo_add_column($pdo, 'sx_ygo_divisions', 'logo_url', 'VARCHAR(500) NULL AFTER rank_to');
    ygo_add_column($pdo, 'sx_ygo_divisions', 'active', 'TINYINT(1) NOT NULL DEFAULT 1 AFTER logo_url');
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS sx_ygo_competitions (
            id INT AUTO_INCREMENT PRIMARY KEY,
            season_id INT NOT NULL,
            division_id INT NULL,
            code VARCHAR(80) NOT NULL,
            name VARCHAR(160) NOT NULL,
            type VARCHAR(40) NOT NULL,
            format VARCHAR(80) NOT NULL DEFAULT 'editable',
            status VARCHAR(40) NOT NULL DEFAULT 'draft',
            starts_on DATE NULL,
            ends_on DATE NULL,
            accent VARCHAR(30) NULL,
            rules TEXT NULL,
            active TINYINT(1) NOT NULL DEFAULT 1,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY ux_ygo_competition (season_id, code)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    ygo_add_column($pdo, 'sx_ygo_competitions', 'scope', "VARCHAR(40) NOT NULL DEFAULT 'division' AFTER type");
    ygo_add_column($pdo, 'sx_ygo_competitions', 'match_format', "VARCHAR(40) NOT NULL DEFAULT '1v1' AFTER format");
    ygo_add_column($pdo, 'sx_ygo_competitions', 'best_of', 'INT NOT NULL DEFAULT 5 AFTER match_format');
    ygo_add_column($pdo, 'sx_ygo_competitions', 'participants_per_side', 'INT NOT NULL DEFAULT 1 AFTER best_of');
    ygo_add_column($pdo, 'sx_ygo_competitions', 'schedule_strategy', "VARCHAR(60) NOT NULL DEFAULT 'manual' AFTER participants_per_side");
    ygo_add_column($pdo, 'sx_ygo_competitions', 'schedule_pattern', "VARCHAR(40) NOT NULL DEFAULT 'consecutive' AFTER schedule_strategy");
    ygo_add_column($pdo, 'sx_ygo_competitions', 'schedule_interval_days', 'INT NOT NULL DEFAULT 1 AFTER schedule_pattern');
    ygo_add_column($pdo, 'sx_ygo_competitions', 'qualifier_matches_per_day', 'INT NOT NULL DEFAULT 1 AFTER schedule_interval_days');
    ygo_add_column($pdo, 'sx_ygo_competitions', 'game_rule_id', 'INT NULL AFTER qualifier_matches_per_day');
    ygo_add_column($pdo, 'sx_ygo_competitions', 'configuration_locked', 'TINYINT(1) NOT NULL DEFAULT 0 AFTER game_rule_id');
    ygo_add_column($pdo, 'sx_ygo_competitions', 'started_at', 'DATETIME NULL AFTER configuration_locked');
    ygo_add_column($pdo, 'sx_ygo_competitions', 'qualifier_duels_per_deck', 'INT NOT NULL DEFAULT 3 AFTER started_at');
    ygo_add_column($pdo, 'sx_ygo_competitions', 'event_ruleset', "VARCHAR(60) NOT NULL DEFAULT 'tcg_advanced' AFTER schedule_strategy");
    ygo_add_column($pdo, 'sx_ygo_competitions', 'ruleset_version', 'VARCHAR(180) NULL AFTER event_ruleset');
    ygo_add_column($pdo, 'sx_ygo_competitions', 'deck_pool', "VARCHAR(60) NOT NULL DEFAULT 'all_active' AFTER event_ruleset");
    ygo_add_column($pdo, 'sx_ygo_competitions', 'promotion_slots', 'INT NOT NULL DEFAULT 0 AFTER deck_pool');
    ygo_add_column($pdo, 'sx_ygo_competitions', 'relegation_slots', 'INT NOT NULL DEFAULT 0 AFTER promotion_slots');
    ygo_add_column($pdo, 'sx_ygo_competitions', 'promotion_to_division_id', 'INT NULL AFTER relegation_slots');
    ygo_add_column($pdo, 'sx_ygo_competitions', 'relegation_to_division_id', 'INT NULL AFTER promotion_to_division_id');
    ygo_add_column($pdo, 'sx_ygo_competitions', 'lock_division_moves', 'TINYINT(1) NOT NULL DEFAULT 0 AFTER relegation_to_division_id');
    ygo_add_column($pdo, 'sx_ygo_competitions', 'victory_condition', "VARCHAR(180) NULL AFTER deck_pool");
    ygo_add_column($pdo, 'sx_ygo_competitions', 'defeat_condition', "VARCHAR(180) NULL AFTER victory_condition");
    ygo_add_column($pdo, 'sx_ygo_competitions', 'draw_condition', "VARCHAR(180) NULL AFTER defeat_condition");
    ygo_add_column($pdo, 'sx_ygo_competitions', 'prompt_template', 'VARCHAR(80) NULL AFTER draw_condition');
    ygo_add_column($pdo, 'sx_ygo_competitions', 'event_system', 'VARCHAR(160) NULL AFTER prompt_template');
    ygo_add_column($pdo, 'sx_ygo_competitions', 'execution_model', 'VARCHAR(80) NULL AFTER event_system');
    ygo_add_column($pdo, 'sx_ygo_competitions', 'participation', 'VARCHAR(120) NULL AFTER execution_model');
    ygo_add_column($pdo, 'sx_ygo_competitions', 'construction', 'VARCHAR(120) NULL AFTER participation');
    ygo_add_column($pdo, 'sx_ygo_competitions', 'environment', 'VARCHAR(80) NULL AFTER construction');
    ygo_add_column($pdo, 'sx_ygo_competitions', 'level', 'VARCHAR(80) NULL AFTER environment');
    ygo_add_column($pdo, 'sx_ygo_competitions', 'event_flow', 'VARCHAR(80) NULL AFTER level');
    ygo_add_column($pdo, 'sx_ygo_competitions', 'participant_source', 'VARCHAR(80) NULL AFTER event_flow');
    ygo_add_column($pdo, 'sx_ygo_competitions', 'advancement_rule', 'VARCHAR(80) NULL AFTER participant_source');
    ygo_add_column($pdo, 'sx_ygo_competitions', 'draw_policy', 'VARCHAR(80) NULL AFTER advancement_rule');
    ygo_add_column($pdo, 'sx_ygo_competitions', 'ranking_impact', 'VARCHAR(80) NULL AFTER draw_policy');
    ygo_add_column($pdo, 'sx_ygo_competitions', 'deck_policy', 'VARCHAR(80) NULL AFTER ranking_impact');
    ygo_add_column($pdo, 'sx_ygo_competitions', 'access_type', 'VARCHAR(80) NULL AFTER deck_policy');
    ygo_add_column($pdo, 'sx_ygo_competitions', 'phase_structure', 'VARCHAR(120) NULL AFTER access_type');
    ygo_add_column($pdo, 'sx_ygo_competitions', 'logo_url', 'VARCHAR(500) NULL AFTER accent');
    ygo_add_column($pdo, 'sx_ygo_competitions', 'active', 'TINYINT(1) NOT NULL DEFAULT 1 AFTER rules');
    ygo_add_column($pdo, 'sx_ygo_competitions', 'updated_at', 'TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP AFTER created_at');
    $pdo->prepare("UPDATE sx_ygo_competitions SET status='pending_configuration' WHERE status='draft' AND active = 1")->execute();
    $pdo->prepare("UPDATE sx_ygo_competitions SET status='pending_configuration' WHERE LOWER(name) = 'test' AND active = 1")->execute();
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS sx_ygo_rulesets (
            id INT AUTO_INCREMENT PRIMARY KEY,
            season_id INT NOT NULL,
            code VARCHAR(80) NOT NULL,
            label VARCHAR(160) NOT NULL,
            format_family VARCHAR(80) NOT NULL DEFAULT 'custom',
            banlist_name VARCHAR(160) NULL,
            banlist_version VARCHAR(120) NULL,
            banlist_effective_on DATE NULL,
            match_format VARCHAR(40) NOT NULL DEFAULT '1v1',
            best_of INT NOT NULL DEFAULT 3,
            participants_per_side INT NOT NULL DEFAULT 1,
            schedule_strategy VARCHAR(60) NOT NULL DEFAULT 'manual',
            deck_pool VARCHAR(60) NOT NULL DEFAULT 'all_active',
            victory_condition VARCHAR(180) NULL,
            defeat_condition VARCHAR(180) NULL,
            draw_condition VARCHAR(180) NULL,
            notes TEXT NULL,
            active TINYINT(1) NOT NULL DEFAULT 1,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY ux_ygo_ruleset (season_id, code)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    ygo_add_column($pdo, 'sx_ygo_rulesets', 'active', 'TINYINT(1) NOT NULL DEFAULT 1 AFTER notes');
    ygo_add_column($pdo, 'sx_ygo_competition_decks', 'updated_at', 'TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP AFTER created_at');
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS sx_ygo_ruleset_versions (
            id INT AUTO_INCREMENT PRIMARY KEY,
            season_id INT NOT NULL,
            code VARCHAR(180) NOT NULL,
            category VARCHAR(40) NOT NULL DEFAULT 'banlist',
            system VARCHAR(40) NOT NULL,
            version_label VARCHAR(160) NOT NULL,
            date_label VARCHAR(80) NOT NULL,
            effective_on DATE NULL,
            sort_order INT NOT NULL DEFAULT 0,
            active TINYINT(1) NOT NULL DEFAULT 1,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY ux_ygo_ruleset_version (season_id, code)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    ygo_add_column($pdo, 'sx_ygo_ruleset_versions', 'active', 'TINYINT(1) NOT NULL DEFAULT 1 AFTER sort_order');
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS sx_ygo_match_types (
            id INT AUTO_INCREMENT PRIMARY KEY,
            code VARCHAR(40) NOT NULL,
            label VARCHAR(80) NOT NULL,
            participants_per_side INT NOT NULL DEFAULT 1,
            sort_order INT NOT NULL DEFAULT 0,
            active TINYINT(1) NOT NULL DEFAULT 1,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY ux_ygo_match_type (code)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    ygo_add_column($pdo, 'sx_ygo_match_types', 'active', 'TINYINT(1) NOT NULL DEFAULT 1 AFTER sort_order');
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS sx_ygo_competition_decks (
            id INT AUTO_INCREMENT PRIMARY KEY,
            competition_id INT NOT NULL,
            deck_id INT NOT NULL,
            sort_order INT NOT NULL DEFAULT 0,
            active TINYINT(1) NOT NULL DEFAULT 1,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY ux_ygo_competition_deck (competition_id, deck_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    ygo_add_column($pdo, 'sx_ygo_competition_decks', 'active', 'TINYINT(1) NOT NULL DEFAULT 1 AFTER sort_order');
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS sx_ygo_game_rules (
            id INT AUTO_INCREMENT PRIMARY KEY,
            season_id INT NOT NULL,
            code VARCHAR(80) NOT NULL,
            name VARCHAR(160) NOT NULL,
            rule_type VARCHAR(60) NOT NULL DEFAULT 'custom',
            scope VARCHAR(80) NOT NULL DEFAULT 'general',
            trigger_event VARCHAR(120) NULL,
            effect TEXT NULL,
            points_value INT NOT NULL DEFAULT 0,
            priority_order INT NOT NULL DEFAULT 100,
            active TINYINT(1) NOT NULL DEFAULT 1,
            notes TEXT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY ux_ygo_game_rule (season_id, code)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    ygo_add_column($pdo, 'sx_ygo_game_rules', 'active', 'TINYINT(1) NOT NULL DEFAULT 1 AFTER priority_order');
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS sx_ygo_game_rule_conditions (
            id INT AUTO_INCREMENT PRIMARY KEY,
            game_rule_id INT NOT NULL,
            condition_type VARCHAR(40) NOT NULL DEFAULT 'validation',
            condition_label VARCHAR(160) NOT NULL,
            operator VARCHAR(40) NOT NULL DEFAULT 'defined',
            target_value VARCHAR(160) NULL,
            result_value VARCHAR(20) NULL,
            status_value VARCHAR(40) NULL,
            score_value VARCHAR(40) NULL,
            failure_message VARCHAR(220) NULL,
            sort_order INT NOT NULL DEFAULT 100,
            active TINYINT(1) NOT NULL DEFAULT 1,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX ix_ygo_game_rule_condition_rule (game_rule_id, active, sort_order)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    ygo_add_column($pdo, 'sx_ygo_game_rule_conditions', 'active', 'TINYINT(1) NOT NULL DEFAULT 1 AFTER sort_order');
    ygo_add_column($pdo, 'sx_ygo_game_rule_conditions', 'result_value', 'VARCHAR(20) NULL AFTER target_value');
    ygo_add_column($pdo, 'sx_ygo_game_rule_conditions', 'status_value', 'VARCHAR(40) NULL AFTER result_value');
    ygo_add_column($pdo, 'sx_ygo_game_rule_conditions', 'score_value', 'VARCHAR(40) NULL AFTER status_value');
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS sx_ygo_competition_dependencies (
            id INT AUTO_INCREMENT PRIMARY KEY,
            season_id INT NOT NULL,
            competition_id INT NOT NULL,
            depends_on_competition_id INT NOT NULL,
            condition_type VARCHAR(40) NOT NULL DEFAULT 'completed_with_results',
            active TINYINT(1) NOT NULL DEFAULT 1,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY ux_ygo_competition_dependency (competition_id, depends_on_competition_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    ygo_add_column($pdo, 'sx_ygo_competition_dependencies', 'active', 'TINYINT(1) NOT NULL DEFAULT 1 AFTER condition_type');
    ygo_add_column($pdo, 'sx_ygo_competition_dependencies', 'take_count', 'INT NOT NULL DEFAULT 0 AFTER condition_type');
    ygo_add_column($pdo, 'sx_ygo_competition_dependencies', 'notes', 'VARCHAR(255) NULL AFTER take_count');
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS sx_ygo_matches (
            id INT AUTO_INCREMENT PRIMARY KEY,
            division_id INT NOT NULL,
            competition_id INT NULL,
            phase VARCHAR(40) NOT NULL DEFAULT 'league',
            round_number INT NOT NULL,
            table_number INT NOT NULL,
            deck_a_id INT NOT NULL,
            deck_b_id INT NOT NULL,
            status VARCHAR(30) NOT NULL DEFAULT 'PENDIENTE',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY ux_ygo_match (division_id, competition_id, phase, round_number, table_number)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    ygo_add_column($pdo, 'sx_ygo_matches', 'scheduled_on', 'DATE NULL AFTER status');
    ygo_add_column($pdo, 'sx_ygo_matches', 'starts_at', 'TIME NULL AFTER scheduled_on');
    ygo_add_column($pdo, 'sx_ygo_matches', 'venue', 'VARCHAR(160) NULL AFTER starts_at');
    ygo_add_column($pdo, 'sx_ygo_matches', 'judge', 'VARCHAR(120) NULL AFTER venue');
    ygo_add_column($pdo, 'sx_ygo_matches', 'notes', 'TEXT NULL AFTER judge');
    ygo_add_column($pdo, 'sx_ygo_matches', 'competition_id', 'INT NULL AFTER division_id');
    ygo_add_column($pdo, 'sx_ygo_matches', 'game_rule_id', 'INT NULL AFTER competition_id');
    ygo_add_column($pdo, 'sx_ygo_matches', 'match_format', "VARCHAR(40) NOT NULL DEFAULT '1v1' AFTER phase");
    ygo_add_column($pdo, 'sx_ygo_matches', 'best_of', 'INT NOT NULL DEFAULT 5 AFTER match_format');
    ygo_add_column($pdo, 'sx_ygo_matches', 'participants_per_side', 'INT NOT NULL DEFAULT 1 AFTER best_of');
    ygo_add_column($pdo, 'sx_ygo_matches', 'deck_c_id', 'INT NULL AFTER deck_b_id');
    ygo_add_column($pdo, 'sx_ygo_matches', 'deck_d_id', 'INT NULL AFTER deck_c_id');
    ygo_add_column($pdo, 'sx_ygo_matches', 'deck_e_id', 'INT NULL AFTER deck_d_id');
    ygo_add_column($pdo, 'sx_ygo_matches', 'deck_f_id', 'INT NULL AFTER deck_e_id');
    ygo_add_column($pdo, 'sx_ygo_matches', 'bracket_round_label', 'VARCHAR(80) NULL AFTER table_number');
    ygo_add_column($pdo, 'sx_ygo_matches', 'bracket_slot', 'INT NULL AFTER bracket_round_label');
    ygo_add_column($pdo, 'sx_ygo_matches', 'bracket_side', 'VARCHAR(20) NULL AFTER bracket_slot');
    ygo_add_column($pdo, 'sx_ygo_matches', 'display_order', 'INT NOT NULL DEFAULT 0 AFTER bracket_side');
    ygo_add_column($pdo, 'sx_ygo_matches', 'generation_number', 'INT NOT NULL DEFAULT 1 AFTER display_order');
    ygo_add_column($pdo, 'sx_ygo_matches', 'updated_at', 'TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP AFTER created_at');
    $pdo->exec('ALTER TABLE sx_ygo_matches MODIFY division_id INT NULL');
    $pdo->exec("UPDATE sx_ygo_matches SET display_order = ((round_number - 1) * 100) + table_number WHERE display_order = 0");
    $pdo->exec("UPDATE sx_ygo_matches SET bracket_round_label = CONCAT('Ronda ', round_number) WHERE bracket_round_label IS NULL OR bracket_round_label = ''");
    $pdo->exec("UPDATE sx_ygo_matches SET bracket_slot = table_number WHERE bracket_slot IS NULL");
    $pdo->exec("UPDATE sx_ygo_matches SET bracket_side = 'main' WHERE bracket_side IS NULL OR bracket_side = ''");
    if (ygo_index_exists($pdo, 'sx_ygo_matches', 'ux_ygo_match')) {
        $indexColumns = $pdo->prepare("
            SELECT GROUP_CONCAT(COLUMN_NAME ORDER BY SEQ_IN_INDEX)
            FROM information_schema.STATISTICS
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = 'sx_ygo_matches'
              AND INDEX_NAME = 'ux_ygo_match'
        ");
        $indexColumns->execute();
        if ((string) $indexColumns->fetchColumn() !== 'division_id,competition_id,phase,round_number,table_number') {
            ygo_drop_index_if_exists($pdo, 'sx_ygo_matches', 'ux_ygo_match');
            $pdo->exec('ALTER TABLE sx_ygo_matches ADD UNIQUE KEY ux_ygo_match (division_id, competition_id, phase, round_number, table_number)');
        }
    } else {
        $pdo->exec('ALTER TABLE sx_ygo_matches ADD UNIQUE KEY ux_ygo_match (division_id, competition_id, phase, round_number, table_number)');
    }
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS sx_ygo_events (
            id INT AUTO_INCREMENT PRIMARY KEY,
            season_id INT NOT NULL,
            competition_id INT NULL,
            division_id INT NULL,
            code VARCHAR(120) NOT NULL,
            title VARCHAR(180) NOT NULL,
            event_type VARCHAR(40) NOT NULL DEFAULT 'qualifier',
            status VARCHAR(40) NOT NULL DEFAULT 'scheduled',
            event_date DATE NOT NULL,
            starts_at TIME NULL,
            ends_at TIME NULL,
            venue VARCHAR(160) NULL,
            notes TEXT NULL,
            active TINYINT(1) NOT NULL DEFAULT 1,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY ux_ygo_event_code (season_id, code)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    $pdo->exec("
        UPDATE sx_ygo_events e
        SET e.active = 0
        WHERE e.competition_id IS NOT NULL
          AND e.active = 1
          AND (e.code LIKE 'competition-%-inicio' OR e.notes LIKE 'Evento creado desde ficha%' OR e.notes LIKE 'Evento activo persistido%')
          AND NOT EXISTS (
              SELECT 1
              FROM sx_ygo_matches m
              WHERE m.competition_id = e.competition_id
                AND m.scheduled_on = e.event_date
          )
    ");
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS sx_ygo_match_results (
            match_id INT PRIMARY KEY,
            d1 CHAR(1) NULL,
            d2 CHAR(1) NULL,
            d3 CHAR(1) NULL,
            d4 CHAR(1) NULL,
            d5 CHAR(1) NULL,
            result CHAR(1) NULL,
            score VARCHAR(10) NULL,
            validation_status VARCHAR(40) NOT NULL DEFAULT 'OK',
            notes TEXT NULL,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    ygo_add_column($pdo, 'sx_ygo_match_results', 'dice_roll', 'TEXT NULL AFTER notes');
    ygo_add_column($pdo, 'sx_ygo_match_results', 'turn_choice', 'VARCHAR(40) NULL AFTER dice_roll');
    ygo_add_column($pdo, 'sx_ygo_match_results', 'dice_winner', 'CHAR(1) NULL AFTER turn_choice');
    ygo_add_column($pdo, 'sx_ygo_match_results', 'turn_chosen_by', 'CHAR(1) NULL AFTER dice_winner');
    ygo_add_column($pdo, 'sx_ygo_match_results', 'turn_decision', 'VARCHAR(20) NULL AFTER turn_chosen_by');
    ygo_add_column($pdo, 'sx_ygo_match_results', 'starts_player', 'CHAR(1) NULL AFTER turn_decision');
    ygo_add_column($pdo, 'sx_ygo_match_results', 'duel_details', 'TEXT NULL AFTER starts_player');
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS sx_ygo_turn_audit (
            id BIGINT AUTO_INCREMENT PRIMARY KEY,
            match_id INT NOT NULL,
            duel_number TINYINT UNSIGNED NOT NULL,
            turn_number SMALLINT UNSIGNED NOT NULL,
            side CHAR(1) NOT NULL,
            ended_at VARCHAR(40) NULL,
            elapsed_seconds INT UNSIGNED NOT NULL DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY ux_ygo_turn_audit (match_id, duel_number, turn_number),
            KEY idx_ygo_turn_audit_match (match_id, duel_number, created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS sx_ygo_live_duel_state (
            match_id INT PRIMARY KEY,
            duel_number TINYINT UNSIGNED NOT NULL DEFAULT 1,
            revision BIGINT UNSIGNED NOT NULL DEFAULT 1,
            state_json LONGTEXT NOT NULL,
            updated_by VARCHAR(20) NOT NULL DEFAULT 'judge',
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            KEY idx_ygo_live_duel_updated (updated_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS sx_ygo_match_snapshots (
            id BIGINT AUTO_INCREMENT PRIMARY KEY,
            match_id INT NOT NULL,
            duel_number TINYINT UNSIGNED NOT NULL,
            side CHAR(1) NOT NULL,
            competition_id INT NULL,
            deck_id INT NULL,
            team_id INT NULL,
            player_id INT NULL,
            deck_name VARCHAR(160) NULL,
            archetype VARCHAR(160) NULL,
            variation TEXT NULL,
            team_name VARCHAR(160) NULL,
            ruleset_code VARCHAR(80) NULL,
            ruleset_version VARCHAR(180) NULL,
            division_name VARCHAR(160) NULL,
            decklist_json LONGTEXT NULL,
            data_origin VARCHAR(40) NOT NULL DEFAULT 'captured',
            captured_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY ux_ygo_match_snapshot (match_id, duel_number, side),
            KEY idx_ygo_match_snapshot_deck (deck_id, captured_at),
            KEY idx_ygo_match_snapshot_team (team_id, captured_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS sx_ygo_matchup_analyses (
            id BIGINT AUTO_INCREMENT PRIMARY KEY,
            deck_id INT NOT NULL,
            opponent_deck_id INT NOT NULL,
            deck_version VARCHAR(180) NOT NULL DEFAULT '',
            opponent_version VARCHAR(180) NOT NULL DEFAULT '',
            ruleset_version VARCHAR(180) NOT NULL DEFAULT '',
            strategic_score TINYINT NOT NULL DEFAULT 0,
            classification VARCHAR(30) NOT NULL DEFAULT 'EQUILIBRADO',
            reason TEXT NULL,
            key_advantages_json LONGTEXT NULL,
            key_disadvantages_json LONGTEXT NULL,
            choke_points_json LONGTEXT NULL,
            going_first_plan TEXT NULL,
            going_second_plan TEXT NULL,
            side_plan TEXT NULL,
            confidence VARCHAR(20) NOT NULL DEFAULT 'MEDIA',
            source_type VARCHAR(20) NOT NULL DEFAULT 'EDITORIAL',
            review_notes TEXT NULL,
            revision INT UNSIGNED NOT NULL DEFAULT 1,
            active TINYINT(1) NOT NULL DEFAULT 1,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY ux_ygo_matchup_analysis_scope (deck_id, opponent_deck_id, deck_version, opponent_version, ruleset_version),
            KEY idx_ygo_matchup_analysis_deck (deck_id, active, updated_at),
            KEY idx_ygo_matchup_analysis_opponent (opponent_deck_id, active)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS sx_ygo_matchup_analysis_history (
            id BIGINT AUTO_INCREMENT PRIMARY KEY,
            analysis_id BIGINT NOT NULL,
            deck_id INT NOT NULL,
            opponent_deck_id INT NOT NULL,
            revision INT UNSIGNED NOT NULL,
            snapshot_json LONGTEXT NOT NULL,
            change_reason TEXT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY ux_ygo_matchup_analysis_revision (analysis_id, revision),
            KEY idx_ygo_matchup_history_pair (deck_id, opponent_deck_id, created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS sx_ygo_deck_analysis_profiles (
            deck_id INT PRIMARY KEY,
            play_style VARCHAR(120) NULL,
            pace VARCHAR(30) NULL,
            complexity VARCHAR(30) NULL,
            primary_game_plan TEXT NULL,
            win_condition TEXT NULL,
            resource_engine TEXT NULL,
            opening_plan TEXT NULL,
            mid_game_plan TEXT NULL,
            late_game_plan TEXT NULL,
            going_first_plan TEXT NULL,
            going_second_plan TEXT NULL,
            side_deck_strategy TEXT NULL,
            strengths_json LONGTEXT NULL,
            weaknesses_json LONGTEXT NULL,
            strategy_tags_json LONGTEXT NULL,
            consistency_score TINYINT UNSIGNED NOT NULL DEFAULT 0,
            ceiling_score TINYINT UNSIGNED NOT NULL DEFAULT 0,
            resilience_score TINYINT UNSIGNED NOT NULL DEFAULT 0,
            interaction_score TINYINT UNSIGNED NOT NULL DEFAULT 0,
            recovery_score TINYINT UNSIGNED NOT NULL DEFAULT 0,
            first_score TINYINT UNSIGNED NOT NULL DEFAULT 0,
            second_score TINYINT UNSIGNED NOT NULL DEFAULT 0,
            confidence VARCHAR(20) NOT NULL DEFAULT 'MEDIA',
            source_type VARCHAR(20) NOT NULL DEFAULT 'EDITORIAL',
            analysis_notes TEXT NULL,
            revision INT UNSIGNED NOT NULL DEFAULT 1,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS sx_ygo_deck_analysis_profile_history (
            id BIGINT AUTO_INCREMENT PRIMARY KEY,
            deck_id INT NOT NULL,
            revision INT UNSIGNED NOT NULL,
            snapshot_json LONGTEXT NOT NULL,
            change_reason TEXT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY ux_ygo_deck_analysis_profile_revision (deck_id, revision),
            KEY idx_ygo_deck_analysis_profile_history (deck_id, created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS sx_ygo_medal_unlocks (
            id BIGINT AUTO_INCREMENT PRIMARY KEY,
            entity_type VARCHAR(20) NOT NULL,
            entity_id INT NOT NULL,
            medal_code VARCHAR(80) NOT NULL,
            medal_name VARCHAR(160) NOT NULL,
            rarity VARCHAR(20) NOT NULL DEFAULT 'common',
            secret TINYINT(1) NOT NULL DEFAULT 0,
            format_code VARCHAR(80) NULL,
            competition_id INT NULL,
            match_id INT NULL,
            duel_number TINYINT UNSIGNED NULL,
            rival_name VARCHAR(160) NULL,
            progress_value DECIMAL(14,2) NOT NULL DEFAULT 0,
            unlocked_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY ux_ygo_medal_unlock (entity_type, entity_id, medal_code, format_code),
            KEY idx_ygo_medal_first (medal_code, unlocked_at),
            KEY idx_ygo_medal_event (competition_id, match_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    if ((int) $pdo->query("SELECT COUNT(*) FROM sx_ygo_medal_unlocks WHERE format_code IS NULL")->fetchColumn() > 0) {
        $pdo->exec("DELETE newer FROM sx_ygo_medal_unlocks newer JOIN sx_ygo_medal_unlocks older ON older.entity_type=newer.entity_type AND older.entity_id=newer.entity_id AND older.medal_code=newer.medal_code AND COALESCE(older.format_code,'')=COALESCE(newer.format_code,'') AND older.id < newer.id");
        $pdo->exec("UPDATE sx_ygo_medal_unlocks SET format_code='' WHERE format_code IS NULL");
    }
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS sx_ygo_checkins (
            id BIGINT AUTO_INCREMENT PRIMARY KEY,
            competition_id INT NULL,
            match_id INT NULL,
            entity_type VARCHAR(20) NOT NULL,
            entity_id INT NULL,
            display_name VARCHAR(160) NOT NULL,
            checkin_code VARCHAR(80) NULL,
            source VARCHAR(30) NOT NULL DEFAULT 'manual',
            checked_in_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY ux_ygo_checkin (competition_id, match_id, entity_type, entity_id, display_name),
            KEY idx_ygo_checkin_event (competition_id, checked_in_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS sx_ygo_meta_history (
            id BIGINT AUTO_INCREMENT PRIMARY KEY,
            snapshot_on DATE NOT NULL,
            season_id INT NULL,
            format_code VARCHAR(80) NOT NULL,
            group_type VARCHAR(20) NOT NULL DEFAULT 'deck',
            group_key VARCHAR(180) NOT NULL,
            appearances INT NOT NULL DEFAULT 0,
            matches INT NOT NULL DEFAULT 0,
            wins INT NOT NULL DEFAULT 0,
            win_rate DECIMAL(6,2) NOT NULL DEFAULT 0,
            presence_share DECIMAL(6,2) NOT NULL DEFAULT 0,
            meta_score DECIMAL(6,2) NOT NULL DEFAULT 0,
            tier VARCHAR(20) NOT NULL DEFAULT 'Tier 3',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY ux_ygo_meta_history (snapshot_on, season_id, format_code, group_type, group_key),
            KEY idx_ygo_meta_history_scope (season_id, format_code, snapshot_on)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS sx_ygo_elo_history (
            id BIGINT AUTO_INCREMENT PRIMARY KEY,
            match_id INT NOT NULL,
            competition_id INT NULL,
            deck_id INT NOT NULL,
            opponent_deck_id INT NOT NULL,
            played_on DATE NOT NULL,
            rating_before DECIMAL(10,2) NOT NULL,
            rating_after DECIMAL(10,2) NOT NULL,
            rating_delta DECIMAL(10,2) NOT NULL,
            result_code CHAR(1) NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY ux_ygo_elo_match_deck (match_id, deck_id),
            KEY idx_ygo_elo_deck_date (deck_id, played_on, match_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS sx_ygo_global_ranking_history (
            id BIGINT AUTO_INCREMENT PRIMARY KEY,
            snapshot_on DATE NOT NULL,
            season_id INT NOT NULL DEFAULT 0,
            scope_key VARCHAR(40) NOT NULL DEFAULT 'global',
            deck_id INT NOT NULL,
            position INT NOT NULL,
            previous_position INT NULL,
            position_delta INT NOT NULL DEFAULT 0,
            points INT NOT NULL DEFAULT 0,
            played INT NOT NULL DEFAULT 0,
            wins INT NOT NULL DEFAULT 0,
            draws INT NOT NULL DEFAULT 0,
            losses INT NOT NULL DEFAULT 0,
            duels_won INT NOT NULL DEFAULT 0,
            duels_drawn INT NOT NULL DEFAULT 0,
            duels_lost INT NOT NULL DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY ux_ygo_global_rank_history (snapshot_on, season_id, scope_key, deck_id),
            KEY idx_ygo_global_rank_deck (deck_id, season_id, snapshot_on),
            KEY idx_ygo_global_rank_position (season_id, snapshot_on, position)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS sx_ygo_event_summaries (
            competition_id INT PRIMARY KEY,
            season_id INT NOT NULL DEFAULT 0,
            finalized_on DATETIME NULL,
            champion_deck_id INT NULL,
            runner_up_deck_id INT NULL,
            champion_team_id INT NULL,
            runner_up_team_id INT NULL,
            standings_json LONGTEXT NOT NULL,
            metagame_json LONGTEXT NOT NULL,
            statistics_json LONGTEXT NOT NULL,
            summary_hash CHAR(64) NOT NULL,
            generated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            KEY idx_ygo_event_summary_season (season_id, finalized_on)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS sx_ygo_medal_progress (
            entity_type VARCHAR(20) NOT NULL,
            entity_id INT NOT NULL,
            medal_code VARCHAR(80) NOT NULL,
            medal_name VARCHAR(160) NOT NULL,
            rarity VARCHAR(20) NOT NULL,
            secret TINYINT(1) NOT NULL DEFAULT 0,
            current_value DECIMAL(14,2) NOT NULL DEFAULT 0,
            target_value DECIMAL(14,2) NOT NULL DEFAULT 1,
            unlocked TINYINT(1) NOT NULL DEFAULT 0,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (entity_type, entity_id, medal_code)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS sx_ygo_sync_requests (
            request_id VARCHAR(80) PRIMARY KEY,
            match_id INT NOT NULL,
            payload_hash CHAR(64) NOT NULL,
            result_code VARCHAR(80) NOT NULL DEFAULT 'resultado',
            synced_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            KEY idx_ygo_sync_match (match_id, synced_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    ygo_add_column($pdo, 'sx_ygo_match_snapshots', 'decklist_hash', "CHAR(64) NULL AFTER decklist_json");
    ygo_add_column($pdo, 'sx_ygo_match_snapshots', 'snapshot_status', "VARCHAR(20) NOT NULL DEFAULT 'exact' AFTER data_origin");
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS sx_ygo_audit_log (
            id INT AUTO_INCREMENT PRIMARY KEY,
            entity_type VARCHAR(60) NOT NULL,
            entity_id INT NULL,
            action VARCHAR(80) NOT NULL,
            payload TEXT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS sx_ygo_competition_status_history (
            id INT AUTO_INCREMENT PRIMARY KEY,
            season_id INT NOT NULL,
            competition_id INT NOT NULL,
            from_status VARCHAR(40) NULL,
            to_status VARCHAR(40) NOT NULL,
            action VARCHAR(80) NOT NULL,
            payload TEXT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            KEY idx_ygo_status_history_competition (competition_id, created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS sx_ygo_standing_snapshots (
            id INT AUTO_INCREMENT PRIMARY KEY,
            season_id INT NOT NULL,
            competition_id INT NOT NULL,
            snapshot_key VARCHAR(40) NOT NULL DEFAULT 'current',
            round_number INT NOT NULL DEFAULT 0,
            deck_id INT NOT NULL,
            position INT NOT NULL,
            points INT NOT NULL DEFAULT 0,
            played INT NOT NULL DEFAULT 0,
            wins INT NOT NULL DEFAULT 0,
            draws INT NOT NULL DEFAULT 0,
            losses INT NOT NULL DEFAULT 0,
            duels_won INT NOT NULL DEFAULT 0,
            duels_lost INT NOT NULL DEFAULT 0,
            difference INT NOT NULL DEFAULT 0,
            payload TEXT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY ux_ygo_standing_snapshot (competition_id, snapshot_key, deck_id),
            KEY idx_ygo_standing_snapshot_competition (competition_id, position)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    $historyCount = (int) $pdo->query('SELECT COUNT(*) FROM sx_ygo_competition_status_history')->fetchColumn();
    if ($historyCount === 0) {
        $pdo->exec("
            INSERT INTO sx_ygo_competition_status_history (season_id, competition_id, from_status, to_status, action, payload)
            SELECT season_id, id, NULL, status, 'bootstrap_status_snapshot', '{\"source\":\"ensure_ygo_tables\"}'
            FROM sx_ygo_competitions
            WHERE active = 1
        ");
    }
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS sx_ygo_deck_cards (
            id INT AUTO_INCREMENT PRIMARY KEY,
            deck_id INT NOT NULL,
            card_name VARCHAR(160) NOT NULL,
            role VARCHAR(80) NULL,
            quantity INT NOT NULL DEFAULT 1,
            section VARCHAR(40) NOT NULL DEFAULT 'main',
            notes TEXT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    ygo_add_column($pdo, 'sx_ygo_deck_cards', 'ygoprodeck_id', 'INT NULL AFTER notes');
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS sx_ygo_deck_media (
            id INT AUTO_INCREMENT PRIMARY KEY,
            deck_id INT NOT NULL,
            media_type VARCHAR(40) NOT NULL DEFAULT 'image',
            url VARCHAR(500) NOT NULL,
            title VARCHAR(160) NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS sx_ygo_honors (
            id INT AUTO_INCREMENT PRIMARY KEY,
            season_id INT NOT NULL,
            competition_id INT NULL,
            champion_deck_id INT NULL,
            runner_up_deck_id INT NULL,
            title VARCHAR(160) NOT NULL,
            notes TEXT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS sx_ygo_players (
            id INT AUTO_INCREMENT PRIMARY KEY,
            team_id INT NULL,
            display_name VARCHAR(160) NOT NULL,
            alias VARCHAR(120) NULL,
            active TINYINT(1) NOT NULL DEFAULT 1,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            KEY idx_ygo_players_team (team_id, active)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS sx_ygo_player_decks (
            player_id INT NOT NULL,
            deck_id INT NOT NULL,
            active TINYINT(1) NOT NULL DEFAULT 1,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (player_id, deck_id),
            KEY idx_ygo_player_decks_deck (deck_id, active)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS sx_ygo_logo_history (
            id INT AUTO_INCREMENT PRIMARY KEY,
            entity_type VARCHAR(30) NOT NULL,
            entity_id INT NOT NULL,
            logo_url VARCHAR(500) NOT NULL,
            source VARCHAR(30) NOT NULL DEFAULT 'upload',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            KEY idx_ygo_logo_history_entity (entity_type, entity_id, created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    $pdo->exec("INSERT INTO sx_ygo_logo_history (entity_type, entity_id, logo_url, source)
        SELECT 'deck', d.id, d.cover_url, 'current' FROM sx_ygo_decks d
        WHERE d.cover_url IS NOT NULL AND d.cover_url <> ''
          AND NOT EXISTS (SELECT 1 FROM sx_ygo_logo_history h WHERE h.entity_type='deck' AND h.entity_id=d.id AND h.logo_url=d.cover_url)");
    $pdo->exec("INSERT INTO sx_ygo_logo_history (entity_type, entity_id, logo_url, source)
        SELECT 'team', t.id, t.logo_url, 'current' FROM sx_ygo_teams t
        WHERE t.logo_url IS NOT NULL AND t.logo_url <> ''
          AND NOT EXISTS (SELECT 1 FROM sx_ygo_logo_history h WHERE h.entity_type='team' AND h.entity_id=t.id AND h.logo_url=t.logo_url)");
    $pdo->exec("INSERT INTO sx_ygo_logo_history (entity_type, entity_id, logo_url, source)
        SELECT 'competition', c.id, c.logo_url, 'current' FROM sx_ygo_competitions c
        WHERE c.logo_url IS NOT NULL AND c.logo_url <> ''
          AND NOT EXISTS (SELECT 1 FROM sx_ygo_logo_history h WHERE h.entity_type='competition' AND h.entity_id=c.id AND h.logo_url=c.logo_url)");
    $historyExists = $pdo->prepare('SELECT id FROM sx_ygo_logo_history WHERE entity_type=? AND entity_id=? AND logo_url=? LIMIT 1');
    $historyImport = $pdo->prepare('INSERT INTO sx_ygo_logo_history (entity_type, entity_id, logo_url, source, created_at) VALUES (?, ?, ?, "imported", FROM_UNIXTIME(?))');
    foreach (['deck' => 'decks', 'team' => 'teams'] as $entityType => $folderName) {
        $baseDir = __DIR__ . '/assets/uploads/' . $folderName;
        if (!is_dir($baseDir)) {
            continue;
        }
        foreach (glob($baseDir . '/' . $entityType . '-*', GLOB_ONLYDIR) ?: [] as $entityDir) {
            if (!preg_match('/' . preg_quote($entityType, '/') . '-(\d+)$/', basename($entityDir), $entityMatch)) {
                continue;
            }
            $entityId = (int) $entityMatch[1];
            foreach (glob($entityDir . '/*.{png,jpg,jpeg,webp,gif}', GLOB_BRACE) ?: [] as $imagePath) {
                $logoUrl = './assets/uploads/' . $folderName . '/' . basename($entityDir) . '/' . basename($imagePath);
                $historyExists->execute([$entityType, $entityId, $logoUrl]);
                if (!$historyExists->fetchColumn()) {
                    $historyImport->execute([$entityType, $entityId, $logoUrl, filemtime($imagePath) ?: time()]);
                }
            }
        }
    }
    ygo_add_column($pdo, 'sx_ygo_honors', 'champion_team_id', 'INT NULL AFTER champion_deck_id');
    ygo_add_column($pdo, 'sx_ygo_honors', 'runner_up_team_id', 'INT NULL AFTER runner_up_deck_id');
    $pdo->exec('UPDATE sx_ygo_honors h LEFT JOIN sx_ygo_decks d ON d.id = h.champion_deck_id SET h.champion_team_id = d.team_id WHERE h.champion_team_id IS NULL AND d.team_id IS NOT NULL');
    $pdo->exec('UPDATE sx_ygo_honors h LEFT JOIN sx_ygo_decks d ON d.id = h.runner_up_deck_id SET h.runner_up_team_id = d.team_id WHERE h.runner_up_team_id IS NULL AND d.team_id IS NOT NULL');
    $analyticsIndexes = [
        ['sx_ygo_matches', 'idx_ygo_matches_analytics', '(competition_id, status, scheduled_on, phase)'],
        ['sx_ygo_match_results', 'idx_ygo_results_analytics', '(match_id, result)'],
        ['sx_ygo_competition_decks', 'idx_ygo_competition_decks_analytics', '(competition_id, active, deck_id)'],
        ['sx_ygo_honors', 'idx_ygo_honors_analytics', '(season_id, competition_id)'],
        ['sx_ygo_audit_log', 'idx_ygo_audit_analytics', '(action, created_at)'],
    ];
    foreach ($analyticsIndexes as [$table, $index, $columns]) {
        if (ygo_table_exists($pdo, $table) && !ygo_index_exists($pdo, $table, $index)) {
            $pdo->exec("ALTER TABLE $table ADD INDEX $index $columns");
        }
    }
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS sx_ygo_settings (
            id INT AUTO_INCREMENT PRIMARY KEY,
            season_id INT NOT NULL,
            setting_key VARCHAR(80) NOT NULL,
            setting_value TEXT NULL,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY ux_ygo_setting (season_id, setting_key)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS sx_ygo_catalog_items (
            id INT AUTO_INCREMENT PRIMARY KEY,
            season_id INT NOT NULL DEFAULT 0,
            catalog_key VARCHAR(80) NOT NULL,
            item_code VARCHAR(120) NOT NULL,
            label VARCHAR(180) NOT NULL,
            icon VARCHAR(80) NULL,
            accent VARCHAR(30) NULL,
            sort_order INT NOT NULL DEFAULT 100,
            meta_json TEXT NULL,
            active TINYINT(1) NOT NULL DEFAULT 1,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY ux_ygo_catalog_item (season_id, catalog_key, item_code),
            KEY ix_ygo_catalog_group (season_id, catalog_key, active, sort_order)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS sx_ygo_ui_labels (
            id INT AUTO_INCREMENT PRIMARY KEY,
            season_id INT NOT NULL DEFAULT 0,
            label_key VARCHAR(120) NOT NULL,
            title VARCHAR(180) NOT NULL,
            subtitle TEXT NULL,
            active TINYINT(1) NOT NULL DEFAULT 1,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY ux_ygo_ui_label (season_id, label_key),
            KEY ix_ygo_ui_label_active (season_id, active, label_key)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
}

function seed_ygo_database(PDO $pdo, array $divisions, array $fixtures): array
{
    $pdo->prepare("INSERT IGNORE INTO sx_ygo_seasons (name, slug, status) VALUES (?, ?, ?)")
        ->execute(['Temporada 2026', 'temporada-2026', 'draft']);
    $seasonId = (int) $pdo->query("SELECT id FROM sx_ygo_seasons WHERE slug = 'temporada-2026'")->fetchColumn();

    $divisionIds = [];
    if (YGO_AUTO_SEED_DIVISIONS && (int) $pdo->query("SELECT COUNT(*) FROM sx_ygo_divisions WHERE season_id = $seasonId")->fetchColumn() === 0) {
        $tier = 1;
        foreach ($divisions as $code => $division) {
            $pdo->prepare("
                INSERT INTO sx_ygo_divisions (season_id, code, name, cup_name, tier_order, deck_count, accent, description)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ")->execute([$seasonId, $code, $division['name'], $division['cup'], $tier, count($division['decks']), $division['accent'] ?? null, $division['description'] ?? null]);
            $tier++;
        }
    }

    $stmt = $pdo->prepare("SELECT id, code FROM sx_ygo_divisions WHERE season_id = ? ORDER BY tier_order, id");
    $stmt->execute([$seasonId]);
    foreach ($stmt->fetchAll() as $row) {
        $divisionIds[(string) $row['code']] = (int) $row['id'];
    }

    if ((int) $pdo->query('SELECT COUNT(*) FROM sx_ygo_teams')->fetchColumn() === 0) {
        foreach (['Station X Titans', 'TuluÃ¡ Duel Team', 'Meta Lab', 'Rogue Masters', 'Duel Academy'] as $teamName) {
            $pdo->prepare("INSERT INTO sx_ygo_teams (name, city, active) VALUES (?, 'Tulua', 1)")->execute([$teamName]);
        }
    }
    $teamIds = $pdo->query('SELECT id FROM sx_ygo_teams ORDER BY id')->fetchAll(PDO::FETCH_COLUMN);
    if (YGO_AUTO_SEED_DIVISIONS && (int) $pdo->query('SELECT COUNT(*) FROM sx_ygo_decks')->fetchColumn() === 0) {
        $deckIds = [];
        foreach ($divisions as $code => $division) {
            if (!isset($divisionIds[$code])) {
                continue;
            }
            foreach ($division['decks'] as $index => $deck) {
                $profile = ygo_team_profile_for_deck((string) $deck[1], (string) $deck[1]);
                $teamId = ygo_find_or_create_team($pdo, $profile['name'], $profile);
                $pdo->prepare("
                    INSERT INTO sx_ygo_decks (division_id, team_id, seed_rank, name, archetype, style, reason, key_cards, variations, notes, active)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1)
                ")->execute([
                    $divisionIds[$code],
                    $teamId,
                    $deck[0],
                    $deck[1],
                    $deck[1],
                    'Competitivo',
                    $deck[2],
                    'Carta clave 1, carta clave 2, carta clave 3',
                    'Pure, engine mixto, side plan',
                    'Ficha editable desde Gestion.',
                ]);
                $deckStmt = $pdo->prepare("SELECT id FROM sx_ygo_decks WHERE division_id = ? AND name = ?");
                $deckStmt->execute([$divisionIds[$code], $deck[1]]);
                $deckIds[$code][$deck[1]] = (int) $deckStmt->fetchColumn();
            }
        }

        if (YGO_AUTO_SEED_MATCHES) {
            foreach ($fixtures as $code => $rounds) {
                if (!isset($divisionIds[$code], $deckIds[$code])) {
                    continue;
                }
                foreach ($rounds as $round) {
                    foreach ($round['matches'] as $match) {
                        if (!isset($deckIds[$code][$match['a']], $deckIds[$code][$match['b']])) {
                            continue;
                        }
                        $pdo->prepare("
                            INSERT INTO sx_ygo_matches (division_id, phase, round_number, table_number, deck_a_id, deck_b_id, status)
                            VALUES (?, 'league', ?, ?, ?, ?, 'PENDIENTE')
                            ON DUPLICATE KEY UPDATE deck_a_id = VALUES(deck_a_id), deck_b_id = VALUES(deck_b_id)
                        ")->execute([
                            $divisionIds[$code],
                            $round['ronda'],
                            $match['mesa'],
                            $deckIds[$code][$match['a']],
                            $deckIds[$code][$match['b']],
                        ]);
                    }
                }
            }
        }
    }

    $allDeckIds = $pdo->query('SELECT id FROM sx_ygo_decks WHERE active = 1 ORDER BY seed_rank, id')->fetchAll(PDO::FETCH_COLUMN);
    $firstDivisionId = $divisionIds[array_key_first($divisionIds)] ?? null;
    if (YGO_AUTO_SEED_MATCHES && $firstDivisionId && $allDeckIds && (int) $pdo->query("SELECT COUNT(*) FROM sx_ygo_matches WHERE phase = 'qualifier'")->fetchColumn() === 0) {
        $createdPairs = [];
        $table = 1;
        $count = count($allDeckIds);
        foreach ($allDeckIds as $index => $deckA) {
            foreach ([1, 7, 13] as $roundIndex => $offset) {
                $deckB = (int) $allDeckIds[($index + $offset) % $count];
                $deckA = (int) $deckA;
                if ($deckA === $deckB) {
                    continue;
                }
                $pairKey = implode('-', [min($deckA, $deckB), max($deckA, $deckB)]);
                if (isset($createdPairs[$pairKey])) {
                    continue;
                }
                $createdPairs[$pairKey] = true;
                $pdo->prepare("
                    INSERT INTO sx_ygo_matches (division_id, phase, round_number, table_number, deck_a_id, deck_b_id, status)
                    VALUES (?, 'qualifier', ?, ?, ?, ?, 'PENDIENTE')
                ")->execute([$firstDivisionId, $roundIndex + 1, $table++, $deckA, $deckB]);
            }
        }
    }

    if (YGO_AUTO_SEED_COMPETITIONS && (int) $pdo->query("SELECT COUNT(*) FROM sx_ygo_competitions WHERE season_id = $seasonId")->fetchColumn() === 0) {
        $competitions = [
            ['clasificatorio', null, 'Torneo clasificatorio inicial', 'qualifier', 'suizo editable', '+0 days', '+14 days', '#36d8ff'],
            ['liga-tcg-avanzado', $divisionIds['TCG_AVANZADO'] ?? null, 'Liga TCG Avanzado', 'league', 'todos contra todos', '+15 days', '+75 days', '#ffd36a'],
            ['liga-ocg', $divisionIds['OCG'] ?? null, 'Liga OCG', 'league', 'todos contra todos', '+15 days', '+75 days', '#9c6cff'],
            ['liga-goat', $divisionIds['GOAT'] ?? null, 'Liga Goat', 'league', 'todos contra todos', '+15 days', '+75 days', '#36d8ff'],
            ['liga-edison', $divisionIds['EDISON'] ?? null, 'Liga Edison', 'league', 'todos contra todos', '+15 days', '+75 days', '#38f29b'],
            ['liga-speed-duel', $divisionIds['SPEED_DUEL'] ?? null, 'Liga Speed Duel', 'league', 'todos contra todos', '+15 days', '+75 days', '#ff7a59'],
            ['liga-genesis', $divisionIds['GENESIS'] ?? null, 'Liga Genesis', 'league', 'todos contra todos', '+15 days', '+75 days', '#6de0c2'],
            ['copa-tcg-avanzado', $divisionIds['TCG_AVANZADO'] ?? null, 'Copa TCG Avanzado', 'cup', 'eliminacion directa', '+30 days', '+90 days', '#ffd36a'],
            ['copa-ocg', $divisionIds['OCG'] ?? null, 'Copa OCG', 'cup', 'eliminacion directa', '+30 days', '+90 days', '#9c6cff'],
            ['copa-goat', $divisionIds['GOAT'] ?? null, 'Copa Goat', 'cup', 'eliminacion directa', '+30 days', '+90 days', '#36d8ff'],
            ['copa-edison', $divisionIds['EDISON'] ?? null, 'Copa Edison', 'cup', 'eliminacion directa', '+30 days', '+90 days', '#38f29b'],
            ['copa-speed-duel', $divisionIds['SPEED_DUEL'] ?? null, 'Copa Speed Duel', 'cup', 'eliminacion directa', '+30 days', '+90 days', '#ff7a59'],
            ['copa-genesis', $divisionIds['GENESIS'] ?? null, 'Copa Genesis', 'cup', 'eliminacion directa', '+30 days', '+90 days', '#6de0c2'],
            ['world-duel', null, 'World Duel Championship', 'world', '6 grupos + eliminacion', '+95 days', '+120 days', '#ff5c7a'],
            ['copa-eternal', null, 'Copa Eternal', 'final', 'top 2 por categoria', '+121 days', '+135 days', '#ffffff'],
        ];
        foreach ($competitions as $competition) {
            $startDays = (int) str_replace(['+', ' days'], '', $competition[5]);
            $endDays = (int) str_replace(['+', ' days'], '', $competition[6]);
            $startsOn = date('Y-m-d', strtotime("+$startDays days"));
            $endsOn = date('Y-m-d', strtotime("+$endDays days"));
            $pdo->prepare("
                INSERT INTO sx_ygo_competitions (season_id, division_id, code, name, type, format, status, starts_on, ends_on, accent, active)
                VALUES (?, ?, ?, ?, ?, ?, 'draft', ?, ?, ?, 1)
            ")->execute([
                $seasonId,
                $competition[1],
                $competition[0],
                $competition[2],
                $competition[3],
                $competition[4],
                $startsOn,
                $endsOn,
                $competition[7],
            ]);
        }
    }
    if (YGO_AUTO_SEED_COMPETITIONS) {
        try {
            ygo_ensure_default_events($pdo, $seasonId, $divisionIds);
            ygo_ensure_default_event_dependencies($pdo, $seasonId);
        } catch (Throwable $error) {
            $pdo->prepare('INSERT INTO sx_ygo_audit_log (entity_type, entity_id, action, payload) VALUES (?, ?, ?, ?)')
                ->execute(['event_seed', $seasonId, 'blocked_default_event_seed', json_encode(['message' => $error->getMessage()], JSON_UNESCAPED_UNICODE)]);
        }
    }
    ygo_sync_format_categories($pdo, $seasonId);

    return [
        'seasons' => (int) $pdo->query('SELECT COUNT(*) FROM sx_ygo_seasons')->fetchColumn(),
        'divisions' => (int) $pdo->query('SELECT COUNT(*) FROM sx_ygo_divisions')->fetchColumn(),
        'decks' => (int) $pdo->query('SELECT COUNT(*) FROM sx_ygo_decks')->fetchColumn(),
        'matches' => (int) $pdo->query('SELECT COUNT(*) FROM sx_ygo_matches')->fetchColumn(),
        'teams' => (int) $pdo->query('SELECT COUNT(*) FROM sx_ygo_teams')->fetchColumn(),
        'competitions' => (int) $pdo->query('SELECT COUNT(*) FROM sx_ygo_competitions')->fetchColumn(),
    ];
}

function ygo_remove_seeded_divisions(PDO $pdo, int $seasonId, array $seedDivisionDefinitions): int
{
    return 0;
    $marker = 'cleanup_seeded_divisions_20260910_b';
    $check = $pdo->prepare('SELECT setting_value FROM sx_ygo_settings WHERE season_id = ? AND setting_key = ? LIMIT 1');
    $check->execute([$seasonId, $marker]);
    if ((string) $check->fetchColumn() === '1') {
        return 0;
    }
    $seedCodes = array_keys($seedDivisionDefinitions);
    if (!$seedCodes) {
        ygo_save_setting($pdo, $marker, '1');
        return 0;
    }
    $placeholders = implode(',', array_fill(0, count($seedCodes), '?'));
    $select = $pdo->prepare("SELECT id FROM sx_ygo_divisions WHERE season_id = ? AND code IN ($placeholders)");
    $select->execute([$seasonId, ...$seedCodes]);
    $ids = array_values(array_filter(array_map('intval', $select->fetchAll(PDO::FETCH_COLUMN)), static fn(int $id): bool => $id > 0));
    if (!$ids) {
        ygo_save_setting($pdo, $marker, '1');
        return 0;
    }
    $idPlaceholders = implode(',', array_fill(0, count($ids), '?'));
    $pdo->prepare("UPDATE sx_ygo_decks SET division_id = NULL WHERE division_id IN ($idPlaceholders)")->execute($ids);
    $pdo->prepare("UPDATE sx_ygo_competitions SET division_id = NULL, promotion_to_division_id = NULL, relegation_to_division_id = NULL WHERE division_id IN ($idPlaceholders) OR promotion_to_division_id IN ($idPlaceholders) OR relegation_to_division_id IN ($idPlaceholders)")
        ->execute([...$ids, ...$ids, ...$ids]);
    $delete = $pdo->prepare("DELETE FROM sx_ygo_divisions WHERE id IN ($idPlaceholders)");
    $delete->execute($ids);
    $deleted = $delete->rowCount();
    ygo_save_setting($pdo, $marker, '1');
    $pdo->prepare('INSERT INTO sx_ygo_audit_log (entity_type, entity_id, action, payload) VALUES (?, ?, ?, ?)')
        ->execute(['division', $seasonId, 'delete_seeded_divisions', json_encode(['codes' => $seedCodes, 'ids' => $ids, 'deleted' => $deleted], JSON_UNESCAPED_UNICODE)]);
    return $deleted;
}

function ygo_ensure_default_events(PDO $pdo, int $seasonId, array $divisionIds): void
{
    $eventSeeds = [
        ['clasificatorio-general', null, 'Clasificatorio General', 'qualifier', 'tcg_advanced', '#36d8ff', 0, 14],
        ['clasificatorio-tcg-avanzado', $divisionIds['TCG_AVANZADO'] ?? null, 'Clasificatorio TCG Avanzado', 'qualifier', 'tcg_advanced', '#ffd36a', 0, 14],
        ['liga-tcg-avanzado', $divisionIds['TCG_AVANZADO'] ?? null, 'Liga TCG Avanzado', 'league', 'tcg_advanced', '#ffd36a', 15, 75],
        ['copa-tcg-avanzado', $divisionIds['TCG_AVANZADO'] ?? null, 'Copa TCG Avanzado', 'cup', 'tcg_advanced', '#ffd36a', 30, 90],
        ['clasificatorio-ocg', $divisionIds['OCG'] ?? null, 'Clasificatorio OCG', 'qualifier', 'ocg', '#9c6cff', 0, 14],
        ['torneo-ocg', $divisionIds['OCG'] ?? null, 'Torneo OCG', 'special', 'ocg', '#9c6cff', 15, 75],
        ['liga-ocg', $divisionIds['OCG'] ?? null, 'Liga OCG', 'league', 'ocg', '#9c6cff', 15, 75],
        ['copa-ocg', $divisionIds['OCG'] ?? null, 'Copa OCG', 'cup', 'ocg', '#9c6cff', 30, 90],
        ['torneo-goat', $divisionIds['GOAT'] ?? null, 'Torneo Goat', 'special', 'goat', '#36d8ff', 15, 75],
        ['liga-goat', $divisionIds['GOAT'] ?? null, 'Liga Goat', 'league', 'goat', '#36d8ff', 15, 75],
        ['copa-goat', $divisionIds['GOAT'] ?? null, 'Copa Goat', 'cup', 'goat', '#36d8ff', 30, 90],
        ['clasificatorio-edison', $divisionIds['EDISON'] ?? null, 'Clasificatorio Edison', 'qualifier', 'edison', '#38f29b', 0, 14],
        ['torneo-edison', $divisionIds['EDISON'] ?? null, 'Torneo Edison', 'special', 'edison', '#38f29b', 15, 75],
        ['liga-edison', $divisionIds['EDISON'] ?? null, 'Liga Edison', 'league', 'edison', '#38f29b', 15, 75],
        ['copa-edison', $divisionIds['EDISON'] ?? null, 'Copa Edison', 'cup', 'edison', '#38f29b', 30, 90],
        ['clasificatorio-speed-duel', $divisionIds['SPEED_DUEL'] ?? null, 'Clasificatorio Speed Duel', 'qualifier', 'speed_duel', '#ff7a59', 0, 14],
        ['torneo-speed-duel', $divisionIds['SPEED_DUEL'] ?? null, 'Torneo Speed Duel', 'special', 'speed_duel', '#ff7a59', 15, 75],
        ['liga-speed-duel', $divisionIds['SPEED_DUEL'] ?? null, 'Liga Speed Duel', 'league', 'speed_duel', '#ff7a59', 15, 75],
        ['copa-speed-duel', $divisionIds['SPEED_DUEL'] ?? null, 'Copa Speed Duel', 'cup', 'speed_duel', '#ff7a59', 30, 90],
        ['clasificatorio-genesis', $divisionIds['GENESIS'] ?? null, 'Clasificatorio Genesis', 'qualifier', 'genesis', '#6de0c2', 0, 14],
        ['torneo-genesis', $divisionIds['GENESIS'] ?? null, 'Torneo Genesis', 'special', 'genesis', '#6de0c2', 15, 75],
        ['liga-genesis', $divisionIds['GENESIS'] ?? null, 'Liga Genesis', 'league', 'genesis', '#6de0c2', 15, 75],
        ['copa-genesis', $divisionIds['GENESIS'] ?? null, 'Copa Genesis', 'cup', 'genesis', '#6de0c2', 30, 90],
        ['world-duel', null, 'World Duel Championship', 'world', 'tcg_advanced', '#ff5c7a', 95, 120],
        ['copa-eternal', null, 'Copa Eternal', 'final', 'tcg_advanced', '#ffffff', 121, 135],
    ];

    $stmt = $pdo->prepare("
        INSERT INTO sx_ygo_competitions
            (season_id, division_id, code, name, type, scope, format, match_format, best_of, participants_per_side, schedule_strategy, event_ruleset, deck_pool, victory_condition, defeat_condition, draw_condition, status, starts_on, ends_on, accent, rules, active)
        VALUES
            (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'draft', ?, ?, ?, ?, 1)
        ON DUPLICATE KEY UPDATE
            division_id=VALUES(division_id),
            type=VALUES(type),
            scope=VALUES(scope),
            match_format=VALUES(match_format),
            best_of=VALUES(best_of),
            participants_per_side=VALUES(participants_per_side),
            schedule_strategy=VALUES(schedule_strategy),
            event_ruleset=VALUES(event_ruleset),
            deck_pool=VALUES(deck_pool),
            victory_condition=VALUES(victory_condition),
            defeat_condition=VALUES(defeat_condition),
            draw_condition=VALUES(draw_condition),
            active=1
    ");
    $archiveDuplicate = $pdo->prepare("
        UPDATE sx_ygo_competitions
        SET active = 0, status = 'archived'
        WHERE season_id = ?
          AND code <> ?
          AND name = ?
          AND type = ?
          AND COALESCE(division_id, 0) = COALESCE(?, 0)
          AND event_ruleset <> ?
    ");
    $existingSeed = $pdo->prepare('
        SELECT id
        FROM sx_ygo_competitions
        WHERE season_id = ? AND name = ? AND type = ? AND COALESCE(division_id, 0) = COALESCE(?, 0) AND active = 1
        ORDER BY id
        LIMIT 1
    ');
    $updateSeed = $pdo->prepare('
        UPDATE sx_ygo_competitions
        SET division_id=?, type=?, scope=?, match_format=?, best_of=?, participants_per_side=?, schedule_strategy=?, event_ruleset=?, deck_pool=?, victory_condition=?, defeat_condition=?, draw_condition=?, accent=?, active=1
        WHERE id=?
    ');
    foreach ($eventSeeds as $seed) {
        [$code, $divisionId, $name, $type, $ruleset, $accent, $startOffset, $endOffset] = $seed;
        $defaults = ygo_competition_defaults($type, $ruleset, $name);
        $scope = $divisionId === null ? 'global' : 'division';
        $existingSeed->execute([$seasonId, $name, $type, $divisionId]);
        $existingSeedId = (int) ($existingSeed->fetchColumn() ?: 0);
        if ($existingSeedId > 0) {
            $updateSeed->execute([
                $divisionId,
                $type,
                $scope,
                $defaults['match_format'],
                $defaults['best_of'],
                $defaults['participants_per_side'],
                $defaults['schedule_strategy'],
                $ruleset,
                $defaults['deck_pool'],
                $defaults['victory_condition'],
                $defaults['defeat_condition'],
                $defaults['draw_condition'],
                $accent,
                $existingSeedId,
            ]);
            $archiveDuplicate->execute([$seasonId, $code, $name, $type, $divisionId, $ruleset]);
            continue;
        }
        $code = ygo_generate_competition_code($pdo, $seasonId, $type, $ruleset, $name);
        $stmt->execute([
            $seasonId,
            $divisionId,
            $code,
            $name,
            $type,
            $scope,
            $defaults['format'],
            $defaults['match_format'],
            $defaults['best_of'],
            $defaults['participants_per_side'],
            $defaults['schedule_strategy'],
            $ruleset,
            $defaults['deck_pool'],
            $defaults['victory_condition'],
            $defaults['defeat_condition'],
            $defaults['draw_condition'],
            date('Y-m-d', strtotime('+' . (int) $startOffset . ' days')),
            date('Y-m-d', strtotime('+' . (int) $endOffset . ' days')),
            $accent,
            $defaults['rules'],
        ]);
        $archiveDuplicate->execute([$seasonId, $code, $name, $type, $divisionId, $ruleset]);
    }
}

function ygo_ensure_default_event_dependencies(PDO $pdo, int $seasonId): void
{
    $stmt = $pdo->prepare("
        SELECT id, type, event_ruleset, code
        FROM sx_ygo_competitions
        WHERE season_id = ? AND active = 1
        ORDER BY id
    ");
    $stmt->execute([$seasonId]);
    $competitions = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $qualifierByRuleset = [];
    foreach ($competitions as $competition) {
        if ((string) $competition['type'] === 'qualifier') {
            $ruleset = (string) (($competition['event_ruleset'] ?? '') ?: 'tcg_advanced');
            if (!isset($qualifierByRuleset[$ruleset]) || str_contains((string) $competition['code'], $ruleset)) {
                $qualifierByRuleset[$ruleset] = (int) $competition['id'];
            }
        }
    }
    $insert = $pdo->prepare("
        INSERT INTO sx_ygo_competition_dependencies (season_id, competition_id, depends_on_competition_id, condition_type, take_count, active)
        VALUES (?, ?, ?, 'completed_with_results', 0, 1)
        ON DUPLICATE KEY UPDATE active=1, condition_type=VALUES(condition_type), take_count=VALUES(take_count)
    ");
    foreach ($competitions as $competition) {
        $type = (string) $competition['type'];
        if (!in_array($type, ['league', 'cup', 'special'], true)) {
            continue;
        }
        $ruleset = (string) (($competition['event_ruleset'] ?? '') ?: 'tcg_advanced');
        $requiredId = $qualifierByRuleset[$ruleset] ?? null;
        if ($requiredId && $requiredId !== (int) $competition['id']) {
            $insert->execute([$seasonId, (int) $competition['id'], $requiredId]);
        }
    }
}

function ygo_ensure_default_rulesets(PDO $pdo, int $seasonId, array $rulesetOptions): void
{
    $currentBanlists = [
        'master_duel' => ['Master Duel Forbidden/Limited List', 'Septiembre 2026', '2026-09-03', 'Lista actual de Master Duel verificada como actualizada el 2026-09-03. Cargar detalle carta por carta desde Reglamento si se requiere control por carta.'],
        'tcg_advanced' => ['TCG Advanced Forbidden & Limited List', 'Mayo 2026', '2026-05-18', 'Lista TCG Advanced vigente publicada el 2026-05-18.'],
        'ocg' => ['OCG Forbidden & Limited List', 'Julio 2026', '2026-07-01', 'Lista OCG vigente desde 2026-07-01.'],
        'goat' => ['Goat Format Forbidden & Limited List', 'Abril 2005', '2005-04-01', 'Formato historico Goat basado en la lista abril 2005.'],
        'edison' => ['Edison Format Forbidden & Limited List', 'Marzo 2010', '2010-03-01', 'Formato historico Edison basado en la lista TCG marzo 2010.'],
        'speed_duel' => ['Speed Duel Forbidden/Limited List', 'Actual por definir', null, 'Speed Duel usa lista propia por grupos Limited 1/2/3; registrar version exacta antes de jugar.'],
        'genesis' => ['Genesys Point List', 'Agosto 2026', '2026-08-01', 'Genesys no usa banlist tradicional; usa sistema de puntos/cupo. Registrar point list exacta antes de jugar.'],
        'custom' => ['Reglamento personalizado', 'Por definir', null, 'Reglamento local editable.'],
    ];
    $stmt = $pdo->prepare("
        INSERT INTO sx_ygo_rulesets
            (season_id, code, label, format_family, banlist_name, banlist_version, banlist_effective_on, match_format, best_of, participants_per_side, schedule_strategy, deck_pool, victory_condition, defeat_condition, draw_condition, notes, active)
        VALUES
            (?, ?, ?, ?, ?, ?, ?, '1v1', 3, 1, 'manual', ?, ?, ?, ?, ?, 1)
        ON DUPLICATE KEY UPDATE label=VALUES(label), format_family=VALUES(format_family), banlist_name=VALUES(banlist_name), banlist_version=VALUES(banlist_version), banlist_effective_on=VALUES(banlist_effective_on), active=1
    ");
    foreach ($rulesetOptions as $code => $label) {
        $defaults = ygo_competition_defaults($code === 'custom' ? 'special' : 'league', (string) $code, (string) $label);
        $banlist = $currentBanlists[(string) $code] ?? [(string) $label, 'Por definir', null, 'Version pendiente de registrar.'];
        $stmt->execute([
            $seasonId,
            (string) $code,
            (string) $label,
            (string) $code,
            $banlist[0],
            $banlist[1],
            $banlist[2],
            (string) ($defaults['deck_pool'] ?? 'all_active'),
            (string) ($defaults['victory_condition'] ?? 'Resultado definido por reglamento'),
            (string) ($defaults['defeat_condition'] ?? 'Resultado definido por reglamento'),
            (string) ($defaults['draw_condition'] ?? 'Empate definido por reglamento'),
            $banlist[3],
        ]);
    }
}

function ygo_ensure_default_ruleset_versions(PDO $pdo, int $seasonId): void
{
    $stmt = $pdo->prepare("
        INSERT INTO sx_ygo_ruleset_versions
            (season_id, code, category, system, version_label, date_label, effective_on, sort_order, active)
        VALUES
            (?, ?, ?, ?, ?, ?, ?, ?, 1)
        ON DUPLICATE KEY UPDATE
            category=VALUES(category),
            system=VALUES(system),
            version_label=VALUES(version_label),
            date_label=VALUES(date_label),
            effective_on=VALUES(effective_on),
            sort_order=VALUES(sort_order),
            active=1
    ");
    foreach (ygo_ruleset_version_catalog() as $index => $item) {
        $effectiveOn = $item['sort_date'] === '9999-12-31' ? null : $item['sort_date'];
        $stmt->execute([
            $seasonId,
            (string) $item['value'],
            (string) $item['category'],
            (string) $item['system'],
            (string) $item['version'],
            (string) $item['date_label'],
            $effectiveOn,
            $index + 1,
        ]);
    }
}

function ygo_ensure_default_game_rules(PDO $pdo, int $seasonId): void
{
    unset($pdo, $seasonId);
    // Las reglas y sus condiciones se administran exclusivamente en sx_ygo_game_rules
    // y sx_ygo_game_rule_conditions desde el CRUD. No se siembran ni se corrigen desde codigo.
}

function ygo_record_logo_history(PDO $pdo, string $entityType, int $entityId, string $logoUrl, string $source = 'upload'): void
{
    $logoUrl = trim($logoUrl);
    if ($entityId <= 0 || $logoUrl === '' || !in_array($entityType, ['deck', 'team', 'competition'], true)) {
        return;
    }
    $pdo->prepare('INSERT INTO sx_ygo_logo_history (entity_type, entity_id, logo_url, source) VALUES (?, ?, ?, ?)')
        ->execute([$entityType, $entityId, $logoUrl, substr($source, 0, 30)]);
}

function ygo_ensure_default_match_types(PDO $pdo): void
{
    $stmt = $pdo->prepare("
        INSERT INTO sx_ygo_match_types (code, label, participants_per_side, sort_order, active)
        VALUES (?, ?, ?, ?, 1)
        ON DUPLICATE KEY UPDATE label=VALUES(label), participants_per_side=VALUES(participants_per_side), sort_order=VALUES(sort_order), active=1
    ");
    foreach ([['1v1', '1v1', 1, 1], ['2v2', '2v2', 2, 2], ['3v3', '3v3', 3, 3]] as $row) {
        $stmt->execute($row);
    }
}

function ygo_allowed_deck_ids_for_competition(PDO $pdo, ?int $competitionId, int $manualDivisionId): array
{
    if (!$competitionId) {
        if ($manualDivisionId > 0) {
            $stmt = $pdo->prepare('SELECT id FROM sx_ygo_decks WHERE active = 1 AND team_id IS NOT NULL AND division_id = ?');
            $stmt->execute([$manualDivisionId]);
            return array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
        }
        $stmt = $pdo->query('SELECT id FROM sx_ygo_decks WHERE active = 1 AND team_id IS NOT NULL');
        return array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
    }

    $stmt = $pdo->prepare('SELECT * FROM sx_ygo_competitions WHERE id = ? AND active = 1 LIMIT 1');
    $stmt->execute([$competitionId]);
    $competition = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$competition) {
        return [];
    }

    if(ygo_admission_required($competition) || ygo_admission_load($pdo,$competition)!==null){$admission=ygo_admission_view($pdo,$competition);return $admission['ok']?($admission['selection']['ids']??[]):[];}
    $pool = (string) (($competition['deck_pool'] ?? '') ?: 'all_active');
    $formatIds = ygo_format_deck_ids($pdo, (string)$competition['event_ruleset']);
    $formatFilter = static fn(array $ids): array => array_values(array_intersect(array_map('intval',$ids),$formatIds));
    $participantSource = strtoupper((string) (($competition['participant_source'] ?? '') ?: 'MANUAL'));
    $accessType = strtoupper((string) (($competition['access_type'] ?? '') ?: 'OPEN'));
    $manualStmt = $pdo->prepare('SELECT deck_id FROM sx_ygo_competition_decks WHERE competition_id = ? AND active = 1 ORDER BY sort_order, id');
    $manualStmt->execute([$competitionId]);
    $manualDeckIds = array_map('intval', $manualStmt->fetchAll(PDO::FETCH_COLUMN));
    $deckPolicy = strtoupper((string) (($competition['deck_policy'] ?? '') ?: 'OPTIONAL_REGISTRATION'));
    if (in_array($deckPolicy, ['REQUIRED_REGISTRATION', 'DECK_LOCK_EVENT', 'DECK_LOCK_PHASE', 'DECK_LOCK_ROUND'], true)) {
        return $formatFilter($manualDeckIds);
    }
    if ($manualDeckIds && in_array($participantSource, ['MANUAL', 'CUSTOM'], true)) {
        return $formatFilter($manualDeckIds);
    }
    if ($manualDeckIds && !in_array($pool, ['division', 'all_active'], true)) {
        return $formatFilter($manualDeckIds);
    }

    $divisionId = $competition['division_id'] !== null ? (int) $competition['division_id'] : $manualDivisionId;
    if (!$divisionId && !empty($competition['event_ruleset'])) {
        $divisionCode = ygo_ruleset_division_code((string) $competition['event_ruleset']);
        if ($divisionCode) {
            $divisionStmt = $pdo->prepare('SELECT id FROM sx_ygo_divisions WHERE code = ? AND active = 1 ORDER BY id DESC LIMIT 1');
            $divisionStmt->execute([$divisionCode]);
            $divisionId = (int) $divisionStmt->fetchColumn();
        }
    }

    if ($pool === 'division') return $formatIds;

    if (in_array($pool, ['qualified', 'top_cut'], true) || in_array($participantSource, ['PREVIOUS_EVENT', 'QUALIFIER', 'RANKING'], true) || in_array($accessType, ['INVITATIONAL', 'QUALIFIER', 'LAST_CHANCE_QUALIFIER'], true)) {
        $dependencyStmt = $pdo->prepare("
            SELECT depends_on_competition_id, condition_type, take_count
            FROM sx_ygo_competition_dependencies
            WHERE competition_id = ? AND active = 1
            ORDER BY id
        ");
        $dependencyStmt->execute([$competitionId]);
        $dependencyRows = $dependencyStmt->fetchAll(PDO::FETCH_ASSOC);
        if ($dependencyRows) {
            $qualifiedIds = [];
            foreach ($dependencyRows as $dependencyRow) {
                $dependencyType = (string) ($dependencyRow['condition_type'] ?? 'completed_with_results');
                $requiredId = (int) ($dependencyRow['depends_on_competition_id'] ?? 0);
                if ($requiredId <= 0) {
                    continue;
                }
                if (in_array($dependencyType, ['top_n', 'winner_to_final', 'defending_champion'], true)) {
                    $take = $dependencyType === 'top_n' ? max(1, (int) ($dependencyRow['take_count'] ?? 0) ?: 4) : 1;
                    foreach (array_slice(ygo_competition_standings($pdo, $requiredId), 0, $take) as $standingRow) {
                        $deckId = (int) ($standingRow['deck_id'] ?? 0);
                        if ($deckId > 0) {
                            $qualifiedIds[] = $deckId;
                        }
                    }
                }
            }
            if ($qualifiedIds) {
                return $formatFilter(array_values(array_unique($qualifiedIds)));
            }
            $requiredIds = array_values(array_unique(array_map(static fn(array $row): int => (int) $row['depends_on_competition_id'], $dependencyRows)));
            $placeholders = implode(',', array_fill(0, count($requiredIds), '?'));
            $qualifiedStmt = $pdo->prepare("
                SELECT DISTINCT deck_id FROM (
                    SELECT m.deck_a_id AS deck_id
                    FROM sx_ygo_matches m
                    JOIN sx_ygo_match_results r ON r.match_id = m.id
                    WHERE m.competition_id IN ($placeholders) AND r.result IS NOT NULL AND r.result <> ''
                    UNION
                    SELECT m.deck_b_id AS deck_id
                    FROM sx_ygo_matches m
                    JOIN sx_ygo_match_results r ON r.match_id = m.id
                    WHERE m.competition_id IN ($placeholders) AND r.result IS NOT NULL AND r.result <> ''
                    UNION
                    SELECT m.deck_c_id AS deck_id
                    FROM sx_ygo_matches m
                    JOIN sx_ygo_match_results r ON r.match_id = m.id
                    WHERE m.competition_id IN ($placeholders) AND m.deck_c_id IS NOT NULL AND r.result IS NOT NULL AND r.result <> ''
                    UNION
                    SELECT m.deck_d_id AS deck_id
                    FROM sx_ygo_matches m
                    JOIN sx_ygo_match_results r ON r.match_id = m.id
                    WHERE m.competition_id IN ($placeholders) AND m.deck_d_id IS NOT NULL AND r.result IS NOT NULL AND r.result <> ''
                    UNION
                    SELECT m.deck_e_id AS deck_id
                    FROM sx_ygo_matches m
                    JOIN sx_ygo_match_results r ON r.match_id = m.id
                    WHERE m.competition_id IN ($placeholders) AND m.deck_e_id IS NOT NULL AND r.result IS NOT NULL AND r.result <> ''
                    UNION
                    SELECT m.deck_f_id AS deck_id
                    FROM sx_ygo_matches m
                    JOIN sx_ygo_match_results r ON r.match_id = m.id
                    WHERE m.competition_id IN ($placeholders) AND m.deck_f_id IS NOT NULL AND r.result IS NOT NULL AND r.result <> ''
                ) qualified
            ");
            $qualifiedStmt->execute(array_merge($requiredIds, $requiredIds, $requiredIds, $requiredIds, $requiredIds, $requiredIds));
            $qualifiedIds = array_map('intval', $qualifiedStmt->fetchAll(PDO::FETCH_COLUMN));
            if ($qualifiedIds) {
                return $formatFilter($pool === 'top_cut' ? array_slice($qualifiedIds, 0, 24) : $qualifiedIds);
            }
        }
        if ($divisionId > 0) {
            $deckStmt = $pdo->prepare('SELECT id FROM sx_ygo_decks WHERE active = 1 AND team_id IS NOT NULL AND division_id = ?');
            $deckStmt->execute([$divisionId]);
            return array_map('intval', $deckStmt->fetchAll(PDO::FETCH_COLUMN));
        }
    }

    return $formatIds;
}

function ygo_match_deck_validation(PDO $pdo, ?int $competitionId, int $manualDivisionId, array $deckIds, int $participantsPerSide = 1): array
{
    $allowed = ygo_allowed_deck_ids_for_competition($pdo, $competitionId, $manualDivisionId);
    if (!$allowed) {
        return ['ok' => false, 'message' => 'No hay decks permitidos para el formato del evento.'];
    }
    $allowedSet = array_fill_keys(array_map('strval', $allowed), true);
    $cleanDeckIds = [];
    foreach ($deckIds as $deckId) {
        if ($deckId !== null && $deckId > 0 && !isset($allowedSet[(string) $deckId])) {
            return ['ok' => false, 'message' => 'Hay decks que no pertenecen al formato/pool del evento.'];
        }
        if ($deckId !== null && $deckId > 0) {
            $cleanDeckIds[] = (int) $deckId;
        }
    }
    if (count($cleanDeckIds) !== count(array_unique($cleanDeckIds))) {
        return ['ok' => false, 'message' => 'No se puede repetir un deck en el mismo match.'];
    }
    if (!$cleanDeckIds) {
        return ['ok' => false, 'message' => 'Debes seleccionar decks para crear el match.'];
    }
    $placeholders = implode(',', array_fill(0, count($cleanDeckIds), '?'));
    $stmt = $pdo->prepare("SELECT id, division_id, team_id FROM sx_ygo_decks WHERE id IN ($placeholders) AND active = 1");
    $stmt->execute($cleanDeckIds);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    if (count($rows) !== count($cleanDeckIds)) {
        return ['ok' => false, 'message' => 'Uno o mas decks no existen o estan inactivos.'];
    }
    $byId = [];
    foreach ($rows as $row) {
        if (empty($row['team_id'])) {
            return ['ok' => false, 'message' => 'Todo deck debe pertenecer a un equipo.'];
        }
        $byId[(int) $row['id']] = $row;
    }
    $divisionIds = array_unique(array_map(static fn(array $row): int => (int) $row['division_id'], $rows));
    if (count($divisionIds) > 1 || ($manualDivisionId > 0 && (int) $divisionIds[0] !== $manualDivisionId)) {
        return ['ok' => false, 'message' => 'Todos los decks del match deben pertenecer al mismo formato del evento.'];
    }
    $participantsPerSide = max(1, min(3, $participantsPerSide));
    $sideA = array_values(array_filter([$deckIds[0] ?? null, $deckIds[2] ?? null, $deckIds[4] ?? null], static fn($id): bool => $id !== null && (int) $id > 0));
    $sideB = array_values(array_filter([$deckIds[1] ?? null, $deckIds[3] ?? null, $deckIds[5] ?? null], static fn($id): bool => $id !== null && (int) $id > 0));
    if (count($sideA) !== $participantsPerSide || count($sideB) !== $participantsPerSide) {
        return ['ok' => false, 'message' => "El match {$participantsPerSide}v{$participantsPerSide} requiere {$participantsPerSide} decks por lado."];
    }
    if ($participantsPerSide > 1) {
        $teamA = (int) $byId[(int) $sideA[0]]['team_id'];
        $teamB = (int) $byId[(int) $sideB[0]]['team_id'];
        foreach ($sideA as $deckId) {
            if ((int) $byId[(int) $deckId]['team_id'] !== $teamA) {
                return ['ok' => false, 'message' => 'En 2v2/3v3 todos los decks del lado A deben ser del mismo equipo.'];
            }
        }
        foreach ($sideB as $deckId) {
            if ((int) $byId[(int) $deckId]['team_id'] !== $teamB) {
                return ['ok' => false, 'message' => 'En 2v2/3v3 todos los decks del lado B deben ser del mismo equipo.'];
            }
        }
        if ($teamA === $teamB) {
            return ['ok' => false, 'message' => 'En 2v2/3v3 los dos lados deben ser equipos distintos.'];
        }
    }
    return ['ok' => true, 'message' => 'OK'];
}

function ygo_match_decks_allowed(PDO $pdo, ?int $competitionId, int $manualDivisionId, array $deckIds, int $participantsPerSide = 1): bool
{
    return ygo_match_deck_validation($pdo, $competitionId, $manualDivisionId, $deckIds, $participantsPerSide)['ok'];
}

function ygo_roster_groups_for_match(PDO $pdo, array $selectedDeckIds, int $participantsPerSide): array
{
    $participantsPerSide = max(1, min(3, $participantsPerSide));
    $selectedDeckIds = array_values(array_unique(array_filter(array_map('intval', $selectedDeckIds), static fn(int $id): bool => $id > 0)));
    if (!$selectedDeckIds) {
        return [];
    }
    if ($participantsPerSide === 1) {
        return array_map(static fn(int $id): array => [$id], $selectedDeckIds);
    }
    $placeholders = implode(',', array_fill(0, count($selectedDeckIds), '?'));
    $stmt = $pdo->prepare("SELECT id, team_id FROM sx_ygo_decks WHERE id IN ($placeholders) AND active = 1 AND team_id IS NOT NULL");
    $stmt->execute($selectedDeckIds);
    $teamByDeck = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $teamByDeck[(int) $row['id']] = (int) $row['team_id'];
    }
    $teams = [];
    foreach ($selectedDeckIds as $deckId) {
        $teamId = $teamByDeck[$deckId] ?? 0;
        if ($teamId <= 0) {
            continue;
        }
        $teams[$teamId] = $teams[$teamId] ?? [];
        $teams[$teamId][] = $deckId;
    }
    $groups = [];
    foreach ($teams as $teamDecks) {
        if (count($teamDecks) >= $participantsPerSide) {
            $groups[] = array_slice($teamDecks, 0, $participantsPerSide);
        }
    }
    return $groups;
}

function ygo_competition_standings(PDO $pdo, int $competitionId, ?string $phaseFilter = null): array
{
    $competitionStmt = $pdo->prepare('SELECT * FROM sx_ygo_competitions WHERE id = ? AND active = 1 LIMIT 1');
    $competitionStmt->execute([$competitionId]);
    $competitionConfig = $competitionStmt->fetch(PDO::FETCH_ASSOC) ?: [];
    $workflowRanking=$competitionConfig ? ygo_workflow_live_ranking($pdo,$competitionConfig,$phaseFilter) : [];
    if($workflowRanking && $phaseFilter===null)$phaseFilter=['swiss'=>'swiss','swiss_top_cut'=>'swiss','groups'=>'groups','groups_knockout'=>'groups','season_playoffs'=>'league'][(string)$competitionConfig['execution_model']]??null;
    $rankingImpact = strtoupper((string) (($competitionConfig['ranking_impact'] ?? '') ?: 'FULL'));
    $level = strtoupper((string) (($competitionConfig['level'] ?? '') ?: 'COMPETITIVE'));
    if (!$workflowRanking && ($rankingImpact === 'NONE' || in_array($level, ['CASUAL', 'BEGINNER', 'ACADEMY', 'PROMOTIONAL', 'EXHIBITION'], true))) {
        return [];
    }
    $winPoints = $rankingImpact === 'REDUCED' ? 1 : ($rankingImpact === 'BONUS' ? 4 : 3);
    $drawPoints = $rankingImpact === 'REDUCED' ? 0 : 1;
    $selectedStmt = $pdo->prepare('SELECT COUNT(*) FROM sx_ygo_competition_decks WHERE competition_id = ? AND active = 1');
    $selectedStmt->execute([$competitionId]);
    $hasSelectedDecks = (int) $selectedStmt->fetchColumn() > 0;
    if ($hasSelectedDecks) {
        $deckStmt = $pdo->prepare("
            SELECT
                d.id,
                d.name,
                d.seed_rank,
                COALESCE(t.name, 'Sin equipo') AS team,
                COALESCE(t.short_code, '') AS team_code,
                COALESCE(t.primary_color, '#36d8ff') AS team_primary_color,
                COALESCE(t.secondary_color, '#ffd25f') AS team_secondary_color
            FROM sx_ygo_competition_decks cd
            JOIN sx_ygo_decks d ON d.id = cd.deck_id
            LEFT JOIN sx_ygo_teams t ON t.id = d.team_id
            WHERE cd.competition_id = ? AND cd.active = 1 AND d.active = 1
            ORDER BY cd.sort_order, d.seed_rank, d.name
        ");
        $deckStmt->execute([$competitionId]);
        $deckRows = $deckStmt->fetchAll(PDO::FETCH_ASSOC);
    } else {
        $deckRows = $pdo->query("
        SELECT
            d.id,
            d.name,
            d.seed_rank,
            COALESCE(t.name, 'Sin equipo') AS team,
            COALESCE(t.short_code, '') AS team_code,
            COALESCE(t.primary_color, '#36d8ff') AS team_primary_color,
            COALESCE(t.secondary_color, '#ffd25f') AS team_secondary_color
        FROM sx_ygo_decks d
        LEFT JOIN sx_ygo_teams t ON t.id = d.team_id
        WHERE d.active = 1
        ORDER BY d.seed_rank, d.name
        ")->fetchAll(PDO::FETCH_ASSOC);
    }
    $rows = [];
    foreach ($deckRows as $deck) {
        $rows[(int) $deck['id']] = [
            'id' => (int) $deck['id'],
            'name' => (string) $deck['name'],
            'team' => (string) $deck['team'],
            'team_code' => (string) $deck['team_code'],
            'team_primary_color' => (string) $deck['team_primary_color'],
            'team_secondary_color' => (string) $deck['team_secondary_color'],
            'seed_rank' => (int) $deck['seed_rank'],
            'pts' => 0,
            'pj' => 0,
            'pg' => 0,
            'pe' => 0,
            'pp' => 0,
            'cg' => 0,
            'cp' => 0,
            'dp' => 0,
        ];
    }
    $phaseSql = $phaseFilter !== null && $phaseFilter !== '' ? ' AND m.phase = ?' : '';
    $matchStmt = $pdo->prepare("
        SELECT m.deck_a_id, m.deck_b_id, r.result, r.score
        FROM sx_ygo_matches m
        JOIN sx_ygo_match_results r ON r.match_id = m.id
        WHERE m.competition_id = ? AND r.result IS NOT NULL AND r.result <> ''$phaseSql
    ");
    $matchStmt->execute($phaseFilter !== null && $phaseFilter !== '' ? [$competitionId, $phaseFilter] : [$competitionId]);
    foreach ($matchStmt->fetchAll(PDO::FETCH_ASSOC) as $match) {
        $aId = (int) $match['deck_a_id'];
        $bId = (int) $match['deck_b_id'];
        if (!isset($rows[$aId], $rows[$bId])) {
            continue;
        }
        [$cgA, $cgB] = array_pad(array_map('intval', explode('-', (string) ($match['score'] ?: '0-0'))), 2, 0);
        $rows[$aId]['pj']++;
        $rows[$bId]['pj']++;
        $rows[$aId]['cg'] += $cgA;
        $rows[$aId]['cp'] += $cgB;
        $rows[$bId]['cg'] += $cgB;
        $rows[$bId]['cp'] += $cgA;
        if ($match['result'] === 'A') {
            $rows[$aId]['pts'] += $winPoints;
            $rows[$aId]['pg']++;
            $rows[$bId]['pp']++;
        } elseif ($match['result'] === 'B') {
            $rows[$bId]['pts'] += $winPoints;
            $rows[$bId]['pg']++;
            $rows[$aId]['pp']++;
        } elseif ($match['result'] === 'E') {
            $rows[$aId]['pts'] += $drawPoints;
            $rows[$bId]['pts'] += $drawPoints;
            $rows[$aId]['pe']++;
            $rows[$bId]['pe']++;
        }
    }
    foreach ($rows as &$row) {
        $row['dp'] = $row['cg'] - $row['cp'];
    }
    unset($row);
    usort($rows, static fn(array $a, array $b): int => $b['pts'] <=> $a['pts'] ?: $b['dp'] <=> $a['dp'] ?: $b['cg'] <=> $a['cg'] ?: $a['seed_rank'] <=> $b['seed_rank']);
    if($workflowRanking){
        $byId=[];foreach($rows as $r)$byId[$r['id']]=$r;$rows=[];
        foreach($workflowRanking as $r)if(isset($byId[$r['id']])){$row=$byId[$r['id']];$row['pts']=$r['points'];$row['buchholz']=$r['buchholz'];$row['group']=$r['group']??null;$row['group_position']=$r['group_position']??null;$rows[]=$row;}
    }
    if($competitionConfig && ($teamConfig=ygo_team_load($pdo,$competitionConfig))!==null){$lineups=[];foreach($teamConfig['lineups'] as $lineup)$lineups[$lineup['captain']]=$lineup;$rows=array_values(array_filter($rows,static fn($r)=>isset($lineups[$r['id']])));foreach($rows as &$r){$r['name']=$lineups[$r['id']]['name'];$r['team']='Equipo '.$teamConfig['size'].'v'.$teamConfig['size'];$r['member_deck_ids']=$lineups[$r['id']]['decks'];}unset($r);}
    return $rows;
}

function ygo_audit_label(string $action): string
{
    return [
        'configure_competition' => 'Configuración del evento guardada',
        'save_competition' => 'Evento guardado',
        'save_event_logo' => 'Logo del evento guardado',
        'apply_qualifier_divisions' => 'Divisiones aplicadas desde el clasificatorio',
        'save_team_lineups' => 'Equipos y alineaciones guardados',
        'save_event_admission' => 'Fuentes y clasificados guardados',
        'workflow_stage' => 'Fase creada',
        'workflow_revert' => 'Fase anterior corregida',
        'division_movements_applied' => 'Ascensos y descensos aplicados',
        'manual_honor_confirmed' => 'Campeón confirmado',
        'create_ffa' => 'Mesa multijugador creada',
        'finalize_ffa' => 'Evento multijugador finalizado',
        'delete_competition' => 'Evento eliminado',
        'blocked_create_match_by_deck_pool' => 'Bloqueo por pool de decks',
        'blocked_default_event_seed' => 'Bloqueo al sembrar eventos base',
        'blocked_create_match_by_rules' => 'Bloqueo por reglas de calendario',
        'blocked_schedule_by_rules' => 'Bloqueo al programar',
        'blocked_result_by_date' => 'Bloqueo de resultado por fecha',
        'turn_added' => 'Turno registrado',
        'save_dependency' => 'Prerequisito guardado',
        'delete_dependency' => 'Prerequisito eliminado',
        'advance_competition_phase' => 'Avance de fase',
        'division_promotion' => 'Ascenso de division',
        'division_relegation' => 'Descenso de division',
        'apply_from_qualifier' => 'Division aplicada desde clasificatorio',
        'blocked_finalize_competition' => 'Bloqueo al finalizar evento',
        'finalize_competition' => 'Evento finalizado',
        'recalculate_standings' => 'Ranking recalculado',
        'archive_qa_codex_data' => 'QA Codex archivado',
        'save_result' => 'Resultado guardado',
    ][$action] ?? $action;
}

function ygo_sync_format_categories(PDO $pdo, int $seasonId): void
{
    global $divisionDefinitions;

    $managed = $pdo->prepare("SELECT setting_value FROM sx_ygo_settings WHERE season_id=? AND setting_key='league_structure_managed' LIMIT 1");
    $managed->execute([$seasonId]);
    if ((string) $managed->fetchColumn() === '1') {
        return;
    }

    $legacyCodes = [
        'TITAN' => 'TCG_AVANZADO',
        'LEGEND' => 'OCG',
        'ELITE' => 'GOAT',
        'CHALLENGER' => 'EDISON',
    ];
    foreach ($legacyCodes as $legacyCode => $newCode) {
        if (!isset($divisionDefinitions[$newCode])) {
            continue;
        }
        $definition = $divisionDefinitions[$newCode];
        $pdo->prepare('
            UPDATE sx_ygo_divisions
            SET code=?, name=?, cup_name=?, tier_order=?, deck_count=?, accent=?, description=?
            WHERE season_id=? AND code=?
        ')->execute([
            $newCode,
            $definition['name'],
            $definition['cup'],
            array_search($newCode, array_keys($divisionDefinitions), true) + 1,
            $definition['size'],
            $definition['accent'],
            $definition['description'],
            $seasonId,
            $legacyCode,
        ]);
    }

    $existingStmt = $pdo->prepare('SELECT code FROM sx_ygo_divisions WHERE season_id = ?');
    $existingStmt->execute([$seasonId]);
    $existingCodes = array_map('strval', $existingStmt->fetchAll(PDO::FETCH_COLUMN));
    if (!$existingCodes) {
        return;
    }
    foreach ($divisionDefinitions as $code => $definition) {
        $tier = array_search($code, array_keys($divisionDefinitions), true) + 1;
        if (!in_array($code, $existingCodes, true)) {
            continue;
        }
        $pdo->prepare('
            UPDATE sx_ygo_divisions
            SET name=?, cup_name=?, tier_order=?, deck_count=?, accent=?, description=?
            WHERE season_id=? AND code=?
        ')->execute([
            $definition['name'],
            $definition['cup'],
            $tier,
            $definition['size'],
            $definition['accent'],
            $definition['description'],
            $seasonId,
            $code,
        ]);
    }

}

function ygo_ensure_goat_league_structure(PDO $pdo, int $seasonId): array
{
    $legacy = $pdo->query("SELECT id FROM sx_ygo_seasons WHERE slug='temporada-2026'")->fetchColumn();
    if ($seasonId !== (int) $legacy) return ['division_id'=>0,'league_id'=>0,'disabled_qualifiers'=>0];
    $managed = $pdo->prepare("SELECT setting_value FROM sx_ygo_settings WHERE season_id=? AND setting_key='league_structure_managed' LIMIT 1");
    $managed->execute([$seasonId]);
    if ((string) $managed->fetchColumn() === '1') {
        $division = $pdo->prepare("SELECT id, league_competition_id FROM sx_ygo_divisions WHERE season_id=? AND code='GOAT' AND active=1 LIMIT 1");
        $division->execute([$seasonId]);
        $row = $division->fetch(PDO::FETCH_ASSOC) ?: [];
        return ['division_id' => (int) ($row['id'] ?? 0), 'league_id' => (int) ($row['league_competition_id'] ?? 0), 'disabled_qualifiers' => 0];
    }

    $pdo->beginTransaction();
    try {
        $pdo->prepare('
            INSERT INTO sx_ygo_divisions
                (season_id, code, name, cup_name, tier_order, deck_count, accent, description, active)
            VALUES
                (?, "GOAT", "Primera Division GOAT", "Copa GOAT", 3, 0, "#36d8ff", "Primera division de la Liga GOAT para partidas retro y control de recursos.", 1)
            ON DUPLICATE KEY UPDATE
                name=VALUES(name), cup_name=VALUES(cup_name), accent=VALUES(accent), description=VALUES(description), active=1
        ')->execute([$seasonId]);

        $divisionStmt = $pdo->prepare('SELECT id, league_competition_id FROM sx_ygo_divisions WHERE season_id=? AND code="GOAT" AND active=1 LIMIT 1');
        $divisionStmt->execute([$seasonId]);
        $goatDivision = $divisionStmt->fetch(PDO::FETCH_ASSOC) ?: [];
        $divisionId = (int) ($goatDivision['id'] ?? 0);
        $linkedLeagueId = (int) ($goatDivision['league_competition_id'] ?? 0);

        $leagueStmt = $pdo->prepare('
            SELECT id
            FROM sx_ygo_competitions
            WHERE season_id=? AND active=1 AND type="league"
              AND (LOWER(COALESCE(event_ruleset, ""))="goat" OR LOWER(name) LIKE "%goat%")
            ORDER BY (code="liga-goat") DESC, id DESC
            LIMIT 1
        ');
        $leagueStmt->execute([$seasonId]);
        $leagueId = (int) ($leagueStmt->fetchColumn() ?: 0);

        $linked = 0;
        if ($divisionId > 0 && $leagueId > 0 && $linkedLeagueId <= 0) {
            $leagueUpdate = $pdo->prepare('UPDATE sx_ygo_competitions SET division_id=?, event_ruleset="goat", deck_pool="division" WHERE id=? AND season_id=?');
            $leagueUpdate->execute([$divisionId, $leagueId, $seasonId]);
            $divisionUpdate = $pdo->prepare('UPDATE sx_ygo_divisions SET league_competition_id=? WHERE id=? AND season_id=?');
            $divisionUpdate->execute([$leagueId, $divisionId, $seasonId]);
            $linked = max($leagueUpdate->rowCount(), $divisionUpdate->rowCount());
        }

        $qualifierStmt = $pdo->prepare('
            SELECT id
            FROM sx_ygo_competitions
            WHERE season_id=? AND active=1 AND type="qualifier"
              AND (LOWER(COALESCE(event_ruleset, ""))="goat" OR LOWER(name) LIKE "%goat%")
        ');
        $qualifierStmt->execute([$seasonId]);
        $qualifierIds = array_values(array_filter(array_map('intval', $qualifierStmt->fetchAll(PDO::FETCH_COLUMN)), static fn(int $id): bool => $id > 0));
        if ($qualifierIds) {
            $placeholders = implode(',', array_fill(0, count($qualifierIds), '?'));
            $pdo->prepare("UPDATE sx_ygo_competitions SET active=0 WHERE id IN ($placeholders)")->execute($qualifierIds);
            $pdo->prepare("UPDATE sx_ygo_events SET active=0 WHERE competition_id IN ($placeholders)")->execute($qualifierIds);
            $pdo->prepare("UPDATE sx_ygo_competition_dependencies SET active=0 WHERE competition_id IN ($placeholders) OR depends_on_competition_id IN ($placeholders)")
                ->execute([...$qualifierIds, ...$qualifierIds]);
        }

        if ($linked > 0 || $qualifierIds) {
            $pdo->prepare('INSERT INTO sx_ygo_audit_log (entity_type, entity_id, action, payload) VALUES (?, ?, ?, ?)')
                ->execute(['competition', $leagueId ?: null, 'ensure_goat_league_structure', json_encode([
                    'division_id' => $divisionId,
                    'league_id' => $leagueId,
                    'disabled_qualifier_ids' => $qualifierIds,
                ], JSON_UNESCAPED_UNICODE)]);
        }
        $pdo->commit();
        return ['division_id' => $divisionId, 'league_id' => $leagueId, 'disabled_qualifiers' => count($qualifierIds)];
    } catch (Throwable $error) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $error;
    }
}

function ygo_provisional_category_for_rank(int $rank): string
{
    global $divisionDefinitions;

    $cursor = 0;
    foreach ($divisionDefinitions as $definition) {
        $cursor += (int) ($definition['size'] ?? 0);
        if ($rank <= $cursor) {
            return (string) $definition['short'];
        }
    }
    $last = end($divisionDefinitions);
    return (string) (($last['short'] ?? '') ?: 'Genesis');
}

function ygo_compute_standings(PDO $pdo, string $phase = 'league'): array
{
    $rows = $pdo->query("
        SELECT d.id, d.name, d.seed_rank, dv.code AS division_code
        FROM sx_ygo_decks d
        JOIN sx_ygo_divisions dv ON dv.id = d.division_id
        WHERE d.active = 1
        ORDER BY d.seed_rank, d.id
    ")->fetchAll(PDO::FETCH_ASSOC);
    $standings = [];
    foreach ($rows as $deck) {
        $standings[(int) $deck['id']] = [
            'id' => (int) $deck['id'],
            'name' => (string) $deck['name'],
            'division' => (string) $deck['division_code'],
            'rank' => (int) $deck['seed_rank'],
            'pts' => 0,
            'pj' => 0,
            'pg' => 0,
            'pe' => 0,
            'pp' => 0,
            'cg' => 0,
            'cp' => 0,
            'dp' => 0,
        ];
    }
    $stmt = $pdo->prepare("
        SELECT m.deck_a_id, m.deck_b_id, r.result, r.score
        FROM sx_ygo_matches m
        JOIN sx_ygo_match_results r ON r.match_id = m.id
        WHERE m.phase = ? AND m.status = 'FINALIZADA'
    ");
    $stmt->execute([$phase]);
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $match) {
        $aId = (int) $match['deck_a_id'];
        $bId = (int) $match['deck_b_id'];
        if (!isset($standings[$aId], $standings[$bId])) {
            continue;
        }
        $scoreParts = array_map('intval', explode('-', (string) $match['score']));
        $scoreA = $scoreParts[0] ?? 0;
        $scoreB = $scoreParts[1] ?? 0;
        $standings[$aId]['pj']++;
        $standings[$bId]['pj']++;
        $standings[$aId]['cg'] += $scoreA;
        $standings[$aId]['cp'] += $scoreB;
        $standings[$bId]['cg'] += $scoreB;
        $standings[$bId]['cp'] += $scoreA;
        if ($match['result'] === 'A') {
            $standings[$aId]['pts'] += 3;
            $standings[$aId]['pg']++;
            $standings[$bId]['pp']++;
        } elseif ($match['result'] === 'B') {
            $standings[$bId]['pts'] += 3;
            $standings[$bId]['pg']++;
            $standings[$aId]['pp']++;
        } elseif ($match['result'] === 'E') {
            $standings[$aId]['pts']++;
            $standings[$bId]['pts']++;
            $standings[$aId]['pe']++;
            $standings[$bId]['pe']++;
        }
    }
    foreach ($standings as &$row) {
        $row['dp'] = $row['cg'] - $row['cp'];
    }
    unset($row);
    usort($standings, static fn(array $a, array $b): int => $b['pts'] <=> $a['pts'] ?: $b['dp'] <=> $a['dp'] ?: $b['cg'] <=> $a['cg'] ?: $a['rank'] <=> $b['rank']);
    return $standings;
}

function ygo_regenerate_league_matches(PDO $pdo, int $seasonId): void
{
    $divisionRows = $pdo->prepare('SELECT id, code FROM sx_ygo_divisions WHERE season_id = ? ORDER BY tier_order, id');
    $divisionRows->execute([$seasonId]);
    foreach ($divisionRows->fetchAll(PDO::FETCH_ASSOC) as $division) {
        $divisionId = (int) $division['id'];
        $pdo->prepare("DELETE r FROM sx_ygo_match_results r JOIN sx_ygo_matches m ON m.id = r.match_id WHERE m.division_id = ? AND m.phase = 'league'")->execute([$divisionId]);
        $pdo->prepare("DELETE FROM sx_ygo_matches WHERE division_id = ? AND phase = 'league'")->execute([$divisionId]);
        $decksStmt = $pdo->prepare('SELECT id, seed_rank, name, reason FROM sx_ygo_decks WHERE division_id = ? AND active = 1 ORDER BY seed_rank, name');
        $decksStmt->execute([$divisionId]);
        $deckRows = $decksStmt->fetchAll(PDO::FETCH_ASSOC);
        $fixture = buildFixtures(array_map(static fn(array $deck): array => [(int) $deck['seed_rank'], (string) $deck['name'], (string) ($deck['reason'] ?? '')], $deckRows));
        $deckIdByName = [];
        foreach ($deckRows as $deck) {
            $deckIdByName[(string) $deck['name']] = (int) $deck['id'];
        }
        foreach ($fixture as $round) {
            foreach ($round['matches'] as $match) {
                if (!isset($deckIdByName[$match['a']], $deckIdByName[$match['b']])) {
                    continue;
                }
                $pdo->prepare("
                    INSERT INTO sx_ygo_matches (division_id, phase, round_number, table_number, deck_a_id, deck_b_id, status)
                    VALUES (?, 'league', ?, ?, ?, ?, 'PENDIENTE')
                ")->execute([$divisionId, $round['ronda'], $match['mesa'], $deckIdByName[$match['a']], $deckIdByName[$match['b']]]);
            }
        }
    }
}

function ygo_qualifier_finalize_check(PDO $pdo, ?int $competitionId = null): array
{
    $competitionId = $competitionId ?: ygo_selected_qualifier_competition_id($pdo);
    if (!$competitionId) {
        return ['ok' => false, 'reasons' => ['Selecciona un clasificatorio valido.']];
    }
    $competitionStmt = $pdo->prepare("SELECT qualifier_duels_per_deck FROM sx_ygo_competitions WHERE id=? AND type='qualifier' AND active=1 LIMIT 1");
    $competitionStmt->execute([$competitionId]);
    $requiredMatches = max(2, min(5, (int) ($competitionStmt->fetchColumn() ?: 3)));
    $reasons = [];
    $matchesStmt = $pdo->prepare("
        SELECT round_number, status
        FROM sx_ygo_matches
        WHERE phase = 'qualifier' AND competition_id = ?
        ORDER BY round_number, table_number, id
    ");
    $matchesStmt->execute([$competitionId]);
    $matches = $matchesStmt->fetchAll(PDO::FETCH_ASSOC);
    if (!$matches) {
        return ['ok' => false, 'reasons' => ['No hay combates clasificatorios programados.']];
    }
    $rounds = [];
    foreach ($matches as $match) {
        $round = (int) $match['round_number'];
        $rounds[$round][] = (string) $match['status'];
    }
    if (empty($rounds[1])) {
        $reasons[] = 'No existe una primera ronda programada.';
    } elseif (array_filter($rounds[1], static fn(string $status): bool => $status !== 'FINALIZADA')) {
        $reasons[] = 'No se puede finalizar: toda la primera ronda debe estar jugada.';
    }
    foreach ($rounds as $round => $statuses) {
        $finished = count(array_filter($statuses, static fn(string $status): bool => $status === 'FINALIZADA'));
        if ($finished > 0 && $finished < count($statuses)) {
            $reasons[] = 'No se puede finalizar: la ronda ' . $round . ' esta en curso.';
        }
    }
    $standings = ygo_competition_standings($pdo, $competitionId, 'qualifier');
    if (!$standings || array_filter($standings, static fn(array $row): bool => (int) $row['pj'] < $requiredMatches)) {
        $reasons[] = 'Cada deck debe tener minimo ' . $requiredMatches . ' combates finalizados.';
    }
    return ['ok' => !$reasons, 'reasons' => array_values(array_unique($reasons))];
}

function ygo_finalize_qualifier(PDO $pdo, ?int $competitionId = null): bool
{
    $competitionId = $competitionId ?: ygo_selected_qualifier_competition_id($pdo);
    if (!$competitionId) {
        return false;
    }
    $seasonId = ygo_current_season_id($pdo);
    $divisionRows = $pdo->prepare('SELECT id, code FROM sx_ygo_divisions WHERE season_id = ? ORDER BY tier_order, id');
    $divisionRows->execute([$seasonId]);
    $official = $divisionRows->fetchAll(PDO::FETCH_ASSOC);
    if (!$official) {
        return false;
    }
    $check = ygo_qualifier_finalize_check($pdo, $competitionId);
    if (!$check['ok']) {
        return false;
    }
    $standings = ygo_competition_standings($pdo, $competitionId, 'qualifier');
    $total = count($standings);
    $base = intdiv($total, count($official));
    $extra = $total % count($official);
    $offset = 0;
    foreach ($official as $index => $division) {
        $size = $base + ($index < $extra ? 1 : 0);
        $slice = array_slice($standings, $offset, $size);
        foreach ($slice as $localRank => $deck) {
            $pdo->prepare('UPDATE sx_ygo_decks SET division_id = ?, seed_rank = ? WHERE id = ?')
                ->execute([(int) $division['id'], $localRank + 1, (int) $deck['id']]);
        }
        $pdo->prepare('UPDATE sx_ygo_divisions SET deck_count = ? WHERE id = ?')->execute([$size, (int) $division['id']]);
        $offset += $size;
    }
    ygo_regenerate_league_matches($pdo, $seasonId);
    $pdo->prepare('INSERT INTO sx_ygo_audit_log (entity_type, entity_id, action, payload) VALUES (?, ?, ?, ?)')
        ->execute(['competition', $competitionId, 'finalize_qualifier', json_encode(['total_decks' => $total], JSON_UNESCAPED_UNICODE)]);
    return true;
}

function ygo_first_division_id(PDO $pdo): int
{
    $seasonId = ygo_current_season_id($pdo);
    return (int) $pdo->query("SELECT id FROM sx_ygo_divisions WHERE season_id = $seasonId ORDER BY tier_order, id LIMIT 1")->fetchColumn();
}

function ygo_decks_for_division(PDO $pdo, ?int $divisionId = null): array
{
    if ($divisionId) {
        $stmt = $pdo->prepare('SELECT id, name, seed_rank FROM sx_ygo_decks WHERE active = 1 AND division_id = ? ORDER BY seed_rank, name');
        $stmt->execute([$divisionId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    return $pdo->query('SELECT id, name, seed_rank FROM sx_ygo_decks WHERE active = 1 ORDER BY seed_rank, name')->fetchAll(PDO::FETCH_ASSOC);
}

function ygo_clear_phase(PDO $pdo, int $divisionId, string $phase): void
{
    $pdo->prepare('DELETE r FROM sx_ygo_match_results r JOIN sx_ygo_matches m ON m.id = r.match_id WHERE m.division_id = ? AND m.phase = ?')->execute([$divisionId, $phase]);
    $pdo->prepare('DELETE FROM sx_ygo_matches WHERE division_id = ? AND phase = ?')->execute([$divisionId, $phase]);
}

function ygo_generate_knockout(PDO $pdo, int $divisionId, string $phase, array $decks): int
{
    ygo_clear_phase($pdo, $divisionId, $phase);
    $created = 0;
    $count = count($decks);
    if ($count < 2) {
        return 0;
    }
    $pairs = intdiv($count, 2);
    for ($i = 0; $i < $pairs; $i++) {
        $a = $decks[$i];
        $b = $decks[$count - 1 - $i];
        $pdo->prepare("
            INSERT INTO sx_ygo_matches (division_id, phase, round_number, table_number, bracket_round_label, bracket_slot, bracket_side, display_order, generation_number, deck_a_id, deck_b_id, status)
            VALUES (?, ?, 1, ?, ?, ?, 'main', ?, 1, ?, ?, 'PENDIENTE')
        ")->execute([$divisionId, $phase, $i + 1, 'Ronda 1', $i + 1, $i + 1, (int) $a['id'], (int) $b['id']]);
        $created++;
    }
    return $created;
}

function ygo_generate_friendlies(PDO $pdo): int
{
    $divisionId = ygo_first_division_id($pdo);
    $decks = ygo_decks_for_division($pdo);
    if (!$divisionId || count($decks) < 2) {
        return 0;
    }
    ygo_clear_phase($pdo, $divisionId, 'friendly');
    $createdPairs = [];
    $created = 0;
    $count = count($decks);
    foreach ($decks as $index => $deck) {
        foreach ([1, 5, 11, 17, 23] as $roundIndex => $offset) {
            $rival = $decks[($index + $offset) % $count];
            if ((int) $deck['id'] === (int) $rival['id']) {
                continue;
            }
            $pairKey = implode('-', [min((int) $deck['id'], (int) $rival['id']), max((int) $deck['id'], (int) $rival['id'])]);
            if (isset($createdPairs[$pairKey])) {
                continue;
            }
            $createdPairs[$pairKey] = true;
            $pdo->prepare("
                INSERT INTO sx_ygo_matches (division_id, phase, round_number, table_number, deck_a_id, deck_b_id, status)
                VALUES (?, 'friendly', ?, ?, ?, ?, 'PENDIENTE')
            ")->execute([$divisionId, $roundIndex + 1, $created + 1, (int) $deck['id'], (int) $rival['id']]);
            $created++;
        }
    }
    return $created;
}

function ygo_generate_cups(PDO $pdo): int
{
    $seasonId = ygo_current_season_id($pdo);
    $stmt = $pdo->prepare('SELECT id FROM sx_ygo_divisions WHERE season_id = ? ORDER BY tier_order, id');
    $stmt->execute([$seasonId]);
    $created = 0;
    foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $divisionId) {
        $created += ygo_generate_knockout($pdo, (int) $divisionId, 'cup', ygo_decks_for_division($pdo, (int) $divisionId));
    }
    return $created;
}

function ygo_generate_world(PDO $pdo): int
{
    $divisionId = ygo_first_division_id($pdo);
    $decks = array_slice(ygo_decks_for_division($pdo), 0, 24);
    if (!$divisionId || count($decks) < 2) {
        return 0;
    }
    ygo_clear_phase($pdo, $divisionId, 'world');
    $created = 0;
    for ($group = 0; $group < 6; $group++) {
        $groupDecks = array_slice($decks, $group * 4, 4);
        for ($i = 0; $i < count($groupDecks); $i++) {
            for ($j = $i + 1; $j < count($groupDecks); $j++) {
                $pdo->prepare("
                    INSERT INTO sx_ygo_matches (division_id, phase, round_number, table_number, deck_a_id, deck_b_id, status)
                    VALUES (?, 'world', ?, ?, ?, ?, 'PENDIENTE')
                ")->execute([$divisionId, $group + 1, $created + 1, (int) $groupDecks[$i]['id'], (int) $groupDecks[$j]['id']]);
                $created++;
            }
        }
    }
    return $created;
}

function ygo_generate_leagues(PDO $pdo): int
{
    $seasonId = ygo_current_season_id($pdo);
    ygo_regenerate_league_matches($pdo, $seasonId);
    return (int) $pdo->query("SELECT COUNT(*) FROM sx_ygo_matches WHERE phase = 'league'")->fetchColumn();
}

function ygo_selected_qualifier_competition_id(PDO $pdo): ?int
{
    $postedId = (int) ($_POST['qualifier_competition_id'] ?? 0);
    if ($postedId > 0) {
        $stmt = $pdo->prepare("SELECT id FROM sx_ygo_competitions WHERE id=? AND type='qualifier' AND active=1 LIMIT 1");
        $stmt->execute([$postedId]);
        $found = (int) $stmt->fetchColumn();
        if ($found > 0) {
            return $found;
        }
    }
    $stmt = $pdo->prepare("SELECT id FROM sx_ygo_competitions WHERE season_id=? AND type='qualifier' AND active=1 ORDER BY FIELD(status, 'active', 'draft', 'completed'), id LIMIT 1");
    $stmt->execute([ygo_current_season_id($pdo)]);
    $found = (int) $stmt->fetchColumn();
    return $found > 0 ? $found : null;
}

function ygo_competition_phase_match_count(PDO $pdo, int $competitionId, string $phase): int
{
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM sx_ygo_matches WHERE competition_id = ? AND phase = ?');
    $stmt->execute([$competitionId, $phase]);
    return (int) $stmt->fetchColumn();
}

function ygo_competition_phase_finished(PDO $pdo, int $competitionId, string $phase): bool
{
    $stmt = $pdo->prepare("
        SELECT
            COUNT(*) AS total,
            SUM(CASE WHEN m.status = 'FINALIZADA' AND r.result IN ('A', 'B', 'DRAW') THEN 1 ELSE 0 END) AS closed
        FROM sx_ygo_matches m
        LEFT JOIN sx_ygo_match_results r ON r.match_id = m.id
        WHERE m.competition_id = ? AND m.phase = ?
    ");
    $stmt->execute([$competitionId, $phase]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
    $total = (int) ($row['total'] ?? 0);
    return $total > 0 && $total === (int) ($row['closed'] ?? 0);
}

function ygo_competition_round_winners(PDO $pdo, int $competitionId, string $phase, int $round): array
{
    $stmt = $pdo->prepare("
        SELECT m.deck_a_id, m.deck_b_id, r.result
        FROM sx_ygo_matches m
        LEFT JOIN sx_ygo_match_results r ON r.match_id = m.id
        WHERE m.competition_id = ? AND m.phase = ? AND m.round_number = ? AND m.status = 'FINALIZADA'
        ORDER BY m.table_number, m.id
    ");
    $stmt->execute([$competitionId, $phase, $round]);
    $winners = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $winner = $row['result'] === 'A' ? (int) $row['deck_a_id'] : ($row['result'] === 'B' ? (int) $row['deck_b_id'] : 0);
        if ($winner<=0) throw new InvalidArgumentException('La llave requiere un ganador valido en todos sus combates; no se avanzara con empate.');
        if ($winner > 0) {
            $winners[] = $winner;
        }
    }
    if($phase==='cup') $winners=array_merge($winners,json_decode(ygo_settings($pdo)['cup_bye_'.$competitionId.'_'.$round] ?? '[]',true) ?: []);
    return array_values(array_unique($winners));
}

function ygo_deck_ids_for_ruleset(PDO $pdo, string $ruleset): array
{
    return ygo_format_deck_ids($pdo, $ruleset);
}

function ygo_knockout_target_size(int $participants): int
{
    if ($participants >= 16) {
        return 16;
    }
    if ($participants >= 8) {
        return 8;
    }
    if ($participants >= 4) {
        return 4;
    }
    return min(2, $participants);
}

function ygo_create_competition_knockout_round(PDO $pdo, array $competition, string $phase, array $deckIds, int $round, string $label): int
{
    $competitionId = (int) ($competition['id'] ?? 0);
    $divisionId = !empty($competition['division_id']) ? (int) $competition['division_id'] : null;
    $deckIds = array_values(array_unique(array_filter(array_map('intval', $deckIds), static fn(int $id): bool => $id > 0)));
    if ($competitionId <= 0 || count($deckIds) < 2) {
        return 0;
    }
    $exists = $pdo->prepare('SELECT COUNT(*) FROM sx_ygo_matches WHERE competition_id = ? AND phase = ? AND round_number = ?');
    $exists->execute([$competitionId, $phase, $round]);
    if ((int) $exists->fetchColumn() > 0) {
        return 0;
    }
    $insert = $pdo->prepare('
        INSERT INTO sx_ygo_matches
            (division_id, competition_id, game_rule_id, phase, match_format, best_of, participants_per_side, round_number, table_number, bracket_round_label, bracket_slot, bracket_side, display_order, generation_number, deck_a_id, deck_b_id, status, scheduled_on, starts_at, venue, notes)
        VALUES
            (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, "main", ?, 1, ?, ?, "PENDIENTE", ?, "19:00:00", "Station X Tulua", ?)
    ');
    $pairs = intdiv(count($deckIds), 2);
    $created = 0;
    $seasonId = ygo_current_season_id($pdo);
    $scheduledBase = (string) (($competition['starts_on'] ?? '') ?: (new DateTimeImmutable())->format('Y-m-d'));
    for ($i = 0; $i < $pairs; $i++) {
        $deckA = $deckIds[$i];
        $deckB = $deckIds[count($deckIds) - 1 - $i];
        $displayOrder = ($round * 100) + ($i + 1);
        $scheduledOn = (new DateTimeImmutable($scheduledBase))->modify('+' . max(0, $round - 1) . ' days')->format('Y-m-d');
        $insert->execute([
            $divisionId,
            $competitionId,
            (int) ($competition['game_rule_id'] ?? 0) ?: null,
            $phase,
            (string) (($competition['match_format'] ?? '') ?: '1v1'),
            (int) (($competition['best_of'] ?? 0) ?: 3),
            max(1, min(3, (int) (($competition['participants_per_side'] ?? 0) ?: 1))),
            $round,
            $i + 1,
            $label,
            $i + 1,
            $displayOrder,
            $deckA,
            $deckB,
            $scheduledOn,
            'Generado por avance automatico de ' . $phase . '.',
        ]);
        $created++;
    }
    $program=ygo_fixed_program($pdo);
    if($phase==='cup' && count($deckIds)%2===1) {
        $bye=(int)$deckIds[intdiv(count($deckIds),2)];
        ygo_save_setting($pdo,'cup_bye_'.$competitionId.'_'.$round,json_encode([$bye]));
        $pdo->prepare('INSERT INTO sx_ygo_audit_log(entity_type,entity_id,action,payload) VALUES(?,?,?,?)')->execute(['competition',$competitionId,'cup_round_bye',json_encode(['round'=>$round,'deck_id'=>$bye])]);
    }
    ygo_admission_schedule_stage($pdo,$competition,(string)($competition['starts_on']??date('Y-m-d')),(string)($competition['schedule_pattern']??'consecutive'),max(1,(int)($competition['qualifier_matches_per_day']??1)),$phase,$round);
    ygo_schedule_fixed_cup_round($pdo,$competitionId,$phase,$round);
    ygo_rebuild_competition_calendar_events_from_matches($pdo, $seasonId, $competitionId, $phase);
    return $created;
}

function ygo_record_competition_honor(PDO $pdo, int $competitionId): int
{
    if ($competitionId <= 0) {
        return 0;
    }
    $exists = $pdo->prepare('SELECT COUNT(*) FROM sx_ygo_honors WHERE competition_id = ?');
    $exists->execute([$competitionId]);
    if ((int) $exists->fetchColumn() > 0) {
        return 0;
    }
    $competitionStmt = $pdo->prepare('SELECT * FROM sx_ygo_competitions WHERE id = ? AND active = 1 LIMIT 1');
    $competitionStmt->execute([$competitionId]);
    $competition = $competitionStmt->fetch(PDO::FETCH_ASSOC) ?: [];
    if (!$competition) {
        return 0;
    }
    if(($competition['execution_model']??'')==='manual')return 0;
    $workflow=ygo_workflow_load($pdo,$competition);
    if($workflow!==null){if(!empty($workflow['complete']) && $workflow['champion']!==null){ygo_workflow_honor($pdo,$competition,(int)$workflow['champion'],(int)$workflow['runner_up']);return 1;}return 0;}
    $finalStmt = $pdo->prepare("
        SELECT m.deck_a_id, m.deck_b_id, r.result
        FROM sx_ygo_matches m
        JOIN sx_ygo_match_results r ON r.match_id = m.id
        WHERE m.competition_id = ? AND m.status = 'FINALIZADA' AND r.result IN ('A', 'B')
          AND m.phase IN ('final', 'cup', 'top_cut', 'playoff')
        ORDER BY m.round_number DESC, m.id DESC
        LIMIT 1
    ");
    $finalStmt->execute([$competitionId]);
    $final = $finalStmt->fetch(PDO::FETCH_ASSOC) ?: [];
    $championId = 0;
    $runnerId = 0;
    $usesDecisiveFinal = in_array((string) ($competition['type'] ?? ''), ['cup', 'final', 'world'], true)
        || in_array((string) ($competition['execution_model'] ?? ''), ['single_elimination', 'double_elimination', 'bracket', 'series'], true);
    if ($usesDecisiveFinal && $final) {
        $championId = $final['result'] === 'A' ? (int) $final['deck_a_id'] : (int) $final['deck_b_id'];
        $runnerId = $final['result'] === 'A' ? (int) $final['deck_b_id'] : (int) $final['deck_a_id'];
    } else {
        $ranking = ygo_competition_standings($pdo, $competitionId);
        $championId = (int) ($ranking[0]['id'] ?? 0);
        $runnerId = (int) ($ranking[1]['id'] ?? 0);
    }
    if ($championId <= 0) {
        return 0;
    }
    $teamStmt = $pdo->prepare('SELECT team_id FROM sx_ygo_decks WHERE id = ? LIMIT 1');
    $teamStmt->execute([$championId]);
    $championTeamId = (int) ($teamStmt->fetchColumn() ?: 0);
    $runnerTeamId = 0;
    if ($runnerId > 0) {
        $teamStmt->execute([$runnerId]);
        $runnerTeamId = (int) ($teamStmt->fetchColumn() ?: 0);
    }
    $pdo->prepare('INSERT INTO sx_ygo_honors (season_id, competition_id, champion_deck_id, champion_team_id, runner_up_deck_id, runner_up_team_id, title, notes) VALUES (?, ?, ?, ?, ?, ?, ?, ?)')
        ->execute([
            (int) $competition['season_id'],
            $competitionId,
            $championId,
            $championTeamId > 0 ? $championTeamId : null,
            $runnerId > 0 ? $runnerId : null,
            $runnerTeamId > 0 ? $runnerTeamId : null,
            'Campeon - ' . (string) $competition['name'],
            'Registrado automaticamente al cerrar el evento.',
        ]);
    return 1;
}

function ygo_apply_league_division_movements(PDO $pdo, int $competitionId): int
{
    return ygo_apply_configured_league_movements($pdo, $competitionId);
}

function ygo_advance_competition_phase(PDO $pdo, int $competitionId): int
{
    return ygo_with_event_write_lock($pdo, fn() => ygo_advance_competition_phase_serialized($pdo,$competitionId));
}

function ygo_advance_competition_phase_serialized(PDO $pdo, int $competitionId): int
{
    $owns=!$pdo->inTransaction();if($owns)$pdo->beginTransaction();
    try {
        $lock=$pdo->prepare('SELECT * FROM sx_ygo_competitions WHERE id=? FOR UPDATE');$lock->execute([$competitionId]);$c=$lock->fetch(PDO::FETCH_ASSOC);
        if(!$c) {if($owns)$pdo->commit();return 0;}
        if(ygo_admission_load($pdo,$c)!==null){$roster=$pdo->prepare('SELECT deck_id FROM sx_ygo_competition_decks WHERE competition_id=? AND active=1 ORDER BY sort_order,id');$roster->execute([$competitionId]);ygo_admission_require($pdo,$c,array_map('intval',$roster->fetchAll(PDO::FETCH_COLUMN)),false);}
        if((ygo_team_kind($c)!=='' || in_array((string)$c['execution_model'],ygo_workflow_models(),true)) && ygo_workflow_load($pdo,$c)!==null){
            $result=ygo_workflow_generate($pdo,$c);if($owns)$pdo->commit();return $result;
        }
        ygo_season_require_open($pdo,(int)$c['season_id']);
        if(in_array(ygo_normalize_status_key((string)$c['status']),['completed','closed','cancelled','archived'],true)) throw new InvalidArgumentException('El evento esta cerrado y no admite avance.');
        ygo_require_supported_model($c);
        $result=ygo_advance_competition_phase_locked($pdo,$competitionId);
        if($owns)$pdo->commit();return $result;
    } catch(Throwable $e) {if($owns && $pdo->inTransaction())$pdo->rollBack();throw $e;}
}

function ygo_advance_competition_phase_locked(PDO $pdo, int $competitionId): int
{
    $stmt = $pdo->prepare('SELECT * FROM sx_ygo_competitions WHERE id = ? AND active = 1 LIMIT 1');
    $stmt->execute([$competitionId]);
    $competition = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
    if (!$competition) {
        return 0;
    }
    $type = (string) (($competition['type'] ?? '') ?: 'tournament');
    $execution = (string) (($competition['execution_model'] ?? '') ?: 'manual');
    if(in_array($execution,ygo_workflow_models(),true))throw new InvalidArgumentException('Este evento anterior no tiene un reglamento versionado. No se reinterpretaran sus combates.');
    $phaseStructure = strtoupper((string) (($competition['phase_structure'] ?? '') ?: 'NONE'));
    $created = 0;

    if (
        $type === 'qualifier'
        && $execution === 'swiss'
        && $phaseStructure === 'NONE'
        && strtoupper((string) (($competition['event_flow'] ?? '') ?: '')) === 'QUALIFIES_TO_EVENT'
        && strtoupper((string) (($competition['advancement_rule'] ?? '') ?: '')) === 'RANKING_THRESHOLD'
    ) {
        $pdo->prepare('INSERT INTO sx_ygo_audit_log (entity_type, entity_id, action, payload) VALUES (?, ?, ?, ?)')
            ->execute(['competition', $competitionId, 'blocked_advance_competition_phase', json_encode(['reason' => 'division_qualifier_has_no_next_phase'], JSON_UNESCAPED_UNICODE)]);
        return 0;
    }

    $advanceKnockout = function (string $phase) use ($pdo, $competition, $competitionId): int {
        $roundStmt = $pdo->prepare('SELECT COALESCE(MAX(round_number), 0) FROM sx_ygo_matches WHERE competition_id = ? AND phase = ?');
        $roundStmt->execute([$competitionId, $phase]);
        $round = (int) $roundStmt->fetchColumn();
        if ($round <= 0) {
            return 0;
        }
        $pendingStmt = $pdo->prepare("SELECT COUNT(*) FROM sx_ygo_matches WHERE competition_id = ? AND phase = ? AND round_number = ? AND status <> 'FINALIZADA'");
        $pendingStmt->execute([$competitionId, $phase, $round]);
        if ((int) $pendingStmt->fetchColumn() > 0) {
            return 0;
        }
        $winners = ygo_competition_round_winners($pdo, $competitionId, $phase, $round);
        if (count($winners) === 1) {
            ygo_record_competition_honor($pdo, $competitionId);
            return 0;
        }
        if (count($winners) < 2) {
            return 0;
        }
        $nextRound = $round + 1;
        $label = count($winners) <= 2 ? 'Final' : 'Ronda ' . $nextRound;
        return ygo_create_competition_knockout_round($pdo, $competition, $phase, $winners, $nextRound, $label);
    };

    if (in_array($execution, ['single_elimination', 'double_elimination', 'top_cut', 'playoffs', 'bracket', 'series'], true)) {
        $phase = ygo_competition_phase_match_count($pdo,$competitionId,'cup')>0 ? 'cup' : ($type === 'world' ? 'world' : ($type === 'final' ? 'final' : 'cup'));
        $created += $advanceKnockout($phase);
    }

    if ($created === 0 && (in_array($execution, ['swiss_top_cut', 'top_cut'], true) || str_contains($phaseStructure, 'TOP_CUT'))) {
        $targetPhase = 'top_cut';
        if (ygo_competition_phase_match_count($pdo, $competitionId, $targetPhase) > 0) {
            $created += $advanceKnockout($targetPhase);
        } else {
            $basePhase = ygo_competition_phase_match_count($pdo, $competitionId, 'swiss') > 0 ? 'swiss' : 'qualifier';
            if (ygo_competition_phase_finished($pdo, $competitionId, $basePhase)) {
                $ranking = ygo_competition_standings($pdo, $competitionId);
                $topSize = ygo_knockout_target_size(count($ranking));
                $deckIds = array_map(static fn(array $row): int => (int) ($row['id'] ?? 0), array_slice($ranking, 0, $topSize));
                $created += ygo_create_competition_knockout_round($pdo, $competition, $targetPhase, $deckIds, 1, 'Top Cut');
            }
        }
    }

    if ($created === 0 && (in_array($execution, ['groups_knockout'], true) || str_contains($phaseStructure, 'GROUPS'))) {
        $targetPhase = 'playoff';
        if (ygo_competition_phase_match_count($pdo, $competitionId, $targetPhase) > 0) {
            $created += $advanceKnockout($targetPhase);
        } elseif (ygo_competition_phase_finished($pdo, $competitionId, 'groups')) {
            $ranking = ygo_competition_standings($pdo, $competitionId);
            $topSize = ygo_knockout_target_size(count($ranking));
            $deckIds = array_map(static fn(array $row): int => (int) ($row['id'] ?? 0), array_slice($ranking, 0, $topSize));
            $created += ygo_create_competition_knockout_round($pdo, $competition, $targetPhase, $deckIds, 1, 'Eliminatoria');
        }
    }

    if ($created === 0 && $type === 'league') {
        $targetPhase = 'playoff';
        if (ygo_competition_phase_match_count($pdo, $competitionId, $targetPhase) > 0) {
            $created += $advanceKnockout($targetPhase);
        } elseif (in_array($execution, ['season_playoffs', 'playoffs'], true) && ygo_competition_phase_finished($pdo, $competitionId, 'league')) {
            $ranking = ygo_competition_standings($pdo, $competitionId);
            $topSize = ygo_knockout_target_size(count($ranking));
            $deckIds = array_map(static fn(array $row): int => (int) ($row['id'] ?? 0), array_slice($ranking, 0, $topSize));
            $created += ygo_create_competition_knockout_round($pdo, $competition, $targetPhase, $deckIds, 1, 'Playoff');
        }
    }

    if ($created > 0) {
        $pdo->prepare('INSERT INTO sx_ygo_audit_log (entity_type, entity_id, action, payload) VALUES (?, ?, ?, ?)')
            ->execute(['competition', $competitionId, 'advance_competition_phase', json_encode(['created' => $created, 'type' => $type, 'execution_model' => $execution], JSON_UNESCAPED_UNICODE)]);
    }
    return $created;
}

function ygo_advance_phase(PDO $pdo, string $phase, ?int $competitionId = null): int
{
    if ($competitionId !== null && $competitionId > 0) {
        return ygo_advance_competition_phase($pdo, $competitionId);
    }
    $divisionId = ygo_first_division_id($pdo);
    $rows = $pdo->prepare("
        SELECT m.division_id, m.phase, m.round_number, m.table_number, m.deck_a_id, m.deck_b_id, r.result
        FROM sx_ygo_matches m
        JOIN sx_ygo_match_results r ON r.match_id = m.id
        WHERE m.phase = ? AND m.status = 'FINALIZADA'
        ORDER BY m.division_id, m.round_number, m.table_number
    ");
    $rows->execute([$phase]);
    $winnersByDivision = [];
    foreach ($rows->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $winner = $row['result'] === 'A' ? (int) $row['deck_a_id'] : ($row['result'] === 'B' ? (int) $row['deck_b_id'] : 0);
        if ($winner > 0) {
            $winnersByDivision[(int) $row['division_id']][] = $winner;
        }
    }
    $created = 0;
    foreach ($winnersByDivision as $divId => $winners) {
        $nextRound = (int) $pdo->query("SELECT COALESCE(MAX(round_number), 0) + 1 FROM sx_ygo_matches WHERE division_id = $divId AND phase = " . $pdo->quote($phase))->fetchColumn();
        for ($i = 0; $i + 1 < count($winners); $i += 2) {
            $slot = intdiv($i, 2) + 1;
            $pdo->prepare("
                INSERT IGNORE INTO sx_ygo_matches (division_id, phase, round_number, table_number, bracket_round_label, bracket_slot, bracket_side, display_order, generation_number, deck_a_id, deck_b_id, status)
                VALUES (?, ?, ?, ?, ?, ?, 'main', ?, 1, ?, ?, 'PENDIENTE')
            ")->execute([$divId ?: $divisionId, $phase, $nextRound, $slot, 'Ronda ' . $nextRound, $slot, (($nextRound - 1) * 100) + $slot, $winners[$i], $winners[$i + 1]]);
            $created++;
        }
    }
    return $created;
}

function ygo_auto_schedule_qualifier(PDO $pdo): int
{
    $startDate = (string) ($_POST['qualifier_schedule_start'] ?? (new DateTimeImmutable())->format('Y-m-d'));
    $startTime = (string) ($_POST['qualifier_schedule_time'] ?? '19:00');
    $tablesPerDay = max(1, min(12, (int) ($_POST['qualifier_schedule_tables'] ?? 6)));
    $matchesPerDay = max(1, min(5, (int) ($_POST['qualifier_matches_per_day'] ?? 1)));
    $tablesPerDay = $matchesPerDay;
    $minutesPerRound = max(20, min(180, (int) ($_POST['qualifier_schedule_minutes'] ?? 45)));
    $base = DateTimeImmutable::createFromFormat('Y-m-d H:i', $startDate . ' ' . $startTime) ?: new DateTimeImmutable();
    $competitionId = ygo_selected_qualifier_competition_id($pdo);

    $stmt = $pdo->prepare("
        SELECT id, round_number, table_number
        FROM sx_ygo_matches
        WHERE phase = 'qualifier'
          AND (? IS NULL OR competition_id = ? OR competition_id IS NULL)
          AND status <> 'FINALIZADA'
        ORDER BY round_number, table_number, id
    ");
    $stmt->execute([$competitionId, $competitionId]);
    $matches = $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
    $updated = 0;
    foreach ($matches as $index => $match) {
        $dayOffset = intdiv($index, $tablesPerDay);
        $slot = $index % $tablesPerDay;
        $dateTime = $base->modify('+' . $dayOffset . ' days')->modify('+' . ($slot * $minutesPerRound) . ' minutes');
        $pdo->prepare('UPDATE sx_ygo_matches SET competition_id=COALESCE(competition_id, ?), scheduled_on=?, starts_at=?, venue=COALESCE(NULLIF(venue, ""), ?), notes=COALESCE(NULLIF(notes, ""), ?) WHERE id=?')
            ->execute([
                $competitionId,
                $dateTime->format('Y-m-d'),
                $dateTime->format('H:i:s'),
                'Station X Tulua',
                'Programado automaticamente desde Gestion.',
                (int) $match['id'],
            ]);
        $updated++;
    }
    if ($competitionId) {
        $pdo->prepare('UPDATE sx_ygo_competitions SET qualifier_matches_per_day = ? WHERE id = ?')
            ->execute([$matchesPerDay, $competitionId]);
    }
    $pdo->prepare('INSERT INTO sx_ygo_audit_log (entity_type, entity_id, action, payload) VALUES (?, ?, ?, ?)')
        ->execute(['competition', $competitionId, 'auto_schedule_qualifier', json_encode(['updated' => $updated, 'start' => $startDate, 'time' => $startTime, 'matches_per_day' => $matchesPerDay], JSON_UNESCAPED_UNICODE)]);
    return $updated;
}

function ygo_normalize_existing_decks_to_tcg_advanced(PDO $pdo): int
{
    $done = (int) $pdo->query("SELECT COUNT(*) FROM sx_ygo_audit_log WHERE entity_type = 'deck' AND action = 'normalize_existing_decks_tcg_advanced'")->fetchColumn();
    if ($done > 0) {
        return 0;
    }
    $seasonId = ygo_current_season_id($pdo);
    $stmt = $pdo->prepare("SELECT id FROM sx_ygo_divisions WHERE season_id = ? AND code = 'TCG_AVANZADO' AND active = 1 LIMIT 1");
    $stmt->execute([$seasonId]);
    $tcgDivisionId = (int) $stmt->fetchColumn();
    if ($tcgDivisionId <= 0) {
        return 0;
    }
    $update = $pdo->prepare('UPDATE sx_ygo_decks SET division_id = ? WHERE active = 1 AND (division_id IS NULL OR division_id <> ?)');
    $update->execute([$tcgDivisionId, $tcgDivisionId]);
    $updated = $update->rowCount();
    $pdo->prepare('INSERT INTO sx_ygo_audit_log (entity_type, entity_id, action, payload) VALUES (?, ?, ?, ?)')
        ->execute(['deck', null, 'normalize_existing_decks_tcg_advanced', json_encode(['division_id' => $tcgDivisionId, 'updated' => $updated], JSON_UNESCAPED_UNICODE)]);
    return (int) $updated;
}

function ygo_rebuild_qualifier_calendar(PDO $pdo): int
{
    $today = (new DateTimeImmutable())->format('Y-m-d');
    $startDate = (string) ($_POST['qualifier_schedule_start'] ?? $today);
    $startTime = (string) ($_POST['qualifier_schedule_time'] ?? '19:00');
    $matchesPerDay = max(1, min(5, (int) ($_POST['qualifier_matches_per_day'] ?? 1)));
    $dayInterval = max(1, min(7, (int) ($_POST['qualifier_day_interval'] ?? 1)));
    $minutesPerMatch = max(20, min(180, (int) ($_POST['qualifier_schedule_minutes'] ?? 45)));
    $base = DateTimeImmutable::createFromFormat('Y-m-d H:i', $startDate . ' ' . $startTime) ?: new DateTimeImmutable($today . ' 19:00');
    $seasonId = ygo_current_season_id($pdo);
    $divisionId = ygo_first_division_id($pdo);
    $competitionId = ygo_selected_qualifier_competition_id($pdo);
    if ($competitionId) {
        $pdo->prepare('UPDATE sx_ygo_competitions SET qualifier_matches_per_day = ? WHERE id = ?')
            ->execute([$matchesPerDay, $competitionId]);
    }
    $deckIds = array_map('intval', $pdo->query('SELECT id FROM sx_ygo_decks WHERE active = 1 ORDER BY seed_rank, id')->fetchAll(PDO::FETCH_COLUMN));
    if (!$divisionId || count($deckIds) < 2) {
        return 0;
    }

    $pdo->prepare("DELETE r FROM sx_ygo_match_results r INNER JOIN sx_ygo_matches m ON m.id = r.match_id WHERE m.phase = 'qualifier' AND m.status <> 'FINALIZADA' AND (? IS NULL OR m.competition_id = ? OR m.competition_id IS NULL)")->execute([$competitionId, $competitionId]);
    $pdo->prepare("DELETE FROM sx_ygo_matches WHERE phase = 'qualifier' AND status <> 'FINALIZADA' AND (? IS NULL OR competition_id = ? OR competition_id IS NULL)")->execute([$competitionId, $competitionId]);
    $pdo->prepare("DELETE FROM sx_ygo_events WHERE season_id=? AND event_type='qualifier' AND status <> 'completed' AND (? IS NULL OR competition_id = ?)")->execute([$seasonId, $competitionId, $competitionId]);

    $players = $deckIds;
    if (count($players) % 2 === 1) {
        $players[] = 0;
    }
    $opponents = [];
    $played = [];
    foreach ($deckIds as $deckId) {
        $opponents[$deckId] = [];
        $played[$deckId] = 0;
    }
    $pairs = [];
    $roundCount = 3;
    $n = count($players);
    for ($round = 1; $round <= $roundCount; $round++) {
        $table = 1;
        for ($i = 0; $i < $n / 2; $i++) {
            $a = (int) $players[$i];
            $b = (int) $players[$n - 1 - $i];
            if ($a <= 0 || $b <= 0 || $a === $b) {
                continue;
            }
            $key = min($a, $b) . '-' . max($a, $b);
            if (isset($pairs[$key])) {
                continue;
            }
            $pairs[$key] = [$round, $table++, $a, $b];
            $opponents[$a][$b] = true;
            $opponents[$b][$a] = true;
            $played[$a]++;
            $played[$b]++;
        }
        $fixed = array_shift($players);
        $last = array_pop($players);
        array_unshift($players, $fixed, $last);
    }

    $table = 1;
    while (count(array_filter($played, static fn(int $count): bool => $count < 3)) > 0) {
        $needs = array_keys(array_filter($played, static fn(int $count): bool => $count < 3));
        $a = (int) array_shift($needs);
        $b = 0;
        foreach (array_merge($needs, $deckIds) as $candidate) {
            $candidate = (int) $candidate;
            if ($candidate !== $a && empty($opponents[$a][$candidate])) {
                $b = $candidate;
                break;
            }
        }
        if ($b <= 0) {
            break;
        }
        $key = min($a, $b) . '-' . max($a, $b);
        $pairs[$key] = [4, $table++, $a, $b];
        $opponents[$a][$b] = true;
        $opponents[$b][$a] = true;
        $played[$a]++;
        $played[$b]++;
    }

    uasort($pairs, static fn(array $a, array $b): int => [$a[0], $a[1]] <=> [$b[0], $b[1]]);
    $total = count($pairs);
    $insert = $pdo->prepare("
        INSERT INTO sx_ygo_matches (division_id, competition_id, phase, round_number, table_number, deck_a_id, deck_b_id, status, scheduled_on, starts_at, venue, notes)
        VALUES (?, ?, 'qualifier', ?, ?, ?, ?, 'PENDIENTE', ?, ?, ?, ?)
    ");
    $created = 0;
    foreach (array_values($pairs) as $index => $pair) {
        $dayIndex = intdiv($index, $matchesPerDay);
        $slot = $index % $matchesPerDay;
        if ($total % $matchesPerDay === 1 && $index === $total - 1 && $dayIndex > 0) {
            $dayIndex--;
            $slot = $matchesPerDay;
        }
        $dateTime = $base->modify('+' . ($dayIndex * $dayInterval) . ' days')->modify('+' . ($slot * $minutesPerMatch) . ' minutes');
        $insert->execute([
            $divisionId,
            $competitionId,
            $pair[0],
            $pair[1],
            $pair[2],
            $pair[3],
            $dateTime->format('Y-m-d'),
            $dateTime->format('H:i:s'),
            'Station X Tulua',
            'Clasificatorio reprogramado desde hoy, con minimo 4 combates por jornada.',
        ]);
        $created++;
    }
    $eventStmt = $pdo->prepare("
        INSERT INTO sx_ygo_events (season_id, competition_id, division_id, code, title, event_type, status, event_date, starts_at, venue, notes)
        VALUES (?, ?, ?, ?, ?, 'qualifier', 'scheduled', ?, ?, 'Station X Tulua', ?)
    ");
    $days = [];
    foreach (array_values($pairs) as $index => $_pair) {
        $dayIndex = intdiv($index, $matchesPerDay);
        if ($total % $matchesPerDay === 1 && $index === $total - 1 && $dayIndex > 0) {
            $dayIndex--;
        }
        $day = $base->modify('+' . ($dayIndex * $dayInterval) . ' days')->format('Y-m-d');
        $days[$day] = ($days[$day] ?? 0) + 1;
    }
    $journey = 1;
    foreach ($days as $day => $count) {
        $eventStmt->execute([
            $seasonId,
            $competitionId,
            $divisionId,
            'clasificatorio-' . ($competitionId ?: 'general') . '-jornada-' . $journey,
            'Clasificatorio - Jornada ' . $journey,
            $day,
            $startTime . ':00',
            $count . ' combates programados. Registro habilitado desde Clasificatorio.',
        ]);
        $journey++;
    }
    $dayKeys = array_keys($days);
    $endDate = $dayKeys ? (string) end($dayKeys) : $base->modify('+' . ((intdiv(max(0, $total - 1), $matchesPerDay)) * $dayInterval) . ' days')->format('Y-m-d');
    if ($competitionId) {
        $pdo->prepare("UPDATE sx_ygo_competitions SET status='active', starts_on=?, ends_on=? WHERE id=?")
            ->execute([$startDate, $endDate, $competitionId]);
    }
    $pdo->prepare('INSERT INTO sx_ygo_audit_log (entity_type, entity_id, action, payload) VALUES (?, ?, ?, ?)')
        ->execute(['competition', $competitionId, 'rebuild_qualifier_calendar', json_encode(['created' => $created, 'start' => $startDate, 'interval_days' => $dayInterval, 'matches_per_day' => $matchesPerDay], JSON_UNESCAPED_UNICODE)]);
    return $created;
}

function ygo_next_competition_match_date(DateTimeImmutable $start, string $pattern, int $index, int $matchesPerDay = 1): string
{
    $dayIndex = intdiv(max(0, $index), max(1, min(5, $matchesPerDay)));
    if ($pattern === 'weekend') {
        $date = $start;
        $weekendIndex = 0;
        while ((int) $date->format('N') < 6) {
            $date = $date->modify('+1 day');
        }
        while ($weekendIndex < $dayIndex) {
            $date = $date->modify('+1 day');
            if ((int) $date->format('N') >= 6) {
                $weekendIndex++;
            }
        }
        return $date->format('Y-m-d');
    }
    $interval = ['consecutive' => 1, 'every_other_day' => 2, 'every_2_days' => 3, 'every_3_days' => 4][$pattern] ?? 1;
    return $start->modify('+' . ($dayIndex * $interval) . ' days')->format('Y-m-d');
}

function ygo_secure_shuffle(array $items): array
{
    $items = array_values($items);
    for ($i = count($items) - 1; $i > 0; $i--) {
        $j = random_int(0, $i);
        if ($i !== $j) {
            [$items[$i], $items[$j]] = [$items[$j], $items[$i]];
        }
    }
    return $items;
}

function ygo_schedule_qualifier_duels(PDO $pdo, array $competition, array $selectedDeckIds, string $startDate, string $pattern, string $matchFormat, int $participantsPerSide, int $duelsPerDeck, int $matchesPerDay = 1, string $phase = 'qualifier'): int
{
    if(!$pdo->inTransaction()){$pdo->beginTransaction();try{$n=ygo_schedule_qualifier_duels($pdo,$competition,$selectedDeckIds,$startDate,$pattern,$matchFormat,$participantsPerSide,$duelsPerDeck,$matchesPerDay,$phase);$pdo->commit();return $n;}catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();throw $e;}}

    if(!in_array((string)($competition['execution_model']??'manual'),['swiss','swiss_top_cut'],true)) throw new InvalidArgumentException('El programador suizo no puede ejecutar otro motor.');

    ygo_require_execution_type((string)$competition['type'], (string)($competition['execution_model'] ?? 'manual'));
    $fresh=$pdo->prepare('SELECT * FROM sx_ygo_competitions WHERE id=? FOR UPDATE');$fresh->execute([$competition['id']]);$competition=$fresh->fetch(PDO::FETCH_ASSOC) ?: $competition;
    $competition=ygo_team_context($pdo,$competition);ygo_require_supported_model($competition);
    ygo_assert_fixture_mutable($pdo, (int)($competition['id'] ?? 0));
    $selectedDeckIds=ygo_admission_require($pdo,$competition,$selectedDeckIds);
    return ygo_workflow_generate($pdo,$competition,$selectedDeckIds,$startDate,$pattern,$matchesPerDay);
}

function ygo_schedule_cup_duels(PDO $pdo, array $competition, array $selectedDeckIds, string $startDate, string $pattern, string $matchFormat, int $participantsPerSide, int $matchesPerDay = 1): int
{
    if(!$pdo->inTransaction()){$pdo->beginTransaction();try{$n=ygo_schedule_cup_duels($pdo,$competition,$selectedDeckIds,$startDate,$pattern,$matchFormat,$participantsPerSide,$matchesPerDay);$pdo->commit();return $n;}catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();throw $e;}}

    if(!in_array((string)($competition['execution_model']??'manual'),['single_elimination','bracket','top_cut','playoffs'],true)) throw new InvalidArgumentException('El programador de llave no puede ejecutar una serie, modo manual u otro motor.');

    ygo_require_execution_type((string)$competition['type'], (string)($competition['execution_model'] ?? 'manual'));
    ygo_require_supported_model($competition);
    $fresh=$pdo->prepare('SELECT * FROM sx_ygo_competitions WHERE id=? FOR UPDATE');$fresh->execute([$competition['id']]);$competition=$fresh->fetch(PDO::FETCH_ASSOC) ?: $competition;
    ygo_assert_fixture_mutable($pdo, (int)($competition['id'] ?? 0));
    $selectedDeckIds=ygo_admission_require($pdo,$competition,$selectedDeckIds);

    $matchesPerDay = max(1, min(5, $matchesPerDay));
    $competitionId = (int) ($competition['id'] ?? 0);
    $seasonId = ygo_current_season_id($pdo);
    $divisionId = !empty($competition['division_id']) ? (int) $competition['division_id'] : null;
    $participantsPerSide = max(1, min(3, $participantsPerSide));
    $minimumDecks = $participantsPerSide * 2;
    $selectedDeckIds = array_values(array_unique(array_filter(array_map('intval', $selectedDeckIds), static fn(int $id): bool => $id > 0)));
    if ($competitionId <= 0 || count($selectedDeckIds) < $minimumDecks) {
        return 0;
    }
    $generationStmt = $pdo->prepare("SELECT COUNT(*) FROM sx_ygo_audit_log WHERE entity_type = 'competition' AND entity_id = ? AND action = 'configure_competition' AND payload LIKE '%\"mode\":\"schedule_cup\"%'");
    $generationStmt->execute([$competitionId]);
    $generation = ((int) $generationStmt->fetchColumn()) + 1;
    $selectedDeckIds = ygo_admission_load($pdo,$competition)!==null ? $selectedDeckIds : ygo_secure_shuffle($selectedDeckIds);
    $groups = ygo_roster_groups_for_match($pdo, $selectedDeckIds, $participantsPerSide);
    $groupCount = count($groups);
    if ($groupCount < 2) {
        return 0;
    }

    $pdo->prepare('DELETE r FROM sx_ygo_match_results r JOIN sx_ygo_matches m ON m.id = r.match_id WHERE m.competition_id = ? AND m.status <> "FINALIZADA"')->execute([$competitionId]);
    $pdo->prepare('DELETE FROM sx_ygo_matches WHERE competition_id = ? AND status <> "FINALIZADA"')->execute([$competitionId]);

    $insert = $pdo->prepare('
        INSERT INTO sx_ygo_matches
            (division_id, competition_id, phase, match_format, best_of, participants_per_side, round_number, table_number, bracket_round_label, bracket_slot, bracket_side, display_order, generation_number, deck_a_id, deck_b_id, deck_c_id, deck_d_id, deck_e_id, deck_f_id, status, scheduled_on, starts_at, venue, notes)
        VALUES
            (?, ?, "cup", ?, ?, ?, 1, ?, "Octavos / ronda inicial", ?, "main", ?, ?, ?, ?, ?, ?, ?, ?, "PENDIENTE", ?, ?, "Station X Tulua", ?)
    ');
    $start = new DateTimeImmutable($startDate);
    $pairs = intdiv($groupCount, 2);
    $created = 0;
    for ($i = 0; $i < $pairs; $i++) {
        $leftIndex = $i;
        $rightIndex = $groupCount - 1 - $i;
        if (($generation + $i) % 2 === 0) {
            [$leftIndex, $rightIndex] = [$rightIndex, $leftIndex];
        }
        $sideA = array_pad($groups[$leftIndex], 3, null);
        $sideB = array_pad($groups[$rightIndex], 3, null);
        $scheduledOn = ygo_next_competition_match_date($start, $pattern, $created, $matchesPerDay);
        $slot = $created % $matchesPerDay;
        $startsAt = (new DateTimeImmutable('19:00'))->modify('+' . ($slot * 45) . ' minutes')->format('H:i:s');
        $insert->execute([
            $divisionId,
            $competitionId,
            $matchFormat,
            (int) ($competition['best_of'] ?? 3),
            $participantsPerSide,
            $created + 1,
            $created + 1,
            $created + 1,
            $generation,
            $sideA[0],
            $sideB[0],
            $sideA[1],
            $sideB[1],
            $sideA[2],
            $sideB[2],
            $scheduledOn,
            $startsAt,
            'Generado por algoritmo de copa. Generacion ' . $generation . '. Duelos por dia: ' . $matchesPerDay . '.',
        ]);
        $created++;
    }
    if($groupCount%2===1) ygo_save_setting($pdo,'cup_bye_'.$competitionId.'_1',json_encode([(int)$groups[intdiv($groupCount,2)][0]]));
    ygo_admission_schedule_stage($pdo,$competition,$startDate,$pattern,$matchesPerDay);
    ygo_rebuild_competition_calendar_events_from_matches($pdo, $seasonId, $competitionId, 'cup');
    return $created;
}

function ygo_round_robin_pairs(array $groups, bool $doubleRound = false): array
{
    $count = count($groups);
    if ($count < 2) {
        return [];
    }
    $indexes = range(0, $count - 1);
    if ($count % 2 === 1) {
        $indexes[] = null;
    }
    $size = count($indexes);
    $rounds = [];
    for ($round = 1; $round < $size; $round++) {
        $pairs = [];
        for ($i = 0; $i < (int) ($size / 2); $i++) {
            $left = $indexes[$i];
            $right = $indexes[$size - 1 - $i];
            if ($left === null || $right === null) {
                continue;
            }
            $pairs[] = [$left, $right];
        }
        $rounds[] = $pairs;
        $fixed = array_shift($indexes);
        $tail = array_pop($indexes);
        array_unshift($indexes, $fixed);
        array_splice($indexes, 1, 0, [$tail]);
    }
    if ($doubleRound) {
        foreach ($rounds as $pairs) {
            $rounds[] = array_map(static fn(array $pair): array => [$pair[1], $pair[0]], $pairs);
        }
    }
    return $rounds;
}

function ygo_schedule_competition_by_model(PDO $pdo, array $competition, array $selectedDeckIds, string $startDate, string $pattern, string $matchFormat, int $participantsPerSide, int $matchesPerDay = 1): int
{
    return ygo_with_event_write_lock($pdo, fn() => ygo_schedule_competition_by_model_serialized($pdo,$competition,$selectedDeckIds,$startDate,$pattern,$matchFormat,$participantsPerSide,$matchesPerDay));
}

function ygo_schedule_competition_by_model_serialized(PDO $pdo, array $competition, array $selectedDeckIds, string $startDate, string $pattern, string $matchFormat, int $participantsPerSide, int $matchesPerDay = 1): int
{
    if(!$pdo->inTransaction()){$pdo->beginTransaction();try{$n=ygo_schedule_competition_by_model($pdo,$competition,$selectedDeckIds,$startDate,$pattern,$matchFormat,$participantsPerSide,$matchesPerDay);$pdo->commit();return $n;}catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();throw $e;}}

    ygo_require_execution_type((string)$competition['type'], (string)($competition['execution_model'] ?? 'manual'));
    $fresh=$pdo->prepare('SELECT * FROM sx_ygo_competitions WHERE id=? FOR UPDATE');$fresh->execute([$competition['id']]);$competition=$fresh->fetch(PDO::FETCH_ASSOC) ?: $competition;
    $competition=ygo_team_context($pdo,$competition);ygo_require_supported_model($competition);
    ygo_assert_fixture_mutable($pdo, (int)($competition['id'] ?? 0));
    $selectedDeckIds=ygo_admission_require($pdo,$competition,$selectedDeckIds);
    if(ygo_team_kind($competition)!=='' || in_array((string)$competition['execution_model'],ygo_workflow_models(),true))return ygo_workflow_generate($pdo,$competition,$selectedDeckIds,$startDate,$pattern,$matchesPerDay);

    $competitionId = (int) ($competition['id'] ?? 0);
    $seasonId = ygo_current_season_id($pdo);
    $divisionId = (int) (($competition['division_id'] ?? 0) ?: ygo_first_division_id($pdo));
    $participantsPerSide = max(1, min(3, $participantsPerSide));
    $matchesPerDay = max(1, min(5, $matchesPerDay));
    $selectedDeckIds = ygo_admission_load($pdo,$competition)!==null ? $selectedDeckIds : ygo_secure_shuffle($selectedDeckIds);
    $groups = ygo_roster_groups_for_match($pdo, $selectedDeckIds, $participantsPerSide);
    if ($competitionId <= 0 || $divisionId <= 0 || count($groups) < 2) {
        return 0;
    }
    $type = (string) (($competition['type'] ?? '') ?: 'tournament');
    $execution = (string) (($competition['execution_model'] ?? '') ?: 'manual');
    $phaseStructure = 'NONE'; // Engine decides scheduling; a descriptive phase must not select another algorithm.
    if(in_array($execution,ygo_workflow_models(),true))return ygo_workflow_generate($pdo,$competition,$selectedDeckIds,$startDate,$pattern,$matchesPerDay);
    if($execution==='free_for_all')throw new InvalidArgumentException('Crea las mesas de3 o4 jugadores desde Battle Royale en la ficha.');
    if ($execution==='manual') throw new InvalidArgumentException('Modo manual: crea sus combates expresamente; no se generara un round robin.');
    $phase = in_array($type, ['league'], true) ? 'league' : ($type === 'world' ? 'world' : ($type === 'final' ? 'final' : 'tournament'));
    if (in_array($execution, ['single_elimination', 'double_elimination', 'top_cut', 'playoffs', 'bracket', 'series'], true)) {
        return ygo_schedule_cup_duels($pdo, $competition, $selectedDeckIds, $startDate, $pattern, $matchFormat, $participantsPerSide, $matchesPerDay);
    }
    if (in_array($execution, ['swiss', 'swiss_top_cut'], true) || $type === 'qualifier') {
        $duels = max(2, min(5, count($groups) - 1));
        $swissPhase = $type === 'qualifier' ? 'qualifier' : 'swiss';
        return ygo_schedule_qualifier_duels($pdo, $competition, $selectedDeckIds, $startDate, $pattern, $matchFormat, $participantsPerSide, $duels, $matchesPerDay, $swissPhase);
    }

    $leaguePlan=null;
    if ($type==='league') {
        $finished=$pdo->prepare("SELECT COUNT(*) FROM sx_ygo_matches WHERE competition_id=? AND status='FINALIZADA'");
        $finished->execute([$competitionId]);
        if((int)$finished->fetchColumn()>0) throw new InvalidArgumentException('No se puede regenerar una liga que ya tiene combates finalizados.');
        $leaguePlan=ygo_league_round_robin_plan($pdo,$competitionId,$selectedDeckIds,$participantsPerSide,$execution==='double_round_robin');
    }

    $pdo->prepare('DELETE r FROM sx_ygo_match_results r JOIN sx_ygo_matches m ON m.id = r.match_id WHERE m.competition_id = ? AND m.status <> "FINALIZADA"')->execute([$competitionId]);
    $pdo->prepare('DELETE FROM sx_ygo_matches WHERE competition_id = ? AND status <> "FINALIZADA"')->execute([$competitionId]);

    if ($leaguePlan!==null) {
        $groups=$leaguePlan['groups'];
        $rounds=$leaguePlan['rounds'];
    } elseif (in_array($execution, ['groups', 'groups_knockout'], true) || str_contains($phaseStructure, 'GROUPS')) {
        $rounds = [];
        $chunks = array_chunk(range(0, count($groups) - 1), max(2, (int) ceil(count($groups) / 2)));
        foreach ($chunks as $chunk) {
            $localGroups = array_map(static fn(int $index): array => $groups[$index], $chunk);
            $localRounds = ygo_round_robin_pairs($localGroups, false);
            foreach ($localRounds as $roundPairs) {
                $rounds[] = array_map(static function (array $pair) use ($chunk): array {
                    return [$chunk[$pair[0]], $chunk[$pair[1]]];
                }, $roundPairs);
            }
        }
        $phase = 'groups';
    } else {
        $rounds = ygo_round_robin_pairs($groups, $execution === 'double_round_robin');
    }

    $generationStmt = $pdo->prepare('SELECT COUNT(*) FROM sx_ygo_audit_log WHERE entity_type = "competition" AND entity_id = ? AND action = "configure_competition"');
    $generationStmt->execute([$competitionId]);
    $generation = ((int) $generationStmt->fetchColumn()) + 1;
    $insert = $pdo->prepare('
        INSERT INTO sx_ygo_matches
            (division_id, competition_id, phase, match_format, best_of, participants_per_side, round_number, table_number, bracket_round_label, bracket_slot, bracket_side, display_order, generation_number, deck_a_id, deck_b_id, deck_c_id, deck_d_id, deck_e_id, deck_f_id, status, scheduled_on, starts_at, venue, notes)
        VALUES
            (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, "main", ?, ?, ?, ?, ?, ?, ?, ?, "PENDIENTE", ?, ?, "Station X Tulua", ?)
    ');
    $start = new DateTimeImmutable($startDate);
    $created = 0;
    foreach ($rounds as $roundIndex => $pairs) {
        foreach ($pairs as $tableIndex => $pair) {
            $sideA = array_pad($groups[(int) $pair[0]], 3, null);
            $sideB = array_pad($groups[(int) $pair[1]], 3, null);
            $scheduledOn = ygo_next_competition_match_date($start, $pattern, $created, $matchesPerDay);
            $slot = $created % $matchesPerDay;
            $startsAt = (new DateTimeImmutable('19:00'))->modify('+' . ($slot * 45) . ' minutes')->format('H:i:s');
            $insert->execute([
                (int) ($pair[2] ?? $divisionId),
                $competitionId,
                $phase,
                $matchFormat,
                (int) ($competition['best_of'] ?? 3),
                $participantsPerSide,
                $roundIndex + 1,
                (int) ($pair[3] ?? ($tableIndex + 1)),
                'Ronda ' . ($roundIndex + 1),
                (int) ($pair[3] ?? ($tableIndex + 1)),
                $created + 1,
                $generation,
                $sideA[0],
                $sideB[0],
                $sideA[1],
                $sideB[1],
                $sideA[2],
                $sideB[2],
                $scheduledOn,
                $startsAt,
                'Generado por modelo ' . $execution . '. Generacion ' . $generation . '.',
            ]);
            $created++;
        }
    }
    ygo_admission_schedule_stage($pdo,$competition,$startDate,$pattern,$matchesPerDay);
    ygo_rebuild_competition_calendar_events_from_matches($pdo, $seasonId, $competitionId, $phase);
    return $created;
}

function ygo_apply_division_from_qualifier(PDO $pdo, int $divisionId): int
{
    if ($divisionId <= 0) {
        return 0;
    }
    $divisionStmt = $pdo->prepare('SELECT * FROM sx_ygo_divisions WHERE id = ? AND active = 1 LIMIT 1');
    $divisionStmt->execute([$divisionId]);
    $division = $divisionStmt->fetch(PDO::FETCH_ASSOC) ?: [];
    $qualifierId = (int) ($division['source_qualifier_id'] ?? 0);
    $rankFrom = (int) ($division['rank_from'] ?? 0);
    $rankTo = (int) ($division['rank_to'] ?? 0);
    if ($qualifierId <= 0 || $rankFrom <= 0 || $rankTo <= 0) {
        return 0;
    }
    if ($rankTo < $rankFrom) {
        [$rankFrom, $rankTo] = [$rankTo, $rankFrom];
    }
    $competitionStmt = $pdo->prepare("SELECT status FROM sx_ygo_competitions WHERE id = ? AND active = 1 LIMIT 1");
    $competitionStmt->execute([$qualifierId]);
    $status = ygo_normalize_status_key((string) ($competitionStmt->fetchColumn() ?: ''));
    if (!in_array($status, ['completed', 'finalizado', 'finalizada'], true)) {
        return 0;
    }
    $ranking = ygo_competition_standings($pdo, $qualifierId);
    $slice = array_slice($ranking, $rankFrom - 1, ($rankTo - $rankFrom) + 1);
    if (!$slice) {
        return 0;
    }
    $update = $pdo->prepare('UPDATE sx_ygo_decks SET division_id = ?, seed_rank = ? WHERE id = ?');
    $count = 0;
    foreach ($slice as $index => $row) {
        $deckId = (int) ($row['deck_id'] ?? $row['id'] ?? 0);
        if ($deckId <= 0) {
            continue;
        }
        $update->execute([$divisionId, $index + 1, $deckId]);
        $count++;
    }
    $pdo->prepare('UPDATE sx_ygo_divisions SET deck_count = ? WHERE id = ?')->execute([$count, $divisionId]);
    $pdo->prepare('INSERT INTO sx_ygo_audit_log (entity_type, entity_id, action, payload) VALUES (?, ?, ?, ?)')
        ->execute(['division', $divisionId, 'apply_from_qualifier', json_encode(['qualifier_id' => $qualifierId, 'rank_from' => $rankFrom, 'rank_to' => $rankTo, 'moved' => $count], JSON_UNESCAPED_UNICODE)]);
    return $count;
}

function ygo_expected_qualifier_match_count(int $selectedDeckCount, int $participantsPerSide, int $duelsPerDeck): int
{
    $participantsPerSide = max(1, min(3, $participantsPerSide));
    $groupCount = $participantsPerSide > 0 ? intdiv($selectedDeckCount, $participantsPerSide) : 0;
    if ($groupCount < 2 || $duelsPerDeck < 2 || $duelsPerDeck > ($groupCount - 1)) {
        return 0;
    }
    $roundLimit = min(max(2, min(5, $duelsPerDeck)), $groupCount - 1);
    return intdiv($groupCount, 2) * $roundLimit;
}

function ygo_configure_competition(PDO $pdo): int
{
    $competitionId = (int) ($_POST['competition_id'] ?? 0);
    if ($competitionId <= 0) {
        return 0;
    }
    $seasonId = ygo_current_season_id($pdo);
    $stmt = $pdo->prepare('SELECT * FROM sx_ygo_competitions WHERE id = ? AND season_id = ? AND active = 1 LIMIT 1');
    $stmt->execute([$competitionId, $seasonId]);
    $competition = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$competition) {
        return 0;
    }

    $auditBefore = ygo_event_audit_snapshot($pdo, $competitionId);
    $mode = (string) ($_POST['config_mode'] ?? 'save');
    if(in_array($mode,['start','schedule_qualifier','schedule_cup','save_schedule'],true)){
        $existing=$pdo->prepare('SELECT COUNT(*) FROM sx_ygo_matches WHERE competition_id=?');$existing->execute([$competitionId]);
        if((int)$existing->fetchColumn()===0) ygo_require_supported_model(ygo_team_context($pdo,$competition));
        elseif(in_array($mode,['schedule_qualifier','schedule_cup','save_schedule'],true)) ygo_assert_fixture_mutable($pdo,$competitionId);
    }
    if (!in_array($mode, ['save', 'save_schedule', 'start', 'schedule_qualifier', 'schedule_cup'], true)) {
        $mode = 'save';
    }
    $competitionName = trim((string) ($_POST['competition_name'] ?? ($competition['name'] ?? '')));
    $competitionType = trim((string) ($_POST['competition_type'] ?? ($competition['type'] ?? 'special')));
    if (!empty($competition['type'])) {
        $competitionType = (string) $competition['type'];
    }
    if (!array_key_exists($competitionType, $GLOBALS['competitionTypeOptions'] ?? [])) {
        $competitionType = (string) ($competition['type'] ?? 'special');
    }
    $divisionId = !empty($competition['division_id']) ? (int)$competition['division_id'] : null;
    $ownedDivisions = ygo_league_divisions($pdo, $competitionId);
    $postedDivisionIds = array_map(static fn($division)=>(int)$division['id'], $ownedDivisions);
    if ($competitionType === 'league') {
        if (!$postedDivisionIds) throw new InvalidArgumentException('Asocia al menos una division a esta liga desde Divisiones.');
        if (!in_array($divisionId, $postedDivisionIds, true)) $divisionId = $postedDivisionIds[0];
    }
    $deckPoolMode = (string) ($_POST['competition_deck_pool'] ?? ($competition['deck_pool'] ?? 'all_active'));
    if (!in_array($deckPoolMode, ['all_active', 'division'], true)) {
        $deckPoolMode = 'all_active';
    }
    $leaguePromotionSlots = max(0, (int) ($_POST['competition_promotion_slots'] ?? ($competition['promotion_slots'] ?? 0)));
    $leagueRelegationSlots = max(0, (int) ($_POST['competition_relegation_slots'] ?? ($competition['relegation_slots'] ?? 0)));
    $leaguePromotionTo = ($_POST['competition_promotion_to'] ?? '') === '' ? null : (int) $_POST['competition_promotion_to'];
    $leagueRelegationTo = ($_POST['competition_relegation_to'] ?? '') === '' ? null : (int) $_POST['competition_relegation_to'];
    $lockDivisionMoves = isset($_POST['competition_lock_division_moves']) ? 1 : 0;
    if ($competitionType !== 'league' || count($postedDivisionIds) < 2) {
        $leaguePromotionSlots = 0;
        $leagueRelegationSlots = 0;
        $leaguePromotionTo = null;
        $leagueRelegationTo = null;
        $lockDivisionMoves = 0;
    }
    $eventRuleset = trim((string) ($_POST['competition_event_ruleset'] ?? ($competition['event_ruleset'] ?? 'custom')));
    $rulesetCheck = $pdo->prepare('SELECT code FROM sx_ygo_rulesets WHERE season_id=? AND code=? AND active=1 LIMIT 1');
    $rulesetCheck->execute([$seasonId, $eventRuleset]);
    if (!$rulesetCheck->fetchColumn()) {
        $eventRuleset = (string) (($competition['event_ruleset'] ?? '') ?: 'custom');
    }
    $rulesetVersion = trim((string) ($_POST['competition_ruleset_version'] ?? ($competition['ruleset_version'] ?? '')));
    $accent = trim((string) ($_POST['competition_accent'] ?? ($competition['accent'] ?? '#36d8ff')));
    $rules = trim((string) ($_POST['competition_rules'] ?? ($competition['rules'] ?? '')));
    $rawPostedStatus = trim((string) ($_POST['competition_status'] ?? ($competition['status'] ?? 'pending_configuration')));
    $currentStatusKey = ygo_normalize_status_key((string) ($competition['status'] ?? ''));
    $postedStatus = in_array($rawPostedStatus, ['cancelled', 'resume'], true)
        ? $rawPostedStatus
        : (string) (($competition['status'] ?? '') ?: 'pending_configuration');
    if (in_array($currentStatusKey, ['cancelled', 'canceled', 'anulado', 'anulada'], true)) {
        $nextStatus = in_array($postedStatus, ['resume', 'pending_configuration'], true) ? 'pending_configuration' : 'cancelled';
        $pdo->prepare('
            UPDATE sx_ygo_competitions
            SET status=?, configuration_locked=IF(? = "pending_configuration", 0, configuration_locked), started_at=IF(? = "pending_configuration", NULL, started_at)
            WHERE id=? AND season_id=?
        ')->execute([$nextStatus, $nextStatus, $nextStatus, $competitionId, $seasonId]);
        ygo_record_competition_status_history($pdo, $seasonId, $competitionId, (string) ($competition['status'] ?? ''), $nextStatus, 'configure_competition', ['mode' => $mode, 'resume_from_cancelled' => $nextStatus === 'pending_configuration']);
        $pdo->prepare('INSERT INTO sx_ygo_audit_log (entity_type, entity_id, action, payload) VALUES (?, ?, ?, ?)')
            ->execute(['competition', $competitionId, 'configure_competition', json_encode(['mode' => $mode, 'status' => $nextStatus, 'resume_from_cancelled' => $nextStatus === 'pending_configuration', 'before'=>$auditBefore, 'after'=>ygo_event_audit_snapshot($pdo,$competitionId)], JSON_UNESCAPED_UNICODE)]);
        return 0;
    }
    $qualifierDuelsPerDeck = max(2, min(5, (int) ($_POST['qualifier_duels_per_deck'] ?? ($competition['qualifier_duels_per_deck'] ?? 3))));

    $selectedDeckIds = $_POST['competition_deck_ids'] ?? [];
    if (!is_array($selectedDeckIds)) {
        $selectedDeckIds = [];
    }
    $selectedDeckIds = array_values(array_unique(array_filter(array_map('intval', $selectedDeckIds), static fn(int $id): bool => $id > 0)));
    $currentDeckStmt = $pdo->prepare('SELECT deck_id FROM sx_ygo_competition_decks WHERE competition_id = ? AND active = 1 ORDER BY sort_order, id');
    $currentDeckStmt->execute([$competitionId]);
    $currentCompetitionDeckIds = array_values(array_map('intval', $currentDeckStmt->fetchAll(PDO::FETCH_COLUMN)));
    $hasFixturesStmt=$pdo->prepare('SELECT COUNT(*) FROM sx_ygo_matches WHERE competition_id=?');$hasFixturesStmt->execute([$competitionId]);
    $hasFixtures=(int)$hasFixturesStmt->fetchColumn()>0;
    $deckRosterLocked = $hasFixtures || !empty($competition['configuration_locked']) || in_array($currentStatusKey, ['completed', 'finalizado', 'finalizada', 'cancelled', 'canceled', 'anulado', 'anulada'], true)
        || (!in_array($competitionType, ['cup', 'tournament', 'final'], true) && (!empty($competition['configuration_locked']) || in_array($currentStatusKey, ['active', 'activo'], true)));
    if ($deckRosterLocked) {
        $oldIds=$currentCompetitionDeckIds;$newIds=$selectedDeckIds;sort($oldIds);sort($newIds);
        if($oldIds!==$newIds) throw new InvalidArgumentException('No se pueden cambiar participantes de un evento ya programado.');
        $selectedDeckIds = $currentCompetitionDeckIds;
    } elseif ($deckPoolMode === 'division') {
        $selectedDeckIds = ygo_available_league_deck_ids($pdo, $competitionId, ygo_format_deck_ids($pdo, $eventRuleset));
    }
    $selectedDeckIds = ygo_validate_format_roster($pdo, $eventRuleset, $selectedDeckIds);
    if(ygo_admission_load($pdo,$competition)!==null)$selectedDeckIds=ygo_admission_require($pdo,$competition,$selectedDeckIds,false);
    $today = new DateTimeImmutable('today');
    $startDateRaw = (string) ($_POST['competition_start_on'] ?? '');
    $existingStartDate = preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) ($competition['starts_on'] ?? '')) ? (string) $competition['starts_on'] : '';
    $startDate = preg_match('/^\d{4}-\d{2}-\d{2}$/', $startDateRaw) ? $startDateRaw : ($existingStartDate ?: $today->format('Y-m-d'));
    if (new DateTimeImmutable($startDate) < $today) {
        $startDate = $existingStartDate !== '' ? $existingStartDate : $today->format('Y-m-d');
    }
    $pattern = (string) ($_POST['competition_schedule_pattern'] ?? 'consecutive');
    $allowedPatterns = ['consecutive', 'every_other_day', 'every_2_days', 'every_3_days', 'weekend'];
    if (!in_array($pattern, $allowedPatterns, true)) {
        $pattern = 'consecutive';
    }
    $qualifierMatchesPerDay = max(1, min(5, (int) ($_POST['qualifier_matches_per_day'] ?? ($competition['qualifier_matches_per_day'] ?? 1))));
    $matchFormat = (string) ($_POST['competition_match_format'] ?? ($competition['match_format'] ?? '1v1'));
    if(($competition['execution_model']??'')==='free_for_all'&&$matchFormat!=='ffa')throw new InvalidArgumentException('Esta ficha usa jugadores independientes, no lados1v1/2v2/3v3.');
    if(ygo_template_base_code((string)($competition['prompt_template']??''))==='pegasus_challenge'&&(!in_array($eventRuleset,['tcg_advanced','tcg_traditional'],true)||$matchFormat!=='1v1'))throw new InvalidArgumentException('Pegasus requiere modalidad individual y TCG Avanzado o Tradicional.');
    if (!in_array($matchFormat, ['1v1', '2v2', '3v3', 'ffa'], true)) {
        $matchFormat = '1v1';
    }
    $existingRuleCode = '';
    if (!empty($competition['game_rule_id'])) {
        $ruleCodeStmt = $pdo->prepare('SELECT code FROM sx_ygo_game_rules WHERE id = ? LIMIT 1');
        $ruleCodeStmt->execute([(int) $competition['game_rule_id']]);
        $existingRuleCode = (string) ($ruleCodeStmt->fetchColumn() ?: '');
    }
    $fallbackRuleId = (int) ($competition['game_rule_id'] ?? 0) ?: null;
    $gameRuleId = ygo_valid_game_rule_id($pdo, $seasonId, (int) ($_POST['competition_game_rule_id'] ?? 0), $fallbackRuleId, $competitionType, (string) ($competition['execution_model'] ?? 'manual'));
    if((int)($competition['best_of']??3)===1 && empty($_POST['competition_game_rule_id']) && empty($competition['game_rule_id'])) $gameRuleId=null;
    $uploadedLogo = ygo_store_event_logo_upload();
    $galleryLogo = ygo_valid_event_gallery_logo((string) ($_POST['competition_gallery_logo'] ?? ''), $competitionType);
    $removeLogo = isset($_POST['competition_remove_logo']);
    $defaultLogos = ygo_event_logo_gallery()[$competitionType] ?? [];
    $defaultLogo = (string) ($defaultLogos[0]['url'] ?? '');
    $logoUrl = $removeLogo ? $defaultLogo : ($uploadedLogo !== '' ? $uploadedLogo : ($galleryLogo !== '' ? $galleryLogo : ((string) ($competition['logo_url'] ?? '') ?: $defaultLogo)));
    $participantsPerSide = max(1, min(3, (int) substr($matchFormat, 0, 1)));
    $intervalDays = ['consecutive' => 1, 'every_other_day' => 2, 'every_2_days' => 3, 'every_3_days' => 4, 'weekend' => 0][$pattern];
    $selectedDeckCount = count($selectedDeckIds);
    $savedTeamRoster=ygo_team_kind($competition)!==''?ygo_team_load($pdo,$competition):null;
    $rosterGroups = $savedTeamRoster!==null ? array_column($savedTeamRoster['lineups'],'decks') : ygo_roster_groups_for_match($pdo, $selectedDeckIds, $participantsPerSide);
    $groupCount = count($rosterGroups);
    $maxQualifierDuels = max(0, $groupCount - 1);
    if ($competitionType === 'qualifier' && $maxQualifierDuels >= 2 && $qualifierDuelsPerDeck > $maxQualifierDuels) {
        $qualifierDuelsPerDeck = $maxQualifierDuels;
    }
    $minimumDecks = $competitionType === 'qualifier' ? max(4, $participantsPerSide * 2) : ($participantsPerSide * 2);
    $hasEnoughDecks = $selectedDeckCount >= $minimumDecks;
    $hasEvenTournamentRoster = $selectedDeckCount >= $participantsPerSide*2;
    $hasValidQualifierDuelCount = $competitionType !== 'qualifier' || ($maxQualifierDuels >= 2 && $qualifierDuelsPerDeck <= $maxQualifierDuels);
    $hasRequiredDivision = $competitionType !== 'league' || ($divisionId !== null && $divisionId > 0);
    $canRunCompetition = $hasRequiredDivision && $hasEnoughDecks && $groupCount >= 2 && $hasValidQualifierDuelCount && $hasEvenTournamentRoster;
    $expectedQualifierMatches = $competitionType === 'qualifier'
        ? (in_array((string)$competition['execution_model'],ygo_workflow_models(),true) ? intdiv($selectedDeckCount,2) : ygo_expected_qualifier_match_count($selectedDeckCount, $participantsPerSide, $qualifierDuelsPerDeck))
        : 0;
    $scheduledQualifierMatches = 0;
    if ($competitionType === 'qualifier') {
        $scheduledStmt = $pdo->prepare('SELECT COUNT(*) FROM sx_ygo_matches WHERE competition_id = ? AND phase IN ("qualifier","swiss") AND status <> "FINALIZADA" AND scheduled_on IS NOT NULL');
        $scheduledStmt->execute([$competitionId]);
        $scheduledQualifierMatches = (int) $scheduledStmt->fetchColumn();
    }
    $existingMatchStmt = $pdo->prepare('SELECT COUNT(*) FROM sx_ygo_matches WHERE competition_id = ? AND status <> "FINALIZADA"');
    $existingMatchStmt->execute([$competitionId]);
    $existingScheduledMatches = (int) $existingMatchStmt->fetchColumn();
    $startDateReached = new DateTimeImmutable($startDate) <= $today;
    $competitionGate = ygo_competition_gate($pdo, $competitionId);
    $canStartCompetition = $startDateReached && $canRunCompetition && $competitionGate['can_execute'] && ($competitionType !== 'qualifier' || ($expectedQualifierMatches > 0 && $scheduledQualifierMatches >= $expectedQualifierMatches));
    $status = $postedStatus;
    if ($postedStatus === 'cancelled') {
        $status = 'cancelled';
    } elseif ($competitionType === 'qualifier' && !$canRunCompetition) {
        $status = 'pending_configuration';
    }
    if ($status === 'cancelled') {
        $mode = 'save';
    } elseif (!$canRunCompetition && in_array($mode, ['start', 'schedule_qualifier', 'schedule_cup'], true)) {
        $status = 'pending_configuration';
    } elseif ($mode === 'start' && $canStartCompetition) {
        $status = 'active';
    } elseif ($mode === 'start' && $competitionType === 'qualifier' && !$canStartCompetition) {
        $status = $scheduledQualifierMatches > 0 ? 'pending_event' : 'pending_configuration';
    } elseif ($mode === 'schedule_qualifier' && $competitionType === 'qualifier' && $canRunCompetition) {
        $status = 'pending_event';
    } elseif ($mode === 'schedule_cup' && in_array($competitionType, ['cup', 'final'], true) && $canRunCompetition && !in_array(ygo_normalize_status_key($postedStatus), ['active', 'activo'], true)) {
        $status = 'pending_event';
    }
    $locked = !empty($competition['configuration_locked']);
    if ($locked) {
        $startDate = (string) (($competition['starts_on'] ?? '') ?: $startDate);
        $pattern = (string) (($competition['schedule_pattern'] ?? '') ?: $pattern);
        $intervalDays = (int) ($competition['schedule_interval_days'] ?? $intervalDays);
        $qualifierMatchesPerDay = max(1, min(5, (int) (($competition['qualifier_matches_per_day'] ?? 1) ?: 1)));
    }
    if ($mode === 'save') {
        $startDate = (string) (($competition['starts_on'] ?? '') ?: $startDate);
        $matchFormat = (string) (($competition['match_format'] ?? '') ?: '1v1');
        $participantsPerSide = max(1, min(3, (int) (($competition['participants_per_side'] ?? 1) ?: 1)));
        $qualifierDuelsPerDeck = max(2, min(5, (int) (($competition['qualifier_duels_per_deck'] ?? 3) ?: 3)));
        $qualifierMatchesPerDay = max(1, min(5, (int) (($competition['qualifier_matches_per_day'] ?? 1) ?: 1)));
        $pattern = (string) (($competition['schedule_pattern'] ?? '') ?: 'consecutive');
        $intervalDays = (int) ($competition['schedule_interval_days'] ?? 1);
        $gameRuleId = !empty($competition['game_rule_id']) ? (int) $competition['game_rule_id'] : null;
    }

    $pdo->beginTransaction();
    try {
        ygo_validate_league_roster($pdo, $competitionId, $selectedDeckIds);
        if (!$deckRosterLocked) {
            $pdo->prepare('UPDATE sx_ygo_competition_decks SET active = 0 WHERE competition_id = ?')->execute([$competitionId]);
            $deckInsert = $pdo->prepare('
                INSERT INTO sx_ygo_competition_decks (competition_id, deck_id, sort_order, active)
                VALUES (?, ?, ?, 1)
                ON DUPLICATE KEY UPDATE sort_order=VALUES(sort_order), active=1
            ');
            foreach ($selectedDeckIds as $index => $deckId) {
                $deckInsert->execute([$competitionId, $deckId, $index + 1]);
            }
        }

        $shouldLock = $mode === 'start' && $canStartCompetition;
        $pdo->prepare('
            UPDATE sx_ygo_competitions
            SET division_id=?, name=?, type=?, event_ruleset=?, ruleset_version=?, accent=?, logo_url=?, rules=?, starts_on=?, status=?, match_format=?, participants_per_side=?, qualifier_duels_per_deck=?, qualifier_matches_per_day=?, deck_pool=?, promotion_slots=?, relegation_slots=?, promotion_to_division_id=?, relegation_to_division_id=?, lock_division_moves=?, schedule_strategy=?, schedule_pattern=?, schedule_interval_days=?, game_rule_id=?, configuration_locked=IF(? = 1, 1, configuration_locked), started_at=IF(? = 1, COALESCE(started_at, NOW()), started_at)
            WHERE id=? AND season_id=?
        ')->execute([
            $divisionId,
            $competitionName !== '' ? $competitionName : (string) ($competition['name'] ?? 'Evento'),
            $competitionType,
            $eventRuleset,
            $rulesetVersion,
            $accent,
            $logoUrl,
            $rules,
            $startDate,
            $status,
            $matchFormat,
            $participantsPerSide,
            $qualifierDuelsPerDeck,
            $qualifierMatchesPerDay,
            $deckPoolMode,
            $leaguePromotionSlots,
            $leagueRelegationSlots,
            $leaguePromotionTo,
            $leagueRelegationTo,
            $lockDivisionMoves,
            $shouldLock ? 'auto_event' : (string) (($competition['schedule_strategy'] ?? '') ?: 'manual'),
            $pattern,
            $intervalDays,
            $gameRuleId ?: null,
            $shouldLock ? 1 : 0,
            $shouldLock ? 1 : 0,
            $competitionId,
            $seasonId,
        ]);
        if (isset($_POST['division_movements']) && is_array($_POST['division_movements'])) {
            ygo_save_division_movements($pdo, $competitionId, $_POST['division_movements']);
        }
        if ($logoUrl !== '' && $logoUrl !== (string) ($competition['logo_url'] ?? '')) {
            ygo_record_logo_history($pdo, 'competition', $competitionId, $logoUrl, $uploadedLogo !== '' ? 'upload' : 'gallery');
        }

        $created = 0;
        if ($mode === 'schedule_qualifier' && $competitionType === 'qualifier' && $canRunCompetition) {
            $scheduleCompetition = array_merge($competition, ['id' => $competitionId, 'type' => $competitionType, 'division_id' => $divisionId ?: ($competition['division_id'] ?? 0), 'best_of' => $competition['best_of'] ?? 3]);
            $created = ygo_schedule_qualifier_duels($pdo, $scheduleCompetition, $selectedDeckIds, $startDate, $pattern, $matchFormat, $participantsPerSide, $qualifierDuelsPerDeck, $qualifierMatchesPerDay);
            ygo_rebuild_competition_calendar_events_from_matches($pdo, $seasonId, $competitionId, 'qualifier');
        } elseif ($mode === 'schedule_cup' && in_array($competitionType, ['cup', 'final'], true) && $canRunCompetition && $existingScheduledMatches === 0) {
            $scheduleCompetition = array_merge($competition, ['id' => $competitionId, 'type' => $competitionType, 'division_id' => $divisionId ?: ($competition['division_id'] ?? 0), 'best_of' => $competition['best_of'] ?? 3]);
            $created = ygo_schedule_competition_by_model($pdo, $scheduleCompetition, $selectedDeckIds, $startDate, $pattern, $matchFormat, $participantsPerSide, $qualifierMatchesPerDay);
            ygo_rebuild_competition_calendar_events_from_matches($pdo, $seasonId, $competitionId, $competitionType);
        } elseif ($mode === 'start' && $canRunCompetition && $existingScheduledMatches === 0) {
            $scheduleCompetition = array_merge($competition, [
                'id' => $competitionId,
                'type' => $competitionType,
                'division_id' => $divisionId ?: ($competition['division_id'] ?? 0),
                'best_of' => $competition['best_of'] ?? 3,
                'execution_model' => $competition['execution_model'] ?? 'manual',
                'phase_structure' => $competition['phase_structure'] ?? 'NONE',
            ]);
            $created = ygo_schedule_competition_by_model($pdo, $scheduleCompetition, $selectedDeckIds, $startDate, $pattern, $matchFormat, $participantsPerSide, $qualifierMatchesPerDay);
            if (in_array($competitionType, ['cup', 'final'], true) && $created < 1) {
                throw new RuntimeException('No fue posible generar la llave de la copa antes de comenzar.');
            }
            if ($created > 0 && in_array($competitionType, ['cup', 'final'], true)) {
                ygo_rebuild_competition_calendar_events_from_matches($pdo, $seasonId, $competitionId, $competitionType);
            }
        }
        if ($created > 0) {
            if(ygo_fixed_program($pdo)) {
                if(ygo_team_kind($competition)==='' && !in_array((string)$competition['execution_model'],ygo_workflow_models(),true) && ygo_admission_load($pdo,$competition)===null)ygo_reflow_pending_competition($pdo,$competitionId,$startDate,$pattern,$qualifierMatchesPerDay);
                $newDates=$pdo->prepare('SELECT * FROM sx_ygo_matches WHERE competition_id=?');$newDates->execute([$competitionId]);
                ygo_season_validate_competition_schedule($pdo,$competitionId);
                foreach($newDates->fetchAll(PDO::FETCH_ASSOC) as $scheduled){
                    $valid=ygo_validate_match_schedule($pdo,(int)$scheduled['id'],$scheduled['scheduled_on'],$scheduled['starts_at'],$scheduled['venue']);
                    if(empty($valid['ok'])) throw new InvalidArgumentException('La programacion nueva no cumple las restricciones de fecha, horario o participantes. El calendario existente se conserva.');
                }
            } elseif(ygo_team_kind($competition)==='' && !in_array((string)$competition['execution_model'],ygo_workflow_models(),true) && ygo_admission_load($pdo,$competition)===null)ygo_rebalance_after_competition_schedule($pdo, $competitionId);
        }
        ygo_persist_competition_calendar_event($pdo, $seasonId, $competitionId);

        $conditionQualifierId = (int) ($_POST['condition_qualifier_id'] ?? 0);
        if(ygo_admission_load($pdo,$competition)!==null){ /* Typed admission owns its dependencies. */ }
        elseif (in_array($competitionType, ['league', 'tournament', 'world'], true)) {
            $pdo->prepare('UPDATE sx_ygo_competition_dependencies SET active=0 WHERE competition_id=?')->execute([$competitionId]);
            if ($conditionQualifierId > 0) {
                $qualifierStmt = $pdo->prepare("
                    SELECT c.id
                    FROM sx_ygo_competitions c
                    WHERE c.id=? AND c.season_id=? AND c.type='qualifier' AND c.active=1
                      AND (
                        LOWER(REPLACE(c.status, ' ', '_')) IN ('pending_event', 'pendiente_evento')
                        OR (
                          LOWER(REPLACE(c.status, ' ', '_')) = 'pending_configuration'
                          AND (SELECT COUNT(*) FROM sx_ygo_competition_decks cd WHERE cd.competition_id = c.id AND cd.active = 1) >= 4
                        )
                      )
                    LIMIT 1
                ");
                $qualifierStmt->execute([$conditionQualifierId, $seasonId]);
                $validQualifierId = (int) ($qualifierStmt->fetchColumn() ?: 0);
                if ($validQualifierId > 0) {
                    $pdo->prepare('
                        INSERT INTO sx_ygo_competition_dependencies (season_id, competition_id, depends_on_competition_id, condition_type, take_count, active)
                        VALUES (?, ?, ?, "completed_with_results", 0, 1)
                        ON DUPLICATE KEY UPDATE condition_type=VALUES(condition_type), take_count=VALUES(take_count), active=1
                    ')->execute([$seasonId, $competitionId, $validQualifierId]);
                }
            }
        } else {
            $pdo->prepare('UPDATE sx_ygo_competition_dependencies SET active=0 WHERE competition_id=?')->execute([$competitionId]);
        }
        $pdo->prepare('INSERT INTO sx_ygo_audit_log (entity_type, entity_id, action, payload) VALUES (?, ?, ?, ?)')
            ->execute(['competition', $competitionId, 'configure_competition', json_encode(['mode' => $mode, 'decks' => count($selectedDeckIds), 'deck_roster_locked' => $deckRosterLocked, 'matches' => $created, 'existing_scheduled_matches' => $existingScheduledMatches, 'reprogrammed' => $existingScheduledMatches > 0 && $created > 0, 'expected_matches' => $expectedQualifierMatches, 'start' => $startDate, 'pattern' => $pattern, 'match_format' => $matchFormat, 'participants_per_side' => $participantsPerSide, 'qualifier_duels_per_deck' => $qualifierDuelsPerDeck, 'qualifier_matches_per_day' => $qualifierMatchesPerDay, 'before'=>$auditBefore, 'after'=>ygo_event_audit_snapshot($pdo,$competitionId)], JSON_UNESCAPED_UNICODE)]);
        ygo_record_competition_status_history($pdo, $seasonId, $competitionId, (string) ($competition['status'] ?? ''), $status, 'configure_competition', ['mode' => $mode, 'matches' => $created]);
        ygo_season_validate_competition_schedule($pdo, $competitionId);
        $pdo->commit();
        return $created;
    } catch (Throwable $error) {
        $pdo->rollBack();
        throw $error;
    }
}

function ygo_delete_competition(PDO $pdo): int
{
    $competitionId = (int) ($_POST['competition_id'] ?? 0);
    $confirmation = strtolower(trim((string) ($_POST['delete_confirmation'] ?? '')));
    if ($competitionId <= 0 || $confirmation !== 'eliminar') {
        return 0;
    }
    $seasonId = ygo_current_season_id($pdo);
    $stmt = $pdo->prepare('SELECT id, status FROM sx_ygo_competitions WHERE id = ? AND season_id = ? AND active = 1 LIMIT 1');
    $stmt->execute([$competitionId, $seasonId]);
    $competition = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$competition) {
        return 0;
    }
    $status = ygo_normalize_status_key((string) ($competition['status'] ?? ''));
    if (in_array($status, ['active', 'completed', 'cancelled'], true)) {
        return 0;
    }
    $pdo->beginTransaction();
    try {
        $pdo->prepare('DELETE r FROM sx_ygo_match_results r INNER JOIN sx_ygo_matches m ON m.id = r.match_id WHERE m.competition_id = ?')->execute([$competitionId]);
        $pdo->prepare('DELETE FROM sx_ygo_matches WHERE competition_id = ?')->execute([$competitionId]);
        $pdo->prepare('DELETE FROM sx_ygo_events WHERE season_id = ? AND competition_id = ?')->execute([$seasonId, $competitionId]);
        $pdo->prepare('DELETE FROM sx_ygo_competition_decks WHERE competition_id = ?')->execute([$competitionId]);
        $pdo->prepare('DELETE FROM sx_ygo_competition_dependencies WHERE competition_id = ? OR depends_on_competition_id = ?')->execute([$competitionId, $competitionId]);
        $pdo->prepare('DELETE FROM sx_ygo_competition_status_history WHERE competition_id = ?')->execute([$competitionId]);
        $pdo->prepare('DELETE FROM sx_ygo_standing_snapshots WHERE competition_id = ?')->execute([$competitionId]);
        $pdo->prepare('DELETE FROM sx_ygo_honors WHERE competition_id = ?')->execute([$competitionId]);
        if (ygo_table_exists($pdo, 'sx_ygo_event_summaries')) $pdo->prepare('DELETE FROM sx_ygo_event_summaries WHERE competition_id = ?')->execute([$competitionId]);
        $pdo->prepare('INSERT INTO sx_ygo_audit_log (entity_type, entity_id, action, payload) VALUES (?, ?, ?, ?)')->execute(['competition', $competitionId, 'delete_competition', json_encode(['status' => $status], JSON_UNESCAPED_UNICODE)]);
        $pdo->prepare('DELETE FROM sx_ygo_competitions WHERE id = ? AND season_id = ?')->execute([$competitionId, $seasonId]);
        $pdo->commit();
        return $competitionId;
    } catch (Throwable $error) {
        $pdo->rollBack();
        throw $error;
    }
}

function ygo_live_duel_match(PDO $pdo, int $matchId): array
{
    $stmt = $pdo->prepare("
        SELECT m.id, m.status, m.best_of, m.round_number, m.table_number, m.scheduled_on, m.starts_at,
               COALESCE(c.name, dv.name, 'Partida') AS competition_name,
               da.name AS deck_a, db.name AS deck_b,
               COALESCE(ta.name, 'Sin equipo') AS team_a, COALESCE(tb.name, 'Sin equipo') AS team_b,
               COALESCE(ta.logo_url, '') AS logo_a, COALESCE(tb.logo_url, '') AS logo_b,
               r.d1, r.d2, r.d3, r.d4, r.d5, r.duel_details
        FROM sx_ygo_matches m
        JOIN sx_ygo_decks da ON da.id = m.deck_a_id
        JOIN sx_ygo_decks db ON db.id = m.deck_b_id
        LEFT JOIN sx_ygo_teams ta ON ta.id = da.team_id
        LEFT JOIN sx_ygo_teams tb ON tb.id = db.team_id
        LEFT JOIN sx_ygo_competitions c ON c.id = m.competition_id
        LEFT JOIN sx_ygo_divisions dv ON dv.id = m.division_id
        LEFT JOIN sx_ygo_match_results r ON r.match_id = m.id
        WHERE m.id = ?
        LIMIT 1
    ");
    $stmt->execute([$matchId]);
    return $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
}

function ygo_live_duel_initial_state(array $match): array
{
    $details = json_decode((string) ($match['duel_details'] ?? ''), true);
    $details = is_array($details) ? $details : [];
    $duelNumber = 1;
    foreach (range(1, 5) as $number) {
        if (!in_array((string) ($match['d' . $number] ?? ''), ['A', 'B', 'E'], true)) {
            $duelNumber = $number;
            break;
        }
        $duelNumber = min(5, $number + 1);
    }
    $detail = [];
    foreach ($details as $row) {
        if (is_array($row) && (int) ($row['duel'] ?? 0) === $duelNumber) {
            $detail = $row;
            break;
        }
    }
    $history = ygo_normalize_turn_history(is_array($detail['turn_history'] ?? null) ? $detail['turn_history'] : []);
    $starts = strtoupper((string) ($detail['starts_player'] ?? ''));
    $starts = in_array($starts, ['A', 'B'], true) ? $starts : '';
    if ($starts === '' && $history && (int) ($history[0]['turn'] ?? 0) === 1) {
        $historyStart = strtoupper((string) ($history[0]['side'] ?? ''));
        $starts = in_array($historyStart, ['A', 'B'], true) ? $historyStart : '';
    }
    $completed = max((int) ($detail['turn_count'] ?? 0), count($history));
    $activeSide = $starts === '' ? '' : ($completed % 2 === 0 ? $starts : ($starts === 'A' ? 'B' : 'A'));
    $state = [
        'duel_number' => $duelNumber,
        'status' => !empty($detail['duel_started']) ? 'running' : 'setup',
        'starts_player' => $starts,
        'active_side' => $activeSide,
        'turn_count' => $completed,
        'turn_history' => $history,
        'lp_a' => max(0, (int) (($detail['lp_a'] ?? '') !== '' ? $detail['lp_a'] : 8000)),
        'lp_b' => max(0, (int) (($detail['lp_b'] ?? '') !== '' ? $detail['lp_b'] : 8000)),
        'lp_start_a' => max(1, (int) ($detail['lp_start_a'] ?? 8000)),
        'lp_start_b' => max(1, (int) ($detail['lp_start_b'] ?? 8000)),
        'lp_history' => is_array($detail['lp_history'] ?? null) ? array_slice($detail['lp_history'], 0, 100) : [],
        'timer_mode' => in_array((string) ($detail['timer_mode'] ?? ''), ['none', 'countdown'], true) ? (string) $detail['timer_mode'] : '',
        'timer_running' => !empty($detail['timer_running']),
        'timer_started_at' => max(0, (int) ($detail['timer_started_at'] ?? 0)),
        'elapsed_seconds' => max(0, min(1200, (int) ($detail['duration_seconds'] ?? 0))),
        'dice_a' => max(0, (int) ($detail['dice_a'] ?? 0)),
        'dice_b' => max(0, (int) ($detail['dice_b'] ?? 0)),
        'dice_winner' => in_array(strtoupper((string) ($detail['dice_winner'] ?? $detail['turn_chosen_by'] ?? '')), ['A', 'B'], true) ? strtoupper((string) ($detail['dice_winner'] ?? $detail['turn_chosen_by'])) : '',
        'turn_decision' => (string) ($detail['turn_decision'] ?? ''),
        'surrendered_by' => in_array(strtoupper((string) ($detail['surrendered_by'] ?? '')), ['A', 'B'], true) ? strtoupper((string) $detail['surrendered_by']) : '',
        'surrendered_at' => mb_substr(trim((string) ($detail['surrendered_at'] ?? '')), 0, 40),
        'updated_at_ms' => (int) floor(microtime(true) * 1000),
        'correction_at_ms' => max(array_merge([0],array_map(static fn($d)=>(int)($d['manual_correction_at_ms']??0),$details))),
    ];
    $previousResult = $duelNumber > 1 ? (string) ($match['d' . ($duelNumber - 1)] ?? '') : '';
    $hasDrawReroll = $state['dice_a'] >= 1 && $state['dice_b'] >= 1 && in_array($state['dice_winner'], ['A', 'B'], true);
    if ($previousResult === 'E' && !$hasDrawReroll) {
        $state['status'] = 'setup';
        $state['starts_player'] = '';
        $state['active_side'] = '';
        $state['timer_running'] = false;
        $state['timer_started_at'] = 0;
    }
    return $state;
}

function ygo_live_duel_normalize_state(array $state): array
{
    $duel = max(1, min(5, (int) ($state['duel_number'] ?? 1)));
    $starts = strtoupper((string) ($state['starts_player'] ?? ''));
    $active = strtoupper((string) ($state['active_side'] ?? ''));
    $history = ygo_normalize_turn_history(is_array($state['turn_history'] ?? null) ? $state['turn_history'] : []);
    if (!in_array($starts, ['A', 'B'], true) && $history && (int) ($history[0]['turn'] ?? 0) === 1) {
        $historyStart = strtoupper((string) ($history[0]['side'] ?? ''));
        $starts = in_array($historyStart, ['A', 'B'], true) ? $historyStart : '';
    }
    if (!in_array($active, ['A', 'B'], true) && in_array($starts, ['A', 'B'], true)) {
        $completedTurns = max((int) ($state['turn_count'] ?? 0), count($history), $history ? (int) end($history)['turn'] : 0);
        $active = $completedTurns % 2 === 0 ? $starts : ($starts === 'A' ? 'B' : 'A');
    }
    $turnCount = max((int) ($state['turn_count'] ?? 0), count($history), $history ? (int) end($history)['turn'] : 0);
    $status = strtolower((string) ($state['status'] ?? 'setup'));
    $status = in_array($status, ['setup', 'ready', 'running', 'paused', 'completed'], true) ? $status : 'setup';
    if (!in_array($starts, ['A', 'B'], true) && in_array($status, ['ready', 'running', 'paused'], true)) {
        $status = 'setup';
        $active = '';
        $state['timer_running'] = false;
        $state['timer_started_at'] = 0;
    }
    return [
        'duel_number' => $duel,
        'status' => $status,
        'starts_player' => in_array($starts, ['A', 'B'], true) ? $starts : '',
        'active_side' => in_array($active, ['A', 'B'], true) ? $active : '',
        'turn_count' => max(0, min(999, $turnCount)),
        'turn_history' => $history,
        'lp_a' => max(0, min(99999, (int) ($state['lp_a'] ?? 8000))),
        'lp_b' => max(0, min(99999, (int) ($state['lp_b'] ?? 8000))),
        'lp_start_a' => max(1, min(99999, (int) ($state['lp_start_a'] ?? 8000))),
        'lp_start_b' => max(1, min(99999, (int) ($state['lp_start_b'] ?? 8000))),
        'lp_history' => array_slice(is_array($state['lp_history'] ?? null) ? $state['lp_history'] : [], 0, 100),
        'timer_mode' => in_array((string) ($state['timer_mode'] ?? ''), ['none', 'countdown'], true) ? (string) $state['timer_mode'] : '',
        'timer_running' => !empty($state['timer_running']),
        'timer_started_at' => max(0, (int) ($state['timer_started_at'] ?? 0)),
        'elapsed_seconds' => max(0, min(1200, (int) ($state['elapsed_seconds'] ?? 0))),
        'dice_a' => max(0, min(6, (int) ($state['dice_a'] ?? 0))),
        'dice_b' => max(0, min(6, (int) ($state['dice_b'] ?? 0))),
        'dice_winner' => in_array(strtoupper((string) ($state['dice_winner'] ?? '')), ['A', 'B'], true) ? strtoupper((string) $state['dice_winner']) : '',
        'turn_decision' => in_array(strtolower((string) ($state['turn_decision'] ?? '')), ['primero', 'segundo'], true) ? strtolower((string) $state['turn_decision']) : '',
        'surrendered_by' => in_array(strtoupper((string) ($state['surrendered_by'] ?? '')), ['A', 'B'], true) ? strtoupper((string) $state['surrendered_by']) : '',
        'surrendered_at' => mb_substr(trim((string) ($state['surrendered_at'] ?? '')), 0, 40),
        'updated_at_ms' => (int) floor(microtime(true) * 1000),
        'correction_at_ms' => max(0,(int)($state['correction_at_ms']??0)),
    ];
}

function ygo_live_duel_get(PDO $pdo, int $matchId, bool $forUpdate = false): array
{
    $match = ygo_live_duel_match($pdo, $matchId);
    if (!$match) return [];
    $select = $pdo->prepare('SELECT duel_number, revision, state_json, updated_by, updated_at FROM sx_ygo_live_duel_state WHERE match_id = ?' . ($forUpdate ? ' FOR UPDATE' : ''));
    $select->execute([$matchId]);
    $row = $select->fetch(PDO::FETCH_ASSOC);
    if (!$row) {
        $state = ygo_live_duel_initial_state($match);
        $pdo->prepare('INSERT IGNORE INTO sx_ygo_live_duel_state (match_id, duel_number, revision, state_json, updated_by) VALUES (?, ?, 1, ?, ?)')
            ->execute([$matchId, $state['duel_number'], json_encode($state, JSON_UNESCAPED_UNICODE), 'judge']);
        $select->execute([$matchId]);
        $row = $select->fetch(PDO::FETCH_ASSOC) ?: [];
    }
    $state = json_decode((string) ($row['state_json'] ?? ''), true);
    $normalizedState = ygo_live_duel_normalize_state(is_array($state) ? $state : ygo_live_duel_initial_state($match));
    $authoritativeState = ygo_live_duel_initial_state($match);
    if ((int) $normalizedState['duel_number'] !== (int) $authoritativeState['duel_number'] && strtoupper((string)$match['status'])!=='FINALIZADA') {
        $normalizedState = ygo_live_duel_normalize_state($authoritativeState);
    }
    $liveDuelNumber = (int) $normalizedState['duel_number'];
    $previousResult = $liveDuelNumber > 1 ? (string) ($match['d' . ($liveDuelNumber - 1)] ?? '') : '';
    $hasDrawReroll = (int) $normalizedState['dice_a'] >= 1 && (int) $normalizedState['dice_b'] >= 1 && in_array((string) $normalizedState['dice_winner'], ['A', 'B'], true);
    if ($previousResult === 'E' && !$hasDrawReroll) {
        $normalizedState['status'] = 'setup';
        $normalizedState['starts_player'] = '';
        $normalizedState['active_side'] = '';
        $normalizedState['timer_running'] = false;
        $normalizedState['timer_started_at'] = 0;
    }
    return [
        'match' => $match,
        'state' => $normalizedState,
        'revision' => max(1, (int) ($row['revision'] ?? 1)),
        'updated_by' => (string) ($row['updated_by'] ?? 'judge'),
        'updated_at' => (string) ($row['updated_at'] ?? ''),
    ];
}

function ygo_live_duel_api(PDO $pdo): void
{
    if(ygo_team_kind(ygo_competition_for_match($pdo,(int)($_REQUEST['match_id']??0)))!==''){http_response_code(409);header('Content-Type: application/json; charset=utf-8');echo json_encode(['ok'=>false,'message'=>'Usa el registro de resultados por equipo en la ficha del evento.']);exit;}
    $matchId = max(0, (int) ($_REQUEST['match_id'] ?? 0));
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store, max-age=0');
    if ($matchId <= 0 || !ygo_live_duel_match($pdo, $matchId)) {
        http_response_code(404);
        echo json_encode(['ok' => false, 'message' => 'Partida no encontrada.'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    try {
        $seasonStmt = $pdo->prepare('SELECT c.season_id FROM sx_ygo_matches m JOIN sx_ygo_competitions c ON c.id=m.competition_id WHERE m.id=?');
        $seasonStmt->execute([$matchId]);
        ygo_season_require_open($pdo, (int) $seasonStmt->fetchColumn(), true);
    } catch (InvalidArgumentException $error) {
        http_response_code(409);
        echo json_encode(['ok'=>false,'read_only'=>true,'message'=>$error->getMessage()], JSON_UNESCAPED_UNICODE);
        exit;
    }
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        echo json_encode(['ok' => true] + ygo_live_duel_get($pdo, $matchId), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }
    $actor = strtoupper((string) ($_POST['actor'] ?? ''));
    $actor = in_array($actor, ['A', 'B'], true) ? $actor : 'judge';
    $operation = preg_replace('/[^a-z_]/', '', strtolower((string) ($_POST['operation'] ?? '')));
    $expectedRevision = max(0, (int) ($_POST['revision'] ?? 0));
    $payload = json_decode((string) ($_POST['payload'] ?? '{}'), true);
    $payload = is_array($payload) ? $payload : [];
    $pdo->beginTransaction();
    try {
        $seasonStmt = $pdo->prepare('SELECT c.season_id FROM sx_ygo_matches m JOIN sx_ygo_competitions c ON c.id=m.competition_id WHERE m.id=?');
        $seasonStmt->execute([$matchId]);
        ygo_season_require_open($pdo, (int) $seasonStmt->fetchColumn(), true, true);
        $live = ygo_live_duel_get($pdo, $matchId, true);
        if ($expectedRevision > 0 && $expectedRevision !== (int) $live['revision']) {
            $pdo->rollBack();
            http_response_code(409);
            echo json_encode(['ok' => false, 'conflict' => true] + ygo_live_duel_get($pdo, $matchId), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            exit;
        }
        $state = $live['state'];
        if ($operation === 'sync' && $actor === 'judge') {
            $incoming = ygo_live_duel_normalize_state($payload);
            if ((int)($incoming['correction_at_ms'] ?? 0) !== (int)($state['correction_at_ms'] ?? 0)) {
                $pdo->rollBack();
                http_response_code(409);
                echo json_encode(['ok' => false, 'conflict' => true] + ygo_live_duel_get($pdo, $matchId), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                exit;
            }
            $incoming['correction_at_ms']=$state['correction_at_ms']??0;
            $expectedDuel = (int) ygo_live_duel_initial_state($live['match'])['duel_number'];
            if ((int) $incoming['duel_number'] !== $expectedDuel) {
                throw new RuntimeException('Guarda el resultado del duelo actual antes de avanzar al siguiente.');
            }
            if ((int) $incoming['duel_number'] !== (int) $state['duel_number']) {
                $state = $incoming;
            } else {
                $merged = ygo_merge_monotonic_turns(
                    ['turn_count' => $state['turn_count'], 'turn_history' => $state['turn_history']],
                    ['turn_count' => $incoming['turn_count'], 'turn_history' => $incoming['turn_history']]
                );
                $incoming['turn_count'] = $merged['detail']['turn_count'];
                $incoming['turn_history'] = $merged['detail']['turn_history'];
                if (in_array((string) ($state['surrendered_by'] ?? ''), ['A', 'B'], true)) {
                    $incoming['surrendered_by'] = (string) $state['surrendered_by'];
                    $incoming['surrendered_at'] = (string) ($state['surrendered_at'] ?? '');
                    $incoming['status'] = 'completed';
                    $incoming['timer_running'] = false;
                    $incoming['timer_started_at'] = 0;
                }
                $state = $incoming;
            }
        } elseif ($operation === 'end_turn') {
            if (!in_array((string) $state['status'], ['running', 'paused'], true) || !in_array((string) $state['active_side'], ['A', 'B'], true)) {
                throw new RuntimeException('El duelo todavía no está en curso.');
            }
            if ($actor !== 'judge' && $actor !== (string) $state['active_side']) {
                throw new RuntimeException('Todavía no es el turno de este lado.');
            }
            $nextTurn = min(999, max((int) $state['turn_count'], count($state['turn_history'])) + 1);
            $liveElapsed = (int) $state['elapsed_seconds'];
            if (!empty($state['timer_running']) && (int) ($state['timer_started_at'] ?? 0) > 0) {
                $liveElapsed = min(1200, $liveElapsed + max(0, (int) floor((microtime(true) * 1000 - (int) $state['timer_started_at']) / 1000)));
            }
            $state['turn_history'][] = ['turn' => $nextTurn, 'side' => (string) $state['active_side'], 'ended_at' => date(DATE_ATOM), 'elapsed_seconds' => $liveElapsed];
            $state['turn_history'] = ygo_normalize_turn_history($state['turn_history']);
            $state['turn_count'] = $nextTurn;
            $state['active_side'] = $state['active_side'] === 'A' ? 'B' : 'A';
        } elseif ($operation === 'surrender') {
            if (!in_array((string) $state['status'], ['running', 'paused'], true) || !in_array((string) $state['active_side'], ['A', 'B'], true)) {
                throw new RuntimeException('El duelo todavia no esta en curso.');
            }
            if ($actor === 'judge' || $actor !== (string) $state['active_side']) {
                throw new RuntimeException('Solo el jugador que tiene el turno puede rendirse.');
            }
            $state['surrendered_by'] = $actor;
            $state['surrendered_at'] = date(DATE_ATOM);
            $state['status'] = 'completed';
            $state['timer_running'] = false;
            $state['timer_started_at'] = 0;
        } elseif ($operation === 'lp_change') {
            if (!in_array((string) $state['status'], ['running', 'paused'], true)) {
                throw new RuntimeException('Los LP solo pueden cambiar mientras el duelo esta en curso.');
            }
            $side = strtoupper((string) ($payload['side'] ?? $actor));
            if (!in_array($side, ['A', 'B'], true) || ($actor !== 'judge' && $actor !== $side)) throw new RuntimeException('No puedes cambiar los LP del otro lado.');
            $field = 'lp_' . strtolower($side);
            $startField = 'lp_start_' . strtolower($side);
            $before = (int) $state[$field];
            $amount = max(0, min(99999, (int) ($payload['amount'] ?? 0)));
            $kind = (string) ($payload['kind'] ?? 'subtract');
            $after = $before;
            if ($kind === 'subtract') $after = max(0, $before - $amount);
            elseif ($kind === 'add') $after = min(99999, $before + $amount);
            elseif ($kind === 'half') $after = (int) floor($before / 2);
            elseif ($kind === 'reset') $after = (int) $state[$startField];
            elseif ($kind === 'set') $after = $amount;
            else throw new RuntimeException('Operación de LP inválida.');
            $state[$field] = $after;
            array_unshift($state['lp_history'], ['side' => strtolower($side), 'operation' => $kind, 'amount' => $amount, 'before' => $before, 'after' => $after, 'at' => date(DATE_ATOM)]);
            $state['lp_history'] = array_slice($state['lp_history'], 0, 100);
        } else {
            throw new RuntimeException('Acción en vivo no permitida.');
        }
        $state = ygo_live_duel_normalize_state($state);
        $revision = (int) $live['revision'] + 1;
        $pdo->prepare('UPDATE sx_ygo_live_duel_state SET duel_number=?, revision=?, state_json=?, updated_by=? WHERE match_id=?')
            ->execute([$state['duel_number'], $revision, json_encode($state, JSON_UNESCAPED_UNICODE), $actor, $matchId]);
        if ($operation === 'surrender') {
            $pdo->prepare('INSERT INTO sx_ygo_audit_log (entity_type, entity_id, action, payload) VALUES (?, ?, ?, ?)')
                ->execute(['match', $matchId, 'duel_surrendered', json_encode(['duel' => (int) $state['duel_number'], 'side' => $actor, 'at' => (string) $state['surrendered_at']], JSON_UNESCAPED_UNICODE)]);
        }
        $pdo->commit();
        echo json_encode(['ok' => true, 'match' => $live['match'], 'state' => $state, 'revision' => $revision, 'updated_by' => $actor], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    } catch (Throwable $error) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        http_response_code(422);
        echo json_encode(['ok' => false, 'message' => $error->getMessage()], JSON_UNESCAPED_UNICODE);
    }
    exit;
}

function ygo_handle_post(PDO $pdo): ?string
{
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST' || !ygo_can_edit()) return ygo_handle_post_serialized($pdo);
    return ygo_with_event_write_lock($pdo, fn() => ygo_handle_post_serialized($pdo));
}

function ygo_handle_post_serialized(PDO $pdo): ?string
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        return null;
    }
    $action = (string) ($_POST['action'] ?? '');
    if(ygo_fixed_program($pdo) && in_array($action,['generate_leagues','generate_cups','generate_world','auto_schedule_qualifier','rebuild_qualifier_calendar'],true)) throw new InvalidArgumentException('Generador historico bloqueado: conserva el calendario fijo. Usa la ficha de un evento nuevo y un motor soportado.');
    ygo_season_initialize_context($pdo);
    if (!ygo_can_edit()) {
        return 'gestion_no_disponible';
    }
    if($action==='configure_statistics_global'){
        $mapping=[];foreach(['FULL','REDUCED','BONUS'] as $code)if(isset($_POST['statistics_k_'.$code])){$value=filter_var($_POST['statistics_k_'.$code],FILTER_VALIDATE_INT);if($value===false)throw new InvalidArgumentException('Importancia estadistica invalida.');$mapping[$code]=$value;}
        ygo_stats_configure_global($pdo,trim((string)($_POST['statistics_anchor']??'')),$mapping);
        ygo_stats_sync($pdo);return 'estadisticas_actualizadas';
    }
    if($action==='publish_statistics_cut'){
        ygo_stats_publish($pdo,(string)($_POST['statistics_kind']??''),(string)($_POST['statistics_scope']??''),(string)($_POST['statistics_cut']??''),trim((string)($_POST['statistics_reason']??'')));
        ygo_stats_sync($pdo);return 'estadisticas_actualizadas';
    }
    $seasonResult = ygo_season_handle_post($pdo, $action);
    if ($seasonResult !== null) return $seasonResult;
    $seasonError = ygo_season_guard_post($pdo, $action);
    if ($seasonError !== null) return $seasonError;
    if ($action === 'sync_offline_form') {
        $requestId = preg_replace('/[^a-zA-Z0-9_-]/', '', (string) ($_POST['offline_request_id'] ?? '')) ?: '';
        $payload = json_decode((string) ($_POST['offline_payload'] ?? ''), true);
        $allowedActions = ['save_deck','save_deck_analysis_profile','save_matchup_analysis','save_team','save_competition','configure_competition','save_match_schedule','save_division','save_settings'];
        if ($requestId === '' || !is_array($payload) || !in_array((string) ($payload['action'] ?? ''), $allowedActions, true)) return 'offline_invalido';
        $existing = $pdo->prepare('SELECT result_code FROM sx_ygo_sync_requests WHERE request_id=? LIMIT 1');
        $existing->execute([$requestId]); $existingResult = $existing->fetchColumn();
        if ($existingResult !== false) return (string) $existingResult;
        $entityMap = [
            'save_deck' => ['sx_ygo_decks','deck_id'], 'save_deck_analysis_profile' => ['sx_ygo_deck_analysis_profiles','deck_id'], 'save_matchup_analysis' => ['sx_ygo_matchup_analyses','analysis_id'], 'save_team' => ['sx_ygo_teams','team_id'],
            'save_competition' => ['sx_ygo_competitions','competition_id'], 'configure_competition' => ['sx_ygo_competitions','competition_id'],
            'save_match_schedule' => ['sx_ygo_matches','schedule_match_id'], 'save_division' => ['sx_ygo_divisions','division_id'],
        ];
        $originalAction = (string) $payload['action'];
        $baseline = trim((string) ($payload['offline_entity_updated_at'] ?? ''));
        if ($baseline !== '' && isset($entityMap[$originalAction])) {
            [$table,$idField] = $entityMap[$originalAction]; $entityId = (int) ($payload[$idField] ?? 0);
            if ($entityId > 0 && ygo_column_exists($pdo,$table,'updated_at')) {
                $current = $pdo->prepare("SELECT updated_at FROM $table WHERE id=? LIMIT 1"); $current->execute([$entityId]);
                $currentUpdated = (string) ($current->fetchColumn() ?: '');
                if ($currentUpdated !== '' && $currentUpdated !== $baseline) return 'offline_conflicto';
            }
        }
        unset($payload['offline_entity_updated_at']);
        $outerPost = $_POST; $_POST = $payload;
        try { $result = ygo_handle_post($pdo) ?: 'offline_guardado'; } finally { $_POST = $outerPost; }
        $pdo->prepare('INSERT INTO sx_ygo_sync_requests (request_id,match_id,payload_hash,result_code) VALUES (?,?,?,?)')->execute([$requestId,0,hash('sha256',json_encode($payload,JSON_UNESCAPED_UNICODE) ?: ''),$result]);
        return $result;
    }
    if ($action === 'save_settings') {
        $allowed = [
            'allow_early_results',
            'allow_overdue_results',
            'result_date_guard',
            'alert_days_ahead',
            'valid_weekdays',
            'allowed_start_time',
            'allowed_end_time',
            'max_matches_per_day',
            'allow_special_days',
            'special_days',
            'venues',
            'stats_minimum_sample',
            'tier_sample_minimum',
            'best_debut_min_matches',
            'meta_presence_weight',
            'tier_zero_presence',
            'tier_zero_winrate',
            'tier_one_score',
            'tier_two_score',
            'elo_k_factor',
            'live_refresh_seconds',
            'notification_minutes_before',
        ];
        foreach ($allowed as $key) {
            if ($key === 'valid_weekdays') {
                $rawDays = $_POST['valid_weekdays'] ?? [];
                $days = is_array($rawDays) ? $rawDays : explode(',', (string) $rawDays);
                $days = array_values(array_intersect(['1', '2', '3', '4', '5', '6', '0'], array_map('strval', $days)));
                $value = implode(',', $days);
                ygo_save_setting($pdo, $key, $value);
                continue;
            } else {
                $value = (string) ($_POST[$key] ?? '0');
            }
            if ($key === 'max_matches_per_day') {
                $value = (string) max(1, min(3, (int) $value));
            } elseif (in_array($key, ['alert_days_ahead', 'live_refresh_seconds', 'notification_minutes_before'], true)) {
                $value = (string) max(1, min(30, (int) $value));
            } elseif (in_array($key, ['stats_minimum_sample', 'tier_sample_minimum', 'best_debut_min_matches'], true)) {
                $value = (string) max(1, min(100, (int) $value));
            } elseif (in_array($key, ['meta_presence_weight', 'tier_zero_presence', 'tier_zero_winrate', 'tier_one_score', 'tier_two_score'], true)) {
                $value = (string) max(0, min(100, (float) $value));
            } elseif ($key === 'elo_k_factor') {
                $value = (string) max(1, min(128, (float) $value));
            } elseif (in_array($key, ['allowed_start_time', 'allowed_end_time'], true)) {
                $value = preg_match('/^\d{2}:\d{2}$/', $value) ? $value : ($key === 'allowed_start_time' ? '10:00' : '22:00');
            } elseif (in_array($key, ['special_days', 'venues'], true)) {
                $value = trim($value);
            } else {
                $value = $value === '1' ? '1' : '0';
            }
            ygo_save_setting($pdo, $key, $value);
        }
        $pdo->prepare('INSERT INTO sx_ygo_audit_log (entity_type, entity_id, action, payload) VALUES (?, ?, ?, ?)')
            ->execute(['settings', ygo_current_season_id($pdo), 'save_settings', json_encode($_POST, JSON_UNESCAPED_UNICODE)]);
        ygo_sync_meta_history($pdo);
        ygo_sync_elo_history($pdo);
        ygo_sync_global_ranking_history($pdo);
        ygo_sync_event_summaries($pdo);
        ygo_sync_medal_unlocks($pdo);
        return 'configuracion_guardada';
    }
    if ($action === 'save_ui_labels') {
        $seasonId = ygo_current_season_id($pdo);
        $keys = $_POST['ui_label_key'] ?? [];
        $titles = $_POST['ui_label_title'] ?? [];
        $subtitles = $_POST['ui_label_subtitle'] ?? [];
        $actives = array_map('strval', (array) ($_POST['ui_label_active'] ?? []));
        $rows = max(count((array) $keys), count((array) $titles));
        $upsert = $pdo->prepare('
            INSERT INTO sx_ygo_ui_labels (season_id, label_key, title, subtitle, active)
            VALUES (?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE title=VALUES(title), subtitle=VALUES(subtitle), active=VALUES(active)
        ');
        for ($i = 0; $i < $rows; $i++) {
            $key = preg_replace('/[^a-zA-Z0-9_.-]/', '', trim((string) ($keys[$i] ?? '')));
            $title = trim((string) ($titles[$i] ?? ''));
            if ($key === '' || $title === '') {
                continue;
            }
            $upsert->execute([$seasonId, $key, $title, trim((string) ($subtitles[$i] ?? '')), in_array($key, $actives, true) ? 1 : 0]);
        }
        $pdo->prepare('INSERT INTO sx_ygo_audit_log (entity_type, entity_id, action, payload) VALUES (?, ?, ?, ?)')
            ->execute(['ui_labels', $seasonId, 'save_ui_labels', json_encode(['rows' => $rows], JSON_UNESCAPED_UNICODE)]);
        return 'textos_guardados';
    }
    if ($action === 'save_catalog_items') {
        $seasonId = ygo_current_season_id($pdo);
        $ids = $_POST['catalog_id'] ?? [];
        $labels = $_POST['catalog_label'] ?? [];
        $icons = $_POST['catalog_icon'] ?? [];
        $accents = $_POST['catalog_accent'] ?? [];
        $sorts = $_POST['catalog_sort_order'] ?? [];
        $actives = array_map('strval', (array) ($_POST['catalog_active'] ?? []));
        $update = $pdo->prepare('UPDATE sx_ygo_catalog_items SET label=?, icon=?, accent=?, sort_order=?, active=? WHERE id=? AND season_id IN (0, ?)');
        foreach ((array) $ids as $index => $idRaw) {
            $id = (int) $idRaw;
            if ($id <= 0) {
                continue;
            }
            $label = trim((string) ($labels[$index] ?? ''));
            if ($label === '') {
                continue;
            }
            $update->execute([
                $label,
                trim((string) ($icons[$index] ?? '')),
                trim((string) ($accents[$index] ?? '')),
                max(0, (int) ($sorts[$index] ?? 100)),
                in_array((string) $id, $actives, true) ? 1 : 0,
                $id,
                $seasonId,
            ]);
        }
        $newKey = preg_replace('/[^a-zA-Z0-9_.-]/', '', trim((string) ($_POST['new_catalog_key'] ?? '')));
        $newCode = preg_replace('/[^a-zA-Z0-9_.-]/', '', trim((string) ($_POST['new_catalog_code'] ?? '')));
        $newLabel = trim((string) ($_POST['new_catalog_label'] ?? ''));
        if ($newKey !== '' && $newCode !== '' && $newLabel !== '') {
            $pdo->prepare('
                INSERT INTO sx_ygo_catalog_items (season_id, catalog_key, item_code, label, icon, accent, sort_order, meta_json, active)
                VALUES (?, ?, ?, ?, ?, ?, ?, "[]", 1)
                ON DUPLICATE KEY UPDATE label=VALUES(label), icon=VALUES(icon), accent=VALUES(accent), sort_order=VALUES(sort_order), active=1
            ')->execute([
                $seasonId,
                $newKey,
                $newCode,
                $newLabel,
                trim((string) ($_POST['new_catalog_icon'] ?? '')),
                trim((string) ($_POST['new_catalog_accent'] ?? '')),
                max(0, (int) ($_POST['new_catalog_sort_order'] ?? 100)),
            ]);
        }
        $pdo->prepare('INSERT INTO sx_ygo_audit_log (entity_type, entity_id, action, payload) VALUES (?, ?, ?, ?)')
            ->execute(['catalog_items', $seasonId, 'save_catalog_items', json_encode(['updated' => count((array) $ids), 'new' => $newKey . ':' . $newCode], JSON_UNESCAPED_UNICODE)]);
        return 'opciones_guardadas';
    }
    if ($action === 'save_team') {
        $id = (int) ($_POST['team_id'] ?? 0);
        $existingLogo = '';
        if ($id > 0) {
            $logoStmt = $pdo->prepare('SELECT logo_url FROM sx_ygo_teams WHERE id = ? LIMIT 1');
            $logoStmt->execute([$id]);
            $existingLogo = (string) ($logoStmt->fetchColumn() ?: '');
        }
        $logoUrl = $existingLogo;
        $values = [
            trim((string) $_POST['team_name']),
            trim((string) $_POST['team_city']),
            $logoUrl,
            strtoupper(substr(trim((string) ($_POST['team_code'] ?? '')), 0, 12)),
            strtoupper(substr(trim((string) ($_POST['team_symbol'] ?? '')), 0, 24)),
        ];
        if ($values[0] !== '') {
            if ($id > 0) {
                $pdo->prepare('UPDATE sx_ygo_teams SET name=?, city=?, logo_url=?, short_code=?, symbol=? WHERE id=?')->execute([...$values, $id]);
            } else {
                $pdo->prepare('INSERT INTO sx_ygo_teams (name, city, logo_url, short_code, symbol, active) VALUES (?, ?, ?, ?, ?, 1)')->execute($values);
                $id = (int) $pdo->lastInsertId();
            }
            $uploadedLogo = ygo_store_team_logo_upload($id);
            if ($uploadedLogo !== '') {
                $pdo->prepare('UPDATE sx_ygo_teams SET logo_url=? WHERE id=?')->execute([$uploadedLogo, $id]);
                ygo_record_logo_history($pdo, 'team', $id, $uploadedLogo);
            }
        }
        if ($competitionType === 'qualifier' && strtolower($competitionRuleset) === 'goat') {
            throw new RuntimeException('El formato GOAT se organiza por liga y divisiones; no admite clasificatorios.');
        }
        return 'equipo_' . $id;
    }
    if ($action === 'save_player') {
        $id = max(0, (int) ($_POST['player_id'] ?? 0));
        $name = trim((string) ($_POST['player_name'] ?? ''));
        $alias = trim((string) ($_POST['player_alias'] ?? ''));
        $teamId = max(0, (int) ($_POST['player_team_id'] ?? 0));
        $deckIds = array_values(array_unique(array_filter(array_map('intval', (array) ($_POST['player_deck_ids'] ?? [])), static fn($value) => $value > 0)));
        if ($name === '') {
            throw new RuntimeException('El nombre del jugador es obligatorio.');
        }
        $pdo->beginTransaction();
        try {
            if ($id > 0) {
                $pdo->prepare('UPDATE sx_ygo_players SET team_id=?, display_name=?, alias=?, active=1 WHERE id=?')->execute([$teamId ?: null, $name, $alias, $id]);
            } else {
                $pdo->prepare('INSERT INTO sx_ygo_players (team_id, display_name, alias, active) VALUES (?, ?, ?, 1)')->execute([$teamId ?: null, $name, $alias]);
                $id = (int) $pdo->lastInsertId();
            }
            $pdo->prepare('UPDATE sx_ygo_player_decks SET active=0 WHERE player_id=?')->execute([$id]);
            $link = $pdo->prepare('INSERT INTO sx_ygo_player_decks (player_id, deck_id, active) VALUES (?, ?, 1) ON DUPLICATE KEY UPDATE active=1');
            foreach ($deckIds as $deckId) $link->execute([$id, $deckId]);
            $pdo->prepare('INSERT INTO sx_ygo_audit_log (entity_type, entity_id, action, payload) VALUES ("player", ?, "save_player", ?)')->execute([$id, json_encode(['team_id' => $teamId, 'deck_ids' => $deckIds], JSON_UNESCAPED_UNICODE)]);
            $pdo->commit();
        } catch (Throwable $error) {
            $pdo->rollBack();
            throw $error;
        }
        return 'jugador_' . $id;
    }
    if ($action === 'select_logo_history') {
        $entityType = trim((string) ($_POST['logo_entity_type'] ?? ''));
        $entityId = (int) ($_POST['logo_entity_id'] ?? 0);
        $logoUrl = trim((string) ($_POST['logo_history_url'] ?? ''));
        $allowed = ['deck' => ['sx_ygo_decks', 'cover_url'], 'team' => ['sx_ygo_teams', 'logo_url'], 'competition' => ['sx_ygo_competitions', 'logo_url']];
        if ($entityId > 0 && $logoUrl !== '' && isset($allowed[$entityType])) {
            $validStmt = $pdo->prepare('SELECT id FROM sx_ygo_logo_history WHERE entity_type=? AND entity_id=? AND logo_url=? LIMIT 1');
            $validStmt->execute([$entityType, $entityId, $logoUrl]);
            if ($validStmt->fetchColumn()) {
                [$table, $column] = $allowed[$entityType];
                $pdo->prepare("UPDATE {$table} SET {$column}=? WHERE id=?")->execute([$logoUrl, $entityId]);
                ygo_record_logo_history($pdo, $entityType, $entityId, $logoUrl, 'history');
            }
        }
        return 'logo_' . $entityType . '_' . $entityId;
    }
    if ($action === 'save_team_deck_links') {
        $teamId = (int) ($_POST['team_id'] ?? 0);
        if ($teamId > 0) {
            $teamExists = $pdo->prepare('SELECT id FROM sx_ygo_teams WHERE id=? AND active=1 LIMIT 1');
            $teamExists->execute([$teamId]);
            if ((int) ($teamExists->fetchColumn() ?: 0) > 0) {
                $addDeckIds = $_POST['team_add_deck_ids'] ?? [];
                $removeDeckIds = $_POST['team_remove_deck_ids'] ?? [];
                if (!is_array($addDeckIds)) {
                    $addDeckIds = [];
                }
                if (!is_array($removeDeckIds)) {
                    $removeDeckIds = [];
                }
                $addDeckIds = array_values(array_unique(array_filter(array_map('intval', $addDeckIds), static fn(int $id): bool => $id > 0)));
                $removeDeckIds = array_values(array_unique(array_filter(array_map('intval', $removeDeckIds), static fn(int $id): bool => $id > 0)));
                if ($addDeckIds) {
                    $placeholders = implode(',', array_fill(0, count($addDeckIds), '?'));
                    $pdo->prepare("UPDATE sx_ygo_decks SET team_id=? WHERE active=1 AND team_id IS NULL AND id IN ($placeholders)")
                        ->execute([$teamId, ...$addDeckIds]);
                }
                if ($removeDeckIds) {
                    $placeholders = implode(',', array_fill(0, count($removeDeckIds), '?'));
                    $pdo->prepare("UPDATE sx_ygo_decks SET team_id=NULL WHERE active=1 AND team_id=? AND id IN ($placeholders)")
                        ->execute([$teamId, ...$removeDeckIds]);
                }
                $pdo->prepare('INSERT INTO sx_ygo_audit_log (entity_type, entity_id, action, payload) VALUES (?, ?, ?, ?)')
                    ->execute(['team', $teamId, 'save_team_deck_links', json_encode(['add' => $addDeckIds, 'remove' => $removeDeckIds], JSON_UNESCAPED_UNICODE)]);
            }
        }
        return 'equipo_ficha';
    }
    if ($action === 'save_division') {
        $id = (int) ($_POST['division_id'] ?? 0);
        $seasonId = ygo_current_season_id($pdo);
        $selectedDeckIds = $_POST['division_deck_ids'] ?? [];
        if (!is_array($selectedDeckIds)) {
            $selectedDeckIds = [];
        }
        $selectedDeckIds = array_values(array_unique(array_filter(array_map('intval', $selectedDeckIds), static fn(int $deckId): bool => $deckId > 0)));
        if (count($selectedDeckIds) > 0 && count($selectedDeckIds) < 4) {
            return 'division_minimo_4_decks';
        }
        $code = strtoupper(trim((string) $_POST['division_code']));
        $name = trim((string) $_POST['division_name']);
        if ($code !== '' && $name !== '') {
            $promotionTo = ($_POST['division_promotion_to'] ?? '') === '' ? null : (int) $_POST['division_promotion_to'];
            $relegationTo = ($_POST['division_relegation_to'] ?? '') === '' ? null : (int) $_POST['division_relegation_to'];
            if ($id > 0 && $promotionTo === $id) {
                $promotionTo = null;
            }
            if ($id > 0 && $relegationTo === $id) {
                $relegationTo = null;
            }
            $leagueCompetitionId = ($_POST['division_league_competition_id'] ?? '') === '' ? null : (int) $_POST['division_league_competition_id'];
            $sourceQualifierId = ($_POST['division_source_qualifier_id'] ?? '') === '' ? null : (int) $_POST['division_source_qualifier_id'];
            $rankFrom = ($_POST['division_rank_from'] ?? '') === '' ? null : max(1, (int) $_POST['division_rank_from']);
            $rankTo = ($_POST['division_rank_to'] ?? '') === '' ? null : max(1, (int) $_POST['division_rank_to']);
            if ($rankFrom !== null && $rankTo !== null && $rankTo < $rankFrom) {
                [$rankFrom, $rankTo] = [$rankTo, $rankFrom];
            }
            $values = [
                $code,
                $name,
                trim((string) $_POST['division_cup']),
                max(1, (int) ($_POST['division_tier'] ?? 1)),
                max(0, (int) ($_POST['division_size'] ?? count($selectedDeckIds))),
                trim((string) $_POST['division_accent']),
                trim((string) $_POST['division_description']),
                ($_POST['division_starts_on'] ?? '') ?: null,
                ($_POST['division_ends_on'] ?? '') ?: null,
                max(0, (int) ($_POST['division_promotion_slots'] ?? 0)),
                max(0, (int) ($_POST['division_relegation_slots'] ?? 0)),
                $promotionTo,
                $relegationTo,
                $leagueCompetitionId,
                $sourceQualifierId,
                $rankFrom,
                $rankTo,
            ];
            if ($id > 0) {
                $pdo->prepare('UPDATE sx_ygo_divisions SET code=?, name=?, cup_name=?, tier_order=?, deck_count=?, accent=?, description=?, starts_on=?, ends_on=?, promotion_slots=?, relegation_slots=?, promotion_to_division_id=?, relegation_to_division_id=?, league_competition_id=?, source_qualifier_id=?, rank_from=?, rank_to=? WHERE id=? AND season_id=?')
                    ->execute([...$values, $id, $seasonId]);
            } else {
                $pdo->prepare('
                    INSERT INTO sx_ygo_divisions
                        (season_id, code, name, cup_name, tier_order, deck_count, accent, description, starts_on, ends_on, promotion_slots, relegation_slots, promotion_to_division_id, relegation_to_division_id, league_competition_id, source_qualifier_id, rank_from, rank_to, active)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1)
                    ON DUPLICATE KEY UPDATE name=VALUES(name), cup_name=VALUES(cup_name), tier_order=VALUES(tier_order), deck_count=VALUES(deck_count), accent=VALUES(accent), description=VALUES(description), starts_on=VALUES(starts_on), ends_on=VALUES(ends_on), promotion_slots=VALUES(promotion_slots), relegation_slots=VALUES(relegation_slots), promotion_to_division_id=VALUES(promotion_to_division_id), relegation_to_division_id=VALUES(relegation_to_division_id), league_competition_id=VALUES(league_competition_id), source_qualifier_id=VALUES(source_qualifier_id), rank_from=VALUES(rank_from), rank_to=VALUES(rank_to), active=1
                ')->execute([$seasonId, ...$values]);
                $lookup = $pdo->prepare('SELECT id FROM sx_ygo_divisions WHERE season_id=? AND code=? LIMIT 1');
                $lookup->execute([$seasonId, $code]);
                $id = (int) $lookup->fetchColumn();
            }
            if ($id > 0) {
                $uploadedLogo = ygo_store_division_logo_upload($id);
                if ($uploadedLogo !== '') {
                    $pdo->prepare('UPDATE sx_ygo_divisions SET logo_url=? WHERE id=? AND season_id=?')->execute([$uploadedLogo, $id, $seasonId]);
                }
            }
            if ($id > 0 && $selectedDeckIds) {
                $placeholders = implode(',', array_fill(0, count($selectedDeckIds), '?'));
                $deckStmt = $pdo->prepare("UPDATE sx_ygo_decks SET division_id=? WHERE active=1 AND id IN ($placeholders)");
                $deckStmt->execute([$id, ...$selectedDeckIds]);
                $pdo->prepare('UPDATE sx_ygo_divisions SET deck_count=(SELECT COUNT(*) FROM sx_ygo_decks WHERE division_id=? AND active=1) WHERE id=?')
                    ->execute([$id, $id]);
            } elseif ($id > 0 && $sourceQualifierId && $rankFrom && $rankTo) {
                ygo_apply_division_from_qualifier($pdo, $id);
            }
        }
        return 'division';
    }
    if ($action === 'save_match_schedule') {
        $matchId = (int) ($_POST['schedule_match_id'] ?? 0);
        if ($matchId > 0) {
            $matchScheduleStmt = $pdo->prepare('SELECT competition_id, phase, status, scheduled_on, starts_at, venue, judge, notes FROM sx_ygo_matches WHERE id = ? LIMIT 1');
            $matchScheduleStmt->execute([$matchId]);
            $matchSchedule = $matchScheduleStmt->fetch(PDO::FETCH_ASSOC) ?: [];
            if (!$matchSchedule) {
                return 'calendario_bloqueado_regla';
            }
            if (in_array(strtoupper((string) ($matchSchedule['status'] ?? '')), ['FINALIZADA', 'FINALIZADO', 'COMPLETED'], true)) {
                return 'calendario_bloqueado_finalizado';
            }
            $scheduledOn = ($_POST['scheduled_on'] ?? '') ?: null;
            $startsAt = ($_POST['starts_at'] ?? '') ?: null;
            $venue = trim((string) ($_POST['venue'] ?? ''));
            $scheduleGuard = ygo_validate_match_schedule($pdo, $matchId, $scheduledOn, $startsAt, $venue);
            if (!$scheduleGuard['ok']) {
                $pdo->prepare('INSERT INTO sx_ygo_audit_log (entity_type, entity_id, action, payload) VALUES (?, ?, ?, ?)')
                    ->execute(['match', $matchId, 'blocked_schedule_by_rules', json_encode($scheduleGuard, JSON_UNESCAPED_UNICODE)]);
                return 'calendario_bloqueado_regla';
            }
            $pdo->prepare('UPDATE sx_ygo_matches SET scheduled_on=?, starts_at=?, venue=?, judge=?, notes=? WHERE id=?')
                ->execute([
                    $scheduledOn,
                    $startsAt,
                    $venue,
                    trim((string) ($_POST['judge'] ?? '')),
                    trim((string) ($_POST['match_notes'] ?? '')),
                    $matchId,
                ]);
            $pdo->prepare('INSERT INTO sx_ygo_audit_log (entity_type, entity_id, action, payload) VALUES (?, ?, ?, ?)')
                ->execute(['match', $matchId, 'reschedule_match', json_encode([
                    'before' => [
                        'scheduled_on' => $matchSchedule['scheduled_on'] ?? null,
                        'starts_at' => $matchSchedule['starts_at'] ?? null,
                        'venue' => $matchSchedule['venue'] ?? '',
                        'judge' => $matchSchedule['judge'] ?? '',
                        'notes' => $matchSchedule['notes'] ?? '',
                    ],
                    'after' => [
                        'scheduled_on' => $scheduledOn,
                        'starts_at' => $startsAt,
                        'venue' => $venue,
                        'judge' => trim((string) ($_POST['judge'] ?? '')),
                        'notes' => trim((string) ($_POST['match_notes'] ?? '')),
                    ],
                ], JSON_UNESCAPED_UNICODE)]);
            $matchCompetitionId = (int) ($matchSchedule['competition_id'] ?? 0);
            if ($matchCompetitionId > 0) {
                ygo_rebuild_competition_calendar_events_from_matches($pdo, ygo_current_season_id($pdo), $matchCompetitionId, (string) ($matchSchedule['phase'] ?? 'match'));
                $competitionTypeStmt = $pdo->prepare('SELECT type FROM sx_ygo_competitions WHERE id=? LIMIT 1');
                $competitionTypeStmt->execute([$matchCompetitionId]);
                if ((string) $competitionTypeStmt->fetchColumn() !== 'qualifier') {
                    ygo_reflow_active_qualifiers($pdo);
                }
            }
        }
        return 'calendario_reprogramado';
    }
    if ($action === 'generate_friendlies') {
        return 'amistosos_' . ygo_generate_friendlies($pdo);
    }
    if ($action === 'generate_leagues') {
        return 'ligas_' . ygo_generate_leagues($pdo);
    }
    if ($action === 'generate_cups') {
        return 'copas_' . ygo_generate_cups($pdo);
    }
    if ($action === 'generate_world') {
        return 'mundial_' . ygo_generate_world($pdo);
    }
    if ($action === 'auto_schedule_qualifier') {
        return 'clasificatorio_programado_' . ygo_auto_schedule_qualifier($pdo);
    }
    if ($action === 'rebuild_qualifier_calendar') {
        return 'clasificatorio_reconstruido_' . ygo_rebuild_qualifier_calendar($pdo);
    }
    if($action==='finalize_ffa'){$id=(int)($_POST['competition_id']??0);ygo_ffa_finalize($pdo,$id);return 'evento_configurado_'.$id;}
    if($action==='create_ffa_match'){
        $id=(int)($_POST['competition_id']??0);$ids=array_map('intval',$_POST['ffa_decks']??[]);
        ygo_ffa_create($pdo,$id,$ids,(string)($_POST['ffa_date']??''),(string)($_POST['ffa_time']??''));return 'evento_configurado_'.$id;
    }
    if($action==='save_ffa_action'){
        $id=(int)($_POST['ffa_match_id']??0);$command=['kind'=>(string)($_POST['ffa_kind']??'')];
        foreach(['deck_id','delta'] as $k)if(isset($_POST['ffa_'.$k])){$n=filter_var($_POST['ffa_'.$k],FILTER_VALIDATE_INT);if($n===false)throw new InvalidArgumentException('Valor de accion invalido.');$command[$k]=$n;}
        if(isset($_POST['ffa_reason']))$command['reason']=(string)$_POST['ffa_reason'];
        ygo_ffa_command($pdo,$id,(int)($_POST['ffa_revision']??-1),(string)($_POST['ffa_request']??''),$command);
        $q=$pdo->prepare('SELECT competition_id FROM sx_ygo_ffa_matches WHERE id=?');$q->execute([$id]);return 'evento_configurado_'.(int)$q->fetchColumn();
    }
    if($action==='save_team_lineups'){
        $rows=ygo_team_decode_payload((string)($_POST['team_lineups_json']??'[]'));
        $id=(int)($_POST['competition_id']??0);ygo_team_save($pdo,$id,$rows);return 'evento_configurado_'.$id;
    }
    if($action==='save_team_result'){
        $rows=ygo_team_decode_payload((string)($_POST['team_sequences_json']??'[]'));
        $mid=(int)($_POST['match_id']??0);$c=ygo_competition_for_match($pdo,$mid);ygo_team_save_result($pdo,$mid,$rows,(int)($_POST['team_revision']??0));return 'evento_configurado_'.(int)($c['id']??0);
    }
    if(in_array($action,['freeze_event_card_rules','save_event_card_rules'],true))throw new InvalidArgumentException('El control de cartas, pools y elecciones de Draft fue retirado.');
    if($action==='save_event_admission'){
        try{$rows=json_decode((string)($_POST['admission_sources_json']??'[]'),true,64,JSON_THROW_ON_ERROR);}catch(JsonException $e){throw new InvalidArgumentException('Las fuentes recibidas no tienen un formato valido.');}
        if(!is_array($rows))throw new InvalidArgumentException('Fuentes invalidas.');
        $capacity=filter_var($_POST['admission_capacity']??0,FILTER_VALIDATE_INT);if($capacity===false)throw new InvalidArgumentException('El total de plazas debe ser entero.');
        $id=(int)($_POST['competition_id']??0);ygo_admission_save($pdo,$id,$rows,$capacity);
        return 'evento_configurado_'.$id;
    }
    if ($action === 'configure_competition') {
        try {
            ygo_configure_competition($pdo);
        } catch (InvalidArgumentException $error) {
            $_SESSION['ygo_season_error'] = $error->getMessage();
            return 'evento_temporada_bloqueada';
        }
        return 'evento_configurado_' . max(0, (int) ($_POST['competition_id'] ?? 0));
    }
    if ($action === 'finalize_competition') {
        $competitionId = (int) ($_POST['competition_id'] ?? 0);
        $seasonId = ygo_current_season_id($pdo);
        $stmt = $pdo->prepare("SELECT id, status FROM sx_ygo_competitions WHERE id = ? AND season_id = ? AND active = 1 LIMIT 1");
        $stmt->execute([$competitionId, $seasonId]);
        $competition = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
        if ($competition) {
            $currentStatus = ygo_normalize_status_key((string) ($competition['status'] ?? ''));
            $ready = ygo_competition_ready_to_finish($pdo, $competitionId);
            if ($ready['ok'] && in_array($currentStatus, ['active', 'activo', 'pending_event', 'pendiente_evento'], true)) {
                $pdo->prepare("UPDATE sx_ygo_competitions SET status='completed' WHERE id=? AND season_id=?")->execute([$competitionId, $seasonId]);
                ygo_persist_competition_standings($pdo, $seasonId, $competitionId, 'finalize_competition');
                $divisionStmt = $pdo->prepare('SELECT id FROM sx_ygo_divisions WHERE season_id = ? AND source_qualifier_id = ? AND active = 1');
                $divisionStmt->execute([$seasonId, $competitionId]);
                $divisionMoves = 0;
                foreach (array_map('intval', $divisionStmt->fetchAll(PDO::FETCH_COLUMN)) as $divisionId) {
                    $divisionMoves += ygo_apply_division_from_qualifier($pdo, $divisionId);
                }
                $leagueMoves = ygo_apply_league_division_movements($pdo, $competitionId);
                $honors = ygo_record_competition_honor($pdo, $competitionId);
                ygo_record_competition_status_history($pdo, $seasonId, $competitionId, (string) ($competition['status'] ?? ''), 'completed', 'finalize_competition', $ready);
                $pdo->prepare('INSERT INTO sx_ygo_audit_log (entity_type, entity_id, action, payload) VALUES (?, ?, ?, ?)')
                    ->execute(['competition', $competitionId, 'finalize_competition', json_encode($ready + ['division_moves' => $divisionMoves, 'league_moves' => $leagueMoves, 'honors' => $honors], JSON_UNESCAPED_UNICODE)]);
            } else {
                $pdo->prepare('INSERT INTO sx_ygo_audit_log (entity_type, entity_id, action, payload) VALUES (?, ?, ?, ?)')
                    ->execute(['competition', $competitionId, 'blocked_finalize_competition', json_encode($ready + ['status' => $currentStatus], JSON_UNESCAPED_UNICODE)]);
            }
        }
        return 'evento_configurado_' . $competitionId;
    }
    if ($action === 'revert_event_stage') {
        $competitionId=(int)($_POST['competition_id']??0);
        ygo_workflow_revert($pdo,$competitionId,(int)($_POST['expected_round']??0),(string)($_POST['recovery_reason']??''),(string)($_POST['recovery_token']??''));
        return 'evento_configurado_'.$competitionId;
    }
    if ($action === 'advance_competition') {
        $competitionId = (int) ($_POST['competition_id'] ?? 0);
        return 'evento_avanzado_' . $competitionId . '_' . ygo_advance_competition_phase($pdo, $competitionId);
    }
    if ($action === 'apply_qualifier_divisions') {
        $competitionId = (int) ($_POST['competition_id'] ?? 0);
        $seasonId = ygo_current_season_id($pdo);
        $typeStmt = $pdo->prepare("SELECT COUNT(*) FROM sx_ygo_competitions WHERE id = ? AND season_id = ? AND type = 'qualifier' AND active = 1");
        $typeStmt->execute([$competitionId, $seasonId]);
        if ((int) $typeStmt->fetchColumn() < 1) {
            return 'divisiones_aplicadas_' . $competitionId . '_0';
        }
        $divisionStmt = $pdo->prepare('SELECT id FROM sx_ygo_divisions WHERE season_id = ? AND source_qualifier_id = ? AND active = 1');
        $divisionStmt->execute([$seasonId, $competitionId]);
        $moves = 0;
        foreach (array_map('intval', $divisionStmt->fetchAll(PDO::FETCH_COLUMN)) as $divisionId) {
            $moves += ygo_apply_division_from_qualifier($pdo, $divisionId);
        }
        $pdo->prepare('INSERT INTO sx_ygo_audit_log(entity_type,entity_id,action,payload) VALUES(?,?,?,?)')->execute(['competition',$competitionId,'apply_qualifier_divisions',json_encode(['moved'=>$moves],JSON_UNESCAPED_UNICODE)]);
        return 'divisiones_aplicadas_' . $competitionId . '_' . $moves;
    }
    if ($action === 'apply_league_movements') {
        $competitionId = (int) ($_POST['competition_id'] ?? 0);
        $seasonId = ygo_current_season_id($pdo);
        $typeStmt = $pdo->prepare("SELECT COUNT(*) FROM sx_ygo_competitions WHERE id = ? AND season_id = ? AND type = 'league' AND active = 1");
        $typeStmt->execute([$competitionId, $seasonId]);
        if ((int) $typeStmt->fetchColumn() < 1) {
            return 'movimientos_liga_' . $competitionId . '_0';
        }
        return 'movimientos_liga_' . $competitionId . '_' . ygo_apply_league_division_movements($pdo, $competitionId);
    }
    if ($action === 'recalculate_competition_standings') {
        $competitionId = (int) ($_POST['competition_id'] ?? 0);
        $seasonId = ygo_current_season_id($pdo);
        $exists = 0;
        if ($competitionId > 0) {
            $stmt = $pdo->prepare("SELECT id FROM sx_ygo_competitions WHERE id = ? AND season_id = ? AND active = 1 AND type NOT IN ('cup', 'final') LIMIT 1");
            $stmt->execute([$competitionId, $seasonId]);
            $exists = (int) ($stmt->fetchColumn() ?: 0);
        }
        if ($exists > 0) {
            ygo_persist_competition_standings($pdo, $seasonId, $competitionId, 'manual_recalculate');
            $pdo->prepare('INSERT INTO sx_ygo_audit_log (entity_type, entity_id, action, payload) VALUES (?, ?, ?, ?)')
                ->execute(['competition', $competitionId, 'recalculate_standings', json_encode(['source' => 'manual_button'], JSON_UNESCAPED_UNICODE)]);
        }
        return 'ranking_recalculado_' . $competitionId . '_' . ($exists > 0 ? 1 : 0);
    }
    if ($action === 'archive_qa_codex_data') {
        $seasonId = ygo_current_season_id($pdo);
        $counts = ['competitions' => 0, 'divisions' => 0, 'decks' => 0, 'teams' => 0];
        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare("UPDATE sx_ygo_competitions SET active=0, status='archived' WHERE season_id=? AND name LIKE 'QA Codex %' AND name <> 'Clasificatorio inicial MD'");
            $stmt->execute([$seasonId]);
            $counts['competitions'] = $stmt->rowCount();
            $stmt = $pdo->prepare("UPDATE sx_ygo_divisions SET active=0 WHERE season_id=? AND name LIKE 'QA Codex %'");
            $stmt->execute([$seasonId]);
            $counts['divisions'] = $stmt->rowCount();
            $stmt = $pdo->prepare("UPDATE sx_ygo_decks SET active=0 WHERE name LIKE 'QA Codex %'");
            $stmt->execute();
            $counts['decks'] = $stmt->rowCount();
            $stmt = $pdo->prepare("UPDATE sx_ygo_teams SET active=0 WHERE name LIKE 'QA Codex %'");
            $stmt->execute();
            $counts['teams'] = $stmt->rowCount();
            $pdo->prepare('INSERT INTO sx_ygo_audit_log (entity_type, entity_id, action, payload) VALUES (?, ?, ?, ?)')
                ->execute(['qa', $seasonId, 'archive_qa_codex_data', json_encode($counts, JSON_UNESCAPED_UNICODE)]);
            $pdo->commit();
        } catch (Throwable $error) {
            $pdo->rollBack();
            throw $error;
        }
        return 'qa_codex_archivado_' . array_sum($counts);
    }
    if ($action === 'delete_competition') {
        return 'evento_eliminado_' . ygo_delete_competition($pdo);
    }
    if ($action === 'delete_manual_friendly') {
        $matchId = max(0, (int) ($_POST['friendly_match_id'] ?? 0));
        $matchStmt = $pdo->prepare("SELECT id, deck_a_id, deck_b_id, scheduled_on, starts_at, status FROM sx_ygo_matches WHERE id = ? AND phase = 'friendly' AND competition_id IS NULL LIMIT 1");
        $matchStmt->execute([$matchId]);
        $manualFriendly = $matchStmt->fetch(PDO::FETCH_ASSOC);
        if (!$manualFriendly) {
            return 'amistoso_no_eliminado';
        }
        $pdo->beginTransaction();
        try {
            foreach (['sx_ygo_sync_requests', 'sx_ygo_turn_audit', 'sx_ygo_live_duel_state', 'sx_ygo_match_snapshots', 'sx_ygo_elo_history', 'sx_ygo_match_results'] as $table) {
                $pdo->prepare("DELETE FROM {$table} WHERE match_id = ?")->execute([$matchId]);
            }
            $pdo->prepare('DELETE FROM sx_ygo_medal_unlocks WHERE match_id = ?')->execute([$matchId]);
            $pdo->prepare('DELETE FROM sx_ygo_checkins WHERE match_id = ?')->execute([$matchId]);
            $pdo->prepare("DELETE FROM sx_ygo_matches WHERE id = ? AND phase = 'friendly' AND competition_id IS NULL")->execute([$matchId]);
            $pdo->prepare('INSERT INTO sx_ygo_audit_log (entity_type, entity_id, action, payload) VALUES (?, ?, ?, ?)')
                ->execute(['match', $matchId, 'delete_manual_friendly', json_encode($manualFriendly, JSON_UNESCAPED_UNICODE)]);
            $pdo->commit();
        } catch (Throwable $error) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            throw $error;
        }
        return 'amistoso_eliminado';
    }
    if ($action === 'advance_phase') {
        $phase = preg_replace('/[^a-z_]/', '', strtolower((string) ($_POST['phase'] ?? 'cup')));
        if ($phase === '') {
            $phase = 'cup';
        }
        $competitionId = (int) ($_POST['competition_id'] ?? 0);
        return 'avance_' . $phase . '_' . ygo_advance_phase($pdo, $phase, $competitionId > 0 ? $competitionId : null);
    }
    if ($action === 'create_match') {
        $divisionId = (int) ($_POST['manual_division_id'] ?? ygo_first_division_id($pdo));
        $phase = preg_replace('/[^a-z_]/', '', strtolower((string) ($_POST['manual_phase'] ?? 'league')));
        if ($phase === '') {
            $phase = 'league';
        }
        $round = max(1, (int) ($_POST['manual_round'] ?? 1));
        $table = max(1, (int) ($_POST['manual_table'] ?? 1));
        $deckA = (int) ($_POST['manual_deck_a_id'] ?? 0);
        $deckB = (int) ($_POST['manual_deck_b_id'] ?? 0);
        if ($deckA > 0 && $deckB > 0 && $deckA !== $deckB) {
            $competitionId = ($_POST['manual_competition_id'] ?? '') === '' ? null : (int) $_POST['manual_competition_id'];
            if($competitionId){$teamCheck=$pdo->prepare('SELECT * FROM sx_ygo_competitions WHERE id=?');$teamCheck->execute([$competitionId]);if(ygo_team_kind($manualEvent=$teamCheck->fetch(PDO::FETCH_ASSOC) ?: [])!==''||($manualEvent['execution_model']??'')==='free_for_all')throw new InvalidArgumentException('Programa esta modalidad desde su ficha con las alineaciones fijadas.');}
            $format = preg_replace('/[^a-z0-9_]/', '', strtolower((string) ($_POST['manual_match_format'] ?? '1v1')));
            $bestOf = max(1, min(10, (int) ($_POST['manual_best_of'] ?? 5)));
            $participantsPerSide = max(1, min(3, (int) ($_POST['manual_participants_per_side'] ?? 1)));
            if (preg_match('/^([123])v\1$/', $format, $matchParts)) {
                $participantsPerSide = (int) $matchParts[1];
            }
            $deckC = ($_POST['manual_deck_c_id'] ?? '') === '' ? null : (int) $_POST['manual_deck_c_id'];
            $deckD = ($_POST['manual_deck_d_id'] ?? '') === '' ? null : (int) $_POST['manual_deck_d_id'];
            $deckE = ($_POST['manual_deck_e_id'] ?? '') === '' ? null : (int) $_POST['manual_deck_e_id'];
            $deckF = ($_POST['manual_deck_f_id'] ?? '') === '' ? null : (int) $_POST['manual_deck_f_id'];
            $manualRuleset = preg_replace('/[^a-z0-9_]/', '', strtolower((string) ($_POST['manual_ruleset_code'] ?? '')));
            $manualGameRuleId = $competitionId === null ? ygo_valid_match_game_rule_id($pdo, ygo_current_season_id($pdo), (int) ($_POST['manual_game_rule_id'] ?? 0), $phase) : null;
            $deckValidation = ygo_match_deck_validation($pdo, $competitionId, $divisionId, [$deckA, $deckB, $deckC, $deckD, $deckE, $deckF], $participantsPerSide);
            if ($deckValidation['ok'] && $competitionId === null && $manualRuleset !== '') {
                $rulesetDeckIds = ygo_deck_ids_for_ruleset($pdo, $manualRuleset);
                $selectedDeckIds = array_values(array_filter([$deckA, $deckB, $deckC, $deckD, $deckE, $deckF]));
                if (!$rulesetDeckIds || array_diff($selectedDeckIds, $rulesetDeckIds)) {
                    $deckValidation = ['ok' => false, 'message' => 'Hay decks que no estan asociados al formato seleccionado.'];
                }
            }
            if (!$deckValidation['ok']) {
                $pdo->prepare('INSERT INTO sx_ygo_audit_log (entity_type, entity_id, action, payload) VALUES (?, ?, ?, ?)')
                    ->execute(['match', null, 'blocked_create_match_by_deck_pool', json_encode(['competition_id' => $competitionId, 'division_id' => $divisionId, 'deck_ids' => [$deckA, $deckB, $deckC, $deckD, $deckE, $deckF], 'message' => $deckValidation['message']], JSON_UNESCAPED_UNICODE)]);
                return 'partido_bloqueado_pool';
            }
            $manualScheduledOn = ($_POST['manual_scheduled_on'] ?? '') ?: null;
            $manualStartsAt = ($_POST['manual_starts_at'] ?? '') ?: null;
            $manualVenue = trim((string) ($_POST['manual_venue'] ?? ''));
            $scheduleGuard = ygo_validate_match_schedule($pdo, 0, $manualScheduledOn, $manualStartsAt, $manualVenue);
            if (!$scheduleGuard['ok']) {
                $pdo->prepare('INSERT INTO sx_ygo_audit_log (entity_type, entity_id, action, payload) VALUES (?, ?, ?, ?)')
                    ->execute(['match', null, 'blocked_create_match_by_rules', json_encode($scheduleGuard, JSON_UNESCAPED_UNICODE)]);
                return 'partido_bloqueado_regla';
            }
            if($competitionId){
                $q=$pdo->prepare('SELECT * FROM sx_ygo_competitions WHERE id=? AND active=1');$q->execute([$competitionId]);$manualEvent=$q->fetch(PDO::FETCH_ASSOC);
                if(!$manualEvent)throw new InvalidArgumentException('Evento inexistente.');
                if(in_array($manualEvent['execution_model'],ygo_workflow_models(),true))throw new InvalidArgumentException('Este sistema genera sus etapas desde la ficha; no admite cruces manuales adicionales.');
                ygo_require_supported_model(ygo_team_context($pdo,$manualEvent));
            }
            if ($competitionId === null && $phase === 'friendly') {
                $nextTableStmt = $pdo->query("SELECT COALESCE(MAX(table_number), 0) + 1 FROM sx_ygo_matches WHERE competition_id IS NULL AND phase = 'friendly'");
                $table = max(1, (int) $nextTableStmt->fetchColumn());
            }
            $displayOrder = (($round - 1) * 100) + $table;
            $pdo->prepare("
                INSERT INTO sx_ygo_matches (division_id, competition_id, game_rule_id, phase, match_format, best_of, participants_per_side, round_number, table_number, bracket_round_label, bracket_slot, bracket_side, display_order, generation_number, deck_a_id, deck_b_id, deck_c_id, deck_d_id, deck_e_id, deck_f_id, status, scheduled_on, starts_at, venue, judge, notes)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'main', ?, 1, ?, ?, ?, ?, ?, ?, 'PENDIENTE', ?, ?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE competition_id=VALUES(competition_id), game_rule_id=VALUES(game_rule_id), deck_a_id=VALUES(deck_a_id), deck_b_id=VALUES(deck_b_id), deck_c_id=VALUES(deck_c_id), deck_d_id=VALUES(deck_d_id), deck_e_id=VALUES(deck_e_id), deck_f_id=VALUES(deck_f_id), match_format=VALUES(match_format), best_of=VALUES(best_of), participants_per_side=VALUES(participants_per_side), bracket_round_label=VALUES(bracket_round_label), bracket_slot=VALUES(bracket_slot), bracket_side=VALUES(bracket_side), display_order=VALUES(display_order), scheduled_on=VALUES(scheduled_on), starts_at=VALUES(starts_at), venue=VALUES(venue), judge=VALUES(judge), notes=VALUES(notes)
            ")->execute([
                $divisionId > 0 ? $divisionId : null, $competitionId, $manualGameRuleId, $phase, $format, $bestOf, $participantsPerSide,
                $round, $table, 'Ronda ' . $round, $table, $displayOrder, $deckA, $deckB, $deckC, $deckD, $deckE, $deckF,
                $manualScheduledOn,
                $manualStartsAt,
                $manualVenue,
                trim((string) ($_POST['manual_judge'] ?? '')),
                trim((string) ($_POST['manual_notes'] ?? '')),
            ]);
            if ($competitionId) {
                ygo_rebuild_competition_calendar_events_from_matches($pdo, ygo_current_season_id($pdo), (int) $competitionId, $phase);
            }
        } else {
            return 'partido_invalido';
        }
        return 'partido_manual';
    }
    if ($action === 'save_deck_card') {
        $deckId = (int) ($_POST['card_deck_id'] ?? 0);
        $cardName = trim((string) ($_POST['card_name'] ?? ''));
        if ($deckId > 0 && $cardName !== '') {
            $pdo->prepare('INSERT INTO sx_ygo_deck_cards (deck_id, card_name, role, quantity, section, notes) VALUES (?, ?, ?, ?, ?, ?)')
                ->execute([$deckId, $cardName, trim((string) ($_POST['card_role'] ?? '')), max(1, (int) ($_POST['card_quantity'] ?? 1)), trim((string) ($_POST['card_section'] ?? 'main')), trim((string) ($_POST['card_notes'] ?? ''))]);
        }
        return 'carta_deck';
    }
    if ($action === 'import_deck_cards') {
        $deckId = (int) ($_POST['import_card_deck_id'] ?? 0);
        $raw = trim((string) ($_POST['deck_card_rows'] ?? ''));
        $replace = !empty($_POST['replace_deck_cards']);
        if ($deckId > 0 && $raw !== '') {
            $rows = preg_split('/\R/', $raw) ?: [];
            $valid = [];
            foreach ($rows as $line) {
                $columns = str_getcsv(trim($line), ';');
                if (count($columns) < 3) $columns = str_getcsv(trim($line), ',');
                $section = strtolower(trim((string) ($columns[0] ?? 'main')));
                $quantity = max(1, min(3, (int) ($columns[1] ?? 1)));
                $name = mb_substr(trim((string) ($columns[2] ?? '')), 0, 180);
                if (!in_array($section, ['main','extra','side','tech'], true) || $name === '' || strtolower($name) === 'nombre') continue;
                $valid[] = [$deckId,$name,mb_substr(trim((string)($columns[3] ?? '')),0,120),$quantity,$section,mb_substr(trim((string)($columns[4] ?? '')),0,255)];
            }
            if ($valid) {
                $pdo->beginTransaction();
                try {
                    if ($replace) $pdo->prepare('DELETE FROM sx_ygo_deck_cards WHERE deck_id=?')->execute([$deckId]);
                    $insert = $pdo->prepare('INSERT INTO sx_ygo_deck_cards (deck_id,card_name,role,quantity,section,notes) VALUES (?,?,?,?,?,?)');
                    foreach ($valid as $row) $insert->execute($row);
                    $pdo->commit();
                } catch (Throwable $error) { $pdo->rollBack(); throw $error; }
            }
        }
        return 'cartas_importadas';
    }
    if ($action === 'save_deck_media') {
        $deckId = (int) ($_POST['media_deck_id'] ?? 0);
        $url = trim((string) ($_POST['media_url'] ?? ''));
        if ($deckId > 0 && $url !== '') {
            $pdo->prepare('INSERT INTO sx_ygo_deck_media (deck_id, media_type, url, title) VALUES (?, ?, ?, ?)')
                ->execute([$deckId, trim((string) ($_POST['media_type'] ?? 'image')), $url, trim((string) ($_POST['media_title'] ?? ''))]);
        }
        return 'media_deck';
    }
    if ($action === 'save_honor') {
        $seasonId=ygo_current_season_id($pdo);$eventId=max(0,(int)($_POST['honor_competition_id']??0));
        $champion=max(0,(int)($_POST['honor_champion_deck_id']??0));$runner=max(0,(int)($_POST['honor_runner_deck_id']??0));$notes=trim((string)($_POST['honor_notes']??''));
        $owns=!$pdo->inTransaction();if($owns)$pdo->beginTransaction();
        try{
            if($champion<1||$champion===$runner)throw new InvalidArgumentException('Selecciona un campeon identificado y distinto del subcampeon.');
            if($eventId>0){
                $q=$pdo->prepare('SELECT * FROM sx_ygo_competitions WHERE id=? AND season_id=? FOR UPDATE');$q->execute([$eventId,$seasonId]);$event=$q->fetch(PDO::FETCH_ASSOC);
                if(!$event||!in_array(ygo_normalize_status_key((string)$event['status']),['completed','closed'],true))throw new InvalidArgumentException('El titulo requiere un evento finalizado confirmado.');
                if(($event['execution_model']??'')!=='manual')throw new InvalidArgumentException('El campeon de este sistema se determina desde su flujo competitivo; no se sustituye desde Palmares.');
                if($notes==='')throw new InvalidArgumentException('La adjudicacion manual requiere justificar la evidencia del titulo.');
                $q=$pdo->prepare('SELECT COUNT(*) FROM sx_ygo_honors WHERE competition_id=?');$q->execute([$eventId]);if($q->fetchColumn())throw new InvalidArgumentException('Esta edicion ya tiene un titulo. Su correccion requiere una revision controlada.');
                $q=$pdo->prepare('SELECT deck_id FROM sx_ygo_competition_decks WHERE competition_id=? AND active=1 UNION SELECT deck_a_id FROM sx_ygo_matches WHERE competition_id=? UNION SELECT deck_b_id FROM sx_ygo_matches WHERE competition_id=?');$q->execute([$eventId,$eventId,$eventId]);$allowed=array_map('intval',$q->fetchAll(PDO::FETCH_COLUMN));
                if(!in_array($champion,$allowed,true)||($runner&&!in_array($runner,$allowed,true)))throw new InvalidArgumentException('El titulo solo puede atribuirse a participantes de esta edicion.');
                ygo_validate_format_roster($pdo,(string)$event['event_ruleset'],array_values(array_filter([$champion,$runner])));
            }
            $q=$pdo->prepare('SELECT team_id FROM sx_ygo_decks WHERE id=?');$q->execute([$champion]);$championTeam=$q->fetchColumn();if(!$championTeam)throw new InvalidArgumentException('El campeon requiere una identidad equipo/deck confirmada.');
            $runnerTeam=null;if($runner){$q->execute([$runner]);$runnerTeam=$q->fetchColumn();if(!$runnerTeam)throw new InvalidArgumentException('Subcampeon no identificado.');}
            $pdo->prepare('INSERT INTO sx_ygo_honors(season_id,competition_id,champion_deck_id,champion_team_id,runner_up_deck_id,runner_up_team_id,title,notes) VALUES(?,?,?,?,?,?,?,?)')->execute([$seasonId,$eventId?:null,$champion,(int)$championTeam,$runner?:null,$runnerTeam?:null,trim((string)($_POST['honor_title']??'Campeon')),$notes]);
            $pdo->prepare('INSERT INTO sx_ygo_audit_log(entity_type,entity_id,action,payload) VALUES(?,?,?,?)')->execute(['competition',$eventId,'manual_honor_confirmed',json_encode(['champion_deck_id'=>$champion,'champion_team_id'=>(int)$championTeam,'evidence'=>$notes],JSON_UNESCAPED_UNICODE)]);
            if($owns)$pdo->commit();return 'palmares';
        }catch(Throwable $e){if($owns&&$pdo->inTransaction())$pdo->rollBack();throw $e;}
    }
    if ($action === 'save_checkin') {
        $competitionId = max(0, (int) ($_POST['checkin_competition_id'] ?? 0));
        $matchId = max(0, (int) ($_POST['checkin_match_id'] ?? 0));
        $entityType = strtolower(trim((string) ($_POST['checkin_entity_type'] ?? 'guest')));
        $entityType = in_array($entityType, ['deck', 'team', 'player', 'judge', 'guest'], true) ? $entityType : 'guest';
        $entityId = max(0, (int) ($_POST['checkin_entity_id'] ?? 0));
        $displayName = mb_substr(trim((string) ($_POST['checkin_display_name'] ?? '')), 0, 160);
        $checkinCode = mb_substr(trim((string) ($_POST['checkin_code'] ?? '')), 0, 80);
        if ($displayName !== '') {
            $pdo->prepare('INSERT INTO sx_ygo_checkins (competition_id, match_id, entity_type, entity_id, display_name, checkin_code, source) VALUES (?, ?, ?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE checked_in_at=CURRENT_TIMESTAMP, checkin_code=VALUES(checkin_code), source=VALUES(source)')
                ->execute([$competitionId ?: null, $matchId ?: null, $entityType, $entityId ?: null, $displayName, $checkinCode ?: null, $checkinCode !== '' ? 'qr' : 'manual']);
            $pdo->prepare('INSERT INTO sx_ygo_audit_log (entity_type, entity_id, action, payload) VALUES (?, ?, ?, ?)')
                ->execute(['checkin', $matchId ?: $competitionId, 'participant_checkin', json_encode(['competition_id' => $competitionId, 'match_id' => $matchId, 'entity_type' => $entityType, 'entity_id' => $entityId, 'display_name' => $displayName], JSON_UNESCAPED_UNICODE)]);
        }
        return 'estadisticas';
    }
    if ($action === 'save_match_result') {
        $matchId = (int) ($_POST['match_id'] ?? 0);
        if(ygo_team_kind(ygo_competition_for_match($pdo,$matchId))!=='')throw new InvalidArgumentException('Usa Resultados por equipo para registrar cada puesto o la serie Tag compartida.');
        $offlineRequestId = preg_replace('/[^a-zA-Z0-9_-]/', '', (string) ($_POST['offline_request_id'] ?? '')) ?: '';
        if ($offlineRequestId !== '') {
            $syncedRequest = $pdo->prepare('SELECT result_code FROM sx_ygo_sync_requests WHERE request_id=? LIMIT 1');
            $syncedRequest->execute([$offlineRequestId]);
            $syncedResult = $syncedRequest->fetchColumn();
            if ($syncedResult !== false) return (string) $syncedResult;
        }
        $values = [];
        for ($i = 1; $i <= 5; $i++) {
            $value = (string) ($_POST["d$i"] ?? '');
            $values[] = in_array($value, ['A', 'B', 'E'], true) ? $value : '';
        }
        if ($matchId > 0) {
            $diceRoll = trim((string) ($_POST['result_dice_roll'] ?? ''));
            $turnChoice = trim((string) ($_POST['result_turn_choice'] ?? ''));
            $duelDetails = trim((string) ($_POST['result_duel_details'] ?? ''));
            $dicePayload = [];
            if ($diceRoll !== '') {
                $decodedDice = json_decode($diceRoll, true);
                $dicePayload = is_array($decodedDice) ? $decodedDice : [];
            }
            $diceWinner = strtoupper((string) ($dicePayload['winner'] ?? ''));
            $turnChosenBy = strtoupper((string) ($dicePayload['chosen_by'] ?? $diceWinner));
            $turnDecision = strtolower((string) ($dicePayload['choice'] ?? ''));
            $startsPlayer = strtoupper((string) ($dicePayload['starts'] ?? ''));
            $diceWinner = in_array($diceWinner, ['A', 'B'], true) ? $diceWinner : null;
            $turnChosenBy = in_array($turnChosenBy, ['A', 'B'], true) ? $turnChosenBy : null;
            $turnDecision = in_array($turnDecision, ['primero', 'segundo', 'first', 'second'], true) ? ($turnDecision === 'first' ? 'primero' : ($turnDecision === 'second' ? 'segundo' : $turnDecision)) : null;
            $startsPlayer = in_array($startsPlayer, ['A', 'B'], true) ? $startsPlayer : null;
            if ($duelDetails !== '') {
                $decodedDetails = json_decode($duelDetails, true);
                if (is_array($decodedDetails)) {
                    foreach ($decodedDetails as $detailIndex => $detailRow) {
                        if (!is_array($detailRow)) {
                            unset($decodedDetails[$detailIndex]);
                            continue;
                        }
                        $detailChosenBy = strtoupper((string) ($detailRow['turn_chosen_by'] ?? ''));
                        $detailDecision = strtolower((string) ($detailRow['turn_decision'] ?? ''));
                        $detailStarts = strtoupper((string) ($detailRow['starts_player'] ?? ''));
                        $decodedDetails[$detailIndex]['turn_chosen_by'] = in_array($detailChosenBy, ['A', 'B'], true) ? $detailChosenBy : '';
                        $decodedDetails[$detailIndex]['turn_decision'] = in_array($detailDecision, ['primero', 'segundo', 'first', 'second'], true) ? ($detailDecision === 'first' ? 'primero' : ($detailDecision === 'second' ? 'segundo' : $detailDecision)) : '';
                        $decodedDetails[$detailIndex]['starts_player'] = in_array($detailStarts, ['A', 'B'], true) ? $detailStarts : '';
                        $decodedDetails[$detailIndex]['dice_a'] = max(0, min(6, (int) ($detailRow['dice_a'] ?? 0)));
                        $decodedDetails[$detailIndex]['dice_b'] = max(0, min(6, (int) ($detailRow['dice_b'] ?? 0)));
                        $detailDiceWinner = strtoupper((string) ($detailRow['dice_winner'] ?? $detailRow['turn_chosen_by'] ?? ''));
                        $decodedDetails[$detailIndex]['dice_winner'] = in_array($detailDiceWinner, ['A', 'B'], true) ? $detailDiceWinner : '';
                        $turnReason = strtolower(trim((string) ($detailRow['turn_reason'] ?? '')));
                        $decodedDetails[$detailIndex]['turn_reason'] = in_array($turnReason, ['dice', 'previous_duel_loss', 'draw_reroll', 'draw_reroll_unrecorded'], true) ? $turnReason : '';
                        $decodedDetails[$detailIndex]['notes'] = mb_substr(trim((string) ($detailRow['notes'] ?? '')), 0, 500);
                        $method = strtolower(trim((string) ($detailRow['defeat_method'] ?? '')));
                        $decodedDetails[$detailIndex]['defeat_method'] = in_array($method, ['life_points', 'surrender', 'time_limit', 'deck_out', 'effect_win'], true) ? $method : '';
                        $surrenderedBy = strtoupper(trim((string) ($detailRow['surrendered_by'] ?? '')));
                        $decodedDetails[$detailIndex]['surrendered_by'] = in_array($surrenderedBy, ['A', 'B'], true) ? $surrenderedBy : '';
                        $decodedDetails[$detailIndex]['surrendered_at'] = mb_substr(trim((string) ($detailRow['surrendered_at'] ?? '')), 0, 40);
                        $decodedDetails[$detailIndex]['turn_count'] = max(0, min(999, (int) ($detailRow['turn_count'] ?? 0)));
                        $decodedDetails[$detailIndex]['brick_a'] = !empty($detailRow['brick_a']);
                        $decodedDetails[$detailIndex]['brick_b'] = !empty($detailRow['brick_b']);
                        $decodedDetails[$detailIndex]['duration_seconds'] = max(0, min(7200, (int) ($detailRow['duration_seconds'] ?? 0)));
                        $decodedDetails[$detailIndex]['timer_running'] = !empty($detailRow['timer_running']);
                        $decodedDetails[$detailIndex]['timer_started_at'] = $decodedDetails[$detailIndex]['timer_running'] ? max(0, (int) ($detailRow['timer_started_at'] ?? 0)) : null;
                        $decodedDetails[$detailIndex]['timer_updated_at'] = max(0, (int) ($detailRow['timer_updated_at'] ?? 0));
                        $decodedDetails[$detailIndex]['duel_started'] = !empty($detailRow['duel_started']) || in_array((string) ($detailRow['winner'] ?? ''), ['A', 'B', 'E'], true);
                        $decodedDetails[$detailIndex]['duel_started_at'] = $decodedDetails[$detailIndex]['duel_started'] ? max(0, (int) ($detailRow['duel_started_at'] ?? 0)) : null;
                        $dataOrigin = strtolower(trim((string) ($detailRow['data_origin'] ?? '')));
                        $decodedDetails[$detailIndex]['data_origin'] = in_array($dataOrigin, ['captured', 'added', 'inferred'], true)
                            ? $dataOrigin
                            : ($decodedDetails[$detailIndex]['duel_started_at'] ? 'captured' : 'added');
                        $timerMode = strtolower(trim((string) ($detailRow['timer_mode'] ?? '')));
                        $decodedDetails[$detailIndex]['timer_mode'] = in_array($timerMode, ['none', 'countdown'], true) ? $timerMode : 'none';
                        $turnHistory = is_array($detailRow['turn_history'] ?? null) ? array_slice($detailRow['turn_history'], 0, 999) : [];
                        $decodedDetails[$detailIndex]['turn_history'] = array_values(array_map(static function (array $turn): array {
                            $side = strtoupper((string) ($turn['side'] ?? ''));
                            return [
                                'turn' => max(1, min(999, (int) ($turn['turn'] ?? 1))),
                                'side' => in_array($side, ['A', 'B'], true) ? $side : '',
                                'ended_at' => mb_substr(trim((string) ($turn['ended_at'] ?? '')), 0, 40),
                                'elapsed_seconds' => max(0, min(7200, (int) ($turn['elapsed_seconds'] ?? 0))),
                            ];
                        }, array_filter($turnHistory, 'is_array')));
                        $decodedDetails[$detailIndex]['lp_start_a'] = max(1, min(99999, (int) ($detailRow['lp_start_a'] ?? 8000)));
                        $decodedDetails[$detailIndex]['lp_start_b'] = max(1, min(99999, (int) ($detailRow['lp_start_b'] ?? 8000)));
                        $decodedDetails[$detailIndex]['deck_a_id'] = max(0, (int) ($detailRow['deck_a_id'] ?? 0));
                        $decodedDetails[$detailIndex]['deck_b_id'] = max(0, (int) ($detailRow['deck_b_id'] ?? 0));
                        $decodedDetails[$detailIndex]['player_a_id'] = max(0, (int) ($detailRow['player_a_id'] ?? 0));
                        $decodedDetails[$detailIndex]['player_b_id'] = max(0, (int) ($detailRow['player_b_id'] ?? 0));
                        $historyRows = is_array($detailRow['lp_history'] ?? null) ? array_slice($detailRow['lp_history'], 0, 100) : [];
                        $decodedDetails[$detailIndex]['lp_history'] = array_values(array_map(static function (array $history): array {
                            $side = strtolower((string) ($history['side'] ?? ''));
                            $operation = strtolower((string) ($history['operation'] ?? ''));
                            return [
                                'side' => in_array($side, ['a', 'b'], true) ? $side : '',
                                'operation' => in_array($operation, ['subtract', 'add', 'half', 'reset'], true) ? $operation : '',
                                'amount' => max(0, min(99999, (int) ($history['amount'] ?? 0))),
                                'before' => max(0, min(99999, (int) ($history['before'] ?? 0))),
                                'after' => max(0, min(99999, (int) ($history['after'] ?? 0))),
                            ];
                        }, array_filter($historyRows, 'is_array')));
                    }
                    if ($diceWinner && $turnChosenBy && $turnDecision && $startsPlayer) {
                        $foundD1 = false;
                        foreach ($decodedDetails as &$detailRow) {
                            if ((int) ($detailRow['duel'] ?? 0) === 1) {
                                $detailRow['turn_chosen_by'] = $detailRow['turn_chosen_by'] ?: $turnChosenBy;
                                $detailRow['turn_decision'] = $detailRow['turn_decision'] ?: $turnDecision;
                                $detailRow['starts_player'] = $detailRow['starts_player'] ?: $startsPlayer;
                                $detailRow['turn_reason'] = $detailRow['turn_reason'] ?? 'dice';
                                $foundD1 = true;
                                break;
                            }
                        }
                        unset($detailRow);
                        if (!$foundD1) {
                            array_unshift($decodedDetails, [
                                'duel' => 1,
                                'winner' => $values[0] ?: '',
                                'defeat_method' => '',
                                'lp_a' => '',
                                'lp_b' => '',
                                'turn_chosen_by' => $turnChosenBy,
                                'turn_decision' => $turnDecision,
                                'starts_player' => $startsPlayer,
                                'turn_reason' => 'dice',
                            ]);
                        }
                    }
                    $duelDetails = json_encode(array_values($decodedDetails), JSON_UNESCAPED_UNICODE);
                }
            }
            $manualStarts=(string)($decodedDetails[0]['starts_player']??'');$verifiedManualStart=ygo_match_editor_verified_start($pdo,$matchId,1,$manualStarts);
            if($verifiedManualStart)$startsPlayer=$manualStarts;
            if (array_filter($values) && (!$diceWinner || !$turnChosenBy || !$turnDecision || !$startsPlayer) && !$verifiedManualStart) {
                return 'resultado_bloqueado_inicio';
            }
            if (array_filter($values)) {
                $detailsByDuel = [];
                foreach (($decodedDetails ?? []) as $detailRow) {
                    $detailsByDuel[(int) ($detailRow['duel'] ?? 0)] = $detailRow;
                }
                foreach ($values as $valueIndex => $value) {
                    if ($value === '') {
                        continue;
                    }
                    $detailRow = $detailsByDuel[$valueIndex + 1] ?? [];
                    if (!in_array((string) ($detailRow['starts_player'] ?? ''), ['A', 'B'], true)) {
                        return 'resultado_bloqueado_inicio';
                    }
                    if (empty($detailRow['duel_started'])) {
                        return 'resultado_bloqueado_inicio';
                    }
                    if (($detailRow['defeat_method'] ?? '') === 'surrender') {
                        if (!in_array($value, ['A', 'B'], true)) {
                            return 'resultado_bloqueado_rendicion';
                        }
                        $surrenderingSide = $value === 'A' ? 'B' : 'A';
                        $startsSide = (string) ($detailRow['starts_player'] ?? '');
                        $completedTurns = max((int) ($detailRow['turn_count'] ?? 0), count($detailRow['turn_history'] ?? []));
                        $activeSide = $completedTurns % 2 === 0 ? $startsSide : ($startsSide === 'A' ? 'B' : 'A');
                        $recordedSurrender = (string) ($detailRow['surrendered_by'] ?? '');
                        if ($activeSide !== $surrenderingSide || ($recordedSurrender !== '' && $recordedSurrender !== $surrenderingSide)) {
                            return 'resultado_bloqueado_rendicion';
                        }
                        $decodedDetails[$valueIndex]['surrendered_by'] = $surrenderingSide;
                        $decodedDetails[$valueIndex]['surrendered_at'] = (string) ($detailRow['surrendered_at'] ?? '') ?: date(DATE_ATOM);
                    }
                    if ($valueIndex > 0 && $values[$valueIndex - 1] === 'E') {
                        $hasRecordedDrawReroll = ($detailRow['turn_reason'] ?? '') === 'draw_reroll'
                            && in_array((int) ($detailRow['dice_a'] ?? 0), range(1, 6), true)
                            && in_array((int) ($detailRow['dice_b'] ?? 0), range(1, 6), true)
                            && in_array((string) ($detailRow['dice_winner'] ?? ''), ['A', 'B'], true);
                        $isHistoricalUnrecordedReroll = ($detailRow['turn_reason'] ?? '') === 'draw_reroll_unrecorded'
                            && !empty($detailRow['duel_started'])
                            && !empty($detailRow['turn_history'])
                            && in_array((string) ($detailRow['data_origin'] ?? ''), ['added', 'inferred'], true);
                        if (!$hasRecordedDrawReroll && !$isHistoricalUnrecordedReroll && !ygo_match_editor_verified_start($pdo,$matchId,$valueIndex+1,(string)($detailRow['starts_player']??''))) {
                            return 'resultado_bloqueado_inicio';
                        }
                    }
                }
            }
            $dateGuard = ygo_validate_match_result_date($pdo, $matchId);
            if (!$dateGuard['ok']) {
                $pdo->prepare('INSERT INTO sx_ygo_audit_log (entity_type, entity_id, action, payload) VALUES (?, ?, ?, ?)')
                    ->execute(['match', $matchId, 'blocked_result_by_date', json_encode($dateGuard, JSON_UNESCAPED_UNICODE)]);
                return 'resultado_bloqueado_fecha';
            }
            $pdo->beginTransaction();
            ygo_admission_assert_result_editable($pdo,$matchId);
            ygo_workflow_assert_result_editable($pdo,$matchId);
            ygo_season_require_open($pdo, ygo_current_season_id($pdo), true, true);
            $pdo->prepare('INSERT IGNORE INTO sx_ygo_match_results (match_id) VALUES (?)')->execute([$matchId]);
            $existingStmt = $pdo->prepare('SELECT r.d1, r.d2, r.d3, r.d4, r.d5, r.duel_details, m.status AS match_status FROM sx_ygo_match_results r JOIN sx_ygo_matches m ON m.id=r.match_id WHERE r.match_id = ? LIMIT 1 FOR UPDATE');
            $existingStmt->execute([$matchId]);
            $existingRow = $existingStmt->fetch(PDO::FETCH_ASSOC) ?: [];
            $existingDetails = json_decode((string) ($existingRow['duel_details'] ?? ''), true);
            $correctionAt=max(array_merge([0],array_map(static fn($d)=>(int)($d['manual_correction_at_ms']??0),is_array($existingDetails)?$existingDetails:[])));
            if($correctionAt>0&&(int)($_POST['result_correction_version']??0)!==$correctionAt)throw new InvalidArgumentException('La partida fue modificada. Recarga antes de guardar para no sobrescribir la corrección.');
            $newTurnAuditRows = [];
            if (isset($decodedDetails) && is_array($decodedDetails)) {
                $existingByDuel = [];
                foreach (is_array($existingDetails) ? $existingDetails : [] as $existingDetail) {
                    if (is_array($existingDetail)) {
                        $existingByDuel[(int) ($existingDetail['duel'] ?? 0)] = $existingDetail;
                    }
                }
                $persistedTurnsStmt = $pdo->prepare('
                    SELECT duel_number, turn_number, side, ended_at, elapsed_seconds
                    FROM sx_ygo_turn_audit
                    WHERE match_id = ?
                    ORDER BY duel_number, turn_number
                ');
                $persistedTurnsStmt->execute([$matchId]);
                foreach ($persistedTurnsStmt->fetchAll(PDO::FETCH_ASSOC) as $turnRow) {
                    $duelNumber = (int) $turnRow['duel_number'];
                    if (!isset($existingByDuel[$duelNumber])) {
                        $existingByDuel[$duelNumber] = ['duel' => $duelNumber, 'turn_count' => 0, 'turn_history' => []];
                    }
                    $existingByDuel[$duelNumber]['turn_history'][] = [
                        'turn' => (int) $turnRow['turn_number'],
                        'side' => (string) $turnRow['side'],
                        'ended_at' => (string) ($turnRow['ended_at'] ?? ''),
                        'elapsed_seconds' => (int) $turnRow['elapsed_seconds'],
                    ];
                    $existingByDuel[$duelNumber]['turn_count'] = max(
                        (int) ($existingByDuel[$duelNumber]['turn_count'] ?? 0),
                        (int) $turnRow['turn_number']
                    );
                }
                foreach ($decodedDetails as &$incomingDetail) {
                    $duelNumber = (int) ($incomingDetail['duel'] ?? 0);
                    $storedDetail = $existingByDuel[$duelNumber] ?? [];
                    if(!empty($storedDetail['manual_correction_at_ms']))$incomingDetail['manual_correction_at_ms']=$storedDetail['manual_correction_at_ms'];
                    $mergedTurns = ygo_merge_monotonic_turns($storedDetail, $incomingDetail);
                    $incomingDetail = $mergedTurns['detail'];
                    foreach ($mergedTurns['appended'] as $turnRow) {
                        $newTurnAuditRows[] = ['duel' => $duelNumber, 'row' => $turnRow];
                    }
                    if (!empty($storedDetail['duel_started'])) {
                        $storedTimerMode = strtolower((string) ($storedDetail['timer_mode'] ?? ''));
                        $incomingDetail['duel_started'] = true;
                        $incomingDetail['duel_started_at'] = max(0, (int) ($storedDetail['duel_started_at'] ?? $incomingDetail['duel_started_at'] ?? 0));
                        if (in_array($storedTimerMode, ['none', 'countdown'], true)) {
                            $incomingDetail['timer_mode'] = $storedTimerMode;
                        }
                    }
                    if (($incomingDetail['timer_mode'] ?? 'none') !== 'countdown') { $incomingDetail['timer_running'] = false; $incomingDetail['timer_started_at'] = null; }
                }
                unset($incomingDetail);
                $duelDetails = json_encode(array_values($decodedDetails), JSON_UNESCAPED_UNICODE);
            }
            if (isset($decodedDetails) && is_array($decodedDetails)) {
                $turnAuditInsert = $pdo->prepare('
                    INSERT IGNORE INTO sx_ygo_turn_audit
                        (match_id, duel_number, turn_number, side, ended_at, elapsed_seconds)
                    VALUES (?, ?, ?, ?, ?, ?)
                ');
                foreach ($decodedDetails as $detailRow) {
                    $duelNumber = max(1, min(5, (int) ($detailRow['duel'] ?? 0)));
                    foreach (ygo_normalize_turn_history(is_array($detailRow['turn_history'] ?? null) ? $detailRow['turn_history'] : []) as $turnRow) {
                        $turnAuditInsert->execute([
                            $matchId,
                            $duelNumber,
                            (int) $turnRow['turn'],
                            (string) $turnRow['side'],
                            (string) $turnRow['ended_at'] !== '' ? (string) $turnRow['ended_at'] : null,
                            (int) $turnRow['elapsed_seconds'],
                        ]);
                    }
                }
            }
            $existingValues = [];
            for ($i = 1; $i <= 5; $i++) {
                $storedValue = (string) ($existingRow["d$i"] ?? '');
                $existingValues[] = in_array($storedValue, ['A', 'B', 'E'], true) ? $storedValue : '';
            }
            $firstWritable = 0;
            while ($firstWritable < 5 && $existingValues[$firstWritable] !== '') {
                $firstWritable++;
            }
            if ($duelDetails === '') {
                for ($index = 0; $index < 5; $index++) {
                    if ($index < $firstWritable) {
                        $values[$index] = $values[$index] ?: $existingValues[$index];
                        continue;
                    }
                    if ($index > $firstWritable) {
                        $values[$index] = $existingValues[$index] ?: '';
                    }
                }
            }
            $evaluation = ygo_apply_event_model_to_result($pdo, $matchId, ygo_evaluate_duel_with_rule($pdo, $matchId, $values), $values);
            $completionNow = ygo_completion_now();
            $decodedDetails = ygo_completion_games($decodedDetails ?? [], is_array($existingDetails) ? $existingDetails : [], $existingValues, $values, $completionNow, !in_array(strtoupper((string)($existingRow['match_status']??'')), ['FINALIZADA','FINALIZADO','COMPLETED','FINISHED'], true));
            $duelDetails = json_encode($decodedDetails, JSON_UNESCAPED_UNICODE);
            $pdo->prepare("
                INSERT INTO sx_ygo_match_results (match_id, d1, d2, d3, d4, d5, result, score, validation_status, notes, dice_roll, turn_choice, dice_winner, turn_chosen_by, turn_decision, starts_player, duel_details)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE
                    d1 = VALUES(d1), d2 = VALUES(d2), d3 = VALUES(d3), d4 = VALUES(d4), d5 = VALUES(d5),
                    result = VALUES(result), score = VALUES(score), validation_status = VALUES(validation_status), notes = VALUES(notes),
                    dice_roll = VALUES(dice_roll), turn_choice = VALUES(turn_choice), dice_winner = VALUES(dice_winner),
                    turn_chosen_by = VALUES(turn_chosen_by), turn_decision = VALUES(turn_decision), starts_player = VALUES(starts_player),
                    duel_details = VALUES(duel_details)
            ")->execute([
                $matchId,
                $values[0] ?: null,
                $values[1] ?: null,
                $values[2] ?: null,
                $values[3] ?: null,
                $values[4] ?: null,
                $evaluation['winner'],
                $evaluation['score'],
                $evaluation['validation'],
                trim((string) ($_POST['result_notes'] ?? '')),
                $diceRoll !== '' ? $diceRoll : null,
                $turnChoice !== '' ? $turnChoice : null,
                $diceWinner,
                $turnChosenBy,
                $turnDecision,
                $startsPlayer,
                $duelDetails !== '' ? $duelDetails : null,
            ]);
            if (isset($decodedDetails) && is_array($decodedDetails)) {
                $matchSnapshotStmt = $pdo->prepare('
                    SELECT m.competition_id, m.division_id, m.deck_a_id, m.deck_b_id,
                           c.event_ruleset, c.ruleset_version, dv.name AS division_name
                    FROM sx_ygo_matches m
                    LEFT JOIN sx_ygo_competitions c ON c.id = m.competition_id
                    LEFT JOIN sx_ygo_divisions dv ON dv.id = COALESCE(m.division_id, c.division_id)
                    WHERE m.id = ? LIMIT 1
                ');
                $matchSnapshotStmt->execute([$matchId]);
                $matchSnapshot = $matchSnapshotStmt->fetch(PDO::FETCH_ASSOC) ?: [];
                $deckSnapshotStmt = $pdo->prepare('
                    SELECT d.id, d.team_id, d.name, d.archetype, d.variations,
                           t.name AS team_name
                    FROM sx_ygo_decks d
                    LEFT JOIN sx_ygo_teams t ON t.id = d.team_id
                    WHERE d.id = ? LIMIT 1
                ');
                $decklistSnapshotStmt = $pdo->prepare('
                    SELECT card_name, role, quantity, section, notes
                    FROM sx_ygo_deck_cards WHERE deck_id = ? ORDER BY section, id
                ');
                $insertSnapshotStmt = $pdo->prepare('
                    INSERT IGNORE INTO sx_ygo_match_snapshots
                        (match_id, duel_number, side, competition_id, deck_id, team_id, player_id,
                         deck_name, archetype, variation, team_name, ruleset_code, ruleset_version,
                         division_name, decklist_json, decklist_hash, data_origin, snapshot_status)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ');
                foreach ($decodedDetails as $detailRow) {
                    $duelNumber = max(1, min(5, (int) ($detailRow['duel'] ?? 0)));
                    foreach (['A', 'B'] as $side) {
                        $suffix = strtolower($side);
                        $defaultDeckKey = $side === 'A' ? 'deck_a_id' : 'deck_b_id';
                        $deckId = max(0, (int) ($detailRow['deck_' . $suffix . '_id'] ?? $matchSnapshot[$defaultDeckKey] ?? 0));
                        if ($deckId <= 0) {
                            continue;
                        }
                        $deckSnapshotStmt->execute([$deckId]);
                        $deckSnapshot = $deckSnapshotStmt->fetch(PDO::FETCH_ASSOC) ?: [];
                        $decklistSnapshotStmt->execute([$deckId]);
                        $decklist = $decklistSnapshotStmt->fetchAll(PDO::FETCH_ASSOC);
                        $originValue = strtolower(trim((string) ($detailRow['data_origin'] ?? $detailRow['source'] ?? '')));
                        $hasTurnHistory = !empty($detailRow['turn_history']) && is_array($detailRow['turn_history']);
                        $origin = str_contains($originValue, 'infer') || str_contains($originValue, 'backfill')
                            ? 'inferred'
                            : (str_contains($originValue, 'add') ? 'added' : ($originValue === 'captured' || $hasTurnHistory ? 'captured' : 'unknown'));
                        $decklistJson = json_encode($decklist, JSON_UNESCAPED_UNICODE);
                        $insertSnapshotStmt->execute([
                            $matchId, $duelNumber, $side,
                            (int) ($matchSnapshot['competition_id'] ?? 0) ?: null,
                            $deckId,
                            (int) ($deckSnapshot['team_id'] ?? 0) ?: null,
                            max(0, (int) ($detailRow['player_' . $suffix . '_id'] ?? 0)) ?: null,
                            (string) ($deckSnapshot['name'] ?? ''),
                            (string) ($deckSnapshot['archetype'] ?? ''),
                            (string) ($deckSnapshot['variations'] ?? ''),
                            (string) ($deckSnapshot['team_name'] ?? ''),
                            (string) ($matchSnapshot['event_ruleset'] ?? ''),
                            (string) ($matchSnapshot['ruleset_version'] ?? ''),
                            (string) ($matchSnapshot['division_name'] ?? ''),
                            $decklistJson,
                            hash('sha256', $decklistJson ?: '[]'),
                            $origin,
                            $origin === 'captured' ? 'exact' : $origin,
                        ]);
                    }
                }
            }
            ygo_completion_set_status($pdo, $matchId, $evaluation['status'], $completionNow);
            $competitionStmt = $pdo->prepare('SELECT competition_id FROM sx_ygo_matches WHERE id = ? LIMIT 1');
            $competitionStmt->execute([$matchId]);
            $resultCompetitionId = (int) ($competitionStmt->fetchColumn() ?: 0);
            if ($resultCompetitionId > 0 && !in_array('ranking_impact:NONE', $evaluation['model_effects'] ?? [], true)) {
                ygo_persist_competition_standings($pdo, ygo_current_season_id($pdo), $resultCompetitionId, 'save_match_result');
            }
            $pdo->prepare('INSERT INTO sx_ygo_audit_log (entity_type, entity_id, action, payload) VALUES (?, ?, ?, ?)')
                ->execute(['match', $matchId, 'save_result', json_encode(['duels' => $values, 'evaluation' => $evaluation, 'date_guard' => $dateGuard, 'dice_roll' => $diceRoll, 'turn_choice' => $turnChoice, 'dice_winner' => $diceWinner, 'turn_chosen_by' => $turnChosenBy, 'turn_decision' => $turnDecision, 'starts_player' => $startsPlayer, 'duel_details' => $duelDetails], JSON_UNESCAPED_UNICODE)]);
            $turnAuditLog = $pdo->prepare('INSERT INTO sx_ygo_audit_log (entity_type, entity_id, action, payload) VALUES (?, ?, ?, ?)');
            foreach ($newTurnAuditRows as $auditRow) {
                $turnAuditLog->execute([
                    'match',
                    $matchId,
                    'turn_added',
                    json_encode([
                        'duel' => (int) $auditRow['duel'],
                        'turn' => (int) $auditRow['row']['turn'],
                        'side' => (string) $auditRow['row']['side'],
                        'ended_at' => (string) $auditRow['row']['ended_at'],
                        'elapsed_seconds' => (int) $auditRow['row']['elapsed_seconds'],
                    ], JSON_UNESCAPED_UNICODE),
                ]);
            }
            ygo_sync_meta_history($pdo);
            ygo_sync_elo_history($pdo);
            ygo_sync_global_ranking_history($pdo);
            ygo_sync_event_summaries($pdo, $resultCompetitionId ?: null);
            ygo_sync_medal_unlocks($pdo, $matchId);
            $savedLiveMatch = ygo_live_duel_match($pdo, $matchId);
            if ($savedLiveMatch) {
                $savedLiveState = ygo_live_duel_initial_state($savedLiveMatch);
                if (($evaluation['status'] ?? '') === 'FINALIZADA') {
                    $savedLiveState['status'] = 'completed';
                    $savedLiveState['active_side'] = '';
                }
                $pdo->prepare("
                    INSERT INTO sx_ygo_live_duel_state (match_id, duel_number, revision, state_json, updated_by)
                    VALUES (?, ?, 1, ?, 'judge')
                    ON DUPLICATE KEY UPDATE duel_number=VALUES(duel_number), revision=revision+1, state_json=VALUES(state_json), updated_by='judge'
                ")->execute([$matchId, $savedLiveState['duel_number'], json_encode($savedLiveState, JSON_UNESCAPED_UNICODE)]);
            }
            if ($offlineRequestId !== '') {
                $pdo->prepare('INSERT INTO sx_ygo_sync_requests (request_id,match_id,payload_hash,result_code) VALUES (?,?,?,?)')
                    ->execute([$offlineRequestId,$matchId,hash('sha256',json_encode($_POST,JSON_UNESCAPED_UNICODE) ?: ''),'resultado']);
            }
            $pdo->commit();
        }
        return 'resultado';
    }
    if ($action === 'save_deck_analysis_profile') {
        $deckId = max(0, (int) ($_POST['deck_analysis_deck_id'] ?? 0));
        $deckStmt = $pdo->prepare('SELECT * FROM sx_ygo_decks WHERE id=? AND active=1 LIMIT 1');
        $deckStmt->execute([$deckId]);
        $deck = $deckStmt->fetch(PDO::FETCH_ASSOC) ?: [];
        if (!$deck) throw new RuntimeException('El deck seleccionado ya no esta activo.');
        $profileListJson = static function (string $field): string {
            $raw = (string) ($_POST[$field] ?? '');
            $structured = json_decode($raw, true);
            $isStructured = is_array($structured) && array_is_list($structured) && count(array_filter($structured, 'is_string')) === count($structured);
            $rows = $isStructured ? $structured : (preg_split('/[\r\n,;]+/', $raw) ?: []);
            $limit = $isStructured ? 2400 : 240;
            $rows = array_values(array_unique(array_filter(array_map(static fn($value): string => mb_substr(trim((string) $value), 0, $limit), $rows))));
            return json_encode($rows, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '[]';
        };
        $score = static fn(string $field): int => max(0, min(5, (int) ($_POST[$field] ?? 0)));
        $confidence = strtoupper(trim((string) ($_POST['deck_analysis_confidence'] ?? 'MEDIA')));
        if (!in_array($confidence, ['BAJA', 'MEDIA', 'ALTA'], true)) $confidence = 'MEDIA';
        $sourceType = strtoupper(trim((string) ($_POST['deck_analysis_source_type'] ?? 'EDITORIAL')));
        if (!in_array($sourceType, ['EDITORIAL', 'ANALYTICAL'], true)) $sourceType = 'EDITORIAL';
        $values = [
            mb_substr(trim((string) ($_POST['deck_analysis_play_style'] ?? '')), 0, 120),
            mb_substr(trim((string) ($_POST['deck_analysis_pace'] ?? '')), 0, 30),
            mb_substr(trim((string) ($_POST['deck_analysis_complexity'] ?? '')), 0, 30),
            trim((string) ($_POST['deck_analysis_primary_game_plan'] ?? '')),
            trim((string) ($_POST['deck_analysis_win_condition'] ?? '')),
            trim((string) ($_POST['deck_analysis_resource_engine'] ?? '')),
            trim((string) ($_POST['deck_analysis_opening_plan'] ?? '')),
            trim((string) ($_POST['deck_analysis_mid_game_plan'] ?? '')),
            trim((string) ($_POST['deck_analysis_late_game_plan'] ?? '')),
            trim((string) ($_POST['deck_analysis_going_first_plan'] ?? '')),
            trim((string) ($_POST['deck_analysis_going_second_plan'] ?? '')),
            trim((string) ($_POST['deck_analysis_side_deck_strategy'] ?? '')),
            $profileListJson('deck_analysis_strengths'),
            $profileListJson('deck_analysis_weaknesses'),
            $profileListJson('deck_analysis_strategy_tags'),
            $score('deck_analysis_consistency_score'),
            $score('deck_analysis_ceiling_score'),
            $score('deck_analysis_resilience_score'),
            $score('deck_analysis_interaction_score'),
            $score('deck_analysis_recovery_score'),
            $score('deck_analysis_first_score'),
            $score('deck_analysis_second_score'),
            $confidence,
            $sourceType,
            trim((string) ($_POST['deck_analysis_notes'] ?? '')),
        ];
        $startedTransaction = !$pdo->inTransaction();
        if ($startedTransaction) $pdo->beginTransaction();
        try {
            $pdo->prepare("
                INSERT INTO sx_ygo_deck_analysis_profiles
                    (deck_id, play_style, pace, complexity, primary_game_plan, win_condition, resource_engine, opening_plan, mid_game_plan, late_game_plan, going_first_plan, going_second_plan, side_deck_strategy, strengths_json, weaknesses_json, strategy_tags_json, consistency_score, ceiling_score, resilience_score, interaction_score, recovery_score, first_score, second_score, confidence, source_type, analysis_notes, revision)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1)
                ON DUPLICATE KEY UPDATE play_style=VALUES(play_style), pace=VALUES(pace), complexity=VALUES(complexity), primary_game_plan=VALUES(primary_game_plan), win_condition=VALUES(win_condition), resource_engine=VALUES(resource_engine), opening_plan=VALUES(opening_plan), mid_game_plan=VALUES(mid_game_plan), late_game_plan=VALUES(late_game_plan), going_first_plan=VALUES(going_first_plan), going_second_plan=VALUES(going_second_plan), side_deck_strategy=VALUES(side_deck_strategy), strengths_json=VALUES(strengths_json), weaknesses_json=VALUES(weaknesses_json), strategy_tags_json=VALUES(strategy_tags_json), consistency_score=VALUES(consistency_score), ceiling_score=VALUES(ceiling_score), resilience_score=VALUES(resilience_score), interaction_score=VALUES(interaction_score), recovery_score=VALUES(recovery_score), first_score=VALUES(first_score), second_score=VALUES(second_score), confidence=VALUES(confidence), source_type=VALUES(source_type), analysis_notes=VALUES(analysis_notes), revision=revision+1
            ")->execute([$deckId, ...$values]);
            $savedStmt = $pdo->prepare('SELECT * FROM sx_ygo_deck_analysis_profiles WHERE deck_id=? LIMIT 1');
            $savedStmt->execute([$deckId]);
            $saved = $savedStmt->fetch(PDO::FETCH_ASSOC) ?: [];
            $revision = (int) ($saved['revision'] ?? 1);
            $pdo->prepare('INSERT INTO sx_ygo_deck_analysis_profile_history (deck_id, revision, snapshot_json, change_reason) VALUES (?, ?, ?, ?)')
                ->execute([$deckId, $revision, json_encode($saved, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), trim((string) ($_POST['deck_analysis_change_reason'] ?? ''))]);
            $pdo->prepare('INSERT INTO sx_ygo_audit_log (entity_type, entity_id, action, payload) VALUES (?, ?, ?, ?)')
                ->execute(['deck_analysis', $deckId, 'save_deck_analysis_profile', json_encode(['revision' => $revision, 'source_type' => $sourceType], JSON_UNESCAPED_UNICODE)]);
            if ($startedTransaction) $pdo->commit();
        } catch (Throwable $error) {
            if ($startedTransaction && $pdo->inTransaction()) $pdo->rollBack();
            throw $error;
        }
        return 'deck_analysis_' . $deckId;
    }
    if ($action === 'save_matchup_analysis') {
        $deckId = max(0, (int) ($_POST['matchup_deck_id'] ?? 0));
        $opponentId = max(0, (int) ($_POST['matchup_opponent_deck_id'] ?? 0));
        if ($deckId <= 0 || $opponentId <= 0 || $deckId === $opponentId) {
            throw new RuntimeException('Selecciona dos decks diferentes para guardar el analisis.');
        }
        $validDeck = $pdo->prepare('SELECT COUNT(*) FROM sx_ygo_decks WHERE id IN (?, ?) AND active=1');
        $validDeck->execute([$deckId, $opponentId]);
        if ((int) $validDeck->fetchColumn() !== 2) {
            throw new RuntimeException('Uno de los decks del matchup ya no esta activo.');
        }
        $score = max(-2, min(2, (int) ($_POST['strategic_score'] ?? 0)));
        $classificationByScore = [-2 => 'MUY_DESFAVORABLE', -1 => 'DESFAVORABLE', 0 => 'EQUILIBRADO', 1 => 'FAVORABLE', 2 => 'MUY_FAVORABLE'];
        $confidence = strtoupper(trim((string) ($_POST['matchup_confidence'] ?? 'MEDIA')));
        if (!in_array($confidence, ['BAJA', 'MEDIA', 'ALTA'], true)) $confidence = 'MEDIA';
        $sourceType = strtoupper(trim((string) ($_POST['matchup_source_type'] ?? 'EDITORIAL')));
        if (!in_array($sourceType, ['EDITORIAL', 'ANALYTICAL'], true)) $sourceType = 'EDITORIAL';
        $listJson = static function (string $field): string {
            $rows = preg_split('/[\r\n,;]+/', (string) ($_POST[$field] ?? '')) ?: [];
            $rows = array_values(array_unique(array_filter(array_map(static fn($value): string => mb_substr(trim((string) $value), 0, 240), $rows))));
            return json_encode($rows, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '[]';
        };
        $scope = [
            mb_substr(trim((string) ($_POST['matchup_deck_version'] ?? '')), 0, 180),
            mb_substr(trim((string) ($_POST['matchup_opponent_version'] ?? '')), 0, 180),
            mb_substr(trim((string) ($_POST['matchup_ruleset_version'] ?? '')), 0, 180),
        ];
        $values = [
            $score,
            $classificationByScore[$score],
            trim((string) ($_POST['matchup_reason'] ?? '')),
            $listJson('matchup_advantages'),
            $listJson('matchup_disadvantages'),
            $listJson('matchup_choke_points'),
            trim((string) ($_POST['matchup_going_first_plan'] ?? '')),
            trim((string) ($_POST['matchup_going_second_plan'] ?? '')),
            trim((string) ($_POST['matchup_side_plan'] ?? '')),
            $confidence,
            $sourceType,
            trim((string) ($_POST['matchup_review_notes'] ?? '')),
        ];
        $startedTransaction = !$pdo->inTransaction();
        if ($startedTransaction) $pdo->beginTransaction();
        try {
            $pdo->prepare("
                INSERT INTO sx_ygo_matchup_analyses
                    (deck_id, opponent_deck_id, deck_version, opponent_version, ruleset_version, strategic_score, classification, reason, key_advantages_json, key_disadvantages_json, choke_points_json, going_first_plan, going_second_plan, side_plan, confidence, source_type, review_notes, revision, active)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1, 1)
                ON DUPLICATE KEY UPDATE strategic_score=VALUES(strategic_score), classification=VALUES(classification), reason=VALUES(reason), key_advantages_json=VALUES(key_advantages_json), key_disadvantages_json=VALUES(key_disadvantages_json), choke_points_json=VALUES(choke_points_json), going_first_plan=VALUES(going_first_plan), going_second_plan=VALUES(going_second_plan), side_plan=VALUES(side_plan), confidence=VALUES(confidence), source_type=VALUES(source_type), review_notes=VALUES(review_notes), revision=revision+1, active=1
            ")->execute([$deckId, $opponentId, ...$scope, ...$values]);
            $analysisStmt = $pdo->prepare('SELECT * FROM sx_ygo_matchup_analyses WHERE deck_id=? AND opponent_deck_id=? AND deck_version=? AND opponent_version=? AND ruleset_version=? LIMIT 1');
            $analysisStmt->execute([$deckId, $opponentId, ...$scope]);
            $analysis = $analysisStmt->fetch(PDO::FETCH_ASSOC) ?: [];
            $analysisId = (int) ($analysis['id'] ?? 0);
            $revision = (int) ($analysis['revision'] ?? 1);
            $pdo->prepare('INSERT INTO sx_ygo_matchup_analysis_history (analysis_id, deck_id, opponent_deck_id, revision, snapshot_json, change_reason) VALUES (?, ?, ?, ?, ?, ?)')
                ->execute([$analysisId, $deckId, $opponentId, $revision, json_encode($analysis, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), trim((string) ($_POST['matchup_change_reason'] ?? ''))]);
            $pdo->prepare('INSERT INTO sx_ygo_audit_log (entity_type, entity_id, action, payload) VALUES (?, ?, ?, ?)')
                ->execute(['matchup_analysis', $analysisId, 'save_matchup_analysis', json_encode(['deck_id' => $deckId, 'opponent_deck_id' => $opponentId, 'revision' => $revision, 'source_type' => $sourceType], JSON_UNESCAPED_UNICODE)]);
            if ($startedTransaction) $pdo->commit();
        } catch (Throwable $error) {
            if ($startedTransaction && $pdo->inTransaction()) $pdo->rollBack();
            throw $error;
        }
        return 'matchup_deck_' . $deckId;
    }
    if ($action === 'save_deck') {
        $id = (int) ($_POST['deck_id'] ?? 0);
        $deckName = trim((string) $_POST['deck_name']);
        $archetype = trim((string) $_POST['archetype']);
        $existingTeamId = null;
        if ($id > 0) {
            $existingTeamStmt = $pdo->prepare('SELECT team_id FROM sx_ygo_decks WHERE id=? AND active=1 LIMIT 1');
            $existingTeamStmt->execute([$id]);
            $existingTeamId = $existingTeamStmt->fetchColumn();
            $existingTeamId = $existingTeamId === false || $existingTeamId === null ? null : (int) $existingTeamId;
        }
        $existingCoverUrl = '';
        if ($id > 0) {
            $existingCoverStmt = $pdo->prepare('SELECT cover_url FROM sx_ygo_decks WHERE id=? AND active=1 LIMIT 1');
            $existingCoverStmt->execute([$id]);
            $existingCoverUrl = (string) ($existingCoverStmt->fetchColumn() ?: '');
        }
        $coverUrl = trim((string) ($_POST['cover_url'] ?? '')) ?: $existingCoverUrl;
        $teamId = array_key_exists('team_id', $_POST) ? (($_POST['team_id'] ?? '') === '' ? 0 : (int) $_POST['team_id']) : (int) ($existingTeamId ?? 0);
        if ($id <= 0 && $teamId <= 0 && $deckName !== '' && array_key_exists('team_id', $_POST) && ($_POST['team_id'] ?? '') !== '') {
            $profile = ygo_team_profile_for_deck($deckName, $archetype);
            $teamId = ygo_find_or_create_team($pdo, $profile['name'], $profile);
        }
        $values = [
            ($_POST['division_id'] ?? '') === '' ? null : (int) $_POST['division_id'],
            $teamId > 0 ? $teamId : null,
            max(1, (int) ($_POST['seed_rank'] ?? 999)),
            $deckName,
            $archetype,
            trim((string) $_POST['style']),
            trim((string) $_POST['reason']),
            trim((string) $_POST['key_cards']),
            trim((string) $_POST['variations']),
            trim((string) $_POST['notes']),
            $coverUrl,
        ];
        if ($values[3] !== '') {
            if ($id > 0) {
                $pdo->prepare('UPDATE sx_ygo_decks SET division_id=?, team_id=?, seed_rank=?, name=?, archetype=?, style=?, reason=?, key_cards=?, variations=?, notes=?, cover_url=?, active=1 WHERE id=?')->execute([...$values, $id]);
            } else {
                $pdo->prepare('INSERT INTO sx_ygo_decks (division_id, team_id, seed_rank, name, archetype, style, reason, key_cards, variations, notes, cover_url, active) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1)')->execute($values);
                $id = (int) $pdo->lastInsertId();
            }
            $uploadedDeckLogo = ygo_store_deck_logo_upload($id);
            if ($uploadedDeckLogo !== '') {
                $pdo->prepare('UPDATE sx_ygo_decks SET cover_url=? WHERE id=?')->execute([$uploadedDeckLogo, $id]);
                ygo_record_logo_history($pdo, 'deck', $id, $uploadedDeckLogo);
            }
        }
        return 'deck_' . $id;
    }
    if ($action === 'remove_deck') {
        $id = (int) ($_POST['deck_id'] ?? 0);
        if ($id > 0) {
            $blocker = ygo_deck_delete_blocker($pdo, $id);
            if ($blocker) {
                $pdo->prepare('INSERT INTO sx_ygo_audit_log (entity_type, entity_id, action, payload) VALUES (?, ?, ?, ?)')
                    ->execute(['deck', $id, 'blocked_remove_deck_in_event', json_encode($blocker, JSON_UNESCAPED_UNICODE)]);
                return 'deck_bloqueado_evento';
            }
            $pdo->prepare('UPDATE sx_ygo_decks SET active=0 WHERE id=?')->execute([$id]);
        }
        return 'deck_quitado';
    }
    if ($action === 'save_competition') {
        $seasonId = ygo_current_season_id($pdo);
        $id = (int) ($_POST['competition_id'] ?? 0);
        $competitionName = trim((string) $_POST['competition_name']);
        $competitionType = trim((string) $_POST['competition_type']);
        $postedDivision = trim((string) ($_POST['competition_division_id'] ?? ''));
        $postedDivisionIds = $_POST['competition_division_ids'] ?? [];
        if (!is_array($postedDivisionIds)) {
            $postedDivisionIds = [];
        }
        $postedDivisionIds = array_values(array_unique(array_filter(array_map('intval', $postedDivisionIds), static fn(int $divisionId): bool => $divisionId > 0)));
        $competitionRuleset = trim((string) ($_POST['competition_event_ruleset'] ?? 'tcg_advanced'));
        if (str_starts_with($postedDivision, 'format:')) {
            $competitionRuleset = substr($postedDivision, 7) ?: $competitionRuleset;
        }
        $rulesetStmt = $pdo->prepare('SELECT * FROM sx_ygo_rulesets WHERE season_id = ? AND code = ? AND active = 1 LIMIT 1');
        $rulesetStmt->execute([$seasonId, $competitionRuleset]);
        $selectedRuleset = $rulesetStmt->fetch(PDO::FETCH_ASSOC) ?: [];
        $existingCompetition = [];
        if ($id > 0) {
            $existingStmt = $pdo->prepare("
                SELECT c.*, r.code AS game_rule_code
                FROM sx_ygo_competitions c
                LEFT JOIN sx_ygo_game_rules r ON r.id = c.game_rule_id
                WHERE c.id = ? AND c.season_id = ?
                LIMIT 1
            ");
            $existingStmt->execute([$id, $seasonId]);
            $existingCompetition = $existingStmt->fetch(PDO::FETCH_ASSOC) ?: [];
            if (!empty($existingCompetition['type'])) {
                $competitionType = (string) $existingCompetition['type'];
            }
        }
        if ($id>0 && !empty($existingCompetition['configuration_locked'])) throw new InvalidArgumentException('El evento esta bloqueado. Usa su ficha sin reescribir la definicion ni el calendario.');
        if($id>0)ygo_assert_fixture_mutable($pdo,$id);
        $competitionCode = $id > 0 ? trim((string) ($existingCompetition['code'] ?? '')) : '';
        if ($competitionCode === '' && $competitionName !== '') {
            $competitionCode = ygo_generate_competition_code($pdo, $seasonId, $competitionType, $competitionRuleset, $competitionName, $id);
        }
        $matchFormat = trim((string) ($_POST['competition_match_format'] ?? ($selectedRuleset['match_format'] ?? '1v1')));
        if (!in_array($matchFormat, ['1v1', '2v2', '3v3', 'ffa'], true)) {
            $matchFormat = '1v1';
        }
        $participantsPerSide = $matchFormat==='ffa'?1:(int) substr($matchFormat, 0, 1);
        $postedDivisionId = $postedDivision !== '' && ctype_digit($postedDivision) && (int) $postedDivision > 0 ? (int) $postedDivision : null;
        if ($postedDivisionId && !in_array($postedDivisionId, $postedDivisionIds, true)) {
            array_unshift($postedDivisionIds, $postedDivisionId);
        }
        if ($competitionType === 'league' && !$postedDivisionIds && $id > 0) {
            $linkedDivisionStmt = $pdo->prepare('SELECT id FROM sx_ygo_divisions WHERE season_id=? AND active=1 AND league_competition_id=? ORDER BY tier_order,id');
            $linkedDivisionStmt->execute([$seasonId, $id]);
            $postedDivisionIds = array_map('intval', $linkedDivisionStmt->fetchAll(PDO::FETCH_COLUMN));
        }
        if ($competitionType === 'league' && !$postedDivisionIds && !empty($existingCompetition['division_id'])) {
            $postedDivisionIds = [(int) $existingCompetition['division_id']];
        }
        if ($competitionType === 'league' && $postedDivisionIds) {
            $placeholders = implode(',', array_fill(0, count($postedDivisionIds), '?'));
            $divisionStmt = $pdo->prepare("SELECT id FROM sx_ygo_divisions WHERE season_id=? AND active=1 AND id IN ($placeholders) ORDER BY FIELD(id,$placeholders)");
            $divisionStmt->execute(array_merge([$seasonId], $postedDivisionIds, $postedDivisionIds));
            $postedDivisionIds = array_map('intval', $divisionStmt->fetchAll(PDO::FETCH_COLUMN));
            $postedDivisionId = $postedDivisionId && in_array($postedDivisionId, $postedDivisionIds, true) ? $postedDivisionId : ($postedDivisionIds[0] ?? null);
        }
        if ($competitionType === 'league' && !$postedDivisionId) {
            throw new RuntimeException('Toda liga debe tener una division activa asociada.');
        }
        $deckPoolMode = (string) ($_POST['competition_deck_pool'] ?? 'all_active');
        if (!in_array($deckPoolMode, ['all_active', 'division'], true)) {
            $deckPoolMode = 'all_active';
        }
        if ($deckPoolMode === 'division' && (!$postedDivisionId || $postedDivisionId <= 0)) {
            $deckPoolMode = 'all_active';
        }
        $hasLeagueMovements = $competitionType === 'league' && count($postedDivisionIds) > 1;
        $leaguePromotionSlots = $hasLeagueMovements ? max(0, (int) ($_POST['competition_promotion_slots'] ?? 0)) : 0;
        $leagueRelegationSlots = $hasLeagueMovements ? max(0, (int) ($_POST['competition_relegation_slots'] ?? 0)) : 0;
        $leaguePromotionTo = $hasLeagueMovements && ($_POST['competition_promotion_to'] ?? '') !== '' ? (int) $_POST['competition_promotion_to'] : null;
        $leagueRelegationTo = $hasLeagueMovements && ($_POST['competition_relegation_to'] ?? '') !== '' ? (int) $_POST['competition_relegation_to'] : null;
        if ($postedDivisionId && $leaguePromotionTo === $postedDivisionId) {
            $leaguePromotionTo = null;
        }
        if ($postedDivisionId && $leagueRelegationTo === $postedDivisionId) {
            $leagueRelegationTo = null;
        }
        $lockDivisionMoves = $hasLeagueMovements && isset($_POST['competition_lock_division_moves']) ? 1 : 0;
        $qualifierDuelsPerDeck = $competitionType === 'qualifier' ? max(2, min(5, (int) ($_POST['qualifier_duels_per_deck'] ?? 3))) : 3;
        $qualifierMatchesPerDay = $competitionType === 'qualifier' ? max(1, min(5, (int) ($_POST['qualifier_matches_per_day'] ?? 1))) : 1;
        $rulesetVersion = trim((string) ($_POST['competition_ruleset_version'] ?? ''));
        if($id===0 && $competitionRuleset!=='goat' && preg_match('/(?:abril|april)\s*2005|goat/i',$rulesetVersion)) throw new InvalidArgumentException('La version GOAT/Abril 2005 no corresponde al formato elegido. Selecciona su reglamento.');
        $promptTemplate = preg_replace('/[^a-zA-Z0-9_.-]/', '', trim((string) ($_POST['competition_prompt_template'] ?? '')));
        $eventSystem = trim((string) ($_POST['competition_event_system'] ?? ''));
        $executionModel = preg_replace('/[^a-zA-Z0-9_.-]/', '', trim((string) ($_POST['competition_execution_model'] ?? '')));
        $templateType = $promptTemplate !== '' ? ygo_event_template_type($promptTemplate) : '';
        if ($templateType !== '' && $templateType !== $competitionType) throw new InvalidArgumentException('El tipo de evento no corresponde a la plantilla elegida; no se borrara ni sustituira silenciosamente.');
        ygo_require_execution_type($competitionType, $executionModel);
        $participation = $matchFormat === 'ffa'?'FREE_FOR_ALL':($matchFormat === '3v3' ? 'TEAM_3V3' : ($matchFormat === '2v2' ? 'TAG_2V2' : 'INDIVIDUAL_1V1'));
        $construction = trim((string) ($_POST['competition_construction'] ?? ''));
        $environment = trim((string) ($_POST['competition_environment'] ?? ''));
        $level = trim((string) ($_POST['competition_level'] ?? ''));
        $eventFlow = preg_replace('/[^A-Z0-9_]/', '', strtoupper(trim((string) ($_POST['competition_event_flow'] ?? 'STANDALONE'))));
        $participantSource = preg_replace('/[^A-Z0-9_]/', '', strtoupper(trim((string) ($_POST['competition_participant_source'] ?? 'MANUAL'))));
        $accessType = preg_replace('/[^A-Z0-9_]/', '', strtoupper(trim((string) ($_POST['competition_access_type'] ?? 'OPEN'))));
        $advancementRule = preg_replace('/[^A-Z0-9_]/', '', strtoupper(trim((string) ($_POST['competition_advancement_rule'] ?? 'MANUAL_SELECTION'))));
        $drawPolicy = preg_replace('/[^A-Z0-9_]/', '', strtoupper(trim((string) ($_POST['competition_draw_policy'] ?? 'USE_TIEBREAKER'))));
        $rankingImpact = preg_replace('/[^A-Z0-9_]/', '', strtoupper(trim((string) ($_POST['competition_ranking_impact'] ?? 'FULL'))));
        $deckPolicy = preg_replace('/[^A-Z0-9_]/', '', strtoupper(trim((string) ($_POST['competition_deck_policy'] ?? 'OPTIONAL_REGISTRATION'))));
        $phaseStructure = preg_replace('/[^A-Z0-9_]/', '', strtoupper(trim((string) ($_POST['competition_phase_structure'] ?? 'NONE'))));
        $choiceErrors=ygo_event_choice_errors(['participation'=>$participation,'construction'=>$construction,'environment'=>$environment,'level'=>$level,'event_flow'=>$eventFlow,'participant_source'=>$participantSource,'access_type'=>$accessType,'advancement_rule'=>$advancementRule,'draw_policy'=>$drawPolicy,'ranking_impact'=>$rankingImpact,'deck_policy'=>$deckPolicy,'phase_structure'=>$phaseStructure]);
        if($choiceErrors)throw new InvalidArgumentException(implode(' ',$choiceErrors));
        if($promptTemplate!=='' && !in_array($promptTemplate,array_column(ygo_event_available_templates(),'code'),true))throw new InvalidArgumentException('Modelo preconfigurado desconocido.');
        if ($competitionType === 'friendly') {
            $rankingImpact = 'NONE';
            $level = $level ?: 'CASUAL';
        }
        $uploadedLogo = ygo_store_event_logo_upload();
        $galleryLogo = ygo_valid_event_gallery_logo((string) ($_POST['competition_gallery_logo'] ?? ''), $competitionType);
        $eventTypeAccent = (string) (($GLOBALS['competitionTypeOptions'][$competitionType]['accent'] ?? '') ?: '#36d8ff');
        $defaultLogos = ygo_event_logo_gallery()[$competitionType] ?? [];
        $defaultLogo = (string) ($defaultLogos[0]['url'] ?? '');
        $logoUrl = $uploadedLogo !== '' ? $uploadedLogo : ($galleryLogo !== '' ? $galleryLogo : ((string) ($existingCompetition['logo_url'] ?? '') ?: $defaultLogo));
        $postedCreateStatus = trim((string) ($_POST['competition_status'] ?? 'pending_configuration'));
        $competitionStatus = $postedCreateStatus === 'cancelled' && $id > 0 ? 'cancelled' : 'pending_configuration';
        $existingRuleCode = (string) ($existingCompetition['game_rule_code'] ?? '');
        $fallbackRuleId = (int) ($existingCompetition['game_rule_id'] ?? 0) ?: null;
        $gameRuleId = ygo_valid_game_rule_id($pdo, $seasonId, (int) ($_POST['competition_game_rule_id'] ?? 0), $fallbackRuleId, $competitionType, $executionModel);
        $bestOf=filter_var($_POST['competition_best_of'] ?? $selectedRuleset['best_of'] ?? 3,FILTER_VALIDATE_INT);
        if($bestOf===false || $bestOf<1 || $bestOf>5)throw new InvalidArgumentException('Mejor de debe ser un entero entre 1 y 5; no se permite D6.');
        if ($bestOf===1 && empty($_POST['competition_game_rule_id'])) $gameRuleId=null;
        if (in_array($promptTemplate,['team_league','team_cup','team_championship'],true) && !in_array($matchFormat,['2v2','3v3'],true)) {
            throw new InvalidArgumentException('Selecciona expresamente 2v2 o 3v3 para este modelo de equipos.');
        }
        $operationalParameters=[];
        foreach(['swiss_rounds','top_n','group_max_size','group_qualifiers','series_matches'] as $parameter){
            $posted=trim((string)($_POST['competition_parameter_'.$parameter]??''));if($posted==='')continue;
            $value=filter_var($posted,FILTER_VALIDATE_INT);if($value===false||$value<1||$value>64)throw new InvalidArgumentException('Parametro operativo invalido: '.$parameter);
            $operationalParameters[$parameter]=$value;
        }
        if(isset($operationalParameters['series_matches'])){if($operationalParameters['series_matches']%2!==1)throw new InvalidArgumentException('La serie debe tener un numero impar de matches.');$operationalParameters['series_wins']=intdiv($operationalParameters['series_matches'],2)+1;}
        foreach(array_keys($operationalParameters) as $key)if(!in_array($key,ygo_execution_parameter_fields($executionModel),true))throw new InvalidArgumentException('El parametro no se aplica al sistema elegido: '.$key);
        if($promptTemplate==='clasificatorio_divisiones_3_duelos'&&(($operationalParameters['swiss_rounds']??3)>3||$qualifierDuelsPerDeck>3))throw new InvalidArgumentException('Esta plantilla admite hasta tres rondas/enfrentamientos por deck; elige Suizo personalizado para otro limite.');
        if(isset($operationalParameters['group_max_size'])&&$operationalParameters['group_max_size']<2)throw new InvalidArgumentException('Un grupo competitivo requiere al menos dos participantes.');
        if($executionModel==='groups_knockout'&&($operationalParameters['group_qualifiers']??2)>($operationalParameters['group_max_size']??4))throw new InvalidArgumentException('Los cupos por grupo superan su capacidad.');
        $preset=null;foreach(ygo_event_available_templates() as $t)if($t['code']===$promptTemplate)$preset=$t;
        if($executionModel==='free_for_all'){$rankingImpact='NONE';$gameRuleId=null;if($matchFormat!=='ffa'||$bestOf!==1)throw new InvalidArgumentException('Battle Royale requiere participacion multijugador y un duelo.');}
        $contractErrors=ygo_event_contract_errors(['type'=>$competitionType,'prompt_template'=>$promptTemplate,'execution_model'=>$executionModel,'phase_structure'=>$phaseStructure,'advancement_rule'=>$advancementRule,'best_of'=>$bestOf,'participant_source'=>$participantSource,'match_format'=>$matchFormat,'event_ruleset'=>$competitionRuleset],$preset);
        if($contractErrors)throw new InvalidArgumentException(implode(' ',$contractErrors));
        $values = [
            $seasonId,
            $postedDivisionId,
            $competitionCode,
            $competitionName,
            $competitionType,
            trim((string) ($_POST['competition_scope'] ?? 'division')),
            trim((string) ($_POST['competition_format'] ?? 'editable')),
            $matchFormat,
            $bestOf,
            max(1, min(3, $participantsPerSide)),
            trim((string) ($selectedRuleset['schedule_strategy'] ?? ($_POST['competition_schedule_strategy'] ?? 'manual'))),
            $competitionRuleset,
            $rulesetVersion,
            $deckPoolMode,
            $leaguePromotionSlots,
            $leagueRelegationSlots,
            $leaguePromotionTo,
            $leagueRelegationTo,
            $lockDivisionMoves,
            trim((string) ($selectedRuleset['victory_condition'] ?? 'Resultado definido por reglamento')),
            trim((string) ($selectedRuleset['defeat_condition'] ?? 'Resultado definido por reglamento')),
            trim((string) ($selectedRuleset['draw_condition'] ?? 'Empate definido por reglamento')),
            $promptTemplate !== '' ? $promptTemplate : null,
            $eventSystem !== '' ? $eventSystem : null,
            $executionModel !== '' ? $executionModel : null,
            $participation !== '' ? $participation : null,
            $construction !== '' ? $construction : null,
            $environment !== '' ? $environment : null,
            $level !== '' ? $level : null,
            $eventFlow !== '' ? $eventFlow : 'STANDALONE',
            $participantSource !== '' ? $participantSource : 'MANUAL',
            $advancementRule !== '' ? $advancementRule : 'MANUAL_SELECTION',
            $drawPolicy !== '' ? $drawPolicy : 'USE_TIEBREAKER',
            $rankingImpact !== '' ? $rankingImpact : 'FULL',
            $deckPolicy !== '' ? $deckPolicy : 'OPTIONAL_REGISTRATION',
            $accessType !== '' ? $accessType : 'OPEN',
            $phaseStructure !== '' ? $phaseStructure : 'NONE',
            $qualifierDuelsPerDeck,
            $qualifierMatchesPerDay,
            $gameRuleId,
            $competitionStatus,
            ($_POST['starts_on'] ?? '') ?: null,
            ($_POST['ends_on'] ?? '') ?: null,
            $eventTypeAccent,
            $logoUrl,
            trim((string) $_POST['competition_rules']),
        ];
        if ($values[2] !== '' && $values[3] !== '') {
            $targetCompetitionId = $id;
            if ($id > 0) {
                $pdo->prepare('UPDATE sx_ygo_competitions SET division_id=?, code=?, name=?, type=?, scope=?, format=?, match_format=?, best_of=?, participants_per_side=?, schedule_strategy=?, event_ruleset=?, ruleset_version=?, deck_pool=?, promotion_slots=?, relegation_slots=?, promotion_to_division_id=?, relegation_to_division_id=?, lock_division_moves=?, victory_condition=?, defeat_condition=?, draw_condition=?, prompt_template=?, event_system=?, execution_model=?, participation=?, construction=?, environment=?, level=?, event_flow=?, participant_source=?, advancement_rule=?, draw_policy=?, ranking_impact=?, deck_policy=?, access_type=?, phase_structure=?, qualifier_duels_per_deck=?, qualifier_matches_per_day=?, game_rule_id=?, status=?, starts_on=?, ends_on=?, accent=?, logo_url=?, rules=?, active=1 WHERE id=?')->execute(array_merge(array_slice($values, 1), [$id]));
                ygo_record_competition_status_history($pdo, $seasonId, $id, (string) ($existingCompetition['status'] ?? ''), $competitionStatus, 'save_competition', ['source' => 'create_event_screen']);
            } else {
                $pdo->prepare('INSERT INTO sx_ygo_competitions (season_id, division_id, code, name, type, scope, format, match_format, best_of, participants_per_side, schedule_strategy, event_ruleset, ruleset_version, deck_pool, promotion_slots, relegation_slots, promotion_to_division_id, relegation_to_division_id, lock_division_moves, victory_condition, defeat_condition, draw_condition, prompt_template, event_system, execution_model, participation, construction, environment, level, event_flow, participant_source, advancement_rule, draw_policy, ranking_impact, deck_policy, access_type, phase_structure, qualifier_duels_per_deck, qualifier_matches_per_day, game_rule_id, status, starts_on, ends_on, accent, logo_url, rules, active) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1)')->execute($values);
                $targetCompetitionId = (int) $pdo->lastInsertId();
                ygo_record_competition_status_history($pdo, $seasonId, $targetCompetitionId, null, $competitionStatus, 'save_competition', ['source' => 'create_event_screen']);
            }
            if ($targetCompetitionId > 0) {
                if ($competitionType === 'league' && $postedDivisionIds) {
                    $pdo->prepare('UPDATE sx_ygo_divisions SET league_competition_id=NULL WHERE season_id=? AND league_competition_id=?')
                        ->execute([$seasonId, $targetCompetitionId]);
                    $placeholders = implode(',', array_fill(0, count($postedDivisionIds), '?'));
                    $pdo->prepare("UPDATE sx_ygo_divisions SET league_competition_id=? WHERE season_id=? AND active=1 AND id IN ($placeholders)")
                        ->execute(array_merge([$targetCompetitionId, $seasonId], $postedDivisionIds));
                }
                $sameRecipe=($existingCompetition['prompt_template']??null)===$promptTemplate && ($existingCompetition['execution_model']??null)===$executionModel;
                $pdo->prepare('UPDATE sx_ygo_competitions SET execution_parameters=? WHERE id=?')->execute([$operationalParameters?json_encode($operationalParameters):($sameRecipe?($existingCompetition['execution_parameters']??null):null),$targetCompetitionId]);
                ygo_record_logo_history($pdo, 'competition', $targetCompetitionId, $logoUrl, $uploadedLogo !== '' ? 'upload' : ($galleryLogo !== '' ? 'gallery' : 'default'));
                ygo_persist_competition_calendar_event($pdo, $seasonId, $targetCompetitionId);
            }
            $conditionQualifierId = (int) ($_POST['condition_qualifier_id'] ?? 0);
            if ($targetCompetitionId > 0 && in_array($competitionType, ['league', 'tournament', 'world'], true)) {
                $pdo->prepare('UPDATE sx_ygo_competition_dependencies SET active=0 WHERE competition_id=?')->execute([$targetCompetitionId]);
                if ($conditionQualifierId > 0) {
                    $qualifierStmt = $pdo->prepare("
                        SELECT c.id
                        FROM sx_ygo_competitions c
                        WHERE c.id=? AND c.season_id=? AND c.type='qualifier' AND c.active=1
                          AND (
                            LOWER(REPLACE(c.status, ' ', '_')) IN ('pending_event', 'pendiente_evento')
                            OR (
                              LOWER(REPLACE(c.status, ' ', '_')) = 'pending_configuration'
                              AND (SELECT COUNT(*) FROM sx_ygo_competition_decks cd WHERE cd.competition_id = c.id AND cd.active = 1) >= 4
                            )
                          )
                        LIMIT 1
                    ");
                    $qualifierStmt->execute([$conditionQualifierId, $seasonId]);
                    $validQualifierId = (int) ($qualifierStmt->fetchColumn() ?: 0);
                    if ($validQualifierId > 0) {
                        $pdo->prepare('
                            INSERT INTO sx_ygo_competition_dependencies (season_id, competition_id, depends_on_competition_id, condition_type, take_count, active)
                            VALUES (?, ?, ?, "completed_with_results", 0, 1)
                            ON DUPLICATE KEY UPDATE condition_type=VALUES(condition_type), take_count=VALUES(take_count), active=1
                        ')->execute([$seasonId, $targetCompetitionId, $validQualifierId]);
                    }
                }
            }
        }
        return $targetCompetitionId > 0 ? 'evento_configurado_' . $targetCompetitionId : 'evento';
    }
    if ($action === 'save_ruleset') {
        $seasonId = ygo_current_season_id($pdo);
        $code = trim((string) ($_POST['ruleset_code'] ?? ''));
        $label = trim((string) ($_POST['ruleset_label'] ?? ''));
        if ($code === '' && $label !== '') {
            $code = ygo_slugify($label);
        }
        $values = [
            $seasonId,
            $code,
            $label,
            trim((string) ($_POST['ruleset_family'] ?? 'custom')),
            trim((string) ($_POST['ruleset_banlist_name'] ?? '')),
            trim((string) ($_POST['ruleset_banlist_version'] ?? '')),
            ($_POST['ruleset_banlist_effective_on'] ?? '') ?: null,
            trim((string) ($_POST['ruleset_match_format'] ?? '1v1')),
            max(1, min(10, (int) ($_POST['ruleset_best_of'] ?? 3))),
            max(1, min(3, (int) ($_POST['ruleset_participants_per_side'] ?? 1))),
            trim((string) ($_POST['ruleset_schedule_strategy'] ?? 'manual')),
            trim((string) ($_POST['ruleset_deck_pool'] ?? 'all_active')),
            trim((string) ($_POST['ruleset_victory_condition'] ?? '')),
            trim((string) ($_POST['ruleset_defeat_condition'] ?? '')),
            trim((string) ($_POST['ruleset_draw_condition'] ?? '')),
            trim((string) ($_POST['ruleset_notes'] ?? '')),
        ];
        if ($values[1] !== '' && $values[2] !== '') {
            $pdo->prepare("
                INSERT INTO sx_ygo_rulesets
                    (season_id, code, label, format_family, banlist_name, banlist_version, banlist_effective_on, match_format, best_of, participants_per_side, schedule_strategy, deck_pool, victory_condition, defeat_condition, draw_condition, notes, active)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1)
                ON DUPLICATE KEY UPDATE label=VALUES(label), format_family=VALUES(format_family), banlist_name=VALUES(banlist_name), banlist_version=VALUES(banlist_version), banlist_effective_on=VALUES(banlist_effective_on), match_format=VALUES(match_format), best_of=VALUES(best_of), participants_per_side=VALUES(participants_per_side), schedule_strategy=VALUES(schedule_strategy), deck_pool=VALUES(deck_pool), victory_condition=VALUES(victory_condition), defeat_condition=VALUES(defeat_condition), draw_condition=VALUES(draw_condition), notes=VALUES(notes), active=1
            ")->execute($values);
            $pdo->prepare('INSERT INTO sx_ygo_audit_log (entity_type, entity_id, action, payload) VALUES (?, ?, ?, ?)')
                ->execute(['ruleset', null, 'save_ruleset', json_encode(['code' => $code, 'label' => $label], JSON_UNESCAPED_UNICODE)]);
        }
        return 'reglamento';
    }
    if ($action === 'save_game_rule') {
        $seasonId = ygo_current_season_id($pdo);
        $ruleId = (int) ($_POST['game_rule_id'] ?? 0);
        $ruleName = trim((string) ($_POST['game_rule_name'] ?? ''));
        $ruleCode = trim((string) ($_POST['game_rule_code'] ?? ''));
        if ($ruleCode === '' && $ruleName !== '') {
            $ruleCode = strtoupper(str_replace('-', '_', ygo_slugify($ruleName)));
        }
        $values = [
            $seasonId,
            $ruleCode,
            $ruleName,
            trim((string) ($_POST['game_rule_type'] ?? 'custom')),
            trim((string) ($_POST['game_rule_scope'] ?? 'general')),
            trim((string) ($_POST['game_rule_trigger'] ?? '')),
            trim((string) ($_POST['game_rule_effect'] ?? '')),
            (int) ($_POST['game_rule_points'] ?? 0),
            max(1, (int) ($_POST['game_rule_priority'] ?? 100)),
            isset($_POST['game_rule_active']) ? 1 : 0,
            trim((string) ($_POST['game_rule_notes'] ?? '')),
        ];
        if ($ruleCode !== '' && $ruleName !== '') {
            if ($ruleId > 0) {
                $pdo->prepare('UPDATE sx_ygo_game_rules SET code=?, name=?, rule_type=?, scope=?, trigger_event=?, effect=?, points_value=?, priority_order=?, active=?, notes=? WHERE id=? AND season_id=?')
                    ->execute(array_merge(array_slice($values, 1), [$ruleId, $seasonId]));
            } else {
                $pdo->prepare('
                    INSERT INTO sx_ygo_game_rules
                        (season_id, code, name, rule_type, scope, trigger_event, effect, points_value, priority_order, active, notes)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                    ON DUPLICATE KEY UPDATE name=VALUES(name), rule_type=VALUES(rule_type), scope=VALUES(scope), trigger_event=VALUES(trigger_event), effect=VALUES(effect), points_value=VALUES(points_value), priority_order=VALUES(priority_order), active=VALUES(active), notes=VALUES(notes)
                ')->execute($values);
                $ruleLookup = $pdo->prepare('SELECT id FROM sx_ygo_game_rules WHERE season_id = ? AND code = ? LIMIT 1');
                $ruleLookup->execute([$seasonId, $ruleCode]);
                $ruleId = (int) $ruleLookup->fetchColumn();
            }
            if ($ruleId > 0) {
                $conditionIds = $_POST['condition_id'] ?? [];
                $conditionTypes = $_POST['condition_type'] ?? [];
                $conditionLabels = $_POST['condition_label'] ?? [];
                $conditionOperators = $_POST['condition_operator'] ?? [];
                $conditionTargets = $_POST['condition_target'] ?? [];
                $conditionResults = $_POST['condition_result'] ?? [];
                $conditionStatuses = $_POST['condition_status'] ?? [];
                $conditionScores = $_POST['condition_score'] ?? [];
                $conditionMessages = $_POST['condition_failure_message'] ?? [];
                $conditionActive = $_POST['condition_active'] ?? [];
                $pdo->prepare('UPDATE sx_ygo_game_rule_conditions SET active=0 WHERE game_rule_id=?')->execute([$ruleId]);
                $conditionUpsert = $pdo->prepare('
                    INSERT INTO sx_ygo_game_rule_conditions
                        (game_rule_id, condition_type, condition_label, operator, target_value, result_value, status_value, score_value, failure_message, sort_order, active)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ');
                $conditionUpdate = $pdo->prepare('
                    UPDATE sx_ygo_game_rule_conditions
                    SET condition_type=?, condition_label=?, operator=?, target_value=?, result_value=?, status_value=?, score_value=?, failure_message=?, sort_order=?, active=?
                    WHERE id=? AND game_rule_id=?
                ');
                $allowedTypes = ['result_case', 'victory', 'defeat', 'draw', 'validation', 'score', 'availability', 'custom'];
                $allowedOperators = ['sequence_equals', 'defined', 'winner_in', 'winner_equals', 'score_min', 'score_max', 'points_value', 'requires_previous', 'blocks_when', 'custom'];
                $allowedStatuses = ['FINALIZADA', 'REQUIERE D5', 'EN CURSO', 'PENDIENTE', 'INVALID'];
                $rows = max(count((array) $conditionLabels), count((array) $conditionTypes));
                for ($i = 0; $i < $rows; $i++) {
                    $label = trim((string) ($conditionLabels[$i] ?? ''));
                    if ($label === '') {
                        continue;
                    }
                    $type = trim((string) ($conditionTypes[$i] ?? 'validation'));
                    $operator = trim((string) ($conditionOperators[$i] ?? 'defined'));
                    $type = in_array($type, $allowedTypes, true) ? $type : 'validation';
                    $operator = in_array($operator, $allowedOperators, true) ? $operator : 'defined';
                    $rowActive = in_array((string) ($conditionActive[$i] ?? '0'), ['1', 'on', 'true'], true) ? 1 : 0;
                    $target = trim((string) ($conditionTargets[$i] ?? ''));
                    $result = trim((string) ($conditionResults[$i] ?? ''));
                    $result = in_array($result, ['A', 'B', 'E'], true) ? $result : '';
                    $statusValue = trim((string) ($conditionStatuses[$i] ?? 'FINALIZADA'));
                    $statusValue = in_array($statusValue, $allowedStatuses, true) ? $statusValue : 'FINALIZADA';
                    $scoreValue = trim((string) ($conditionScores[$i] ?? ''));
                    $message = trim((string) ($conditionMessages[$i] ?? ''));
                    $sort = ($i + 1) * 10;
                    $conditionId = (int) ($conditionIds[$i] ?? 0);
                    if ($conditionId > 0) {
                        $conditionUpdate->execute([$type, $label, $operator, $target, $result, $statusValue, $scoreValue, $message, $sort, $rowActive, $conditionId, $ruleId]);
                    } else {
                        $conditionUpsert->execute([$ruleId, $type, $label, $operator, $target, $result, $statusValue, $scoreValue, $message, $sort, $rowActive]);
                    }
                }
            }
            $pdo->prepare('INSERT INTO sx_ygo_audit_log (entity_type, entity_id, action, payload) VALUES (?, ?, ?, ?)')
                ->execute(['game_rule', $ruleId ?: null, 'save_game_rule', json_encode(['code' => $ruleCode, 'name' => $ruleName], JSON_UNESCAPED_UNICODE)]);
        }
        return 'reglamento';
    }
    if ($action === 'archive_game_rule') {
        $seasonId = ygo_current_season_id($pdo);
        $ruleId = (int) ($_POST['game_rule_id'] ?? 0);
        if ($ruleId > 0) {
            $pdo->prepare('UPDATE sx_ygo_game_rules SET active=0 WHERE id=? AND season_id=?')->execute([$ruleId, $seasonId]);
            $pdo->prepare('UPDATE sx_ygo_game_rule_conditions SET active=0 WHERE game_rule_id=?')->execute([$ruleId]);
            $pdo->prepare('INSERT INTO sx_ygo_audit_log (entity_type, entity_id, action, payload) VALUES (?, ?, ?, ?)')
                ->execute(['game_rule', $ruleId, 'archive_game_rule', '{}']);
        }
        return 'reglamento';
    }
    if ($action === 'archive_competition') {
        $id = (int) ($_POST['competition_id'] ?? 0);
        if ($id > 0) {
            $pdo->prepare("UPDATE sx_ygo_competitions SET active=0, status='archived' WHERE id=?")->execute([$id]);
        }
        return 'evento_archivado';
    }
    if ($action === 'save_competition_dependency') {
        $seasonId = ygo_current_season_id($pdo);
        $competitionId = (int) ($_POST['dependency_competition_id'] ?? 0);
        $dependsOnId = (int) ($_POST['dependency_depends_on_id'] ?? 0);
        $condition = preg_replace('/[^a-z_]/', '', strtolower((string) ($_POST['dependency_condition'] ?? 'completed_with_results')));
        if (!in_array($condition, ['completed', 'has_results', 'completed_with_results', 'top_n', 'winner_to_final', 'defending_champion', 'creates_divisions'], true)) {
            $condition = 'completed_with_results';
        }
        $takeCount = max(0, min(128, (int) ($_POST['dependency_take_count'] ?? 0)));
        if ($condition === 'top_n' && $takeCount <= 0) {
            $takeCount = 4;
        }
        if ($competitionId > 0 && $dependsOnId > 0 && $competitionId !== $dependsOnId) {
            $pdo->prepare("
                INSERT INTO sx_ygo_competition_dependencies (season_id, competition_id, depends_on_competition_id, condition_type, take_count, active)
                VALUES (?, ?, ?, ?, ?, 1)
                ON DUPLICATE KEY UPDATE condition_type=VALUES(condition_type), take_count=VALUES(take_count), active=1
            ")->execute([$seasonId, $competitionId, $dependsOnId, $condition, $takeCount]);
            $pdo->prepare('INSERT INTO sx_ygo_audit_log (entity_type, entity_id, action, payload) VALUES (?, ?, ?, ?)')
                ->execute(['competition', $competitionId, 'save_dependency', json_encode(['depends_on' => $dependsOnId, 'condition' => $condition, 'take_count' => $takeCount], JSON_UNESCAPED_UNICODE)]);
        }
        return 'dependencia_evento';
    }
    if ($action === 'delete_competition_dependency') {
        $dependencyId = (int) ($_POST['dependency_id'] ?? 0);
        if ($dependencyId > 0) {
            $pdo->prepare('UPDATE sx_ygo_competition_dependencies SET active=0 WHERE id=?')->execute([$dependencyId]);
            $pdo->prepare('INSERT INTO sx_ygo_audit_log (entity_type, entity_id, action, payload) VALUES (?, ?, ?, ?)')
                ->execute(['competition_dependency', $dependencyId, 'delete_dependency', '{}']);
        }
        return 'dependencia_quitada';
    }
    if ($action === 'set_qualifier_status') {
        $seasonId = ygo_current_season_id($pdo);
        $competitionId = ygo_selected_qualifier_competition_id($pdo);
        $status = (string) ($_POST['status'] ?? 'active');
        if (!in_array($status, ['draft', 'active', 'completed'], true)) {
            $status = 'active';
        }
        if ($status === 'completed') {
            if (!ygo_finalize_qualifier($pdo, $competitionId)) {
                return 'clasificatorio_incompleto';
            }
        }
        if ($competitionId) {
            $currentStmt = $pdo->prepare("SELECT status FROM sx_ygo_competitions WHERE id=? AND season_id=? AND type='qualifier' AND active=1 LIMIT 1");
            $currentStmt->execute([$competitionId, $seasonId]);
            $fromStatus = (string) ($currentStmt->fetchColumn() ?: '');
            $pdo->prepare("UPDATE sx_ygo_competitions SET status=? WHERE id=? AND season_id=? AND type='qualifier' AND active=1")->execute([$status, $competitionId, $seasonId]);
            ygo_record_competition_status_history($pdo, $seasonId, $competitionId, $fromStatus, $status, 'set_qualifier_status');
        }
        return 'clasificatorio_' . $status;
    }
    return null;
}

function ygo_load_data(PDO $pdo, array $fallbackDivisions, array $fallbackFixtures, array $fallbackDecks): array
{
    $seasonId = ygo_current_season_id($pdo);
    $divisionRows = $pdo->prepare('SELECT * FROM sx_ygo_divisions WHERE season_id=? AND active=1 ORDER BY tier_order,id');
    $divisionRows->execute([$seasonId]);
    $dbDivisions = $divisionRows->fetchAll(PDO::FETCH_ASSOC);

    $divisions = [];
    foreach ($dbDivisions as $division) {
        $code = (string) $division['code'];
        $divisions[$code] = [
            'id' => (int) $division['id'],
            'name' => (string) $division['name'],
            'short' => $code,
            'cup' => (string) $division['cup_name'],
            'deck_count' => (int) ($division['deck_count'] ?? 0),
            'tier_order' => (int) ($division['tier_order'] ?? 0),
            'accent' => (string) ($division['accent'] ?: ($fallbackDivisions[$code]['accent'] ?? '#36d8ff')),
            'description' => (string) ($division['description'] ?: ($fallbackDivisions[$code]['description'] ?? 'Evento editable.')),
            'starts_on' => (string) ($division['starts_on'] ?? ''),
            'ends_on' => (string) ($division['ends_on'] ?? ''),
            'promotion_slots' => (int) ($division['promotion_slots'] ?? 2),
            'relegation_slots' => (int) ($division['relegation_slots'] ?? 2),
            'promotion_to_division_id' => $division['promotion_to_division_id'] !== null ? (int) $division['promotion_to_division_id'] : null,
            'relegation_to_division_id' => $division['relegation_to_division_id'] !== null ? (int) $division['relegation_to_division_id'] : null,
            'league_competition_id' => $division['league_competition_id'] !== null ? (int) $division['league_competition_id'] : null,
            'source_qualifier_id' => $division['source_qualifier_id'] !== null ? (int) $division['source_qualifier_id'] : null,
            'rank_from' => $division['rank_from'] !== null ? (int) $division['rank_from'] : null,
            'rank_to' => $division['rank_to'] !== null ? (int) $division['rank_to'] : null,
            'logo_url' => (string) ($division['logo_url'] ?? ''),
            'created_at' => (string) ($division['created_at'] ?? ''),
            'active' => (int) ($division['active'] ?? 1),
            'decks' => [],
        ];
    }

    $deckRows = $pdo->query("
        SELECT d.*, dv.code AS division_code, t.name AS team_name
             , t.logo_url AS team_logo, t.short_code AS team_code, t.primary_color AS team_primary_color, t.secondary_color AS team_secondary_color, t.symbol AS team_symbol
        FROM sx_ygo_decks d
        LEFT JOIN sx_ygo_divisions dv ON dv.id = d.division_id AND dv.active = 1
        LEFT JOIN sx_ygo_teams t ON t.id = d.team_id
        WHERE d.active = 1
        ORDER BY COALESCE(dv.tier_order, 9999), d.seed_rank, d.name
    ")->fetchAll(PDO::FETCH_ASSOC);
    $allDecks = [];
    foreach ($deckRows as $deck) {
        $code = (string) ($deck['division_code'] ?? '');
        $hasDivision = $code !== '' && isset($divisions[$code]);
        $row = [
            (int) $deck['seed_rank'],
            (string) $deck['name'],
            (string) ($deck['reason'] ?: 'Ficha editable'),
            'id' => (int) $deck['id'],
            'team_id' => $deck['team_id'] !== null ? (int) $deck['team_id'] : null,
            'team' => (string) ($deck['team_name'] ?: 'Sin equipo'),
            'team_code' => (string) ($deck['team_code'] ?: ''),
            'team_logo' => ygo_media_src((string) ($deck['team_logo'] ?: '')),
            'team_primary_color' => (string) ($deck['team_primary_color'] ?: ''),
            'team_secondary_color' => (string) ($deck['team_secondary_color'] ?: ''),
            'team_symbol' => (string) ($deck['team_symbol'] ?: ''),
            'archetype' => (string) ($deck['archetype'] ?: $deck['name']),
            'style' => (string) ($deck['style'] ?: 'Editable'),
            'key_cards' => (string) ($deck['key_cards'] ?: ''),
            'variations' => (string) ($deck['variations'] ?: ''),
            'notes' => (string) ($deck['notes'] ?: ''),
            'cover_url' => ygo_media_src((string) ($deck['cover_url'] ?: '')),
            'logo_url' => ygo_media_src((string) ($deck['logo_url'] ?: '')),
        ];
        if ($hasDivision) {
            $divisions[$code]['decks'][] = $row;
        }
        $allDecks[] = [
            'id' => (int) $deck['id'],
            'rank' => (int) $deck['seed_rank'],
            'name' => (string) $deck['name'],
            'division' => $hasDivision ? $code : 'Sin division',
            'division_id' => $hasDivision ? (int) $deck['division_id'] : null,
            'team' => (string) ($deck['team_name'] ?: 'Sin equipo'),
            'team_code' => (string) ($deck['team_code'] ?: ''),
            'team_logo' => ygo_media_src((string) ($deck['team_logo'] ?: '')),
            'team_primary_color' => (string) ($deck['team_primary_color'] ?: ''),
            'team_secondary_color' => (string) ($deck['team_secondary_color'] ?: ''),
            'team_symbol' => (string) ($deck['team_symbol'] ?: ''),
            'team_id' => $deck['team_id'] !== null ? (int) $deck['team_id'] : null,
            'reason' => (string) ($deck['reason'] ?: 'Ficha editable'),
            'archetype' => (string) ($deck['archetype'] ?: $deck['name']),
            'style' => (string) ($deck['style'] ?: 'Editable'),
            'key_cards' => (string) ($deck['key_cards'] ?: ''),
            'variations' => (string) ($deck['variations'] ?: ''),
            'notes' => (string) ($deck['notes'] ?: ''),
            'cover_url' => ygo_media_src((string) ($deck['cover_url'] ?: '')),
            'logo_url' => ygo_media_src((string) ($deck['logo_url'] ?: '')),
        ];
    }

    $qualifierDeckRows = [];
    foreach ($allDecks as $deck) {
        $qualifierDeckRows[] = [
            (int) $deck['rank'],
            (string) $deck['name'],
            (string) $deck['reason'],
            'id' => (int) $deck['id'],
            'team_id' => $deck['team_id'],
            'team' => $deck['team'],
            'team_code' => $deck['team_code'],
            'team_logo' => $deck['team_logo'] ?? '',
            'team_primary_color' => $deck['team_primary_color'],
            'team_secondary_color' => $deck['team_secondary_color'],
            'team_symbol' => $deck['team_symbol'],
            'archetype' => $deck['archetype'],
            'style' => $deck['style'],
            'key_cards' => $deck['key_cards'],
            'variations' => $deck['variations'],
            'notes' => $deck['notes'],
            'cover_url' => $deck['cover_url'],
            'logo_url' => $deck['logo_url'] ?? '',
        ];
    }
    $divisions = ['CLASIFICATORIO' => [
        'id' => 0,
        'name' => 'Torneo clasificatorio inicial',
        'short' => 'CLASIFICATORIO',
        'cup' => 'Ranking de entrada a divisiones',
        'accent' => '#36d8ff',
        'description' => 'Fase previa gestionable con minimo 3 combates por deck.',
        'decks' => $qualifierDeckRows,
    ]] + $divisions;

    $fixtures = [];
    $matchRows = $pdo->query("
        SELECT m.*, CASE WHEN m.phase = 'qualifier' THEN 'CLASIFICATORIO' ELSE dv.code END AS division_code,
               c.name AS competition_name, c.type AS competition_type, c.status AS competition_status, c.event_ruleset AS competition_ruleset, c.ruleset_version AS competition_ruleset_version,
               da.name AS deck_a_name, db.name AS deck_b_name,
               ta.name AS team_a_name, tb.name AS team_b_name,
               ta.short_code AS team_a_code, tb.short_code AS team_b_code,
               ta.logo_url AS team_a_logo, tb.logo_url AS team_b_logo,
               dc.name AS deck_c_name, dd.name AS deck_d_name, de.name AS deck_e_name, df.name AS deck_f_name,
               r.d1, r.d2, r.d3, r.d4, r.d5, r.result, r.score, r.validation_status, r.notes AS result_notes,
               r.dice_roll, r.turn_choice, r.dice_winner, r.turn_chosen_by, r.turn_decision, r.starts_player, r.duel_details, r.updated_at AS result_updated_at
        FROM sx_ygo_matches m
        LEFT JOIN sx_ygo_divisions dv ON dv.id = m.division_id
        JOIN sx_ygo_decks da ON da.id = m.deck_a_id
        JOIN sx_ygo_decks db ON db.id = m.deck_b_id
        LEFT JOIN sx_ygo_teams ta ON ta.id = da.team_id
        LEFT JOIN sx_ygo_teams tb ON tb.id = db.team_id
        LEFT JOIN sx_ygo_decks dc ON dc.id = m.deck_c_id
        LEFT JOIN sx_ygo_decks dd ON dd.id = m.deck_d_id
        LEFT JOIN sx_ygo_decks de ON de.id = m.deck_e_id
        LEFT JOIN sx_ygo_decks df ON df.id = m.deck_f_id
        LEFT JOIN sx_ygo_competitions c ON c.id = m.competition_id AND c.active = 1
        LEFT JOIN sx_ygo_match_results r ON r.match_id = m.id
        WHERE m.competition_id IS NULL OR c.id IS NOT NULL
        ORDER BY dv.tier_order, m.display_order, m.round_number, m.table_number
    ")->fetchAll(PDO::FETCH_ASSOC);
    foreach ($matchRows as $match) {
        $phase = (string) $match['phase'];
        $baseCode = (string) $match['division_code'];
        if ($phase === 'qualifier') {
            $code = 'CLASIFICATORIO';
        } elseif ($phase === 'league') {
            $code = $baseCode !== '' ? $baseCode : 'LIGA';
        } elseif ($phase === 'cup') {
            $code = $baseCode !== '' ? 'COPA_' . $baseCode : 'COPA';
        } elseif ($phase === 'world') {
            $code = 'MUNDIAL';
        } elseif ($phase === 'friendly') {
            $code = 'AMISTOSOS';
        } else {
            $code = strtoupper($phase);
        }
        if ($code === '') {
            $code = strtoupper($phase ?: 'GENERAL');
        }
        $round = (int) $match['round_number'];
        if (!isset($fixtures[$code][$round])) {
            $fixtures[$code][$round] = ['ronda' => $round, 'descansa' => '', 'matches' => []];
        }
        $fixtures[$code][$round]['matches'][] = [
            'id' => (int) $match['id'],
            'phase' => $phase,
            'competition_id' => $match['competition_id'] !== null ? (int) $match['competition_id'] : null,
            'manual_friendly' => $phase === 'friendly' && $match['competition_id'] === null,
            'game_rule_id' => $match['game_rule_id'] !== null ? (int) $match['game_rule_id'] : null,
            'competition_name' => (string) ($match['competition_name'] ?? ''),
            'competition_type' => (string) ($match['competition_type'] ?? $phase),
            'competition_status' => (string) ($match['competition_status'] ?? ''),
            'competition_ruleset' => (string) ($match['competition_ruleset'] ?? ''),
            'competition_ruleset_version' => (string) ($match['competition_ruleset_version'] ?? ''),
            'match_format' => (string) ($match['match_format'] ?? '1v1'),
            'best_of' => (int) ($match['best_of'] ?? 5),
            'participants_per_side' => (int) ($match['participants_per_side'] ?? 1),
            'mesa' => (int) $match['table_number'],
            'bracket_round_label' => (string) ($match['bracket_round_label'] ?? ''),
            'bracket_slot' => $match['bracket_slot'] !== null ? (int) $match['bracket_slot'] : null,
            'bracket_side' => (string) ($match['bracket_side'] ?? ''),
            'display_order' => (int) ($match['display_order'] ?? 0),
            'generation_number' => (int) ($match['generation_number'] ?? 1),
            'deck_a_id' => (int) $match['deck_a_id'],
            'deck_b_id' => (int) $match['deck_b_id'],
            'deck_c_id' => $match['deck_c_id'] !== null ? (int) $match['deck_c_id'] : null,
            'deck_d_id' => $match['deck_d_id'] !== null ? (int) $match['deck_d_id'] : null,
            'deck_e_id' => $match['deck_e_id'] !== null ? (int) $match['deck_e_id'] : null,
            'deck_f_id' => $match['deck_f_id'] !== null ? (int) $match['deck_f_id'] : null,
            'a' => (string) $match['deck_a_name'],
            'b' => (string) $match['deck_b_name'],
            'team_a' => (string) ($match['team_a_name'] ?: 'Sin equipo'),
            'team_b' => (string) ($match['team_b_name'] ?: 'Sin equipo'),
            'team_a_code' => (string) ($match['team_a_code'] ?: ''),
            'team_b_code' => (string) ($match['team_b_code'] ?: ''),
            'team_a_logo' => (string) ($match['team_a_logo'] ?? ''),
            'team_b_logo' => (string) ($match['team_b_logo'] ?? ''),
            'team_a_extra' => array_values(array_filter([(string) ($match['deck_c_name'] ?? ''), (string) ($match['deck_e_name'] ?? '')])),
            'team_b_extra' => array_values(array_filter([(string) ($match['deck_d_name'] ?? ''), (string) ($match['deck_f_name'] ?? '')])),
            'estado' => (string) $match['status'],
            'scheduled_on' => (string) ($match['scheduled_on'] ?? ''),
            'starts_at' => (string) ($match['starts_at'] ?? ''),
            'venue' => (string) ($match['venue'] ?? ''),
            'judge' => (string) ($match['judge'] ?? ''),
            'notes' => (string) ($match['notes'] ?? ''),
            'duels' => array_values(array_filter([$match['d1'], $match['d2'], $match['d3'], $match['d4'], $match['d5']], static fn($v): bool => $v !== null && $v !== '')),
            'result' => (string) ($match['result'] ?? ''),
            'score' => (string) ($match['score'] ?? ''),
            'result_notes' => (string) ($match['result_notes'] ?? ''),
            'dice_roll' => (string) ($match['dice_roll'] ?? ''),
            'turn_choice' => (string) ($match['turn_choice'] ?? ''),
            'dice_winner' => (string) ($match['dice_winner'] ?? ''),
            'turn_chosen_by' => (string) ($match['turn_chosen_by'] ?? ''),
            'turn_decision' => (string) ($match['turn_decision'] ?? ''),
            'starts_player' => (string) ($match['starts_player'] ?? ''),
            'duel_details' => (string) ($match['duel_details'] ?? ''),
            'result_updated_at' => (string) ($match['result_updated_at'] ?? ''),
            'completed_at' => $match['completed_at'] ?? null,
        ];
    }
    foreach ($divisions as $code => $division) {
        if (!isset($fixtures[$code])) {
            $fixtures[$code] = [];
        } else {
            ksort($fixtures[$code]);
            $fixtures[$code] = array_values($fixtures[$code]);
        }
    }
    $deckMatchCounts = [];
    foreach ($matchRows as $match) {
        foreach (['deck_a_id', 'deck_b_id', 'deck_c_id', 'deck_d_id', 'deck_e_id', 'deck_f_id'] as $deckColumn) {
            $matchDeckId = (int) ($match[$deckColumn] ?? 0);
            if ($matchDeckId > 0) {
                $deckMatchCounts[$matchDeckId] = ($deckMatchCounts[$matchDeckId] ?? 0) + 1;
            }
        }
    }

    $teams = $pdo->query('SELECT * FROM sx_ygo_teams WHERE active = 1 ORDER BY name')->fetchAll(PDO::FETCH_ASSOC);
    $competitions = $pdo->query("
        SELECT c.*, d.code AS division_code, s.name AS season_name, s.status AS season_status, s.starts_on AS season_starts_on, s.ends_on AS season_ends_on
        FROM sx_ygo_competitions c
        JOIN sx_ygo_seasons s ON s.id=c.season_id
        LEFT JOIN sx_ygo_divisions d ON d.id = c.division_id
        WHERE c.active = 1
        ORDER BY COALESCE(c.starts_on, '2999-12-31'), c.type, c.name
    ")->fetchAll(PDO::FETCH_ASSOC);
    $leagueDivisionIdsByCompetition = [];
    foreach ($dbDivisions as $division) {
        $leagueCompetitionId = (int) ($division['league_competition_id'] ?? 0);
        if ($leagueCompetitionId > 0) {
            $leagueDivisionIdsByCompetition[$leagueCompetitionId][] = (int) $division['id'];
        }
    }
    $matchTypes = $pdo->query('SELECT * FROM sx_ygo_match_types WHERE active = 1 ORDER BY sort_order, id')->fetchAll(PDO::FETCH_ASSOC);
    $competitionDeckRows = $pdo->query('SELECT competition_id, deck_id FROM sx_ygo_competition_decks WHERE active = 1 ORDER BY competition_id, sort_order, id')->fetchAll(PDO::FETCH_ASSOC);
    $competitionDecksById = [];
    foreach ($competitionDeckRows as $row) {
        $competitionDecksById[(int) $row['competition_id']][] = (int) $row['deck_id'];
    }
    $deckTeamById = [];
    foreach ($allDecks as $deck) {
        $deckTeamById[(int) ($deck['id'] ?? 0)] = $deck['team_id'] !== null ? (int) $deck['team_id'] : null;
    }
    $competitionDependencies = ygo_competition_dependencies($pdo);
    $competitionGateById = [];
    $progressGroups = ygo_event_progress_groups($pdo);
    foreach ($competitions as &$competition) {
        $competitionId = (int) $competition['id'];
        $competition['progress'] = ygo_event_progress($progressGroups[$competitionId] ?? [], in_array((string)$competition['type'], ['cup','final'], true));
        $associatedDivisionIds = array_values(array_unique($leagueDivisionIdsByCompetition[$competitionId] ?? []));
        $primaryDivisionId = (int) ($competition['division_id'] ?? 0);
        if ($primaryDivisionId > 0 && !in_array($primaryDivisionId, $associatedDivisionIds, true)) {
            array_unshift($associatedDivisionIds, $primaryDivisionId);
        }
        $competition['division_ids'] = $associatedDivisionIds;
        $competition['division_count'] = count($associatedDivisionIds);
        $gate = ygo_competition_gate($pdo, $competitionId);
        $competition['dependencies'] = $gate['dependencies'];
        $competition['can_execute'] = $gate['can_execute'];
        $competition['blocked_reason'] = $gate['can_execute'] ? '' : $gate['message'];
        try {
            $allowedDeckIds = ygo_allowed_deck_ids_for_competition($pdo, $competitionId, (int) ($competition['division_id'] ?? 0));
            $standings = ygo_competition_standings($pdo, $competitionId);
        } catch (Throwable $error) {
            $allowedDeckIds = [];
            $standings = [];
        }
        $competition['selected_deck_ids'] = $competitionDecksById[$competitionId] ?? [];
        $visibleDeckIds = $competition['selected_deck_ids'] ?: $allowedDeckIds;
        $participantTeamIds = [];
        foreach ($visibleDeckIds as $deckId) {
            $teamId = $deckTeamById[(int) $deckId] ?? null;
            if ($teamId !== null && $teamId > 0) {
                $participantTeamIds[$teamId] = true;
            }
        }
        $competition['deck_pool_count'] = count($allowedDeckIds);
        $competition['participant_deck_count'] = count($visibleDeckIds);
        $competition['participant_team_count'] = count($participantTeamIds);
        $competition['ranking'] = $standings;
        $competition['ranking_ready'] = count($standings) > 0;
        $competition['top_cut'] = array_slice($standings, 0, (string) ($competition['type'] ?? '') === 'world' ? 24 : 16);
        $competitionGateById[$competitionId] = $gate;
    }
    unset($competition);
    foreach ($fixtures as &$rounds) {
        foreach ($rounds as &$round) {
            foreach ($round['matches'] as &$match) {
                $competitionId = (int) ($match['competition_id'] ?? 0);
                $gate = $competitionId > 0 ? ($competitionGateById[$competitionId] ?? ygo_competition_gate($pdo, $competitionId)) : ['can_execute' => true, 'message' => 'Sin competencia asociada.'];
                $match['competition_can_execute'] = (bool) ($gate['can_execute'] ?? true);
                $match['competition_blocked_reason'] = (string) (($gate['message'] ?? '') ?: '');
            }
            unset($match);
        }
        unset($round);
    }
    unset($rounds);
    $cards = $pdo->query('SELECT * FROM sx_ygo_deck_cards ORDER BY deck_id, section, id')->fetchAll(PDO::FETCH_ASSOC);
    $media = $pdo->query('SELECT * FROM sx_ygo_deck_media ORDER BY deck_id, id')->fetchAll(PDO::FETCH_ASSOC);
    $logoHistory = $pdo->query('SELECT * FROM sx_ygo_logo_history ORDER BY created_at DESC, id DESC')->fetchAll(PDO::FETCH_ASSOC);
    $honors = $pdo->query("
        SELECT h.*, c.name AS competition_name, dc.name AS champion_name, dr.name AS runner_name,
               tc.name AS champion_team_name, tr.name AS runner_team_name
        FROM sx_ygo_honors h
        LEFT JOIN sx_ygo_competitions c ON c.id = h.competition_id
        LEFT JOIN sx_ygo_decks dc ON dc.id = h.champion_deck_id
        LEFT JOIN sx_ygo_decks dr ON dr.id = h.runner_up_deck_id
        LEFT JOIN sx_ygo_teams tc ON tc.id = h.champion_team_id
        LEFT JOIN sx_ygo_teams tr ON tr.id = h.runner_up_team_id
        ORDER BY h.created_at DESC, h.id DESC
    ")->fetchAll(PDO::FETCH_ASSOC);
    $audit = $pdo->query('SELECT * FROM sx_ygo_audit_log ORDER BY created_at DESC, id DESC LIMIT 80')->fetchAll(PDO::FETCH_ASSOC);
    $events = $pdo->query("
        SELECT e.*, c.name AS competition_name, c.type AS competition_type, c.status AS competition_status, d.code AS division_code
        FROM sx_ygo_events e
        LEFT JOIN sx_ygo_competitions c ON c.id = e.competition_id
        LEFT JOIN sx_ygo_divisions d ON d.id = e.division_id
        WHERE e.active = 1
        ORDER BY e.event_date, e.starts_at, e.id
    ")->fetchAll(PDO::FETCH_ASSOC);
    $rulesets = $pdo->query('SELECT * FROM sx_ygo_rulesets WHERE active = 1 ORDER BY format_family, label')->fetchAll(PDO::FETCH_ASSOC);
    $rulesetVersions = $pdo->query('SELECT * FROM sx_ygo_ruleset_versions WHERE active = 1 ORDER BY sort_order, COALESCE(effective_on, "9999-12-31") DESC, version_label DESC')->fetchAll(PDO::FETCH_ASSOC);
    $gameRules = $pdo->query('SELECT * FROM sx_ygo_game_rules ORDER BY active DESC, priority_order, name')->fetchAll(PDO::FETCH_ASSOC);
    $gameRuleConditions = $pdo->query('SELECT * FROM sx_ygo_game_rule_conditions WHERE active = 1 ORDER BY game_rule_id, sort_order, id')->fetchAll(PDO::FETCH_ASSOC);
    $conditionsByRule = [];
    foreach ($gameRuleConditions as $condition) {
        $conditionsByRule[(int) ($condition['game_rule_id'] ?? 0)][] = $condition;
    }
    foreach ($gameRules as &$gameRule) {
        $gameRule['conditions'] = $conditionsByRule[(int) ($gameRule['id'] ?? 0)] ?? [];
        $gameRule['condition_count'] = count(array_filter($gameRule['conditions'], static fn($condition) => !empty($condition['active'])));
    }
    unset($gameRule);
    $statusHistory = ygo_table_exists($pdo, 'sx_ygo_competition_status_history')
        ? $pdo->query('SELECT * FROM sx_ygo_competition_status_history ORDER BY created_at DESC, id DESC LIMIT 120')->fetchAll(PDO::FETCH_ASSOC)
        : [];
    ygo_backfill_missing_standing_snapshots($pdo, ygo_current_season_id($pdo));
    $standingSnapshots = ygo_table_exists($pdo, 'sx_ygo_standing_snapshots')
        ? $pdo->query('SELECT * FROM sx_ygo_standing_snapshots ORDER BY competition_id, snapshot_key, position')->fetchAll(PDO::FETCH_ASSOC)
        : [];
    $seasons = $pdo->query('SELECT s.*, (SELECT COUNT(*) FROM sx_ygo_competitions c WHERE c.season_id=s.id) event_count, (SELECT COUNT(*) FROM sx_ygo_divisions d WHERE d.season_id=s.id) division_count FROM sx_ygo_seasons s ORDER BY s.starts_on DESC,s.id DESC')->fetchAll(PDO::FETCH_ASSOC);
    $players = ygo_table_exists($pdo, 'sx_ygo_players')
        ? $pdo->query("SELECT p.*, t.name AS team_name FROM sx_ygo_players p LEFT JOIN sx_ygo_teams t ON t.id=p.team_id WHERE p.active=1 ORDER BY p.display_name")->fetchAll(PDO::FETCH_ASSOC)
        : [];
    $playerDecks = ygo_table_exists($pdo, 'sx_ygo_player_decks')
        ? $pdo->query('SELECT player_id, deck_id FROM sx_ygo_player_decks WHERE active=1')->fetchAll(PDO::FETCH_ASSOC)
        : [];
    $matchSnapshots = ygo_table_exists($pdo, 'sx_ygo_match_snapshots')
        ? $pdo->query('SELECT * FROM sx_ygo_match_snapshots ORDER BY captured_at DESC, id DESC')->fetchAll(PDO::FETCH_ASSOC)
        : [];
    $matchupAnalyses = ygo_table_exists($pdo, 'sx_ygo_matchup_analyses')
        ? $pdo->query("SELECT a.*, d.name AS deck_name, o.name AS opponent_name FROM sx_ygo_matchup_analyses a JOIN sx_ygo_decks d ON d.id=a.deck_id JOIN sx_ygo_decks o ON o.id=a.opponent_deck_id WHERE a.active=1 ORDER BY a.updated_at DESC, a.id DESC")->fetchAll(PDO::FETCH_ASSOC)
        : [];
    $matchupAnalysisHistory = ygo_table_exists($pdo, 'sx_ygo_matchup_analysis_history')
        ? $pdo->query('SELECT * FROM sx_ygo_matchup_analysis_history ORDER BY created_at DESC, id DESC LIMIT 2000')->fetchAll(PDO::FETCH_ASSOC)
        : [];
    $deckAnalysisProfiles = ygo_table_exists($pdo, 'sx_ygo_deck_analysis_profiles')
        ? $pdo->query('SELECT * FROM sx_ygo_deck_analysis_profiles ORDER BY updated_at DESC, deck_id')->fetchAll(PDO::FETCH_ASSOC)
        : [];
    $deckAnalysisProfileHistory = ygo_table_exists($pdo, 'sx_ygo_deck_analysis_profile_history')
        ? $pdo->query('SELECT * FROM sx_ygo_deck_analysis_profile_history ORDER BY created_at DESC, id DESC LIMIT 2000')->fetchAll(PDO::FETCH_ASSOC)
        : [];
    $medalUnlocks = ygo_table_exists($pdo, 'sx_ygo_medal_unlocks')
        ? $pdo->query('SELECT * FROM sx_ygo_medal_unlocks ORDER BY unlocked_at DESC, id DESC')->fetchAll(PDO::FETCH_ASSOC)
        : [];
    $checkins = ygo_table_exists($pdo, 'sx_ygo_checkins')
        ? $pdo->query('SELECT * FROM sx_ygo_checkins ORDER BY checked_in_at DESC, id DESC LIMIT 300')->fetchAll(PDO::FETCH_ASSOC)
        : [];
    $metaHistory = ygo_table_exists($pdo, 'sx_ygo_meta_history')
        ? $pdo->query('SELECT * FROM sx_ygo_meta_history ORDER BY snapshot_on DESC, id DESC LIMIT 2000')->fetchAll(PDO::FETCH_ASSOC)
        : [];
    $eloHistory = ygo_table_exists($pdo, 'sx_ygo_elo_history')
        ? $pdo->query('SELECT e.*,d.name AS deck_name,o.name AS opponent_name FROM sx_ygo_elo_history e LEFT JOIN sx_ygo_decks d ON d.id=e.deck_id LEFT JOIN sx_ygo_decks o ON o.id=e.opponent_deck_id ORDER BY e.played_on DESC,e.match_id DESC,e.id DESC LIMIT 4000')->fetchAll(PDO::FETCH_ASSOC)
        : [];
    $medalProgress = ygo_table_exists($pdo, 'sx_ygo_medal_progress')
        ? $pdo->query('SELECT * FROM sx_ygo_medal_progress ORDER BY entity_type,entity_id,unlocked DESC,rarity,medal_name')->fetchAll(PDO::FETCH_ASSOC)
        : [];
    $rankingHistory = ygo_table_exists($pdo, 'sx_ygo_global_ranking_history')
        ? $pdo->query('SELECT h.*,d.name AS deck_name,t.name AS team_name FROM sx_ygo_global_ranking_history h LEFT JOIN sx_ygo_decks d ON d.id=h.deck_id LEFT JOIN sx_ygo_teams t ON t.id=d.team_id ORDER BY h.snapshot_on DESC,h.season_id,h.position LIMIT 5000')->fetchAll(PDO::FETCH_ASSOC)
        : [];
    $eventSummaries = ygo_table_exists($pdo, 'sx_ygo_event_summaries')
        ? $pdo->query('SELECT s.*,c.name AS competition_name,d1.name AS champion_name,d2.name AS runner_up_name FROM sx_ygo_event_summaries s LEFT JOIN sx_ygo_competitions c ON c.id=s.competition_id LEFT JOIN sx_ygo_decks d1 ON d1.id=s.champion_deck_id LEFT JOIN sx_ygo_decks d2 ON d2.id=s.runner_up_deck_id ORDER BY s.finalized_on DESC,s.competition_id DESC')->fetchAll(PDO::FETCH_ASSOC)
        : [];

    $allDecks=ygo_deck_list_metadata($allDecks,$cards,$deckAnalysisProfiles);
    return [
        'divisions' => $divisions,
        'fixtures' => $fixtures,
        'allDecks' => $allDecks,
        'teams' => $teams,
        'competitions' => $competitions,
        'cards' => $cards,
        'media' => $media,
        'logoHistory' => $logoHistory,
        'honors' => $honors,
        'audit' => $audit,
        'events' => $events,
        'rulesets' => $rulesets,
        'rulesetVersions' => $rulesetVersions,
        'matchTypes' => $matchTypes,
        'gameRules' => $gameRules,
        'gameRuleConditions' => $gameRuleConditions,
        'competitionDependencies' => $competitionDependencies,
        'statusHistory' => $statusHistory,
        'standingSnapshots' => $standingSnapshots,
        'seasons' => $seasons,
        'seasonEvents' => ygo_season_event_rows($pdo),
        'players' => $players,
        'playerDecks' => $playerDecks,
        'matchSnapshots' => $matchSnapshots,
        'matchupAnalyses' => $matchupAnalyses,
        'matchupAnalysisHistory' => $matchupAnalysisHistory,
        'deckAnalysisProfiles' => $deckAnalysisProfiles,
        'deckAnalysisProfileHistory' => $deckAnalysisProfileHistory,
        'medalUnlocks' => $medalUnlocks,
        'checkins' => $checkins,
        'metaHistory' => $metaHistory,
        'eloHistory' => $eloHistory,
        'medalProgress' => $medalProgress,
        'rankingHistory' => $rankingHistory,
        'eventSummaries' => $eventSummaries,
        'settings' => ygo_settings($pdo),
        'uiLabels' => ygo_ui_labels($pdo),
        'serverToday' => date('Y-m-d'),
    ];
}

$fixtures = [];
$allDecks = [];
foreach ($divisions as $key => $division) {
    $fixtures[$key] = buildFixtures($division['decks']);
    foreach ($division['decks'] as $deck) {
        $allDecks[] = ['rank' => $deck[0], 'name' => $deck[1], 'division' => $key, 'reason' => $deck[2]];
    }
}

$defaultUiLabels = [];
foreach (ygo_default_ui_labels() as $defaultUiLabel) {
    $defaultUiLabels[$defaultUiLabel[0]] = ['id' => 0, 'key' => $defaultUiLabel[0], 'title' => $defaultUiLabel[1], 'subtitle' => $defaultUiLabel[2], 'active' => 1];
}
$appData = ['eventExtendedCatalog'=>ygo_extended_event_catalog(), 'executionCapabilities'=>ygo_execution_capabilities(), 'executionModelsByType'=>array_combine(array_keys($competitionTypeOptions),array_map('ygo_allowed_execution_models_for_type',array_keys($competitionTypeOptions))), 'divisions' => $divisions, 'fixtures' => $fixtures, 'allDecks' => $allDecks, 'teams' => [], 'competitions' => [], 'competitionDependencies' => [], 'statusHistory' => [], 'standingSnapshots' => [], 'matchSnapshots' => [], 'matchupAnalyses' => [], 'matchupAnalysisHistory' => [], 'deckAnalysisProfiles' => [], 'deckAnalysisProfileHistory' => [], 'medalUnlocks' => [], 'medalProgress' => [], 'rankingHistory' => [], 'eventSummaries' => [], 'checkins' => [], 'metaHistory' => [], 'eloHistory' => [], 'seasons' => [], 'players' => [], 'playerDecks' => [], 'cards' => [], 'media' => [], 'logoHistory' => [], 'honors' => [], 'audit' => [], 'events' => [], 'rulesets' => [], 'rulesetVersions' => [], 'matchTypes' => [], 'gameRules' => [], 'gameRuleConditions' => [], 'eventPromptTemplates' => ygo_event_available_templates(), 'competitionTypeOptions' => $competitionTypeOptions, 'settings' => ygo_default_settings(), 'uiLabels' => $defaultUiLabels, 'serverToday' => date('Y-m-d')];
$dbStatus = ['connected' => false, 'message' => 'Sin conexion comprobada', 'counts' => []];
$flashMessage = null;
$flashClass = 'alert-success';
try {
    $prefix = '';
    $entity = 1;
    $pdo = connect_store_common($prefix, $entity);
    ensure_ygo_tables($pdo);
    ygo_add_column($pdo, 'sx_ygo_matches', 'completed_at', 'DATETIME NULL');
    ygo_stats_schema($pdo);
    ygo_ffa_schema($pdo);
    ygo_stats_bootstrap_global($pdo);
    ygo_add_column($pdo, 'sx_ygo_competitions', 'execution_parameters', 'LONGTEXT NULL');
    $seedCounts = seed_ygo_database($pdo, $divisions, $fixtures);
    ygo_ensure_seasons_schema($pdo);
    ygo_season_initialize_context($pdo);
    ygo_season_close_expired($pdo);
    $seasonId = ygo_current_season_id($pdo);
    ygo_ensure_default_catalogs($pdo, $seasonId);
    $catalogGroups = ygo_load_catalog_groups($pdo, $seasonId);
    $competitionTypeOptions = ygo_catalog_competition_type_options($catalogGroups['competition_type'] ?? []);
    $eventRulesetOptions = ygo_catalog_label_options($catalogGroups['ruleset'] ?? []);
    $eventDeckPoolOptions = ygo_catalog_label_options($catalogGroups['deck_pool'] ?? []);
    $competitionStatusOptions = ygo_catalog_label_options($catalogGroups['competition_status'] ?? []);
    $matchStatusOptions = ygo_catalog_label_options($catalogGroups['match_status'] ?? []);
    $schedulePatternOptions = ygo_catalog_label_options($catalogGroups['schedule_pattern'] ?? []);
    ygo_ensure_default_settings($pdo);
    ygo_ensure_default_ui_labels($pdo);
    $deletedSeedDivisions = ygo_remove_seeded_divisions($pdo, $seasonId, $divisionDefinitions);
    ygo_ensure_default_rulesets($pdo, $seasonId, $eventRulesetOptions);
    ygo_ensure_default_ruleset_versions($pdo, $seasonId);
    ygo_ensure_default_match_types($pdo);
    ygo_ensure_default_game_rules($pdo, $seasonId);
    $goatLeagueStructure = ygo_ensure_goat_league_structure($pdo, $seasonId);
    $octoberScheduleMigration = ygo_apply_october_2026_schedule($pdo);
    $activatedDueCompetitions = ygo_activate_due_scheduled_competitions($pdo);
    ygo_ensure_sequential_competition_codes($pdo, $seasonId);
    $teamSyncCount = ygo_sync_deck_teams($pdo);
    $deckFormatSyncCount = ygo_normalize_existing_decks_to_tcg_advanced($pdo);
    $deckAnalysisBaselineCount = ygo_seed_deck_analysis_profiles($pdo);
    $medalBackfillVersion = 'medal_backfill_v2';
    $medalBackfillStmt = $pdo->prepare('SELECT COUNT(*) FROM sx_ygo_audit_log WHERE action = ?');
    $medalBackfillStmt->execute([$medalBackfillVersion]);
    if ((int) $medalBackfillStmt->fetchColumn() === 0) {
        $unlockedCount = ygo_sync_medal_unlocks($pdo);
        $pdo->prepare('INSERT INTO sx_ygo_audit_log (entity_type, entity_id, action, payload) VALUES (?, ?, ?, ?)')
            ->execute(['system', 0, $medalBackfillVersion, json_encode(['unlocks_processed' => $unlockedCount], JSON_UNESCAPED_UNICODE)]);
    }
    $snapshotBackfillVersion = 'snapshot_backfill_v1';
    $snapshotBackfillStmt = $pdo->prepare('SELECT COUNT(*) FROM sx_ygo_audit_log WHERE action = ?');
    $snapshotBackfillStmt->execute([$snapshotBackfillVersion]);
    if ((int) $snapshotBackfillStmt->fetchColumn() === 0) {
        $snapshotCount = ygo_backfill_match_snapshots($pdo);
        $pdo->prepare('INSERT INTO sx_ygo_audit_log (entity_type, entity_id, action, payload) VALUES (?, ?, ?, ?)')
            ->execute(['system', 0, $snapshotBackfillVersion, json_encode(['snapshots_inferred' => $snapshotCount], JSON_UNESCAPED_UNICODE)]);
    }
    $intelligenceVersion = 'historical_intelligence_v2';
    $intelligenceStmt = $pdo->prepare('SELECT COUNT(*) FROM sx_ygo_audit_log WHERE action=?');
    $intelligenceStmt->execute([$intelligenceVersion]);
    if ((int) $intelligenceStmt->fetchColumn() === 0) {
        $metaRows = ygo_sync_meta_history($pdo);
        $eloRows = ygo_sync_elo_history($pdo);
        $rankingRows = ygo_sync_global_ranking_history($pdo);
        $summaryRows = ygo_sync_event_summaries($pdo);
        ygo_sync_medal_unlocks($pdo);
        $pdo->prepare('INSERT INTO sx_ygo_audit_log (entity_type,entity_id,action,payload) VALUES (?,?,?,?)')->execute(['system',0,$intelligenceVersion,json_encode(['meta_rows'=>$metaRows,'elo_rows'=>$eloRows],JSON_UNESCAPED_UNICODE)]);
    }
    if ((string) ($_GET['api'] ?? '') === 'duel_session') {
        ygo_live_duel_api($pdo);
    }
    if ((string) ($_GET['api'] ?? '') === 'match_editor') {
        ygo_match_editor_api($pdo);
    }
    if ((string) ($_GET['api'] ?? '') === 'event_audit') ygo_event_audit_api($pdo);
    if ((string) ($_GET['api'] ?? '') === 'event_logo') ygo_event_logo_api($pdo);
    if ((string) ($_GET['api'] ?? '') === 'deck_builder') ygo_deck_builder_api($pdo);
    try { $posted = ygo_handle_post($pdo); }
    catch(InvalidArgumentException $validationError) {
        if($pdo->inTransaction())$pdo->rollBack();
        $posted=null;$flashMessage=$validationError->getMessage();$flashClass='alert-warning';
    }
    if ($posted !== null) {
        ygo_sync_event_summaries($pdo);
    }
    $dbStatus = [
        'connected' => true,
        'message' => 'Conectado a la misma base de datos de la tienda',
        'counts' => array_merge($seedCounts, ['deleted_seed_divisions' => $deletedSeedDivisions, 'deck_teams_sync' => $teamSyncCount, 'deck_format_sync' => $deckFormatSyncCount, 'deck_analysis_baselines' => $deckAnalysisBaselineCount, 'goat_disabled_qualifiers' => $goatLeagueStructure['disabled_qualifiers'] ?? 0, 'shared_schedule_limit' => $octoberScheduleMigration['global_daily_limit'] ?? 3, 'auto_started_events' => $activatedDueCompetitions]),
    ];
    if ($posted !== null) {
        $targetScreen = preg_match('/^jugador_\d+$/', $posted) ? 'estadisticas' : (preg_match('/^(?:deck|deck_analysis|logo_deck|matchup_deck)_\d+$/', $posted) ? 'deck_ficha' : (preg_match('/^(?:equipo|logo_team)_\d+$/', $posted) ? 'equipo_ficha' : (preg_match('/^logo_competition_\d+$/', $posted) ? 'evento_ficha' : (strpos($posted, 'clasificatorio_') === 0 ? 'clasificatorio' : (in_array($posted, ['resultado', 'resultado_bloqueado_fecha', 'resultado_bloqueado_inicio', 'resultado_bloqueado_rendicion'], true) ? 'resultados' : (in_array($posted, ['calendario', 'calendario_reprogramado', 'calendario_bloqueado_finalizado', 'partido_manual', 'partido_invalido', 'amistoso_eliminado', 'amistoso_no_eliminado', 'calendario_bloqueado_regla', 'partido_bloqueado_regla', 'partido_bloqueado_pool'], true) ? 'calendario' : ($posted === 'reglamento' ? 'reglamento' : ($posted === 'reglas' ? 'reglas' : (($posted === 'evento' || strpos($posted, 'evento_configurado_') === 0 || preg_match('/^(evento_avanzado|divisiones_aplicadas|movimientos_liga|ranking_recalculado)_/', $posted)) ? 'evento_ficha' : 'gestion')))))))));
        $postedEventId = 0;
        if (preg_match('/^evento_configurado_(\d+)$/', $posted, $eventMatch)) {
            $postedEventId = (int) $eventMatch[1];
        } elseif (preg_match('/^logo_competition_(\d+)$/', $posted, $eventMatch)) {
            $postedEventId = (int) $eventMatch[1];
        } elseif (preg_match('/^(?:evento_avanzado|divisiones_aplicadas|movimientos_liga|ranking_recalculado)_(\d+)_\d+$/', $posted, $eventMatch)) {
            $postedEventId = (int) $eventMatch[1];
        } elseif ($targetScreen === 'clasificatorio') {
            $postedEventId = (int) ($_POST['qualifier_competition_id'] ?? 0);
        }
        $extraQuery = $postedEventId > 0 ? '&event_id=' . $postedEventId : '';
        if (preg_match('/^(?:deck|deck_analysis|logo_deck|matchup_deck)_(\d+)$/', $posted, $entityMatch)) {
            $extraQuery .= '&deck_id=' . (int) $entityMatch[1];
            if (str_starts_with($posted, 'matchup_deck_') || str_starts_with($posted, 'deck_analysis_')) $extraQuery .= '&deck_view=analysis';
        } elseif (preg_match('/^(?:equipo|logo_team)_(\d+)$/', $posted, $entityMatch)) {
            $extraQuery .= '&team_id=' . (int) $entityMatch[1];
        }
        if (str_starts_with($posted, 'temporada_')) $targetScreen = 'temporadas';
        if ($posted === 'evento_temporada_bloqueada') $targetScreen = 'torneos';
        if (!ygo_season_find($pdo, ygo_current_season_id($pdo))) unset($GLOBALS['ygo_request_season_id']);
        header('Location: ./?saved=' . rawurlencode($posted) . '&season_context=' . ygo_current_season_id($pdo) . $extraQuery . '#' . $targetScreen);
        exit;
    }
    ygo_stats_sync($pdo);
    $appData = ygo_load_data($pdo, $divisions, $fixtures, $allDecks);
    $appData['statisticsV8'] = ygo_stats_view($pdo);
    $appData['executionCapabilities']=ygo_execution_capabilities();
    $appData['executionModelsByType']=array_combine(array_keys($competitionTypeOptions),array_map('ygo_allowed_execution_models_for_type',array_keys($competitionTypeOptions)));
    $appData['ffaMatches']=ygo_ffa_rows($pdo);$appData['canEditEvents']=ygo_can_edit();
    $appData['teamResults']=[];$teamResultsQuery=$pdo->prepare("SELECT setting_key,setting_value FROM sx_ygo_settings WHERE season_id=? AND setting_key LIKE 'event_team_result_%'");$teamResultsQuery->execute([ygo_current_season_id($pdo)]);foreach($teamResultsQuery->fetchAll(PDO::FETCH_ASSOC) as $tr)$appData['teamResults'][(int)substr($tr['setting_key'],18)]=json_decode($tr['setting_value'],true);
    foreach($appData['competitions'] as &$workflowCompetition){$workflowCompetition['teamConfig']=ygo_team_load($pdo,$workflowCompetition);$workflowCompetition['workflow']=ygo_workflow_load($pdo,$workflowCompetition);$workflowCompetition['recoveryToken']=$workflowCompetition['workflow']!==null?ygo_workflow_recovery_token($workflowCompetition['workflow']):null;}unset($workflowCompetition);
    $appData['admissionSources']=ygo_admission_sources($pdo);
    foreach($appData['competitions'] as &$admissionCompetition)$admissionCompetition['admission']=ygo_admission_view($pdo,$admissionCompetition);unset($admissionCompetition);
    $appData['eventExtendedCatalog']=ygo_extended_event_catalog();
    $appData['businessEventDescriptors']=json_decode((string)file_get_contents(__DIR__.'/includes/event_business_descriptors.json'),true);
    $appData['catalogs'] = $catalogGroups;
    $appData['eventPromptTemplates'] = ygo_event_available_templates();
    $appData['competitionTypeOptions'] = $competitionTypeOptions;
    $divisions = $appData['divisions'];
    $fixtures = $appData['fixtures'];
    $allDecks = $appData['allDecks'];
    $teams = $appData['teams'];
    $competitions = $appData['competitions'];
    $competitionDependencies = $appData['competitionDependencies'];
    $cards = $appData['cards'];
    $media = $appData['media'];
    $honors = $appData['honors'];
    $players = $appData['players'];
    $playerDecks = $appData['playerDecks'];
    $audit = $appData['audit'];
    $rulesets = $appData['rulesets'];
    $rulesetVersions = $appData['rulesetVersions'];
    $matchTypes = $appData['matchTypes'];
    $gameRules = $appData['gameRules'];
    $settings = $appData['settings'];
    $uiLabels = $appData['uiLabels'] ?? $defaultUiLabels;
    $savedStatus = isset($_GET['saved']) ? (string) $_GET['saved'] : '';
    if (in_array($savedStatus, ['temporada_error','evento_temporada_bloqueada'], true)) {
        $flashMessage = (string) ($_SESSION['ygo_season_error'] ?? 'Revisa la temporada y las fechas del evento.');
        unset($_SESSION['ygo_season_error']);
        $flashClass = 'alert-warning';
    } elseif ($savedStatus === 'temporada_guardada') {
        $flashMessage = 'Temporada actualizada correctamente.';
    }
    if ($savedStatus === 'resultado_bloqueado_fecha') {
        $flashMessage = 'Resultado bloqueado: el duelo no esta habilitado para ejecutarse en esta fecha segun la configuracion actual.';
        $flashClass = 'alert-warning';
    } elseif ($savedStatus === 'resultado_bloqueado_inicio') {
        $flashMessage = 'Resultado bloqueado: primero debes lanzar los dados, definir quien comienza y confirmar Comenzar duelo.';
        $flashClass = 'alert-warning';
    } elseif ($savedStatus === 'resultado_bloqueado_rendicion') {
        $flashMessage = 'Rendicion bloqueada: solo puede rendirse el lado que tiene el turno activo.';
        $flashClass = 'alert-warning';
    } elseif (in_array($savedStatus, ['calendario_bloqueado_regla', 'partido_bloqueado_regla'], true)) {
        $flashMessage = 'Calendario bloqueado: la fecha, hora o capacidad del dia no cumple las reglas configuradas.';
        $flashClass = 'alert-warning';
    } elseif ($savedStatus === 'calendario_bloqueado_finalizado') {
        $flashMessage = 'No se cambio la fecha: un duelo finalizado conserva su programacion historica.';
        $flashClass = 'alert-warning';
    } elseif ($savedStatus === 'calendario_reprogramado') {
        $flashMessage = 'Duelo reprogramado correctamente. El calendario del evento fue actualizado.';
        $flashClass = 'alert-success';
    } elseif ($savedStatus === 'partido_bloqueado_pool') {
        $flashMessage = 'Partido bloqueado: uno o mas decks no pertenecen al pool habilitado para ese evento.';
        $flashClass = 'alert-warning';
    } elseif ($savedStatus === 'partido_invalido') {
        $flashMessage = 'No se creo el amistoso: selecciona dos decks diferentes y vuelve a intentarlo.';
        $flashClass = 'alert-warning';
    } elseif ($savedStatus === 'partido_manual') {
        $flashMessage = 'Combate amistoso creado y agregado al calendario.';
        $flashClass = 'alert-success';
    } elseif ($savedStatus === 'amistoso_eliminado') {
        $flashMessage = 'Combate amistoso eliminado junto con sus datos asociados.';
        $flashClass = 'alert-success';
    } elseif ($savedStatus === 'amistoso_no_eliminado') {
        $flashMessage = 'No se elimino el combate: solo pueden borrarse amistosos independientes creados manualmente.';
        $flashClass = 'alert-warning';
    } elseif ($savedStatus === 'deck_bloqueado_evento') {
        $flashMessage = 'Deck bloqueado: no se puede eliminar porque esta asociado a un evento activo, pendiente, pendiente de evento o finalizado.';
        $flashClass = 'alert-warning';
    } elseif ($savedStatus === 'division_minimo_4_decks') {
        $flashMessage = 'Division bloqueada: selecciona minimo 4 decks para crear o guardar una division.';
        $flashClass = 'alert-warning';
    } elseif (preg_match('/^evento_avanzado_(\d+)_(\d+)$/', $savedStatus, $match)) {
        $created = (int) $match[2];
        $flashMessage = $created > 0
            ? 'Siguiente fase creada: ' . $created . ' combates nuevos generados.'
            : 'No se creo una nueva fase: verifica que la fase anterior este finalizada y tenga ganadores.';
        $flashClass = $created > 0 ? 'alert-success' : 'alert-warning';
    } elseif (preg_match('/^divisiones_aplicadas_(\d+)_(\d+)$/', $savedStatus, $match)) {
        $moved = (int) $match[2];
        $flashMessage = $moved > 0
            ? 'Divisiones aplicadas desde clasificatorio: ' . $moved . ' decks asignados.'
            : 'No se aplicaron divisiones: falta finalizar el clasificatorio o configurar rangos validos.';
        $flashClass = $moved > 0 ? 'alert-success' : 'alert-warning';
    } elseif (preg_match('/^movimientos_liga_(\d+)_(\d+)$/', $savedStatus, $match)) {
        $moved = (int) $match[2];
        $flashMessage = $moved > 0
            ? 'Ascensos/descensos aplicados: ' . $moved . ' movimientos registrados.'
            : 'No se aplicaron movimientos: la liga no esta cerrada, no hay cupos configurados o los movimientos estan bloqueados.';
        $flashClass = $moved > 0 ? 'alert-success' : 'alert-warning';
    } elseif (preg_match('/^ranking_recalculado_(\d+)_(\d+)$/', $savedStatus, $match)) {
        $ok = (int) $match[2];
        $flashMessage = $ok > 0
            ? 'Ranking recalculado: snapshots general y por fase actualizados.'
            : 'No se recalculo ranking: el evento no existe o no esta activo.';
        $flashClass = $ok > 0 ? 'alert-success' : 'alert-warning';
    } elseif (preg_match('/^qa_codex_archivado_(\d+)$/', $savedStatus, $match)) {
        $count = (int) $match[1];
        $flashMessage = $count > 0
            ? 'Datos QA Codex archivados: ' . $count . ' registros marcados como inactivos.'
            : 'No habia datos QA Codex activos para archivar.';
        $flashClass = $count > 0 ? 'alert-success' : 'alert-warning';
    } elseif ($savedStatus !== '') {
        $flashMessage = 'Cambio guardado: ' . $savedStatus;
        $flashClass = 'alert-success';
    }
} catch (Throwable $error) {
    if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    $dbStatus = [
        'connected' => false,
        'message' => 'No se pudo conectar a la base de datos en este entorno',
        'counts' => [],
    ];
    $appData = ['eventExtendedCatalog'=>ygo_extended_event_catalog(), 'executionCapabilities'=>ygo_execution_capabilities(), 'executionModelsByType'=>array_combine(array_keys($competitionTypeOptions),array_map('ygo_allowed_execution_models_for_type',array_keys($competitionTypeOptions))), 'divisions' => [], 'fixtures' => [], 'allDecks' => [], 'teams' => [], 'competitions' => [], 'competitionDependencies' => [], 'statusHistory' => [], 'standingSnapshots' => [], 'cards' => [], 'media' => [], 'logoHistory' => [], 'honors' => [], 'audit' => [], 'events' => [], 'rulesets' => [], 'rulesetVersions' => [], 'matchTypes' => [], 'gameRules' => [], 'gameRuleConditions' => [], 'eventPromptTemplates' => ygo_event_available_templates(), 'competitionTypeOptions' => $competitionTypeOptions, 'settings' => ygo_default_settings(), 'uiLabels' => $defaultUiLabels, 'serverToday' => date('Y-m-d')];
    $divisions = [];
    $fixtures = [];
    $allDecks = [];
    $teams = [];
    $competitions = [];
    $competitionDependencies = [];
    $cards = [];
    $media = [];
    $honors = [];
    $audit = [];
    $rulesets = [];
    $rulesetVersions = [];
    $matchTypes = [];
    $gameRules = [];
    $settings = ygo_default_settings();
    $uiLabels = $defaultUiLabels;
}

$uiLabels = $uiLabels ?? ($appData['uiLabels'] ?? $defaultUiLabels);
$uiText = static function (string $key, string $field, string $fallback) use ($uiLabels): string {
    if (isset($uiLabels[$key]['active']) && (int) $uiLabels[$key]['active'] !== 1) {
        return $fallback;
    }
    $value = trim((string) ($uiLabels[$key][$field] ?? ''));
    return $value !== '' ? $value : $fallback;
};
$appData['dbStatus'] = $dbStatus;
$appData['eventLogoGallery'] = ygo_event_logo_gallery();
$displayDivisions = array_filter(
    $divisions,
    static fn(array $division): bool => (int) ($division['id'] ?? 0) > 0 && (int) ($division['active'] ?? 1) === 1
);
$uniqueDisplayDivisions = [];
foreach ($displayDivisions as $divisionKey => $division) {
    $uniqueKey = (string) (($division['code'] ?? '') ?: ($division['short'] ?? '') ?: $divisionKey);
    if ($uniqueKey === '') {
        $uniqueKey = (string) $divisionKey;
    }
    if (!isset($uniqueDisplayDivisions[$uniqueKey])) {
        $uniqueDisplayDivisions[$uniqueKey] = $division;
    }
}
$displayDivisions = $uniqueDisplayDivisions;
$deckTeamByName = [];
$appData['presentationLabels'] = ygo_ui_labels_catalog($divisions, $competitions);
$presentationLabels = $appData['presentationLabels'];
foreach ($allDecks as $deck) {
    $deckTeamByName[(string) ($deck['name'] ?? '')] = (string) (($deck['team'] ?? '') ?: 'Sin equipo');
}
$deckMatchLabel = static function (string $deckName) use ($deckTeamByName): string {
    return (($deckTeamByName[$deckName] ?? 'Sin equipo') ?: 'Sin equipo') . ' / ' . $deckName;
};
$matchOptions = [];
foreach ($fixtures as $code => $rounds) {
    foreach ($rounds as $round) {
        foreach ($round['matches'] as $match) {
            $matchOptions[] = [
                'id' => (int) ($match['id'] ?? 0),
                'label' => ygo_ui_match_label($match, (string)$code, $round['ronda'], $presentationLabels) . ': ' . $deckMatchLabel((string) $match['a']) . ' vs ' . $deckMatchLabel((string) $match['b']),
            ];
        }
    }
}

$qualifierCompetition = null;
$qualifierCompetitions = [];
foreach ($competitions as $competition) {
    if (($competition['type'] ?? '') === 'qualifier') {
        $qualifierCompetitions[] = $competition;
        if ($qualifierCompetition === null) {
            $qualifierCompetition = $competition;
        }
    }
}
$pendingEventQualifiers = array_values(array_filter($qualifierCompetitions, 'ygo_is_pending_event_qualifier_candidate'));
$activeQualifierCompetition = null;
foreach ($qualifierCompetitions as $competition) {
    $statusKey = ygo_normalize_status_key((string) ($competition['status'] ?? ''));
    if (in_array($statusKey, ['active', 'activo', 'en_curso'], true) && (($competition['can_execute'] ?? true) !== false)) {
        $activeQualifierCompetition = $competition;
        break;
    }
}
$requestedQualifierId = max(0, (int) ($_GET['event_id'] ?? 0));
$requestedQualifierCompetition = null;
foreach ($qualifierCompetitions as $competition) {
    if ((int) ($competition['id'] ?? 0) === $requestedQualifierId) {
        $requestedQualifierCompetition = $competition;
        break;
    }
}
$showQualifierDetail = $requestedQualifierCompetition !== null;
$qualifierCompetitionIdMap = array_fill_keys(array_map(static fn(array $competition): int => (int) ($competition['id'] ?? 0), $qualifierCompetitions), true);
$qualifierStatsByCompetition = [];
foreach ($fixtures as $rounds) {
    foreach ($rounds as $round) {
        foreach (($round['matches'] ?? []) as $match) {
            $competitionId = (int) ($match['competition_id'] ?? 0);
            if ($competitionId <= 0 || !isset($qualifierCompetitionIdMap[$competitionId])) {
                continue;
            }
            if (!isset($qualifierStatsByCompetition[$competitionId])) {
                $qualifierStatsByCompetition[$competitionId] = ['matches' => 0, 'finished' => 0];
            }
            $qualifierStatsByCompetition[$competitionId]['matches']++;
            if ((string) ($match['estado'] ?? '') === 'FINALIZADA' || !empty($match['score'])) {
                $qualifierStatsByCompetition[$competitionId]['finished']++;
            }
        }
    }
}
$displayQualifierCompetition = $requestedQualifierCompetition ?? $activeQualifierCompetition ?? $qualifierCompetition;
$displayQualifierId = (int) ($displayQualifierCompetition['id'] ?? 0);
$displayQualifierSelectedIds = array_map('intval', (array) ($displayQualifierCompetition['selected_deck_ids'] ?? []));
$displayQualifierSelectedMap = array_fill_keys($displayQualifierSelectedIds, true);
$displayQualifierDecks = $displayQualifierSelectedIds
    ? array_values(array_filter($allDecks, fn(array $deck): bool => isset($displayQualifierSelectedMap[(int) ($deck['id'] ?? 0)])))
    : [];
$displayQualifierRequiredDuels = max(2, min(5, (int) (($displayQualifierCompetition['qualifier_duels_per_deck'] ?? 0) ?: 3)));
$displayQualifierMatchesPerDay = max(1, min(5, (int) (($displayQualifierCompetition['qualifier_matches_per_day'] ?? 0) ?: 1)));
$displayQualifierMatches = [];
foreach (($fixtures['CLASIFICATORIO'] ?? []) as $round) {
    foreach (($round['matches'] ?? []) as $match) {
        if ($displayQualifierId > 0 && (int) ($match['competition_id'] ?? 0) !== $displayQualifierId) {
            continue;
        }
        $displayQualifierMatches[] = $match + ['round_number' => (int) ($round['ronda'] ?? 0)];
    }
}
usort($displayQualifierMatches, static function (array $a, array $b): int {
    return strcmp((string) ($a['scheduled_on'] ?? '9999-12-31'), (string) ($b['scheduled_on'] ?? '9999-12-31'))
        ?: strcmp((string) ($a['starts_at'] ?? '23:59:59'), (string) ($b['starts_at'] ?? '23:59:59'))
        ?: ((int) ($a['round_number'] ?? 0) <=> (int) ($b['round_number'] ?? 0))
        ?: ((int) ($a['mesa'] ?? 0) <=> (int) ($b['mesa'] ?? 0));
});
$displayQualifierRoundCount = count(array_unique(array_map(static fn(array $match): int => (int) ($match['round_number'] ?? 0), $displayQualifierMatches)));
$displayQualifierScheduledCount = count(array_filter($displayQualifierMatches, static fn(array $match): bool => !empty($match['scheduled_on'])));
$displayQualifierFinishedCount = count(array_filter($displayQualifierMatches, static fn(array $match): bool => (($match['estado'] ?? '') === 'FINALIZADA') || !empty($match['score'])));
$qualifierStatus = ygo_normalize_status_key((string) ($displayQualifierCompetition['status'] ?? 'draft'));
$qualifierFinished = in_array($qualifierStatus, ['completed', 'finalizada', 'finalizado', 'closed', 'archived'], true);
$qualifierStatusLabel = $qualifierFinished ? 'Finalizado' : ($qualifierStatus === 'pending_event' ? 'Pendiente evento' : (in_array($qualifierStatus, ['active', 'activo', 'en_curso'], true) ? 'Activo' : 'Pendiente configuracion'));
$divisionModeLabel = $qualifierFinished ? 'Formatos oficiales' : 'Formatos pendientes del clasificatorio';
$cupEventTypes = ['cup', 'final'];
$cupCompetitions = array_values(array_filter($competitions, static fn(array $competition): bool => in_array((string) ($competition['type'] ?? ''), $cupEventTypes, true)));
$cupMatchesByCompetition = [];
$cupStatsByCompetition = [];
foreach ($fixtures as $rounds) {
    foreach ($rounds as $round) {
        foreach (($round['matches'] ?? []) as $match) {
            $competitionId = (int) ($match['competition_id'] ?? 0);
            if ($competitionId <= 0) {
                continue;
            }
            $phase = (string) (($match['phase'] ?? '') ?: '');
            if (!in_array($phase, ['cup', 'final'], true)) {
                continue;
            }
            $cupMatchesByCompetition[$competitionId][] = $match + ['round_number' => (int) ($round['ronda'] ?? 0)];
            if (!isset($cupStatsByCompetition[$competitionId])) {
                $cupStatsByCompetition[$competitionId] = ['total' => 0, 'scheduled' => 0, 'finalized' => 0, 'pending' => 0, 'dates' => []];
            }
            $isFinalized = (($match['estado'] ?? '') === 'FINALIZADA') || !empty($match['score']);
            $hasDate = !empty($match['scheduled_on']);
            $cupStatsByCompetition[$competitionId]['total']++;
            $cupStatsByCompetition[$competitionId][$isFinalized ? 'finalized' : 'pending']++;
            if ($hasDate) {
                $cupStatsByCompetition[$competitionId]['scheduled']++;
                $cupStatsByCompetition[$competitionId]['dates'][(string) $match['scheduled_on']] = true;
            }
        }
    }
}
foreach ($cupMatchesByCompetition as &$cupMatches) {
    usort($cupMatches, static fn(array $left, array $right): int =>
        ((int) ($left['display_order'] ?? 0) <=> (int) ($right['display_order'] ?? 0))
        ?: ((int) ($left['round_number'] ?? 0) <=> (int) ($right['round_number'] ?? 0))
        ?: ((int) ($left['mesa'] ?? 0) <=> (int) ($right['mesa'] ?? 0))
    );
}
unset($cupMatches);
$cupActiveCount = count(array_filter($cupCompetitions, static fn(array $competition): bool => in_array(ygo_normalize_status_key((string) ($competition['status'] ?? '')), ['active', 'activo', 'en_curso'], true)));
$cupCompletedCount = count(array_filter($cupCompetitions, static fn(array $competition): bool => in_array(ygo_normalize_status_key((string) ($competition['status'] ?? '')), ['completed', 'finalized', 'finalizado', 'finalizada', 'cancelled', 'canceled', 'anulado', 'anulada'], true)));
$cupTotalMatches = array_sum(array_map(static fn(array $stats): int => (int) ($stats['total'] ?? 0), $cupStatsByCompetition));
$featuredCupCompetition = $cupCompetitions[0] ?? null;
$worldCompetitions = array_values(array_filter($competitions, static fn(array $competition): bool => (string) ($competition['type'] ?? '') === 'world'));
$worldMatchesByCompetition = [];
$worldStatsByCompetition = [];
foreach ($fixtures as $rounds) {
    foreach ($rounds as $round) {
        foreach (($round['matches'] ?? []) as $match) {
            $competitionId = (int) ($match['competition_id'] ?? 0);
            if ($competitionId <= 0 || (string) (($match['phase'] ?? '') ?: '') !== 'world') {
                continue;
            }
            $worldMatchesByCompetition[$competitionId][] = $match + ['round_number' => (int) ($round['ronda'] ?? 0)];
            if (!isset($worldStatsByCompetition[$competitionId])) {
                $worldStatsByCompetition[$competitionId] = ['total' => 0, 'scheduled' => 0, 'finalized' => 0, 'pending' => 0];
            }
            $isFinalized = (($match['estado'] ?? '') === 'FINALIZADA') || !empty($match['score']);
            $worldStatsByCompetition[$competitionId]['total']++;
            $worldStatsByCompetition[$competitionId][$isFinalized ? 'finalized' : 'pending']++;
            if (!empty($match['scheduled_on'])) {
                $worldStatsByCompetition[$competitionId]['scheduled']++;
            }
        }
    }
}
$worldActiveCount = count(array_filter($worldCompetitions, static fn(array $competition): bool => in_array(ygo_normalize_status_key((string) ($competition['status'] ?? '')), ['active', 'activo', 'en_curso'], true)));
$worldTotalMatches = array_sum(array_map(static fn(array $stats): int => (int) ($stats['total'] ?? 0), $worldStatsByCompetition));
$eventSummaryByType = [];
$eventSummaryByRuleset = [];
$eventSummaryByStatus = [];
foreach ($competitions as $competition) {
    $type = (string) (($competition['type'] ?? '') ?: 'special');
    $ruleset = (string) (($competition['event_ruleset'] ?? '') ?: 'custom');
    $statusKey = (string) (($competition['status'] ?? '') ?: 'draft');
    $eventSummaryByType[$type] = ($eventSummaryByType[$type] ?? 0) + 1;
    $eventSummaryByRuleset[$ruleset] = ($eventSummaryByRuleset[$ruleset] ?? 0) + 1;
    $eventSummaryByStatus[$statusKey] = ($eventSummaryByStatus[$statusKey] ?? 0) + 1;
}
$competitionById = [];
foreach ($competitions as $competition) {
    $competitionById[(int) ($competition['id'] ?? 0)] = $competition;
}
$dependencyFlow = [];
foreach ($competitions as $competition) {
    foreach (($competition['dependencies'] ?? []) as $dependency) {
        $dependencyFlow[] = [
            'from' => (string) ($dependency['name'] ?? 'Evento previo'),
            'to' => (string) ($competition['name'] ?? 'Evento'),
            'ruleset' => (string) (($competition['event_ruleset'] ?? '') ?: 'custom'),
            'ok' => !empty($dependency['ok']),
            'status' => (string) ($dependency['status'] ?? 'draft'),
            'condition' => (string) ($dependency['condition_type'] ?? 'completed_with_results'),
        ];
    }
}
$rulesetSelectOptions = [];
foreach ($rulesets as $ruleset) {
    $rulesetSelectOptions[(string) ($ruleset['code'] ?? '')] = (string) ($ruleset['label'] ?? $ruleset['code'] ?? '');
}
if (!$rulesetSelectOptions) {
    $rulesetSelectOptions = $eventRulesetOptions;
}
$formatSelectOptions = array_filter(
    $rulesetSelectOptions,
    static fn(string $label, string $code): bool => $code !== '' && $code !== 'custom',
    ARRAY_FILTER_USE_BOTH
);
$deckFormatCodesById = [];
if (!empty($dbStatus['connected']) && isset($pdo)) {
    foreach ($formatSelectOptions as $formatCode => $formatLabel) {
        foreach (ygo_deck_ids_for_ruleset($pdo, (string) $formatCode) as $formatDeckId) {
            $deckFormatCodesById[$formatDeckId][] = (string) $formatCode;
        }
    }
}
foreach ($allDecks as &$formatDeck) {
    $formatDeck['format_codes'] = array_values(array_unique($deckFormatCodesById[(int) ($formatDeck['id'] ?? 0)] ?? []));
}
unset($formatDeck);
$appData['allDecks'] = $allDecks;
$appData['leagueDeckAssignments'] = !empty($dbStatus['connected']) && isset($pdo) ? ygo_league_deck_assignments($pdo) : [];
$appData['currentSeasonId'] = $dbStatus['connected'] ? ygo_current_season_id($pdo) : 0;
$rulesetVersionCatalog = $rulesetVersions ?: ygo_ruleset_version_catalog();
$matchTypeOptions = $matchTypes ?: [
    ['code' => '1v1', 'label' => '1v1', 'participants_per_side' => 1],
    ['code' => '2v2', 'label' => '2v2', 'participants_per_side' => 2],
    ['code' => '3v3', 'label' => '3v3', 'participants_per_side' => 3],
];
$todayIso = date('Y-m-d');
$currentEventsByFormat = [];
foreach ($competitions as $competition) {
    $status = strtolower((string) ($competition['status'] ?? 'draft'));
    $starts = (string) ($competition['starts_on'] ?? '');
    $ends = (string) ($competition['ends_on'] ?? '');
    if (!in_array($status, ['active', 'en_curso', 'activo'], true)) {
        continue;
    }
    $formatKey = (string) (($competition['division_code'] ?? '') ?: (($eventRulesetOptions[(string) ($competition['event_ruleset'] ?? '')] ?? '') ?: 'General'));
    $currentEventsByFormat[$formatKey][] = $competition;
}
$recentResults = [];
foreach ($fixtures as $code => $rounds) {
    foreach ($rounds as $round) {
        foreach (($round['matches'] ?? []) as $match) {
            if (($match['estado'] ?? '') !== 'FINALIZADA' && empty($match['score'])) {
                continue;
            }
            $recentResults[] = [
                'date' => (string) (($match['scheduled_on'] ?? '') ?: $todayIso),
                'label' => ygo_ui_match_label($match, (string)$code, $round['ronda'] ?? '-', $presentationLabels),
                'versus' => (string) (($match['a'] ?? 'Deck A') . ' vs ' . ($match['b'] ?? 'Deck B')),
                'score' => (string) (($match['score'] ?? '') ?: '0-0'),
                'status' => (string) (($match['estado'] ?? '') ?: 'FINALIZADA'),
            ];
        }
    }
}
usort($recentResults, static fn(array $a, array $b): int => strcmp($b['date'], $a['date']));
$recentResults = array_slice($recentResults, 0, 6);
$activeDashboardEvents = [];
foreach ($currentEventsByFormat as $formatEvents) {
    foreach ($formatEvents as $event) {
        $activeDashboardEvents[] = $event;
    }
}
$activeEventCount = count($activeDashboardEvents);
$totalFixtureMatches = array_sum(array_map(static fn(array $rounds): int => array_sum(array_map(static fn(array $round): int => count($round['matches'] ?? []), $rounds)), $fixtures));
$totalFinalizedMatches = array_sum(array_map(static fn(array $rounds): int => array_sum(array_map(static fn(array $round): int => count(array_filter($round['matches'] ?? [], static fn(array $match): bool => (($match['estado'] ?? '') === 'FINALIZADA') || !empty($match['score']))), $rounds)), $fixtures));
$pendingConfigEventCount = count(array_filter($competitions, static fn(array $competition): bool => ygo_normalize_status_key((string) ($competition['status'] ?? '')) === 'pending_configuration'));
$pendingEventCount = count(array_filter($competitions, static fn(array $competition): bool => ygo_normalize_status_key((string) ($competition['status'] ?? '')) === 'pending_event'));
$completedEventCount = count(array_filter($competitions, static fn(array $competition): bool => in_array(ygo_normalize_status_key((string) ($competition['status'] ?? '')), ['completed', 'finalized', 'finalizado', 'finalizada'], true)));
$cancelledEventCount = count(array_filter($competitions, static fn(array $competition): bool => in_array(ygo_normalize_status_key((string) ($competition['status'] ?? '')), ['cancelled', 'canceled', 'anulado', 'anulada'], true)));
$activeGameRuleCount = count(array_filter($gameRules, static fn(array $rule): bool => !empty($rule['active'])));
$deckWithTeamCount = count(array_filter($allDecks, static fn(array $deck): bool => !empty($deck['team_id'])));
$deckWithoutTeamCount = max(0, count($allDecks) - $deckWithTeamCount);
$deckWithMediaCount = count(array_filter($allDecks, static fn(array $deck): bool => !empty($deck['cover_url']) || !empty($deck['key_cards'])));
$eventsWithoutDateCount = count(array_filter($competitions, static fn(array $competition): bool => empty($competition['starts_on'])));
$scheduledFixtureMatches = array_sum(array_map(static fn(array $rounds): int => array_sum(array_map(static fn(array $round): int => count(array_filter($round['matches'] ?? [], static fn(array $match): bool => !empty($match['scheduled_on']))), $rounds)), $fixtures));
$nextDashboardEvent = $activeDashboardEvents[0] ?? ($competitions[0] ?? null);
$dashboardSeasonName = '2027 B';
foreach ($seasons as $dashboardSeason) {
    if (($dashboardSeason['status'] ?? '') === 'active' && trim((string) ($dashboardSeason['name'] ?? '')) !== '') {
        $dashboardSeasonName = trim((string) $dashboardSeason['name']);
        break;
    }
}
$dashboardMatches = [];
$dashboardMatchStatsByCompetition = [];
$dashboardMatchStatsByPhase = [];
$dashboardDeckUsage = [];
foreach ($fixtures as $code => $rounds) {
    foreach ($rounds as $round) {
        foreach (($round['matches'] ?? []) as $match) {
            $competitionId = (int) ($match['competition_id'] ?? 0);
            $phaseKey = (string) (($match['phase'] ?? '') ?: strtolower((string) $code));
            $isFinalized = (($match['estado'] ?? '') === 'FINALIZADA') || !empty($match['score']);
            if (!isset($dashboardMatchStatsByPhase[$phaseKey])) {
                $dashboardMatchStatsByPhase[$phaseKey] = ['total' => 0, 'finalized' => 0, 'pending' => 0];
            }
            $dashboardMatchStatsByPhase[$phaseKey]['total']++;
            $dashboardMatchStatsByPhase[$phaseKey][$isFinalized ? 'finalized' : 'pending']++;
            if ($competitionId > 0) {
                if (!isset($dashboardMatchStatsByCompetition[$competitionId])) {
                    $dashboardMatchStatsByCompetition[$competitionId] = ['total' => 0, 'finalized' => 0, 'pending' => 0];
                }
                $dashboardMatchStatsByCompetition[$competitionId]['total']++;
                $dashboardMatchStatsByCompetition[$competitionId][$isFinalized ? 'finalized' : 'pending']++;
            }
            foreach (['a', 'b'] as $side) {
                $deckName = (string) ($match[$side] ?? '');
                if ($deckName !== '') {
                    $dashboardDeckUsage[$deckName] = ($dashboardDeckUsage[$deckName] ?? 0) + 1;
                }
            }
            $dashboardMatches[] = [
                'id' => (int) ($match['id'] ?? 0),
                'date' => (string) (($match['scheduled_on'] ?? '') ?: ''),
                'time' => (string) (($match['starts_at'] ?? '') ?: ''),
                'label' => ygo_ui_match_label($match, (string)$code, $round['ronda'] ?? '-', $presentationLabels),
                'versus' => $deckMatchLabel((string) ($match['a'] ?? 'Deck A')) . ' vs ' . $deckMatchLabel((string) ($match['b'] ?? 'Deck B')),
                'status' => (string) (($match['estado'] ?? '') ?: 'PENDIENTE'),
                'score' => (string) (($match['score'] ?? '') ?: ''),
                'competition_id' => $competitionId,
            ];
        }
    }
}
usort($dashboardMatches, static fn(array $a, array $b): int => strcmp(($a['date'] ?: '2999-12-31') . ' ' . ($a['time'] ?: '23:59:59'), ($b['date'] ?: '2999-12-31') . ' ' . ($b['time'] ?: '23:59:59')));
$upcomingDashboardMatches = array_slice(array_values(array_filter($dashboardMatches, static fn(array $match): bool => ($match['date'] ?? '') === date('Y-m-d'))), 0, 8);
$teamDeckCounts = [];
$teamDashboardLogos = [];
foreach ($allDecks as $deck) {
    $teamName = (string) (($deck['team'] ?? '') ?: 'Sin equipo');
    $teamDeckCounts[$teamName] = ($teamDeckCounts[$teamName] ?? 0) + 1;
    $deckLogo = ygo_media_src((string) (($deck['team_logo'] ?? '') ?: (($deck['cover_url'] ?? '') ?: ($deck['logo_url'] ?? ''))));
    if ($deckLogo !== '' && empty($teamDashboardLogos[$teamName])) {
        $teamDashboardLogos[$teamName] = $deckLogo;
    }
}
$dashboardLiveRanking = [];
$dashboardRankingSource = '';
foreach ($activeDashboardEvents as $event) {
    $ranking = array_values(array_filter((array) ($event['ranking'] ?? []), static fn(array $row): bool => (int) ($row['pj'] ?? 0) > 0));
    if ($ranking) {
        $dashboardLiveRanking = array_slice($ranking, 0, 8);
        $dashboardRankingSource = (string) ($event['name'] ?? 'Evento activo');
        break;
    }
}
$topDashboardDecks = array_slice($allDecks, 0, 10);
$topDashboardTeamPool = array_map(static function (array $team) use ($teamDashboardLogos): array {
    $teamName = (string) ($team['name'] ?? '');
    $team['logo_url'] = ygo_media_src((string) (($team['logo_url'] ?? '') ?: ($teamDashboardLogos[$teamName] ?? '')));
    return $team;
}, $teams);
usort($topDashboardTeamPool, static function (array $a, array $b) use ($teamDeckCounts): int {
    $aLogo = trim((string) ($a['logo_url'] ?? '')) !== '' ? 1 : 0;
    $bLogo = trim((string) ($b['logo_url'] ?? '')) !== '' ? 1 : 0;
    $aDecks = (int) ($teamDeckCounts[(string) ($a['name'] ?? '')] ?? 0);
    $bDecks = (int) ($teamDeckCounts[(string) ($b['name'] ?? '')] ?? 0);
    return $bLogo <=> $aLogo ?: $bDecks <=> $aDecks ?: strcmp((string) ($a['name'] ?? ''), (string) ($b['name'] ?? ''));
});
$topDashboardTeams = array_slice($topDashboardTeamPool, 0, 8);
$qualifierPairings = [];
$deckCount = count($displayQualifierDecks);
if ($deckCount > 1) {
    foreach ($displayQualifierDecks as $index => $deck) {
        $rivals = [];
        for ($offset = 1; $offset <= $displayQualifierRequiredDuels; $offset++) {
            $rival = $displayQualifierDecks[($index + $offset) % $deckCount];
            if ($rival['name'] !== $deck['name']) {
                $rivals[] = $rival['name'];
            }
        }
        $qualifierPairings[] = ['deck' => $deck['name'], 'team' => $deck['team'] ?? 'Sin equipo', 'rivals' => array_slice(array_values(array_unique($rivals)), 0, $displayQualifierRequiredDuels)];
    }
}
$qualifierProgress = [];
$displayQualifierRanking = array_values((array) ($displayQualifierCompetition['ranking'] ?? []));
$displayQualifierRankingByDeckId = [];
foreach ($displayQualifierRanking as $position => $standingRow) {
    $displayQualifierRankingByDeckId[(int) ($standingRow['id'] ?? 0)] = $standingRow + ['position' => $position + 1];
}
foreach ($displayQualifierDecks as $deck) {
    $deckId = (int) ($deck['id'] ?? 0);
    $standing = $displayQualifierRankingByDeckId[$deckId] ?? [];
    $qualifierProgress[$deck['name']] = [
        'rank' => (int) ($standing['position'] ?? $deck['rank']),
        'seed_rank' => (int) ($deck['rank'] ?? 0),
        'deck_id' => $deckId,
        'deck' => $deck['name'],
        'team' => $deck['team'] ?? 'Sin equipo',
        'code' => $deck['team_code'] ?? '',
        'team_logo' => ygo_media_src((string) ($deck['team_logo'] ?? '')),
        'division' => $deck['division'],
        'pts' => (int) ($standing['pts'] ?? 0),
        'pj' => (int) ($standing['pj'] ?? 0),
        'pg' => (int) ($standing['pg'] ?? 0),
        'pe' => (int) ($standing['pe'] ?? 0),
        'pp' => (int) ($standing['pp'] ?? 0),
        'cg' => (int) ($standing['cg'] ?? 0),
        'cp' => (int) ($standing['cp'] ?? 0),
        'dp' => (int) ($standing['dp'] ?? 0),
        'played' => 0,
        'scheduled' => 0,
        'opponents' => [],
        'ready' => false,
    ];
}
foreach (($fixtures['CLASIFICATORIO'] ?? []) as $round) {
    foreach (($round['matches'] ?? []) as $match) {
        if ($displayQualifierId > 0 && (int) ($match['competition_id'] ?? 0) !== $displayQualifierId) {
            continue;
        }
        foreach ([[$match['a'] ?? '', $match['b'] ?? ''], [$match['b'] ?? '', $match['a'] ?? '']] as $pair) {
            [$deckName, $opponentName] = $pair;
            if (!isset($qualifierProgress[$deckName])) {
                continue;
            }
            if (!empty($match['scheduled_on'])) {
                $qualifierProgress[$deckName]['scheduled']++;
            }
            if (($match['estado'] ?? '') === 'FINALIZADA') {
                $qualifierProgress[$deckName]['played']++;
                if ($opponentName !== '') {
                    $qualifierProgress[$deckName]['opponents'][$opponentName] = true;
                }
            }
        }
    }
}
foreach ($qualifierProgress as &$progressRow) {
    $progressRow['opponent_count'] = count($progressRow['opponents']);
    $progressRow['ready'] = $progressRow['played'] >= $displayQualifierRequiredDuels;
    $progressRow['remaining'] = max(0, $displayQualifierRequiredDuels - $progressRow['played']);
}
unset($progressRow);
usort($qualifierProgress, static fn(array $a, array $b): int =>
    $b['pts'] <=> $a['pts']
    ?: $b['dp'] <=> $a['dp']
    ?: $b['cg'] <=> $a['cg']
    ?: $a['seed_rank'] <=> $b['seed_rank']
);
foreach ($qualifierProgress as $position => &$progressRow) {
    $progressRow['rank'] = $position + 1;
}
unset($progressRow);
$qualifierReadyCount = count(array_filter($qualifierProgress, static fn(array $row): bool => $row['ready']));
$qualifierFinalizeCheck = ['ok' => false, 'reasons' => ['Conecta la base de datos para validar el cierre.']];
if ($dbStatus['connected'] && isset($pdo)) {
    try {
        $qualifierFinalizeCheck = ygo_qualifier_finalize_check($pdo, $displayQualifierId);
    } catch (Throwable $error) {
        $qualifierFinalizeCheck = ['ok' => false, 'reasons' => ['No se pudo validar el cierre del clasificatorio.']];
    }
}
$auditSummary = [];
foreach ($audit ?? [] as $auditRow) {
    $action = (string) ($auditRow['action'] ?? 'accion');
    $auditSummary[$action] = ($auditSummary[$action] ?? 0) + 1;
}
arsort($auditSummary);

$duelPlayerSide = strtoupper((string) ($_GET['duel_player'] ?? ''));
if ($dbStatus['connected'] && in_array($duelPlayerSide, ['A', 'B'], true)) {
    $duelPlayerMatchId = max(0, (int) ($_GET['match_id'] ?? 0));
    $duelPlayerMatch = $duelPlayerMatchId > 0 ? ygo_live_duel_match($pdo, $duelPlayerMatchId) : [];
    if (!$duelPlayerMatch) {
        http_response_code(404);
    }
    $sideKey = strtolower($duelPlayerSide);
    $opponentSide = $duelPlayerSide === 'A' ? 'B' : 'A';
    $ownTeam = (string) ($duelPlayerMatch['team_' . $sideKey] ?? ('Equipo ' . $duelPlayerSide));
    $ownDeck = (string) ($duelPlayerMatch['deck_' . $sideKey] ?? ('Deck ' . $duelPlayerSide));
    $ownLogo = ygo_media_src((string) ($duelPlayerMatch['logo_' . $sideKey] ?? ''));
    header('Content-Type: text/html; charset=utf-8');
    ?><!doctype html>
    <html lang="es" data-duel-player-side="<?php echo e($duelPlayerSide); ?>" data-duel-player-match="<?php echo $duelPlayerMatchId; ?>">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover">
        <meta name="theme-color" content="#06111f">
        <title><?php echo e($ownTeam); ?> | Pantalla de duelo</title>
        <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
        <link href="./assets/player-duel.css?v=20260927-surrender-calc-1" rel="stylesheet">
    </head>
    <body>
    <main class="player-duel-shell" data-player-duel-root>
        <header class="player-duel-header">
            <div class="player-duel-brand"><?php if ($ownLogo !== ''): ?><img src="<?php echo e($ownLogo); ?>" alt="Logo de <?php echo e($ownTeam); ?>"><?php else: ?><span><?php echo e($duelPlayerSide); ?></span><?php endif; ?></div>
            <div><small>Pantalla del lado <?php echo e($duelPlayerSide); ?></small><h1><?php echo e($ownTeam); ?></h1><p><?php echo e($ownDeck); ?></p></div>
            <span class="player-sync is-waiting" data-player-sync><i></i> Conectando</span>
        </header>
        <?php if (!$duelPlayerMatch): ?>
            <section class="player-message"><h2>Partida no encontrada</h2><p>Revisa el enlace proporcionado por el juez.</p></section>
        <?php else: ?>
            <section class="player-match-meta"><span><?php echo e((string) ($duelPlayerMatch['competition_name'] ?? 'Partida')); ?></span><b>R<?php echo (int) ($duelPlayerMatch['round_number'] ?? 0); ?> · Mesa <?php echo (int) ($duelPlayerMatch['table_number'] ?? 0); ?></b><strong data-player-duel-number>D1</strong></section>
            <section class="player-turn-panel" data-player-turn-panel>
                <small>Turno actual</small><h2 data-player-turn-label>Esperando al juez</h2><p data-player-turn-detail>El duelo todavía no ha comenzado.</p>
                <button type="button" class="player-primary" data-player-end-turn disabled><i class="bi bi-check2-circle"></i><span>Terminar mi turno</span></button>
                <button type="button" class="player-surrender" data-player-surrender disabled><i class="bi bi-flag-fill"></i><span>Rendirme</span></button>
            </section>
            <section class="player-scoreboard">
                <article class="is-own"><small>Mis LP</small><strong data-player-lp-own>8000</strong><span><?php echo e($ownTeam); ?></span></article>
                <b>VS</b>
                <article><small>LP rival</small><strong data-player-lp-rival>8000</strong><span><?php echo e((string) ($duelPlayerMatch['team_' . strtolower($opponentSide)] ?? ('Equipo ' . $opponentSide))); ?></span></article>
            </section>
            <section class="player-tools">
                <header><div><small>Herramienta personal</small><h2>Calculadora de LP</h2></div><strong data-player-entry>0</strong></header>
                <div class="player-keypad"><?php foreach (['7','8','9','4','5','6','1','2','3','0','00','000'] as $key): ?><button type="button" data-player-key="<?php echo e($key); ?>"><?php echo e($key); ?></button><?php endforeach; ?></div>
                <div class="player-operations">
                    <button type="button" data-player-lp-op="clear"><i class="bi bi-backspace"></i><span>Borrar</span></button>
                    <button type="button" data-player-lp-op="subtract"><i class="bi bi-dash-lg"></i><span>Restar</span></button>
                    <button type="button" data-player-lp-op="add"><i class="bi bi-plus-lg"></i><span>Sumar</span></button>
                    <button type="button" data-player-lp-op="half"><b>1/2</b><span>Mitad</span></button>
                    <button type="button" data-player-lp-op="reset"><i class="bi bi-arrow-repeat"></i><span>Inicial</span></button>
                </div>
            </section>
            <section class="player-tools player-standard-tool">
                <header><div><small>Calculo auxiliar</small><h2>Calculadora estandar</h2></div><strong data-player-standard-display>0</strong></header>
                <div class="player-standard-keypad">
                    <?php foreach (['7','8','9','divide','4','5','6','multiply','1','2','3','subtract','clear','0','equals','add'] as $key): ?>
                        <?php $label = ['divide' => '&divide;', 'multiply' => '&times;', 'subtract' => '&minus;', 'add' => '+', 'clear' => 'C', 'equals' => '='][$key] ?? $key; ?>
                        <button type="button" data-player-standard-key="<?php echo e($key); ?>"><?php echo $label; ?></button>
                    <?php endforeach; ?>
                </div>
                <button type="button" class="player-use-standard" data-player-standard-use>Usar resultado como valor LP</button>
            </section>
            <section class="player-status-grid">
                <article><small>Tiempo</small><strong data-player-clock>Sin reloj</strong></article>
                <article><small>Turnos completados</small><strong data-player-turn-count>0</strong></article>
                <article class="player-dice-status"><small>Dados e inicio</small><strong data-player-dice>Esperando definición</strong></article>
            </section>
            <div class="player-toast" data-player-toast hidden></div>
        <?php endif; ?>
    </main>
    <?php if ($duelPlayerMatch): ?><script src="./assets/player-duel.js?v=20260927-surrender-calc-1" defer></script><?php endif; ?>
    <?php echo ygo_ui_labels_script($presentationLabels); ?>
    </body></html><?php
    exit;
}

if($dbStatus['connected']&&(string)($_GET['api']??'')==='ffa_history'){
    $q=$pdo->prepare('SELECT revision,payload,created_at FROM sx_ygo_ffa_actions WHERE match_id=? ORDER BY revision');$q->execute([(int)($_GET['ffa_match_id']??0)]);
    header('Content-Type: application/json; charset=utf-8');header('Cache-Control: no-store');echo json_encode(['actions'=>$q->fetchAll(PDO::FETCH_ASSOC)],JSON_UNESCAPED_UNICODE);exit;
}
if ($dbStatus['connected'] && (string)($_GET['api']??'')==='statistics') {
    $stats=ygo_stats_view($pdo);
    if(!empty($_GET['publication_id'])){
        $q=$pdo->prepare('SELECT id,payload,published_at FROM sx_ygo_statistics_publications WHERE id=?');$q->execute([(int)$_GET['publication_id']]);$cut=$q->fetch(PDO::FETCH_ASSOC);
        if(!$cut){http_response_code(404);header('Content-Type: application/json');echo json_encode(['status'=>'PENDIENTE','reason'=>'Corte publicado inexistente']);exit;}
        $stats=ygo_stats_public_values(json_decode($cut['payload'],true));$stats['publication_id']=$cut['id'];$stats['published_at']=$cut['published_at'];
    }
    $format=trim((string)($_GET['format']??''));
    if($format!==''){
        foreach(['global','metagame','medals','ledger'] as $collection)$stats[$collection]=array_values(array_filter($stats[$collection]??[],static fn($r)=>(string)($r['format_id']??'')===$format));
        foreach(['ranking','ledger'] as $collection)$stats['free_for_all'][$collection]=array_values(array_filter($stats['free_for_all'][$collection]??[],static fn($r)=>($r['format_id']??'')===$format));
    }
    if(!empty($_GET['entity_team_id'])){
        $team=(int)$_GET['entity_team_id'];
        foreach(['global','profiles','medals'] as $list)$stats[$list]=array_values(array_filter($stats[$list]??[],static fn($r)=>(int)($r['team_id']??0)===$team));
        $stats['ledger']=array_values(array_filter($stats['ledger']??[],static fn($r)=>(int)($r['team_a_id']??0)===$team||(int)($r['team_b_id']??0)===$team||in_array($team,array_column($r['participants']??[],'team_id'),true)));
        $stats['free_for_all']['ranking']=array_values(array_filter($stats['free_for_all']['ranking']??[],static fn($r)=>(int)$r['team_id']===$team));
        $stats['free_for_all']['ledger']=array_values(array_filter($stats['free_for_all']['ledger']??[],static fn($r)=>in_array($team,array_column($r['changes'],'team_id'),true)));
    }
    $filters=[];foreach(['event_id','season_id','division_id','phase','event_type','team_id','deck_id','player_id','judge','venue','from','to'] as $key)if(isset($_GET['stats_'.$key])&&trim((string)$_GET['stats_'.$key])!=='')$filters[$key]=trim((string)$_GET['stats_'.$key]);
    $analysis=$stats;
    if($filters){
        $analysis=ygo_stats_calculate(ygo_stats_filter($stats['confirmed_matches']??[],$filters),[],[],$stats['policy']??[],$stats['cutoff']??null);
        $selectedMatches=array_fill_keys(array_column($analysis['ledger'],'match_id'),true);
        foreach($analysis['global'] as &$r){
            $official=array_values(array_filter($stats['global']??[],static fn($g)=>$g['team_id']===$r['team_id']&&$g['format_id']===$r['format_id']))[0]??null;
            $r['rating']=$official['rating']??null;$r['rival_pre_sum']=0;
            foreach($stats['ledger']??[] as $entry){
                if(!isset($selectedMatches[$entry['match_id']])||$entry['format_id']!==$r['format_id'])continue;
                if(($entry['kind']??'')==='tag'){foreach($entry['participants'] as $participant)if($participant['team_id']===$r['team_id'])$r['rival_pre_sum']+=$participant['rival_mean'];continue;}
                if($entry['team_a_id']===$r['team_id'])$r['rival_pre_sum']+=$entry['before_b'];
                elseif($entry['team_b_id']===$r['team_id'])$r['rival_pre_sum']+=$entry['before_a'];
            }
        }unset($r);
        $stats['matchups']=$analysis['matchups'];$stats['players']=$analysis['players'];
    }
    $analytics=ygo_stats_analytics($analysis,max(1,min(100,(int)($_GET['minimum']??3))));
    $stats['analytical_filters']=$filters;

    $stats['analytics']=$analytics;
    if(in_array($_GET['export']??'',['csv','pdf'],true)){
        ini_set('precision','17');
        $rows=ygo_stats_export_rows($stats,(string)($_GET['collection']??'global'));
        if($_GET['export']==='pdf')ygo_output_simple_pdf('Estadisticas | '.$stats['version'].' | '.$stats['cutoff'],$rows,'station-x-estadisticas.pdf');
        header('Content-Type: text/csv; charset=utf-8');header('Content-Disposition: attachment; filename="station-x-estadisticas.csv"');
        $out=fopen('php://output','w');foreach($rows as $row)fputcsv($out,$row);fclose($out);exit;
    }
    header('Content-Type: application/json; charset=utf-8');header('Cache-Control: no-store');echo json_encode($stats,JSON_UNESCAPED_UNICODE|JSON_PRESERVE_ZERO_FRACTION);exit;
}
if ($dbStatus['connected'] && (string) ($_GET['api'] ?? '') === 'live') {
    $liveMatches = [];
    foreach ($fixtures as $rounds) {
        foreach ($rounds as $round) {
            foreach (($round['matches'] ?? []) as $match) {
                $liveMatches[] = [
                    'id' => (int) ($match['id'] ?? 0),
                    'competition_id' => (int) ($match['competition_id'] ?? 0),
                    'competition_name' => (string) ($match['competition_name'] ?? ''),
                    'ronda' => (string) ($match['ronda'] ?? ''),
                    'mesa' => (string) ($match['mesa'] ?? ''),
                    'a' => (string) ($match['a'] ?? ''),
                    'b' => (string) ($match['b'] ?? ''),
                    'team_a' => (string) ($match['team_a'] ?? ''),
                    'team_b' => (string) ($match['team_b'] ?? ''),
                    'venue' => (string) ($match['venue'] ?? ''),
                    'estado' => (string) ($match['estado'] ?? ''),
                    'score' => (string) ($match['score'] ?? ''),
                    'd1' => $match['d1'] ?? null, 'd2' => $match['d2'] ?? null,
                    'd3' => $match['d3'] ?? null, 'd4' => $match['d4'] ?? null, 'd5' => $match['d5'] ?? null,
                    'duel_details' => (string) ($match['duel_details'] ?? ''),
                    'scheduled_on' => (string) ($match['scheduled_on'] ?? ''),
                    'starts_at' => (string) ($match['starts_at'] ?? ''),
                    'result_updated_at' => (string) ($match['result_updated_at'] ?? ''),
                ];
            }
        }
    }
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store, max-age=0');
    echo json_encode([
        'server_time' => date(DATE_ATOM),
        'revision' => (int) ($pdo->query('SELECT COALESCE(MAX(id),0) FROM sx_ygo_audit_log')->fetchColumn() ?: 0),
        'matches' => $liveMatches,
        'medal_unlocks' => array_slice($appData['medalUnlocks'] ?? [], 0, 100),
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

if ($dbStatus['connected'] && isset($_GET['public_live'])) {
    $refreshSeconds = max(5, min(30, (int) ($settings['live_refresh_seconds'] ?? 15)));
    header('Content-Type: text/html; charset=utf-8');
    ?><!doctype html><html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Partidas en vivo | YUGIOH MD</title><link rel="stylesheet" href="./assets/app.css?v=20260922-intelligence-5"><style>body{margin:0;background:#050d19;color:#eef5ff;font-family:Inter,Arial,sans-serif}.public-live{max-width:1500px;margin:auto;padding:clamp(16px,3vw,42px)}.public-live header{display:flex;justify-content:space-between;align-items:center;gap:18px;border-bottom:1px solid #1b4264;padding-bottom:20px}.public-live h1{font-size:clamp(28px,5vw,56px);margin:0}.public-live header span{color:#ffd25f;font-weight:800}.public-live-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(min(100%,360px),1fr));gap:16px;margin-top:24px}.public-match{border:1px solid #244663;background:#09172a;padding:20px;border-radius:8px}.public-match.is-live{border-color:#20c997;box-shadow:0 0 0 1px #20c99755}.public-match .meta{display:flex;justify-content:space-between;color:#9db0ce}.public-match .teams{display:grid;grid-template-columns:1fr auto 1fr;align-items:center;gap:12px;margin:24px 0}.public-match .teams b{font-size:clamp(18px,3vw,28px)}.public-match .teams b:last-child{text-align:right}.public-match .score{font-size:32px;color:#ffd25f;font-weight:900}.public-empty{padding:50px;border:1px dashed #35506f;text-align:center;margin-top:24px;color:#aabbd5}@media(max-width:600px){.public-live header{align-items:flex-start;flex-direction:column}.public-match .teams{grid-template-columns:1fr}.public-match .teams b:last-child{text-align:left}.public-match .score{font-size:26px}}</style></head><body><main class="public-live"><header><div><span>YUGIOH MD | STATION X TULUA</span><h1>Centro público de partidas</h1><p>Mesas activas y próximas partidas. Actualización automática.</p></div><a class="btn btn-outline-info" href="./?public_season=<?php echo (int) ygo_current_season_id($pdo); ?>#estadisticas">Ver temporada</a></header><section class="public-live-grid" id="public-live-grid"></section></main><script>const grid=document.getElementById('public-live-grid');const esc=(v)=>String(v??'').replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));async function refresh(){try{const r=await fetch('./?api=live',{cache:'no-store'});const d=await r.json();const rows=(d.matches||[]).filter(m=>!['FINALIZADA','COMPLETED'].includes(String(m.estado||'').toUpperCase())).sort((a,b)=>String(a.scheduled_on+' '+a.starts_at).localeCompare(String(b.scheduled_on+' '+b.starts_at)));grid.innerHTML=rows.length?rows.map(m=>`<article class="public-match ${String(m.estado).toUpperCase().includes('CURSO')?'is-live':''}"><div class="meta"><span>${esc(m.competition_name||'Partida')}</span><b>R${esc(m.ronda||'-')} | Mesa ${esc(m.mesa||'-')}</b></div><div class="teams"><b>${esc(m.a||'Deck A')}<small>${esc(m.team_a||'')}</small></b><span class="score">${esc(m.score||'VS')}</span><b>${esc(m.b||'Deck B')}<small>${esc(m.team_b||'')}</small></b></div><div class="meta"><span>${esc(m.scheduled_on||'Sin fecha')} ${esc(String(m.starts_at||'').slice(0,5))}</span><strong>${esc(m.estado||'PENDIENTE')}</strong></div></article>`).join(''):'<div class="public-empty">No hay mesas activas o próximas en este momento.</div>'}catch(e){grid.innerHTML='<div class="public-empty">No se pudo actualizar el marcador. Se reintentará automáticamente.</div>'}}refresh();setInterval(refresh,<?php echo $refreshSeconds * 1000; ?>);</script><?php echo ygo_ui_labels_script($presentationLabels); ?></body></html><?php
    exit;
}

if ($dbStatus['connected'] && isset($_GET['export'])) {
    $export = preg_replace('/[^a-z0-9_-]/i', '', (string) $_GET['export']);
    $format = preg_replace('/[^a-z0-9_-]/i', '', (string) ($_GET['format'] ?? 'csv'));
    $rows = [['YUGIOH MD', 'Station X Tulua', date('Y-m-d H:i:s')]];
    $title = 'Reporte ' . ucfirst(str_replace('_', ' ', $export));
    if ($format === 'xls') {
        header('Content-Type: application/vnd.ms-excel; charset=utf-8');
        header('Content-Disposition: attachment; filename="yugioh-' . $export . '.xls"');
    } elseif ($format === 'print') {
        header('Content-Type: text/html; charset=utf-8');
    } elseif ($format === 'pdf') {
        // El generador PDF define sus propios encabezados despues de construir las filas.
    } else {
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="yugioh-' . $export . '.csv"');
    }

    $add = static function (array $row) use (&$rows): void {
        $rows[] = $row;
    };

    $add(['']);
    if ($export === 'entity_report') {
        $entityType = preg_replace('/[^a-z_]/', '', strtolower((string) ($_GET['entity_type'] ?? 'deck')));
        $entityId = max(0, (int) ($_GET['entity_id'] ?? 0));
        $allowedEntityTypes = ['deck', 'team', 'event', 'season'];
        if (!in_array($entityType, $allowedEntityTypes, true) || $entityId <= 0) {
            $add(['Error', 'Entidad o identificador invalido']);
        } else {
            $title = 'Informe completo ' . ucfirst($entityType) . ' #' . $entityId;
            $add(['SECCION', 'FICHA']);
            if ($entityType === 'deck') {
                $entity = array_values(array_filter($allDecks, static fn(array $row): bool => (int) ($row['id'] ?? 0) === $entityId))[0] ?? [];
                $add(['Deck', $entity['name'] ?? 'No encontrado']); $add(['Equipo', $entity['team'] ?? 'Sin equipo']); $add(['Formato', $entity['format'] ?? $entity['ruleset'] ?? 'Sin formato']); $add(['Division', $entity['division'] ?? 'Sin division']); $add(['Arquetipo', $entity['archetype'] ?? '']); $add(['Variante', $entity['variations'] ?? '']);
            } elseif ($entityType === 'team') {
                $entity = array_values(array_filter($teams, static fn(array $row): bool => (int) ($row['id'] ?? 0) === $entityId))[0] ?? [];
                $add(['Equipo', $entity['name'] ?? 'No encontrado']); $add(['Codigo', $entity['short_code'] ?? $entity['code'] ?? '']); $add(['Ciudad', $entity['city'] ?? '']);
            } elseif ($entityType === 'event') {
                $entity = array_values(array_filter($competitions, static fn(array $row): bool => (int) ($row['id'] ?? 0) === $entityId))[0] ?? [];
                $add(['Evento', $entity['name'] ?? 'No encontrado']); $add(['Tipo', $entity['type'] ?? '']); $add(['Formato', $entity['event_ruleset'] ?? '']); $add(['Estado', $entity['status'] ?? '']);
                $summary = array_values(array_filter($appData['eventSummaries'] ?? [], static fn(array $row): bool => (int) ($row['competition_id'] ?? 0) === $entityId))[0] ?? [];
                if ($summary) {
                    $add(['Campeon', $summary['champion_name'] ?? '']); $add(['Subcampeon', $summary['runner_up_name'] ?? '']); $add(['Resumen congelado', $summary['finalized_on'] ?? '']);
                    $add(['']); $add(['CLASIFICACION DEFINITIVA']); $add(['Posicion','Deck','Equipo','PTS','Partidas G/E/P','Duelos G/P']);
                    foreach ((json_decode((string)($summary['standings_json'] ?? '[]'),true) ?: []) as $standing) $add([$standing['position'] ?? '',$standing['deck_name'] ?? '',$standing['team_name'] ?? '',$standing['points'] ?? 0,($standing['wins'] ?? 0).'/'.($standing['draws'] ?? 0).'/'.($standing['losses'] ?? 0),($standing['duels_won'] ?? 0).'/'.($standing['duels_lost'] ?? 0)]);
                    $add(['']); $add(['METAGAME DEL EVENTO']); $add(['Deck','Apariciones','Cuota']);
                    foreach ((json_decode((string)($summary['metagame_json'] ?? '[]'),true) ?: []) as $metaRow) $add([$metaRow['deck_name'] ?? '',$metaRow['appearances'] ?? 0,($metaRow['share'] ?? 0).'%']);
                    $stats = json_decode((string)($summary['statistics_json'] ?? '{}'),true) ?: [];
                    $add(['']); $add(['MEJORES ESTADISTICAS']); $add(['Partidas', $stats['matches'] ?? 0]);
                    if (!empty($stats['fastest_victory'])) $add(['Victoria mas rapida',($stats['fastest_victory']['deck'] ?? '').' | '.($stats['fastest_victory']['value'] ?? 0).' s']);
                    if (!empty($stats['fewest_turns_victory'])) $add(['Victoria en menos turnos',($stats['fewest_turns_victory']['deck'] ?? '').' | '.($stats['fewest_turns_victory']['value'] ?? 0).' turnos']);
                }
            } else {
                $entity = array_values(array_filter($appData['seasons'] ?? [], static fn(array $row): bool => (int) ($row['id'] ?? 0) === $entityId))[0] ?? [];
                $add(['Temporada', $entity['name'] ?? 'No encontrada']); $add(['Inicio', $entity['starts_on'] ?? '']); $add(['Fin', $entity['ends_on'] ?? '']); $add(['Estado', $entity['status'] ?? '']);
            }
            $relatedDeckIds = [];
            if ($entityType === 'deck') $relatedDeckIds = [$entityId];
            if ($entityType === 'team') $relatedDeckIds = array_map(static fn(array $row): int => (int) $row['id'], array_filter($allDecks, static fn(array $row): bool => (int) ($row['team_id'] ?? 0) === $entityId));
            $reportStats = ['matches'=>0,'wins'=>0,'draws'=>0,'losses'=>0,'duels_won'=>0,'duels_drawn'=>0,'duels_lost'=>0];
            $reportMatchups = []; $reportSnapshotOrigins = [];
            $add(['']); $add(['SECCION', 'PARTIDAS E HISTORIAL']);
            $add(['Fecha','Evento','Deck A','Deck B','Resultado','Marcador','Duelos','Procedencia snapshot']);
            foreach ($fixtures as $rounds) foreach ($rounds as $round) foreach (($round['matches'] ?? []) as $match) {
                $competition = array_values(array_filter($competitions, static fn(array $row): bool => (int) ($row['id'] ?? 0) === (int) ($match['competition_id'] ?? 0)))[0] ?? [];
                $belongs = $entityType === 'event' ? (int)($match['competition_id'] ?? 0) === $entityId : ($entityType === 'season' ? (int)($competition['season_id'] ?? 0) === $entityId : in_array((int)($match['deck_a_id'] ?? 0),$relatedDeckIds,true) || in_array((int)($match['deck_b_id'] ?? 0),$relatedDeckIds,true));
                if (!$belongs) continue;
                $origins = array_values(array_unique(array_map(static fn(array $row): string => (string)($row['snapshot_status'] ?? $row['data_origin'] ?? 'unknown'), array_filter($appData['matchSnapshots'] ?? [], static fn(array $row): bool => (int)($row['match_id'] ?? 0) === (int)($match['id'] ?? 0)))));
                foreach ($origins as $origin) $reportSnapshotOrigins[$origin] = ($reportSnapshotOrigins[$origin] ?? 0) + 1;
                if ($relatedDeckIds && in_array((string)($match['estado'] ?? $match['status'] ?? ''), ['FINALIZADA','completed','finalizada'], true) && in_array((string)($match['result'] ?? ''), ['A','B','E'], true)) {
                    $onA = in_array((int)($match['deck_a_id'] ?? 0),$relatedDeckIds,true); $onB = in_array((int)($match['deck_b_id'] ?? 0),$relatedDeckIds,true);
                    $side = $onA ? 'A' : ($onB ? 'B' : ''); $rivalName = $onA ? (string)($match['b'] ?? '') : (string)($match['a'] ?? '');
                    if ($side !== '') {
                        $reportStats['matches']++; $resultCode=(string)($match['result'] ?? '');
                        if ($resultCode === $side) $reportStats['wins']++; elseif ($resultCode === 'E') $reportStats['draws']++; else $reportStats['losses']++;
                        if (!isset($reportMatchups[$rivalName])) $reportMatchups[$rivalName]=['wins'=>0,'draws'=>0,'losses'=>0];
                        if ($resultCode === $side) $reportMatchups[$rivalName]['wins']++; elseif ($resultCode === 'E') $reportMatchups[$rivalName]['draws']++; else $reportMatchups[$rivalName]['losses']++;
                        foreach (['d1','d2','d3','d4','d5'] as $duelKey) { $duelResult=(string)($match[$duelKey] ?? ''); if ($duelResult === '') continue; if ($duelResult === $side) $reportStats['duels_won']++; elseif ($duelResult === 'E') $reportStats['duels_drawn']++; else $reportStats['duels_lost']++; }
                    }
                }
                $add([$match['scheduled_on'] ?? '',$competition['name'] ?? $match['competition_name'] ?? '',$match['a'] ?? '',$match['b'] ?? '',$match['result'] ?? '',$match['score'] ?? '',implode('/',array_filter([$match['d1'] ?? '',$match['d2'] ?? '',$match['d3'] ?? '',$match['d4'] ?? '',$match['d5'] ?? ''])),implode(', ',$origins) ?: 'sin snapshot']);
            }
            if ($relatedDeckIds) {
                $add(['']); $add(['SECCION', 'ESTADISTICAS']);
                $add(['Partidas G/E/P',$reportStats['wins'].'/'.$reportStats['draws'].'/'.$reportStats['losses']]); $add(['Duelos G/E/P',$reportStats['duels_won'].'/'.$reportStats['duels_drawn'].'/'.$reportStats['duels_lost']]);
                $add(['Winrate partidas',$reportStats['matches'] ? round($reportStats['wins']/$reportStats['matches']*100,2).'%' : 'N/D']); $add(['Cobertura snapshots',implode(', ',array_map(static fn($origin,$count): string => $origin.': '.$count,array_keys($reportSnapshotOrigins),array_values($reportSnapshotOrigins))) ?: 'Sin snapshots']);
                $add(['']); $add(['SECCION', 'MATCHUPS']); $add(['Rival','Partidas G/E/P']);
                uasort($reportMatchups,static fn(array $a,array $b): int => array_sum($b) <=> array_sum($a)); foreach ($reportMatchups as $rival=>$record) $add([$rival,$record['wins'].'/'.$record['draws'].'/'.$record['losses']]);
                $add(['']); $add(['SECCION', 'MEDALLAS']); $add(['Deck ID','Medalla','Rareza','Fecha','Evento','Rival']);
                foreach (($appData['medalUnlocks'] ?? []) as $medal) if ($medal['entity_type'] === 'deck' && in_array((int)$medal['entity_id'],$relatedDeckIds,true)) $add([$medal['entity_id'],$medal['medal_name'],$medal['rarity'],$medal['unlocked_at'],$medal['competition_id'],$medal['rival_name']]);
                $add(['']); $add(['SECCION', 'CARTAS Y MULTIMEDIA']); $add(['Deck ID','Tipo','Nombre / URL','Seccion','Cantidad']);
                foreach ($cards as $card) if (in_array((int)$card['deck_id'],$relatedDeckIds,true)) $add([$card['deck_id'],'Carta',$card['card_name'],$card['section'],$card['quantity']]);
                foreach ($media as $item) if (in_array((int)$item['deck_id'],$relatedDeckIds,true)) $add([$item['deck_id'],'Media',$item['url'],$item['media_type'],'']);
                if (!array_filter($cards, static fn(array $card): bool => in_array((int)$card['deck_id'],$relatedDeckIds,true))) $add(['','Aviso','Sin lista exacta de cartas cargada; no se infiere contenido.','','']);
            }
        }
    }

    if ($format === 'pdf') {
        ygo_output_simple_pdf($title, $rows, 'yugioh-' . $export . '-' . date('Y-m-d') . '.pdf');
    }
    if ($format === 'xls' || $format === 'print') {
        echo '<!doctype html><html lang="es"><head><meta charset="utf-8"><title>' . e($title) . '</title><style>
            body{margin:0;font-family:Arial,sans-serif;color:#111827;background:#eef1f6}
            .report{max-width:1180px;margin:0 auto;padding:24px}
            .hero{border-radius:12px;padding:22px 24px;color:#fff;background:linear-gradient(135deg,#07101f,#12356c 60%,#0b1220);box-shadow:0 10px 28px rgba(15,23,42,.18)}
            .hero small{display:block;color:#ffd25f;font-weight:800;text-transform:uppercase;letter-spacing:.05em}
            h1{margin:6px 0 4px;font-size:30px}
            .meta{margin:0;color:#c9d6f0}
            table{border-collapse:collapse;width:100%;margin-top:18px;background:#fff;border-radius:10px;overflow:hidden;box-shadow:0 8px 24px rgba(15,23,42,.08)}
            th,td{border:1px solid #d7dce8;padding:9px 10px;text-align:left;font-size:13px}
            th{background:#0b1830;color:#eef5ff;text-transform:uppercase;font-size:11px;letter-spacing:.04em}
            tr:nth-child(even) td{background:#f8fafc}
            .toolbar{margin:16px 0}
            .toolbar button{border:0;border-radius:8px;padding:10px 14px;color:#07101f;background:#ffd25f;font-weight:800}
            @media print{body{background:#fff}.toolbar{display:none}.report{padding:0}.hero{box-shadow:none;border-radius:0}}
        </style></head><body><main class="report">';
        echo '<section class="hero"><small>YUGIOH MD</small><h1>' . e($title) . '</h1><p class="meta">Station X Tulua | Generado ' . e(date('Y-m-d H:i:s')) . '</p></section>';
        if ($format === 'print') {
            echo '<div class="toolbar"><button onclick="window.print()">Imprimir / guardar PDF</button></div>';
        }
        echo '<table>';
        foreach ($rows as $index => $row) {
            echo '<tr>';
            foreach ($row as $cell) {
                $tag = $index <= 2 ? 'th' : 'td';
                echo '<' . $tag . '>' . e((string) $cell) . '</' . $tag . '>';
            }
            echo '</tr>';
        }
        echo '</table></main>'.ygo_ui_labels_script($presentationLabels).'</body></html>';
    } else {
        $out = fopen('php://output', 'w');
        foreach ($rows as $row) {
            fputcsv($out, $row);
        }
        fclose($out);
    }
    exit;
}
    $publicSeasonId = max(0, (int) ($_GET['public_season'] ?? 0));
    $publicSeasonRow = null;
    foreach (($appData['seasons'] ?? []) as $seasonRow) {
        if ((int) ($seasonRow['id'] ?? 0) === $publicSeasonId) {
            $publicSeasonRow = $seasonRow;
            break;
        }
    }
    $publicSeasonName = $publicSeasonRow
        ? (string) (($publicSeasonRow['name'] ?? '') ?: ('Temporada ' . $publicSeasonId))
        : ('Temporada ' . $publicSeasonId);
?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>YUGIOH MD - Station X Tulua</title>
    <meta name="description" content="Dashboard competitivo de Yu-Gi-Oh para YUGIOH MD, copas, mundial, fixtures y tablas.">
    <link rel="canonical" href="https://stationxtulua.com/yugioh/">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
<link rel="manifest" href="./manifest.webmanifest">
<meta name="theme-color" content="#07101f">
<link href="./assets/deck-builder.css?v=20261028-inspector-111" rel="stylesheet">
<link href="./assets/deck-history.css?v=20261024-history-92" rel="stylesheet">
<link href="./assets/modules/statistics/workspace.css?v=20261117-global-catalog" rel="stylesheet">
<link href="./assets/modules/deck-guide/list-metadata.css?v=20261113-deck-metadata-172" rel="stylesheet">
<link href="./assets/modules/deck-guide/strategy-workspace.css?v=20261024-strategy-95" rel="stylesheet">
<link href="./assets/modules/combo-creator/step-lesson.css?v=20261113-step-lessons-173" rel="stylesheet">
<link href="./assets/modules/play-calculator/card-menu.css?v=20261116-actions-1" rel="stylesheet">
<link href="./assets/modules/play-calculator/workspace.css?v=20261116-workspace-1" rel="stylesheet">
<link href="./assets/modules/card-inspector/styles.css?v=20261116-readable-3" rel="stylesheet">
<link href="./assets/modules/combo-creator/styles.css?v=20261116-deck-picker-1" rel="stylesheet">
<link href="./assets/modules/play-calculator/styles.css?v=20261116-workspace-1" rel="stylesheet">
<link href="./assets/modules/deck-guide/learning-guide.css?v=20261102-library-125" rel="stylesheet">
<link href="./assets/result-context.css?v=20261026-divisions-100" rel="stylesheet">
<link href="./assets/app.css?v=20261024-event-filters-90" rel="stylesheet">
<link href="./assets/modules/navigation/sidebar.css?v=20261117-sidebar-arrow" rel="stylesheet">
<link href="./assets/modules/navigation/global-search.css?v=20261113-global-search-166" rel="stylesheet">
<link href="./assets/modules/match-sheet/layout.css?v=20261104-match-sheet-134" rel="stylesheet">
<link href="./assets/modules/match-sheet/entity-links.css?v=20261117-entity-links" rel="stylesheet">
<link href="./assets/library.css?v=20261018-library-48" rel="stylesheet">
<link href="./assets/match-editor.css?v=20261020-match-49" rel="stylesheet">
<link rel="stylesheet" href="./assets/result-desktop.css?v=20261109-result-button-158">
<link rel="stylesheet" href="./assets/lp-workspace.css?v=20261027-lp-105">
<link rel="stylesheet" href="./assets/alert-colors.css?v=20261109-alert-colors-156">
<link rel="stylesheet" href="./assets/alert-order.css?v=20261027-alert-order-109">
<link rel="stylesheet" href="./assets/news.css?v=20261028-news-116">
</head>
<body class="<?php echo $publicSeasonId > 0 ? 'public-season-mode' : ''; ?>" data-public-season="<?php echo e((string) $publicSeasonId); ?>">
<script>
    window.DML_DATA = <?php echo json_encode($appData, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
</script>

<?php
$routeTitle = static fn(string $key, string $fallback): string => $uiText('route.' . $key, 'title', $fallback);
$routes = [
    'dashboard' => [$routeTitle('dashboard', 'Dashboard'), 'bi-grid-1x2-fill'],
    'buscar' => [$routeTitle('buscar', 'Buscar'), 'bi-search'],
    'mundos' => ['Mundos', 'bi-globe2'],
    'mundo_decks' => ['Decks del mundo', 'bi-globe2'],
    'mundos_deck_editar' => ['Asignar mundos', 'bi-globe2'],
    'calculadora_jugadas' => ['Calculadora de jugadas', 'bi-calculator'],
    'inspector_cartas' => ['Inspector de cartas', 'bi-search'],
    'creador_combos' => ['Creador de combos y estrategias', 'bi-bezier2'],
    'biblioteca' => ['Biblioteca', 'bi-book'],
    'clasificatorio' => [$routeTitle('clasificatorio', 'Clasificatorio'), 'bi-diagram-3'],
    'divisiones' => [$routeTitle('divisiones', 'Divisiones'), 'bi-trophy'],
    'temporadas' => ['Temporadas', 'bi-calendar2-range'],
    'calendario' => [$routeTitle('calendario', 'Calendario'), 'bi-calendar-event'],
    'resultados' => [$routeTitle('resultados', 'Resultados'), 'bi-list-check'],
    'tabla' => [$routeTitle('tabla', 'Tablas'), 'bi-table'],
    'deck' => [$routeTitle('deck', 'Decks'), 'bi-journal-richtext'],
    'deck_ficha' => [$routeTitle('deck_ficha', 'Ficha del deck'), 'bi-journal-richtext'],
    'equipos' => [$routeTitle('equipos', 'Equipos'), 'bi-shield-check'],
    'equipo_ficha' => [$routeTitle('equipo_ficha', 'Ficha del equipo'), 'bi-shield-check'],
    'estadisticas' => [$routeTitle('estadisticas', 'Estadisticas'), 'bi-graph-up-arrow'],
    'torneos' => [$routeTitle('torneos', 'Torneos'), 'bi-diagram-3-fill'],
    'ligas' => [$routeTitle('ligas', 'Ligas'), 'bi-calendar2-range'],
    'eventos_especiales' => [$routeTitle('eventos_especiales', 'Eventos especiales'), 'bi-stars'],
    'crear_evento' => [$routeTitle('crear_evento', 'Crear evento'), 'bi-plus-circle'],
    'copas' => [$routeTitle('copas', 'Copas'), 'bi-award'],
    'mundial' => [$routeTitle('mundial', 'Mundial'), 'bi-globe-americas'],
    'herramienta_lp' => [$routeTitle('herramienta_lp', 'Calculadora LP'), 'bi-calculator'],
    'noticias' => ['Noticias', 'bi-newspaper'],
    'reglamento' => [$routeTitle('reglamento', 'Reglamento'), 'bi-scroll'],
    'reglas' => [$routeTitle('reglas', 'Reglas'), 'bi-magic'],
    'gestion' => [$routeTitle('gestion', 'Gestion'), 'bi-sliders2-vertical'],
];
?>

<header class="app-header">
    <div class="container-fluid px-lg-4">
        <div class="topbar">
            <button class="icon-button sidebar-mobile-open" type="button" data-sidebar-open title="Abrir menu"><i class="bi bi-list"></i></button>
            <a class="brand" href="#dashboard" data-route="dashboard" aria-label="YUGIOH MD">
                <img class="brand-logo" src="./assets/img/dml-logo.svg" alt="YUGIOH MD">
                <span class="brand-copy"><strong>YUGIOH MD</strong><small>Station X Tulua</small></span>
            </a>
            <div class="searchbox">
                <i class="bi bi-search"></i>
                <input type="search" placeholder="Buscar decks, equipos o eventos" aria-label="Buscar decks, equipos o eventos" data-global-search>
                <div class="search-results" data-search-results hidden></div>
            </div>
            <div class="header-actions">
                <button class="icon-button" type="button" title="Noticias" aria-label="Abrir noticias" data-route="noticias"><i class="bi bi-newspaper"></i><span class="news-header-badge" data-news-badge hidden>0</span></button>
                <button class="icon-button" type="button" title="Notificaciones" data-alert-modal-open><i class="bi bi-bell"></i><span data-alert-badge="total">0</span></button>
            </div>
        </div>
    </div>
</header>

<div class="app-shell">
    <aside class="side-nav" aria-label="Menu principal">
        <div class="side-card side-header">
            <img class="side-logo" src="./assets/img/dml-logo.svg" alt="YUGIOH MD">
            <div class="side-heading">
                <strong>YUGIOH MD</strong>
                <small><?php echo e($dashboardSeasonName); ?></small>
            </div>
            <button class="side-tool side-collapse" type="button" data-sidebar-toggle title="Contraer menu" aria-label="Contraer menu" aria-expanded="true"><i class="bi bi-chevron-left" aria-hidden="true"></i></button>
        </div>
        <nav class="side-menu" aria-label="Pantallas principales">
            <details class="side-group">
                <summary><i class="bi bi-house-door"></i><span>Inicio</span></summary>
                <button class="nav-pill side-pill" type="button" data-route="dashboard"><i class="bi <?php echo e($routes['dashboard'][1]); ?>"></i> <span><?php echo e($routes['dashboard'][0]); ?></span></button>
                <button class="nav-pill side-pill" type="button" data-route="buscar"><i class="bi <?php echo e($routes['buscar'][1]); ?>"></i> <span><?php echo e($routes['buscar'][0]); ?></span></button>
                <button class="nav-pill side-pill" type="button" data-route="noticias"><i class="bi bi-newspaper"></i> <span>Noticias</span><em class="nav-badge" data-news-badge hidden>0</em></button>
            </details>
            <details class="side-group">
                <summary><i class="bi bi-controller"></i><span>Eventos</span></summary>
                <button class="nav-pill side-pill" type="button" data-route="torneos"><i class="bi <?php echo e($routes['torneos'][1]); ?>"></i> <span><?php echo e($routes['torneos'][0]); ?></span></button>
                <button class="nav-pill side-pill" type="button" data-route="temporadas"><i class="bi <?php echo e($routes['temporadas'][1]); ?>"></i> <span><?php echo e($routes['temporadas'][0]); ?></span></button>
                <button class="nav-pill side-pill" type="button" data-route="calendario"><i class="bi <?php echo e($routes['calendario'][1]); ?>"></i> <span><?php echo e($routes['calendario'][0]); ?></span><em class="nav-badge" data-alert-badge="calendar" hidden>0</em></button>
                <button class="nav-pill side-pill" type="button" data-route="resultados"><i class="bi <?php echo e($routes['resultados'][1]); ?>"></i> <span><?php echo e($routes['resultados'][0]); ?></span><em class="nav-badge" data-alert-badge="results" hidden>0</em></button>
            </details>
            <details class="side-group">
                <summary><i class="bi bi-people"></i><span>Decks y equipos</span></summary>
                <button class="nav-pill side-pill" type="button" data-route="equipos"><i class="bi <?php echo e($routes['equipos'][1]); ?>"></i> <span><?php echo e($routes['equipos'][0]); ?></span></button>
                <button class="nav-pill side-pill" type="button" data-route="deck"><i class="bi <?php echo e($routes['deck'][1]); ?>"></i> <span><?php echo e($routes['deck'][0]); ?></span></button>
                <button class="nav-pill side-pill" type="button" data-route="mundos"><i class="bi bi-globe2"></i> <span>Mundos</span></button>
                <button class="nav-pill side-pill" type="button" data-route="estadisticas"><i class="bi <?php echo e($routes['estadisticas'][1]); ?>"></i> <span><?php echo e($routes['estadisticas'][0]); ?></span></button>
            </details>
            <details class="side-group">
                <summary><i class="bi bi-wrench-adjustable-circle"></i><span>Herramientas</span></summary>
                <button class="nav-pill side-pill" type="button" data-route="calculadora_jugadas"><i class="bi bi-calculator"></i> <span>Calculadora de jugadas</span></button>
                <button class="nav-pill side-pill" type="button" data-route="inspector_cartas"><i class="bi bi-search"></i> <span>Inspector de cartas</span></button>
                <button class="nav-pill side-pill" type="button" data-route="creador_combos"><i class="bi bi-bezier2"></i> <span>Creador de combos y estrategias</span></button>
                <button class="nav-pill side-pill" type="button" data-route="herramienta_lp"><i class="bi <?php echo e($routes['herramienta_lp'][1]); ?>"></i> <span><?php echo e($routes['herramienta_lp'][0]); ?></span></button>
                <button class="nav-pill side-pill" type="button" data-route="biblioteca"><i class="bi bi-book"></i> <span>Biblioteca</span></button>
            </details>
            <details class="side-group">
                <summary><i class="bi bi-tools"></i><span>Configuracion</span></summary>
                <button class="nav-pill side-pill" type="button" data-route="gestion"><i class="bi <?php echo e($routes['gestion'][1]); ?>"></i> <span><?php echo e($routes['gestion'][0]); ?></span></button>
                <button class="nav-pill side-pill" type="button" data-route="reglamento"><i class="bi <?php echo e($routes['reglamento'][1]); ?>"></i> <span><?php echo e($routes['reglamento'][0]); ?></span></button>
            </details>
        </nav>
    </aside>
    <button class="sidebar-backdrop" type="button" data-sidebar-close aria-label="Cerrar menu"></button>

<main class="app-main container-fluid px-lg-4 py-4">
    <?php if ($flashMessage): ?><div class="alert <?php echo e($flashClass); ?> mb-3 app-flash-message" data-flash-message><?php echo e($flashMessage); ?></div><?php endif; ?>
    <section class="screen" data-screen="dashboard">
        <div class="dashboard-pro">
            <section class="dashboard-hero-pro">
                <div class="dashboard-hero-copy">
                    <span class="hero-mark"></span>
                    <p class="eyebrow">YUGIOH MD</p>
                    <h1>Bienvenido a YUGIOH MD</h1>
                    <p>Organiza y administra eventos activos por formato con datos reales de la base: calendario, participantes, resultados y estados actuales.</p>
                </div>
                <div class="dashboard-hero-status">
                    <article>
                        <i class="bi bi-shield-shaded"></i>
                        <small>Temporada actual</small>
                        <strong><?php echo e($dashboardSeasonName); ?></strong>
                    </article>
                    <article>
                        <i class="bi bi-calendar2-event"></i>
                        <small>Proximo foco</small>
                        <strong><?php echo e((string) ($nextDashboardEvent['starts_on'] ?? date('Y-m-d'))); ?></strong>
                    </article>
                </div>
            </section>

            <section class="news-dashboard" data-raw-text><div class="news-dashboard-head"><div><h2><i class="bi bi-newspaper"></i> En el radar</h2><p>Novedades de las cuatro fuentes que sigues.</p></div><button type="button" class="btn btn-outline-info btn-sm" data-route="noticias">Ver noticias ↗</button></div><div class="news-dashboard-grid" data-news-dashboard-grid><p>Preparando las publicaciones guardadas…</p></div></section>
            <div class="dashboard-command-layout">
                <div class="dashboard-main-stack">
                    <section class="panel dashboard-active-panel">
                        <div class="panel-head">
                            <div><h2>Eventos activos ahora</h2><p>Torneos y eventos en ventana real. Sigue la accion, revisa rondas y resultados.</p></div>
                            <button class="btn btn-outline-info btn-sm" type="button" data-route="torneos">Ver todos <i class="bi bi-arrow-right"></i></button>
                        </div>
                        <div class="dashboard-format-tabs" aria-label="Formatos activos">
                            <button class="active" type="button" data-dashboard-format-filter=""><i class="bi bi-stars"></i> Todos <b><?php echo $activeEventCount; ?></b></button>
                            <?php foreach ($currentEventsByFormat as $formatName => $formatEvents): ?>
                                <button type="button" data-dashboard-format-filter="<?php echo e((string) $formatName); ?>"><?php echo e($formatName); ?> <b><?php echo count($formatEvents); ?></b></button>
                            <?php endforeach; ?>
                        </div>
                        <div class="dashboard-event-card-grid">
                            <?php foreach (array_slice($activeDashboardEvents, 0, 5) as $index => $event): ?>
                                <?php
                                    $eventType = (string) (($event['type'] ?? '') ?: 'special');
                                    $eventMeta = $competitionTypeOptions[$eventType] ?? $competitionTypeOptions['special'];
                                    $rulesetCode = (string) (($event['event_ruleset'] ?? '') ?: 'custom');
                                    $rulesetLabel = (string) (($eventRulesetOptions[$rulesetCode] ?? '') ?: 'Libre');
                                    $deckPoolCode = (string) (($event['deck_pool'] ?? '') ?: 'all_active');
                                    $deckPoolLabel = (string) (($eventDeckPoolOptions[$deckPoolCode] ?? '') ?: $deckPoolCode);
                                    $deckPoolCount = $deckPoolCode === 'all_active' ? count($allDecks) : (int) (($event['deck_pool_count'] ?? '') ?: 0);
                                    $eventId = (int) ($event['id'] ?? 0);
                                    $eventStats = $dashboardMatchStatsByCompetition[$eventId] ?? ['total' => 0, 'finalized' => 0, 'pending' => 0];
                                    if ($eventStats['total'] === 0 && isset($dashboardMatchStatsByPhase[$eventType])) {
                                        $eventStats = $dashboardMatchStatsByPhase[$eventType];
                                    }
                                    $eventFormatName = (string) (($event['division_code'] ?? '') ?: (($eventRulesetOptions[$rulesetCode] ?? '') ?: 'General'));
                                    $progress = $eventStats['total'] > 0 ? (int) round(($eventStats['finalized'] / $eventStats['total']) * 100) : 0;
                                ?>
                                <article class="dashboard-event-card-pro" data-dashboard-event-card data-dashboard-format="<?php echo e($eventFormatName); ?>" style="--accent: <?php echo e((string) ($eventMeta['accent'] ?? '#895cff')); ?>; --i: <?php echo $index; ?>">
                                    <div class="event-card-cover<?php echo !empty($event['logo_url']) ? ' has-logo' : ''; ?>">
                                        <?php if (!empty($event['logo_url'])): ?><img src="<?php echo e((string) $event['logo_url']); ?>" alt="<?php echo e((string) ($event['name'] ?? 'Evento')); ?>"><?php endif; ?>
                                        <span><?php echo e((string) ($eventMeta['label'] ?? 'Evento')); ?></span>
                                        <em><?php echo e((string) (($event['status'] ?? '') ?: 'active')); ?></em>
                                    </div>
                                    <div class="event-card-body">
                                        <h3><?php echo e((string) ($event['name'] ?? 'Evento YUGIOH MD')); ?></h3>
                                        <p><i class="bi bi-diagram-3"></i> <?php echo e((string) (($event['format'] ?? '') ?: 'Formato editable')); ?></p>
                                        <p class="<?php echo $deckPoolCount === 0 ? 'event-warning-line' : ''; ?>"><i class="bi bi-people"></i> <?php echo $deckPoolCount > 0 ? e((string) $deckPoolCount) . ' decks habilitados' : 'Pool sin decks habilitados'; ?></p>
                                        <p><i class="bi bi-layers"></i> <?php echo e($deckPoolLabel); ?></p>
                                        <p><i class="bi bi-bookmark-star"></i> <?php echo e($rulesetLabel); ?></p>
                                        <p><i class="bi bi-list-check"></i> <?php echo (int) $eventStats['finalized']; ?>/<?php echo (int) $eventStats['total']; ?> duelos cerrados</p>
                                        <div class="event-progress"><span style="width: <?php echo $progress; ?>%"></span></div>
                                        <button class="btn btn-primary btn-sm w-100" type="button" data-event-open="<?php echo e((string) $eventId); ?>">Abrir ficha <i class="bi bi-card-checklist"></i></button>
                                    </div>
                                </article>
                            <?php endforeach; ?>
                            <?php if (!$activeDashboardEvents): ?>
                                <article class="dashboard-empty-state"><i class="bi bi-calendar-x"></i><strong>No hay eventos activos hoy</strong><span>Los eventos en borrador quedan en administracion hasta que entren en ventana activa.</span><button class="btn btn-primary btn-sm" type="button" data-route="crear_evento">Crear evento</button></article>
                            <?php endif; ?>
                        </div>
                        <div class="dashboard-active-support">
                            <section>
                                <h3>Duelos de hoy</h3>
                                <div class="dashboard-next-match-list">
                                    <?php foreach ($upcomingDashboardMatches as $match): ?>
                                        <?php
                                        $matchStatus = strtoupper((string) (($match['status'] ?? '') ?: 'PENDIENTE'));
                                        $matchStatusClass = in_array($matchStatus, ['FINALIZADA', 'FINALIZADO'], true) ? 'ok' : (in_array($matchStatus, ['EN CURSO', 'REQUIERE D5'], true) ? 'warn' : 'blocked');
                                        $matchStatusLabel = in_array($matchStatus, ['FINALIZADA', 'FINALIZADO'], true) ? 'Finalizado' : (in_array($matchStatus, ['EN CURSO', 'REQUIERE D5'], true) ? 'En curso' : 'Pendiente');
                                        ?>
                                        <article class="dashboard-next-match" role="button" tabindex="0" data-dashboard-match-open data-match-id="<?php echo e((string) ($match['id'] ?? '')); ?>">
                                            <b><?php echo e(substr((string) $match['time'], 0, 5)); ?></b>
                                            <span><?php echo e($match['label']); ?></span>
                                            <em class="status-chip <?php echo e($matchStatusClass); ?>"><?php echo e($matchStatusLabel); ?></em>
                                            <small><?php echo e($match['versus']); ?></small>
                                            <i class="bi bi-chevron-right" aria-hidden="true"></i>
                                        </article>
                                    <?php endforeach; ?>
                                    <?php if (!$upcomingDashboardMatches): ?><p class="note">No hay duelos programados para hoy.</p><?php endif; ?>
                                </div>
                            </section>
                            <section>
                                <h3>Eventos guardados</h3>
                                <div class="dashboard-state-grid">
                                    <?php foreach ($eventSummaryByStatus as $statusName => $statusCount): ?>
                                        <span><b><?php echo e((string) $statusCount); ?></b><?php echo e(ygo_event_status_label((string) $statusName)); ?></span>
                                    <?php endforeach; ?>
                                </div>
                            </section>
                        </div>
                    </section>

                    <section class="panel dashboard-results-panel">
                        <div class="panel-head"><div><h2>Ultimos resultados de duelos</h2><p>Resultados recientes en tiempo real de los eventos activos.</p></div><button class="btn btn-outline-light btn-sm" type="button" data-route="resultados">Ver todos <i class="bi bi-arrow-right"></i></button></div>
                        <div class="table-responsive">
                            <table class="table app-table dashboard-results-table">
                                <thead><tr><th>Evento</th><th>Jugador A</th><th>VS</th><th>Jugador B</th><th>Resultado</th></tr></thead>
                                <tbody>
                                <?php foreach ($recentResults as $result): ?>
                                    <?php $duelists = explode(' vs ', (string) $result['versus'], 2); ?>
                                    <tr><td><?php echo e($result['label']); ?></td><td><?php echo e($duelists[0] ?? 'Deck A'); ?></td><td><?php echo e($result['score']); ?></td><td><?php echo e($duelists[1] ?? 'Deck B'); ?></td><td><span class="status-chip ok"><?php echo e($result['status']); ?></span></td></tr>
                                <?php endforeach; ?>
                                <?php if (!$recentResults): ?><tr><td colspan="5">Todavia no hay resultados guardados en BD. Revisa proximos duelos en el panel superior.</td></tr><?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </section>

                </div>

                <aside class="dashboard-side-stack">
                    <section class="panel dashboard-ranking-panel">
                        <div class="panel-head"><div><h2><?php echo $dashboardLiveRanking ? 'Rankings en vivo' : 'Actividad registrada'; ?></h2><p data-dashboard-ranking-source><?php echo $dashboardLiveRanking ? e($dashboardRankingSource) : 'Sin ranking activo por resultados todavia.'; ?></p></div></div>
                        <label class="dashboard-ranking-event-label">Evento y división<select class="form-select" data-dashboard-ranking-event></select></label>
                        <label class="dashboard-ranking-event-label">Clasificacion
                            <select class="form-select" data-dashboard-ranking-metric>
                                <option value="general_stats">Estadisticas Generales</option>
                                <option value="points">Puntaje General</option>
                                <option value="duels">Duelos</option>
                                <option value="damage_dealt">Dano Hecho</option>
                                <option value="damage_received">Dano Recibido</option>
                                <option value="surrenders">Rendicion</option>
                                <option value="lp_losses">Puntos de Vida</option>
                            </select>
                        </label>
                        <h3 data-dashboard-ranking-title>Equipos con mas puntos</h3>
                        <p class="dashboard-ranking-coverage" data-dashboard-lp-coverage hidden></p>
                        <div class="dashboard-rank-list" data-dashboard-ranking-list>
                            <?php foreach ($topDashboardTeams as $index => $team): ?>
                                <?php
                                $teamName = (string) ($team['name'] ?? 'Equipo');
                                $teamLogo = ygo_media_src((string) ($team['logo_url'] ?? ''));
                                $teamMark = strtoupper(substr((string) (($team['symbol'] ?? '') ?: (($team['short_code'] ?? '') ?: $teamName)), 0, 3));
                                ?>
                                <article>
                                    <b><?php echo $index + 1; ?></b>
                                    <span class="rank-avatar media-avatar"><?php if ($teamLogo !== ''): ?><img src="<?php echo e($teamLogo); ?>" alt="<?php echo e($teamName); ?>"><?php else: ?><?php echo e($teamMark); ?><?php endif; ?></span>
                                    <strong><?php echo e($teamName); ?></strong>
                                    <em><?php echo (int) ($teamDeckCounts[$teamName] ?? 0); ?> decks</em>
                                </article>
                            <?php endforeach; ?>
                        </div>
                    </section>
                </aside>
            </div>
        </div>
        <div class="legacy-dashboard" hidden>
        <div class="hero-panel">
            <div>
                <p class="eyebrow">Yu-Gi-Oh competitivo</p>
                <h1>YUGIOH MD</h1>
                <p>Centro operativo para crear eventos independientes: clasificatorios, torneos, copas, ligas, Goat, TCG Avanzado, OCG y formatos especiales con reglas propias.</p>
            </div>
            <div class="hero-kpis">
                <div><strong><?php echo count($allDecks); ?></strong><span>decks activos</span></div>
                <div><strong><?php echo count($displayDivisions); ?></strong><span>formatos editables</span></div>
                <div><strong>3</strong><span>combates minimos</span></div>
                <div><strong><?php echo count($competitions); ?></strong><span>eventos configurados</span></div>
            </div>
        </div>
        <div class="phase-strip mt-3">
            <article class="phase-card active"><span>1</span><b>Eventos</b><small><?php echo count($competitions); ?> activos</small></article>
            <article class="phase-card <?php echo $qualifierFinished ? 'active' : ''; ?>"><span>2</span><b>Prerequisitos</b><small><?php echo count($competitionDependencies); ?> reglas</small></article>
            <article class="phase-card"><span>3</span><b>Reglamentos</b><small><?php echo count($eventSummaryByRuleset); ?> pools</small></article>
            <article class="phase-card"><span>4</span><b>Combates</b><small>Victoria / derrota / empate</small></article>
        </div>
        <?php if ($flashMessage): ?><div class="alert <?php echo e($flashClass); ?> mt-3 app-flash-message" data-flash-message><?php echo e($flashMessage); ?></div><?php endif; ?>

        <div class="dashboard-live-grid mt-3">
            <section class="panel">
                <div class="panel-head"><div><h2>Eventos activos por formato</h2><p>Vista inmediata de lo que esta abierto ahora.</p></div><button class="btn btn-outline-info btn-sm" type="button" data-route="torneos"><i class="bi bi-grid"></i> Ver eventos</button></div>
                <div class="dashboard-event-list">
                    <?php foreach ($currentEventsByFormat as $formatName => $formatEvents): ?>
                        <article>
                            <b><?php echo e($formatName); ?></b>
                            <div>
                                <?php foreach (array_slice($formatEvents, 0, 4) as $event): ?>
                                    <span><?php echo e((string) ($event['name'] ?? 'Evento')); ?> <small><?php echo e((string) (($event['status'] ?? '') ?: 'draft')); ?></small></span>
                                <?php endforeach; ?>
                            </div>
                        </article>
                    <?php endforeach; ?>
                    <?php if (!$currentEventsByFormat): ?><p class="note">No hay eventos en ventana activa para hoy.</p><?php endif; ?>
                </div>
            </section>
            <section class="panel">
                <div class="panel-head"><div><h2>Ultimos resultados</h2><p>Eventos y duelos cerrados recientemente.</p></div><button class="btn btn-outline-light btn-sm" type="button" data-route="resultados"><i class="bi bi-list-check"></i> Resultados</button></div>
                <div class="dashboard-result-list">
                    <?php foreach ($recentResults as $result): ?>
                        <article><span><?php echo e($result['label']); ?></span><b><?php echo e($result['score']); ?></b><small><?php echo e($result['versus']); ?></small></article>
                    <?php endforeach; ?>
                    <?php if (!$recentResults): ?><p class="note">Todavia no hay resultados guardados.</p><?php endif; ?>
                </div>
            </section>
            <section class="panel">
                <div class="panel-head"><div><h2>Tops</h2><p>Decks base y equipos con mayor presencia.</p></div><button class="btn btn-outline-light btn-sm" type="button" data-route="estadisticas"><i class="bi bi-bar-chart"></i> Estadisticas</button></div>
                <div class="dashboard-top-list">
                    <?php foreach (array_slice($allDecks, 0, 5) as $index => $deck): ?>
                        <article><b>#<?php echo $index + 1; ?></b><span><?php echo e((string) $deck['name']); ?></span><small><?php echo e((string) (($deck['team'] ?? '') ?: 'Sin equipo')); ?> | <?php echo e((string) $deck['division']); ?></small></article>
                    <?php endforeach; ?>
                </div>
            </section>
        </div>

        <div class="hifi-kpi-strip mt-3">
            <article class="hifi-kpi"><span><i class="bi bi-collection"></i></span><small>Decks registrados</small><strong><?php echo number_format(count($allDecks)); ?></strong><em>Base activa</em></article>
            <article class="hifi-kpi"><span><i class="bi bi-trophy-fill"></i></span><small>Eventos activos</small><strong><?php echo number_format(count(array_filter($competitions, static fn($c): bool => in_array((string) ($c['status'] ?? ''), ['active', 'draft'], true)))); ?></strong><em>Liga, copas y mundial</em></article>
            <article class="hifi-kpi"><span><i class="bi bi-crosshair"></i></span><small>Rondas cargadas</small><strong><?php echo number_format(array_sum(array_map(static fn($f): int => count($f), $fixtures))); ?></strong><em>Fixture editable</em></article>
            <article class="hifi-kpi"><span><i class="bi bi-calendar2-week-fill"></i></span><small>Proximos eventos</small><strong><?php echo number_format(count($competitions)); ?></strong><em>Ver calendario</em></article>
        </div>

        <section class="panel event-command-center mt-3">
            <div class="panel-head"><div><h2>Mapa de eventos</h2><p>Clasificatorios, ligas, copas y formatos especiales se administran como eventos independientes.</p></div><button class="btn btn-outline-info btn-sm" type="button" data-route="gestion"><i class="bi bi-plus-circle"></i> Crear evento</button></div>
            <div class="event-type-grid">
                <?php foreach ($competitionTypeOptions as $eventType => $eventTypeMeta): ?>
                    <article style="--accent: <?php echo e((string) $eventTypeMeta['accent']); ?>">
                        <i class="bi <?php echo e((string) $eventTypeMeta['icon']); ?>"></i>
                        <span><?php echo e((string) $eventTypeMeta['label']); ?></span>
                        <strong><?php echo (int) ($eventSummaryByType[$eventType] ?? 0); ?></strong>
                    </article>
                <?php endforeach; ?>
            </div>
            <div class="ruleset-strip">
                <?php foreach ($eventRulesetOptions as $rulesetCode => $rulesetLabel): ?>
                    <span><?php echo e($rulesetLabel); ?> <b><?php echo (int) ($eventSummaryByRuleset[$rulesetCode] ?? 0); ?></b></span>
                <?php endforeach; ?>
            </div>
        </section>

        <div class="row g-3 mt-1">
            <?php foreach ($displayDivisions as $key => $division): ?>
                <div class="col-xl-4">
                    <article class="league-card" style="--accent: <?php echo e($division['accent']); ?>">
                        <div class="d-flex justify-content-between gap-3">
                            <div><span class="tag"><?php echo e($division['short']); ?></span><h2><?php echo e($division['name']); ?></h2></div>
                            <span class="division-emblem"><?php echo e($division['short']); ?></span>
                        </div>
                        <p><?php echo e($division['description']); ?></p>
                        <div class="mini-grid">
                            <span><b><?php echo count($division['decks']); ?></b> decks</span>
                            <span><b><?php echo count($fixtures[$key]); ?></b> rondas</span>
                            <span><b><?php echo array_sum(array_map(static fn($r): int => count($r['matches']), $fixtures[$key])); ?></b> partidas</span>
                        </div>
                    </article>
                </div>
            <?php endforeach; ?>
        </div>

        <div class="row g-3 mt-1">
            <div class="col-xl-8">
                <section class="panel">
                    <div class="panel-head"><div><h2>Estado maestro</h2><p>Replica visual del Dashboard_Maestro y Dashboard de liga.</p></div><span class="status-chip">PENDIENTE / EN CURSO</span></div>
                    <div class="table-responsive">
                        <table class="table app-table">
                            <thead><tr><th>Indicador</th><th>Valor</th><th>Detalle</th></tr></thead>
                            <tbody>
                            <tr><td>Ligas</td><td><?php echo (int) ($eventSummaryByType['league'] ?? 0); ?></td><td>Ligas configuradas en YUGIOH MD</td></tr>
                            <tr><td>Clasificatorio</td><td><?php echo e($qualifierStatusLabel); ?></td><td>Se administra, se juega, se registran resultados y luego se finaliza</td></tr>
                            <tr><td>Divisiones</td><td><?php echo count($displayDivisions); ?></td><td><?php echo e($divisionModeLabel); ?> dentro del flujo de eventos</td></tr>
                            <tr><td>Partidas de liga</td><td><?php echo array_sum(array_map(static fn($f): int => array_sum(array_map(static fn($r): int => count($r['matches']), $f)), $fixtures)); ?></td><td>Calendario todos contra todos</td></tr>
                            <tr><td>Corte divisional</td><td>Configurable</td><td>Segun ranking final del clasificatorio y reglas vigentes</td></tr>
                            <tr><td>Base de datos</td><td><?php echo $dbStatus['connected'] ? 'Activa' : 'Pendiente'; ?></td><td><?php echo e($dbStatus['message']); ?></td></tr>
                            </tbody>
                        </table>
                    </div>
                </section>
            </div>
            <div class="col-xl-4">
                <section class="panel h-100">
                    <div class="panel-head"><div><h2>Flujo de temporada</h2><p>Orden competitivo definido.</p></div></div>
                    <ol class="timeline-list"><li>Torneo clasificatorio</li><li>Asignacion de formatos</li><li>Amistosos previos</li><li>Liga regular</li><li>Copas por formato</li><li>World Duel Championship</li><li>Copa Eternal</li></ol>
                    <?php if ($dbStatus['connected']): ?>
                        <div class="mini-grid mt-3">
                            <span><b><?php echo e((string) ($dbStatus['counts']['decks'] ?? 0)); ?></b> decks DB</span>
                            <span><b><?php echo e((string) ($dbStatus['counts']['matches'] ?? 0)); ?></b> partidas DB</span>
                            <span><b><?php echo e((string) ($dbStatus['counts']['divisions'] ?? 0)); ?></b> divisiones DB</span>
                            <span><b><?php echo e((string) ($dbStatus['counts']['teams'] ?? 0)); ?></b> equipos DB</span>
                            <span><b><?php echo e((string) ($dbStatus['counts']['competitions'] ?? 0)); ?></b> eventos DB</span>
                        </div>
                    <?php endif; ?>
                </section>
            </div>
        </div>
        <section class="panel management-shortcuts mt-3">
            <div class="panel-head">
                <div><h2>Gestion editable</h2><p>Las opciones de configuracion estan disponibles como parte normal del aplicativo.</p></div>
                <button class="btn btn-primary fw-bold" type="button" data-route="gestion"><i class="bi bi-sliders"></i> Abrir gestion</button>
            </div>
            <div class="gestion-command-grid">
                <article><i class="bi bi-people"></i><small>Equipos</small><strong>Nombre, codigo, colores y logo</strong><button class="btn btn-outline-light btn-sm" type="button" data-route="gestion">Editar</button></article>
                <article><i class="bi bi-journal-richtext"></i><small>Decks</small><strong>Ficha, arquetipo, cartas y media</strong><button class="btn btn-outline-light btn-sm" type="button" data-route="gestion">Editar</button></article>
                <article><i class="bi bi-trophy"></i><small>Torneos</small><strong>Ligas, copas, mundial y estados</strong><button class="btn btn-outline-light btn-sm" type="button" data-route="gestion">Editar</button></article>
                <article><i class="bi bi-calendar-event"></i><small>Calendario</small><strong>Fechas, horas, mesas y sede</strong><button class="btn btn-outline-light btn-sm" type="button" data-route="calendario">Programar</button></article>
            </div>
            <div class="pending-decks mt-3">
                <h3>Decks pendientes para cerrar</h3>
                <div>
                    <?php foreach (array_filter($qualifierProgress, static fn(array $row): bool => !$row['ready']) as $row): ?>
                        <span><?php echo e($row['deck']); ?> <small><?php echo e((string) $row['played']); ?>/3 combates completados</small></span>
                    <?php endforeach; ?>
                    <?php if ($qualifierReadyCount === count($qualifierProgress)): ?><span>Todos los decks cumplen el minimo competitivo.</span><?php endif; ?>
                </div>
            </div>
        </section>
        <div class="dashboard-arena mt-3">
            <section class="panel">
                <div class="panel-head"><div><h2>Top decks</h2><p>Vista tipo ranking competitivo.</p></div><button class="btn btn-outline-light btn-sm" type="button" data-route="tabla">Ver todos</button></div>
                <div class="arena-ranking">
                    <?php foreach (array_slice($allDecks, 0, 5) as $index => $deck): ?>
                        <article><b><?php echo $index + 1; ?></b><span class="mini-card-art" style="--i:<?php echo $index; ?>"></span><strong><?php echo e($deck['name']); ?></strong><small><?php echo e($deck['division']); ?></small><em><?php echo e((string) max(6, 18 - ($index * 3))); ?>%</em></article>
                    <?php endforeach; ?>
                </div>
            </section>
            <section class="panel">
                <div class="panel-head"><div><h2>Top jugadores</h2><p>Equipos con decks registrados.</p></div><button class="btn btn-outline-light btn-sm" type="button" data-route="gestion">Gestion completa</button></div>
                <div class="arena-ranking players">
                    <?php foreach (array_slice($teams, 0, 5) as $index => $team): ?>
                        <article><b><?php echo $index + 1; ?></b><span class="player-medal"><?php echo e(substr((string) $team['name'], 0, 1)); ?></span><strong><?php echo e((string) $team['name']); ?></strong><small><?php echo e((string) ($team['city'] ?: 'Station X')); ?></small><em><?php echo number_format(2450 - ($index * 135)); ?> pts</em></article>
                    <?php endforeach; ?>
                </div>
            </section>
            <section class="panel season-progress">
                <div class="panel-head"><div><h2>Progreso de temporada</h2><p>Estado actual de la liga.</p></div><span class="status-chip">Nivel 42</span></div>
                <div class="rank-emblem">YMD</div>
                <div class="xp-bar"><span style="width:68%"></span></div>
                <p>Clasificatorio abierto, divisiones editables y calendario listo para programar combates.</p>
                <button class="btn btn-outline-light w-100" type="button" data-route="clasificatorio">Ver fase actual</button>
            </section>
        </div>
        </div>
    </section>

    <?php require __DIR__.'/includes/decks/calculator-workspace-view.php'; ?>
    <?php require __DIR__.'/includes/decks/card-inspector-view.php'; ?>
    <?php require __DIR__.'/includes/decks/attributes-view.php'; ?>
    <?php require __DIR__.'/includes/decks/combo-creator-view.php'; ?>
    <?php require __DIR__.'/includes/library-view.php'; ?>
    <section class="screen" data-screen="crear_evento" hidden>
        <div class="page-title"><h1><?php echo e($uiText('route.crear_evento', 'title', 'Crear evento')); ?></h1><p><?php echo e($uiText('route.crear_evento', 'subtitle', 'Pantalla unica para crear clasificatorios, copas, torneos, ligas y eventos especiales.')); ?></p></div>
        <div class="event-create-layout">
            <section class="panel event-create-main">
                <div class="panel-head"><div><h2>Nuevo evento</h2><p>Configura los datos y las reglas del evento.</p></div><span class="status-chip">Unico formulario</span></div>
                <form method="post" class="gestion-form event-create-form" enctype="multipart/form-data">
                    <input type="hidden" name="action" value="save_competition">
                    <?php $seasonFieldMode = "edit"; require __DIR__ . "/includes/season-event-field.php"; ?>
                    <input type="hidden" name="competition_id" data-gestion-competition-id>
                    <input type="hidden" name="competition_code" data-gestion-competition-code>
                    <select hidden data-gestion-competition-select aria-hidden="true"><option value="">Nuevo evento</option></select>
                    <label class="span-all">Nombre del evento<input class="form-control" name="competition_name" data-gestion-competition-name required placeholder="Ej: Clasificatorio TCG Avanzado"></label>
                    <div class="event-form-row two-col">
                        <label>Tipo de evento<select class="form-select" name="competition_type" data-gestion-competition-type>
                            <?php foreach ($competitionTypeOptions as $eventType => $eventTypeMeta): ?>
                                <option value="<?php echo e($eventType); ?>"><?php echo e((string) $eventTypeMeta['label']); ?></option>
                            <?php endforeach; ?>
                        </select><small>Define el modulo base: clasificatorio, torneo, liga, copa, mundial, final, amistoso o especial.</small></label>
                        <label>Modelo preconfigurado del evento<select class="form-select" name="competition_prompt_template" data-gestion-competition-template>
                            <option value="">Personalizado / sin plantilla</option>
                            <?php foreach (ygo_event_available_templates() as $template): ?>
                                <option value="<?php echo e((string) $template['code']); ?>" data-template-type="<?php echo e((string) ($template['type'] ?? 'tournament')); ?>"><?php echo e((string) ($template['label'] . ' | ' . $template['system'] . ' | ' . $template['rules'])); ?></option>
                            <?php endforeach; ?>
                        </select><small>Rellena una configuracion coherente sin borrar el tipo de evento elegido.</small></label>
                    </div>
                    <input type="hidden" name="competition_event_system" data-gestion-competition-event-system>
                    <div class="event-form-row two-col">
                        <label>Formato / division principal<select class="form-select" name="competition_division_id" data-gestion-competition-division><option value="">General</option><optgroup label="Formatos disponibles"><?php foreach ($formatSelectOptions as $rulesetCode => $rulesetLabel): ?><option value="format:<?php echo e((string) $rulesetCode); ?>" data-ruleset="<?php echo e((string) $rulesetCode); ?>"><?php echo e((string) $rulesetLabel); ?></option><?php endforeach; ?></optgroup><?php if ($displayDivisions): ?><optgroup label="Divisiones"><?php foreach ($displayDivisions as $division): ?><option value="<?php echo e((string) ($division['id'] ?? '')); ?>" data-ruleset="<?php echo e((string) ($division['ruleset'] ?? $division['event_ruleset'] ?? '')); ?>"><?php echo e($division['short']); ?></option><?php endforeach; ?></optgroup><?php endif; ?></select><small>Para una liga, esta sera la division principal.</small></label>
                        <label data-gestion-league-divisions hidden>Divisiones asociadas a la liga<select class="form-select" name="competition_division_ids[]" data-gestion-league-divisions-select multiple size="4"><?php foreach ($displayDivisions as $division): ?><option value="<?php echo e((string) ($division['id'] ?? '')); ?>"><?php echo e((string) (($division['name'] ?? '') ?: ($division['short'] ?? 'Division'))); ?></option><?php endforeach; ?></select><small>Selecciona una o varias divisiones al crear la liga. La principal debe quedar incluida.</small></label>
                    </div>
                    <div class="event-form-row two-col">
                        <label>Sistema competitivo<select class="form-select" name="competition_execution_model" data-gestion-competition-execution-model><?php foreach ($eventModelOptions['competition_system'] as $optionCode => $optionLabel): ?><option value="<?php echo e($optionCode); ?>"><?php echo e($optionLabel); ?></option><?php endforeach; ?></select><small>Controla calendario, fases, avance y validaciones de resultado.</small></label>
                        <label>Participacion del match<select class="form-select" name="competition_match_format" data-gestion-competition-match-format required><option value="">Selecciona participacion</option><option value="ffa" data-participants="1">3-4 jugadores independientes</option><?php foreach ($matchTypeOptions as $matchType): ?><option value="<?php echo e((string) $matchType['code']); ?>" data-participants="<?php echo e((string) ($matchType['participants_per_side'] ?? 1)); ?>"><?php echo e((string) ($matchType['label'] ?? $matchType['code'])); ?></option><?php endforeach; ?></select><small>Define cuantos equipos/decks participan por lado del combate.</small></label>
                    </div>
                    <div class="alert alert-warning span-all" data-event-model-diagnostic hidden></div>
                    <div class="alert alert-info span-all" data-event-workflow-policy hidden></div>
                    <input type="hidden" data-gestion-competition-execution-preview value="manual">
<section class="event-model-box"><h3>Parámetros de ejecución</h3><p>Reglamento interno Station X 2026 v1: puntos 3/1/0, BYE 3 sin duelo ni XP, desempate por puntos, Buchholz, diferencia y orden inicial. Las finales decisivas requieren ganador. Estos valores no certifican un reglamento oficial. Si dejas un parámetro vacío, se conserva el valor de esta política; los valores elegidos quedan fijados al programar.</p><div class="event-form-row three-col"><label>Rondas suizas<input class="form-control" type="number" min="1" max="64" name="competition_parameter_swiss_rounds" data-parameter="swiss_rounds" placeholder="Política interna mostrada arriba"></label><label>Clasificados al corte<input class="form-control" type="number" min="1" max="64" name="competition_parameter_top_n" data-parameter="top_n" placeholder="Política interna mostrada arriba"></label><label>Máximo por grupo<input class="form-control" type="number" min="1" max="64" name="competition_parameter_group_max_size" data-parameter="group_max_size" placeholder="Política interna mostrada arriba"></label><label>Clasificados por grupo<input class="form-control" type="number" min="1" max="64" name="competition_parameter_group_qualifiers" data-parameter="group_qualifiers" placeholder="Política interna mostrada arriba"></label><label>Matches de la serie (impar)<input class="form-control" type="number" min="1" max="64" name="competition_parameter_series_matches" data-parameter="series_matches" placeholder="Política interna mostrada arriba"></label></div></section>
                    <section class="event-model-box">
                        <div class="panel-head compact-head"><div><h3>Reglas operativas del evento</h3><p>Elige como se clasifica y como se resuelve cada enfrentamiento. La lectura del evento explica cada valor.</p></div></div>
                        <div hidden aria-hidden="true">
                            <select class="form-select" name="competition_participation" data-gestion-competition-participation><?php foreach ($eventModelOptions['participation'] as $optionCode => $optionLabel): ?><option value="<?php echo e($optionCode); ?>"><?php echo e($optionLabel); ?></option><?php endforeach; ?></select>
                            <select class="form-select" name="competition_construction" data-gestion-competition-construction><?php foreach ($eventModelOptions['construction'] as $optionCode => $optionLabel): ?><option value="<?php echo e($optionCode); ?>"><?php echo e($optionLabel); ?></option><?php endforeach; ?></select>
                            <select class="form-select" name="competition_participant_source" data-gestion-competition-participant-source><?php foreach ($eventModelOptions['participant_source'] as $optionCode => $optionLabel): ?><option value="<?php echo e($optionCode); ?>"><?php echo e($optionLabel); ?></option><?php endforeach; ?></select>
                            <select class="form-select" name="competition_phase_structure" data-gestion-competition-phase-structure><?php foreach ($eventModelOptions['phase_structure'] as $optionCode => $optionLabel): ?><option value="<?php echo e($optionCode); ?>"><?php echo e($optionLabel); ?></option><?php endforeach; ?></select>
                            <select class="form-select" name="competition_event_flow" data-gestion-competition-event-flow><?php foreach ($eventModelOptions['event_flow'] as $optionCode => $optionLabel): ?><option value="<?php echo e($optionCode); ?>"><?php echo e($optionLabel); ?></option><?php endforeach; ?></select>
                            <select class="form-select" name="competition_ranking_impact" data-gestion-competition-ranking-impact><?php foreach ($eventModelOptions['ranking_impact'] as $optionCode => $optionLabel): ?><option value="<?php echo e($optionCode); ?>"><?php echo e($optionLabel); ?></option><?php endforeach; ?></select>
                            <select class="form-select" name="competition_deck_policy" data-gestion-competition-deck-policy><?php foreach ($eventModelOptions['deck_policy'] as $optionCode => $optionLabel): ?><option value="<?php echo e($optionCode); ?>"><?php echo e($optionLabel); ?></option><?php endforeach; ?></select>
                        </div>
                        <div hidden aria-hidden="true">
                            <div hidden aria-hidden="true"><select class="form-select" name="competition_environment" data-gestion-competition-environment><?php foreach ($eventModelOptions['environment'] as $optionCode => $optionLabel): ?><option value="<?php echo e($optionCode); ?>"><?php echo e($optionLabel); ?></option><?php endforeach; ?></select></div>
                            <div hidden aria-hidden="true"><select class="form-select" name="competition_level" data-gestion-competition-level><?php foreach ($eventModelOptions['level'] as $optionCode => $optionLabel): ?><option value="<?php echo e($optionCode); ?>"><?php echo e($optionLabel); ?></option><?php endforeach; ?></select></div>
                            <div hidden aria-hidden="true"><select class="form-select" name="competition_access_type" data-gestion-competition-access-type><?php foreach ($eventModelOptions['access_type'] as $optionCode => $optionLabel): ?><option value="<?php echo e($optionCode); ?>"><?php echo e($optionLabel); ?></option><?php endforeach; ?></select></div>
                        </div>
                        <div class="event-form-row two-col">
                            <label>Regla de avance<select class="form-select" name="competition_advancement_rule" data-gestion-competition-advancement-rule><?php foreach ($eventModelOptions['advancement_rule'] as $optionCode => $optionLabel): ?><option value="<?php echo e($optionCode); ?>"><?php echo e($optionLabel); ?></option><?php endforeach; ?></select></label>
                            <label>Politica de empate<select class="form-select" name="competition_draw_policy" data-gestion-competition-draw-policy><?php foreach ($eventModelOptions['draw_policy'] as $optionCode => $optionLabel): ?><option value="<?php echo e($optionCode); ?>"><?php echo e($optionLabel); ?></option><?php endforeach; ?></select></label>
                        </div>
                        <div class="event-form-row two-col">
                            <label>Formato de partida / Mejor de<input class="form-control" type="number" min="1" max="5" name="competition_best_of" data-gestion-competition-best-of value="3"><small>1 = BO1, 3 = BO3, 5 = BO5; otro valor funciona como serie personalizada.</small></label>
                        </div>
                        <input type="hidden" name="qualifier_duels_per_deck" value="3" data-gestion-competition-qualifier-duels>
                        <input type="hidden" name="qualifier_matches_per_day" value="1" data-gestion-competition-matches-per-day>
                        
                    </section>
                    <div class="event-form-row two-col">
                        <label>Regla del evento<select class="form-select" name="competition_game_rule_id" data-gestion-competition-game-rule><option value="">Usar regla compatible por defecto</option><?php foreach ($gameRules as $rule): ?><?php if (empty($rule['active']) || !in_array((string) ($rule['scope'] ?? ''), ['event', 'all_matches', 'general'], true)) { continue; } ?><option value="<?php echo e((string) ($rule['id'] ?? '')); ?>"><?php echo e((string) ($rule['name'] ?? 'Regla')); ?> | <?php echo e((string) ($rule['scope'] ?? 'general')); ?></option><?php endforeach; ?></select></label>
                    </div>
                    <div class="event-logo-gallery" data-event-logo-gallery>
                        <div class="event-logo-gallery-head">
                            <span>Logo de galeria</span>
                            <small data-event-logo-gallery-count></small>
                        </div>
                        <input type="hidden" name="competition_gallery_logo" data-event-logo-gallery-input>
                        <div class="event-logo-gallery-grid" data-event-logo-gallery-grid></div>
                    </div>
                    <div class="event-form-row two-col">
                        <label>Subir logo nuevo<input class="form-control" type="file" name="competition_logo" accept="image/png,image/jpeg,image/webp,image/gif" data-gestion-competition-logo data-event-logo-upload><small>PNG, JPG, WEBP o GIF. Si subes uno, reemplaza el logo de galeria.</small></label>
                    </div>
                    <div class="event-form-row two-col">
                        <label>Tipo de reglas / formato base<select class="form-select" name="competition_event_ruleset" data-gestion-competition-ruleset><?php foreach ($rulesetSelectOptions as $rulesetCode => $rulesetLabel): ?><option value="<?php echo e($rulesetCode); ?>"><?php echo e($rulesetLabel); ?></option><?php endforeach; ?></select><small>Define el entorno de reglas principal: TCG, OCG, GOAT, Edison, Speed Duel, Master Duel o custom.</small></label>
                        <label>Reglamento / banlist<select class="form-select" name="competition_ruleset_version" data-gestion-competition-ruleset-version><option value="">Selecciona reglamento / por definir</option>
                            <?php foreach ($rulesetVersionCatalog as $versionOption): ?>
                                <?php $versionLabel = (string) ($versionOption['version_label'] ?? $versionOption['version'] ?? ''); ?>
                                <option value="<?php echo e($versionLabel); ?>" data-system="<?php echo e((string) $versionOption['system']); ?>" data-date="<?php echo e((string) $versionOption['date_label']); ?>" data-category="<?php echo e((string) $versionOption['category']); ?>"><?php echo e((string) ($versionOption['system'] . ' - ' . $versionLabel . ' - ' . $versionOption['date_label'])); ?></option>
                            <?php endforeach; ?>
                        </select></label>
                    </div>
                    <input type="hidden" name="competition_scope" data-gestion-competition-scope value="division">
                    <input type="hidden" name="competition_format" data-gestion-competition-format value="editable">
                    <input type="hidden" name="competition_status" data-gestion-competition-status value="pending_configuration">
                    <input type="hidden" name="starts_on" data-gestion-competition-start value="">
                    <input type="hidden" name="ends_on" data-gestion-competition-end value="">
                    <label class="span-all">Modo de participantes<select class="form-select" name="competition_deck_pool" data-gestion-competition-deck-pool>
                        <option value="all_active">Libre: mostrar todos los decks/equipos activos</option>
                        <option value="division">Division completa: usar solo decks/equipos de la division elegida</option>
                    </select><small>Para 2v2 y 3v3 se mostraran equipos con la cantidad minima de decks en la division.</small></label>
                    <section class="league-movement-box" data-gestion-league-movement hidden>
                        <div class="panel-head compact-head"><div><h3>Movimiento de divisiones</h3><p>Solo para ligas: ascensos, descensos y bloqueo de movimiento.</p></div></div>
                        <div class="two-col">
                            <label>Equipos que suben<input class="form-control" type="number" min="0" max="20" name="competition_promotion_slots" data-gestion-competition-promotion-slots value="0"></label>
                            <label>Suben hacia<select class="form-select" name="competition_promotion_to" data-gestion-competition-promotion-to><option value="">Sin ascenso</option><?php foreach ($displayDivisions as $division): ?><option value="<?php echo e((string) ($division['id'] ?? '')); ?>"><?php echo e($division['short']); ?></option><?php endforeach; ?></select></label>
                        </div>
                        <div class="two-col">
                            <label>Equipos que bajan<input class="form-control" type="number" min="0" max="20" name="competition_relegation_slots" data-gestion-competition-relegation-slots value="0"></label>
                            <label>Bajan hacia<select class="form-select" name="competition_relegation_to" data-gestion-competition-relegation-to><option value="">Sin descenso</option><?php foreach ($displayDivisions as $division): ?><option value="<?php echo e((string) ($division['id'] ?? '')); ?>"><?php echo e($division['short']); ?></option><?php endforeach; ?></select></label>
                        </div>
                        <label class="toggle-line"><input type="checkbox" name="competition_lock_division_moves" value="1" data-gestion-competition-lock-moves> Bloquear mover decks entre divisiones para esta liga</label>
                    </section>
                    <input type="hidden" name="competition_participants_per_side" data-gestion-competition-participants value="1">
                    <input type="hidden" name="competition_schedule_strategy" data-gestion-competition-schedule value="manual">
                    <label class="span-all">Notas del evento<textarea class="form-control" name="competition_rules" data-gestion-competition-rules rows="3" placeholder="Notas internas, sede, politica de ronda o aclaraciones del organizador"></textarea></label>
                    <button class="btn btn-primary fw-bold" type="submit"><i class="bi bi-save"></i> Guardar evento</button>
                </form>
            </section>
            <aside class="event-create-side" data-event-create-guide>
                <h2>Lectura del evento</h2>
                <p>Asi funcionara el evento con los valores actuales del formulario.</p>
                <div data-event-guide-content style="display:grid;gap:.85rem"></div>
            </aside>
        </div>
    </section>

    <section class="screen" data-screen="buscar" hidden>
        <div class="page-title"><h1><?php echo e($uiText('route.buscar', 'title', 'Buscar')); ?></h1><p><?php echo e($uiText('route.buscar', 'subtitle', 'Encuentra decks, equipos, ligas, copas, torneos, rondas y pantallas del aplicativo.')); ?></p></div>
        <div class="search-layout">
            <aside class="panel search-filters">
                <div class="panel-head"><div><h2>Filtros</h2><p>Busqueda avanzada.</p></div><a href="#buscar">Limpiar</a></div>
                <label>Deck<select class="form-select"><option>Cualquier arquetipo</option><option>Combo</option><option>Control</option><option>Midrange</option></select></label>
                <label>Jugador<select class="form-select"><option>Todos los equipos</option><?php foreach (array_slice($teams, 0, 8) as $team): ?><option><?php echo e((string) $team['name']); ?></option><?php endforeach; ?></select></label>
                <label>Torneo<select class="form-select"><option>Todos los torneos</option><?php foreach (array_slice($competitions, 0, 8) as $competition): ?><option><?php echo e((string) $competition['name']); ?></option><?php endforeach; ?></select></label>
                <label>Categoria<select class="form-select"><option>Todas las categorias</option><?php foreach ($displayDivisions as $division): ?><option><?php echo e($division['short']); ?></option><?php endforeach; ?></select></label>
                <button class="btn btn-primary w-100" type="button"><i class="bi bi-funnel"></i> Aplicar filtros</button>
            </aside>
            <section class="panel search-main">
                <div class="search-hero">
                    <div>
                        <h2>Que estas buscando?</h2>
                        <p>Busca decks, jugadores, torneos, copas y mas en YUGIOH MD.</p>
                        <div class="search-page-box">
                            <i class="bi bi-search"></i>
                            <input type="search" placeholder="Ej: Kewl Tune, Copa TCG Avanzado, OCG" data-search-page-input>
                        </div>
                    </div>
                </div>
                <div class="trend-strip">
                    <?php foreach (array_slice($allDecks, 0, 4) as $index => $deck): ?>
                        <article><span class="mini-card-art" style="--i:<?php echo $index; ?>"></span><b><?php echo e($deck['name']); ?></b><small><?php echo e($deck['division']); ?></small></article>
                    <?php endforeach; ?>
                </div>
                <div class="search-page-results mt-3" data-search-page-results></div>
            </section>
        </div>
    </section>

    <section class="screen" data-screen="clasificatorio" data-qualifier-event-id="<?php echo e($showQualifierDetail ? (string) $displayQualifierId : ''); ?>" hidden>
        <div data-qualifier-directory <?php echo $showQualifierDetail ? 'hidden' : ''; ?>>
            <div class="page-title with-actions module-title qualifier-directory-title">
                <div><span class="tag">Clasificatorios</span><h1>Eventos clasificatorios</h1><p>Selecciona un clasificatorio para consultar su ranking, combates y cierre.</p></div>
                <button class="btn btn-primary fw-bold" type="button" data-route="crear_evento"><i class="bi bi-plus-circle"></i> Crear clasificatorio</button>
            </div>
            <section class="panel event-filter-panel mt-3">
                <details class="mobile-collapsible event-filter-shell" open>
                    <summary><span><i class="bi bi-funnel"></i> Filtros de clasificatorios</span><i class="bi bi-chevron-down"></i></summary>
                    <div class="event-search-filters qualifier-directory-filters">
                        <label>Buscar evento<input class="form-control" type="search" placeholder="Nombre, codigo, formato, regla o banlist" data-qualifier-directory-search></label>
                        <label>Tipo<select class="form-select" disabled><option>Clasificatorio</option></select></label>
                        <label>Estado<select class="form-select" data-qualifier-directory-status><option value="">Todos</option><?php foreach ($competitionStatusOptions as $statusCode => $statusLabel): ?><?php if ($statusCode === 'resume') { continue; } ?><option value="<?php echo e($statusCode); ?>"><?php echo e($statusLabel); ?></option><?php endforeach; ?></select></label>
                        <label>Formato<select class="form-select" data-qualifier-directory-format><option value="">Todos</option><?php foreach ($formatSelectOptions as $rulesetCode => $rulesetLabel): ?><option value="<?php echo e((string) $rulesetCode); ?>"><?php echo e((string) $rulesetLabel); ?></option><?php endforeach; ?></select></label>
                        <button class="btn btn-outline-light" type="button" data-qualifier-directory-clear><i class="bi bi-x-circle"></i> Limpiar</button>
                    </div>
                </details>
            </section>
            <section class="panel mt-3">
                <div class="panel-head"><div><h2>Tabla de clasificatorios</h2><p>Consulta estado, formato, participantes y avance antes de abrir un evento.</p></div><span class="status-chip" data-qualifier-directory-count><?php echo count($qualifierCompetitions); ?> eventos</span></div>
                <div class="table-responsive">
                    <table class="table app-table compact event-sort-table mobile-card-table qualifier-directory-table" data-qualifier-directory-table>
                        <thead><tr><th><button type="button" data-qualifier-directory-sort="name">Evento <i class="bi bi-arrow-down-up"></i></button></th><th><button type="button" data-qualifier-directory-sort="status">Estado <i class="bi bi-arrow-down-up"></i></button></th><th><button type="button" data-qualifier-directory-sort="format">Formato <i class="bi bi-arrow-down-up"></i></button></th><th>Match</th><th><button type="button" data-qualifier-directory-sort="start">Inicio <i class="bi bi-arrow-down-up"></i></button></th><th><button type="button" data-qualifier-directory-sort="participants">Participantes <i class="bi bi-arrow-down-up"></i></button></th><th>Avance</th><th>Acciones</th></tr></thead>
                        <tbody>
                        <?php foreach ($qualifierCompetitions as $competition): ?>
                            <?php
                            $directoryId = (int) ($competition['id'] ?? 0);
                            $directoryStatus = ygo_normalize_status_key((string) ($competition['status'] ?? 'pending_configuration'));
                            $directoryStatusClass = ygo_event_status_class($directoryStatus);
                            $directoryFormat = (string) (($competition['event_ruleset'] ?? '') ?: 'custom');
                            $directoryParticipants = count((array) ($competition['selected_deck_ids'] ?? []));
                            $directoryStats = $qualifierStatsByCompetition[$directoryId] ?? ['matches' => 0, 'finished' => 0];
                            $directoryStart = (string) (($competition['starts_on'] ?? '') ?: '');
                            $directorySearch = strtolower(implode(' ', [(string) ($competition['name'] ?? ''), (string) ($competition['code'] ?? ''), $directoryFormat, (string) ($competition['ruleset_version'] ?? ''), ygo_event_status_label($directoryStatus)]));
                            ?>
                            <tr class="<?php echo e($directoryStatusClass); ?>" data-qualifier-directory-row data-qualifier-status="<?php echo e($directoryStatus); ?>" data-qualifier-format="<?php echo e($directoryFormat); ?>" data-qualifier-search="<?php echo e($directorySearch); ?>" data-sort-name="<?php echo e(strtolower((string) ($competition['name'] ?? ''))); ?>" data-sort-status="<?php echo e($directoryStatus); ?>" data-sort-format="<?php echo e($directoryFormat); ?>" data-sort-start="<?php echo e($directoryStart !== '' ? $directoryStart : '9999-12-31'); ?>" data-sort-participants="<?php echo e((string) $directoryParticipants); ?>">
                                <td data-label="Evento"><div class="event-table-title"><span class="event-table-logo"><?php if (!empty($competition['logo_url'])): ?><img src="<?php echo e((string) $competition['logo_url']); ?>" alt="<?php echo e((string) ($competition['name'] ?? 'Clasificatorio')); ?>"><?php else: ?><i class="bi bi-diagram-3"></i><?php endif; ?></span><span><strong><?php echo e((string) ($competition['name'] ?? 'Clasificatorio')); ?></strong><br><small><?php echo e((string) ($competition['code'] ?? '')); ?></small></span></div></td>
                                <td data-label="Estado"><span class="status-chip <?php echo e($directoryStatusClass); ?>"><?php echo e(ygo_event_status_label($directoryStatus)); ?></span></td>
                                <td data-label="Formato"><?php echo e((string) ($formatSelectOptions[$directoryFormat] ?? strtoupper($directoryFormat))); ?><br><small><?php echo e((string) (($competition['ruleset_version'] ?? '') ?: 'Sin version')); ?></small></td>
                                <td data-label="Match"><?php echo e(strtoupper((string) (($competition['match_format'] ?? '') ?: '1v1'))); ?> | BO<?php echo e((string) (($competition['best_of'] ?? '') ?: 3)); ?></td>
                                <td data-label="Inicio"><?php echo e($directoryStart !== '' ? $directoryStart : 'Sin fecha'); ?></td>
                                <td data-label="Participantes"><?php echo e((string) $directoryParticipants); ?> decks</td>
                                <td data-label="Avance"><strong><?php echo e((string) $directoryStats['finished']); ?> / <?php echo e((string) $directoryStats['matches']); ?></strong><small class="d-block">combates finalizados</small></td>
                                <td data-label="Acciones"><button class="btn btn-outline-info btn-sm" type="button" data-qualifier-directory-open="<?php echo e((string) $directoryId); ?>"><i class="bi bi-eye"></i> Ver</button></td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (!$qualifierCompetitions): ?><tr><td colspan="8"><div class="empty-state">No hay clasificatorios registrados.</div></td></tr><?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </section>
        </div>
        <div data-qualifier-detail <?php echo $showQualifierDetail ? '' : 'hidden'; ?>>
        <header class="qualifier-workspace-header">
            <div class="qualifier-workspace-title">
                <div><span class="tag">Clasificatorio</span><span class="status-chip <?php echo $qualifierFinished ? 'ok' : ''; ?>"><?php echo e($qualifierStatusLabel); ?></span></div>
                <h1><?php echo e((string) (($displayQualifierCompetition['name'] ?? '') ?: 'Sin clasificatorio en BD')); ?></h1>
                <p><strong><?php echo e((string) (($displayQualifierCompetition['code'] ?? '') ?: 'Sin codigo')); ?></strong><span><?php echo e((string) (($displayQualifierCompetition['event_ruleset'] ?? '') ?: 'Sin reglamento')); ?></span><span><?php echo e((string) (($displayQualifierCompetition['starts_on'] ?? '') ?: 'Sin fecha')); ?></span></p>
            </div>
            <div class="qualifier-title-actions">
                <button class="btn btn-outline-light fw-bold" type="button" data-qualifier-directory-back><i class="bi bi-arrow-left"></i> Clasificatorios</button>
                <?php if ($displayQualifierId > 0): ?><button class="btn btn-outline-info fw-bold" type="button" data-event-open="<?php echo e((string) $displayQualifierId); ?>"><i class="bi bi-sliders"></i> Editar ficha</button><?php endif; ?>
                <button class="btn btn-warning fw-bold" type="button" data-bs-toggle="modal" data-bs-target="#qualifierStatusModal"><i class="bi bi-flag-fill"></i> Estado del evento</button>
                <button class="btn btn-primary fw-bold" type="button" data-route="crear_evento"><i class="bi bi-plus-circle"></i> Nuevo</button>
            </div>
        </header>
        <section class="qualifier-overview" aria-label="Resumen del clasificatorio">
            <label class="qualifier-event-picker"><span>Evento</span><select class="form-select" data-qualifier-event-switch><?php foreach ($qualifierCompetitions as $competition): ?><option value="<?php echo e((string) $competition['id']); ?>" <?php echo (int) $competition['id'] === $displayQualifierId ? 'selected' : ''; ?>><?php echo e((string) $competition['name']); ?></option><?php endforeach; ?><?php if (!$qualifierCompetitions): ?><option value="">Sin clasificatorios</option><?php endif; ?></select></label>
            <article><span>Participantes</span><strong><?php echo count($displayQualifierDecks); ?></strong><small>decks</small></article>
            <article><span>Programados</span><strong><?php echo count($displayQualifierMatches); ?></strong><small>combates</small></article>
            <article><span>Finalizados</span><strong><?php echo $displayQualifierFinishedCount; ?></strong><small>de <?php echo count($displayQualifierMatches); ?></small></article>
            <article><span>Requisito</span><strong><?php echo $displayQualifierRequiredDuels; ?></strong><small>combates por deck</small></article>
        </section>
        <section class="qualifier-view-navigation" aria-label="Vistas del evento seleccionado">
            <header class="qualifier-tab-context">
                <span>Estas tres vistas pertenecen a</span>
                <strong><?php echo e((string) (($displayQualifierCompetition['name'] ?? '') ?: 'Sin clasificatorio seleccionado')); ?></strong>
                <small><?php echo e((string) (($displayQualifierCompetition['code'] ?? '') ?: 'Sin codigo')); ?> · ID <?php echo e((string) $displayQualifierId); ?></small>
            </header>
            <nav class="qualifier-action-tabs" aria-label="Vistas de <?php echo e((string) ($displayQualifierCompetition['name'] ?? 'clasificatorio')); ?>">
                <button class="active" type="button" data-qualifier-tab="configurar"><i class="bi bi-table"></i><span>Ranking</span></button>
                <button type="button" data-qualifier-tab="jugar"><i class="bi bi-controller"></i><span>Combates</span></button>
                <button type="button" data-qualifier-tab="finalizar"><i class="bi bi-trophy"></i><span>Finalizar evento</span></button>
            </nav>
        </section>
        <section class="panel mb-3 qualifier-panel active" data-qualifier-panel="configurar">
            <div class="panel-head"><div><h2>Clasificacion del clasificatorio</h2><p>Orden oficial: puntos, diferencia de duelos, duelos ganados y siembra inicial. Cada deck debe completar <?php echo $displayQualifierRequiredDuels; ?> combates.</p></div><div class="qualifier-ranking-controls"><label>Orden<select class="form-select" data-qualifier-ranking-order><option value="asc">1 al ultimo</option><option value="desc">Ultimo al 1</option></select></label><span class="status-chip"><?php echo $qualifierReadyCount; ?> / <?php echo count($qualifierProgress); ?> con combates completos</span></div></div>
            <div class="qualifier-progress-meter"><span style="width: <?php echo count($qualifierProgress) ? round(($qualifierReadyCount / count($qualifierProgress)) * 100) : 0; ?>%"></span></div>
            <div class="table-responsive">
                <table class="table app-table compact mobile-card-table audit-mobile-table" data-qualifier-ranking-table>
                    <thead><tr><th>#</th><th>Equipo / Deck</th><th>PTS</th><th>PJ</th><th>PG / E / PP</th><th>CG / CP</th><th>DIF</th><th>Combates</th></tr></thead>
                    <tbody data-qualifier-ranking-body>
                    <?php foreach ($qualifierProgress as $row): ?>
                        <tr class="clickable-row" data-qualifier-deck-row data-deck-name="<?php echo e($row['deck']); ?>" data-qualifier-position="<?php echo e((string) $row['rank']); ?>">
                            <td data-label="#"><?php echo e((string) $row['rank']); ?></td>
                            <td data-label="Equipo / Deck"><span class="qualifier-team-identity"><span class="team-inline-badge<?php echo !empty($row['team_logo']) ? ' has-image' : ''; ?>"><?php if (!empty($row['team_logo'])): ?><img src="<?php echo e((string) $row['team_logo']); ?>" alt="<?php echo e($row['team']); ?>"><?php else: ?><?php echo e((string) ($row['code'] ?: substr($row['team'], 0, 3))); ?><?php endif; ?></span><span><strong><?php echo e($row['team']); ?></strong><small><?php echo e($row['deck']); ?></small></span></span></td>
                            <td data-label="PTS"><strong><?php echo e((string) $row['pts']); ?></strong></td>
                            <td data-label="PJ"><?php echo e((string) $row['pj']); ?></td>
                            <td data-label="PG / E / PP"><?php echo e($row['pg'] . ' / ' . $row['pe'] . ' / ' . $row['pp']); ?></td>
                            <td data-label="CG / CP"><?php echo e($row['cg'] . ' / ' . $row['cp']); ?></td>
                            <td data-label="DIF"><strong><?php echo $row['dp'] > 0 ? '+' : ''; ?><?php echo e((string) $row['dp']); ?></strong></td>
                            <td data-label="Combates"><div class="qualifier-combat-progress <?php echo $row['ready'] ? 'is-complete' : 'is-pending'; ?>"><strong><?php echo e((string) $row['played']); ?> / <?php echo $displayQualifierRequiredDuels; ?></strong><small><?php echo $row['ready'] ? 'Completo' : 'Faltan ' . e((string) $row['remaining']); ?></small><span><i style="width:<?php echo e((string) min(100, round(($row['played'] / max(1, $displayQualifierRequiredDuels)) * 100))); ?>%"></i></span></div></td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (!$qualifierProgress): ?><tr><td colspan="8"><div class="empty-state">El clasificatorio no tiene decks seleccionados en base de datos.</div></td></tr><?php endif; ?>
                    </tbody>
                </table>
            </div>
        </section>
        <div class="qualifier-stage">
                <section class="panel qualifier-panel" data-qualifier-panel="jugar" hidden>
                    <div class="panel-head">
                        <div><h2>Combates del clasificatorio</h2><p>Consulta la programacion, filtra y abre un combate para registrar o revisar su resultado D1-D5.</p></div>
                        <span class="status-chip"><?php echo count($displayQualifierMatches); ?> combates</span>
                    </div>
                    <div class="result-filter-grid mb-3">
                        <input class="form-control" data-qualifier-match-search placeholder="Buscar por deck, equipo, ID, ronda, mesa o fecha">
                        <select class="form-select" data-qualifier-match-status>
                            <option value="">Todo estado</option>
                            <option value="PENDIENTE">Pendiente</option>
                            <option value="EN CURSO">En curso</option>
                            <option value="FINALIZADA">Finalizada</option>
                        </select>
                        <select class="form-select" data-qualifier-match-round><option value="">Todas las rondas</option></select>
                        <select class="form-select" data-qualifier-match-order><option value="pending_first">Pendientes primero</option><option value="finished_first">Finalizados primero</option><option value="date_asc">Fecha: menor a mayor</option><option value="date_desc">Fecha: mayor a menor</option><option value="round_asc">Ronda: menor a mayor</option><option value="round_desc">Ronda: mayor a menor</option></select>
                        <button class="btn btn-outline-light" type="button" data-qualifier-clear-filters><i class="bi bi-arrow-counterclockwise"></i> Limpiar</button>
                    </div>
                    <div class="table-responsive">
                        <table class="table app-table compact">
                            <thead><tr><th>Fecha</th><th>Hora</th><th>Ronda</th><th>Mesa</th><th>Combate</th><th>Estado</th><th>Resultado</th><th></th></tr></thead>
                            <tbody data-qualifier-match-list>
                            <?php foreach ($displayQualifierMatches as $match): ?>
                                <tr class="clickable-row" data-qualifier-match-row data-match-id="<?php echo e((string) $match['id']); ?>">
                                    <td><?php echo e((string) (($match['scheduled_on'] ?? '') ?: 'Sin fecha')); ?></td>
                                    <td><?php echo e((string) (!empty($match['starts_at']) ? substr((string) $match['starts_at'], 0, 5) : '')); ?></td>
                                    <td>R<?php echo e((string) ($match['round_number'] ?? '')); ?></td>
                                    <td>M<?php echo e((string) ($match['mesa'] ?? '')); ?></td>
                                    <td><?php echo e($deckMatchLabel((string) ($match['a'] ?? '')) . ' vs ' . $deckMatchLabel((string) ($match['b'] ?? ''))); ?></td>
                                    <td><span class="status-chip"><?php echo e((string) ($match['estado'] ?? 'PENDIENTE')); ?></span></td>
                                    <td><?php echo e((string) (($match['score'] ?? '') ?: '-')); ?></td>
                                    <td><button class="btn btn-outline-info btn-sm" type="button" data-open-match-sheet data-match-id="<?php echo e((string) $match['id']); ?>"><i class="bi bi-eye"></i> Ficha</button></td>
                                </tr>
                            <?php endforeach; ?>
                            <?php if (!$displayQualifierMatches): ?><tr><td colspan="8"><div class="empty-state">Este clasificatorio no tiene duelos programados.</div></td></tr><?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </section>
                <section class="panel qualifier-panel" data-qualifier-panel="finalizar" hidden>
                    <div class="panel-head">
                        <div><h2>Finalizar <?php echo e((string) ($displayQualifierCompetition['name'] ?? 'clasificatorio')); ?></h2><p>Esta vista comprueba que todos los combates y requisitos estén completos antes de cerrar definitivamente el evento. No sirve para capturar resultados.</p></div>
                        <span class="status-chip <?php echo $qualifierFinalizeCheck['ok'] ? 'ok' : 'warn'; ?>"><?php echo $qualifierFinalizeCheck['ok'] ? 'Listo para cerrar' : 'Cierre bloqueado'; ?></span>
                    </div>
                    <div class="rule-note mb-3 <?php echo $qualifierFinalizeCheck['ok'] ? 'ok' : 'warn'; ?>">
                        <i class="bi <?php echo $qualifierFinalizeCheck['ok'] ? 'bi-unlock-fill' : 'bi-lock-fill'; ?>"></i>
                        <p><?php echo e($qualifierFinalizeCheck['ok'] ? 'Todos los requisitos de este evento estan completos.' : implode(' ', $qualifierFinalizeCheck['reasons'])); ?></p>
                    </div>
                    <?php if ($dbStatus['connected']): ?>
                        <form method="post" class="qualifier-finalize-action">
                            <input type="hidden" name="action" value="set_qualifier_status">
                            <input type="hidden" name="qualifier_competition_id" value="<?php echo e((string) $displayQualifierId); ?>">
                            <button class="btn btn-warning fw-bold" name="status" value="completed" type="submit" <?php echo $qualifierFinalizeCheck['ok'] ? '' : 'disabled'; ?>><i class="bi bi-flag-fill"></i> Finalizar clasificatorio</button>
                            <small><?php echo $qualifierFinalizeCheck['ok'] ? 'El cierre guardara la clasificacion definitiva de este evento.' : 'El boton se habilitara cuando se completen los requisitos indicados.'; ?></small>
                        </form>
                    <?php endif; ?>
                    <div class="table-responsive">
                        <table class="table app-table compact qualifier-completion-table mobile-card-table" data-qualifier-finalize-table>
                            <thead><tr><th>Equipo / Deck</th><th>Cumplimiento de combates</th></tr></thead>
                            <tbody>
                            <?php foreach ($qualifierProgress as $row): ?>
                                <tr class="<?php echo $row['ready'] ? 'is-complete' : 'is-pending'; ?>"><td data-label="Equipo / Deck"><span class="qualifier-team-identity"><span class="team-inline-badge<?php echo !empty($row['team_logo']) ? ' has-image' : ''; ?>"><?php if (!empty($row['team_logo'])): ?><img src="<?php echo e((string) $row['team_logo']); ?>" alt="<?php echo e($row['team']); ?>"><?php else: ?><?php echo e((string) ($row['code'] ?: substr($row['team'], 0, 3))); ?><?php endif; ?></span><span><strong><?php echo e($row['team']); ?></strong><small><?php echo e($row['deck']); ?></small></span></span></td><td data-label="Cumplimiento"><span class="qualifier-completion-badge <?php echo $row['ready'] ? 'is-complete' : 'is-pending'; ?>"><i class="bi <?php echo $row['ready'] ? 'bi-check-circle-fill' : 'bi-hourglass-split'; ?>"></i><strong><?php echo e((string) $row['played']); ?> / <?php echo $displayQualifierRequiredDuels; ?></strong><small><?php echo $row['ready'] ? 'Todos los combates completos' : 'Faltan ' . e((string) $row['remaining']) . ' ' . ($row['remaining'] === 1 ? 'combate' : 'combates'); ?></small></span></td></tr>
                            <?php endforeach; ?>
                            <?php if (!$qualifierProgress): ?><tr><td colspan="2"><div class="empty-state">Este clasificatorio no tiene participantes.</div></td></tr><?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </section>
        </div>
        </div>
    </section>

    <section class="screen" data-screen="divisiones" hidden>
        <div class="page-title with-actions module-title">
            <div><h1><?php echo e($uiText('route.divisiones', 'title', 'Divisiones')); ?></h1><p>Divisiones del modulo de eventos: agrupan equipos/decks por liga, origen clasificatorio, rango y movimientos de ascenso/descenso.</p></div>
            <button class="btn btn-primary fw-bold" type="button" data-open-gestion-module="divisiones"><i class="bi bi-plus-circle"></i> Crear / editar division</button>
        </div>
        <div class="module-kpi-strip mb-3">
            <article><i class="bi bi-trophy"></i><span>Divisiones BD</span><b><?php echo number_format(count($displayDivisions)); ?></b></article>
            <article><i class="bi bi-collection"></i><span>Decks activos</span><b><?php echo number_format(count($allDecks)); ?></b></article>
            <article><i class="bi bi-diagram-3"></i><span>Eventos</span><b><?php echo number_format(count($competitions)); ?></b></article>
            <article><i class="bi bi-calendar2-week"></i><span>Combates</span><b><?php echo number_format($totalFixtureMatches); ?></b></article>
        </div>
        <section class="panel divisions-crud-panel mb-3">
            <div class="panel-head"><div><h2>Tabla de divisiones</h2><p>Consulta, filtra y abre la ficha de cada divisi?n y sus decks inscritos.</p></div><span class="status-chip" data-division-visible-count><?php echo count($displayDivisions); ?> divisiones</span></div>
            <div class="event-filter-row">
                <label>Buscar<input class="form-control" type="search" data-division-search placeholder="Nombre, codigo, copa o descripcion"></label>
                <label>Minimo<select class="form-select" data-division-min-filter><option value="">Todas</option><option value="ready">Con minimo 4 decks</option><option value="missing">Faltan decks</option></select></label>
                <button class="btn btn-outline-light" type="button" data-division-filter-clear><i class="bi bi-x-circle"></i> Limpiar</button>
            </div>
            <div class="table-responsive">
                <table class="table app-table compact division-crud-table">
                    <thead><tr><th>Division</th><th>Liga</th><th>Origen</th><th>Equipos</th><th>Movimiento</th><th>Creacion</th><th>Acciones</th></tr></thead>
                    <tbody>
                    <?php foreach ($displayDivisions as $divisionKey => $division): ?>
                        <?php
                            $divisionDeckCount = count((array) ($division['decks'] ?? []));
                            $divisionReady = $divisionDeckCount >= 4;
                            $divisionSearch = strtolower((string) (($division['short'] ?? '') . ' ' . ($division['name'] ?? '') . ' ' . ($division['cup'] ?? '') . ' ' . ($division['description'] ?? '')));
                            $targetName = static function (?int $targetId) use ($displayDivisions): string {
                                foreach ($displayDivisions as $candidate) {
                                    if ((int) ($candidate['id'] ?? 0) === (int) $targetId) {
                                        return (string) ($candidate['short'] ?? $candidate['name'] ?? 'Division');
                                    }
                                }
                                return 'Sin destino';
                            };
                            $competitionNameById = static function (?int $competitionId, string $fallback = 'Modo libre / sin liga') use ($competitions): string {
                                foreach ($competitions as $competition) {
                                    if ((int) ($competition['id'] ?? 0) === (int) $competitionId) {
                                        return (string) ($competition['name'] ?? 'Evento');
                                    }
                                }
                                return $fallback;
                            };
                            $divisionLogo = ygo_media_src((string) ($division['logo_url'] ?? ''));
                            $rankRange = ($division['rank_from'] ?? null) && ($division['rank_to'] ?? null)
                                ? 'Pos. ' . (int) $division['rank_from'] . ' a ' . (int) $division['rank_to']
                                : 'Manual';
                        ?>
                        <tr data-division-row data-division-id="<?php echo e((string) ($division['id'] ?? '')); ?>" data-division-min="<?php echo $divisionReady ? 'ready' : 'missing'; ?>" data-division-search="<?php echo e($divisionSearch); ?>">
                            <td data-label="Division">
                                <span class="division-table-identity">
                                    <span class="division-table-logo"><?php echo $divisionLogo !== '' ? '<img src="' . e($divisionLogo) . '" alt="' . e((string) ($division['name'] ?? 'Division')) . '">' : e((string) ($division['short'] ?? 'DV')); ?></span>
                                    <span><strong><?php echo e((string) ($division['name'] ?? 'Division')); ?></strong><br><small><?php echo e((string) ($division['short'] ?? '')); ?> | <?php echo e((string) ($division['cup'] ?? '')); ?></small></span>
                                </span>
                            </td>
                            <td data-label="Liga"><?php echo e($competitionNameById($division['league_competition_id'] ?? null)); ?></td>
                            <td data-label="Origen"><small><?php echo e($competitionNameById($division['source_qualifier_id'] ?? null, 'Manual / sin clasificatorio')); ?><br><?php echo e($rankRange); ?></small></td>
                            <td data-label="Equipos"><?php echo $divisionDeckCount; ?> / <?php echo max(0, (int) ($division['deck_count'] ?? 0)); ?> objetivo<br><span class="status-chip <?php echo $divisionReady ? 'state-active' : 'state-pending-config'; ?>"><?php echo $divisionReady ? 'Lista' : 'Faltan equipos'; ?></span></td>
                            <td data-label="Movimiento"><small>Suben <?php echo (int) ($division['promotion_slots'] ?? 0); ?> a <?php echo e($targetName($division['promotion_to_division_id'] ?? null)); ?><br>Bajan <?php echo (int) ($division['relegation_slots'] ?? 0); ?> a <?php echo e($targetName($division['relegation_to_division_id'] ?? null)); ?></small></td>
                            <td data-label="Creacion"><?php echo e(substr((string) ($division['created_at'] ?? ''), 0, 10) ?: 'Sin fecha'); ?></td>
                            <td data-label="Acciones"><div class="division-row-actions"><button class="btn btn-outline-info btn-sm" type="button" data-division-open="<?php echo e((string) $divisionKey); ?>" data-division-scroll><i class="bi bi-eye"></i> Ver ficha</button><button class="btn btn-outline-light btn-sm" type="button" data-open-gestion-module="divisiones" data-gestion-division-id="<?php echo e((string) ($division['id'] ?? '')); ?>"><i class="bi bi-pencil-square"></i> Editar</button></div></td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (!$displayDivisions): ?><tr><td colspan="7"><div class="empty-state">No hay divisiones registradas en base de datos.</div></td></tr><?php endif; ?>
                    </tbody>
                </table>
            </div>
        </section>
        <?php if (!$qualifierFinished): ?><div class="alert alert-info">El clasificatorio sigue abierto. Estos formatos aun no son resultado final; se llenan oficialmente al cerrar el clasificatorio.</div><?php endif; ?>
        <div class="division-selector-grid mb-3" hidden>
            <?php foreach ($displayDivisions as $key => $division): ?>
                <?php
                $divisionDecks = $qualifierFinished ? ($division['decks'] ?? []) : [];
                $divisionRounds = $qualifierFinished ? ($fixtures[$key] ?? []) : [];
                $divisionMatches = array_sum(array_map(static fn(array $round): int => count($round['matches'] ?? []), $divisionRounds));
                ?>
                <button class="panel division-panel division-entry" type="button" data-division-open="<?php echo e($key); ?>" style="--accent: <?php echo e($division['accent']); ?>">
                    <span><?php echo e($division['short']); ?></span>
                    <strong><?php echo e($division['cup']); ?></strong>
                    <small><?php echo count($divisionDecks); ?> decks | <?php echo $divisionMatches; ?> combates</small>
                    <em><?php echo $qualifierFinished ? 'Oficial' : 'Provisional'; ?></em>
                </button>
            <?php endforeach; ?>
        </div>
        <?php foreach ($displayDivisions as $detailKey => $division): ?>
            <?php
                $divisionDecks = $qualifierFinished ? ($division['decks'] ?? []) : [];
                $divisionRounds = $qualifierFinished ? ($fixtures[$detailKey] ?? []) : [];
                $divisionCompetitions = array_values(array_filter($competitions, static fn(array $competition): bool => (string) ($competition['division_code'] ?? '') === $detailKey));
            $divisionMatches = array_sum(array_map(static fn(array $round): int => count($round['matches'] ?? []), $divisionRounds));
            $scheduledMatches = 0;
            foreach ($divisionRounds as $round) {
                foreach (($round['matches'] ?? []) as $match) {
                    if (!empty($match['scheduled_on'])) {
                        $scheduledMatches++;
                    }
                }
            }
            ?>
            <section class="division-workbench" data-division-detail="<?php echo e($detailKey); ?>" hidden>
                <div class="division-hero panel" style="--accent: <?php echo e($division['accent']); ?>">
                    <div>
                        <span class="tag"><?php echo $qualifierFinished ? 'Formato oficial' : 'Vista pendiente'; ?></span>
                        <h2><?php echo e($division['name']); ?></h2>
                        <p><?php echo e($division['description']); ?></p>
                        <button class="btn btn-outline-light btn-sm mt-3" type="button" data-open-gestion-module="divisiones" data-gestion-division-id="<?php echo e((string) ($division['id'] ?? '')); ?>"><i class="bi bi-pencil-square"></i> Editar formato</button>
                    </div>
                    <div class="division-badge"><?php echo e($division['short']); ?></div>
                </div>
                <div class="mini-grid mb-3">
                    <span><b>Decks</b><br><?php echo count($divisionDecks); ?></span>
                    <span><b>Rondas</b><br><?php echo count($divisionRounds); ?></span>
                    <span><b>Combates</b><br><?php echo $divisionMatches; ?></span>
                    <span><b>Programados</b><br><?php echo $scheduledMatches; ?></span>
                    <span><b>Copa</b><br><?php echo e($division['cup']); ?></span>
                    <span><b>Estado</b><br><?php echo $qualifierFinished ? 'Oficial' : 'Provisional'; ?></span>
                </div>
                <section class="panel division-category-hub mb-3">
                    <div class="panel-head"><div><h2>Explorar division</h2><p>Abre solo la informacion que necesitas consultar.</p></div><span class="status-chip">4 secciones</span></div>
                    <div class="division-category-grid">
                        <button type="button" data-division-category-open="decks" data-division-category-owner="<?php echo e($detailKey); ?>"><i class="bi bi-collection"></i><span><b>Decks y equipos</b><small><?php echo count($divisionDecks); ?> participantes</small></span><i class="bi bi-chevron-right"></i></button>
                        <button type="button" data-division-category-open="processes" data-division-category-owner="<?php echo e($detailKey); ?>"><i class="bi bi-diagram-3"></i><span><b>Procesos</b><small>Tabla, calendario y resultados</small></span><i class="bi bi-chevron-right"></i></button>
                        <button type="button" data-division-category-open="events" data-division-category-owner="<?php echo e($detailKey); ?>"><i class="bi bi-trophy"></i><span><b>Eventos asociados</b><small><?php echo count($divisionCompetitions); ?> registrados</small></span><i class="bi bi-chevron-right"></i></button>
                        <button type="button" data-division-category-open="matches" data-division-category-owner="<?php echo e($detailKey); ?>"><i class="bi bi-calendar2-week"></i><span><b>Combates</b><small><?php echo $divisionMatches; ?> en el fixture</small></span><i class="bi bi-chevron-right"></i></button>
                    </div>
                </section>
                <div class="division-process-grid">
                    <section class="panel" data-division-category-source="decks" data-division-category-owner="<?php echo e($detailKey); ?>">
                        <div class="panel-head"><div><h2>Decks y equipos</h2><p>Informacion aislada de <?php echo e($division['short']); ?>.</p></div><button class="btn btn-outline-light btn-sm" type="button" data-route="deck">Abrir fichas</button></div>
                        <div class="table-responsive">
                            <table class="table app-table compact division-deck-table">
                                <thead><tr><th>Seed</th><th>Deck</th><th>Equipo</th><th>Arquetipo</th><th>Estilo</th></tr></thead>
                                <tbody>
                                <?php foreach ($divisionDecks as $deck): ?>
                                    <tr><td><?php echo e((string) $deck[0]); ?></td><td><?php echo e($deck[1]); ?></td><td><span class="team-inline-badge" style="--team-primary: <?php echo e((string) ($deck['team_primary_color'] ?? '#36d8ff')); ?>; --team-secondary: <?php echo e((string) ($deck['team_secondary_color'] ?? '#ffd25f')); ?>"><?php echo e((string) (($deck['team_code'] ?? '') ?: ($deck['team_symbol'] ?? '') ?: substr((string) ($deck['team'] ?? 'YMD'), 0, 3))); ?></span> <?php echo e((string) ($deck['team'] ?? $deck[1] . ' Club')); ?></td><td><?php echo e((string) ($deck['archetype'] ?? $deck[1])); ?></td><td><?php echo e((string) ($deck['style'] ?? 'Competitivo')); ?></td></tr>
                                <?php endforeach; ?>
                                <?php if (!$divisionDecks): ?><tr><td colspan="5"><div class="empty-state">Sin participantes oficiales. Esta categoria se llena cuando se cierre el clasificatorio.</div></td></tr><?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </section>
                    <section class="panel" data-division-category-source="processes" data-division-category-owner="<?php echo e($detailKey); ?>">
                    <div class="panel-head"><div><h2>Procesos del formato</h2><p>Acciones operativas sin salir del contexto de <?php echo e($division['short']); ?>.</p></div></div>
                        <div class="process-list">
                            <button type="button" data-route="tabla"><i class="bi bi-table"></i><span>Ver tabla y zonas</span></button>
                            <button type="button" data-route="calendario"><i class="bi bi-calendar2-week"></i><span>Programar combates</span></button>
                            <button type="button" data-route="resultados"><i class="bi bi-clipboard2-check"></i><span>Registrar resultados</span></button>
                            <button type="button" data-route="copas"><i class="bi bi-trophy"></i><span>Revisar copa asociada</span></button>
                            <button type="button" data-open-gestion-module="competiciones"><i class="bi bi-diagram-3-fill"></i><span>Gestionar torneos de este formato</span></button>
                        </div>
                        <?php if (!$qualifierFinished): ?><p class="note mt-3">Estas acciones quedan preparadas, pero la categoria se vuelve oficial solo cuando el clasificatorio se cierre desde Gestion.</p><?php endif; ?>
                    </section>
                </div>
                <section class="panel mt-3" data-division-category-source="events" data-division-category-owner="<?php echo e($detailKey); ?>">
                    <div class="panel-head"><div><h2>Copas y torneos de <?php echo e($division['short']); ?></h2><p>Eventos asociados a esta categoria. Los globales se gestionan desde Eventos.</p></div><button class="btn btn-outline-warning btn-sm" type="button" data-open-gestion-module="competiciones"><i class="bi bi-plus-circle"></i> Crear copa/torneo</button></div>
                    <div class="competition-mini-grid">
                        <?php foreach ($divisionCompetitions as $competition): ?>
                            <article style="--accent: <?php echo e((string) (($competition['accent'] ?? '') ?: $division['accent'])); ?>">
                                <span class="tag"><?php echo e(strtoupper((string) ($competition['type'] ?? 'torneo'))); ?></span>
                                <h3><?php echo e((string) ($competition['name'] ?? 'Evento')); ?></h3>
                                <p><?php echo e((string) (($competition['format'] ?? '') ?: 'Formato editable')); ?> | <?php echo e((string) (($competition['match_format'] ?? '') ?: '1v1')); ?> | mejor de <?php echo e((string) (($competition['best_of'] ?? '') ?: '5')); ?></p>
                                <small><?php echo e((string) (($competition['status'] ?? '') ?: 'draft')); ?> | <?php echo e((string) (($competition['schedule_strategy'] ?? '') ?: 'manual')); ?></small>
                            </article>
                        <?php endforeach; ?>
                        <?php if (!$divisionCompetitions): ?><div class="empty-state">Esta categoria aun no tiene copas/torneos asociados. Puedes crearlos desde Gestion editable.</div><?php endif; ?>
                    </div>
                </section>
                <section class="panel mt-3" data-division-category-source="matches" data-division-category-owner="<?php echo e($detailKey); ?>">
                    <div class="panel-head"><div><h2>Combates de la categoria</h2><p>Fixture, fechas y estado solo de <?php echo e($division['short']); ?>.</p></div><span class="status-chip"><?php echo $divisionMatches; ?> combates</span></div>
                    <div class="table-responsive">
                        <table class="table app-table compact division-match-table">
                            <thead><tr><th>Ronda</th><th>Mesa</th><th>Combate</th><th>Fecha</th><th>Hora</th><th>Estado</th></tr></thead>
                            <tbody>
                            <?php foreach (array_slice($divisionRounds, 0, 8) as $round): ?>
                                <?php foreach (($round['matches'] ?? []) as $match): ?>
                                    <tr><td><?php echo e((string) $round['ronda']); ?></td><td><?php echo e((string) $match['mesa']); ?></td><td><?php echo e($deckMatchLabel((string) $match['a']) . ' vs ' . $deckMatchLabel((string) $match['b'])); ?></td><td><?php echo e((string) ($match['scheduled_on'] ?? 'Pendiente')); ?></td><td><?php echo e((string) ($match['starts_at'] ?? '')); ?></td><td><?php echo e((string) ($match['estado'] ?? 'PENDIENTE')); ?></td></tr>
                                <?php endforeach; ?>
                            <?php endforeach; ?>
                            <?php if (!$divisionRounds): ?><tr><td colspan="6"><div class="empty-state">Sin calendario oficial de categoria. Primero finaliza el clasificatorio.</div></td></tr><?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </section>
            </section>
        <?php endforeach; ?>
        <div data-division-category-staging hidden></div>
        <div class="modal fade dml-modal profile-category-modal division-category-modal" id="divisionCategoryModal" tabindex="-1" aria-hidden="true" data-division-category-modal>
            <div class="modal-dialog modal-xl modal-dialog-scrollable">
                <div class="modal-content">
                    <div class="modal-header">
                        <div><span class="tag" data-division-category-context>Ficha de division</span><h2 class="modal-title" data-division-category-title>Detalle</h2><p data-division-category-subtitle></p></div>
                        <button class="btn-close btn-close-white" type="button" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                    </div>
                    <div class="modal-body" data-division-category-body></div>
                    <div class="modal-footer profile-category-modal-footer">
                        <button class="btn btn-outline-light" type="button" data-division-category-prev title="Seccion anterior"><i class="bi bi-chevron-left"></i><span>Anterior</span></button>
                        <span data-division-category-position>1 de 4</span>
                        <button class="btn btn-outline-info" type="button" data-division-category-next title="Seccion siguiente"><span>Siguiente</span><i class="bi bi-chevron-right"></i></button>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section class="screen" data-screen="calendario" hidden>
        <div class="page-title with-actions">
            <div><h1><?php echo e($uiText('route.calendario', 'title', 'Calendario')); ?></h1><p><?php echo e($uiText('route.calendario', 'subtitle', 'Consulta eventos, jornadas y combates persistidos en base de datos.')); ?></p></div>
            <span class="status-chip" data-calendar-count>0 registros</span>
        </div>
        <div class="calendar-profile-scope" data-calendar-profile-scope hidden>
            <span class="calendar-profile-scope-icon"><i class="bi bi-calendar3"></i></span>
            <span><small data-calendar-profile-kind>Calendario filtrado</small><strong data-calendar-profile-label></strong></span>
            <button class="btn btn-outline-light btn-sm" type="button" data-calendar-profile-clear><i class="bi bi-x-lg"></i> Ver calendario general</button>
        </div>
        <section class="panel calendar-panel mb-3">
            <div class="panel-head calendar-panel-head"><div><h2>Agenda general</h2><p>Usa filtros, cambia de vista y entra al detalle del dia sin salir del calendario.</p></div></div>
            <div class="calendar-toolbar">
                <div class="calendar-month-controls">
                    <button class="icon-button" type="button" data-calendar-prev title="Mes anterior"><i class="bi bi-chevron-left"></i></button>
                    <?php
                    $calendarTitleDate = new DateTimeImmutable('first day of this month');
                    $calendarMonths = ['01' => 'enero', '02' => 'febrero', '03' => 'marzo', '04' => 'abril', '05' => 'mayo', '06' => 'junio', '07' => 'julio', '08' => 'agosto', '09' => 'septiembre', '10' => 'octubre', '11' => 'noviembre', '12' => 'diciembre'];
                    ?>
                    <strong data-calendar-title><?php echo e($calendarMonths[$calendarTitleDate->format('m')] . ' ' . $calendarTitleDate->format('Y')); ?></strong>
                    <button class="icon-button" type="button" data-calendar-next title="Mes siguiente"><i class="bi bi-chevron-right"></i></button>
                    <button class="btn btn-outline-light" type="button" data-calendar-today>Hoy</button>
                </div>
                <div class="calendar-view-tabs" role="tablist" aria-label="Vistas de calendario">
                    <button class="active" type="button" data-calendar-view="month">Mes</button>
                    <button type="button" data-calendar-view="week">Semana</button>
                    <button type="button" data-calendar-view="day">Dia</button>
                    <button type="button" data-calendar-view="year">Ano</button>
                    <button type="button" data-calendar-view="agenda">Agenda</button>
                </div>
                <div class="calendar-filters">
                    <select class="form-select" data-calendar-type><option value="">Todas las categorias de evento</option><?php foreach ($competitionTypeOptions as $eventType => $eventTypeMeta): ?><option value="<?php echo e($eventType); ?>"><?php echo e((string) $eventTypeMeta['label']); ?></option><?php endforeach; ?></select>
                    <select class="form-select" data-calendar-division><option value="">Todos los formatos</option><option value="CLASIFICATORIO">CLASIFICATORIO</option><?php foreach ($displayDivisions as $key => $division): ?><option value="<?php echo e((string) (($division['code'] ?? '') ?: $key)); ?>"><?php echo e($division['short']); ?></option><?php endforeach; ?></select>
                    <input class="form-control" data-calendar-search placeholder="Buscar evento, deck, copa o ronda">
                </div>
            </div>
            <div class="calendar-create-toolbar mt-3">
                <button class="btn btn-primary fw-bold" type="button" data-bs-toggle="modal" data-bs-target="#friendlyMatchModal"><i class="bi bi-plus-circle"></i> Crear combate amistoso</button>
                <span>Combate independiente: no pertenece a ningun evento, pero queda programado en calendario.</span>
            </div>
            <div class="calendar-layout mt-3">
                <div class="calendar-main">
                    <div class="fullcalendar-shell" data-fullcalendar hidden></div>
                    <div class="calendar-weekdays calendar-fallback" data-calendar-weekdays><span>Lun</span><span>Mar</span><span>Mie</span><span>Jue</span><span>Vie</span><span>Sab</span><span>Dom</span></div>
                    <div class="calendar-month calendar-fallback" data-calendar-grid>
                        <?php
                        $calendarFirst = new DateTimeImmutable('first day of this month');
                        $calendarStart = $calendarFirst->modify('-' . ((int) $calendarFirst->format('N') - 1) . ' days');
                        $calendarMonth = $calendarFirst->format('m');
                        $calendarToday = (new DateTimeImmutable())->format('Y-m-d');
                        for ($i = 0; $i < 42; $i++):
                            $calendarDay = $calendarStart->modify('+' . $i . ' days');
                            $calendarIso = $calendarDay->format('Y-m-d');
                            $calendarClasses = trim(($calendarDay->format('m') !== $calendarMonth ? ' outside' : '') . ($calendarIso === $calendarToday ? ' selected' : ''));
                        ?>
                            <button class="calendar-day <?php echo e($calendarClasses); ?>" type="button" data-calendar-day="<?php echo e($calendarIso); ?>"><b><?php echo e($calendarDay->format('j')); ?></b><span class="calendar-day-action">Ver dia</span></button>
                        <?php endfor; ?>
                    </div>
                </div>
                <aside class="calendar-side">
                    <div class="panel-head"><div><h2>Detalle del dia</h2><p>Jornadas y combates de la fecha seleccionada.</p></div></div>
                    <div class="calendar-day-detail" data-calendar-detail></div>
                </aside>
            </div>
            <div class="calendar-legend">
                <span><i style="--accent:#2f80ed"></i> Fecha incompleta</span>
                <span><i style="--accent:#22c97a"></i> Fecha completa</span>
                <span><i style="--accent:#8a8f9c"></i> Sin combates</span>
            </div>
        </section>
        <div class="control-strip">
            <select class="form-select" data-fixture-competition aria-label="Evento activo"><option value="">Cargando eventos activos...</option></select>
            <select class="form-select" data-fixture-round aria-label="Ronda del evento"></select>
            <span class="status-chip" data-fixture-bye></span>
        </div>
        <section class="panel"><div class="table-responsive"><table class="table app-table"><thead><tr><th>Mesa</th><th>Deck A</th><th>Deck B</th><th>Estado</th></tr></thead><tbody data-fixture-body></tbody></table></div></section>
    </section>

    <section class="screen" data-screen="resultados" hidden>
        <div class="page-title"><h1><?php echo e($uiText('route.resultados', 'title', 'Captura de resultados')); ?></h1><p><?php echo e($uiText('route.resultados', 'subtitle', 'D1-D5 con cierre automatico, validacion, auditoria y guardado en MySQL.')); ?></p></div>
        <section class="panel result-queue-panel">
            <div class="panel-head"><div><h2>Centro de partidas</h2><p>Busca, filtra y selecciona el combate que vas a capturar.</p></div><span class="status-chip" data-result-filter-count>0 visibles</span></div>
            <div class="result-filter-grid">
                <label class="result-filter-search"><span>Buscar</span><input class="form-control" data-result-search placeholder="Deck, equipo, ronda, mesa o fecha"></label>
                <label><span>Fase</span><select class="form-select" data-result-phase>
                    <option value="">Todas las fases</option>
                    <option value="qualifier">Clasificatorio</option>
                    <option value="league">Liga</option>
                    <option value="cup">Copa</option>
                    <option value="world">Mundial</option>
                    <option value="events">Eventos</option>
                    <option value="friendly">Amistoso</option>
                </select></label>
                <label><span>Evento activo</span><select class="form-select" data-result-event-filter aria-label="Filtrar por evento activo"><option value="">Todos los eventos</option></select></label>
                <label><span>División</span><select class="form-select" data-result-league-division aria-label="Filtrar por división de liga"><option value="">Todas las divisiones</option></select></label>
                <label><span>Estado</span><select class="form-select" data-result-status>
                    <option value="">Todo estado</option>
                    <option value="PENDIENTE">Pendiente</option>
                    <option value="EN CURSO">En curso</option>
                    <option value="FINALIZADA">Finalizada</option>
                </select></label>
                <label><span>Programación</span><select class="form-select" data-result-schedule-filter aria-label="Filtrar por programación"><option value="all">Toda la programación</option><option value="overdue">Vencido</option><option value="today">Hoy</option><option value="future">Fecha futura</option><option value="unscheduled">Sin fecha</option></select></label>
                <label><span>Ronda</span><select class="form-select" data-result-round-filter><option value="">Todas las rondas</option></select></label>
                <label><span>Orden</span><select class="form-select" data-result-sort aria-label="Ordenar resultados por fecha">
                    <option value="desc" selected>Fecha: mayor a menor</option>
                    <option value="asc">Fecha: menor a mayor</option>
                </select></label>
                <label><span>Periodo</span><select class="form-select" data-result-period aria-label="Periodo de consulta"><option value="all">Todos</option><option value="today">Hoy</option><option value="day">Por dia</option><option value="month">Por mes</option><option value="range">Rango de fechas</option></select></label>
                <label data-result-period-field="day" hidden><span>Dia (opcional)</span><input class="form-control" type="date" data-result-date aria-label="Filtrar por dia"></label>
                <label data-result-period-field="month" hidden><span>Mes (opcional)</span><input class="form-control" type="month" data-result-month aria-label="Filtrar por mes"></label>
                <label data-result-period-field="range" hidden><span>Desde (opcional)</span><input class="form-control" type="date" data-result-date-from aria-label="Fecha desde"></label>
                <label data-result-period-field="range" hidden><span>Hasta (opcional)</span><input class="form-control" type="date" data-result-date-to aria-label="Fecha hasta"></label>
                <p class="text-warning mb-0" data-result-period-error role="status" hidden>La fecha desde no puede ser posterior a la fecha hasta.</p>
                <button class="btn btn-outline-light result-filter-clear" type="button" data-result-clear-filters><i class="bi bi-x-circle"></i> Limpiar</button>
            </div>
            <div class="table-responsive result-queue-wrap">
                <table class="table app-table compact result-queue-table mobile-card-table">
                    <thead><tr><th>Programacion</th><th>Enfrentamiento</th><th>Estado</th><th>Marcador</th><th>Accion</th></tr></thead>
                    <tbody data-result-queue></tbody>
                </table>
            </div>
        </section>
        <section class="panel match-center mt-3" hidden>
            <form method="post">
                <input type="hidden" name="action" value="save_match_result">
                <input type="hidden" name="match_id" data-result-match-id>
                <div class="control-strip"><select class="form-select" data-result-division><option value="">Todas las categorias/fases</option><?php foreach ($divisions as $key => $division): ?><option value="<?php echo e($key); ?>"><?php echo e($division['short']); ?></option><?php endforeach; ?></select><select class="form-select" data-result-match></select><button class="btn btn-outline-light" type="button" data-clear-result><i class="bi bi-arrow-counterclockwise"></i> Limpiar</button></div>
                <div class="match-scoreboard">
                    <div><span class="duelist-avatar">A</span><small>Jugador A</small><strong data-result-a></strong></div>
                    <b>VS</b>
                    <div><span class="duelist-avatar">B</span><small>Jugador B</small><strong data-result-b></strong></div>
                </div>
                <div class="match-body">
                    <div>
                        <div class="duel-grid my-3"><?php for ($i = 1; $i <= 5; $i++): ?><label><span>D<?php echo $i; ?></span><select class="form-select" name="d<?php echo $i; ?>" data-duel="<?php echo $i; ?>"><option value="">-</option><option value="A">A</option><option value="E">E</option><option value="B">B</option></select></label><?php endfor; ?></div>
                        <label class="w-100 mb-3"><span class="label">Notas de auditoria</span><input class="form-control" name="result_notes" placeholder="Correccion, mesa, observacion o juez"></label>
                        <div class="result-summary" data-result-summary>Sin captura</div>
                        <button class="btn btn-primary fw-bold mt-3" type="submit" data-result-save-button><i class="bi bi-database-check"></i> Guardar resultado</button>
                    </div>
                    <aside class="match-log"><h2>Linea de match</h2><ol><li>Selecciona categoria y partida</li><li>Captura D1-D5</li><li>El sistema valida cierre</li><li>Guarda auditoria</li></ol></aside>
                </div>
            </form>
        </section>
    </section>

    <section class="screen" data-screen="deck" hidden>
        <div class="page-title deck-page-title with-actions module-title">
            <div><h1><?php echo e($uiText('route.deck', 'title', 'Decks')); ?></h1><p><?php echo e($uiText('route.deck', 'subtitle', 'Listado y filtros de decks guardados. La ficha se abre en una pantalla aparte.')); ?></p></div>
            <div class="d-flex gap-2 flex-wrap">
                <button class="btn btn-outline-info" type="button" data-deck-download-all title="Descargar TXT de todos los decks y formatos"><i class="bi bi-download" aria-hidden="true"></i> Importar listado</button>
                <button class="btn btn-primary" type="button" data-open-deck-sheet=""><i class="bi bi-plus-circle"></i> Crear deck</button>
            </div>
        </div>
        <section class="panel">
            <div class="panel-head"><div><h2>Decks creados</h2><p>Consulta por tabla, filtra y abre la ficha de cada deck.</p></div><span class="status-chip"><?php echo count($allDecks); ?> activos</span></div>
            <p class="note" data-deck-download-status role="status" aria-live="polite"></p>
            <div class="deck-list-filters mb-3">
                <label class="deck-list-search-field">Buscar<input class="form-control" type="search" placeholder="Deck, equipo o arquetipo" data-deck-list-search></label>
                <label>Formato<select class="form-select" data-deck-list-format-filter><option value="">Todos</option><option value="tcg">TCG</option><option value="rush">RUSH DUEL</option><option value="goat">GOAT</option><option value="ocg">OCG</option></select></label>
                <label>Division<select class="form-select" data-deck-list-division-filter><option value="">Todas</option><?php foreach ($displayDivisions as $division): ?><option value="<?php echo e(strtolower((string) ($division['short'] ?? $division['code'] ?? ''))); ?>"><?php echo e((string) ($division['short'] ?? $division['name'] ?? 'Division')); ?></option><?php endforeach; ?></select></label>
                <label>Equipo<select class="form-select" data-deck-list-team-filter><option value="">Todos</option><?php foreach ($teams as $team): ?><option value="<?php echo e(strtolower((string) ($team['name'] ?? ''))); ?>"><?php echo e((string) ($team['name'] ?? 'Equipo')); ?></option><?php endforeach; ?></select></label>
                <label>Tier<select class="form-select" data-deck-list-tier-filter><option value="">Todos</option><option value="tier zero">Tier Zero</option><option value="tier 1">Tier 1</option><option value="tier 2">Tier 2</option><option value="tier 3">Tier 3</option><option value="sin tier">Sin tier</option></select></label>
                <label>Actividad<select class="form-select" data-deck-list-activity-filter><option value="">Toda</option><option value="with_results">Con resultados</option><option value="without_results">Sin resultados</option></select></label>
                <label>Cartas cargadas<select class="form-select" data-deck-list-cards-filter><option value="">Todos los decks</option><option value="with_cards">Con cartas cargadas</option><option value="without_cards">Sin cartas cargadas</option></select></label>
                <label>Orden<select class="form-select" data-deck-list-order><option value="rank">Ranking</option><option value="name">Nombre A-Z</option><option value="matches">Mas partidas</option><option value="winrate">Mayor winrate</option></select></label>
                <button class="btn btn-outline-light" type="button" data-deck-list-clear><i class="bi bi-arrow-counterclockwise"></i> Limpiar</button>
            </div>
            <div class="deck-list-filter-summary"><span data-deck-list-visible-count><?php echo count($allDecks); ?> de <?php echo count($allDecks); ?> decks</span></div>
            <div class="table-responsive">
                <table class="table app-table entity-table mobile-card-table">
                    <thead><tr><th>Deck</th><th>Equipo asociado</th><th>Formato</th><th>Liga actual</th><th>Divisi?n</th><th>Partidas</th><th>Partidas G/E/P</th><th>Posición en la liga</th><th>WR partidas</th><th>Acciones</th></tr></thead>
                    <tbody>
                        <?php foreach ($allDecks as $deck): ?>
                            <?php $deckEvents = (int) ($deckMatchCounts[(int) ($deck['id'] ?? 0)] ?? 0); ?>
                            <tr data-deck-list-row data-has-cards="<?php echo !empty($deck['has_cards']) ? '1' : '0'; ?>" data-deck-list-id="<?php echo e((string) ($deck['id'] ?? '')); ?>" data-team="<?php echo e(strtolower((string) ($deck['team'] ?? ''))); ?>" data-division="<?php echo e(strtolower((string) ($deck['division'] ?? ''))); ?>" data-search="<?php echo e(strtolower((string) (($deck['name'] ?? '') . ' ' . ($deck['team'] ?? '') . ' ' . ($deck['division'] ?? '') . ' ' . ($deck['display_archetype'] ?? $deck['archetype'] ?? '') . ' ' . ($deck['display_style'] ?? '')))); ?>">
                                <td data-label="Deck">
                                    <span class="deck-table-identity">
                                        <?php if (!empty($deck['cover_url'])): ?>
                                            <span class="deck-table-logo"><img src="<?php echo e((string) $deck['cover_url']); ?>" alt="<?php echo e((string) $deck['name']); ?>"></span>
                                        <?php else: ?>
                                            <span class="deck-table-logo deck-table-logo-fallback"><?php echo e(strtoupper(substr((string) ($deck['name'] ?? '?'), 0, 1))); ?></span>
                                        <?php endif; ?>
                                        <?php if (!empty($deck['has_cards'])): ?><button type="button" class="deck-loaded-marker" data-deck-loaded-filter aria-pressed="false" title="Cartas cargadas · filtrar decks con cartas" aria-label="<?php echo e($deck['name'].' tiene cartas cargadas. Filtrar decks con cartas.'); ?>"><i class="bi bi-stack" aria-hidden="true"></i></button><?php else: ?><span class="deck-loaded-marker is-empty" title="Sin cartas cargadas" aria-label="Sin cartas cargadas"><i class="bi bi-stack" aria-hidden="true"></i></span><?php endif; ?>
                                        <span class="deck-list-description"><strong><?php echo e((string) $deck['name']); ?></strong><small data-deck-list-style>Estilo: <?php echo e((string) (($deck['display_style'] ?? '') ?: 'Sin estilo registrado')); ?></small><small data-deck-list-archetype>Arquetipo: <?php echo e((string) ($deck['display_archetype'] ?? $deck['archetype'] ?? $deck['name'])); ?></small><span class="attribute-tags" data-attribute-tags-deck="<?php echo (int)$deck['id']; ?>"></span></span>
                                    </span>
                                </td>
                                <td data-label="Equipo"><?php echo e((string) (($deck['team'] ?? '') ?: 'Sin equipo')); ?></td>
                                <td data-label="Formato" data-deck-list-format>Sin registros</td>
                                <td data-label="Liga actual" data-deck-list-league>Sin liga actual</td>
                                <td data-label="Divisi?n" data-deck-list-division>Sin divisi?n</td>
                                <td data-label="Partidas" data-deck-list-matches>0</td>
                                <td data-label="Partidas G/E/P" data-deck-list-match-record>0 / 0 / 0</td>
                                <td data-label="Posición en la liga" data-deck-list-league-position>Sin clasificación</td>
                                <td data-label="WR partidas" data-deck-list-winrate>0%</td>
                                <td data-label="Acciones"><div class="d-flex gap-2"><button class="btn btn-outline-info btn-sm" type="button" data-open-deck-sheet="<?php echo e((string) ($deck['id'] ?? '')); ?>" title="Ver ficha" aria-label="Ver ficha de <?php echo e((string) $deck['name']); ?>"><i class="bi bi-eye" aria-hidden="true"></i></button><button class="btn btn-outline-info btn-sm" type="button" data-deck-download="<?php echo e((string) ($deck['id'] ?? '')); ?>" title="Descargar listado TXT"><i class="bi bi-download" aria-hidden="true"></i> Importar</button></div></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </section>
    </section>

    <section class="screen" data-screen="deck_ficha" hidden>
        <div class="page-title with-actions module-title">
            <div><h1><?php echo e($uiText('route.deck_ficha', 'title', 'Ficha del deck')); ?></h1><p><?php echo e($uiText('route.deck_ficha', 'subtitle', 'Detalle, estadisticas, cartas, eventos y edicion del deck seleccionado.')); ?></p></div>
            <div class="profile-header-actions">
                <button class="btn btn-outline-info" type="button" data-profile-calendar><i class="bi bi-calendar3"></i> Calendario</button>
                <button class="btn btn-outline-light" type="button" data-route="deck"><i class="bi bi-arrow-left"></i> Volver a decks</button>
            </div>
        </div>
        <select data-deck-select hidden aria-hidden="true" tabindex="-1"></select>
        <div class="deck-profile-layout" data-deck-profile-view="summary">
            <div class="deck-profile-aside">
                <section class="panel profile-panel deck-identity-panel">
                    <div class="deck-cover" data-profile-cover><div class="deck-avatar" data-profile-initials></div></div>
                    <h2 data-profile-name></h2>
                    <p data-profile-division></p>
                    <span class="status-chip" data-profile-rank></span>
                    <p class="note mt-2" data-profile-rank-detail></p>
                    <div class="profile-meta mt-3">
                        <span class="deck-team-profile-card"><b>Equipo asociado</b><small data-profile-team></small></span>
                        <span><b>Arquetipo</b><small data-profile-archetype></small></span>
                        <span><b>Estilo</b><small data-profile-style></small></span>
                        <span data-profile-attributes></span>
                    </div>
                </section>
            </div>
            <div class="deck-profile-main">
                <section class="panel deck-category-hub">
                    <div class="panel-head"><div><h2>Explorar ficha</h2><p>Abre una categoria sin recorrer toda la pagina.</p></div><span class="status-chip">12 secciones</span></div>
                    <div class="deck-category-grid">
                        <button type="button" data-deck-category-open="performance"><i class="bi bi-graph-up-arrow"></i><span><b>Rendimiento</b><small data-deck-category-metric="performance">Partidas y duelos</small></span><i class="bi bi-chevron-right"></i></button>
                        <button type="button" data-deck-category-open="history"><i class="bi bi-clock-history"></i><span><b>Historial y agenda</b><small data-deck-category-metric="history">Partidas registradas</small></span><i class="bi bi-chevron-right"></i></button>
                        <button type="button" data-deck-category-open="events"><i class="bi bi-calendar2-event"></i><span><b>Historial de eventos</b><small data-deck-category-metric="events">Participaciones e inscripciones</small></span><i class="bi bi-chevron-right"></i></button>
                        <button type="button" data-deck-category-open="decklist"><i class="bi bi-collection"></i><span><b>Decklist y multimedia</b><small data-deck-category-metric="decklist">Main, Extra y Side</small></span><i class="bi bi-chevron-right"></i></button>
                        <button type="button" data-deck-category-open="ranking"><i class="bi bi-bar-chart-line"></i><span><b>Ranking y tier</b><small data-deck-category-metric="ranking">Posicion e historico</small></span><i class="bi bi-chevron-right"></i></button>
                        <button type="button" data-deck-category-open="achievements"><i class="bi bi-trophy"></i><span><b>Logros</b><small data-deck-category-metric="achievements">Titulos y medallas</small></span><i class="bi bi-chevron-right"></i></button>
                        <button type="button" data-deck-category-open="strategy"><i class="bi bi-diagram-3"></i><span><b>Estilo y estrategia</b><small data-deck-category-metric="strategy">Plan competitivo</small></span><i class="bi bi-chevron-right"></i></button>
                        <button type="button" data-deck-category-open="created-combos"><i class="bi bi-bezier2"></i><span><b>Mis combos y estrategias</b><small>Guías visuales, combos y creaciones del deck</small></span><i class="bi bi-chevron-right"></i></button>
                        <button type="button" data-deck-category-open="play-calculator"><i class="bi bi-calculator"></i><span><b>Calculador de jugadas</b><small data-deck-category-metric="play-calculator">Tablero interactivo · cálculo local</small></span><i class="bi bi-chevron-right"></i></button>
                        <button type="button" data-deck-category-open="matchups"><i class="bi bi-grid-3x3-gap"></i><span><b>Matchups</b><small data-deck-category-metric="matchups">Teoria contra realidad</small></span><i class="bi bi-chevron-right"></i></button>
                        <button type="button" data-deck-category-open="edit"><i class="bi bi-sliders"></i><span><b>Editar deck</b><small data-deck-category-metric="edit">Datos, logo y notas</small></span><i class="bi bi-chevron-right"></i></button>
                        <button type="button" data-deck-data-open><i class="bi bi-arrow-down-up"></i><span><b>Importar / exportar Data</b><small>Listado de cartas y contenido del deck en ZIP</small></span><i class="bi bi-chevron-right"></i></button>
                    </div>
                </section>
                <?php require __DIR__ . '/includes/decks/play-calculator-view.php'; ?>
                <section class="panel deck-created-combos-panel"><div data-deck-created-combos></div></section>
                <section class="panel deck-editor-panel">
                    <div class="panel-head"><div><h2>Crear / editar deck</h2><p>El equipo asociado se consulta aqui. Asociar o desasociar decks se hace desde la ficha del equipo.</p></div><span class="status-chip">Ficha deck</span></div>
                    <form method="post" class="gestion-form" enctype="multipart/form-data">
                        <input type="hidden" name="action" value="save_deck">
                        <input type="hidden" name="deck_id" data-gestion-deck-id>
                        <div class="deck-form-grid">
                            <label class="span-2">Editar deck existente<select class="form-select" data-gestion-deck-select><option value="">Nuevo deck</option><?php foreach ($allDecks as $deck): ?><option value="<?php echo e((string) $deck['id']); ?>"><?php echo e($deck['name']); ?></option><?php endforeach; ?></select></label>
                            <label class="span-2">Nombre del deck<input class="form-control" name="deck_name" data-gestion-deck-name required></label>
                            <label>Equipo asociado<input class="form-control" data-gestion-deck-team-label value="Sin equipo" readonly></label>
                            <label>Division<select class="form-select" name="division_id" data-gestion-deck-division><option value="">Sin division</option><?php foreach ($displayDivisions as $division): ?><option value="<?php echo e((string) ($division['id'] ?? '')); ?>"><?php echo e($division['short']); ?></option><?php endforeach; ?></select></label>
                            <label>Semilla interna<input class="form-control" type="number" min="1" name="seed_rank" data-gestion-deck-rank value="999"><small>Sólo se usa para programación; no representa el ranking global.</small></label>
                            <label>Estilo<input class="form-control" name="style" data-gestion-deck-style placeholder="Combo, control, midrange"></label>
                            <label class="span-2">Arquetipo<input class="form-control" name="archetype" data-gestion-deck-archetype></label>
                            <label class="span-2">Motivo / resumen<input class="form-control" name="reason" data-gestion-deck-reason></label>
                            <label>Cartas clave<textarea class="form-control" name="key_cards" data-gestion-deck-cards rows="3"></textarea></label>
                            <label>Variaciones<textarea class="form-control" name="variations" data-gestion-deck-variations rows="3"></textarea></label>
                            <label class="span-2">Notas / ficha<textarea class="form-control" name="notes" data-gestion-deck-notes rows="3"></textarea></label>
                            <input type="hidden" name="cover_url" data-gestion-deck-cover>
                            <label class="span-2 deck-upload-field">Subir logo del deck<input class="form-control" type="file" name="deck_logo" accept="image/png,image/jpeg,image/webp,image/gif"><small>PNG, JPG, WEBP o GIF. Si subes uno nuevo, reemplaza la imagen actual del deck.</small></label>
                        </div>
                        <div class="deck-form-actions">
                            <button class="btn btn-outline-info fw-bold" type="button" data-open-logo-history="deck"><i class="bi bi-images"></i> Historial de logos</button>
                            <button class="btn btn-primary fw-bold" type="submit"><i class="bi bi-save"></i> Guardar deck</button>
                        </div>
                    </form>
                    <form method="post" class="deck-delete-form">
                        <input type="hidden" name="action" value="remove_deck">
                        <input type="hidden" name="deck_id" data-gestion-remove-deck-id>
                        <button class="btn btn-outline-danger" type="submit"><i class="bi bi-trash"></i> Quitar deck seleccionado</button>
                    </form>
                </section>
                <section class="panel deck-profile-detail-panel">
                    <div class="panel-head"><div><h2>Rendimiento competitivo</h2><p>Partidas y duelos se contabilizan por separado.</p></div></div>
                    <div class="deck-record-overview">
                        <article class="match-record-card"><span>Partidas G / E / P</span><strong data-profile-match-record>0 / 0 / 0</strong><small data-profile-match-rate>0% de victorias</small></article>
                        <article class="duel-record-card"><span>Duelos G / E / P</span><strong data-profile-duel-record>0 / 0 / 0</strong><small data-profile-duel-rate>0% de victorias</small></article>
                        <article><span>Actividad</span><strong data-profile-total-scheduled>0</strong><small data-profile-pending>0 pendientes</small></article>
                    </div>
                    <div class="mini-grid profile-stats" data-profile-stats></div>
                    <div class="deck-builder-rivals mt-3" data-profile-rivals>
                        <article><h3>Rivales a los que más ha vencido</h3><div class="profile-list" data-profile-top-beaten></div></article>
                        <article><h3>Rivales contra los que más ha perdido</h3><div class="profile-list" data-profile-top-lost-to></div></article>
                    </div>
                    <div class="tier-history-summary mt-3" data-profile-tier-history></div>
                    <div class="card-gallery mt-3" data-card-gallery></div>
                    <div class="deck-detail-grid mt-3">
                        <article><h3>Cartas clave</h3><p data-profile-cards></p></article>
                        <article><h3>Variaciones</h3><p data-profile-variations></p></article>
                        <article><h3>Notas</h3><p data-profile-notes></p></article>
                        <article><h3>Cartas registradas</h3><div class="profile-list" data-profile-card-list></div></article>
                        <article><h3>Media</h3><div class="profile-media" data-profile-media-list></div></article>
                    </div>
                    <div class="deck-achievement-layout mt-3">
                        <section><div class="panel-head"><div><h3>Logros</h3><p>Titulos y subcampeonatos obtenidos en eventos.</p></div></div><div class="deck-honor-list" data-profile-honors></div></section>
                        <section><div class="panel-head"><div><h3>Campeonatos y logros</h3><p>Consulta los requisitos, las medallas obtenidas y el progreso de tu equipo.</p></div></div><div class="stats-v8-profile" data-profile-medals></div></section>
                    </div>
                    <section class="deck-events-section" data-profile-events></section>
                    <section class="deck-history-section mt-3">
                        <div class="panel-head"><div><h3>Historial de partidas</h3><p>Programacion, rival, resultado de la partida y récord de sus duelos.</p></div><span class="status-chip" data-profile-history-count>0 partidas</span></div>
                        <div class="table-responsive"><table class="table app-table compact deck-history-table"><thead><tr><th>Fecha</th><th>Evento</th><th>Ronda / mesa</th><th>Rival</th><th>Estado</th><th>Resultado</th><th>Duelos G/E/P</th><th></th></tr></thead><tbody data-profile-history></tbody></table></div>
                    </section>
                </section>
            </div>
        </div>
        <section class="deck-analysis-screen" data-deck-profile-view="analysis" hidden>
            <header class="deck-analysis-header">
                <div><span class="eyebrow">Analisis particular</span><h2 data-matchup-analysis-title>Teoria vs. realidad</h2><p>La compatibilidad estrategica es editorial. Los resultados se recalculan exclusivamente desde partidas persistidas.</p></div>
                <div class="analysis-source-legend"><span><i class="bi bi-pencil-square"></i> EDITORIAL / ANALYTICAL</span><span><i class="bi bi-database-check"></i> OBSERVED / CALCULATED</span></div>
            </header>
            <section class="deck-strategy-profile">
                <div class="panel-head"><div><span class="eyebrow">Perfil general</span><h3>Identidad y estilo de juego</h3><p>Describe como funciona el deck. Este bloque es editorial y no cambia por una racha de resultados.</p></div><span class="source-badge editorial" data-deck-analysis-source>EDITORIAL</span></div>
                <div class="deck-strategy-identity" data-deck-strategy-identity></div>
                <div class="deck-strategy-content">
                    <div data-deck-strategy-plans></div>
                    <div data-deck-strategy-strengths></div>
                    <div data-deck-strategy-scores></div>
                </div>
                <details class="deck-strategy-editor">
                    <summary><span><i class="bi bi-pencil-square"></i> Editar perfil estrategico completo</span><i class="bi bi-chevron-down"></i></summary>
                    <form method="post" data-deck-analysis-profile-form>
                        <input type="hidden" name="action" value="save_deck_analysis_profile">
                        <input type="hidden" name="deck_analysis_deck_id" data-deck-analysis-form-id>
                        <div class="deck-strategy-form-grid">
                            <label>Estilo de juego<input class="form-control" name="deck_analysis_play_style" data-deck-analysis-play-style placeholder="Combo, control, midrange, stun, OTK..."></label>
                            <label>Ritmo<select class="form-select" name="deck_analysis_pace" data-deck-analysis-pace><option value="">Pendiente</option><option value="EXPLOSIVO">Explosivo</option><option value="RAPIDO">Rapido</option><option value="MEDIO">Medio</option><option value="LENTO">Lento</option><option value="ADAPTABLE">Adaptable</option></select></label>
                            <label>Complejidad<select class="form-select" name="deck_analysis_complexity" data-deck-analysis-complexity><option value="">Pendiente</option><option value="BAJA">Baja</option><option value="MEDIA">Media</option><option value="ALTA">Alta</option><option value="MUY_ALTA">Muy alta</option></select></label>
                            <label>Confianza<select class="form-select" name="deck_analysis_confidence" data-deck-analysis-confidence><option>BAJA</option><option selected>MEDIA</option><option>ALTA</option></select></label>
                            <label>Fuente<select class="form-select" name="deck_analysis_source_type" data-deck-analysis-source-type><option>EDITORIAL</option><option>ANALYTICAL</option></select></label>
                            <label class="span-2">Plan principal<textarea class="form-control" name="deck_analysis_primary_game_plan" data-deck-analysis-primary-plan rows="3"></textarea></label>
                            <label class="span-2">Condicion de victoria<textarea class="form-control" name="deck_analysis_win_condition" data-deck-analysis-win-condition rows="3"></textarea></label>
                            <label class="span-2">Motor de recursos<textarea class="form-control" name="deck_analysis_resource_engine" data-deck-analysis-resource-engine rows="3"></textarea></label>
                            <label>Plan de apertura<textarea class="form-control" name="deck_analysis_opening_plan" data-deck-analysis-opening-plan rows="3"></textarea></label>
                            <label>Plan de juego medio<textarea class="form-control" name="deck_analysis_mid_game_plan" data-deck-analysis-mid-plan rows="3"></textarea></label>
                            <label>Plan de juego tardio<textarea class="form-control" name="deck_analysis_late_game_plan" data-deck-analysis-late-plan rows="3"></textarea></label>
                            <label>Plan empezando primero<textarea class="form-control" name="deck_analysis_going_first_plan" data-deck-analysis-first-plan rows="3"></textarea></label>
                            <label>Plan empezando segundo<textarea class="form-control" name="deck_analysis_going_second_plan" data-deck-analysis-second-plan rows="3"></textarea></label>
                            <label>Estrategia de Side Deck<textarea class="form-control" name="deck_analysis_side_deck_strategy" data-deck-analysis-side-strategy rows="3"></textarea></label>
                            <label>Fortalezas<textarea class="form-control" name="deck_analysis_strengths" data-deck-analysis-strengths rows="4" placeholder="Una por linea"></textarea></label>
                            <label>Debilidades<textarea class="form-control" name="deck_analysis_weaknesses" data-deck-analysis-weaknesses rows="4" placeholder="Una por linea"></textarea></label>
                            <label>Etiquetas estrategicas<textarea class="form-control" name="deck_analysis_strategy_tags" data-deck-analysis-tags rows="4" placeholder="Una por linea"></textarea></label>
                        </div>
                        <fieldset class="deck-strategy-score-editor"><legend>Valoracion editorial de 0 a 5</legend>
                            <label>Consistencia<input type="number" class="form-control" min="0" max="5" name="deck_analysis_consistency_score" data-deck-analysis-consistency></label>
                            <label>Techo de poder<input type="number" class="form-control" min="0" max="5" name="deck_analysis_ceiling_score" data-deck-analysis-ceiling></label>
                            <label>Resiliencia<input type="number" class="form-control" min="0" max="5" name="deck_analysis_resilience_score" data-deck-analysis-resilience></label>
                            <label>Interaccion<input type="number" class="form-control" min="0" max="5" name="deck_analysis_interaction_score" data-deck-analysis-interaction></label>
                            <label>Recuperacion<input type="number" class="form-control" min="0" max="5" name="deck_analysis_recovery_score" data-deck-analysis-recovery></label>
                            <label>Primero<input type="number" class="form-control" min="0" max="5" name="deck_analysis_first_score" data-deck-analysis-first-score></label>
                            <label>Segundo<input type="number" class="form-control" min="0" max="5" name="deck_analysis_second_score" data-deck-analysis-second-score></label>
                        </fieldset>
                        <div class="deck-strategy-form-grid mt-3">
                            <label class="span-2">Notas del analisis<textarea class="form-control" name="deck_analysis_notes" data-deck-analysis-notes rows="3"></textarea></label>
                            <label class="span-2">Motivo de esta revision<input class="form-control" name="deck_analysis_change_reason" placeholder="Cambio de soporte, banlist o revision editorial"></label>
                        </div>
                        <div class="matchup-form-actions"><span data-deck-analysis-editor-state>Perfil estrategico pendiente.</span><button class="btn btn-primary" type="submit"><i class="bi bi-save"></i> Guardar perfil estrategico</button></div>
                    </form>
                </details>
            </section>
            <div class="deck-matchup-section-title"><span class="eyebrow">Analisis por rival</span><h3>Matchups: teoria vs. resultados</h3><p>Selecciona el rival y el alcance de los resultados que deseas comparar.</p></div>
            <div class="deck-analysis-filters">
                <label>Rival<select class="form-select" data-matchup-opponent></select></label>
                <label>Periodo<select class="form-select" data-matchup-period><option value="all">Todo el historico</option><option value="30">Ultimos 30 dias</option><option value="90">Ultimos 90 dias</option><option value="year">Ano actual</option><option value="season">Temporada actual</option></select></label>
                <label>Evento<select class="form-select" data-matchup-event><option value="">Todos los eventos</option></select></label>
                <label>Division<select class="form-select" data-matchup-division><option value="">Todas las divisiones</option></select></label>
                <label>Banlist / reglas<select class="form-select" data-matchup-ruleset-version><option value="">Todas las versiones</option></select></label>
                <label>Version propia<select class="form-select" data-matchup-version><option value="">Todas las versiones</option></select></label>
                <label>Version rival<select class="form-select" data-matchup-opponent-version><option value="">Todas las versiones</option></select></label>
                <label>Posicion<select class="form-select" data-matchup-position><option value="">Primero y segundo</option><option value="first">Empezando primero</option><option value="second">Empezando segundo</option></select></label>
            </div>
            <div class="deck-analysis-kpis" data-matchup-overview></div>
            <div class="deck-analysis-columns">
                <section class="analysis-pane is-strategic">
                    <div class="panel-head"><div><span class="eyebrow">Nivel 1 - Teoria</span><h3>Compatibilidad estrategica</h3><p>No se deriva del winrate y solo cambia mediante una revision editorial.</p></div><span class="source-badge editorial">EDITORIAL</span></div>
                    <div data-matchup-strategic-summary></div>
                </section>
                <section class="analysis-pane is-observed">
                    <div class="panel-head"><div><span class="eyebrow">Nivel 2 - Realidad</span><h3>Rendimiento observado</h3><p>Calculado al instante con el alcance seleccionado.</p></div><span class="source-badge calculated">CALCULATED</span></div>
                    <div data-matchup-observed-summary></div>
                </section>
            </div>
            <section class="analysis-comparison-band">
                <div><span class="eyebrow">Nivel 3 - Analisis</span><h3>Comparacion cualitativa</h3></div>
                <div data-matchup-comparison></div>
            </section>
            <div class="deck-analysis-lower-grid">
                <section class="panel matchup-editor-panel">
                    <div class="panel-head"><div><h3>Editar analisis estrategico</h3><p>Una direccion no copia automaticamente la direccion inversa. Cada revision queda en el historico.</p></div></div>
                    <form method="post" data-matchup-analysis-form>
                        <input type="hidden" name="action" value="save_matchup_analysis">
                        <input type="hidden" name="matchup_deck_id" data-matchup-form-deck-id>
                        <input type="hidden" name="matchup_opponent_deck_id" data-matchup-form-opponent-id>
                        <div class="matchup-form-grid">
                            <label>Score estrategico<select class="form-select" name="strategic_score" data-matchup-form-score><option value="2">+2 Muy favorable</option><option value="1">+1 Favorable</option><option value="0" selected>0 Equilibrado</option><option value="-1">-1 Desfavorable</option><option value="-2">-2 Muy desfavorable</option></select></label>
                            <label>Confianza<select class="form-select" name="matchup_confidence" data-matchup-form-confidence><option>BAJA</option><option selected>MEDIA</option><option>ALTA</option></select></label>
                            <label>Fuente<select class="form-select" name="matchup_source_type" data-matchup-form-source><option>EDITORIAL</option><option>ANALYTICAL</option></select></label>
                            <label>Banlist / reglas<input class="form-control" name="matchup_ruleset_version" data-matchup-form-ruleset placeholder="General si queda vacio"></label>
                            <label>Version propia<input class="form-control" name="matchup_deck_version" data-matchup-form-deck-version placeholder="General si queda vacio"></label>
                            <label>Version rival<input class="form-control" name="matchup_opponent_version" data-matchup-form-opponent-version placeholder="General si queda vacio"></label>
                            <label class="span-3">Razon estrategica<textarea class="form-control" name="matchup_reason" data-matchup-form-reason rows="3" placeholder="Describe interacciones mecanicas, recursos y condiciones del matchup."></textarea></label>
                            <label>Ventajas clave<textarea class="form-control" name="matchup_advantages" data-matchup-form-advantages rows="4" placeholder="Una por linea"></textarea></label>
                            <label>Desventajas clave<textarea class="form-control" name="matchup_disadvantages" data-matchup-form-disadvantages rows="4" placeholder="Una por linea"></textarea></label>
                            <label>Choke points<textarea class="form-control" name="matchup_choke_points" data-matchup-form-choke-points rows="4" placeholder="Uno por linea"></textarea></label>
                            <label>Plan empezando primero<textarea class="form-control" name="matchup_going_first_plan" data-matchup-form-first-plan rows="3"></textarea></label>
                            <label>Plan empezando segundo<textarea class="form-control" name="matchup_going_second_plan" data-matchup-form-second-plan rows="3"></textarea></label>
                            <label>Plan de Side Deck<textarea class="form-control" name="matchup_side_plan" data-matchup-form-side-plan rows="3"></textarea></label>
                            <label class="span-3">Notas de revision<textarea class="form-control" name="matchup_review_notes" data-matchup-form-review-notes rows="2"></textarea></label>
                            <label class="span-3">Motivo de este cambio<input class="form-control" name="matchup_change_reason" placeholder="Soporte nuevo, banlist, nueva version o revision del analista"></label>
                        </div>
                        <div class="matchup-form-actions"><span data-matchup-editor-state>Sin analisis guardado para este alcance.</span><button class="btn btn-primary" type="submit"><i class="bi bi-save"></i> Guardar revision estrategica</button></div>
                    </form>
                </section>
                <section class="panel matchup-history-panel">
                    <div class="panel-head"><div><h3>Historico editorial</h3><p>Versiones anteriores del matchup seleccionado.</p></div><span class="status-chip" data-matchup-history-count>0 revisiones</span></div>
                    <div data-matchup-history></div>
                </section>
            </div>
            <section class="panel mt-3">
                <div class="panel-head"><div><h3>Mapa de rivales del deck</h3><p>Todos los rivales con evidencia real y su analisis editorial independiente.</p></div><span class="status-chip" data-matchup-table-count>0 rivales</span></div>
                <div class="table-responsive"><table class="table app-table mobile-card-table"><thead><tr><th>Rival</th><th>Teoria</th><th>Muestra</th><th>Partidas G/E/P</th><th>WR partidas</th><th>WR duelos</th><th>Comparacion</th><th>Revision</th></tr></thead><tbody data-matchup-rivals-table></tbody></table></div>
            </section>
        </section>
        <div data-deck-category-staging hidden></div>
        <div class="modal fade dml-modal deck-category-modal" id="deckCategoryModal" tabindex="-1" aria-hidden="true" data-deck-category-modal>
            <div class="modal-dialog modal-xl modal-dialog-scrollable">
                <div class="modal-content">
                    <div class="modal-header">
                        <div><span class="tag">Ficha del deck</span><h2 class="modal-title" data-deck-category-title>Detalle</h2><p data-deck-category-subtitle></p></div>
                        <button class="btn-close btn-close-white" type="button" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                    </div>
                    <div class="modal-body" data-deck-category-body></div>
                    <div class="modal-footer deck-category-modal-footer">
                        <button class="btn btn-outline-light" type="button" data-deck-category-prev title="Categoria anterior"><i class="bi bi-chevron-left"></i><span>Anterior</span></button>
                        <span data-deck-category-position>1 de 9</span>
                        <button class="btn btn-outline-info" type="button" data-deck-category-next title="Categoria siguiente"><span>Siguiente</span><i class="bi bi-chevron-right"></i></button>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section class="screen" data-screen="equipos" hidden>
        <div class="page-title with-actions module-title">
            <div><h1><?php echo e($uiText('route.equipos', 'title', 'Equipos')); ?></h1><p><?php echo e($uiText('route.equipos', 'subtitle', 'Listado y filtros de equipos guardados. La ficha se abre en una pantalla aparte.')); ?></p></div>
            <button class="btn btn-primary" type="button" data-open-team-sheet=""><i class="bi bi-shield-plus"></i> Crear equipo</button>
        </div>
        <section class="panel">
            <div class="panel-head"><div><h2>Equipos creados</h2><p>Tabla principal para buscar, filtrar y abrir la ficha de equipo.</p></div><span class="status-chip"><?php echo count($teams); ?> activos</span></div>
            <div class="team-list-filters mb-3">
                <label>Buscar<input class="form-control" type="search" placeholder="Equipo, codigo o deck..." data-team-list-search></label>
                <label>Formato<select class="form-select" data-team-list-format><option value="">Todos los formatos</option><?php foreach ($eventRulesetOptions as $code => $label): ?><option value="<?php echo e((string) $code); ?>"><?php echo e((string) $label); ?></option><?php endforeach; ?></select></label>
                <label>Division<select class="form-select" data-team-list-division><option value="">Todas las divisiones</option><?php foreach ($divisions as $code => $division): ?><option value="<?php echo e((string) $code); ?>"><?php echo e((string) ($division['name'] ?? $code)); ?></option><?php endforeach; ?></select></label>
                <button class="btn btn-outline-light" type="button" data-team-list-clear><i class="bi bi-arrow-counterclockwise"></i> Limpiar</button>
            </div>
            <div class="table-responsive">
                <table class="table app-table entity-table mobile-card-table team-list-table">
                    <thead><tr><th>Equipo</th><th>Codigo</th><th>Formatos</th><th>Division</th><th>Decks</th><th>Partidas</th><th>Partidas G/E/P</th><th>Duelos G/E/P</th><th>Winrate</th><th>Acciones</th></tr></thead>
                    <tbody>
            <?php foreach ($teams as $team): ?>
                <?php
                $teamDecks = array_values(array_filter($allDecks, static fn(array $deck): bool => (string) ($deck['team_id'] ?? '') === (string) ($team['id'] ?? '')));
                $primary = (string) (($team['primary_color'] ?? '') ?: '#36d8ff');
                $secondary = (string) (($team['secondary_color'] ?? '') ?: '#ffd25f');
                $teamSymbol = (string) (($team['symbol'] ?? '') ?: ($team['short_code'] ?? '') ?: substr((string) $team['name'], 0, 2));
                ?>
                <tr data-team-list-row data-team-id="<?php echo e((string) ($team['id'] ?? '')); ?>" data-search="<?php echo e(strtolower((string) (($team['name'] ?? '') . ' ' . ($team['short_code'] ?? '') . ' ' . implode(' ', array_column($teamDecks, 'name'))))); ?>" style="--team-primary: <?php echo e($primary); ?>; --team-secondary: <?php echo e($secondary); ?>">
                    <td data-label="Equipo">
                        <span class="team-table-identity">
                            <?php if (!empty($team['logo_url'])): ?>
                                <span class="team-table-logo"><img src="<?php echo e((string) $team['logo_url']); ?>" alt="<?php echo e((string) $team['name']); ?>"></span>
                            <?php else: ?>
                                <span class="team-inline-badge" style="--team-primary: <?php echo e($primary); ?>; --team-secondary: <?php echo e($secondary); ?>"><?php echo e($teamSymbol); ?></span>
                            <?php endif; ?>
                            <strong><?php echo e((string) $team['name']); ?></strong>
                        </span>
                    </td>
                    <td data-label="Codigo"><?php echo e((string) (($team['short_code'] ?? '') ?: 'YMD')); ?></td>
                    <td data-label="Formatos" data-team-list-formats>Sin formato</td>
                    <td data-label="Division" data-team-list-divisions>Sin division</td>
                    <td data-label="Decks"><?php echo count($teamDecks); ?></td>
                    <td data-label="Partidas" data-team-list-matches>0</td>
                    <td data-label="Partidas G/E/P" data-team-list-match-record>0 / 0 / 0</td>
                    <td data-label="Duelos G/E/P" data-team-list-duel-record>0 / 0 / 0</td>
                    <td data-label="Winrate" data-team-list-winrate>0%</td>
                    <td data-label="Acciones"><button class="btn btn-outline-info btn-sm" type="button" data-open-team-sheet="<?php echo e((string) ($team['id'] ?? '')); ?>"><i class="bi bi-shield-check"></i> Ver ficha</button></td>
                </tr>
            <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </section>
    </section>

    <section class="screen" data-screen="equipo_ficha" hidden>
        <div class="page-title with-actions module-title">
            <div><h1><?php echo e($uiText('route.equipo_ficha', 'title', 'Ficha del equipo')); ?></h1><p>Identidad, decks, rendimiento competitivo y palmares del equipo.</p></div>
            <div class="profile-header-actions">
                <a class="btn btn-outline-info" data-team-export-pdf href="#"><i class="bi bi-file-earmark-pdf"></i> Informe PDF</a>
                <button class="btn btn-outline-info" type="button" data-share-team-profile><i class="bi bi-share"></i> Compartir</button>
                <button class="btn btn-outline-info" type="button" data-team-calendar><i class="bi bi-calendar3"></i> Calendario</button>
                <button class="btn btn-outline-light" type="button" data-route="equipos"><i class="bi bi-arrow-left"></i> Volver a equipos</button>
            </div>
        </div>
        <section class="panel">
            <div class="panel-head"><div><h2>Datos del equipo</h2><p>Ficha editable separada del listado principal.</p></div><span class="status-chip">CRUD equipo</span></div>
            <form method="post" class="gestion-form team-sheet-form" enctype="multipart/form-data">
                <input type="hidden" name="action" value="save_team">
                <input type="hidden" name="team_id" data-gestion-team-id>
                <label>Editar equipo existente<select class="form-select" data-gestion-team-select><option value="">Nuevo equipo</option><?php foreach ($teams as $team): ?><option value="<?php echo e((string) $team['id']); ?>"><?php echo e((string) $team['name']); ?></option><?php endforeach; ?></select></label>
                <div class="team-sheet-layout">
                    <div class="team-logo-preview" data-team-logo-preview><i class="bi bi-shield"></i></div>
                    <div class="team-fields-grid">
                        <label>Nombre del equipo<input class="form-control" name="team_name" data-gestion-team-name required></label>
                        <label>Codigo<input class="form-control" name="team_code" data-gestion-team-code maxlength="12" placeholder="HSE"></label>
                        <label>Simbolo<input class="form-control" name="team_symbol" data-gestion-team-symbol maxlength="24" placeholder="HS"></label>
                        <label>Ciudad<input class="form-control" name="team_city" data-gestion-team-city placeholder="Tulua"></label>
                        <label class="team-logo-field">Subir logo de equipo<input class="form-control" type="file" name="team_logo" accept="image/png,image/jpeg,image/webp,image/gif"><small>PNG, JPG, WEBP o GIF. Si subes uno nuevo, reemplaza el logo actual.</small></label>
                    </div>
                </div>
                <div class="team-sheet-actions">
                    <button class="btn btn-outline-info fw-bold" type="button" data-open-logo-history="team"><i class="bi bi-images"></i> Historial de logos</button>
                    <button class="btn btn-outline-info fw-bold" type="button" data-open-team-decks><i class="bi bi-link-45deg"></i> Asociar / desasociar decks</button>
                    <button class="btn btn-primary fw-bold" type="submit"><i class="bi bi-save"></i> Guardar equipo</button>
                </div>
            </form>
        </section>
        <section class="team-performance-grid mt-3">
            <article class="panel team-stats-panel">
                <div class="panel-head"><div><h2>Estadisticas generales</h2><p>Resultados acumulados de todos los decks asociados.</p></div><span class="status-chip" data-team-stats-record>0-0-0</span></div>
                <div class="team-stat-grid" data-team-stat-grid>
                    <span><small>Eventos</small><b data-team-stat-events>0</b></span>
                    <span><small>Matches</small><b data-team-stat-matches>0</b></span>
                    <span><small>Ganados</small><b data-team-stat-wins>0</b></span>
                    <span><small>Perdidos</small><b data-team-stat-losses>0</b></span>
                    <span><small>Empates</small><b data-team-stat-draws>0</b></span>
                    <span><small>Duelos ganados</small><b data-team-stat-duels-won>0</b></span>
                    <span><small>Duelos perdidos</small><b data-team-stat-duels-lost>0</b></span>
                    <span><small>Duelos empatados</small><b data-team-stat-duels-drawn>0</b></span>
                    <span><small>Winrate</small><b data-team-stat-winrate>0%</b></span>
                    <span><small>Manos brick</small><b data-team-stat-bricks>0</b></span>
                    <span><small>Promedio de turnos</small><b data-team-stat-turns>0</b></span>
                </div>
                <div class="tier-history-summary mt-3" data-team-tier-history></div>
                <div class="team-rival-grid mt-3">
                    <section><h3>Decks que mas ha vencido</h3><div class="profile-list" data-team-top-beaten></div></section>
                    <section><h3>Decks que mas lo han derrotado</h3><div class="profile-list" data-team-top-lost-to></div></section>
                </div>
            </article>
            <article class="panel team-honors-panel">
                <div class="panel-head"><div><h2>Vitrina de logros</h2><p>Medallas obtenidas al ganar eventos.</p></div><div class="team-honor-actions"><span class="status-chip" data-team-honor-count>0 logros</span><button class="btn btn-outline-warning btn-sm" type="button" data-open-gestion-module="resumen"><i class="bi bi-plus-circle"></i> Registrar</button></div></div>
                <div class="team-honor-shelf" data-team-honor-shelf><p class="note">Selecciona un equipo para consultar su palmares.</p></div>
            </article>
        </section>
        <section class="panel mt-3 team-event-history-panel">
            <div class="panel-head"><div><h2>Rendimiento por evento</h2><p>Matches y duelos separados por competencia.</p></div></div>
            <div class="table-responsive">
                <table class="table app-table compact mobile-card-table team-event-history-table">
                    <thead><tr><th>Evento</th><th>Formato</th><th>MJ</th><th>MG</th><th>MP</th><th>DG</th><th>DP</th><th>Winrate</th></tr></thead>
                    <tbody data-team-event-history><tr><td colspan="8">Selecciona un equipo.</td></tr></tbody>
                </table>
            </div>
        </section>
    </section>

    <section class="screen" data-screen="estadisticas" hidden>
        <?php if ($publicSeasonId > 0): ?><section class="public-season-banner"><i class="bi bi-eye"></i><div><small>Vista publica de temporada</small><strong><?php echo e($publicSeasonName); ?></strong><span>Pagina de consulta sin controles administrativos. Muestra resultados, ranking y metagame de esta temporada.</span></div><a class="btn btn-outline-info" href="./?export=entity_report&amp;entity_type=season&amp;entity_id=<?php echo (int) $publicSeasonId; ?>&amp;format=pdf"><i class="bi bi-file-earmark-pdf"></i> Informe PDF</a></section><?php endif; ?>
        <div data-stats-tables-panel hidden>
            <h2>Tablas</h2><p>Posiciones por evento y división, calculadas desde resultados reales.</p>
            <div class="control-strip"><select class="form-select" data-table-division><?php foreach ($divisions as $key => $division): ?><option value="<?php echo e($key); ?>"><?php echo e($division['short']); ?></option><?php endforeach; ?></select></div>
            <section class="panel"><div class="table-responsive"><table class="table app-table standings"><thead><tr><th>Pos</th><th>Equipo / Deck</th><th>PTS</th><th>PJ</th><th>PG</th><th>PE</th><th>PP</th><th>CG</th><th>CP</th><th>DP</th><th>Zona</th></tr></thead><tbody data-standings-body></tbody></table></div><p class="note">Criterios: PTS, DP, CG, H2H. Si persiste empate extremo: combate unico.</p></section>
        </div>
    </section>

    <section class="screen" data-screen="torneos" hidden>
        <div class="page-title with-actions module-title"><div><h1><?php echo e($uiText('route.torneos', 'title', 'Eventos competitivos')); ?></h1><p><?php echo e($uiText('route.torneos', 'subtitle', 'Administra eventos desde la tabla principal.')); ?></p></div><button class="btn btn-primary fw-bold" type="button" data-route="crear_evento"><i class="bi bi-plus-circle"></i> Crear evento</button></div>
        <p class="alert alert-warning" role="alert" data-event-open-error hidden></p>
        <nav class="event-module-navigation" aria-label="Modulos de eventos competitivos">
            <button class="btn btn-outline-info" type="button" data-route="torneos" data-event-type-shortcut="league"><i class="bi <?php echo e($routes['ligas'][1]); ?>"></i> <span><?php echo e($routes['ligas'][0]); ?></span></button>
            <button class="btn btn-outline-info" type="button" data-route="torneos" data-event-type-shortcut="tournament"><i class="bi bi-trophy"></i> <span>Torneos</span></button>
            <button class="btn btn-outline-info" type="button" data-route="torneos" data-event-type-shortcut="special"><i class="bi <?php echo e($routes['eventos_especiales'][1]); ?>"></i> <span><?php echo e($routes['eventos_especiales'][0]); ?></span></button>
            <button class="btn btn-outline-info" type="button" data-route="clasificatorio"><i class="bi <?php echo e($routes['clasificatorio'][1]); ?>"></i> <span><?php echo e($routes['clasificatorio'][0]); ?></span></button>
            <button class="btn btn-outline-info" type="button" data-route="copas"><i class="bi <?php echo e($routes['copas'][1]); ?>"></i> <span><?php echo e($routes['copas'][0]); ?></span></button>
            <button class="btn btn-outline-info" type="button" data-route="mundial"><i class="bi <?php echo e($routes['mundial'][1]); ?>"></i> <span><?php echo e($routes['mundial'][0]); ?></span></button>
            <button class="btn btn-outline-info fw-bold" type="button" data-route="divisiones"><i class="bi bi-diagram-3"></i> Divisiones</button>
        </nav>
        <section class="panel event-filter-panel mt-3">
            <details class="mobile-collapsible event-filter-shell" open>
                <summary><span><i class="bi bi-funnel"></i> Filtros de busqueda</span><i class="bi bi-chevron-down"></i></summary>
            <div class="event-search-filters">
                <label>Buscar evento<input class="form-control" type="search" placeholder="Nombre, codigo, formato, regla o banlist" data-event-search-filter></label>
                <label>Tipo<select class="form-select" data-event-type-filter><option value="">Todos</option><?php foreach ($competitionTypeOptions as $eventType => $eventTypeMeta): ?><option value="<?php echo e($eventType); ?>"><?php echo e((string) $eventTypeMeta['label']); ?></option><?php endforeach; ?></select></label>
                <label>Estado<select class="form-select" data-event-status-filter><option value="">Todos</option><?php foreach ($competitionStatusOptions as $statusCode => $statusLabel): ?><?php if ($statusCode === 'resume') { continue; } ?><option value="<?php echo e($statusCode); ?>"><?php echo e($statusLabel); ?></option><?php endforeach; ?></select></label>
                <label>Formato<select class="form-select" data-event-division-filter><option value="">Todos</option><option value="division:General">General</option><optgroup label="Formatos disponibles"><?php foreach ($formatSelectOptions as $rulesetCode => $rulesetLabel): ?><option value="ruleset:<?php echo e((string) $rulesetCode); ?>"><?php echo e((string) $rulesetLabel); ?></option><?php endforeach; ?></optgroup><?php if ($displayDivisions): ?><optgroup label="Divisiones"><?php foreach ($displayDivisions as $division): ?><option value="division:<?php echo e((string) (($division['code'] ?? '') ?: ($division['short'] ?? ''))); ?>"><?php echo e((string) (($division['short'] ?? '') ?: ($division['code'] ?? 'Formato'))); ?></option><?php endforeach; ?></optgroup><?php endif; ?></select></label>
                <button class="btn btn-outline-light" type="button" data-event-filter-clear><i class="bi bi-x-circle"></i> Limpiar</button>
            </div>
            </details>
        </section>
        <section class="panel mt-3">
            <div class="panel-head"><div><h2>Tabla de eventos</h2><p>Consulta general de estado, formato, equipos y programacion persistida.</p></div><span class="status-chip"><?php echo count($competitions); ?> eventos</span></div>
            <div class="table-responsive">
                <table class="table app-table compact event-sort-table" data-event-sort-table>
                    <thead><tr><th><button type="button" data-event-sort="name">Evento <i class="bi bi-arrow-down-up"></i></button></th><th><button type="button" data-event-sort="type">Tipo <i class="bi bi-arrow-down-up"></i></button></th><th><button type="button" data-event-sort="status">Estado <i class="bi bi-arrow-down-up"></i></button></th><th><button type="button" data-event-sort="division">Formato <i class="bi bi-arrow-down-up"></i></button></th><th><button type="button" data-event-sort="match">Match <i class="bi bi-arrow-down-up"></i></button></th><th><button type="button" data-event-sort="start">Inicio <i class="bi bi-arrow-down-up"></i></button></th><th><button type="button" data-event-sort="progress">Progreso <i class="bi bi-arrow-down-up"></i></button></th><th><button type="button" data-event-sort="decks">Divisiones / equipos <i class="bi bi-arrow-down-up"></i></button></th><th>Acciones</th></tr></thead>
                    <tbody>
                    <?php foreach ($competitions as $competition): ?>
                        <?php
                        $tableType = (string) ($competition['type'] ?? 'special');
                        $tableStatus = (string) ($competition['status'] ?? 'pending_configuration');
                        $tableStatusKey = ygo_normalize_status_key($tableStatus);
                        $tableStatusLabels = ygo_event_status_labels() + ['draft' => 'Pendiente configuracion', 'finalized' => 'Finalizado'];
                        $tableStatusClass = ygo_event_status_class($tableStatus);
                        $tableDivision = (string) (($competition['division_code'] ?? '') ?: 'General');
                        $tableRuleset = (string) (($competition['event_ruleset'] ?? '') ?: 'custom');
                        $tableFormat = (string) ($rulesetSelectOptions[$tableRuleset] ?? $eventRulesetOptions[$tableRuleset] ?? 'Por definir');
                        $tableProgress = $competition['progress'] ?? ['done'=>0,'total'=>0,'round'=>null,'ratio'=>0];
                        $tableAccent = (string) (($competitionTypeOptions[$tableType]['accent'] ?? '') ?: '#00d9ff');
                        $tableTeamCount = (int) ($competition['participant_team_count'] ?? 0);
                        $tableStructureCount = $tableType === 'league' ? (int) ($competition['division_count'] ?? 0) : $tableTeamCount;
                        $tableStructureLabel = $tableType === 'league'
                            ? $tableStructureCount . ($tableStructureCount === 1 ? ' división' : ' divisiones')
                            : $tableTeamCount . ($tableTeamCount === 1 ? ' equipo' : ' equipos');
                        ?>
                        <tr class="<?php echo e($tableStatusClass); ?>" style="--event-accent: <?php echo e($tableAccent); ?>; --accent: <?php echo e($tableAccent); ?>;" data-event-card data-event-season="<?php echo (int) $competition['season_id']; ?>" data-event-type="<?php echo e($tableType); ?>" data-event-ruleset="<?php echo e((string) ($competition['event_ruleset'] ?? 'custom')); ?>" data-event-version="<?php echo e((string) ($competition['ruleset_version'] ?? '')); ?>" data-event-status="<?php echo e($tableStatusKey); ?>" data-event-division="<?php echo e($tableDivision); ?>" data-sort-name="<?php echo e(strtolower((string) ($competition['name'] ?? ''))); ?>" data-sort-type="<?php echo e(strtolower((string) (($competitionTypeOptions[$tableType]['label'] ?? '') ?: $tableType))); ?>" data-sort-status="<?php echo e($tableStatusKey); ?>" data-sort-division="<?php echo e(strtolower($tableFormat)); ?>" data-sort-match="<?php echo e(strtolower((string) (($competition['match_format'] ?? '') ?: '1v1'))); ?>" data-sort-start="<?php echo e((string) (($competition['starts_on'] ?? '') ?: '9999-12-31')); ?>" data-sort-progress="<?php echo e((string)$tableProgress['ratio']); ?>" data-sort-decks="<?php echo e((string) $tableStructureCount); ?>" data-event-search="<?php echo e(strtolower((string) (($competition['name'] ?? '') . ' ' . $tableFormat . ' ' . ($competition['code'] ?? '') . ' ' . ($competition['division_code'] ?? '') . ' ' . ($competition['event_ruleset'] ?? '') . ' ' . ($competition['ruleset_version'] ?? '') . ' ' . ygo_event_status_label($tableStatus)))); ?>">
                            <td data-label="Evento">
                                <div class="event-table-title">
                                    <span class="event-table-logo"><?php if (!empty($competition['logo_url'])): ?><img src="<?php echo e((string) $competition['logo_url']); ?>" alt="<?php echo e((string) ($competition['name'] ?? 'Evento')); ?>"><?php else: ?><i class="bi bi-trophy"></i><?php endif; ?></span>
                                    <span><strong><?php echo e((string) ($competition['name'] ?? 'Evento')); ?></strong><br><small><?php echo e((string) ($competition['code'] ?? '')); ?></small></span>
                                </div>
                            </td>
                            <td data-label="Tipo"><?php echo e((string) (($competitionTypeOptions[$tableType]['label'] ?? '') ?: $tableType)); ?></td>
                            <td data-label="Estado"><span class="status-chip <?php echo e($tableStatusClass); ?>"><?php echo e($tableStatusLabels[$tableStatus] ?? ygo_event_status_label($tableStatus)); ?></span></td>
                            <td data-label="Formato"><?php echo e($tableFormat); ?></td>
                            <td data-label="Match"><?php echo e(strtoupper((string) (($competition['match_format'] ?? '') ?: '1v1'))); ?></td>
                            <td data-label="Inicio"><?php echo e((string) (($competition['starts_on'] ?? '') ?: 'Sin fecha')); ?></td>
                            <td data-label="Progreso"><strong><?php echo (int)$tableProgress['done']; ?>/<?php echo (int)$tableProgress['total']; ?></strong><small class="d-block"><?php echo $tableProgress['round'] !== null ? 'Ronda ' . (int)$tableProgress['round'] . ' | ' : ''; ?><?php echo (int)$tableProgress['total'] > 0 ? 'combates completados' : 'Sin combates programados'; ?></small></td>
                            <td data-label="<?php echo $tableType === 'league' ? 'Divisiones' : 'Equipos'; ?>"><?php echo e($tableStructureLabel); ?></td>
                            <td data-label="Acciones"><button class="btn btn-outline-info btn-sm" type="button" data-event-open="<?php echo e((string) ($competition['id'] ?? '')); ?>"><i class="bi bi-eye"></i> Ver</button></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </section>
    </section>

    <section class="screen" data-screen="copas" hidden>
        <div class="page-title with-actions">
            <div><h1><?php echo e($uiText('route.copas', 'title', 'Copas')); ?></h1><p><?php echo e($uiText('route.copas', 'subtitle', 'Consulta solo copas y finales persistidas en base de datos, con su llave real y fechas programadas.')); ?></p></div>
            <button class="btn btn-primary fw-bold" type="button" data-route="crear_evento"><i class="bi bi-plus-circle"></i> Crear copa</button>
        </div>
        <div class="cup-db-stats">
            <article><span>Copas en BD</span><b><?php echo number_format(count($cupCompetitions)); ?></b></article>
            <article><span>Activas</span><b><?php echo number_format($cupActiveCount); ?></b></article>
            <article><span>Combates</span><b><?php echo number_format($cupTotalMatches); ?></b></article>
            <article><span>Finalizadas/anuladas</span><b><?php echo number_format($cupCompletedCount); ?></b></article>
        </div>
        <section class="panel event-filter-panel cup-filter-panel">
            <details class="mobile-collapsible" open>
                <summary><span><i class="bi bi-funnel"></i> Filtros de copas</span><i class="bi bi-chevron-down"></i></summary>
                <div class="event-filter-row">
                    <label>Buscar<input class="form-control" type="search" data-cup-search placeholder="Nombre, codigo, formato o estado"></label>
                    <label>Estado<select class="form-select" data-cup-status-filter><option value="">Todos</option><?php foreach ($competitionStatusOptions as $statusCode => $statusLabel): ?><?php if ($statusCode === 'resume') { continue; } ?><option value="<?php echo e($statusCode); ?>"><?php echo e($statusLabel); ?></option><?php endforeach; ?></select></label>
                    <label>Reglamento<select class="form-select" data-cup-ruleset-filter><option value="">Todos</option><?php foreach ($rulesetSelectOptions as $rulesetCode => $rulesetLabel): ?><option value="<?php echo e((string) $rulesetCode); ?>"><?php echo e((string) $rulesetLabel); ?></option><?php endforeach; ?></select></label>
                    <button class="btn btn-outline-light" type="button" data-cup-filter-clear><i class="bi bi-x-circle"></i> Limpiar</button>
                </div>
            </details>
        </section>
        <section class="panel mt-3">
            <div class="panel-head"><div><h2>Tabla de copas</h2><p>Consulta, filtra y abre la ficha de cada copa persistida.</p></div><span class="status-chip" data-cup-visible-count><?php echo count($cupCompetitions); ?> copas</span></div>
            <div class="table-responsive">
                <table class="table app-table compact cup-sort-table" data-cup-sort-table>
                    <thead><tr><th><button type="button" data-cup-sort="name">Copa <i class="bi bi-arrow-down-up"></i></button></th><th><button type="button" data-cup-sort="status">Estado <i class="bi bi-arrow-down-up"></i></button></th><th><button type="button" data-cup-sort="ruleset">Reglamento <i class="bi bi-arrow-down-up"></i></button></th><th><button type="button" data-cup-sort="start">Inicio <i class="bi bi-arrow-down-up"></i></button></th><th><button type="button" data-cup-sort="decks">Decks <i class="bi bi-arrow-down-up"></i></button></th><th><button type="button" data-cup-sort="matches">Combates <i class="bi bi-arrow-down-up"></i></button></th><th>Acciones</th></tr></thead>
                    <tbody>
            <?php foreach ($cupCompetitions as $cupCompetition): ?>
                <?php
                    $cupId = (int) ($cupCompetition['id'] ?? 0);
                    $cupType = (string) (($cupCompetition['type'] ?? '') ?: 'cup');
                    $cupAccent = (string) (($competitionTypeOptions[$cupType]['accent'] ?? '') ?: '#ff2bd6');
                    $cupStatusClass = ygo_event_status_class((string) ($cupCompetition['status'] ?? 'pending_configuration'));
                    $cupStatusLabel = ygo_event_status_label((string) ($cupCompetition['status'] ?? 'pending_configuration'));
                    $cupRuleset = (string) (($rulesetSelectOptions[(string) ($cupCompetition['event_ruleset'] ?? '')] ?? '') ?: (($cupCompetition['event_ruleset'] ?? '') ?: 'Sin reglamento'));
                    $cupStats = $cupStatsByCompetition[$cupId] ?? ['total' => 0, 'scheduled' => 0, 'finalized' => 0, 'pending' => 0, 'dates' => []];
                    $cupStatusKey = ygo_normalize_status_key((string) ($cupCompetition['status'] ?? 'pending_configuration'));
                    $cupSearch = strtolower((string) (($cupCompetition['name'] ?? '') . ' ' . ($cupCompetition['code'] ?? '') . ' ' . ($cupCompetition['event_ruleset'] ?? '') . ' ' . $cupStatusLabel));
                ?>
                <tr class="<?php echo e($cupStatusClass); ?>" style="--event-accent: <?php echo e($cupAccent); ?>; --accent: <?php echo e($cupAccent); ?>;" data-cup-row data-cup-status="<?php echo e($cupStatusKey); ?>" data-cup-ruleset="<?php echo e((string) ($cupCompetition['event_ruleset'] ?? '')); ?>" data-cup-search="<?php echo e($cupSearch); ?>" data-sort-name="<?php echo e(strtolower((string) ($cupCompetition['name'] ?? ''))); ?>" data-sort-status="<?php echo e($cupStatusKey); ?>" data-sort-ruleset="<?php echo e(strtolower($cupRuleset)); ?>" data-sort-start="<?php echo e((string) (($cupCompetition['starts_on'] ?? '') ?: '9999-12-31')); ?>" data-sort-decks="<?php echo e((string) ((int) ($cupCompetition['deck_pool_count'] ?? 0))); ?>" data-sort-matches="<?php echo e((string) ((int) ($cupStats['total'] ?? 0))); ?>">
                    <td data-label="Copa"><div class="event-table-title"><span class="event-table-logo"><?php if (!empty($cupCompetition['logo_url'])): ?><img src="<?php echo e((string) $cupCompetition['logo_url']); ?>" alt="<?php echo e((string) ($cupCompetition['name'] ?? 'Copa')); ?>"><?php else: ?><i class="bi bi-award"></i><?php endif; ?></span><span><strong><?php echo e((string) ($cupCompetition['name'] ?? 'Copa')); ?></strong><br><small><?php echo e((string) ($cupCompetition['code'] ?? '')); ?></small></span></div></td>
                    <td data-label="Estado"><span class="status-chip <?php echo e($cupStatusClass); ?>"><?php echo e($cupStatusLabel); ?></span></td>
                    <td data-label="Reglamento"><?php echo e($cupRuleset); ?><br><small><?php echo e((string) (($cupCompetition['event_ruleset'] ?? '') ?: 'custom')); ?></small></td>
                    <td data-label="Inicio"><?php echo e((string) (($cupCompetition['starts_on'] ?? '') ?: 'Sin fecha')); ?></td>
                    <td data-label="Decks"><?php echo e((string) ((int) ($cupCompetition['deck_pool_count'] ?? 0))); ?></td>
                    <td data-label="Combates"><?php echo e((string) ((int) ($cupStats['total'] ?? 0))); ?></td>
                    <td data-label="Acciones"><button class="btn btn-outline-info btn-sm" type="button" data-event-open="<?php echo e((string) $cupId); ?>"><i class="bi bi-card-checklist"></i> Abrir ficha</button></td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$cupCompetitions): ?><tr><td colspan="7"><div class="empty-state">No hay copas ni finales registradas en base de datos.</div></td></tr><?php endif; ?>
                    </tbody>
                </table>
            </div>
        </section>
    </section>

    <section class="screen" data-screen="mundial" hidden>
        <div class="page-title with-actions">
            <div><h1><?php echo e($uiText('route.mundial', 'title', 'Mundial')); ?></h1><p><?php echo e($uiText('route.mundial', 'subtitle', 'Eventos tipo Mundial registrados en base de datos, con estado, decks y combates asociados.')); ?></p></div>
            <button class="btn btn-primary fw-bold" type="button" data-route="crear_evento"><i class="bi bi-plus-circle"></i> Crear mundial</button>
        </div>
        <div class="cup-db-stats world-db-stats">
            <article><span>Mundiales en BD</span><b><?php echo number_format(count($worldCompetitions)); ?></b></article>
            <article><span>Activos</span><b><?php echo number_format($worldActiveCount); ?></b></article>
            <article><span>Combates</span><b><?php echo number_format($worldTotalMatches); ?></b></article>
            <article><span>Decks registrados</span><b><?php echo number_format(array_sum(array_map(static fn(array $competition): int => (int) ($competition['deck_pool_count'] ?? 0), $worldCompetitions))); ?></b></article>
        </div>
        <section class="panel mt-3">
            <div class="panel-head"><div><h2>Tabla de mundiales</h2><p>Consulta y entra a la ficha de cada Mundial persistido.</p></div><span class="status-chip"><?php echo count($worldCompetitions); ?> mundiales</span></div>
            <div class="table-responsive">
                <table class="table app-table compact event-sort-table">
                    <thead><tr><th>Evento</th><th>Estado</th><th>Reglamento</th><th>Inicio</th><th>Decks</th><th>Combates</th><th>Acciones</th></tr></thead>
                    <tbody>
                    <?php foreach ($worldCompetitions as $worldCompetition): ?>
                        <?php
                        $worldId = (int) ($worldCompetition['id'] ?? 0);
                        $worldStatusClass = ygo_event_status_class((string) ($worldCompetition['status'] ?? 'pending_configuration'));
                        $worldRuleset = (string) (($rulesetSelectOptions[(string) ($worldCompetition['event_ruleset'] ?? '')] ?? '') ?: (($worldCompetition['event_ruleset'] ?? '') ?: 'Sin reglamento'));
                        $worldStats = $worldStatsByCompetition[$worldId] ?? ['total' => 0, 'scheduled' => 0, 'finalized' => 0, 'pending' => 0];
                        ?>
                        <tr class="<?php echo e($worldStatusClass); ?>" style="--event-accent: <?php echo e((string) ($competitionTypeOptions['world']['accent'] ?? '#9b5cff')); ?>;">
                            <td data-label="Evento"><div class="event-table-title"><span class="event-table-logo"><?php if (!empty($worldCompetition['logo_url'])): ?><img src="<?php echo e((string) $worldCompetition['logo_url']); ?>" alt="<?php echo e((string) ($worldCompetition['name'] ?? 'Mundial')); ?>"><?php else: ?><i class="bi bi-globe-americas"></i><?php endif; ?></span><span><strong><?php echo e((string) ($worldCompetition['name'] ?? 'Mundial')); ?></strong><br><small><?php echo e((string) ($worldCompetition['code'] ?? '')); ?></small></span></div></td>
                            <td data-label="Estado"><span class="status-chip <?php echo e($worldStatusClass); ?>"><?php echo e(ygo_event_status_label((string) ($worldCompetition['status'] ?? 'pending_configuration'))); ?></span></td>
                            <td data-label="Reglamento"><?php echo e($worldRuleset); ?></td>
                            <td data-label="Inicio"><?php echo e((string) (($worldCompetition['starts_on'] ?? '') ?: 'Sin fecha')); ?></td>
                            <td data-label="Decks"><?php echo e((string) ((int) ($worldCompetition['deck_pool_count'] ?? 0))); ?></td>
                            <td data-label="Combates"><?php echo e((string) ((int) ($worldStats['total'] ?? 0))); ?></td>
                            <td data-label="Acciones"><button class="btn btn-outline-info btn-sm" type="button" data-event-open="<?php echo e((string) $worldId); ?>"><i class="bi bi-card-checklist"></i> Abrir ficha</button></td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (!$worldCompetitions): ?><tr><td colspan="7"><div class="empty-state">No hay eventos tipo Mundial registrados en base de datos.</div></td></tr><?php endif; ?>
                    </tbody>
                </table>
            </div>
        </section>
    </section>

    <section class="screen" data-screen="herramienta_lp" hidden>
        <div class="page-title with-actions module-title">
            <div><h1><?php echo e($uiText('route.herramienta_lp', 'title', 'Calculadora LP')); ?></h1><p><?php echo e($uiText('route.herramienta_lp', 'subtitle', 'Herramienta de puntos de vida para duelos estilo YUGIOH Neuron.')); ?></p></div>
            <button class="btn btn-outline-light" type="button" data-lp-full-reset><i class="bi bi-arrow-counterclockwise"></i> Reset duelo</button>
        </div>
        <section class="lp-tool-shell" aria-label="Calculadora de puntos de vida">
            <div class="lp-config-rig">
                <div class="lp-config-presets" role="group" aria-label="Puntos iniciales">
                    <button type="button" data-lp-start-preset="8000">8000</button>
                    <button type="button" data-lp-start-preset="4000">4000</button>
                    <button type="button" data-lp-start-preset="16000">16000</button>
                </div>
                <label><span>LP inicial D1</span><input class="form-control" type="number" inputmode="numeric" min="0" step="100" data-lp-start="a" value="8000"></label>
                <label><span>LP inicial D2</span><input class="form-control" type="number" inputmode="numeric" min="0" step="100" data-lp-start="b" value="8000"></label>
                <button type="button" data-lp-apply-start><i class="bi bi-check2-circle"></i> Aplicar LP</button>
            </div>
            <div class="lp-timer-rig">
                <span>Timer</span>
                <strong data-lp-timer-display>00:00:00</strong>
                <div class="lp-timer-actions">
                    <button type="button" data-lp-timer-toggle><i class="bi bi-play-fill"></i> Iniciar</button>
                    <button type="button" data-lp-timer-reset><i class="bi bi-stop-fill"></i> Reset</button>
                </div>
            </div>
            <div class="lp-duel-board">
                <article class="lp-player lp-player-a" data-lp-player="a">
                    <div class="lp-player-head">
                        <label><span>Duelista 1</span><input class="form-control" data-lp-name="a" value="Duelista 1"></label>
                        <button type="button" data-lp-action="half" data-lp-target="a">1/2</button>
                    </div>
                    <div class="lp-display" data-lp-display="a">8000</div>
                    <div class="lp-bar"><i data-lp-bar="a"></i></div>
                    <div class="lp-controls" data-lp-controls="a">
                        <button type="button" data-lp-delta="-1000" data-lp-target="a">-1000</button>
                        <button type="button" data-lp-delta="-500" data-lp-target="a">-500</button>
                        <button type="button" data-lp-delta="-100" data-lp-target="a">-100</button>
                        <button type="button" data-lp-delta="-50" data-lp-target="a">-50</button>
                        <button type="button" data-lp-delta="50" data-lp-target="a">+50</button>
                        <button type="button" data-lp-delta="100" data-lp-target="a">+100</button>
                        <button type="button" data-lp-delta="500" data-lp-target="a">+500</button>
                        <button type="button" data-lp-delta="1000" data-lp-target="a">+1000</button>
                        <button type="button" data-lp-delta="2000" data-lp-target="a">+2000</button>
                    </div>
                    <div class="lp-custom">
                        <input class="form-control" type="number" inputmode="numeric" min="0" step="100" placeholder="Valor" data-lp-custom="a">
                        <button type="button" data-lp-custom-apply="subtract" data-lp-target="a">Restar</button>
                        <button type="button" data-lp-custom-apply="add" data-lp-target="a">Sumar</button>
                        <button type="button" data-lp-custom-apply="set" data-lp-target="a">Fijar</button>
                        <button type="button" data-lp-action="zero" data-lp-target="a">0 LP</button>
                    </div>
                </article>
                <div class="lp-center-column">
                    <button type="button" data-lp-action="undo"><i class="bi bi-arrow-counterclockwise"></i><span>Undo</span></button>
                    <button type="button" data-lp-action="reset"><i class="bi bi-arrow-clockwise"></i><span>8000</span></button>
                    <button type="button" data-lp-action="swap"><i class="bi bi-arrow-left-right"></i><span>Swap</span></button>
                    <button type="button" data-lp-action="turn"><i class="bi bi-arrow-repeat"></i><span>Turno <b data-lp-turn>1</b></span></button>
                    <button type="button" data-lp-action="coin"><i class="bi bi-circle-half"></i><span data-lp-coin>Moneda</span></button>
                    <button type="button" data-lp-action="dice"><i class="bi bi-dice-5"></i><span>Dado <b data-lp-dice>-</b></span></button>
                </div>
                <article class="lp-player lp-player-b" data-lp-player="b">
                    <div class="lp-player-head">
                        <label><span>Duelista 2</span><input class="form-control" data-lp-name="b" value="Duelista 2"></label>
                        <button type="button" data-lp-action="half" data-lp-target="b">1/2</button>
                    </div>
                    <div class="lp-display" data-lp-display="b">8000</div>
                    <div class="lp-bar"><i data-lp-bar="b"></i></div>
                    <div class="lp-controls" data-lp-controls="b">
                        <button type="button" data-lp-delta="-1000" data-lp-target="b">-1000</button>
                        <button type="button" data-lp-delta="-500" data-lp-target="b">-500</button>
                        <button type="button" data-lp-delta="-100" data-lp-target="b">-100</button>
                        <button type="button" data-lp-delta="-50" data-lp-target="b">-50</button>
                        <button type="button" data-lp-delta="50" data-lp-target="b">+50</button>
                        <button type="button" data-lp-delta="100" data-lp-target="b">+100</button>
                        <button type="button" data-lp-delta="500" data-lp-target="b">+500</button>
                        <button type="button" data-lp-delta="1000" data-lp-target="b">+1000</button>
                        <button type="button" data-lp-delta="2000" data-lp-target="b">+2000</button>
                    </div>
                    <div class="lp-custom">
                        <input class="form-control" type="number" inputmode="numeric" min="0" step="100" placeholder="Valor" data-lp-custom="b">
                        <button type="button" data-lp-custom-apply="subtract" data-lp-target="b">Restar</button>
                        <button type="button" data-lp-custom-apply="add" data-lp-target="b">Sumar</button>
                        <button type="button" data-lp-custom-apply="set" data-lp-target="b">Fijar</button>
                        <button type="button" data-lp-action="zero" data-lp-target="b">0 LP</button>
                    </div>
                </article>
            </div>
            <div class="lp-history-panel">
                <div class="panel-head compact-head"><div><h2>Historial</h2><p>Ultimos cambios de puntos de vida en esta mesa.</p></div><span class="status-chip" data-lp-status>Listo</span></div>
                <div class="lp-history-list" data-lp-history></div>
            </div>
        </section>
    </section>

    <?php require __DIR__.'/includes/news_view.php'; ?>

    <section class="screen" data-screen="reglas" hidden>
        <div class="page-title with-actions module-title">
            <div><h1><?php echo e($uiText('route.reglas', 'title', 'Reglas especiales')); ?></h1><p><?php echo e($uiText('route.reglas', 'subtitle', 'CRUD de reglas custom para juegos, experiencia, bonus, sanciones y condiciones operativas.')); ?></p></div>
            <button class="btn btn-outline-light" type="button" data-route="reglamento"><i class="bi bi-journal-check"></i> Ver reglamentos</button>
        </div>
        <div class="module-kpi-strip rules-kpi-strip mb-3">
            <article><i class="bi bi-sliders"></i><span>Reglas BD</span><b><?php echo number_format(count($gameRules)); ?></b></article>
            <article><i class="bi bi-check-circle"></i><span>Activas</span><b><?php echo number_format($activeGameRuleCount); ?></b></article>
            <article><i class="bi bi-pause-circle"></i><span>Inactivas</span><b><?php echo number_format(max(0, count($gameRules) - $activeGameRuleCount)); ?></b></article>
            <article><i class="bi bi-diagram-3"></i><span>Eventos</span><b><?php echo number_format(count($competitions)); ?></b></article>
        </div>
        <div class="game-rules-layout">
            <section class="panel game-rule-editor">
                <div class="panel-head"><div><h2>Configurar regla</h2><p>Las reglas se guardan en base de datos y pueden aplicarse a eventos o juegos.</p></div><span class="status-chip"><?php echo count($gameRules); ?> reglas</span></div>
                <form method="post" class="gestion-form" enctype="multipart/form-data">
                    <input type="hidden" name="action" value="save_game_rule">
                    <input type="hidden" name="game_rule_id" data-game-rule-id>
                    <label>Editar regla<select class="form-select" data-game-rule-select><option value="">Nueva regla</option><?php foreach ($gameRules as $rule): ?><option value="<?php echo e((string) $rule['id']); ?>"><?php echo e((string) $rule['name']); ?><?php echo empty($rule['active']) ? ' (inactiva)' : ''; ?></option><?php endforeach; ?></select></label>
                    <div class="two-col">
                        <label>Codigo<input class="form-control" name="game_rule_code" data-game-rule-code placeholder="CUSTOM_XP"></label>
                        <label>Nombre<input class="form-control" name="game_rule_name" data-game-rule-name required placeholder="CUSTOM XP"></label>
                    </div>
                    <div class="three-col">
                        <label>Tipo<select class="form-select" name="game_rule_type" data-game-rule-type><option value="xp">XP</option><option value="bonus">Bonus</option><option value="penalty">Sancion</option><option value="tie_break">Desempate</option><option value="custom">Custom</option></select></label>
                        <label>Aplica a<select class="form-select" name="game_rule_scope" data-game-rule-scope><option value="event">Evento</option><option value="all_matches">Eventos y amistosos</option><option value="friendly">Duelos amistosos</option><option value="match">Duelo manual</option><option value="deck">Deck</option><option value="team">Equipo</option><option value="season">Temporada</option><option value="general">General</option></select></label>
                        <label>Prioridad<input class="form-control" type="number" min="1" name="game_rule_priority" data-game-rule-priority value="100"></label>
                    </div>
                    <label>Cuando ocurre<input class="form-control" name="game_rule_trigger" data-game-rule-trigger placeholder="Resultado de duelo validado"></label>
                    <label>Efecto<textarea class="form-control" name="game_rule_effect" data-game-rule-effect rows="4" placeholder="Describe que hace la regla y como se calcula"></textarea></label>
                    <div class="two-col">
                        <label>Valor XP / puntos<input class="form-control" type="number" name="game_rule_points" data-game-rule-points value="0"></label>
                        <label class="toggle-line"><input type="checkbox" name="game_rule_active" value="1" data-game-rule-active checked> Regla activa</label>
                    </div>
                    <label>Notas<textarea class="form-control" name="game_rule_notes" data-game-rule-notes rows="3" placeholder="Restricciones, excepciones o referencias internas"></textarea></label>
                    <button class="btn btn-primary fw-bold" type="submit"><i class="bi bi-save"></i> Guardar regla</button>
                </form>
                <form method="post" class="mt-2">
                    <input type="hidden" name="action" value="archive_game_rule">
                    <input type="hidden" name="game_rule_id" data-game-rule-archive-id>
                    <button class="btn btn-outline-danger" type="submit"><i class="bi bi-archive"></i> Desactivar regla seleccionada</button>
                </form>
            </section>
            <section class="panel game-rule-list-panel">
                <div class="panel-head"><div><h2>Reglas guardadas</h2><p>Consulta las reglas competitivas configuradas.</p></div></div>
                <div class="game-rule-cards">
                    <?php foreach ($gameRules as $rule): ?>
                        <article class="<?php echo !empty($rule['active']) ? 'active' : 'inactive'; ?>">
                            <div><strong><?php echo e((string) $rule['name']); ?></strong><small><?php echo e((string) $rule['code']); ?></small></div>
                            <span><?php echo e(strtoupper((string) $rule['rule_type'])); ?> | <?php echo e((string) $rule['scope']); ?></span>
                            <p><?php echo e((string) (($rule['effect'] ?? '') ?: 'Sin efecto configurado.')); ?></p>
                            <footer><em><?php echo !empty($rule['active']) ? 'Activa' : 'Inactiva'; ?></em><b><?php echo (int) ($rule['points_value'] ?? 0); ?> XP</b></footer>
                        </article>
                    <?php endforeach; ?>
                    <?php if (!$gameRules): ?><p class="note">No hay reglas registradas en BD.</p><?php endif; ?>
                </div>
            </section>
        </div>
    </section>

    <section class="screen rule-detail-screen" data-screen="regla_ficha" hidden>
        <div class="page-title with-actions module-title">
            <div><h1 data-rule-detail-heading>Nueva regla</h1><p>Ficha editable de la regla y sus condiciones persistidas en base de datos.</p></div>
            <button class="btn btn-outline-light" type="button" data-route="reglamento"><i class="bi bi-arrow-left"></i> Volver al listado</button>
        </div>
        <form method="post" class="panel rule-detail-form" data-game-rule-form>
            <input type="hidden" name="action" value="save_game_rule">
            <input type="hidden" name="game_rule_id" data-game-rule-id>
            <div class="rule-detail-grid">
                <section class="rule-info-section">
                    <div class="panel-head compact-head">
                        <div><h2>Datos de la regla</h2><p>Define donde aplica, cuando se dispara y que efecto tiene antes de configurar sus condiciones.</p></div>
                    </div>
                    <div class="rule-info-grid">
                        <label>Codigo<input class="form-control" name="game_rule_code" data-game-rule-code placeholder="CUSTOM_XP" required></label>
                        <label>Nombre<input class="form-control" name="game_rule_name" data-game-rule-name required placeholder="CUSTOM XP"></label>
                        <label>Tipo<select class="form-select" name="game_rule_type" data-game-rule-type><option value="xp">XP</option><option value="bonus">Bonus</option><option value="penalty">Sancion</option><option value="tie_break">Desempate</option><option value="custom">Custom</option></select></label>
                        <label>Aplica a<select class="form-select" name="game_rule_scope" data-game-rule-scope><option value="event">Evento</option><option value="all_matches">Eventos y amistosos</option><option value="friendly">Duelos amistosos</option><option value="match">Duelo manual</option><option value="deck">Deck</option><option value="team">Equipo</option><option value="season">Temporada</option><option value="general">General</option></select></label>
                        <label>Prioridad<input class="form-control" type="number" min="1" name="game_rule_priority" data-game-rule-priority value="100"></label>
                        <label>Valor XP / puntos<input class="form-control" type="number" name="game_rule_points" data-game-rule-points value="0"></label>
                        <label class="span-2">Cuando ocurre<input class="form-control" name="game_rule_trigger" data-game-rule-trigger placeholder="Resultado de duelo validado"></label>
                        <label class="rule-active-field toggle-line"><input type="checkbox" name="game_rule_active" value="1" data-game-rule-active checked> Regla activa</label>
                        <label class="span-2">Efecto<textarea class="form-control" name="game_rule_effect" data-game-rule-effect rows="3" placeholder="Describe que hace la regla y como se calcula"></textarea></label>
                        <label>Notas<textarea class="form-control" name="game_rule_notes" data-game-rule-notes rows="3" placeholder="Restricciones, excepciones o referencias internas"></textarea></label>
                    </div>
                </section>
                <section class="rule-conditions-section">
                    <div class="panel-head compact-head">
                        <div><h2>Condiciones</h2><p>Define las condiciones que se aplican al validar los resultados.</p></div>
                        <button class="btn btn-outline-info btn-sm" type="button" data-rule-condition-add><i class="bi bi-plus-circle"></i> Agregar condicion</button>
                    </div>
                    <div class="table-responsive rule-condition-table-wrap">
                        <table class="table app-table compact rule-condition-table">
                            <thead>
                                <tr>
                                    <th>Condicion</th>
                                    <th>Operador</th>
                                    <th>Secuencia / valor</th>
                                    <th>Resultado</th>
                                    <th>Estado</th>
                                    <th>Motivo</th>
                                    <th>Acciones</th>
                                </tr>
                            </thead>
                            <tbody data-rule-condition-list></tbody>
                        </table>
                    </div>
                    <template data-rule-condition-template>
                        <tr class="rule-condition-row">
                            <td data-label="Condicion">
                                <strong data-condition-label-text>-</strong>
                                <small data-condition-type-text>-</small>
                            </td>
                            <td data-label="Operador" data-condition-operator-text>-</td>
                            <td data-label="Secuencia / valor"><code data-condition-target-text>-</code></td>
                            <td data-label="Resultado" data-condition-result-text>-</td>
                            <td data-label="Estado"><span class="status-chip" data-condition-status-text>-</span></td>
                            <td data-label="Motivo"><small data-condition-message-text>-</small></td>
                            <td data-label="Acciones">
                                <div class="table-actions">
                                    <button class="btn btn-outline-info btn-sm" type="button" data-condition-edit><i class="bi bi-pencil-square"></i></button>
                                    <button class="btn btn-outline-danger btn-sm" type="button" data-condition-remove><i class="bi bi-trash"></i></button>
                                </div>
                                <input type="hidden" name="condition_id[]" data-condition-id>
                                <input type="hidden" name="condition_type[]" data-condition-type>
                                <input type="hidden" name="condition_label[]" data-condition-label>
                                <input type="hidden" name="condition_operator[]" data-condition-operator>
                                <input type="hidden" name="condition_target[]" data-condition-target>
                                <input type="hidden" name="condition_result[]" data-condition-result>
                                <input type="hidden" name="condition_status[]" data-condition-status>
                                <input type="hidden" name="condition_score[]" data-condition-score>
                                <input type="hidden" name="condition_failure_message[]" data-condition-message>
                                <input type="hidden" name="condition_active[]" value="1" data-condition-active>
                            </td>
                        </tr>
                    </template>
                </section>
            </div>
            <div class="modal fade rule-condition-modal" tabindex="-1" data-rule-condition-modal aria-hidden="true">
                <div class="modal-dialog modal-lg modal-dialog-centered">
                    <div class="modal-content">
                        <div class="modal-header">
                            <div><h2 class="modal-title" data-condition-modal-title>Condicion de regla</h2><p>Edita una condicion sin perder de vista la tabla completa.</p></div>
                            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                        </div>
                        <div class="modal-body">
                            <input type="hidden" data-condition-modal-row>
                            <div class="two-col">
                                <label>Tipo<select class="form-select" data-condition-modal-type><option value="result_case">Caso D1-D5</option><option value="victory">Victoria</option><option value="defeat">Derrota</option><option value="draw">Empate</option><option value="validation">Validacion</option><option value="score">Puntaje / XP</option><option value="availability">Disponibilidad</option><option value="custom">Custom</option></select></label>
                                <label>Condicion<input class="form-control" data-condition-modal-label placeholder="Ej: cierre D2 por victorias consecutivas"></label>
                            </div>
                            <div class="two-col">
                                <label>Operador<select class="form-select" data-condition-modal-operator><option value="sequence_equals">Secuencia exacta</option><option value="defined">Debe estar definido</option><option value="winner_in">Ganador en lista</option><option value="winner_equals">Ganador igual a</option><option value="score_min">Marcador minimo</option><option value="score_max">Marcador maximo</option><option value="points_value">Valor de puntos</option><option value="requires_previous">Requiere resultado previo</option><option value="blocks_when">Bloquea cuando</option><option value="custom">Custom</option></select></label>
                                <label>Secuencia / valor<input class="form-control" data-condition-modal-target placeholder="A,A / A,B,E,E / texto"></label>
                            </div>
                            <div class="three-col">
                                <label>Resultado<select class="form-select" data-condition-modal-result><option value="">Sin ganador</option><option value="A">Gana A</option><option value="B">Gana B</option><option value="E">Empate</option></select></label>
                                <label>Estado<select class="form-select" data-condition-modal-status><option value="FINALIZADA">Finalizada</option><option value="REQUIERE D5">Requiere D5</option><option value="EN CURSO">En curso</option><option value="PENDIENTE">Pendiente</option><option value="INVALID">Invalida</option></select></label>
                                <label>Marcador<input class="form-control" data-condition-modal-score placeholder="2-0 / 1-1 / -"></label>
                            </div>
                            <label>Mensaje de validacion<input class="form-control" data-condition-modal-message placeholder="Mensaje cuando no cumple"></label>
                            <label class="toggle-line mt-2"><input type="checkbox" data-condition-modal-active checked> Condicion activa</label>
                        </div>
                        <div class="modal-footer">
                            <button class="btn btn-outline-light" type="button" data-bs-dismiss="modal">Cancelar</button>
                            <button class="btn btn-primary fw-bold" type="button" data-condition-modal-save><i class="bi bi-save"></i> Aplicar condicion</button>
                        </div>
                    </div>
                </div>
            </div>
            <div class="rule-detail-actions">
                <button class="btn btn-primary fw-bold" type="submit"><i class="bi bi-save"></i> Guardar regla</button>
                <button class="btn btn-outline-light" type="button" data-route="reglamento">Cancelar</button>
            </div>
        </form>
        <form method="post" class="rule-archive-form" data-game-rule-archive-form>
            <input type="hidden" name="action" value="archive_game_rule">
            <input type="hidden" name="game_rule_id" data-game-rule-archive-id>
            <button class="btn btn-outline-danger" type="submit"><i class="bi bi-archive"></i> Desactivar regla</button>
        </form>
    </section>

    <section class="screen" data-screen="reglamento" hidden>
        <div class="page-title with-actions module-title">
            <div><h1><?php echo e($uiText('route.reglamento', 'title', 'Reglas custom')); ?></h1><p><?php echo e($uiText('route.reglamento', 'subtitle', 'CRUD de reglas y condiciones que valida la plataforma al registrar resultados.')); ?></p></div>
            <button class="btn btn-primary fw-bold" type="button" data-rule-open=""><i class="bi bi-plus-circle"></i> Nueva regla</button>
        </div>
        <div class="module-kpi-strip rules-kpi-strip mb-3">
            <article><i class="bi bi-sliders"></i><span>Reglas BD</span><b><?php echo number_format(count($gameRules)); ?></b></article>
            <article><i class="bi bi-check-circle"></i><span>Activas</span><b><?php echo number_format($activeGameRuleCount); ?></b></article>
            <article><i class="bi bi-ui-checks-grid"></i><span>Condiciones</span><b><?php echo number_format(array_sum(array_map(static fn(array $rule): int => (int) ($rule['condition_count'] ?? 0), $gameRules))); ?></b></article>
            <article><i class="bi bi-diagram-3"></i><span>Eventos con regla</span><b><?php echo number_format(count(array_filter($competitions, static fn(array $competition): bool => !empty($competition['game_rule_id'])))); ?></b></article>
        </div>
        <section class="panel rules-crud-panel mb-3">
            <div class="panel-head">
                <div><h2>Reglas custom</h2><p>Primero consulta y filtra. Para crear, editar o quitar condiciones abre la ficha de la regla.</p></div>
                <span class="status-chip" data-rule-visible-count><?php echo count($gameRules); ?> reglas</span>
            </div>
            <div class="event-filter-row rule-filter-row">
                <label>Buscar<input class="form-control" type="search" data-rule-search placeholder="Nombre, codigo, tipo, efecto o condicion"></label>
                <label>Tipo<select class="form-select" data-rule-type-filter><option value="">Todos</option><option value="xp">XP</option><option value="bonus">Bonus</option><option value="penalty">Sancion</option><option value="tie_break">Desempate</option><option value="custom">Custom</option></select></label>
                <label>Estado<select class="form-select" data-rule-status-filter><option value="">Todos</option><option value="active">Activas</option><option value="inactive">Inactivas</option></select></label>
                <button class="btn btn-outline-light" type="button" data-rule-filter-clear><i class="bi bi-x-circle"></i> Limpiar</button>
            </div>
            <div class="table-responsive">
                <table class="table app-table compact rule-crud-table">
                    <thead><tr><th>Regla</th><th>Tipo</th><th>Aplica a</th><th>Condiciones</th><th>Puntos</th><th>Estado</th><th>Acciones</th></tr></thead>
                    <tbody>
                    <?php foreach ($gameRules as $rule): ?>
                        <?php
                            $ruleConditionsText = strtolower(implode(' ', array_map(static fn(array $condition): string => (string) (($condition['condition_label'] ?? '') . ' ' . ($condition['condition_type'] ?? '') . ' ' . ($condition['operator'] ?? '') . ' ' . ($condition['target_value'] ?? '')), (array) ($rule['conditions'] ?? []))));
                            $ruleSearch = strtolower((string) (($rule['name'] ?? '') . ' ' . ($rule['code'] ?? '') . ' ' . ($rule['rule_type'] ?? '') . ' ' . ($rule['scope'] ?? '') . ' ' . ($rule['effect'] ?? '') . ' ' . $ruleConditionsText));
                            $ruleStatus = !empty($rule['active']) ? 'active' : 'inactive';
                        ?>
                        <tr data-rule-row data-rule-id="<?php echo e((string) ($rule['id'] ?? '')); ?>" data-rule-type="<?php echo e((string) ($rule['rule_type'] ?? 'custom')); ?>" data-rule-status="<?php echo e($ruleStatus); ?>" data-rule-search="<?php echo e($ruleSearch); ?>">
                            <td data-label="Regla"><strong><?php echo e((string) ($rule['name'] ?? 'Regla')); ?></strong><br><small><?php echo e((string) ($rule['code'] ?? '')); ?></small></td>
                            <td data-label="Tipo"><?php echo e(strtoupper((string) ($rule['rule_type'] ?? 'custom'))); ?></td>
                            <td data-label="Aplica a"><?php echo e((string) (($rule['scope'] ?? '') ?: 'general')); ?></td>
                            <td data-label="Condiciones"><?php echo (int) ($rule['condition_count'] ?? 0); ?></td>
                            <td data-label="Puntos"><?php echo (int) ($rule['points_value'] ?? 0); ?></td>
                            <td data-label="Estado"><span class="status-chip <?php echo !empty($rule['active']) ? 'state-active' : 'state-cancelled'; ?>"><?php echo !empty($rule['active']) ? 'Activa' : 'Inactiva'; ?></span></td>
                            <td data-label="Acciones"><button class="btn btn-outline-info btn-sm" type="button" data-rule-open="<?php echo e((string) ($rule['id'] ?? '')); ?>"><i class="bi bi-pencil-square"></i> Abrir ficha</button></td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (!$gameRules): ?><tr><td colspan="7"><div class="empty-state">No hay reglas registradas en base de datos.</div></td></tr><?php endif; ?>
                    </tbody>
                </table>
            </div>
        </section>
    </section>

    <section class="screen" data-screen="gestion" hidden>
        <div class="page-title with-actions module-title">
            <div><h1><?php echo e($uiText('route.gestion', 'title', 'Configuracion')); ?></h1><p><?php echo e($uiText('route.gestion', 'subtitle', 'Administracion central de eventos, formatos, reglas, calendario, resultados, decks y equipos.')); ?></p></div>
            <button class="btn btn-primary fw-bold" type="button" data-route="crear_evento"><i class="bi bi-plus-circle"></i> Crear evento</button>
        </div>
        <?php if (!$dbStatus['connected']): ?><div class="alert alert-warning">La edicion requiere conexion a MySQL.</div><?php endif; ?>
        <div class="module-kpi-strip gestion-overview-strip mb-3">
            <article><i class="bi bi-diagram-3"></i><span>Eventos</span><b><?php echo number_format(count($competitions)); ?></b></article>
            <article><i class="bi bi-trophy"></i><span>Formatos</span><b><?php echo number_format(count($displayDivisions)); ?></b></article>
            <article><i class="bi bi-collection"></i><span>Decks</span><b><?php echo number_format(count($allDecks)); ?></b></article>
            <article><i class="bi bi-shield"></i><span>Equipos</span><b><?php echo number_format(count($teams)); ?></b></article>
            <article><i class="bi bi-journal-check"></i><span>Reglamentos</span><b><?php echo number_format(count($rulesets)); ?></b></article>
            <article><i class="bi bi-sliders"></i><span>Reglas</span><b><?php echo number_format(count($gameRules)); ?></b></article>
        </div>
        <div class="gestion-mode-badge mb-3"><i class="bi bi-sliders"></i><span>Edicion habilitada para toda la interfaz</span></div>
        <section class="gestion-command-panel panel mb-3">
            <div class="panel-head">
                <div><h2>Centro de mando</h2><p>Gestiona el flujo completo: clasificatorio vivo, divisiones oficiales, calendario y resultados.</p></div>
                <span class="status-chip"><?php echo e($qualifierStatusLabel); ?></span>
            </div>
            <div class="gestion-command-grid">
                <article><i class="bi bi-diagram-3"></i><small>Clasificatorio</small><strong><?php echo e($qualifierStatusLabel); ?></strong><button class="btn btn-outline-light btn-sm" type="button" data-route="clasificatorio">Abrir</button></article>
                <article><i class="bi bi-collection"></i><small>Decks / equipos</small><strong><?php echo count($allDecks); ?> / <?php echo count($teams); ?></strong><button class="btn btn-outline-light btn-sm" type="button" data-route="deck">Fichas</button></article>
                <article><i class="bi bi-calendar2-week"></i><small>Calendario</small><strong><?php echo e((string) ($dbStatus['counts']['matches'] ?? 0)); ?> combates</strong><button class="btn btn-outline-light btn-sm" type="button" data-route="calendario">Programar</button></article>
            </div>
        </section>
        <nav class="gestion-tabs mb-3" aria-label="Modulos de gestion">
            <button class="active" type="button" data-gestion-tab="resumen"><i class="bi bi-grid"></i> Resumen</button>
            <button type="button" data-gestion-tab="competiciones"><i class="bi bi-diagram-3"></i> Eventos</button>
            <button type="button" data-gestion-tab="calendario"><i class="bi bi-calendar2-week"></i> Calendario</button>
            <button type="button" data-gestion-tab="resultados"><i class="bi bi-plus-circle"></i> Resultados</button>
            <button type="button" data-gestion-tab="textos"><i class="bi bi-type"></i> Textos / opciones</button>
            <button type="button" data-gestion-tab="cartas"><i class="bi bi-image"></i> Cartas/media</button>
            <button type="button" data-gestion-tab="auditoria"><i class="bi bi-clock-history"></i> Auditoria</button>
        </nav>
        <div class="gestion-grid">
            <section class="panel gestion-module" data-gestion-module="textos">
                <div class="panel-head">
                    <div>
                        <h2><?php echo e($uiText('gestion.labels_title', 'title', 'Titulos y opciones dinamicas')); ?></h2>
                        <p><?php echo e($uiText('gestion.labels_title', 'subtitle', 'Edita textos visibles y catalogos de opciones persistidos en base de datos.')); ?></p>
                    </div>
                    <span class="status-chip"><?php echo count($uiLabels); ?> textos</span>
                </div>
                <form method="post" class="gestion-form" enctype="multipart/form-data">
                    <input type="hidden" name="action" value="save_ui_labels">
                    <div class="table-responsive">
                        <table class="table table-dark table-sm align-middle admin-table">
                            <thead><tr><th>Clave</th><th>Titulo visible</th><th>Descripcion</th><th>Activo</th></tr></thead>
                            <tbody>
                                <?php foreach ($uiLabels as $label): ?>
                                    <tr>
                                        <td><code><?php echo e((string) ($label['key'] ?? '')); ?></code><input type="hidden" name="ui_label_key[]" value="<?php echo e((string) ($label['key'] ?? '')); ?>"></td>
                                        <td><input class="form-control form-control-sm" name="ui_label_title[]" value="<?php echo e((string) ($label['title'] ?? '')); ?>" required></td>
                                        <td><input class="form-control form-control-sm" name="ui_label_subtitle[]" value="<?php echo e((string) ($label['subtitle'] ?? '')); ?>"></td>
                                        <td class="text-center"><input type="checkbox" name="ui_label_active[]" value="<?php echo e((string) ($label['key'] ?? '')); ?>" <?php echo !empty($label['active']) ? 'checked' : ''; ?>></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <button class="btn btn-primary fw-bold" type="submit"><i class="bi bi-save"></i> Guardar titulos</button>
                </form>
                <form method="post" class="gestion-form mt-3">
                    <input type="hidden" name="action" value="save_catalog_items">
                    <div class="panel-head compact-head">
                        <div><h3>Catalogos de opciones</h3><p>Estas opciones alimentan selectores de eventos, estados, reglas y patrones.</p></div>
                        <span class="status-chip"><?php echo array_sum(array_map('count', $catalogGroups)); ?> opciones</span>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-dark table-sm align-middle admin-table">
                            <thead><tr><th>Catalogo</th><th>Codigo</th><th>Etiqueta</th><th>Icono</th><th>Color</th><th>Orden</th><th>Activo</th></tr></thead>
                            <tbody>
                                <?php foreach ($catalogGroups as $catalogKey => $catalogItems): ?>
                                    <?php foreach ($catalogItems as $catalogItem): ?>
                                        <tr>
                                            <td><code><?php echo e((string) $catalogKey); ?></code><input type="hidden" name="catalog_id[]" value="<?php echo e((string) ($catalogItem['id'] ?? 0)); ?>"></td>
                                            <td><code><?php echo e((string) ($catalogItem['code'] ?? '')); ?></code></td>
                                            <td><input class="form-control form-control-sm" name="catalog_label[]" value="<?php echo e((string) ($catalogItem['label'] ?? '')); ?>" required></td>
                                            <td><input class="form-control form-control-sm" name="catalog_icon[]" value="<?php echo e((string) ($catalogItem['icon'] ?? '')); ?>" placeholder="bi-trophy"></td>
                                            <td><input class="form-control form-control-sm" name="catalog_accent[]" value="<?php echo e((string) ($catalogItem['accent'] ?? '')); ?>" placeholder="#36d8ff"></td>
                                            <td><input class="form-control form-control-sm" type="number" min="0" name="catalog_sort_order[]" value="<?php echo e((string) ($catalogItem['sort_order'] ?? 100)); ?>"></td>
                                            <td class="text-center"><input type="checkbox" name="catalog_active[]" value="<?php echo e((string) ($catalogItem['id'] ?? 0)); ?>" <?php echo !empty($catalogItem['active']) ? 'checked' : ''; ?>></td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <div class="panel-head compact-head"><div><h3>Nueva opcion</h3><p>Agrega una opcion a cualquier catalogo existente o nuevo.</p></div></div>
                    <div class="three-col">
                        <label>Catalogo<input class="form-control" name="new_catalog_key" placeholder="competition_type"></label>
                        <label>Codigo<input class="form-control" name="new_catalog_code" placeholder="friendly"></label>
                        <label>Etiqueta<input class="form-control" name="new_catalog_label" placeholder="Amistoso"></label>
                    </div>
                    <div class="three-col">
                        <label>Icono<input class="form-control" name="new_catalog_icon" placeholder="bi-controller"></label>
                        <label>Color<input class="form-control" name="new_catalog_accent" placeholder="#36d8ff"></label>
                        <label>Orden<input class="form-control" type="number" min="0" name="new_catalog_sort_order" value="100"></label>
                    </div>
                    <button class="btn btn-warning fw-bold" type="submit"><i class="bi bi-save"></i> Guardar opciones</button>
                </form>
            </section>

            <section class="panel gestion-module" data-gestion-module="equipos">
                <div class="panel-head"><div><h2>Equipos</h2><p>Un equipo puede representar uno o varios decks.</p></div><span class="status-chip"><?php echo count($teams); ?> activos</span></div>
                <div class="team-deck-save-row">
                    <span>La creacion, edicion y asociacion de decks se gestiona desde la ficha modal.</span>
                    <button class="btn btn-primary fw-bold" type="button" data-open-team-sheet=""><i class="bi bi-shield-plus"></i> Abrir ficha de equipo</button>
                </div>
            </section>

            <section class="panel gestion-module" data-gestion-module="decks">
                <div class="panel-head"><div><h2>Decks</h2><p>Ficha editable: equipo, formato, cartas, variaciones, estado y notas.</p></div><span class="status-chip"><?php echo count($allDecks); ?> activos</span></div>
                <form method="post" class="gestion-form">
                    <input type="hidden" name="action" value="save_deck">
                    <input type="hidden" name="deck_id" data-gestion-deck-id>
                    <label>Editar deck existente<select class="form-select" data-gestion-deck-select><option value="">Nuevo deck</option><?php foreach ($allDecks as $deck): ?><option value="<?php echo e((string) $deck['id']); ?>"><?php echo e($deck['name']); ?></option><?php endforeach; ?></select></label>
                    <label>Nombre del deck<input class="form-control" name="deck_name" data-gestion-deck-name required></label>
                    <div class="two-col">
                        <label>Equipo asociado<input class="form-control" data-gestion-deck-team-label value="Sin equipo" readonly></label>
                        <label>Division<select class="form-select" name="division_id" data-gestion-deck-division><option value="">Sin division</option><?php foreach ($displayDivisions as $division): ?><option value="<?php echo e((string) ($division['id'] ?? '')); ?>"><?php echo e($division['short']); ?></option><?php endforeach; ?></select></label>
                    </div>
                    <div class="two-col">
                        <label>Semilla interna<input class="form-control" type="number" min="1" name="seed_rank" data-gestion-deck-rank value="999"><small>Sólo se usa para programación; no representa el ranking global.</small></label>
                        <label>Estilo<input class="form-control" name="style" data-gestion-deck-style placeholder="Combo, control, midrange"></label>
                    </div>
                    <label>Arquetipo<input class="form-control" name="archetype" data-gestion-deck-archetype></label>
                    <label>Motivo / resumen<input class="form-control" name="reason" data-gestion-deck-reason></label>
                    <label>Cartas clave<textarea class="form-control" name="key_cards" data-gestion-deck-cards rows="2"></textarea></label>
                    <label>Variaciones<textarea class="form-control" name="variations" data-gestion-deck-variations rows="2"></textarea></label>
                    <label>Notas / ficha<textarea class="form-control" name="notes" data-gestion-deck-notes rows="3"></textarea></label>
                    <input type="hidden" name="cover_url" data-gestion-deck-cover>
                    <label>Subir logo del deck<input class="form-control" type="file" name="deck_logo" accept="image/png,image/jpeg,image/webp,image/gif"><small>PNG, JPG, WEBP o GIF. Si subes uno nuevo, reemplaza la imagen actual del deck.</small></label>
                    <div class="d-flex gap-2 flex-wrap">
                        <button class="btn btn-primary fw-bold" type="submit"><i class="bi bi-save"></i> Guardar deck</button>
                    </div>
                </form>
                <form method="post" class="mt-2">
                    <input type="hidden" name="action" value="remove_deck">
                    <input type="hidden" name="deck_id" data-gestion-remove-deck-id>
                    <button class="btn btn-outline-danger" type="submit"><i class="bi bi-trash"></i> Quitar deck seleccionado</button>
                </form>
            </section>

            <section class="panel gestion-module" data-gestion-module="competiciones">
                <div class="panel-head"><div><h2>Eventos competitivos</h2><p>Administra clasificatorios, copas, torneos, ligas y eventos por reglamento con prerequisitos y reglas propias.</p></div><span class="status-chip"><?php echo count($competitions); ?> activos</span></div>
                <div class="event-admin-redirect">
                    <i class="bi bi-plus-circle"></i>
                    <div><strong>La creacion de eventos ahora esta en una pantalla unica.</strong><span>Desde aqui administra prerequisitos y archivo de eventos existentes.</span></div>
                    <button class="btn btn-primary btn-sm" type="button" data-route="crear_evento">Crear / editar evento</button>
                </div>
                <form method="post" class="gestion-form" hidden>
                    <input type="hidden" name="action" value="save_competition">
                    <?php $seasonFieldMode = "edit"; require __DIR__ . "/includes/season-event-field.php"; ?>
                    <input type="hidden" name="competition_id" data-gestion-competition-id>
                    <label>Editar evento<select class="form-select" data-gestion-competition-select><option value="">Nuevo evento</option><?php foreach ($competitions as $competition): ?><option value="<?php echo e((string) $competition['id']); ?>"><?php echo e((string) $competition['name']); ?></option><?php endforeach; ?></select></label>
                    <div class="two-col">
                        <label>Codigo<input class="form-control" name="competition_code" data-gestion-competition-code required></label>
                        <label>Nombre<input class="form-control" name="competition_name" data-gestion-competition-name required></label>
                    </div>
                    <div class="two-col">
                        <label>Tipo de evento<select class="form-select" name="competition_type" data-gestion-competition-type><?php foreach ($competitionTypeOptions as $eventType => $eventTypeMeta): ?><option value="<?php echo e($eventType); ?>"><?php echo e((string) $eventTypeMeta['label']); ?></option><?php endforeach; ?></select></label>
                        <label>Alcance<select class="form-select" name="competition_scope" data-gestion-competition-scope><option value="division">Asociada a formato</option><option value="global">Global / sin formato</option><option value="cross_division">Cruza formatos</option><option value="team_event">Por equipos</option></select></label>
                    </div>
                    <div class="two-col">
                        <label>Formato asociado / division principal<select class="form-select" name="competition_division_id" data-gestion-competition-division><option value="">General</option><optgroup label="Formatos disponibles"><?php foreach ($formatSelectOptions as $rulesetCode => $rulesetLabel): ?><option value="format:<?php echo e((string) $rulesetCode); ?>" data-ruleset="<?php echo e((string) $rulesetCode); ?>"><?php echo e((string) $rulesetLabel); ?></option><?php endforeach; ?></optgroup><?php if ($displayDivisions): ?><optgroup label="Divisiones"><?php foreach ($displayDivisions as $division): ?><option value="<?php echo e((string) ($division['id'] ?? '')); ?>" data-ruleset="<?php echo e((string) ($division['ruleset'] ?? $division['event_ruleset'] ?? '')); ?>"><?php echo e($division['short']); ?></option><?php endforeach; ?></optgroup><?php endif; ?></select></label>
                        <label data-gestion-league-divisions hidden>Divisiones asociadas a la liga<select class="form-select" name="competition_division_ids[]" data-gestion-league-divisions-select multiple size="4"><?php foreach ($displayDivisions as $division): ?><option value="<?php echo e((string) ($division['id'] ?? '')); ?>"><?php echo e((string) (($division['name'] ?? '') ?: ($division['short'] ?? 'Division'))); ?></option><?php endforeach; ?></select><small>Selecciona todas las divisiones que pertenecen a esta liga.</small></label>
                        <label>Sistema de torneo<input class="form-control" name="competition_format" data-gestion-competition-format placeholder="liga, suizo, eliminacion directa, grupos"></label>
                    </div>
                    <div class="two-col">
                        <label>Categoria base<select class="form-select" name="competition_event_ruleset" data-gestion-competition-ruleset><?php foreach ($eventRulesetOptions as $rulesetCode => $rulesetLabel): ?><option value="<?php echo e($rulesetCode); ?>"><?php echo e($rulesetLabel); ?></option><?php endforeach; ?></select></label>
                        <label>Modo de participantes<select class="form-select" name="competition_deck_pool" data-gestion-competition-deck-pool><option value="all_active">Libre: todos los decks/equipos</option><option value="division">Division completa</option></select></label>
                    </div>
                    <div class="two-col">
                        <label>Formato de match<select class="form-select" name="competition_match_format" data-gestion-competition-match-format><?php foreach ($matchTypeOptions as $matchType): ?><option value="<?php echo e((string) $matchType['code']); ?>" data-participants="<?php echo e((string) ($matchType['participants_per_side'] ?? 1)); ?>"><?php echo e((string) ($matchType['label'] ?? $matchType['code'])); ?></option><?php endforeach; ?></select></label>
                        <label>Mejor de<input class="form-control" type="number" min="1" max="5" name="competition_best_of" data-gestion-competition-best-of value="5"></label>
                    </div>
                    <div class="two-col">
                        <label>Participantes por lado<input class="form-control" type="number" min="1" max="3" name="competition_participants_per_side" data-gestion-competition-participants value="1"></label>
                        <label>Programacion<select class="form-select" name="competition_schedule_strategy" data-gestion-competition-schedule><option value="manual">Manual</option><option value="auto_round_robin">Liga completa automatica</option><option value="auto_knockout">Llave automatica</option><option value="auto_swiss">Rondas suizas</option><option value="friendly_rotation">Amistosos rotativos</option></select></label>
                    </div>
                    <div class="two-col">
                        <label>Estado<select class="form-select" name="competition_status" data-gestion-competition-status><?php foreach ($competitionStatusOptions as $statusCode => $statusLabel): ?><?php if ($statusCode === 'resume') { continue; } ?><option value="<?php echo e($statusCode); ?>"><?php echo e($statusLabel); ?></option><?php endforeach; ?></select></label>
                        <label>Color / acento<input class="form-control" name="competition_accent" data-gestion-competition-accent placeholder="#36d8ff"></label>
                    </div>
                    <div class="two-col">
                        <label>Inicio<input class="form-control" type="date" name="starts_on" data-gestion-competition-start></label>
                        <label>Fin<input class="form-control" type="date" name="ends_on" data-gestion-competition-end></label>
                    </div>
                    <div class="three-col">
                        <label>Condicion de victoria<input class="form-control" name="competition_victory_condition" data-gestion-competition-victory placeholder="Dos victorias consecutivas o ventaja al cierre"></label>
                        <label>Condicion de derrota<input class="form-control" name="competition_defeat_condition" data-gestion-competition-defeat placeholder="Menor marcador al cierre"></label>
                        <label>Condicion de empate<input class="form-control" name="competition_draw_condition" data-gestion-competition-draw placeholder="D5 empatado o dos empates consecutivos"></label>
                    </div>
                    <label>Reglas<textarea class="form-control" name="competition_rules" data-gestion-competition-rules rows="3"></textarea></label>
                    <button class="btn btn-primary fw-bold" type="submit"><i class="bi bi-save"></i> Guardar evento</button>
                </form>
                <form method="post" class="mt-2">
                    <input type="hidden" name="action" value="archive_competition">
                    <input type="hidden" name="competition_id" data-gestion-archive-competition-id>
                    <button class="btn btn-outline-danger" type="submit"><i class="bi bi-archive"></i> Archivar evento seleccionado</button>
                </form>
                <div class="dependency-board mt-3">
                    <div class="panel-head compact-head"><div><h3>Prerequisitos de ejecucion</h3><p>Define que torneo, copa o liga debe terminar antes de habilitar otra competencia.</p></div></div>
                    <form method="post" class="gestion-form dependency-form">
                        <input type="hidden" name="action" value="save_competition_dependency">
                        <label>Evento a bloquear<select class="form-select" name="dependency_competition_id" required><?php foreach ($competitions as $competition): ?><option value="<?php echo e((string) $competition['id']); ?>"><?php echo e((string) $competition['name']); ?></option><?php endforeach; ?></select></label>
                        <label>Debe cumplirse primero<select class="form-select" name="dependency_depends_on_id" required><?php foreach ($competitions as $competition): ?><option value="<?php echo e((string) $competition['id']); ?>"><?php echo e((string) $competition['name']); ?></option><?php endforeach; ?></select></label>
                        <label>Condicion<select class="form-select" name="dependency_condition"><option value="completed_with_results">Finalizada con resultados</option><option value="completed">Solo finalizada</option><option value="has_results">Con algun resultado</option><option value="top_n">Top N entra al evento</option><option value="winner_to_final">Ganador entra a final</option><option value="defending_champion">Campeon defensor habilita evento</option><option value="creates_divisions">Clasificatorio crea divisiones</option></select></label>
                        <label>Cantidad Top N<input class="form-control" type="number" min="0" max="128" name="dependency_take_count" value="4"><small>Solo aplica cuando la condicion sea Top N.</small></label>
                        <button class="btn btn-warning fw-bold" type="submit"><i class="bi bi-link-45deg"></i> Guardar regla</button>
                    </form>
                    <div class="dependency-list">
                        <?php foreach ($competitions as $competition): ?>
                            <?php foreach (($competition['dependencies'] ?? []) as $dependency): ?>
                                <article class="<?php echo !empty($dependency['ok']) ? 'ready' : 'blocked'; ?>">
                                    <div>
                                        <b><?php echo e((string) $competition['name']); ?></b>
                                        <span>depende de <?php echo e((string) $dependency['name']); ?> | <?php echo e((string) $dependency['condition_type']); ?><?php echo !empty($dependency['take_count']) ? ' | Top ' . e((string) $dependency['take_count']) : ''; ?></span>
                                    </div>
                                    <em><?php echo !empty($dependency['ok']) ? 'OK' : 'Pendiente'; ?></em>
                                    <form method="post">
                                        <input type="hidden" name="action" value="delete_competition_dependency">
                                        <input type="hidden" name="dependency_id" value="<?php echo e((string) $dependency['id']); ?>">
                                        <button class="btn btn-outline-danger btn-sm" type="submit"><i class="bi bi-x-circle"></i></button>
                                    </form>
                                </article>
                            <?php endforeach; ?>
                        <?php endforeach; ?>
                        <?php if (!$competitionDependencies): ?><p class="note">Aun no hay prerequisitos. Ejemplo: Torneo XY puede depender de que el clasificatorio termine con resultados.</p><?php endif; ?>
                    </div>
                </div>
            </section>

            <section class="panel gestion-module" data-gestion-module="divisiones">
                <div class="panel-head"><div><h2>Divisiones</h2><p>CRUD de divisiones: nombre, orden, cupos, ascensos/descensos y decks asignados.</p></div><span class="status-chip"><?php echo count($displayDivisions); ?> configuradas</span></div>
                <form method="post" class="gestion-form">
                    <input type="hidden" name="action" value="save_division">
                    <label>Editar division<select class="form-select" name="division_id" data-gestion-division-select><option value="">Nueva division</option><?php foreach ($displayDivisions as $division): ?><option value="<?php echo e((string) $division['id']); ?>"><?php echo e($division['short']); ?> - <?php echo e($division['name']); ?></option><?php endforeach; ?></select></label>
                    <div class="two-col">
                        <label>Codigo<input class="form-control" name="division_code" data-gestion-division-code required></label>
                        <label>Orden<input class="form-control" type="number" min="1" name="division_tier" data-gestion-division-tier required></label>
                    </div>
                    <label>Nombre<input class="form-control" name="division_name" data-gestion-division-name required></label>
                    <label>Nombre competitivo / copa asociada<input class="form-control" name="division_cup" data-gestion-division-cup required></label>
                    <div class="two-col">
                        <label>Liga asociada<select class="form-select" name="division_league_competition_id" data-gestion-division-league><option value="">Modo libre / sin liga</option><?php foreach ($competitions as $competition): ?><?php if ((string) ($competition['type'] ?? '') !== 'league') { continue; } ?><option value="<?php echo e((string) ($competition['id'] ?? '')); ?>"><?php echo e((string) ($competition['name'] ?? 'Liga')); ?></option><?php endforeach; ?></select><small>La liga es el contenedor principal; la division agrupa equipos dentro de esa liga.</small></label>
                        <label>Clasificatorio origen<select class="form-select" name="division_source_qualifier_id" data-gestion-division-source><option value="">Manual / sin clasificatorio</option><?php foreach ($competitions as $competition): ?><?php if ((string) ($competition['type'] ?? '') !== 'qualifier') { continue; } ?><option value="<?php echo e((string) ($competition['id'] ?? '')); ?>"><?php echo e((string) ($competition['name'] ?? 'Clasificatorio')); ?> | <?php echo e(ygo_event_status_label((string) ($competition['status'] ?? ''))); ?></option><?php endforeach; ?></select><small>Usalo cuando la division nace del resultado final de un clasificatorio.</small></label>
                    </div>
                    <div class="two-col">
                        <label>Desde posicion<input class="form-control" type="number" min="1" name="division_rank_from" data-gestion-division-rank-from placeholder="Ej: 1"></label>
                        <label>Hasta posicion<input class="form-control" type="number" min="1" name="division_rank_to" data-gestion-division-rank-to placeholder="Ej: 10"></label>
                    </div>
                    <div class="two-col">
                        <label>Cupos objetivo<input class="form-control" type="number" min="0" name="division_size" data-gestion-division-size></label>
                        <label>Color<input class="form-control" name="division_accent" data-gestion-division-accent placeholder="#36d8ff"></label>
                    </div>
                    <label>Logo de division<input class="form-control" type="file" name="division_logo" accept="image/png,image/jpeg,image/webp,image/gif"><small>Se guarda en el servidor dentro de assets/uploads/divisions/division-ID.</small></label>
                    <div class="two-col">
                        <label>Inicio<input class="form-control" type="date" name="division_starts_on" data-gestion-division-start></label>
                        <label>Fin<input class="form-control" type="date" name="division_ends_on" data-gestion-division-end></label>
                    </div>
                    <div class="two-col">
                        <label>Cupos de ascenso<input class="form-control" type="number" min="0" name="division_promotion_slots" data-gestion-division-promotion-slots></label>
                        <label>Cupos de descenso<input class="form-control" type="number" min="0" name="division_relegation_slots" data-gestion-division-relegation-slots></label>
                    </div>
                    <div class="two-col">
                        <label>Sube hacia<select class="form-select" name="division_promotion_to" data-gestion-division-promotion-to><option value="">Sin ascenso</option><?php foreach ($displayDivisions as $targetDivision): ?><option value="<?php echo e((string) $targetDivision['id']); ?>"><?php echo e($targetDivision['short']); ?></option><?php endforeach; ?></select></label>
                        <label>Baja hacia<select class="form-select" name="division_relegation_to" data-gestion-division-relegation-to><option value="">Sin descenso</option><?php foreach ($displayDivisions as $targetDivision): ?><option value="<?php echo e((string) $targetDivision['id']); ?>"><?php echo e($targetDivision['short']); ?></option><?php endforeach; ?></select></label>
                    </div>
                    <label>Descripcion<textarea class="form-control" name="division_description" data-gestion-division-description rows="3"></textarea></label>
                    <div class="division-deck-admin">
                        <div class="panel-head compact-head"><div><h3>Decks de la division</h3><p>Selecciona minimo 4 si vas a reasignar decks a esta division.</p></div><span class="status-chip" data-division-selected-count>0 seleccionados</span></div>
                        <input class="form-control" type="search" data-division-deck-search placeholder="Buscar deck o equipo">
                        <div class="deck-picker-row">
                            <select class="form-select" data-division-deck-picker>
                                <option value="">Selecciona un deck</option>
                                <?php foreach ($allDecks as $deck): ?>
                                    <option value="<?php echo e((string) ($deck['id'] ?? '')); ?>" data-division-id="<?php echo e((string) ($deck['division_id'] ?? '')); ?>" data-search="<?php echo e(strtolower((string) (($deck['name'] ?? '') . ' ' . ($deck['team'] ?? '') . ' ' . ($deck['division'] ?? '')))); ?>"><?php echo e((string) (($deck['team'] ?? 'Sin equipo') . ' - ' . ($deck['name'] ?? 'Deck') . ' | ' . ($deck['division'] ?? 'Sin division'))); ?></option>
                                <?php endforeach; ?>
                            </select>
                            <button class="btn btn-primary" type="button" data-division-deck-add><i class="bi bi-plus-circle"></i> Agregar</button>
                        </div>
                        <div class="selected-deck-list" data-division-selected-decks></div>
                        <div data-division-hidden-decks hidden></div>
                    </div>
                    <button class="btn btn-primary fw-bold" type="submit"><i class="bi bi-save"></i> Guardar division</button>
                </form>
            </section>

            <section class="panel gestion-module" data-gestion-module="calendario">
                <div class="panel-head"><div><h2>Calendario de combates</h2><p>Programa fecha, hora, sede, juez y notas para cualquier combate.</p></div><span class="status-chip"><?php echo count($matchOptions); ?> combates</span></div>
                <form method="post" class="gestion-form">
                    <input type="hidden" name="action" value="save_match_schedule">
                    <label>Combate<select class="form-select" name="schedule_match_id" data-gestion-match-select><?php foreach ($matchOptions as $option): ?><?php if ($option['id'] > 0): ?><option value="<?php echo e((string) $option['id']); ?>"><?php echo e($option['label']); ?></option><?php endif; ?><?php endforeach; ?></select></label>
                    <div class="two-col">
                        <label>Fecha<input class="form-control" type="date" name="scheduled_on" data-gestion-match-date></label>
                        <label>Hora<input class="form-control" type="time" name="starts_at" data-gestion-match-time></label>
                    </div>
                    <div class="two-col">
                        <label>Sede<input class="form-control" name="venue" data-gestion-match-venue placeholder="Station X Tulua"></label>
                        <label>Juez / encargado<input class="form-control" name="judge" data-gestion-match-judge></label>
                    </div>
                    <label>Notas<textarea class="form-control" name="match_notes" data-gestion-match-notes rows="3"></textarea></label>
                    <button class="btn btn-primary fw-bold" type="submit"><i class="bi bi-calendar-check"></i> Guardar fecha</button>
                </form>
                <form method="post" class="gestion-form mt-3">
                    <input type="hidden" name="action" value="save_settings">
                    <div class="panel-head compact-head"><div><h3>Reglas de ejecucion de duelos</h3><p>Controla si se pueden registrar resultados fuera de la fecha programada. Se guarda en MySQL por temporada.</p></div></div>
                    <label class="toggle-line"><input type="hidden" name="result_date_guard" value="0"><input type="checkbox" name="result_date_guard" value="1" <?php echo ygo_setting_enabled($settings, 'result_date_guard') ? 'checked' : ''; ?>> Validar fecha programada al guardar resultados</label>
                    <label class="toggle-line"><input type="hidden" name="allow_early_results" value="0"><input type="checkbox" name="allow_early_results" value="1" <?php echo ygo_setting_enabled($settings, 'allow_early_results') ? 'checked' : ''; ?>> Permitir adelantar duelos antes de su fecha</label>
                    <label class="toggle-line"><input type="hidden" name="allow_overdue_results" value="0"><input type="checkbox" name="allow_overdue_results" value="1" <?php echo ygo_setting_enabled($settings, 'allow_overdue_results') ? 'checked' : ''; ?>> Permitir registrar duelos vencidos</label>
                    <label>Dias para alerta de proximos duelos<input class="form-control" type="number" min="1" max="30" name="alert_days_ahead" value="<?php echo e((string) (($settings['alert_days_ahead'] ?? '') ?: '3')); ?>"></label>
                    <?php $validWeekdays = array_filter(array_map('trim', explode(',', (string) ($settings['valid_weekdays'] ?? '')))); ?>
                    <div class="weekday-picker">
                        <span>Dias validos de juego</span>
                        <?php foreach ([1 => 'Lun', 2 => 'Mar', 3 => 'Mie', 4 => 'Jue', 5 => 'Vie', 6 => 'Sab', 0 => 'Dom'] as $dayValue => $dayLabel): ?>
                            <label><input type="checkbox" name="valid_weekdays[]" value="<?php echo e((string) $dayValue); ?>" <?php echo in_array((string) $dayValue, $validWeekdays, true) ? 'checked' : ''; ?>> <?php echo e($dayLabel); ?></label>
                        <?php endforeach; ?>
                    </div>
                    <div class="two-col">
                        <label>Hora minima<input class="form-control" type="time" name="allowed_start_time" value="<?php echo e(substr((string) (($settings['allowed_start_time'] ?? '') ?: '10:00'), 0, 5)); ?>"></label>
                        <label>Hora maxima<input class="form-control" type="time" name="allowed_end_time" value="<?php echo e(substr((string) (($settings['allowed_end_time'] ?? '') ?: '22:00'), 0, 5)); ?>"></label>
                    </div>
                    <label>Capacidad global de combates por dia<input class="form-control" type="number" min="1" max="3" name="max_matches_per_day" value="<?php echo e((string) (($settings['max_matches_per_day'] ?? '') ?: '3')); ?>"><small>El clasificatorio cede cupos cuando comparte fecha con otros eventos.</small></label>
                    <label class="toggle-line"><input type="hidden" name="allow_special_days" value="0"><input type="checkbox" name="allow_special_days" value="1" <?php echo ygo_setting_enabled($settings, 'allow_special_days') ? 'checked' : ''; ?>> Permitir dias especiales fuera de los dias validos</label>
                    <label>Dias especiales<textarea class="form-control" name="special_days" rows="2" placeholder="2026-09-08, 2026-09-15"><?php echo e((string) ($settings['special_days'] ?? '')); ?></textarea></label>
                    <label>Sedes disponibles<textarea class="form-control" name="venues" rows="2" placeholder="Station X Tulua"><?php echo e((string) ($settings['venues'] ?? 'Station X Tulua')); ?></textarea></label>
                    <div class="panel-head compact-head mt-3"><div><h3>Formulas competitivas</h3><p>Parametros usados por ranking Elo, metagame, tiers, actualizacion automatica y alertas.</p></div></div>
                    <div class="settings-metric-grid">
                        <label>Muestra minima<input class="form-control" type="number" min="1" max="100" name="stats_minimum_sample" value="<?php echo e((string) ($settings['stats_minimum_sample'] ?? '3')); ?>"></label>
                        <label>Muestra minima por tier<input class="form-control" type="number" min="1" max="100" name="tier_sample_minimum" value="<?php echo e((string) ($settings['tier_sample_minimum'] ?? '3')); ?>"></label>
                        <label>Partidas para mejor debut<input class="form-control" type="number" min="1" max="100" name="best_debut_min_matches" value="<?php echo e((string) ($settings['best_debut_min_matches'] ?? '3')); ?>"></label>
                        <label>Peso de presencia (%)<input class="form-control" type="number" min="0" max="100" name="meta_presence_weight" value="<?php echo e((string) ($settings['meta_presence_weight'] ?? '50')); ?>"></label>
                        <label>Tier Zero: presencia (%)<input class="form-control" type="number" min="0" max="100" name="tier_zero_presence" value="<?php echo e((string) ($settings['tier_zero_presence'] ?? '35')); ?>"></label>
                        <label>Tier Zero: winrate (%)<input class="form-control" type="number" min="0" max="100" name="tier_zero_winrate" value="<?php echo e((string) ($settings['tier_zero_winrate'] ?? '65')); ?>"></label>
                        <label>Tier 1: indice minimo<input class="form-control" type="number" min="0" max="100" name="tier_one_score" value="<?php echo e((string) ($settings['tier_one_score'] ?? '70')); ?>"></label>
                        <label>Tier 2: indice minimo<input class="form-control" type="number" min="0" max="100" name="tier_two_score" value="<?php echo e((string) ($settings['tier_two_score'] ?? '45')); ?>"></label>
                        <label>Factor K de Elo<input class="form-control" type="number" min="1" max="128" name="elo_k_factor" value="<?php echo e((string) ($settings['elo_k_factor'] ?? '32')); ?>"></label>
                        <label>Actualizar centro (segundos)<input class="form-control" type="number" min="5" max="30" name="live_refresh_seconds" value="<?php echo e((string) ($settings['live_refresh_seconds'] ?? '15')); ?>"></label>
                        <label>Aviso previo (minutos)<input class="form-control" type="number" min="1" max="30" name="notification_minutes_before" value="<?php echo e((string) ($settings['notification_minutes_before'] ?? '30')); ?>"></label>
                    </div>
                    <button class="btn btn-outline-warning fw-bold" type="submit"><i class="bi bi-shield-check"></i> Guardar reglas de ejecucion</button>
                </form>
                <form method="post" class="gestion-form mt-3">
                    <input type="hidden" name="action" value="auto_schedule_qualifier">
                    <div class="panel-head compact-head"><div><h3>Programar clasificatorio automaticamente</h3><p>Asigna fechas a todos los combates clasificatorios pendientes.</p></div></div>
                    <div class="two-col">
                        <label>Clasificatorio<select class="form-select" name="qualifier_competition_id"><?php foreach ($qualifierCompetitions as $competition): ?><option value="<?php echo e((string) $competition['id']); ?>"><?php echo e((string) $competition['name']); ?></option><?php endforeach; ?></select></label>
                        <label>Fecha inicial<input class="form-control" type="date" name="qualifier_schedule_start" value="<?php echo e((new DateTimeImmutable())->format('Y-m-d')); ?>"></label>
                    </div>
                    <div class="two-col">
                        <label>Hora inicial<input class="form-control" type="time" name="qualifier_schedule_time" value="19:00"></label>
                        <label>Mesas por dia<input class="form-control" type="number" min="1" max="12" name="qualifier_schedule_tables" value="6"></label>
                        <label>Minutos por mesa<input class="form-control" type="number" min="20" max="180" name="qualifier_schedule_minutes" value="45"></label>
                    </div>
                    <button class="btn btn-outline-info fw-bold" type="submit"><i class="bi bi-magic"></i> Autoprogramar clasificatorio</button>
                </form>
                <form method="post" class="gestion-form mt-3">
                    <input type="hidden" name="action" value="rebuild_qualifier_calendar">
                    <div class="panel-head compact-head"><div><h3>Reconstruir clasificatorio oficial</h3><p>65 combates unicos: inicia hoy y corre todas las fechas hacia adelante.</p></div></div>
                    <div class="two-col">
                        <label>Clasificatorio<select class="form-select" name="qualifier_competition_id"><?php foreach ($qualifierCompetitions as $competition): ?><option value="<?php echo e((string) $competition['id']); ?>"><?php echo e((string) $competition['name']); ?></option><?php endforeach; ?></select></label>
                        <label>Fecha inicial<input class="form-control" type="date" name="qualifier_schedule_start" value="<?php echo e((new DateTimeImmutable())->format('Y-m-d')); ?>"></label>
                    </div>
                    <div class="two-col">
                        <label>Hora inicial<input class="form-control" type="time" name="qualifier_schedule_time" value="19:00"></label>
                        <label>Combates por jornada<input class="form-control" type="number" min="1" max="5" name="qualifier_matches_per_day" value="1"></label>
                        <label>Intervalo en dias<input class="form-control" type="number" min="1" max="7" name="qualifier_day_interval" value="1"></label>
                    </div>
                    <input type="hidden" name="qualifier_schedule_minutes" value="45">
                    <button class="btn btn-warning fw-bold" type="submit"><i class="bi bi-calendar2-check"></i> Aplicar calendario oficial</button>
                </form>
            </section>

            <section class="panel gestion-module" data-gestion-module="resumen">
                <div class="panel-head"><div><h2>Generadores</h2><p>Crea estructuras base sin escribir SQL: amistosos, copas y mundial.</p></div><span class="status-chip">Automatizacion</span></div>
                <div class="generator-actions">
                    <form method="post"><input type="hidden" name="action" value="generate_friendlies"><button class="btn btn-outline-info w-100" type="submit"><i class="bi bi-shuffle"></i> Generar amistosos</button></form>
                    <form method="post"><input type="hidden" name="action" value="generate_leagues"><button class="btn btn-outline-success w-100" type="submit"><i class="bi bi-calendar2-range"></i> Generar ligas completas</button></form>
                    <form method="post"><input type="hidden" name="action" value="generate_cups"><button class="btn btn-outline-warning w-100" type="submit"><i class="bi bi-diagram-3"></i> Generar copas</button></form>
                    <form method="post"><input type="hidden" name="action" value="generate_world"><button class="btn btn-outline-light w-100" type="submit"><i class="bi bi-globe-americas"></i> Generar mundial</button></form>
                </div>
                <button class="btn btn-outline-info mt-3" type="button" data-route="temporadas">Administrar temporadas</button>
            </section>

            <section class="panel gestion-module" data-gestion-module="resultados">
                <div class="panel-head"><div><h2>Crear partido manual</h2><p>Agrega cruces puntuales para desempate, repechaje, copa o amistoso.</p></div><span class="status-chip">Manual</span></div>
                <form method="post" class="gestion-form">
                    <input type="hidden" name="action" value="create_match">
                    <div class="two-col">
                        <label>Formato<select class="form-select" name="manual_division_id"><?php foreach ($displayDivisions as $division): ?><option value="<?php echo e((string) $division['id']); ?>"><?php echo e($division['short']); ?></option><?php endforeach; ?></select></label>
                        <label>Fase<select class="form-select" name="manual_phase"><option value="qualifier">Clasificatorio</option><option value="league">Liga</option><option value="friendly">Amistoso</option><option value="cup">Copa</option><option value="world">Mundial</option><option value="eternal">Eternal</option><option value="tiebreaker">Desempate</option></select></label>
                    </div>
                    <div class="two-col">
                        <label>Evento<select class="form-select" name="manual_competition_id" data-manual-competition-select><option value="">Sin asociar / general</option><?php foreach ($competitions as $competition): ?><option value="<?php echo e((string) $competition['id']); ?>" data-type="<?php echo e((string) ($competition['type'] ?? '')); ?>" data-ruleset="<?php echo e((string) ($competition['event_ruleset'] ?? '')); ?>" data-deck-pool="<?php echo e((string) ($competition['deck_pool'] ?? 'all_active')); ?>" data-division-id="<?php echo e((string) ($competition['division_id'] ?? '')); ?>" data-match-format="<?php echo e((string) ($competition['match_format'] ?? '1v1')); ?>" data-best-of="<?php echo e((string) ($competition['best_of'] ?? 5)); ?>" data-participants="<?php echo e((string) ($competition['participants_per_side'] ?? 1)); ?>"><?php echo e((string) $competition['name']); ?> <?php echo !empty($competition['division_code']) ? '(' . e((string) $competition['division_code']) . ')' : '(Global)'; ?></option><?php endforeach; ?></select></label>
                        <label>Formato<select class="form-select" name="manual_match_format"><?php foreach ($matchTypeOptions as $matchType): ?><option value="<?php echo e((string) $matchType['code']); ?>"><?php echo e((string) ($matchType['label'] ?? $matchType['code'])); ?></option><?php endforeach; ?></select></label>
                    </div>
                    <div class="two-col">
                        <label>Mejor de<input class="form-control" type="number" min="1" max="10" name="manual_best_of" value="5"></label>
                        <label>Participantes por lado<input class="form-control" type="number" min="1" max="3" name="manual_participants_per_side" value="1"></label>
                    </div>
                    <label>Regla para partido sin evento<select class="form-select" name="manual_game_rule_id"><option value="">Usar regla compatible por defecto</option><?php foreach ($gameRules as $rule): ?><?php if (empty($rule['active']) || !in_array((string) ($rule['scope'] ?? ''), ['all_matches', 'friendly', 'match', 'general'], true)) { continue; } ?><option value="<?php echo e((string) ($rule['id'] ?? '')); ?>"><?php echo e((string) ($rule['name'] ?? 'Regla')); ?> | <?php echo e((string) ($rule['scope'] ?? 'general')); ?></option><?php endforeach; ?></select><small>Si eliges un evento, manda la regla del evento. Si queda sin evento o amistoso, usa esta regla.</small></label>
                    <div class="two-col">
                        <label>Ronda<input class="form-control" type="number" min="1" name="manual_round" value="1"></label>
                        <label>Mesa<input class="form-control" type="number" min="1" name="manual_table" value="1"></label>
                    </div>
                    <div class="two-col">
                        <label>Lado A - Deck 1<select class="form-select" name="manual_deck_a_id" data-manual-deck-select><?php foreach ($allDecks as $deck): ?><option value="<?php echo e((string) $deck['id']); ?>" data-division-id="<?php echo e((string) ($deck['division_id'] ?? '')); ?>" data-team-id="<?php echo e((string) ($deck['team_id'] ?? '')); ?>" data-division="<?php echo e((string) ($deck['division'] ?? '')); ?>"><?php echo e($deck['team'] . ' - ' . $deck['name']); ?></option><?php endforeach; ?></select></label>
                        <label>Lado B - Deck 1<select class="form-select" name="manual_deck_b_id" data-manual-deck-select><?php foreach ($allDecks as $deck): ?><option value="<?php echo e((string) $deck['id']); ?>" data-division-id="<?php echo e((string) ($deck['division_id'] ?? '')); ?>" data-team-id="<?php echo e((string) ($deck['team_id'] ?? '')); ?>" data-division="<?php echo e((string) ($deck['division'] ?? '')); ?>"><?php echo e($deck['team'] . ' - ' . $deck['name']); ?></option><?php endforeach; ?></select></label>
                    </div>
                    <div class="two-col">
                        <label>Lado A - Deck 2<select class="form-select" name="manual_deck_c_id" data-manual-deck-select><option value="">No aplica</option><?php foreach ($allDecks as $deck): ?><option value="<?php echo e((string) $deck['id']); ?>" data-division-id="<?php echo e((string) ($deck['division_id'] ?? '')); ?>" data-team-id="<?php echo e((string) ($deck['team_id'] ?? '')); ?>" data-division="<?php echo e((string) ($deck['division'] ?? '')); ?>"><?php echo e($deck['team'] . ' - ' . $deck['name']); ?></option><?php endforeach; ?></select></label>
                        <label>Lado B - Deck 2<select class="form-select" name="manual_deck_d_id" data-manual-deck-select><option value="">No aplica</option><?php foreach ($allDecks as $deck): ?><option value="<?php echo e((string) $deck['id']); ?>" data-division-id="<?php echo e((string) ($deck['division_id'] ?? '')); ?>" data-team-id="<?php echo e((string) ($deck['team_id'] ?? '')); ?>" data-division="<?php echo e((string) ($deck['division'] ?? '')); ?>"><?php echo e($deck['team'] . ' - ' . $deck['name']); ?></option><?php endforeach; ?></select></label>
                    </div>
                    <div class="two-col">
                        <label>Lado A - Deck 3<select class="form-select" name="manual_deck_e_id" data-manual-deck-select><option value="">No aplica</option><?php foreach ($allDecks as $deck): ?><option value="<?php echo e((string) $deck['id']); ?>" data-division-id="<?php echo e((string) ($deck['division_id'] ?? '')); ?>" data-team-id="<?php echo e((string) ($deck['team_id'] ?? '')); ?>" data-division="<?php echo e((string) ($deck['division'] ?? '')); ?>"><?php echo e($deck['team'] . ' - ' . $deck['name']); ?></option><?php endforeach; ?></select></label>
                        <label>Lado B - Deck 3<select class="form-select" name="manual_deck_f_id" data-manual-deck-select><option value="">No aplica</option><?php foreach ($allDecks as $deck): ?><option value="<?php echo e((string) $deck['id']); ?>" data-division-id="<?php echo e((string) ($deck['division_id'] ?? '')); ?>" data-team-id="<?php echo e((string) ($deck['team_id'] ?? '')); ?>" data-division="<?php echo e((string) ($deck['division'] ?? '')); ?>"><?php echo e($deck['team'] . ' - ' . $deck['name']); ?></option><?php endforeach; ?></select></label>
                    </div>
                    <p class="note" data-manual-deck-pool-note>Selecciona un evento para aplicar su pool de decks.</p>
                    <div class="two-col">
                        <label>Fecha<input class="form-control" type="date" name="manual_scheduled_on"></label>
                        <label>Hora<input class="form-control" type="time" name="manual_starts_at"></label>
                    </div>
                    <div class="two-col">
                        <label>Sede<input class="form-control" name="manual_venue" placeholder="Station X Tulua"></label>
                        <label>Juez<input class="form-control" name="manual_judge"></label>
                    </div>
                    <label>Notas<textarea class="form-control" name="manual_notes" rows="2"></textarea></label>
                    <button class="btn btn-primary fw-bold" type="submit"><i class="bi bi-plus-circle"></i> Crear partido</button>
                </form>
                <div class="generator-actions mt-3">
                    <form method="post"><input type="hidden" name="action" value="advance_phase"><input type="hidden" name="phase" value="cup"><button class="btn btn-outline-warning w-100" type="submit"><i class="bi bi-chevron-double-right"></i> Avanzar copa</button></form>
                    <form method="post"><input type="hidden" name="action" value="advance_phase"><input type="hidden" name="phase" value="world"><button class="btn btn-outline-light w-100" type="submit"><i class="bi bi-chevron-double-right"></i> Avanzar mundial</button></form>
                </div>
            </section>

            <section class="panel gestion-module" data-gestion-module="cartas">
                <div class="panel-head"><div><h2>Cartas y media del deck</h2><p>Registra main deck, side deck, variantes, logos e imagenes.</p></div><span class="status-chip">Ficha avanzada</span></div>
                <form method="post" class="gestion-form">
                    <input type="hidden" name="action" value="save_deck_card">
                    <label>Deck<select class="form-select" name="card_deck_id"><?php foreach ($allDecks as $deck): ?><option value="<?php echo e((string) $deck['id']); ?>"><?php echo e($deck['name']); ?></option><?php endforeach; ?></select></label>
                    <div class="two-col">
                        <label>Carta<input class="form-control" name="card_name" required></label>
                        <label>Rol<input class="form-control" name="card_role" placeholder="Starter, extender, handtrap"></label>
                    </div>
                    <div class="two-col">
                        <label>Cantidad<input class="form-control" type="number" min="1" max="3" name="card_quantity" value="1"></label>
                        <label>Seccion<select class="form-select" name="card_section"><option value="main">Main</option><option value="side">Side</option><option value="extra">Extra</option><option value="tech">Tech</option></select></label>
                    </div>
                    <label>Notas<input class="form-control" name="card_notes"></label>
                    <button class="btn btn-primary fw-bold" type="submit"><i class="bi bi-card-list"></i> Agregar carta</button>
                </form>
                <form method="post" class="gestion-form mt-3">
                    <input type="hidden" name="action" value="import_deck_cards">
                    <div class="panel-head compact-head"><div><h3>Importar lista real completa</h3><p>Una carta por linea: seccion;cantidad;nombre;rol;notas. No se generan cartas ficticias.</p></div></div>
                    <label>Deck<select class="form-select" name="import_card_deck_id"><?php foreach ($allDecks as $deck): ?><option value="<?php echo e((string) $deck['id']); ?>"><?php echo e($deck['name']); ?></option><?php endforeach; ?></select></label>
                    <label>Lista de cartas<textarea class="form-control" name="deck_card_rows" rows="8" placeholder="main;3;Nombre exacto;Starter;Notas&#10;extra;1;Nombre exacto;Boss;"></textarea></label>
                    <label class="toggle-line"><input type="checkbox" name="replace_deck_cards" value="1"> Reemplazar la lista actual de este deck</label>
                    <button class="btn btn-primary fw-bold" type="submit"><i class="bi bi-file-earmark-arrow-up"></i> Importar lista</button>
                </form>
                <form method="post" class="gestion-form mt-3">
                    <input type="hidden" name="action" value="save_deck_media">
                    <label>Deck<select class="form-select" name="media_deck_id"><?php foreach ($allDecks as $deck): ?><option value="<?php echo e((string) $deck['id']); ?>"><?php echo e($deck['name']); ?></option><?php endforeach; ?></select></label>
                    <div class="two-col">
                        <label>Tipo<select class="form-select" name="media_type"><option value="image">Imagen</option><option value="logo">Logo</option><option value="video">Video</option></select></label>
                        <label>Titulo<input class="form-control" name="media_title"></label>
                    </div>
                    <label>URL<input class="form-control" name="media_url" required></label>
                    <button class="btn btn-outline-info fw-bold" type="submit"><i class="bi bi-image"></i> Agregar media</button>
                </form>
            </section>

            <section class="panel gestion-module" data-gestion-module="resumen">
                <div class="panel-head"><div><h2>Palmares</h2><p>Guarda campeones, subcampeones y cierre historico.</p></div><span class="status-chip"><?php echo count($honors); ?> registros</span></div>
                <form method="post" class="gestion-form">
                    <input type="hidden" name="action" value="save_honor">
                    <label>Titulo<input class="form-control" name="honor_title" placeholder="Campeon Copa Apex" required></label>
                    <label>Evento<select class="form-select" name="honor_competition_id"><option value="">General</option><?php foreach ($competitions as $competition): ?><option value="<?php echo e((string) $competition['id']); ?>"><?php echo e((string) $competition['name']); ?></option><?php endforeach; ?></select></label>
                    <div class="two-col">
                        <label>Campeon<select class="form-select" name="honor_champion_deck_id"><option value="">Por definir</option><?php foreach ($allDecks as $deck): ?><option value="<?php echo e((string) $deck['id']); ?>"><?php echo e($deck['name']); ?></option><?php endforeach; ?></select></label>
                        <label>Subcampeon<select class="form-select" name="honor_runner_deck_id"><option value="">Por definir</option><?php foreach ($allDecks as $deck): ?><option value="<?php echo e((string) $deck['id']); ?>"><?php echo e($deck['name']); ?></option><?php endforeach; ?></select></label>
                    </div>
                    <label>Notas<textarea class="form-control" name="honor_notes" rows="2"></textarea></label>
                    <button class="btn btn-primary fw-bold" type="submit"><i class="bi bi-trophy"></i> Guardar palmares</button>
                </form>
            </section>

            <section class="panel audit-panel gestion-module" data-gestion-module="auditoria">
                <div class="panel-head"><div><h2>Auditoria</h2><p>Ultimas acciones registradas del sistema.</p></div><span class="status-chip"><?php echo count($audit); ?> eventos</span></div>
                <div class="audit-summary-grid mb-3">
                    <?php foreach (array_slice($auditSummary, 0, 6, true) as $action => $count): ?>
                        <article class="<?php echo str_contains((string) $action, 'blocked') ? 'danger' : ''; ?>"><span><?php echo e(ygo_audit_label((string) $action)); ?></span><b><?php echo e((string) $count); ?></b></article>
                    <?php endforeach; ?>
                    <?php if (!$auditSummary): ?><article><span>Sin auditoria</span><b>0</b></article><?php endif; ?>
                </div>
                <div class="result-filter-grid mb-3">
                    <input class="form-control" data-audit-search placeholder="Buscar accion, entidad o detalle">
                    <select class="form-select" data-audit-kind>
                        <option value="">Todas las acciones</option>
                        <option value="blocked">Bloqueos</option>
                        <option value="save">Guardados</option>
                        <option value="schedule">Calendario</option>
                        <option value="result">Resultados</option>
                    </select>
                    <select class="form-select" data-audit-entity>
                        <option value="">Todas las entidades</option>
                        <option value="competition">Eventos</option>
                        <option value="match">Combates</option>
                        <option value="deck">Decks</option>
                        <option value="division">Divisiones</option>
                        <option value="qa">QA</option>
                    </select>
                    <select class="form-select" data-audit-action>
                        <option value="">Todas las acciones especificas</option>
                        <?php foreach (array_keys($auditSummary) as $auditActionOption): ?>
                            <option value="<?php echo e((string) $auditActionOption); ?>"><?php echo e(ygo_audit_label((string) $auditActionOption)); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="table-responsive">
                    <table class="table app-table compact mobile-card-table audit-mobile-table">
                        <thead><tr><th>Fecha</th><th>Entidad</th><th>Tipo</th><th>Accion</th><th>Lectura</th><th>Detalle tecnico</th></tr></thead>
                        <tbody data-audit-body><?php foreach (array_slice($audit, 0, 40) as $row): ?><?php $auditAction = (string) $row['action']; $auditEntity = (string) ($row['entity_type'] ?? ''); $auditTone = str_contains($auditAction, 'blocked') ? 'danger' : (str_contains($auditAction, 'save') || str_contains($auditAction, 'recalculate') ? 'ok' : 'warn'); $auditLabel = ygo_audit_label($auditAction); ?><tr data-audit-row data-audit-entity="<?php echo e($auditEntity); ?>" data-audit-action="<?php echo e($auditAction); ?>" data-audit-text="<?php echo e(strtolower((string) $row['created_at'] . ' ' . $auditEntity . ' ' . $row['entity_id'] . ' ' . $auditAction . ' ' . $auditLabel . ' ' . $row['payload'])); ?>"><td data-label="Fecha"><?php echo e((string) $row['created_at']); ?></td><td data-label="Entidad"><?php echo e($auditEntity); ?> #<?php echo e((string) $row['entity_id']); ?></td><td data-label="Tipo"><span class="status-chip <?php echo e($auditTone); ?>"><?php echo e($auditTone === 'danger' ? 'Alerta' : ($auditTone === 'ok' ? 'Guardado' : 'Cambio')); ?></span></td><td data-label="Accion"><?php echo e($auditLabel); ?></td><td data-label="Lectura"><?php echo e($auditTone === 'danger' ? 'Revisar evento, prerequisito, fecha o pool antes de repetir la accion.' : 'Accion registrada correctamente.'); ?></td><td data-label="Detalle" data-raw-text><?php echo e(substr((string) $row['payload'], 0, 180)); ?></td></tr><?php endforeach; ?></tbody>
                    </table>
                </div>
            </section>

            <section class="panel gestion-module" data-gestion-module="resumen">
                <h2>Reglas vivas de configuracion</h2>
                <ul class="rule-list">
                    <li>Titan, Legend, Elite y Challenger son datos editables, no codigo fijo.</li>
                    <li>Los equipos se crean aparte y pueden representar varios decks.</li>
                    <li>Los eventos controlan fechas para el calendario visual.</li>
                    <li>El clasificatorio se juega y al finalizar redistribuye decks en los formatos oficiales.</li>
                    <li>La UI usa estilo competitivo oscuro inspirado en Master Duel: paneles, brillos, emblemas, ranking y cartas.</li>
                </ul>
            </section>
        </div>
    </section>
</main>
</div>

<div class="modal fade dml-modal" id="alertCenterModal" tabindex="-1" aria-hidden="true" data-alert-modal>
    <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header border-0">
                <div>
                    <span class="tag">Notificaciones</span>
                    <h2 class="modal-title">Centro de alertas</h2>
                    <p>En curso primero; después vencidos, hoy, mañana y sin fecha.</p>
                </div>
                <button class="btn-close btn-close-white" type="button" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <div class="modal-body">
                <section class="alert-center compact" data-alert-center="modal"></section>
            </div>
        </div>
    </div>
</div>

<div class="modal fade dml-modal" id="qualifierStatusModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <div>
                    <span class="tag">Clasificatorio</span>
                    <h2 class="modal-title"><?php echo e((string) ($displayQualifierCompetition['name'] ?? 'Estado del clasificatorio')); ?></h2>
                    <p><?php echo e((string) (($displayQualifierCompetition['code'] ?? '') ?: 'Sin codigo')); ?> · solo se modificara este evento.</p>
                </div>
                <span class="status-chip"><?php echo e($qualifierStatusLabel); ?></span>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <div class="modal-body">
                <ul class="rule-list">
                    <?php foreach ($qualifierRules as $rule): ?>
                        <li><?php echo e($rule); ?></li>
                    <?php endforeach; ?>
                </ul>
                <div class="rule-note mt-3 <?php echo $qualifierFinalizeCheck['ok'] ? 'ok' : 'warn'; ?>">
                    <i class="bi <?php echo $qualifierFinalizeCheck['ok'] ? 'bi-unlock-fill' : 'bi-lock-fill'; ?>"></i>
                    <p>
                        <strong><?php echo $qualifierFinalizeCheck['ok'] ? 'Clasificatorio listo para finalizar.' : 'Cierre bloqueado.'; ?></strong><br>
                        <?php echo e($qualifierFinalizeCheck['ok'] ? 'Primera ronda completa, sin rondas en curso y minimo deportivo cumplido.' : implode(' ', $qualifierFinalizeCheck['reasons'])); ?>
                    </p>
                </div>
            </div>
            <div class="modal-footer modal-actions-row">
                <button class="btn btn-primary" type="button" data-route="resultados" data-bs-dismiss="modal"><i class="bi bi-pencil-square"></i> Registrar combate</button>
                <button class="btn btn-outline-light" type="button" data-route="gestion" data-bs-dismiss="modal"><i class="bi bi-sliders"></i> Gestionar torneo</button>
                <?php if ($dbStatus['connected']): ?>
                    <form method="post" class="d-inline-flex gap-2">
                        <input type="hidden" name="action" value="set_qualifier_status">
                        <input type="hidden" name="qualifier_competition_id" value="<?php echo e((string) $displayQualifierId); ?>">
                        <button class="btn btn-outline-info" name="status" value="active" type="submit"><i class="bi bi-play-circle"></i> Marcar en curso</button>
                        <button class="btn btn-warning fw-bold" name="status" value="completed" type="submit" <?php echo $qualifierFinalizeCheck['ok'] ? '' : 'disabled'; ?>><i class="bi bi-flag-fill"></i> Finalizar clasificatorio</button>
                    </form>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<div class="modal fade dml-modal" id="teamSheetModal" tabindex="-1" aria-hidden="true" data-team-sheet-modal>
    <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <div>
                    <span class="tag">Decks</span>
                    <h2 class="modal-title">Asociar decks al equipo</h2>
                    <p>Agrega decks sin equipo o quita decks asociados sin salir de la ficha.</p>
                </div>
                <button class="btn-close btn-close-white" type="button" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <div class="modal-body team-sheet-modal-body">
                <section class="panel">
                    <div class="panel-head"><div><h2>Decks del equipo</h2><p>Busca un deck sin equipo para asociarlo o marca asociados para quitarlos.</p></div><span class="status-chip" data-team-sheet-deck-count>0 decks</span></div>
                    <form method="post" class="gestion-form team-deck-link-form">
                        <input type="hidden" name="action" value="save_team_deck_links">
                        <input type="hidden" name="team_id" data-team-link-team-id>
                        <div class="team-deck-selector-row">
                            <label>Buscar deck para asociar<input class="form-control" list="teamAvailableDeckOptions" placeholder="Escribe nombre, formato o arquetipo..." data-team-deck-picker></label>
                            <datalist id="teamAvailableDeckOptions" data-team-deck-picker-options></datalist>
                            <button class="btn btn-outline-info fw-bold" type="button" data-team-add-picked-deck><i class="bi bi-plus-circle"></i> Agregar</button>
                        </div>
                        <select class="visually-hidden" name="team_add_deck_ids[]" multiple data-team-add-decks></select>
                        <select class="visually-hidden" name="team_remove_deck_ids[]" multiple data-team-remove-decks></select>
                        <div class="team-deck-save-row">
                            <span data-team-link-summary>Selecciona decks para asociar o quitar.</span>
                            <button class="btn btn-primary fw-bold" type="submit"><i class="bi bi-link-45deg"></i> Guardar asociaciones</button>
                        </div>
                    </form>
                    <div class="profile-list mt-3" data-team-sheet-current-decks></div>
                </section>
            </div>
        </div>
    </div>
</div>

<div class="modal fade dml-modal" id="matchSheetModal" tabindex="-1" aria-hidden="true" data-match-sheet-modal>
    <div class="modal-dialog modal-dialog-centered modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <div>
                    <span class="tag" data-match-sheet-phase>Combate</span>
                    <h2 class="modal-title" data-match-sheet-title>Ficha del combate</h2>
                    <p data-match-sheet-meta></p>
                    <div class="match-header-identity" data-match-sheet-identity></div>
                </div>
                <div class="modal-top-actions">
                    <button class="btn btn-primary fw-bold" type="button" data-match-sheet-capture><i class="bi bi-clipboard2-check"></i> Cargar resultado</button>
                </div>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <div class="modal-body">
                <div class="match-sheet-grid">
                    <section class="match-sheet-duel">
                        <article>
                            <span class="match-sheet-logo" data-match-sheet-logo-a>A</span>
                            <small data-match-sheet-team-a>Equipo A</small>
                            <strong data-match-sheet-a>Deck A</strong>
                            <span class="match-league-position" data-match-sheet-league-a hidden></span>
                        </article>
                        <b>VS</b>
                        <article>
                            <span class="match-sheet-logo" data-match-sheet-logo-b>B</span>
                            <small data-match-sheet-team-b>Equipo B</small>
                            <strong data-match-sheet-b>Deck B</strong>
                            <span class="match-league-position" data-match-sheet-league-b hidden></span>
                        </article>
                    </section>
                    <section class="match-sheet-stats">
                        <h3>Estado competitivo</h3>
                        <div class="mini-grid">
                            <span><b>ID</b><br><small data-match-sheet-id>-</small></span>
                            <span><b>Estado</b><br><small data-match-sheet-status>-</small></span>
                            <span><b>Marcador</b><br><small data-match-sheet-score>-</small></span>
                            <span><b>Resultado</b><br><small data-match-sheet-winner>-</small></span>
                        </div>
                        <div class="stat-bars mt-3" data-match-sheet-duel-bars></div>
                    </section>
                </div>
                <div class="match-sheet-details mt-3">
                    <span><b>Fecha programada</b><small data-match-sheet-date></small></span>
                    <span><b>Finalizacion del duelo</b><small data-match-sheet-completed></small></span>
                    <span><b>Inicio</b><small data-match-sheet-turn></small></span>
                    <span><b>Notas</b><small data-match-sheet-notes></small></span>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="modal fade dml-modal" id="resultCaptureModal" tabindex="-1" aria-hidden="true" data-result-capture-modal>
    <div class="modal-dialog modal-xl result-capture-dialog">
        <form method="post" class="modal-content result-capture-form" data-result-capture-form>
            <input type="hidden" name="action" value="save_match_result">
            <input type="hidden" name="match_id" data-result-capture-match-id>
            <input type="hidden" name="result_dice_roll" data-result-capture-dice-json>
            <input type="hidden" name="result_turn_choice" data-result-capture-turn-choice>
            <input type="hidden" name="result_duel_details" data-result-capture-details-json>
            <input type="hidden" name="result_correction_version" data-result-correction-version>
            <div class="modal-header result-console-head">
                <div class="result-console-title">
                    <span class="tag" data-result-capture-phase>Resultado</span>
                    <h2 class="modal-title" data-result-capture-title>Capturar resultado</h2>
                    <p data-result-capture-meta></p>
                    <div class="match-header-identity" data-result-capture-identity></div>
                </div>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <div class="modal-body result-console-body">
                <div class="match-sheet-duel result-capture-duel result-versus-hero">
                    <article class="result-side-card result-side-a" data-result-capture-side-a>
                        <span class="result-capture-team-logo" data-result-capture-logo-a>A</span>
                        <span class="result-side-copy">
                            <small>Equipo A</small>
                            <b data-result-capture-team-a>Equipo A</b>
                            <strong data-result-capture-a>Deck A</strong>
                            <span class="match-league-position" data-result-capture-league-a hidden></span>
                        </span>
                    </article>
                    <b class="result-vs-core">
                        <span><em data-result-capture-score-a>0</em><i>VS</i><em data-result-capture-score-b>0</em></span>
                        <small data-result-capture-bestof>Mejor de 3</small>
                    </b>
                    <article class="result-side-card result-side-b" data-result-capture-side-b>
                        <span class="result-capture-team-logo" data-result-capture-logo-b>B</span>
                        <span class="result-side-copy">
                            <small>Equipo B</small>
                            <b data-result-capture-team-b>Equipo B</b>
                            <strong data-result-capture-b>Deck B</strong>
                            <span class="match-league-position" data-result-capture-league-b hidden></span>
                        </span>
                    </article>
                </div>
                <section class="result-player-screens" data-result-player-screens>
                    <div><small>Pantallas sincronizadas</small><strong>Control del juez</strong><span data-result-live-sync>Preparando sesión en vivo...</span></div>
                    <button class="btn btn-outline-info result-terminals-open" type="button" data-result-terminals-open><i class="bi bi-phone"></i> Terminales</button>
                    <div class="result-terminals-sheet" data-result-terminals-sheet aria-hidden="true">
                        <button class="result-terminals-backdrop" type="button" data-result-terminals-close aria-label="Cerrar terminales"></button>
                        <section class="result-terminals-panel" role="dialog" aria-modal="true" aria-labelledby="resultTerminalsTitle">
                            <header>
                                <div><small>Acceso para cada lado</small><h3 id="resultTerminalsTitle">Terminales</h3></div>
                                <button class="btn btn-outline-light btn-sm" type="button" data-result-terminals-close aria-label="Cerrar terminales"><i class="bi bi-x-lg"></i></button>
                            </header>
                            <div class="result-player-screen-actions">
                                <div class="result-player-screen-group">
                                    <strong>Terminal A</strong>
                                    <a class="btn btn-outline-info btn-sm" href="#" target="_blank" rel="noopener" data-result-player-link="A"><i class="bi bi-phone"></i> Abrir</a>
                                    <button class="btn btn-outline-light btn-sm result-player-copy" type="button" data-result-player-copy="A" title="Copiar enlace de la terminal A" aria-label="Copiar enlace de la terminal A"><i class="bi bi-copy"></i></button>
                                </div>
                                <div class="result-player-screen-group">
                                    <strong>Terminal B</strong>
                                    <a class="btn btn-outline-info btn-sm" href="#" target="_blank" rel="noopener" data-result-player-link="B"><i class="bi bi-phone"></i> Abrir</a>
                                    <button class="btn btn-outline-light btn-sm result-player-copy" type="button" data-result-player-copy="B" title="Copiar enlace de la terminal B" aria-label="Copiar enlace de la terminal B"><i class="bi bi-copy"></i></button>
                                </div>
                            </div>
                        </section>
                    </div>
                </section>
                <section class="result-step result-dice-panel">
                    <span class="result-step-index">1</span>
                    <div class="result-dice-copy">
                        <i class="bi bi-dice-5"></i>
                        <span>
                            <h3>Resumen del match</h3>
                            <p data-result-capture-dice-summary>Sin lanzamiento registrado.</p>
                        </span>
                    </div>
                    <h3 class="result-sidebar-subtitle">Tirada de inicio</h3>
                    <div class="result-dice-stage" aria-hidden="true" data-result-dice-stage>
                        <div class="result-dice-player-label result-dice-player-a">Equipo A</div>
                        <div class="result-dice-player-label result-dice-player-b">Equipo B</div>
                        <canvas class="result-dice-3d-canvas" data-result-dice-canvas></canvas>
                        <div class="result-die result-die-cube" data-result-die-a data-value="1">
                            <span class="die-face die-face-1"><i></i></span>
                            <span class="die-face die-face-2"><i></i><i></i></span>
                            <span class="die-face die-face-3"><i></i><i></i><i></i></span>
                            <span class="die-face die-face-4"><i></i><i></i><i></i><i></i></span>
                            <span class="die-face die-face-5"><i></i><i></i><i></i><i></i><i></i></span>
                            <span class="die-face die-face-6"><i></i><i></i><i></i><i></i><i></i><i></i></span>
                        </div>
                        <strong>VS</strong>
                        <div class="result-die result-die-cube" data-result-die-b data-value="1">
                            <span class="die-face die-face-1"><i></i></span>
                            <span class="die-face die-face-2"><i></i><i></i></span>
                            <span class="die-face die-face-3"><i></i><i></i><i></i></span>
                            <span class="die-face die-face-4"><i></i><i></i><i></i><i></i></span>
                            <span class="die-face die-face-5"><i></i><i></i><i></i><i></i><i></i></span>
                            <span class="die-face die-face-6"><i></i><i></i><i></i><i></i><i></i><i></i></span>
                        </div>
                    </div>
                    <button class="btn btn-warning fw-bold" type="button" data-result-roll-dice><i class="bi bi-dice-5"></i> Lanzar dado</button>
                    <div class="result-turn-choice" data-result-turn-choice-panel hidden>
                        <span data-result-turn-choice-question></span>
                        <button class="btn btn-outline-info btn-sm" type="button" data-result-turn-select="first">Elige primero</button>
                        <button class="btn btn-outline-info btn-sm" type="button" data-result-turn-select="second">Elige segundo</button>
                    </div>
                </section>
                <section class="result-step result-duel-section">
                    <span class="result-step-index">2</span>
                    <header class="result-duel-section-head">
                        <div>
                            <h3><i class="bi bi-crosshair"></i> Duelo actual <span data-result-current-duel>D1</span></h3>
                            <p>Registra únicamente el duelo en curso. Al guardarlo se habilitará el siguiente si todavía es necesario.</p>
                        </div>
                        <div class="result-duel-progress">
                            <span>Duelos completados</span>
                            <i><b data-result-duel-progress-bar></b></i>
                            <strong data-result-duel-progress-text>0 / 5</strong>
                        </div>
                    </header>
                    <button class="btn btn-outline-info result-history-open" type="button" data-result-history-open><i class="bi bi-clock-history"></i> Historial de duelos</button>
                    <div class="result-history-sheet" data-result-history-sheet aria-hidden="true">
                        <button class="result-history-backdrop" type="button" data-result-history-close aria-label="Cerrar historial"></button>
                        <div class="result-duel-history" role="dialog" aria-modal="true" aria-labelledby="resultHistoryTitle">
                            <header>
                                <div><h4 id="resultHistoryTitle">Historial de duelos</h4><p>Resultados ya registrados en este match.</p></div>
                                <button class="btn btn-outline-light btn-sm result-history-close" type="button" data-result-history-close aria-label="Cerrar historial"><i class="bi bi-x-lg"></i></button>
                            </header>
                            <div class="result-duel-history-list" data-result-capture-duel-list></div>
                        </div>
                    </div>
                    <div class="result-duel-detail-grid">
                        <?php for ($i = 1; $i <= 5; $i++): ?>
                            <section class="result-duel-detail-card" data-result-duel-card="<?php echo $i; ?>">
                                <header>
                                    <h3>D<?php echo $i; ?></h3>
                                    <span data-result-duel-status="<?php echo $i; ?>">Pendiente</span>
                                </header>
                                <div class="result-duel-setup">
                                    <div class="result-duel-start-box">
                                        <small>Resultado del dado</small>
                                        <div>
                                            <b data-result-duel-dice-a="<?php echo $i; ?>">-</b>
                                            <b data-result-duel-dice-b="<?php echo $i; ?>">-</b>
                                        </div>
                                        <button class="btn btn-warning btn-sm result-draw-reroll" type="button" data-result-duel-reroll="<?php echo $i; ?>" hidden><i class="bi bi-dice-5"></i> Lanzar dados por empate</button>
                                        <small>Empieza primero</small>
                                        <span data-result-duel-first="<?php echo $i; ?>">Sin definir</span>
                                        <small>Empieza segundo</small>
                                        <span data-result-duel-second="<?php echo $i; ?>">Sin definir</span>
                                    </div>
                                    <div class="result-turn-ledger" data-result-duel-turn-ledger="<?php echo $i; ?>">
                                        <span data-result-duel-turn-owner="<?php echo $i; ?>">Define el inicio del duelo.</span>
                                        <div class="result-turn-ledger-actions">
                                            <button class="btn btn-outline-info btn-sm" type="button" data-result-duel-turn-select="<?php echo $i; ?>" data-choice="first">Elige empezar</button>
                                            <button class="btn btn-outline-info btn-sm" type="button" data-result-duel-turn-select="<?php echo $i; ?>" data-choice="second">Elige segundo</button>
                                        </div>
                                        <input type="hidden" data-result-duel-turn-choice="<?php echo $i; ?>">
                                    </div>
                                    <div class="result-duel-launch" data-result-duel-launch="<?php echo $i; ?>" hidden>
                                        <span><small>Preparacion completa</small><strong>Confirma para comenzar D<?php echo $i; ?></strong></span>
                                        <button class="btn btn-primary fw-bold" type="button" data-result-duel-begin="<?php echo $i; ?>"><i class="bi bi-play-fill"></i> Comenzar duelo</button>
                                    </div>
                                </div>
                                <div class="result-duel-live" data-result-duel-live="<?php echo $i; ?>" hidden>
                                    <section class="result-turn-tracker">
                                        <span>Turno actual</span>
                                        <strong data-result-turn-current="<?php echo $i; ?>">Turno 1</strong>
                                        <b data-result-turn-player="<?php echo $i; ?>">Esperando inicio</b>
                                        <div>
                                            <button class="btn btn-primary fw-bold" type="button" data-result-turn-end="<?php echo $i; ?>"><i class="bi bi-check2-circle"></i> Terminar turno</button>
                                        </div>
                                        <small data-result-turn-total="<?php echo $i; ?>">0 turnos completados</small>
                                        <div class="result-turn-history" data-result-turn-history="<?php echo $i; ?>"></div>
                                        <input type="hidden" data-result-capture-turns="<?php echo $i; ?>" value="0">
                                    </section>
                                    <div class="result-duel-timer" data-result-duel-timer="<?php echo $i; ?>">
                                        <span>Tiempo del duelo</span><strong data-result-duel-clock="<?php echo $i; ?>">20:00</strong>
                                        <input type="hidden" data-result-capture-duration="<?php echo $i; ?>" value="0">
                                        <div><button class="btn btn-outline-info btn-sm" type="button" data-result-timer-toggle="<?php echo $i; ?>"><i class="bi bi-pause-fill"></i> Pausar reloj</button></div>
                                        <small data-result-duel-alarm="<?php echo $i; ?>">Limite 20 min. Aviso cuando falten 5 min.</small>
                                    </div>
                                </div>
                                <button class="btn btn-outline-info result-outcome-open" type="button" data-result-outcome-open="<?php echo $i; ?>"><i class="bi bi-flag"></i> Registrar resultado</button>
                                <div class="result-outcome-sheet" data-result-outcome-sheet="<?php echo $i; ?>" aria-hidden="true">
                                    <button class="result-outcome-backdrop" type="button" data-result-outcome-close="<?php echo $i; ?>" aria-label="Cerrar resultado"></button>
                                    <section class="result-outcome-panel" role="dialog" aria-modal="true" aria-labelledby="resultOutcomeTitle<?php echo $i; ?>">
                                        <header>
                                            <div><small>Duelo D<?php echo $i; ?></small><h3 id="resultOutcomeTitle<?php echo $i; ?>">Resultado y cierre</h3></div>
                                            <button class="btn btn-outline-light btn-sm" type="button" data-result-outcome-close="<?php echo $i; ?>" aria-label="Cerrar resultado"><i class="bi bi-x-lg"></i></button>
                                        </header>
                                        <div class="result-duel-outcome-grid">
                                            <label>Resultado del duelo<select class="form-select" name="d<?php echo $i; ?>" data-result-capture-duel="<?php echo $i; ?>"><option value="">Sin jugar</option><option value="A">Gana A</option><option value="E">Empate</option><option value="B">Gana B</option></select></label>
                                            <label>Como termino<select class="form-select" data-result-capture-method="<?php echo $i; ?>"><option value="">Sin definir</option><option value="life_points">Derrota definitiva por LP</option><option value="surrender">Rendicion del oponente</option><option value="time_limit">Tiempo finalizado</option><option value="deck_out">Deck sin cartas</option><option value="effect_win">Condicion de victoria por efecto</option></select></label>
                                        </div>
                                        <button class="btn btn-primary result-outcome-done" type="button" data-result-outcome-close="<?php echo $i; ?>"><i class="bi bi-check2"></i> Listo</button>
                                    </section>
                                </div>
                                <fieldset class="result-brick-fieldset" data-result-brick-fieldset="<?php echo $i; ?>">
                                    <legend>Mano brick <small>Opcional; aplica al primer turno de cada jugador.</small></legend>
                                    <label data-result-brick-side="A"><input class="form-check-input" type="checkbox" data-result-capture-brick-a="<?php echo $i; ?>"> Lado A tuvo brick</label>
                                    <label data-result-brick-side="B"><input class="form-check-input" type="checkbox" data-result-capture-brick-b="<?php echo $i; ?>"> Lado B tuvo brick</label>
                                </fieldset>
                                <div class="result-brick-summary" data-result-brick-summary="<?php echo $i; ?>" hidden><i class="bi bi-hand-index-thumb"></i><span>Manos iniciales</span><strong data-result-brick-summary-text="<?php echo $i; ?>">A: No | B: No</strong></div>
                                <div class="result-lp-grid">
                                    <label>LP final A<input class="form-control" type="number" min="0" max="99999" step="1" data-result-capture-lp-a="<?php echo $i; ?>" placeholder="0" readonly></label>
                                    <label>LP final B<input class="form-control" type="number" min="0" max="99999" step="1" data-result-capture-lp-b="<?php echo $i; ?>" placeholder="0" readonly></label>
                                </div>
                                <div class="result-lp-grid result-participant-grid" data-result-participant-grid="<?php echo $i; ?>">
                                    <label>Deck participante A<select class="form-select" data-result-capture-deck-a="<?php echo $i; ?>"></select></label>
                                    <label>Deck participante B<select class="form-select" data-result-capture-deck-b="<?php echo $i; ?>"></select></label>
                                    <label>Jugador A<select class="form-select" data-result-capture-player-a="<?php echo $i; ?>"><option value="">Sin asignar</option></select></label>
                                    <label>Jugador B<select class="form-select" data-result-capture-player-b="<?php echo $i; ?>"><option value="">Sin asignar</option></select></label>
                                </div>
                                <button class="btn btn-outline-info btn-sm result-lp-open" type="button" data-result-lp-open="<?php echo $i; ?>"><i class="bi bi-calculator"></i> Calculadora LP</button>
                                <input type="hidden" data-result-capture-origin="<?php echo $i; ?>">
                                <label class="result-duel-note-field">Notas del combate<input class="form-control" maxlength="500" data-result-capture-duel-note="<?php echo $i; ?>" placeholder="Sin notas..."></label>
                                <div class="result-duel-card-summary" data-result-duel-card-summary="<?php echo $i; ?>">Pendiente de registrar</div>
                            </section>
                        <?php endfor; ?>
                    </div>
                </section>
                <section class="result-step result-notes-section">
                    <span class="result-step-index">3</span>
                    <div class="result-notes-title">
                        <i class="bi bi-file-earmark-text"></i>
                        <span>
                            <h3>Notas de auditoria</h3>
                            <p>Agrega correcciones, observaciones o el nombre del juez.</p>
                        </span>
                    </div>
                    <label><input class="form-control" name="result_notes" maxlength="500" data-result-capture-notes placeholder="Escribe aqui..."></label>
                    <small><span data-result-notes-count>0</span> / 500</small>
                </section>
                <section class="result-step result-save-section">
                    <span class="result-step-index">4</span>
                    <div class="result-validation-title">
                        <i class="bi bi-check-circle-fill"></i>
                        <span>
                            <h3>Estado y validacion</h3>
                            <p>Revisa la informacion antes de guardar.</p>
                        </span>
                    </div>
                    <div class="result-score-pill">
                        <span>Marcador del match</span>
                        <strong data-result-capture-score-pill>0 - 0</strong>
                    </div>
                    <div class="result-validation-pill" data-result-validation-pill>
                        <strong data-result-capture-validation-title>Validacion OK</strong>
                        <small data-result-capture-summary>Sin captura</small>
                    </div>
                </section>
                <section class="result-lp-calculator" data-result-lp-calculator hidden aria-label="Calculadora de puntos de vida">
                    <header>
                        <div><span class="tag">Duelo <b data-result-lp-duel>D1</b></span><h3>Calculadora de puntos de vida</h3></div>
                        <button class="btn btn-outline-info btn-sm" type="button" data-result-lp-sound-toggle aria-pressed="true" aria-label="Silenciar sonido de puntos de vida" title="Silenciar sonido de puntos de vida"><i class="bi bi-volume-up-fill" aria-hidden="true"></i></button>
                        <button class="icon-button" type="button" data-result-lp-close title="Cerrar calculadora"><i class="bi bi-x-lg"></i></button>
                    </header>
                    <div class="result-lp-timer">
                        <button type="button" data-result-lp-timer-toggle title="Iniciar o pausar"><i class="bi bi-play-fill"></i></button>
                        <strong data-result-lp-timer>00:00:00</strong>
                        <button type="button" data-result-lp-timer-reset title="Reiniciar cronometro"><i class="bi bi-arrow-counterclockwise"></i></button>
                    </div>
                    <div class="result-lp-grid result-lp-start-grid">
                        <label>LP inicial A<input class="form-control" type="number" min="0" step="100" value="8000" data-result-lp-start="a"></label>
                        <label>LP inicial B<input class="form-control" type="number" min="0" step="100" value="8000" data-result-lp-start="b"></label>
                        <button class="btn btn-outline-info" type="button" data-result-lp-start-apply>Aplicar LP inicial</button>
                    </div>
                    <div class="result-lp-scoreboard">
                        <button class="result-lp-side active" type="button" data-result-lp-side="a"><small>Equipo A</small><span data-result-lp-name="a">Equipo A</span><strong data-result-lp-value="a">8000</strong></button>
                        <span class="result-lp-versus">VS</span>
                        <button class="result-lp-side" type="button" data-result-lp-side="b"><small>Equipo B</small><span data-result-lp-name="b">Equipo B</span><strong data-result-lp-value="b">8000</strong></button>
                    </div>
                    <div class="result-lp-workbench">
                        <div class="result-lp-entry"><span>Valor</span><strong data-result-lp-entry>0</strong></div>
                        <div class="result-lp-keypad">
                            <?php foreach (['7', '8', '9', '4', '5', '6', '1', '2', '3', '0', '00', '000'] as $key): ?><button type="button" data-result-lp-key="<?php echo e($key); ?>"><?php echo e($key); ?></button><?php endforeach; ?>
                        </div>
                        <div class="result-lp-operations">
                            <button type="button" data-result-lp-op="clear"><i class="bi bi-backspace"></i><span>Borrar</span></button>
                            <button type="button" data-result-lp-op="subtract"><i class="bi bi-dash-lg"></i><span>Restar</span></button>
                            <button type="button" data-result-lp-op="add"><i class="bi bi-plus-lg"></i><span>Sumar</span></button>
                            <button type="button" data-result-lp-op="half"><b>1/2</b><span>Mitad</span></button>
                            <button type="button" data-result-lp-op="undo"><i class="bi bi-arrow-counterclockwise"></i><span>Deshacer</span></button>
                            <button type="button" data-result-lp-op="reset"><i class="bi bi-arrow-repeat"></i><span>LP inicial</span></button>
                        </div>
                    </div>
                    <button class="btn btn-outline-info" type="button" data-result-standard-open><i class="bi bi-calculator"></i> Abrir calculadora auxiliar</button>
                    <div class="result-lp-history" data-result-lp-history>Sin movimientos registrados.</div>
                    <footer>
                        <button class="btn btn-outline-light" type="button" data-result-lp-close>Cerrar</button>
                        <button class="btn btn-primary fw-bold" type="button" data-result-lp-apply><i class="bi bi-check2-circle"></i> Aplicar LP al duelo</button>
                    </footer>
                </section>
            </div>
            <section class="lp-workspace-modal" data-result-standard-modal hidden role="dialog" aria-modal="true" aria-labelledby="lpAuxTitle">
                <button class="lp-workspace-backdrop" type="button" data-dialog-close aria-label="Cerrar calculadora auxiliar"></button>
                <article class="lp-workspace-panel">
                    <header><div><small>Cálculo auxiliar</small><h3 id="lpAuxTitle">Calculadora</h3></div><button class="btn btn-outline-light" type="button" data-dialog-close aria-label="Cerrar calculadora">×</button></header>
                    <section class="result-standard-calculator" data-result-standard-calculator>
                        <header><div><small>Calculo auxiliar</small><h4>Calculadora estandar</h4></div><span data-result-standard-expression>0</span><strong data-result-standard-display>0</strong></header>
                        <div class="result-standard-keypad">
                            <?php foreach (['7','8','9','divide','4','5','6','multiply','1','2','3','subtract','clear','0','equals','add'] as $key): ?>
                                <?php $label = ['divide' => '&divide;', 'multiply' => '&times;', 'subtract' => '&minus;', 'add' => '+', 'clear' => 'C', 'equals' => '='][$key] ?? $key; ?>
                                <button type="button" data-result-standard-key="<?php echo e($key); ?>"><?php echo $label; ?></button>
                            <?php endforeach; ?>
                        </div>
                        <button class="btn btn-outline-info" type="button" data-result-standard-use>Usar resultado como valor LP</button>
                    </section>
                </article>
            </section>
            <section class="lp-workspace-modal" data-result-lp-victory-modal hidden role="dialog" aria-modal="true" aria-labelledby="lpVictoryTitle">
                <button class="lp-workspace-backdrop" type="button" data-dialog-close aria-label="Volver sin otorgar victoria"></button>
                <article class="lp-workspace-panel">
                    <header><h3 id="lpVictoryTitle">Victoria por puntos de vida</h3><button class="btn btn-outline-light" type="button" data-dialog-close aria-label="Cerrar confirmación">×</button></header>
                    <p data-result-lp-victory-text></p>
                    <footer><button class="btn btn-outline-light" type="button" data-dialog-close data-dialog-focus>Seguir sin otorgar victoria</button><button class="btn btn-primary" type="button" data-result-lp-victory-confirm>Otorgar victoria y guardar</button></footer>
                </article>
            </section>
            <section class="result-duel-read-modal" data-result-duel-read-modal hidden role="dialog" aria-modal="true" aria-labelledby="resultDuelReadTitle">
                <button class="result-duel-read-backdrop" type="button" data-result-duel-read-close aria-label="Cerrar detalle"></button>
                <article class="result-duel-read-dialog">
                    <header>
                        <div><span class="tag">Detalle del duelo</span><h2 id="resultDuelReadTitle" data-result-duel-read-title>D1</h2><p data-result-duel-read-subtitle>Informacion persistida</p></div>
                        <button class="icon-button" type="button" data-result-duel-read-close title="Cerrar"><i class="bi bi-x-lg"></i></button>
                    </header>
                    <div class="result-duel-read-body" data-result-duel-read-body></div>
                    <footer><button class="btn btn-outline-light" type="button" data-result-duel-read-close><i class="bi bi-x-lg"></i> Cerrar</button></footer>
                </article>
            </section>
            <div class="modal-footer result-console-footer">
                <span class="result-save-state" data-result-save-state data-state="saved"><i class="bi bi-cloud-check"></i> Turnos guardados</span>
                <button class="btn btn-outline-light" type="button" data-bs-dismiss="modal">Cancelar</button>
                <button class="btn btn-outline-info fw-bold" type="button" data-modify-captured-match><i class="bi bi-pencil-square"></i> Modificar</button>
                <button class="btn btn-primary fw-bold" type="submit" data-result-capture-save><i class="bi bi-database-check"></i> Guardar cambios</button>
            </div>
        </form>
    </div>
</div>

<div class="modal fade dml-modal" id="qualifierDeckSheetModal" tabindex="-1" aria-hidden="true" data-qualifier-deck-sheet-modal>
    <div class="modal-dialog modal-dialog-centered modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <div>
                    <span class="tag">Ficha clasificatorio</span>
                    <h2 class="modal-title" data-qualifier-deck-title>Deck</h2>
                    <p data-qualifier-deck-meta></p>
                </div>
                <div class="modal-top-actions">
                    <button class="btn btn-primary fw-bold" type="button" data-qualifier-deck-profile><i class="bi bi-journal-richtext"></i> Ver ficha deck</button>
                    <button class="btn btn-outline-light" type="button" data-route="resultados" data-bs-dismiss="modal"><i class="bi bi-clipboard2-check"></i> Captura general</button>
                </div>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <div class="modal-body">
                <div class="result-dashboard">
                    <article class="panel result-kpi"><span>Programados</span><strong data-qualifier-deck-scheduled>0</strong><small>Combates asignados</small></article>
                    <article class="panel result-kpi"><span>Finalizados</span><strong data-qualifier-deck-played>0</strong><small>Jugados</small></article>
                    <article class="panel result-kpi"><span>Rivales</span><strong data-qualifier-deck-rivals>0</strong><small>Diferentes</small></article>
                    <article class="panel result-kpi"><span>Estado</span><strong data-qualifier-deck-status>0%</strong><small>Avance minimo</small></article>
                </div>
                <div class="table-responsive">
                    <table class="table app-table compact mobile-card-table qualifier-history-table">
                        <thead><tr><th>ID</th><th>Fecha</th><th>Ronda</th><th>Mesa</th><th>Rival</th><th>Condicion</th><th>Estado</th><th>Resultado</th><th></th></tr></thead>
                        <tbody data-qualifier-deck-matches></tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<section class="screen event-config-screen" data-screen="evento_ficha" hidden data-competition-config-modal>
        <form method="post" class="panel event-config-form" enctype="multipart/form-data">
            <input type="hidden" name="action" value="configure_competition">

            <input type="hidden" name="competition_id" data-competition-config-id>
            <input type="hidden" name="config_mode" value="save" data-competition-config-mode>
            <div class="event-config-hero">
                <div class="event-config-nav">
                    <button type="button" class="btn btn-outline-light btn-sm" data-route="torneos"><i class="bi bi-arrow-left"></i> Eventos</button>
                    <span class="tag" data-competition-config-status-chip>Pendiente configuracion</span>
                </div>
                <div class="event-config-title-block">
                    <span class="tag">Ficha del evento</span>
                    <div class="event-config-title-row">
                        <span class="event-config-logo" data-competition-config-logo-wrap><img src="" alt="Logo del evento" data-competition-config-logo></span>
                        <div>
                            <h2 class="modal-title" data-competition-config-title>Ficha del evento</h2>
                            <p data-competition-config-meta>Configuracion, decks, calendario y prerequisitos persistidos en base de datos.</p>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-body">
                <div class="event-config-stack">
                    <section class="panel compact-panel event-form-panel">
                        <div class="panel-head compact-head"><div><h3>Datos del evento</h3><p>Informacion principal persistida en base de datos.</p></div><div class="event-data-actions"><button class="btn btn-outline-info fw-bold" type="button" data-bs-toggle="modal" data-bs-target="#eventLogoModal"><i class="bi bi-image"></i> Logo</button><button class="btn btn-outline-warning fw-bold" type="button" data-event-programming-open><i class="bi bi-calendar2-week"></i> Programar</button><button class="btn btn-outline-info fw-bold" type="button" data-league-movement-open hidden><i class="bi bi-arrow-down-up"></i> Ascenso y descenso</button></div></div>
                        <div class="event-main-fields">
                            <?php $seasonFieldMode = "config"; require __DIR__ . "/includes/season-event-field.php"; ?>
                            <label class="span-2">Nombre del evento<input class="form-control" name="competition_name" data-competition-config-name placeholder="Nombre del evento"></label>
                            <label>Estado<select class="form-select" name="competition_status" data-competition-config-status><?php foreach ($competitionStatusOptions as $statusCode => $statusLabel): ?><option value="<?php echo e($statusCode); ?>" <?php echo $statusCode === 'resume' ? 'data-resume-option hidden' : ''; ?>><?php echo e($statusLabel); ?></option><?php endforeach; ?></select><small data-competition-status-note></small></label>
                            <label>Tipo de evento<select class="form-select" name="competition_type" data-competition-config-type data-locked-always="1" disabled><?php foreach ($competitionTypeOptions as $eventType => $eventTypeMeta): ?><option value="<?php echo e($eventType); ?>"><?php echo e((string) $eventTypeMeta['label']); ?></option><?php endforeach; ?></select><small>El tipo se define al crear el evento y no se edita desde la ficha.</small></label>
                            <label>Formato<select class="form-select" name="competition_event_ruleset" data-competition-config-format-label data-competition-config-ruleset><?php foreach ($rulesetSelectOptions as $rulesetCode => $rulesetLabel): ?><option value="<?php echo e($rulesetCode); ?>"><?php echo e($rulesetLabel); ?></option><?php endforeach; ?></select></label>
                            <input type="hidden" name="competition_division_id" data-competition-config-division>
                            <label>Modo de participantes<select class="form-select" name="competition_deck_pool" data-competition-config-deck-pool><?php foreach (['all_active'=>'Seleccionar decks del formato','division'=>'Todos los decks del formato'] as $poolCode => $poolLabel): ?><option value="<?php echo e($poolCode); ?>"><?php echo e($poolLabel); ?></option><?php endforeach; ?></select><small data-competition-deck-pool-note></small></label>
                            <label>Logo del evento<input class="form-control" type="file" name="competition_logo" accept="image/png,image/jpeg,image/webp,image/gif" data-competition-config-logo-input data-event-logo-upload><small>Opcional. Si subes uno nuevo, reemplaza el logo actual.</small></label>
                            <label class="toggle-line"><input type="checkbox" name="competition_remove_logo" value="1" data-competition-config-remove-logo> Quitar imagen actual del evento</label>
                            <div class="event-logo-gallery span-2" data-event-logo-gallery>
                                <div class="event-logo-gallery-head">
                                    <span>Logo de galeria</span>
                                    <small data-event-logo-gallery-count></small>
                                </div>
                                <input type="hidden" name="competition_gallery_logo" data-event-logo-gallery-input>
                                <div class="event-logo-gallery-grid" data-event-logo-gallery-grid></div>
                            </div>
                            <label>Reglamento / banlist<select class="form-select" name="competition_ruleset_version" data-competition-config-ruleset-version><option value="">Por definir</option><?php foreach ($rulesetVersionCatalog as $versionOption): ?><?php $versionValue = (string) (($versionOption['version_label'] ?? '') ?: ($versionOption['version'] ?? '')); if ($versionValue === '') { continue; } ?><option value="<?php echo e($versionValue); ?>" data-ruleset="<?php echo e((string) (($versionOption['ruleset_code'] ?? '') ?: ($versionOption['system'] ?? ''))); ?>"><?php echo e(ygo_ruleset_version_option_label($versionOption)); ?><?php echo !empty($versionOption['effective_on']) ? ' (' . e((string) $versionOption['effective_on']) . ')' : ''; ?></option><?php endforeach; ?></select></label>
                            <div class="span-2"><h3>Reglas elegidas al crear el evento</h3><p>Valores guardados del evento. Se muestran para consulta; la programacion conserva estas reglas.</p></div>
                            <label data-event-main-field="template">Modelo preconfigurado del evento<input class="form-control" type="text" readonly data-event-main-choice="template" aria-label="Modelo preconfigurado del evento"></label>
                            <label>Parametros de ejecucion guardados<input class="form-control" type="text" readonly data-event-main-choice="parameters" aria-label="Parametros de ejecucion guardados"></label>
                            <label data-event-main-field="execution">Sistema competitivo<input class="form-control" type="text" readonly data-event-main-choice="execution" aria-label="Sistema competitivo"></label>
                            <label data-event-main-field="division">Formato / division principal<input class="form-control" type="text" readonly data-event-main-choice="division" aria-label="Formato / division principal"></label>
                            <label data-event-main-field="divisions">Divisiones asociadas a la liga<input class="form-control" type="text" readonly data-event-main-choice="divisions" aria-label="Divisiones asociadas a la liga"></label>
                            <label data-event-main-field="match">Participacion del match<input class="form-control" type="text" readonly data-event-main-choice="match" aria-label="Participacion del match"></label>
                            <label data-event-main-field="best-of">Formato de partida / Mejor de<input class="form-control" type="text" readonly data-event-main-choice="best-of" aria-label="Formato de partida / Mejor de"></label>
                            <label data-event-main-field="advancement">Regla de avance<input class="form-control" type="text" readonly data-event-main-choice="advancement" aria-label="Regla de avance"></label>
                            <label data-event-main-field="draw">Politica de empate<input class="form-control" type="text" readonly data-event-main-choice="draw" aria-label="Politica de empate"></label>
                            <label data-event-main-field="rule">Regla del evento<input class="form-control" type="text" readonly data-event-main-choice="rule" aria-label="Regla del evento"></label>
                            <label class="span-2 event-notes-field">Notas del evento<textarea class="form-control" name="competition_rules" rows="3" data-competition-config-rules placeholder="Notas internas, sede o aclaraciones del organizador"></textarea></label>
                        </div>
                        <input type="hidden" name="competition_accent" data-competition-config-accent>
                        <div class="event-config-actions mt-3"><button class="btn btn-primary fw-bold" type="button" data-competition-config-submit="save"><i class="bi bi-save"></i> Guardar ficha</button></div>
                    </section>
                    <div class="event-config-actions mb-3"><button class="btn btn-outline-info" type="button" data-open-event-saved-reading><i class="bi bi-info-circle"></i> Ver configuración guardada</button></div>
                    <section class="panel compact-panel event-form-panel" data-event-programming-panel>
                        <div class="panel-head compact-head"><div><h3>Programacion</h3><p>La plataforma calcula la fecha de cada duelo a partir de estos datos.</p></div></div>
                        <div class="event-schedule-fields">
                            <label>Dia de inicio<input class="form-control" type="date" name="competition_start_on" data-competition-config-start min="<?php echo e(date('Y-m-d')); ?>" value="<?php echo e(date('Y-m-d')); ?>"></label>
                            <label>Tipo de match<select class="form-select" name="competition_match_format" data-competition-config-match><option value="ffa" data-participants="1">3-4 jugadores independientes</option><?php foreach ($matchTypeOptions as $matchType): ?><option value="<?php echo e((string) $matchType['code']); ?>" data-participants="<?php echo e((string) ($matchType['participants_per_side'] ?? 1)); ?>"><?php echo e((string) ($matchType['label'] ?? $matchType['code'])); ?></option><?php endforeach; ?></select></label>
                            <label data-competition-config-qualifier-duels-wrap>Combates requeridos por deck<select class="form-select" name="qualifier_duels_per_deck" data-competition-config-qualifier-duels><option value="2">2 combates</option><option value="3">3 combates</option><option value="4">4 combates</option><option value="5">5 combates</option></select><small>Define cuantos combates debe finalizar cada deck para completar el clasificatorio.</small></label>
                            <label data-competition-config-matches-per-day-wrap>Duelos por dia<select class="form-select" name="qualifier_matches_per_day" data-competition-config-matches-per-day><option value="1">1 duelo por dia</option><option value="2">2 duelos por dia</option><option value="3">3 duelos por dia</option><option value="4">4 duelos por dia</option><option value="5">5 duelos por dia</option></select><small>Maximo 5. Controla cuantas partidas quedan en la misma fecha.</small></label>
                            <label>Regla del evento<select class="form-select" name="competition_game_rule_id" data-competition-config-rule><option value="">Sin regla especial</option><?php foreach ($gameRules as $rule): ?><?php if (empty($rule['active']) || !in_array((string) ($rule['scope'] ?? ''), ['event', 'all_matches', 'general'], true)) { continue; } ?><option value="<?php echo e((string) ($rule['id'] ?? '')); ?>"><?php echo e((string) ($rule['name'] ?? 'Regla')); ?> | <?php echo e((string) ($rule['scope'] ?? 'general')); ?></option><?php endforeach; ?></select></label>
                            <label>Frecuencia de duelos<select class="form-select" name="competition_schedule_pattern" data-competition-config-pattern><?php foreach ($schedulePatternOptions as $patternCode => $patternLabel): ?><option value="<?php echo e($patternCode); ?>"><?php echo e($patternLabel); ?></option><?php endforeach; ?></select></label>
                        </div>
                        <article class="event-rule-applied-card" data-competition-rule-card>
                            <div>
                                <span>Regla aplicada a los duelos</span>
                                <strong data-competition-rule-name>Sin regla especial</strong>
                                <small data-competition-rule-code>Se usara la validacion compatible por defecto.</small>
                            </div>
                            <p data-competition-rule-effect>Selecciona una regla para ver que validaciones se aplicaran al cargar resultados.</p>
                            <button class="btn btn-outline-info btn-sm" type="button" data-open-event-rule-modal><i class="bi bi-list-check"></i> Ver detalle</button>
                        </article>
                        <div class="event-config-actions event-program-actions">
                            <button class="btn btn-primary fw-bold" type="button" data-competition-config-submit="save_schedule"><i class="bi bi-save"></i> Guardar programacion</button>
                            <button class="btn btn-warning fw-bold" type="button" data-competition-config-submit="schedule_qualifier" data-competition-config-qualifier-action><i class="bi bi-diagram-3"></i> Programar duelos clasificatorios</button>
                            <button class="btn btn-warning fw-bold" type="button" data-competition-config-submit="schedule_cup" data-competition-config-cup-action hidden><i class="bi bi-trophy"></i> Programar copa</button>
                            <button class="btn btn-outline-info fw-bold" type="button" data-open-event-ranking-modal data-requires-active-event disabled><i class="bi bi-table"></i> Ver tabla general</button>
                            <button class="btn btn-outline-info fw-bold" type="button" data-open-event-schedule-modal data-requires-active-event disabled><i class="bi bi-diagram-3"></i> Ver duelos programados</button>
                        </div>
                    </section>
                </div>
                <div class="modal fade dml-modal" id="eventLogoModal" tabindex="-1" aria-hidden="true">
                    <div class="modal-dialog modal-dialog-centered modal-lg modal-dialog-scrollable"><div class="modal-content">
                        <div class="modal-header"><div><span class="tag">Identidad visual</span><h2 class="modal-title">Logo del evento</h2><p>Sube una imagen o elige una de la galeria.</p></div><button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Cerrar"></button></div>
                        <div class="modal-body"><div class="event-logo-modal-fields" data-event-logo-modal-fields></div><button class="btn btn-outline-info mt-3" type="button" data-open-logo-history="competition"><i class="bi bi-clock-history"></i> Ver historial de logos</button></div>
                        <div role="status" aria-live="polite" class="px-3" data-event-logo-message></div>
                        <div class="modal-footer"><button class="btn btn-outline-light" type="button" data-bs-dismiss="modal">Cancelar</button><button class="btn btn-primary" type="button" data-event-logo-save>Guardar logo</button></div>
                    </div></div>
                </div>
                <div class="modal fade dml-modal" id="eventProgrammingModal" tabindex="-1" aria-hidden="true">
                    <div class="modal-dialog modal-dialog-centered modal-xl modal-dialog-scrollable"><div class="modal-content">
                        <div class="modal-header"><div><span class="tag">Programacion</span><h2 class="modal-title">Programar evento</h2><p>Estos datos se guardan al programar y organizan los cruces automaticamente.</p></div><button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Cerrar"></button></div>
                        <div class="modal-body" data-event-programming-modal-body></div>
                    </div></div>
                </div>
                <div class="modal fade dml-modal event-decks-modal" id="competitionDecksModal" tabindex="-1" aria-hidden="true" data-competition-decks-modal>
                    <div class="modal-dialog modal-dialog-centered modal-lg modal-dialog-scrollable">
                        <div class="modal-content">
                            <div class="modal-header">
                                <div>
                                    <span class="tag">Participantes</span>
                                    <h2 class="modal-title" data-participant-modal-title>Decks participantes</h2>
                                    <p>Busca, agrega o quita decks activos sin repetir seleccion.</p>
                                </div>
                                <span class="status-chip" data-competition-config-count>0 seleccionados</span>
                                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                            </div>
                            <div class="modal-body">
                                <section class="panel compact-panel event-deck-panel">
                                    <div class="deck-picker-tools">
                                        <div class="participant-division-buttons" data-participant-division-buttons aria-label="Divisiones de la liga" hidden></div>
                                        <input class="form-control" type="search" placeholder="Escribir deck..." data-competition-deck-search>
                                        <div class="deck-picker-row">
                                            <select class="form-select" aria-label="Deck y formato" aria-describedby="participantFormatRestriction" data-competition-deck-picker></select>
                                            <button class="btn btn-primary" type="button" data-competition-deck-add><i class="bi bi-plus-circle"></i> Agregar</button>
                                        </div>
                                        <p class="note mb-0" id="participantFormatRestriction" role="status" aria-live="polite" data-competition-format-restriction></p>
                                    </div>
                                    <div class="table-responsive selected-deck-table-wrap">
                                        <table class="table app-table compact selected-deck-table mobile-card-table">
                                            <thead><tr><th>#</th><th>Deck</th><th>Equipo</th><th>Formato</th><th>Accion</th></tr></thead>
                                            <tbody data-competition-selected-decks></tbody>
                                        </table>
                                    </div>
                                    <div class="deck-hidden-inputs" data-competition-hidden-decks hidden>
                                        <?php foreach ($allDecks as $deck): ?>
                                            <label data-config-deck-row data-division="<?php echo e((string) ($deck['division'] ?? '')); ?>">
                                                <input type="checkbox" name="competition_deck_ids[]" value="<?php echo e((string) ($deck['id'] ?? '')); ?>" data-competition-config-deck>
                                            </label>
                                        <?php endforeach; ?>
                                    </div>
                                </section>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal fade dml-modal qualifier-load-modal" id="qualifierLoadModal" tabindex="-1" aria-hidden="true" data-qualifier-load-modal>
                    <div class="modal-dialog modal-dialog-centered modal-xl">
                        <div class="modal-content">
                            <div class="modal-header">
                                <div>
                                    <span class="tag">Cargar clasificatorio</span>
                                    <h2 class="modal-title">Clasificatorios finalizados</h2>
                                    <p>Selecciona un rango par de posiciones para cargar decks a esta copa o torneo.</p>
                                </div>
                                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                            </div>
                            <div class="modal-body">
                                <div class="qualifier-load-layout">
                                    <section class="qualifier-load-table">
                                        <input class="form-control" type="search" placeholder="Buscar clasificatorio, formato o fecha..." data-qualifier-load-search>
                                        <div class="table-responsive">
                                            <table class="table app-table compact">
                                                <thead><tr><th>Clasificatorio</th><th>Formato</th><th>Finalizacion</th><th>Participantes</th><th>Ranking</th><th>Accion</th></tr></thead>
                                                <tbody data-qualifier-load-list></tbody>
                                            </table>
                                        </div>
                                    </section>
                                    <section class="qualifier-load-panel">
                                        <h3 data-qualifier-load-title>Selecciona un clasificatorio</h3>
                                        <p data-qualifier-load-meta>Solo se listan clasificatorios finalizados con ranking calculado.</p>
                                        <div class="two-col">
                                            <label>Desde posicion<input class="form-control" type="number" min="1" step="1" data-qualifier-load-from value="1"></label>
                                            <label>Hasta posicion<input class="form-control" type="number" min="1" step="1" data-qualifier-load-to value="4"></label>
                                        </div>
                                        <p class="note" data-qualifier-load-note>El total debe ser par y minimo 4.</p>
                                        <div class="qualifier-preview-summary" data-qualifier-load-preview></div>
                                        <button class="btn btn-warning fw-bold w-100" type="button" data-qualifier-load-apply disabled><i class="bi bi-upload"></i> Cargar decks seleccionados</button>
                                    </section>
                                </div>
                                <section class="qualifier-ranking-section">
                                    <div class="panel-head compact-head">
                                        <div><h3>Ranking completo del clasificatorio</h3><p>Todas las posiciones disponibles para elegir el rango exacto.</p></div>
                                    </div>
                                    <div class="table-responsive qualifier-ranking-table-wrap">
                                        <table class="table app-table compact">
                                            <thead><tr><th>Posicion</th><th>Deck</th><th>Equipo</th><th>Formato</th><th>PJ</th><th>Pts</th></tr></thead>
                                            <tbody data-qualifier-load-ranking></tbody>
                                        </table>
                                    </div>
                                </section>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer event-config-footer-actions">
                <div class="event-footer-left">
                    <button class="btn btn-outline-light" type="button" data-route="torneos"><i class="bi bi-arrow-left"></i> Volver</button>
                </div>
                <div class="event-footer-tools">
                    <button class="btn btn-outline-warning fw-bold" type="button" data-qualifier-load-open hidden><i class="bi bi-upload"></i> Cargar clasificatorio</button>
                    <button class="btn btn-outline-info fw-bold" type="button" data-competition-decks-open><i class="bi bi-collection"></i> Decks participantes</button>
                    <button class="btn btn-outline-info fw-bold" type="button" data-open-team-lineups hidden>Equipos y alineaciones</button>
                    <button class="btn btn-outline-info fw-bold" type="button" data-open-team-matches hidden>Resultados por equipo</button>
                    <button class="btn btn-outline-info fw-bold" type="button" data-open-event-admission>Fuentes y clasificados</button>
                    <button class="btn btn-outline-warning fw-bold" type="button" data-open-event-recovery hidden>Corregir fase anterior</button>
                    <button class="btn btn-outline-info fw-bold" type="button" data-open-event-rule-modal><i class="bi bi-list-check"></i> Ver regla aplicada</button>
                    <button class="btn btn-outline-light fw-bold" type="button" data-open-event-audit><i class="bi bi-clock-history"></i> Auditoría</button>
                </div>
                <div class="event-action-preview" data-competition-action-preview>
                    <strong>Lectura preventiva</strong>
                    <span>Selecciona un evento activo para ver si puede avanzar, aplicar divisiones o mover equipos.</span>
                </div>
                <div class="event-footer-main">
                    <button class="btn btn-outline-warning fw-bold" type="button" data-competition-action-submit="advance_competition" data-requires-active-event hidden disabled><i class="bi bi-chevron-double-right"></i> Crear siguiente fase</button>
                    <button class="btn btn-outline-warning fw-bold" type="button" data-competition-action-submit="apply_qualifier_divisions" data-requires-active-event disabled><i class="bi bi-diagram-3"></i> Aplicar divisiones</button>
                    <button class="btn btn-outline-warning fw-bold" type="button" data-competition-action-submit="apply_league_movements" data-requires-active-event hidden disabled><i class="bi bi-arrow-down-up"></i> Aplicar ascensos/descensos</button>
                    <button class="btn btn-outline-danger fw-bold" type="button" data-competition-delete-open disabled><i class="bi bi-trash"></i> Eliminar evento</button>
                    <button class="btn btn-outline-warning fw-bold" type="button" data-competition-finalize-submit hidden disabled><i class="bi bi-flag-fill"></i> Finalizar evento</button>
                    <button class="btn btn-primary fw-bold" type="button" data-competition-config-submit="start"><i class="bi bi-play-circle"></i> Comenzar</button>
                </div>
            </div>

            <div class="modal fade dml-modal" id="leagueMovementModal" tabindex="-1" aria-labelledby="leagueMovementTitle" aria-hidden="true" data-league-movement-modal>
                <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable"><div class="modal-content">
                    <div class="modal-header"><div><h2 class="modal-title" id="leagueMovementTitle">Ascenso y descenso</h2><p data-league-movement-summary></p></div><button class="btn-close btn-close-white" type="button" data-bs-dismiss="modal" aria-label="Cerrar"></button></div>
                    <div class="modal-body"><p class="note">Configura un mismo numero de ascensos y descensos entre divisiones consecutivas. La primera division solo desciende y la ultima solo asciende. Los movimientos se aplican al finalizar la liga con resultados.</p><div data-league-movement-rows></div><label class="toggle-line mt-3"><input type="checkbox" name="competition_lock_division_moves" value="1" data-competition-config-lock-moves> Bloquear movimientos entre divisiones</label></div>
                    <div class="modal-footer"><button class="btn btn-outline-light" type="button" data-bs-dismiss="modal">Cerrar</button><button class="btn btn-primary" type="button" data-competition-config-submit="save">Guardar ascenso y descenso</button></div>
                </div></div>
            </div>
        </form>
</section>

<div class="modal fade dml-modal" id="deleteCompetitionModal" tabindex="-1" aria-hidden="true" data-delete-competition-modal>
    <div class="modal-dialog modal-dialog-centered">
        <form method="post" class="modal-content">
            <input type="hidden" name="action" value="delete_competition">
            <input type="hidden" name="competition_id" data-delete-competition-id>
            <div class="modal-header">
                <div>
                    <span class="tag danger">Eliminar evento</span>
                    <h2 class="modal-title">Eliminar evento completo</h2>
                    <p data-delete-competition-meta>Esta accion borra el evento y sus datos asociados de la base de datos.</p>
                </div>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <div class="modal-body">
                <p class="note">Solo se pueden eliminar eventos que no esten activos, finalizados ni anulados. Para confirmar escribe <b>eliminar</b>.</p>
                <input class="form-control" name="delete_confirmation" placeholder="escribe eliminar" autocomplete="off" data-delete-competition-confirmation>
            </div>
            <div class="modal-footer">
                <button class="btn btn-outline-light" type="button" data-bs-dismiss="modal">Cancelar</button>
                <button class="btn btn-danger fw-bold" type="submit" data-delete-competition-submit disabled><i class="bi bi-trash"></i> Eliminar definitivamente</button>
            </div>
        </form>
</div>
</div>

<div class="modal fade dml-modal" id="eventRankingModal" tabindex="-1" aria-hidden="true" data-event-ranking-modal>
    <div class="modal-dialog modal-dialog-centered modal-xl modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <div>
                    <span class="tag" data-event-ranking-label>Tabla general</span>
                    <h2 class="modal-title" data-event-ranking-modal-title>Tabla del evento</h2>
                    <p data-event-ranking-modal-meta>Posiciones calculadas desde resultados persistidos.</p>
                </div>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <div class="modal-body">
                <div class="ranking-phase-tabs" data-event-ranking-phase-tabs></div>
                <div class="event-ranking-list event-modal-list" data-competition-config-ranking></div>
            </div>
        </div>
    </div>
</div>

<div class="modal fade dml-modal" id="eventSavedReadingModal" tabindex="-1" aria-labelledby="event-saved-reading-title" aria-hidden="true">
 <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable"><div class="modal-content">
  <div class="modal-header"><div><span class="tag">Configuración del evento</span><h2 class="modal-title" id="event-saved-reading-title">Configuración guardada del evento</h2><p data-event-saved-reading-name></p></div><button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Cerrar"></button></div>
  <div class="modal-body"><p>Opciones guardadas del evento y su significado. Los cambios de la ficha aparecen aquí después de guardarlos.</p><div data-event-saved-reading style="display:grid;grid-template-columns:repeat(auto-fit,minmax(min(100%,260px),1fr));gap:.85rem"></div></div>
  <div class="modal-footer"><button class="btn btn-outline-light" type="button" data-bs-dismiss="modal">Cerrar</button></div>
 </div></div>
</div>

<div class="modal fade dml-modal" id="eventRuleModal" tabindex="-1" aria-hidden="true" data-event-rule-modal>
    <div class="modal-dialog modal-dialog-centered modal-xl modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <div>
                    <span class="tag">Regla aplicada</span>
                    <h2 class="modal-title" data-event-rule-modal-title>Regla del evento</h2>
                    <p data-event-rule-modal-meta>Validaciones que se usaran al cargar resultados de los duelos.</p>
                </div>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <div class="modal-body">
                <div class="event-rule-detail" data-event-rule-modal-body></div>
            </div>
        </div>
    </div>
</div>

<div class="modal fade dml-modal" id="eventScheduleModal" tabindex="-1" aria-hidden="true" data-event-schedule-modal>
    <div class="modal-dialog modal-dialog-centered modal-xl modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <div>
                    <span class="tag">Duelos programados</span>
                    <h2 class="modal-title" data-event-schedule-modal-title>Calendario del evento</h2>
                    <p data-event-schedule-modal-meta>Cruces y fechas tentativas guardadas en base de datos.</p>
                </div>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <div class="modal-body">
                <div class="event-dependency-list event-modal-list" data-competition-config-pending-matches></div>
            </div>
        </div>
    </div>
</div>

<div class="modal fade dml-modal" id="confirmStartQualifierModal" tabindex="-1" aria-hidden="true" data-confirm-start-qualifier-modal>
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <div>
                    <span class="tag">Confirmacion</span>
                    <h2 class="modal-title" data-confirm-start-title>Comenzar evento</h2>
                    <p data-confirm-start-subtitle>Al comenzar se bloqueara la configuracion del evento.</p>
                </div>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <div class="modal-body">
                <p class="note" data-confirm-start-message>Esta seguro de comenzar el evento?</p>
            </div>
            <div class="modal-footer">
                <button class="btn btn-outline-light" type="button" data-bs-dismiss="modal">Cancelar</button>
                <button class="btn btn-primary fw-bold" type="button" data-confirm-start-qualifier-submit><i class="bi bi-play-circle"></i> Si, comenzar</button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade dml-modal" id="confirmScheduleQualifierModal" tabindex="-1" aria-hidden="true" data-confirm-schedule-qualifier-modal>
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <div>
                    <span class="tag">Programacion</span>
                    <h2 class="modal-title">Generar duelos y fechas</h2>
                    <p data-confirm-schedule-qualifier-meta>Se creara el calendario tentativo del clasificatorio.</p>
                </div>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <div class="modal-body">
                <div class="rule-note warn">
                    <i class="bi bi-exclamation-triangle-fill"></i>
                    <p>
                        <strong>Confirmar programacion</strong><br>
                        <span data-confirm-schedule-qualifier-message>Si continuas, se generaran duelos y fechas tentativas en base de datos.</span>
                    </p>
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn btn-outline-light" type="button" data-bs-dismiss="modal">Cancelar</button>
                <button class="btn btn-warning fw-bold" type="button" data-confirm-schedule-qualifier-submit><i class="bi bi-diagram-3"></i> Si, generar</button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade dml-modal" id="calendarDayModal" tabindex="-1" aria-hidden="true" data-calendar-day-modal>
    <div class="modal-dialog modal-dialog-centered modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <div>
                    <span class="tag">Agenda del dia</span>
                    <h2 class="modal-title" data-calendar-day-modal-title>Fecha</h2>
                    <p data-calendar-day-modal-meta></p>
                </div>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <div class="modal-body">
                <div class="calendar-day-modal-list" data-calendar-day-modal-events></div>
            </div>
        </div>
    </div>
</div>

<div class="modal fade dml-modal" id="friendlyMatchModal" tabindex="-1" aria-hidden="true" data-friendly-match-modal>
    <div class="modal-dialog modal-xl modal-dialog-centered">
        <div class="modal-content">
            <form method="post" class="modal-form">
                <div class="modal-header">
                    <div>
                        <span class="tag">Amistoso</span>
                        <h2 class="modal-title">Crear combate amistoso</h2>
                        <p>Combate independiente sin evento asociado. Se guardara en BD y aparecera en calendario.</p>
                    </div>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="action" value="create_match">
                    <input type="hidden" name="manual_competition_id" value="">
                    <input type="hidden" name="manual_phase" value="friendly">
                    <input type="hidden" name="manual_match_format" value="1v1">
                    <input type="hidden" name="manual_division_id" value="" data-friendly-division-value>
                    <input type="hidden" name="manual_ruleset_code" value="" data-friendly-ruleset-value>
                    <input type="hidden" name="manual_best_of" value="5">
                    <input type="hidden" name="manual_participants_per_side" value="1">
                    <input type="hidden" name="manual_round" value="1">
                    <input type="hidden" name="manual_table" value="1">
                    <div class="config-grid compact">
                        <label>Formato<select class="form-select" data-friendly-division><option value="">General (todos los decks activos)</option><?php if ($formatSelectOptions): ?><optgroup label="Formatos"><?php foreach ($formatSelectOptions as $formatCode => $formatLabel): ?><option value="ruleset:<?php echo e((string) $formatCode); ?>"><?php echo e((string) $formatLabel); ?></option><?php endforeach; ?></optgroup><?php endif; ?><?php if ($displayDivisions): ?><optgroup label="Divisiones"><?php foreach ($displayDivisions as $division): ?><option value="division:<?php echo e((string) $division['id']); ?>"><?php echo e($division['short']); ?></option><?php endforeach; ?></optgroup><?php endif; ?></select><small>Al elegir un formato solo se cargan sus decks y equipos asociados.</small></label>
                        <label>Fecha<input class="form-control" type="date" name="manual_scheduled_on" value="<?php echo e((new DateTimeImmutable())->format('Y-m-d')); ?>" required></label>
                        <label>Hora<input class="form-control" type="time" name="manual_starts_at" value="19:00" required></label>
                        <label>Sede<input class="form-control" name="manual_venue" value="Station X Tulua"></label>
                    </div>
                    <label class="mt-3">Regla para este amistoso<select class="form-select" name="manual_game_rule_id"><option value="">Usar regla compatible por defecto</option><?php foreach ($gameRules as $rule): ?><?php if (empty($rule['active']) || !in_array((string) ($rule['scope'] ?? ''), ['all_matches', 'friendly', 'match', 'general'], true)) { continue; } ?><option value="<?php echo e((string) ($rule['id'] ?? '')); ?>"><?php echo e((string) ($rule['name'] ?? 'Regla')); ?> | <?php echo e((string) ($rule['scope'] ?? 'general')); ?></option><?php endforeach; ?></select></label>
                    <div class="two-col mt-3">
                        <label>Deck A<select class="form-select" name="manual_deck_a_id" required data-friendly-deck-select><?php foreach ($allDecks as $deckIndex => $deck): ?><option value="<?php echo e((string) $deck['id']); ?>" data-division-id="<?php echo e((string) ($deck['division_id'] ?? '')); ?>" data-format-codes="<?php echo e(implode(',', $deck['format_codes'] ?? [])); ?>" data-team-id="<?php echo e((string) ($deck['team_id'] ?? '')); ?>"><?php echo e($deck['team'] . ' - ' . $deck['name']); ?></option><?php endforeach; ?></select></label>
                        <label>Deck B<select class="form-select" name="manual_deck_b_id" required data-friendly-deck-select><?php foreach ($allDecks as $deckIndex => $deck): ?><option value="<?php echo e((string) $deck['id']); ?>" data-division-id="<?php echo e((string) ($deck['division_id'] ?? '')); ?>" data-format-codes="<?php echo e(implode(',', $deck['format_codes'] ?? [])); ?>" data-team-id="<?php echo e((string) ($deck['team_id'] ?? '')); ?>" <?php echo $deckIndex === 1 ? 'selected' : ''; ?>><?php echo e($deck['team'] . ' - ' . $deck['name']); ?></option><?php endforeach; ?></select></label>
                    </div>
                    <p class="form-hint mt-2" data-friendly-pool-summary></p>
                    <label class="mt-3">Motivo / notas<textarea class="form-control" name="manual_notes" rows="3" placeholder="Practica, exhibicion, desempate informal, reto comunitario..."></textarea></label>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-light" data-bs-dismiss="modal">Cancelar</button>
                    <button class="btn btn-primary fw-bold" type="submit"><i class="bi bi-calendar-plus"></i> Crear amistoso</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade" id="duelTimerConfirmModal" tabindex="-1" aria-hidden="true" data-duel-timer-confirm-modal>
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <div><span class="tag">Inicio del duelo</span><h2 class="modal-title" data-duel-start-confirm-title>¿Comenzar el duelo?</h2></div>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <div class="modal-body"><p data-duel-start-confirm-copy>Confirma el inicio y decide si usaras el limite opcional de 20 minutos. Esta decision quedara bloqueada durante el duelo.</p></div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-light" data-duel-start-without-timer>Comenzar sin reloj</button>
                <button type="button" class="btn btn-primary fw-bold" data-duel-start-with-timer><i class="bi bi-play-fill"></i> Comenzar con 20 minutos</button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade dml-modal" id="deleteFriendlyMatchModal" tabindex="-1" aria-hidden="true" data-delete-friendly-modal>
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form method="post" data-delete-friendly-form>
                <input type="hidden" name="action" value="delete_manual_friendly">
                <input type="hidden" name="friendly_match_id" data-delete-friendly-match-id>
                <div class="modal-header">
                    <div><span class="tag danger">Eliminar amistoso</span><h2 class="modal-title">¿Eliminar este combate?</h2></div>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>
                <div class="modal-body">
                    <p>Se eliminara el amistoso y también su resultado, turnos, LP, estado en vivo y snapshots asociados.</p>
                    <strong data-delete-friendly-label>Combate amistoso</strong>
                </div>
                <div class="modal-footer">
                    <button class="btn btn-outline-light" type="button" data-bs-dismiss="modal">Cancelar</button>
                    <button class="btn btn-danger fw-bold" type="submit"><i class="bi bi-trash3"></i> Eliminar amistoso</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade dml-modal" id="calendarEventModal" tabindex="-1" aria-hidden="true" data-calendar-modal>
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <div>
                    <span class="tag" data-calendar-modal-type>Evento</span>
                    <h2 class="modal-title" data-calendar-modal-title>Detalle del calendario</h2>
                    <p data-calendar-modal-meta></p>
                </div>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <div class="modal-body">
                <div class="calendar-modal-grid">
                    <section>
                        <h3>Combates</h3>
                        <div data-calendar-modal-matches></div>
                    </section>

                </div>
            </div>
        </div>
    </div>
</div>

<?php require __DIR__.'/includes/match-editor-view.php'; ?>
<?php require __DIR__.'/includes/event-audit-view.php'; ?>

<div class="modal fade dml-modal" id="logoHistoryModal" tabindex="-1" aria-hidden="true" data-logo-history-modal>
    <div class="modal-dialog modal-dialog-centered modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <div><span class="tag">Galeria</span><h2 class="modal-title" data-logo-history-title>Historial de logos</h2><p>Del mas reciente al mas antiguo. Elige uno solo si deseas reemplazar el actual.</p></div>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <div class="modal-body"><div class="logo-history-grid" data-logo-history-grid></div></div>
        </div>
    </div>
</div>

<footer class="app-footer">
    <span>YUGIOH MD - Station X Tulua</span>
    <span>PHP + Bootstrap 5 + MySQL editable</span>
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" defer></script>
<script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.15/index.global.min.js" defer></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/three.js/r128/three.min.js" defer></script>
<script src="https://cdn.jsdelivr.net/npm/qrcodejs@1.0.0/qrcode.min.js" defer></script>
<script src="./assets/ui-labels.js?v=20261026-labels-101" defer></script>
<script src="./assets/event-admission.js?v=20261018-ranking-47" defer></script>
<script src="./assets/free-for-all.js?v=20261018-ffa-47" defer></script>
<script src="./assets/event-teams.js?v=20261018-ranking-47" defer></script>
<script src="./assets/event-recovery.js?v=20261018-ranking-47" defer></script>
<script src="./assets/event-models.js?v=20261018-ranking-47" defer></script>
<script src="./assets/modules/statistics/global-ranking.js?v=20261117-global-catalog"></script>
<script src="./assets/modules/statistics/workspace.js?v=20261117-global-catalog"></script>
<script src="./assets/statistics-v8.js?v=20261117-global-catalog"></script>
<script src="./assets/modules/statistics/live.js?v=20261113-stats-live-180"></script>
<script src="./assets/library-search.js?v=20261018-library-48" defer></script>
<script src="./assets/library.js?v=20261018-library-48" defer></script>
<link href="./assets/modules/cup-bracket/navigation.css?v=20261108-cup-open-155" rel="stylesheet">
<script src="./assets/modules/cup-bracket/navigation.js?v=20261108-cup-open-155" defer></script>
<script src="./assets/cup-tree.js?v=20261108-cup-open-155" defer></script>
<script src="./assets/deck-events.js?v=20261024-event-filters-90" defer></script>
<script src="./assets/daily-duels.js?v=20261026-labels-101" defer></script>
<script src="./assets/card-inspector.js?v=20261028-inspector-111" defer></script>
<script src="./assets/deck-builder.js?v=20261028-inspector-111" defer></script>
<script src="./assets/deck-history.js?v=20261024-history-92" defer></script>
<script src="./assets/modules/deck-guide/strategy-workspace.js?v=20261030-depth-122" defer></script>
<script src="./assets/modules/deck-guide/deck-deliveries.js?v=20261102-library-125" defer></script>
<script src="./assets/modules/deck-export/export.js?v=20261102-export-127" defer></script>
<link rel="stylesheet" href="./assets/modules/deck-data/styles.css?v=20261117-data-1">
<script src="./assets/modules/deck-data/ui.js?v=20261117-data-folder-2" defer></script>
<script src="./assets/modules/deck-guide/deck-study.js?v=20261102-library-125" defer></script>
<script src="./assets/modules/deck-guide/learning-guide.js?v=20261102-library-125" defer></script>
<script src="./assets/result-desktop.js?v=20261109-result-button-158" defer></script>
<script src="./assets/participant-labels.js?v=20261027-participants-103" defer></script>
<script src="./assets/lp-workspace.js?v=20261027-lp-105" defer></script>
<script src="./assets/lp-sound.js?v=20261027-sound-106" defer></script>
<script src="./assets/storage-maintenance.js?v=20261028-cleanup-113" defer></script>
<script src="./assets/news.js?v=20261028-news-115" defer></script>
<script src="./assets/modules/play-calculator/engine.js?v=20261116-actions-1" defer></script>
<script src="./assets/modules/play-calculator/hand-practice.js?v=20261113-hand-practice-170" defer></script>
<script src="./assets/modules/play-calculator/storage.js?v=20261103-board-129" defer></script>
<script src="./assets/modules/play-calculator/card-menu.js?v=20261116-actions-1" defer></script>
<script src="./assets/modules/play-calculator/planner.js?v=20261116-actions-1" defer></script><script src="./assets/modules/play-calculator/ui.js?v=20261116-actions-1" defer></script>
<script src="./assets/modules/combo-creator/model.js?v=20261103-combos-133" defer></script>
<script src="./assets/modules/combo-creator/step-lesson.js?v=20261113-step-lessons-173" defer></script>
<script src="./assets/modules/combo-creator/documented-import.js?v=20261116-folder-import-1" defer></script>
<script src="./assets/modules/play-calculator/workspace.js?v=20261116-actions-1" defer></script>
<script src="./assets/modules/card-inspector/model.js?v=20261116-readable-3" defer></script>
<script src="./assets/modules/card-inspector/ui.js?v=20261116-readable-3" defer></script>
<script src="./assets/modules/combo-creator/ui.js?v=20261116-deck-picker-1" defer></script>
<script src="./assets/modules/match-sheet/navigation.js?v=20261104-match-sheet-134" defer></script>
<script src="./assets/modules/match-sheet/entity-links.js?v=20261117-entity-links" defer></script>
<link href="./assets/modules/deck-guide/checked-study.css?v=20261104-checked-study-137" rel="stylesheet">
<link href="./assets/modules/deck-guide/study-sections.css?v=20261107-study-eleven-139" rel="stylesheet">
<script src="./assets/modules/deck-guide/study-eleven.js?v=20261107-study-ten-143" defer></script>
<script src="./assets/modules/deck-guide/study-sections.js?v=20261107-study-ten-143" defer></script>
<link href="./assets/modules/deck-guide/curated-study.css?v=20261113-combo-library-164" rel="stylesheet">
<script src="./assets/modules/deck-guide/photo-viewer.js?v=20261108-reader-148" defer></script>
<link href="./assets/modules/deck-guide/endboard.css?v=20261113-combo-library-164" rel="stylesheet">
<script src="./assets/modules/deck-guide/endboard.js?v=20261113-combo-library-164" defer></script>
<link href="./assets/modules/deck-guide/curated-visual.css?v=20261113-combo-library-164" rel="stylesheet">
<link href="./assets/modules/deck-guide/delivery-extensions.css?v=20261114-delivery-v12-186" rel="stylesheet">
<script src="./assets/modules/deck-guide/delivery-extensions.js?v=20261116-folder-import-1" defer></script>
<script src="./assets/modules/deck-guide/curated-visual.js?v=20261114-delivery-v12-186" defer></script>
<script src="./assets/modules/deck-guide/functional-role.js?v=20261116-folder-import-1" defer></script>
<script src="./assets/modules/deck-guide/card-detail.js?v=20261115-card-detail-196" defer></script>
<script src="./assets/modules/deck-guide/curated-study.js?v=20261117-folder-delivery-v37-complete" defer></script>
<script src="./assets/modules/combo-creator/editorial-library.js?v=20261113-combo-library-164" defer></script>
<script src="./assets/modules/deck-guide/checked-study.js?v=20261108-curated-147" defer></script>
<script src="./assets/modules/deck-guide/content-state.js?v=20261113-combo-library-164" defer></script>
<script src="./assets/modules/dashboard/ranking-scopes.js?v=20261107-ranking-divisions-146" defer></script>
<script src="./assets/modules/navigation/global-search.js?v=20261113-global-search-167" defer></script>
<link rel="stylesheet" href="./assets/modules/deck-guide/league-position.css?v=20261113-match-positions-183">
<link rel="stylesheet" href="./assets/modules/deck-attributes/style.css?v=20261115-card-detail-196">
<script src="./assets/modules/deck-attributes/model.js?v=20261115-card-detail-196"></script>
<script src="./assets/modules/deck-attributes/ui.js?v=20261115-card-detail-196"></script>
<script src="./assets/modules/deck-guide/league-position.js?v=20261113-match-positions-182" defer></script>
<script src="./assets/app.js?v=20261117-match-entities" defer></script>
<script src="./assets/match-editor.js?v=20261027-participants-103" defer></script>
<script src="./assets/event-audit.js?v=20261021-audit-55" defer></script>
<script src="./assets/seasons.js?v=20261016-season-editor-4" defer></script>
</body>
</html>






