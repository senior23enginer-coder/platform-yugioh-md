<?php
declare(strict_types=1);

/**
 * Editorial starting point for registered decks. Observed indicators are never
 * stored here: they continue to come from finalized matches in the database.
 */
function ygo_deck_analysis_phase_templates(): array
{
    return [
        'combo' => [
            'opening_plan' => 'Convertir el acceso inicial en una secuencia estable, proteger los puntos de extension y reservar una continuacion.',
            'mid_game_plan' => 'Reconstruir con los recursos conservados y transformar cada carta disponible en presion o interrupcion.',
            'late_game_plan' => 'Cerrar antes de agotar el motor; si el duelo se alarga, priorizar las piezas que reciclan recursos.',
            'going_first_plan' => 'Establecer interacciones variadas sin comprometer toda la mano ante una sola respuesta.',
            'going_second_plan' => 'Forzar la primera interrupcion, abrir una ruta alternativa y convertir la ruptura de mesa en dano decisivo.',
            'side_deck_strategy' => 'Reducir piezas redundantes y sumar respuestas al tipo de interrupcion o mesa que presenta el rival.',
        ],
        'midrange' => [
            'opening_plan' => 'Iniciar con la linea de mayor eficiencia y conservar recursos para el siguiente intercambio.',
            'mid_game_plan' => 'Ganar intercambios uno por uno, mantener presencia y reutilizar el motor principal.',
            'late_game_plan' => 'Aprovechar la ventaja acumulada y cerrar cuando el rival ya no pueda reconstruir.',
            'going_first_plan' => 'Preparar una mesa flexible con interaccion y seguimiento, evitando sobreextenderse.',
            'going_second_plan' => 'Intercambiar de forma eficiente, desmontar la pieza central y establecer el motor propio.',
            'side_deck_strategy' => 'Ajustar la proporcion entre motor y respuestas segun se espere comenzar primero o segundo.',
        ],
        'control' => [
            'opening_plan' => 'Asegurar acceso a interacciones y una fuente repetible de ventaja antes de comprometer recursos.',
            'mid_game_plan' => 'Administrar respuestas, negar los turnos fuertes del rival y convertir cada intercambio en ventaja.',
            'late_game_plan' => 'Cerrar desde una posicion de recursos superior sin perder el control de la mesa.',
            'going_first_plan' => 'Preparar capas de interrupcion y proteger el motor que sostiene el duelo largo.',
            'going_second_plan' => 'Romper la amenaza indispensable, estabilizar y pasar a un plan de control gradual.',
            'side_deck_strategy' => 'Cambiar respuestas situacionales por herramientas precisas contra el motor y las amenazas del rival.',
        ],
        'otk' => [
            'opening_plan' => 'Reunir acceso al motor y herramientas para atravesar la mesa antes de comprometer la linea de dano.',
            'mid_game_plan' => 'Eliminar defensas, conservar extensores y calcular una ventana segura de cierre.',
            'late_game_plan' => 'Reconstruir una ultima ofensiva; el rendimiento cae si el rival estabiliza por completo.',
            'going_first_plan' => 'Crear una posicion defensiva suficiente y preservar recursos para el turno de respuesta.',
            'going_second_plan' => 'Desmontar la mesa en orden y convertir el acceso restante en dano letal.',
            'side_deck_strategy' => 'Priorizar rompe-mesas y respuestas de alto impacto, retirando cartas lentas o exclusivas de primer turno.',
        ],
        'graveyard' => [
            'opening_plan' => 'Cargar el cementerio con valor, activar el motor y mantener una ruta de recuperacion.',
            'mid_game_plan' => 'Reciclar piezas, encadenar efectos desde cementerio y sostener presion sin vaciar los recursos.',
            'late_game_plan' => 'Ganar por recurrencia, protegiendo los efectos de retorno y las piezas que reinician el motor.',
            'going_first_plan' => 'Construir interaccion mientras se deja preparado el cementerio para el siguiente turno.',
            'going_second_plan' => 'Usar el cementerio como extension para superar intercambios y reconstruir tras romper la mesa.',
            'side_deck_strategy' => 'Anticipar odio al cementerio y añadir respuestas que no dependan del mismo eje de recursos.',
        ],
        'retro' => [
            'opening_plan' => 'Evaluar mano, tempo y economia de cartas antes de comprometer una amenaza importante.',
            'mid_game_plan' => 'Intercambiar recursos con disciplina y jugar alrededor de las respuestas conocidas del formato.',
            'late_game_plan' => 'Maximizar cada carta restante y cerrar solo cuando la ventaja sea estable.',
            'going_first_plan' => 'Establecer informacion, defensa y ventaja gradual sin regalar intercambios favorables.',
            'going_second_plan' => 'Recuperar el tempo con remocion eficiente y castigar recursos mal invertidos.',
            'side_deck_strategy' => 'Adaptar amenazas y respuestas al plan concreto del rival y al ritmo historico del formato.',
        ],
        'rush' => [
            'opening_plan' => 'Desarrollar el campo con eficiencia, habilitar el motor tematico y conservar una amenaza de seguimiento.',
            'mid_game_plan' => 'Renovar la mano, mantener presion y ajustar el orden de ataques a la mesa rival.',
            'late_game_plan' => 'Buscar un turno de cierre sin abandonar por completo la defensa.',
            'going_first_plan' => 'Crear una mesa estable que limite el primer contraataque.',
            'going_second_plan' => 'Aprovechar el volumen de invocaciones para recuperar la mesa y presionar puntos de vida.',
            'side_deck_strategy' => 'Ajustar remocion, amenazas y respuestas al ritmo del formato Rush y al tipo de monstruos rival.',
        ],
    ];
}

