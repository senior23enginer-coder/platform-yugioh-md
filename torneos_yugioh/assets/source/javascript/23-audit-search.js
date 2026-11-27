/**
 * audit-search: ordered adapter fragment of the existing browser application.
 * Shared legacy closure: dependencies and initialization order are declared in manifest.json.
 * Pure new business logic belongs in assets/modules, not in this adapter.
 */
// @runtime-start
  function renderAuditFilter() {
    const filters = {
      query: auditSearch ? auditSearch.value : '',
      kind: auditKind ? auditKind.value : '',
      entity: auditEntity ? auditEntity.value : '',
      action: auditAction ? auditAction.value : ''
    };
    auditRows.forEach((row) => {
      row.hidden = window.StationXAuditBracketPolicy.auditHidden(filters, {
        text: row.dataset.auditText || row.textContent || '',
        entity: row.dataset.auditEntity || '',
        action: row.dataset.auditAction || ''
      }, normalize);
    });
  }

  [auditSearch, auditKind, auditEntity, auditAction].forEach((control) => {
    if (control) control.addEventListener(control.tagName === 'INPUT' ? 'input' : 'change', renderAuditFilter);
  });

  function pairRows(names) {
    return window.StationXAuditBracketPolicy.pairRows(names);
  }

  document.querySelectorAll('[data-bracket]').forEach((node) => {
    const division = node.dataset.bracket;
    const status = competitionStatus('cup', division);
    if (!['active', 'completed', 'finalizada', 'finalizado'].includes(status)) {
      node.innerHTML = '<div class="match-pill muted">La llave se habilita cuando la copa este activa.</div>';
      return;
    }
    const names = data.divisions[division].decks.map((deck) => deck[1]);
    node.innerHTML = pairRows(names).map((pair, index) => `<div class="match-pill"><b>Llave ${index + 1}</b><br>${matchLabelWithTeam(pair[0])} vs ${matchLabelWithTeam(pair[1])}</div>`).join('');
  });

  const eternal = document.querySelector('[data-eternal-bracket]');
  if (eternal) {
    if (!officialDivisions.every((division) => hasPlayedMatches(division))) {
      eternal.innerHTML = '<div class="match-pill muted">Sin clasificacion a Copa Eternal hasta que existan tablas con resultados reales en todas las divisiones.</div>';
    } else {
    const names = officialDivisions.flatMap((division) => data.divisions[division].decks.slice(0, 3).map((deck) => deck[1]));
    eternal.innerHTML = pairRows(names).map((pair, index) => `<div class="match-pill"><b>${index === 0 ? 'Preliminar' : `Cuadro ${index}`}</b><br>${matchLabelWithTeam(pair[0])} vs ${matchLabelWithTeam(pair[1])}</div>`).join('');
    }
  }

  const worldGroups = document.querySelector('[data-world-groups]');
  if (worldGroups) {
    if (!officialDivisions.some((division) => hasPlayedMatches(division))) {
      worldGroups.innerHTML = '<article class="group-card"><h3>Sin clasificados</h3><p>El mundial no arma grupos temporales con semillas. Primero deben jugarse las fases que entregan cupos.</p></article>';
    } else {
    const pool = officialDivisions.flatMap((division) => data.divisions[division].decks.slice(0, 6).map((deck) => `${matchLabelWithTeam(deck[1])} (${division})`));
    worldGroups.innerHTML = window.StationXAuditBracketPolicy.worldGroups(pool).map((groupItems, group) => {
      const items = groupItems.map((name) => `<li>${name}</li>`).join('');
      return `<article class="group-card"><h3>Grupo ${String.fromCharCode(65 + group)}</h3><ol>${items}</ol><span class="status-chip">Clasifican 2</span></article>`;
    }).join('');
    }
  }

  if (search) {
    search.addEventListener('input', () => renderSearchResults(searchResults, search.value, true));
    search.addEventListener('focus', () => renderSearchResults(searchResults, search.value, true));
    search.addEventListener('keydown', (event) => {
      if (event.key === 'Enter') {
        event.preventDefault();
        const items = searchItems(search.value);
        if (items[0]) openSearchItem(items[0]);
        else {
          routeTo('buscar');
          if (searchPageInput) {
            searchPageInput.value = search.value;
            renderSearchResults(searchPageResults, search.value, false);
          }
        }
      }
      if (event.key === 'Escape' && searchResults) {
        searchResults.hidden = true;
      }
    });
    document.addEventListener('click', (event) => {
      if (searchResults && !event.target.closest('.searchbox')) searchResults.hidden = true;
    });
  }

  if (searchPageInput) {
    searchPageInput.addEventListener('input', () => renderSearchResults(searchPageResults, searchPageInput.value, false));
    renderSearchResults(searchPageResults, searchPageInput.value, false);
  }

