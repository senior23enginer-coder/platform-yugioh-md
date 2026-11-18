<?php
declare(strict_types=1);

// Legacy fallback catalog and presentation defaults; no persistence.
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