function ygo_deck_analysis_baseline_specs(): array
{
    $s = static fn(string $pattern, string $style, string $pace, string $complexity, string $plan, string $win, string $resource, array $strengths, array $weaknesses, array $tags, array $scores, string $confidence = 'MEDIA'): array => compact(
        'pattern', 'style', 'pace', 'complexity', 'plan', 'win', 'resource', 'strengths', 'weaknesses', 'tags', 'scores', 'confidence'
    );

    return [
        'Kewl Tune' => $s('combo', 'Synchro reactivo', 'RAPIDO', 'ALTA', 'Encadenar Tuners y Synchros con capacidad de jugar durante el turno rival.', 'Ganar por interrupcion sincronizada y presion de Synchros.', 'Tuners en mano, efectos al ser material y acceso al Extra Deck.', ['Flexibilidad de Synchro', 'Interaccion en turno rival', 'Buen techo'], ['Exige secuencias precisas', 'Sensibilidad a interrupciones tempranas'], ['Synchro', 'Tuner', 'Reactivo'], [4,5,3,5,3,5,4]),
        'Branded' => $s('midrange', 'Fusion midrange', 'MEDIO', 'MEDIA', 'Resolver el motor Branded para producir Fusiones, interrupcion y seguimiento.', 'Superar al rival con Fusiones sucesivas y ventaja recurrente.', 'Fusiones, cementerio y cartas Branded que reemplazan recursos.', ['Recurrencia', 'Flexibilidad', 'Juego largo'], ['Dependencia de accesos clave', 'Puede sufrir cierres del cementerio'], ['Fusion', 'Midrange', 'Recursion'], [4,4,4,4,5,4,4]),
        'Vanquish Soul K9' => $s('midrange', 'Midrange reactivo', 'MEDIO', 'ALTA', 'Combinar el juego reactivo de Vanquish Soul con extensores K9 y ventaja incremental.', 'Ganar intercambios mientras mantiene amenazas que rotan entre mano y campo.', 'Atributos en mano, efectos rapidos y extensores del motor K9.', ['Interaccion flexible', 'Dificil de agotar', 'Buen juego en ambos turnos'], ['Gestion de mano exigente', 'Pierde eficiencia con atributos pobres'], ['Midrange', 'Reactivo', 'K9'], [4,4,4,5,4,4,4]),
        'Mitsurugi Pure' => $s('combo', 'Ritual midrange', 'MEDIO', 'ALTA', 'Tributar y resolver Invocaciones Ritual conservando valor de las piezas utilizadas.', 'Establecer amenazas Ritual y encadenar ventaja por tributos.', 'Monstruos Ritual, tributos y busqueda tematica.', ['Motor cohesivo', 'Valor al tributar', 'Presion sostenida'], ['Dependencia del motor Ritual', 'Secuencias sensibles a negaciones'], ['Ritual', 'Tributo', 'Midrange'], [4,4,4,4,4,4,3]),
        'Elfnote' => $s('control', 'Synchro de control posicional', 'MEDIO', 'ALTA', 'Controlar zonas y posicionamiento mientras convierte el motor en Synchros e interaccion.', 'Restringir las lineas del rival y ganar con ventaja posicional.', 'Synchros, movimiento de zonas y soporte continuo.', ['Control espacial', 'Juego reactivo', 'Interaccion tecnica'], ['Requiere posicionamiento correcto', 'Techo dependiente de la secuencia'], ['Synchro', 'Control', 'Zonas'], [3,4,3,5,3,4,3]),
        'Chaos Ritual' => $s('combo', 'Ritual combo', 'MEDIO', 'ALTA', 'Alinear materiales, niveles y acceso Ritual para establecer amenazas de alto impacto.', 'Cerrar mediante monstruos Ritual y cadenas de efectos de soporte.', 'Buscadores Ritual, cementerio y materiales reutilizables.', ['Techo alto', 'Amenazas potentes', 'Acceso tematico'], ['Puede requerir varias piezas', 'Perfil sujeto a confirmar lista exacta'], ['Ritual', 'Chaos', 'Combo'], [3,4,3,4,3,4,3], 'BAJA'),
        'Dracotail' => $s('midrange', 'Fusion reactiva', 'MEDIO', 'ALTA', 'Usar materiales Dracotail para Fusionar y convertirlos en acceso a soporte adicional.', 'Encadenar Fusiones con interrupcion y seguimiento.', 'Materiales de Fusion que generan valor al llegar al cementerio.', ['Ventaja al fusionar', 'Seguimiento', 'Interaccion'], ['Dependencia del cementerio', 'Lineas complejas'], ['Fusion', 'Cementerio', 'Reactivo'], [4,4,4,5,4,4,4]),
        'DoomZ' => $s('graveyard', 'Combo de destruccion y retorno', 'RAPIDO', 'ALTA', 'Destruir piezas propias con valor, recuperarlas y convertir el ciclo en desarrollo.', 'Acumular ventaja mediante destruccion, retorno y presion repetida.', 'Cementerio, auto-destruccion y efectos de recuperacion/equipamiento.', ['Recurrencia', 'Extensores', 'Techo combo'], ['Vulnerable al odio al cementerio', 'Secuencias exigentes'], ['Combo', 'Cementerio', 'Recursion'], [3,5,4,4,5,4,4]),
        'Ryzeal' => $s('midrange', 'Xyz midrange', 'RAPIDO', 'MEDIA', 'Colocar cuerpos compatibles y convertirlos en Xyz con interaccion y seguimiento.', 'Controlar el ritmo con Xyz eficientes y presion constante.', 'Extensores de nivel compatible y Extra Deck Xyz.', ['Consistencia', 'Eficiencia', 'Presion'], ['Dependencia del Extra Deck', 'Puede perder opciones en duelo largo'], ['Xyz', 'Midrange', 'Consistente'], [5,4,4,4,3,4,4]),
        'Maliss' => $s('combo', 'Cyberse combo', 'RAPIDO', 'ALTA', 'Encadenar recursos Cyberse y zonas de exclusion para construir interaccion y continuidad.', 'Ganar con una mesa amplia y seguimiento digital.', 'Exclusion, cementerio y enlaces Cyberse.', ['Movilidad de recursos', 'Techo alto', 'Recuperacion'], ['Lineas largas', 'Vulnerable a interrupcion dirigida'], ['Cyberse', 'Link', 'Combo'], [4,5,4,4,4,5,4]),
        'Tearlaments' => $s('graveyard', 'Fusion de cementerio', 'RAPIDO', 'ALTA', 'Enviar cartas al cementerio y convertir cada activacion valida en Fusion y ventaja.', 'Superar al rival con cadenas de Fusion, interrupcion y reciclaje.', 'Mills, efectos de cementerio y Fusiones reciclables.', ['Techo alto', 'Recurrencia', 'Juego en ambos turnos'], ['Varianza del envio', 'Odio al cementerio'], ['Fusion', 'Cementerio', 'Reactivo'], [3,5,5,5,5,4,5]),
        'Snake-Eyes' => $s('midrange', 'Combo midrange', 'RAPIDO', 'ALTA', 'Convertir monstruos y zonas de Magia/Trampa en extensores para desarrollar enlaces y seguimiento.', 'Dominar el campo con una cadena eficiente de invocaciones y recursos persistentes.', 'Monstruos de nivel bajo, colocacion en zona trasera y enlaces.', ['Consistencia', 'Extension', 'Seguimiento'], ['Dependencia de puntos iniciales', 'Expuesto a interrupciones de motor'], ['Link', 'Combo', 'Midrange'], [5,5,4,5,5,5,4]),
        'Fire King' => $s('graveyard', 'Midrange de destruccion', 'MEDIO', 'MEDIA', 'Destruir cartas propias para activar valor y reconstruir el campo.', 'Ganar por recurrencia y amenazas que regresan tras la destruccion.', 'Destruccion propia, cementerio y efectos durante fases posteriores.', ['Recurrencia', 'Sinergia con destruccion', 'Juego largo'], ['Ritmo diferido', 'Odio al cementerio'], ['Destruccion', 'Cementerio', 'Midrange'], [4,4,4,4,5,4,3]),
        'Unchained' => $s('midrange', 'Link de destruccion', 'MEDIO', 'MEDIA', 'Destruir cartas propias para extender y usar monstruos rivales en lineas Link.', 'Desmontar la mesa rival mientras mantiene presion recurrente.', 'Destruccion propia, flotacion y Link Summon.', ['Interaccion con monstruos rivales', 'Recurrencia', 'Buen grind'], ['Puede atascarse sin activadores', 'Dependencia del cementerio'], ['Link', 'Destruccion', 'Midrange'], [4,4,4,4,5,4,4]),
        'Purrely' => $s('control', 'Xyz de acumulacion', 'MEDIO', 'ALTA', 'Convertir Memorias en materiales Xyz y proteger una amenaza que acumula valor.', 'Ganar con un Xyz dificil de remover y ventaja generada por sus materiales.', 'Memorias, excavacion y materiales Xyz.', ['Amenaza resistente', 'Ventaja acumulativa', 'Flexibilidad'], ['Dependencia de una pieza central', 'Gestion compleja de materiales'], ['Xyz', 'Control', 'Recursos'], [4,5,4,4,4,5,3]),
        'Swordsoul' => $s('midrange', 'Synchro midrange', 'MEDIO', 'MEDIA', 'Generar Tuners-token y convertir cada acceso en Synchros eficientes.', 'Controlar con Synchros y cerrar con dano sostenido.', 'Wyrm, tokens y Extra Deck Synchro.', ['Consistencia', 'Lineas claras', 'Buen equilibrio'], ['Techo predecible', 'Dependencia de la invocacion inicial'], ['Synchro', 'Wyrm', 'Midrange'], [4,4,4,4,3,4,4]),
        'Sky Striker' => $s('control', 'Control de hechizos', 'LENTO', 'ALTA', 'Administrar Magias y rotar Links para responder con eficiencia.', 'Ganar por ventaja de cartas, precision y acceso repetido a herramientas.', 'Magias en cementerio y Links de un solo monstruo.', ['Caja de herramientas', 'Juego largo', 'Alta adaptabilidad'], ['Inicio lento', 'Exige administrar zonas y recursos'], ['Link', 'Control', 'Spell'], [4,3,4,5,5,3,5]),
        'DoomZ Artmage' => $s('graveyard', 'Combo hibrido', 'RAPIDO', 'ALTA', 'Usar el ciclo DoomZ como extension para acceder a amenazas Artmage.', 'Construir un campo de alto techo con recursos que regresan.', 'Destruccion/retorno DoomZ y extensores Artmage.', ['Techo alto', 'Multiples ejes', 'Recuperacion'], ['Complejidad alta', 'Mayor riesgo de manos incompatibles'], ['Combo', 'Hibrido', 'Cementerio'], [3,5,4,5,4,5,4], 'BAJA'),
        'White Forest Azamina' => $s('combo', 'Synchro/Fusion hibrido', 'MEDIO', 'ALTA', 'Convertir Magias/Trampas y cuerpos tematicos en lineas Synchro y Azamina.', 'Ganar mediante interacciones de Extra Deck y ventaja reciclada.', 'Cartas White Forest, soporte Azamina y cementerio.', ['Versatilidad', 'Techo tecnico', 'Recursos reciclables'], ['Secuencias complejas', 'Dependencia de varias zonas'], ['Synchro', 'Fusion', 'Hibrido'], [4,5,4,5,4,5,4]),
        'Kashtira Pure' => $s('midrange', 'Xyz de exclusion y zonas', 'RAPIDO', 'MEDIA', 'Invocar cuerpos de nivel alto sin tributo y presionar recursos mediante exclusion.', 'Restringir recursos o zonas y cerrar con cuerpos Xyz de gran presencia.', 'Invocaciones propias, exclusion y Xyz de rango alto.', ['Presion inmediata', 'Exclusion', 'Amenazas grandes'], ['Recuperacion limitada', 'Sensible a rompe-mesas'], ['Xyz', 'Banish', 'Control de zonas'], [4,4,3,4,2,4,4]),
        'Demonherrero Bystial' => $s('midrange', 'Midrange hibrido', 'MEDIO', 'ALTA', 'Combinar extensores Demonherrero con cuerpos Bystial para interrumpir cementerios y acceder al Extra Deck.', 'Ganar por eficiencia de motor y amenazas que reemplazan recursos.', 'Cementerio, cuerpos Bystial y motor Demonherrero.', ['Interaccion de cementerio', 'Extensores', 'Flexibilidad'], ['Puede depender del atributo rival', 'Motor hibrido exigente'], ['Bystial', 'Hibrido', 'Midrange'], [4,5,4,5,4,5,4]),
        'Cyber Dragon K9' => $s('otk', 'Maquinas OTK hibridas', 'RAPIDO', 'ALTA', 'Usar K9 para ampliar las lineas de Cyber Dragon y abrir un turno de alto dano.', 'Romper la mesa y cerrar con Fusiones/Xyz de Maquina.', 'Invocaciones especiales de Maquina, contacto y motor K9.', ['Potencial OTK', 'Capacidad de romper mesa', 'Extension'], ['Dependencia del Extra Deck', 'Riesgo de sobreextension'], ['Machine', 'OTK', 'K9'], [3,5,3,4,3,3,5]),
        'Invoked' => $s('midrange', 'Fusion toolbox', 'MEDIO', 'MEDIA', 'Resolver el acceso Invoked y adaptar la Fusion al atributo y estado del duelo.', 'Controlar con Fusiones eficientes y repetir el motor en turnos posteriores.', 'Aleister, Invocation, cementerios y atributos disponibles.', ['Motor compacto', 'Adaptabilidad', 'Recurrencia'], ['Punto inicial reconocible', 'Techo puro moderado'], ['Fusion', 'Toolbox', 'Midrange'], [4,3,4,4,4,4,3]),
        'Mathmech' => $s('combo', 'Cyberse combo/OTK', 'RAPIDO', 'ALTA', 'Encadenar Cyberse para establecer interaccion o preparar un cierre de alto dano.', 'Cerrar con acceso Link/Xyz y una linea de dano explosiva.', 'Extensores Cyberse, cementerio y Extra Deck.', ['Consistencia', 'OTK', 'Acceso compacto'], ['Dependencia de Normal Summon o cementerio', 'Lineas vulnerables a puntos clave'], ['Cyberse', 'Combo', 'OTK'], [4,5,3,4,3,4,5]),
        'Infernoble' => $s('combo', 'Synchro de equipamiento', 'RAPIDO', 'ALTA', 'Convertir Guerreros y Equipos en extensores para una secuencia Synchro amplia.', 'Establecer una mesa de alto techo con proteccion y seguimiento.', 'Equip Spells, Guerreros Fuego y cementerio.', ['Techo alto', 'Acceso a herramientas', 'Presion'], ['Lineas largas', 'Sensibilidad a interrupciones tempranas'], ['Warrior', 'Synchro', 'Equip'], [3,5,3,5,3,5,3]),
        'Memento' => $s('graveyard', 'Combo de cementerio', 'MEDIO', 'ALTA', 'Enviar y transformar monstruos Memento para ensamblar una amenaza central y recursos de apoyo.', 'Cerrar con una amenaza grande respaldada por recurrencia.', 'Cementerio, destruccion propia y monstruos Memento.', ['Recursion', 'Techo creciente', 'Motor tematico'], ['Dependencia del cementerio', 'Preparacion de varias piezas'], ['Cementerio', 'Combo', 'Boss monster'], [3,4,4,4,4,4,3]),
        'Exosister' => $s('midrange', 'Xyz anti-cementerio', 'RAPIDO', 'MEDIA', 'Castigar movimientos de cementerio y convertir pares de monstruos en Xyz reactivos.', 'Impedir el motor rival y ganar con Xyz especializados.', 'Monstruos Exosister, Xyz y disparadores contra cementerio.', ['Excelente contra cementerio', 'Interaccion dirigida', 'Plan claro'], ['Rendimiento dependiente del matchup', 'Menor recurrencia'], ['Xyz', 'Anti-GY', 'Counter-meta'], [4,3,3,4,2,4,4]),
        'Salamangreat' => $s('midrange', 'Link midrange', 'MEDIO', 'MEDIA', 'Establecer el motor Cyberse, reciclar recursos y mantener acceso a Links recurrentes.', 'Ganar por consistencia, interaccion y presion repetida.', 'Cementerio, reencarnacion Link y trampas tematicas.', ['Consistencia', 'Recurrencia', 'Motor compacto'], ['Techo moderado', 'Odio al cementerio'], ['Cyberse', 'Link', 'Midrange'], [5,3,4,4,5,4,4]),
        'K9 Pure' => $s('midrange', 'Motor K9 puro', 'RAPIDO', 'ALTA', 'Usar extensores K9 para acceder a interaccion de Extra Deck y conservar seguimiento.', 'Mantener iniciativa con invocaciones flexibles y respuestas rapidas.', 'Extensores K9 y Extra Deck.', ['Flexibilidad', 'Buen acceso a cuerpos', 'Juego reactivo'], ['Lista exacta condiciona el techo', 'Puede depender del Extra Deck'], ['K9', 'Midrange', 'Extra Deck'], [4,4,3,4,3,4,4], 'BAJA'),
        'Kashtira Demonherrero' => $s('combo', 'Combo hibrido de exclusion', 'RAPIDO', 'ALTA', 'Combinar presion Kashtira con extensores Demonherrero para ampliar el acceso al Extra Deck.', 'Cerrar recursos del rival mientras establece amenazas adicionales.', 'Exclusion, cuerpos de nivel alto y motor Demonherrero.', ['Multiples ejes', 'Presion temprana', 'Techo alto'], ['Compatibilidad de motores', 'Recuperacion irregular'], ['Kashtira', 'Demonherrero', 'Hibrido'], [3,5,3,5,3,5,4], 'BAJA'),
        'Resonator RDA Horus' => $s('combo', 'Synchro hibrido', 'RAPIDO', 'ALTA', 'Usar Resonators para escalar Synchros y Horus como extension y plan de respaldo.', 'Ganar con Dragones Synchro de alto impacto y cuerpos Horus persistentes.', 'Tuners, Synchros Dragon y motor Horus.', ['Techo alto', 'Cuerpos grandes', 'Plan alternativo'], ['Lineas largas', 'Riesgo de manos de motores separados'], ['Synchro', 'Dragon', 'Horus'], [3,5,4,4,4,5,4]),
        'Crystron' => $s('combo', 'Synchro reactivo', 'MEDIO', 'ALTA', 'Preparar Tuners y materiales que permitan sincronizar en respuesta al rival.', 'Interrumpir durante el turno rival y reciclar piezas Maquina.', 'Tuners Crystron, cementerio y Synchros.', ['Juego reactivo', 'Toolbox Synchro', 'Recurrencia'], ['Secuencias complejas', 'Necesita preparacion'], ['Machine', 'Synchro', 'Reactivo'], [3,4,4,5,4,4,3]),
        'Vanquish Soul Zoodiac' => $s('midrange', 'Midrange/Xyz hibrido', 'MEDIO', 'ALTA', 'Combinar la rotacion reactiva de Vanquish Soul con acceso compacto a Xyz Zoodiac.', 'Ganar intercambios con herramientas de dos motores complementarios.', 'Atributos en mano, rotacion Vanquish Soul y Xyz de un material.', ['Toolbox', 'Interaccion flexible', 'Motor compacto'], ['Gestion de espacio y mano', 'Compatibilidad de motores'], ['Vanquish Soul', 'Zoodiac', 'Xyz'], [3,4,4,5,4,4,4]),
        'Exodia' => $s('combo', 'Combo de condicion alternativa', 'RAPIDO', 'ALTA', 'Filtrar y robar cartas protegiendo la ruta que reune las cinco piezas.', 'Resolver la condicion de victoria de Exodia.', 'Robo, busqueda y compresion del deck.', ['Victoria alternativa', 'Ignora combate', 'Explosividad'], ['Alta varianza', 'Vulnerable a interrupcion y descarte'], ['Exodia', 'Draw', 'Alternate win'], [2,5,2,2,1,3,2]),
        'Blue Dragon' => $s('midrange', 'Dragon beatdown', 'MEDIO', 'MEDIA', 'Acelerar Dragones de alto ATK y convertirlos en presion o acceso al Extra Deck.', 'Ganar por superioridad de cuerpos y dano de combate.', 'Dragones, cementerio y soporte de invocacion especial.', ['Amenazas grandes', 'Presion de combate', 'Acceso Dragon'], ['Puede robar piezas poco activas', 'Perfil sujeto a confirmar lista exacta'], ['Dragon', 'Beatdown', 'Extra Deck'], [3,4,3,3,3,3,4], 'BAJA'),
        'Dark World' => $s('combo', 'Combo de descarte', 'RAPIDO', 'ALTA', 'Descartar cartas Dark World por efecto para encadenar robo, invocaciones y ventaja.', 'Desarmar recursos o establecer una mesa amplia antes de cerrar.', 'Descarte por efecto, cementerio y robo.', ['Techo explosivo', 'Reciclaje', 'Muchas extensiones'], ['Varianza', 'Lineas largas y sensibles a interrupcion'], ['Fiend', 'Discard', 'Combo'], [3,5,3,4,4,5,3]),
        'Resonator Red Dragon Archfiend' => $s('combo', 'Synchro Dragon', 'RAPIDO', 'ALTA', 'Usar Resonators y cuerpos compatibles para escalar a Red Dragon Archfiend.', 'Cerrar mediante Dragones Synchro de alto impacto.', 'Tuners Resonator, cementerio y Extra Deck Synchro.', ['Techo alto', 'Presion de combate', 'Amenazas potentes'], ['Dependencia del Extra Deck', 'Puntos de interrupcion claros'], ['Synchro', 'Dragon', 'Combo'], [3,5,3,4,3,5,3]),
        'Traptrix Ragnaraika' => $s('control', 'Control de trampas/Link', 'MEDIO', 'ALTA', 'Combinar trampas Trap Hole con extensores Ragnaraika para controlar y reciclar el campo.', 'Negar el desarrollo rival y ganar mediante ventaja incremental.', 'Trampas normales, monstruos Planta/Insecto/Reptil y Links.', ['Control fuerte primero', 'Recurrencia', 'Interaccion variada'], ['Mas debil ante rompe-mesas', 'Gestion compleja de zonas'], ['Trap', 'Link', 'Control'], [4,4,4,5,4,5,3]),
        'Cyber Dragon Pure' => $s('otk', 'Maquinas OTK', 'RAPIDO', 'MEDIA', 'Romper la mesa y convertir Cyber Dragons en Fusiones o Xyz de alto dano.', 'Finalizar en un turno mediante contacto, ataques multiples o dano elevado.', 'Invocaciones especiales de Maquina y Extra Deck.', ['OTK', 'Buen segundo', 'Amenazas grandes'], ['Recuperacion limitada', 'Predecible al comenzar primero'], ['Machine', 'Fusion', 'OTK'], [3,4,2,3,2,2,5]),
        'Six Samurai' => $s('combo', 'Warrior combo', 'RAPIDO', 'ALTA', 'Acumular contadores y extensores Six Samurai para encadenar invocaciones.', 'Establecer una mesa extensa o cerrar con multiples atacantes.', 'Contadores, Guerreros y Extra Deck.', ['Techo muy alto', 'Gran capacidad de extension', 'Explosividad'], ['Lineas largas', 'Dependencia de iniciadores y contadores'], ['Warrior', 'Counter', 'Combo'], [3,5,2,4,2,5,3]),
        'Barrera de Hielo' => $s('midrange', 'Synchro de control', 'MEDIO', 'MEDIA', 'Desarrollar Agua/Aqua para acceder a Synchros y limitar acciones rivales.', 'Ganar con control de campo y Dragones Synchro de Barrera de Hielo.', 'Monstruos Agua, cementerio y Synchros.', ['Toolbox Synchro', 'Control tematico', 'Acceso a remocion'], ['Consistencia moderada', 'Requiere establecer materiales'], ['Water', 'Synchro', 'Control'], [3,4,3,4,3,4,3]),
        'Volcanic' => $s('control', 'Burn/control', 'MEDIO', 'MEDIA', 'Cargar municion Volcanic y convertirla en remocion y dano directo.', 'Reducir recursos y LP con efectos de disparo y control de mesa.', 'Municion en cementerio, aceleradores y efectos de burn.', ['Remocion', 'Dano sin combate', 'Plan distintivo'], ['Ritmo moderado', 'Dependencia de piezas de soporte'], ['Pyro', 'Burn', 'Control'], [3,3,3,4,3,4,3]),
        'Horus' => $s('midrange', 'Motor de cementerio', 'MEDIO', 'MEDIA', 'Enviar piezas Horus al cementerio y recuperarlas como cuerpos persistentes.', 'Ganar por presencia repetida y acceso a Xyz/Extra Deck.', 'King Sarcophagus, cementerio y cuerpos Horus.', ['Recurrencia', 'Cuerpos grandes', 'Motor flexible'], ['Vulnerable al odio al cementerio', 'Costo de mano'], ['Horus', 'Cementerio', 'Midrange'], [4,4,4,3,5,4,4]),
        'Relinquished' => $s('retro', 'Control por absorcion', 'LENTO', 'MEDIA', 'Resolver Relinquished/Thousand-Eyes Restrict para neutralizar amenazas y proteger el tempo.', 'Ganar controlando monstruos rivales y atacando desde ventaja.', 'Ritual, Fusion y absorcion de monstruos.', ['Neutraliza amenazas', 'Control de combate', 'Buen intercambio'], ['Necesita ensamblar piezas', 'Puede ser lento'], ['GOAT', 'Ritual', 'Control'], [3,3,3,4,3,3,3]),
        'Reasoning' => $s('retro', 'Turbo de cementerio', 'RAPIDO', 'ALTA', 'Usar Reasoning/Monster Gate para acelerar monstruos y cargar recursos.', 'Crear ventaja explosiva y cerrar antes de perder el impulso.', 'Excavacion, cementerio y monstruos de niveles variados.', ['Explosividad', 'Aceleracion', 'Presion'], ['Varianza', 'Dependencia de resoluciones clave'], ['GOAT', 'Reasoning Gate', 'Turbo'], [2,5,3,3,3,4,4]),
        'Goat Control' => $s('retro', 'Control clasico', 'LENTO', 'ALTA', 'Administrar removal, flips y tokens para controlar tempo y ventaja de cartas.', 'Ganar el duelo largo mediante intercambios eficientes y Thousand-Eyes Restrict.', 'Magias defensivas, Flip Effects, tokens y Fusion Deck.', ['Flexibilidad', 'Juego largo', 'Profundidad tactica'], ['Cierre lento', 'Exige lectura del rival'], ['GOAT', 'Control', 'Flip'], [4,3,5,4,5,4,4]),
        'Thunder Chaos' => $s('retro', 'Chaos agresivo', 'MEDIO', 'MEDIA', 'Intercambiar monstruos de Luz/Oscuridad y habilitar amenazas Chaos.', 'Cerrar con presion de Thunder Dragon y monstruos Chaos.', 'Cementerio Luz/Oscuridad, descarte y amenazas Chaos.', ['Amenazas fuertes', 'Buena economia de cartas', 'Presion'], ['Requiere atributos correctos', 'Odio al cementerio'], ['GOAT', 'Chaos', 'Thunder'], [4,4,4,4,4,4,4]),
        'Chaos Control' => $s('retro', 'Control Chaos', 'LENTO', 'ALTA', 'Controlar el ritmo mientras prepara Luz y Oscuridad para amenazas Chaos.', 'Ganar por ventaja y cierres de alto impacto en el duelo medio/largo.', 'Cementerio, Flip Effects y monstruos Chaos.', ['Recurrencia', 'Amenazas decisivas', 'Adaptabilidad'], ['Preparacion gradual', 'Gestion exigente del cementerio'], ['GOAT', 'Chaos', 'Control'], [4,4,5,4,5,4,4]),
        'Monarch' => $s('retro', 'Control por tributo', 'MEDIO', 'MEDIA', 'Generar tributos eficientes y convertirlos en remocion mediante Monarchs.', 'Mantener tempo con invocaciones por tributo que producen ventaja inmediata.', 'Generadores de tributo y Monarchs.', ['Remocion al invocar', 'Cuerpos solidos', 'Plan directo'], ['Puede robar tributos sin soporte', 'Ritmo dependiente del campo'], ['GOAT', 'Tribute', 'Control'], [3,3,3,4,3,4,3]),
        'Warrior' => $s('retro', 'Warrior toolbox', 'MEDIO', 'MEDIA', 'Usar buscadores y Guerreros versatiles para adaptar la amenaza a cada estado.', 'Ganar por tempo, presion de combate y respuestas eficientes.', 'Reinforcement of the Army, Guerreros y removal.', ['Consistencia', 'Toolbox', 'Presion'], ['Techo moderado', 'Depende del combate'], ['GOAT', 'Warrior', 'Toolbox'], [4,3,4,3,3,4,4]),
        'Pyro' => $s('rush', 'Rush aggro/burn', 'RAPIDO', 'MEDIA', 'Llenar el campo con Pyro y combinar presion de combate con dano de efecto.', 'Reducir LP rapidamente antes de que el rival estabilice.', 'Invocaciones repetidas, Pyro y efectos de burn.', ['Presion', 'Dano directo', 'Ritmo alto'], ['Menor juego largo', 'Perfil sujeto a la lista Rush exacta'], ['Rush Duel', 'Pyro', 'Aggro'], [4,4,2,3,2,3,4], 'BAJA'),
        'Royal' => $s('rush', 'Rush midrange', 'MEDIO', 'MEDIA', 'Desarrollar amenazas Royal y sostener ventaja de mesa con invocaciones constantes.', 'Ganar por presencia superior y ataques bien secuenciados.', 'Monstruos Royal y renovacion de mano Rush.', ['Presencia', 'Equilibrio', 'Seguimiento'], ['Identidad depende de la lista exacta', 'Interaccion moderada'], ['Rush Duel', 'Royal', 'Midrange'], [3,3,3,3,3,3,3], 'BAJA'),
        'Hero' => $s('rush', 'Rush aggro/Fusion', 'RAPIDO', 'MEDIA', 'Encadenar HERO y soporte de Fusion para mantener ofensiva.', 'Cerrar con Heroes de alto impacto y presion de combate.', 'HERO, Fusion y renovacion de mano Rush.', ['Dano', 'Sinergia tematica', 'Amenazas reconocibles'], ['Puede gastar recursos rapido', 'Perfil sujeto a la lista exacta'], ['Rush Duel', 'HERO', 'Fusion'], [4,4,3,3,3,3,4], 'BAJA'),
        'Dark Magician' => $s('rush', 'Rush control/beatdown', 'MEDIO', 'MEDIA', 'Preparar soporte Spellcaster y convertir Dark Magician en una amenaza central.', 'Controlar la mesa y cerrar con atacantes Spellcaster.', 'Spellcasters, Magias/Trampas y soporte de Dark Magician.', ['Amenaza central fuerte', 'Soporte variado', 'Control'], ['Dependencia de cartas tematicas', 'Perfil sujeto a la lista exacta'], ['Rush Duel', 'Spellcaster', 'Dark Magician'], [3,4,3,4,3,4,3], 'BAJA'),
    ];
}

