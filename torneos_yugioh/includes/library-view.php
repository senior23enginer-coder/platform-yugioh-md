<?php $library=ygo_library_documents($appData); ?>
<section class="screen library-screen" data-screen="biblioteca" hidden>
    <div class="library-hero panel">
        <span class="tag">Centro de consulta</span>
        <h1>Biblioteca</h1>
        <p>Entiende la plataforma, consulta sus reglas y encuentra cómo organizar tus eventos.</p>
        <form data-library-search role="search">
            <label for="library-query">¿Qué quieres consultar?</label>
            <div class="library-search-row"><i class="bi bi-search" aria-hidden="true"></i><input id="library-query" type="search" class="form-control" placeholder="Ej.: ¿cómo funciona una liga de ida y vuelta?" autocomplete="off" maxlength="250" aria-describedby="library-search-help"><button class="btn btn-primary" type="submit">Buscar</button><button class="btn btn-outline-light" type="button" data-library-clear>Limpiar</button></div>
            <small id="library-search-help">Busca por nombres, códigos o preguntas. Reconoce sinónimos y errores sencillos de escritura.</small>
        </form>
        <div class="library-suggestions" aria-label="Consultas sugeridas"><?php foreach(['Ida y vuelta','Diferencia entre tipo y modelo','Cómo crear un evento','Ascenso y descenso','CUSTOM_XP','Elo Battle Royale'] as $suggestion): ?><button type="button" class="btn btn-outline-info btn-sm" data-library-query="<?php echo e($suggestion); ?>"><?php echo e($suggestion); ?></button><?php endforeach; ?></div>
    </div>
    <div class="library-toolbar"><label>Categoría<select class="form-select" data-library-category><option value="">Toda la biblioteca</option><?php foreach($library['categories'] as $category): ?><option><?php echo e($category); ?></option><?php endforeach; ?></select></label><p role="status" aria-live="polite" data-library-status><?php echo count($library['articles']); ?> artículos disponibles</p></div>
    <div class="library-layout"><div data-library-results class="library-results" aria-label="Artículos encontrados"></div><article class="panel library-reader" data-library-reader aria-labelledby="library-article-title"></article></div>
    <noscript><div class="panel"><h2>Consulta de la Biblioteca</h2><p>Activa JavaScript para buscar y abrir artículos. También puedes consultar las fichas de eventos y las reglas desde el menú.</p></div></noscript>
    <script type="application/json" id="library-data"><?php echo json_encode($library,JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR); ?></script>
</section>