function ygo_seed_deck_analysis_profiles(PDO $pdo): int
{
    $templates = ygo_deck_analysis_phase_templates();
    $specs = ygo_deck_analysis_baseline_specs();
    $decks = $pdo->query('SELECT id, name FROM sx_ygo_decks WHERE active=1 ORDER BY id')->fetchAll(PDO::FETCH_ASSOC);
    $insert = $pdo->prepare(
        'INSERT IGNORE INTO sx_ygo_deck_analysis_profiles
        (deck_id,play_style,pace,complexity,primary_game_plan,win_condition,resource_engine,opening_plan,mid_game_plan,late_game_plan,going_first_plan,going_second_plan,side_deck_strategy,strengths_json,weaknesses_json,strategy_tags_json,consistency_score,ceiling_score,resilience_score,interaction_score,recovery_score,first_score,second_score,confidence,source_type,analysis_notes,revision)
        VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,1)'
    );
    $history = $pdo->prepare('INSERT IGNORE INTO sx_ygo_deck_analysis_profile_history (deck_id,revision,snapshot_json,change_reason) VALUES (?,1,?,?)');
    $saved = $pdo->prepare('SELECT * FROM sx_ygo_deck_analysis_profiles WHERE deck_id=? LIMIT 1');
    $created = 0;

    foreach ($decks as $deck) {
        $name = trim((string) ($deck['name'] ?? ''));
        if (!isset($specs[$name])) {
            continue;
        }
        $spec = $specs[$name];
        $phase = $templates[$spec['pattern']] ?? $templates['midrange'];
        $scores = array_pad(array_map(static fn($value): int => max(0, min(5, (int) $value)), $spec['scores']), 7, 3);
        $notes = 'Base editorial inicial del arquetipo. Debe revisarse cuando cambie la lista, la banlist o el enfoque del deck. Las metricas observadas se calculan aparte con resultados de la plataforma.';
        $insert->execute([
            (int) $deck['id'], $spec['style'], $spec['pace'], $spec['complexity'], $spec['plan'], $spec['win'], $spec['resource'],
            $phase['opening_plan'], $phase['mid_game_plan'], $phase['late_game_plan'], $phase['going_first_plan'], $phase['going_second_plan'], $phase['side_deck_strategy'],
            json_encode($spec['strengths'], JSON_UNESCAPED_UNICODE), json_encode($spec['weaknesses'], JSON_UNESCAPED_UNICODE), json_encode($spec['tags'], JSON_UNESCAPED_UNICODE),
            ...$scores, $spec['confidence'], 'ANALYTICAL', $notes,
        ]);
        if ($insert->rowCount() !== 1) {
            continue;
        }
        $created++;
        $saved->execute([(int) $deck['id']]);
        $snapshot = $saved->fetch(PDO::FETCH_ASSOC) ?: [];
        $history->execute([(int) $deck['id'], json_encode($snapshot, JSON_UNESCAPED_UNICODE), 'Base editorial inicial']);
    }

    if ($created > 0) {
        $pdo->prepare('INSERT INTO sx_ygo_audit_log (entity_type,entity_id,action,payload) VALUES (?,?,?,?)')
            ->execute(['system', 0, 'seed_deck_analysis_baselines_v1', json_encode(['created' => $created, 'catalog_size' => count($specs)], JSON_UNESCAPED_UNICODE)]);
    }
    return $created;
}
