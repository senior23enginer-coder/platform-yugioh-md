(function (root) {
  "use strict";
  // A read/write checklist viewer; intentionally contains NO AI invocation.
  const panel = document.querySelector("[data-deck-analysis-progress]");
  const picker = document.querySelector("[data-deck-select]");
  if (!panel || !picker) return;
  const content = panel.querySelector("[data-deck-progress-content]");
  const headline = panel.querySelector("[data-deck-progress-headline]");
  const esc = (value) => String(value ?? "").replace(/[&<>"']/g, (c) => ({
    "&": "&amp;", "<": "&lt;", ">": "&gt;", '"': "&quot;", "'": "&#39;"
  }[c]));
  let activeId = 0, response = null, sequence = 0, busy = false;
  function duration(seconds) {
    if (!Number.isFinite(seconds)) return "Sin datos reales";
    if (seconds < 60) return Math.round(seconds) + " s";
    const mins = Math.round(seconds / 60);
    if (mins < 60) return mins + " min";
    return Math.floor(mins / 60) + " h " + mins % 60 + " min";
  }
  function endpoint(op) {
    return "./?api=deck_analysis_progress&deck_id=" + activeId + "&op=" + op;
  }
  async function request(op, payload) {
    const options = { cache: "no-store", credentials: "same-origin" };
    if (payload) {
      if (!response?.token) throw Error("La sesión no permite guardar cambios.");
      options.method = "POST";
      options.headers = { "Content-Type": "application/json", "X-CSRF-Token": response.token };
      options.body = JSON.stringify({ deck_id: activeId, revision: response.ledger?.revision, ...payload });
    }
    const r = await fetch(endpoint(op), options);
    const data = await r.json().catch(() => ({ ok: false, message: "La respuesta del servidor no es válida." }));
    if (!r.ok || !data.ok) throw Error(data.message || "No fue posible actualizar el seguimiento.");
    return data;
  }
  function row(task, firstOpen) {
    const done = task.status === "COMPLETE", blocked = task.status === "BLOCKED",
      running = task.status === "RUNNING";
    const actionable = !busy && (!firstOpen || task.task_id === firstOpen);
    const status = done ? "Terminada" : blocked ? "Bloqueada" : running ? "En proceso" : "Pendiente";
    return `<li class="progress-task ${esc(task.status.toLowerCase())}">
      <div class="progress-task-head"><b>${esc(task.task_id)} · ${status}</b>
      <small>${duration(task.elapsed_seconds || 0)} · ${Number(task.attempts || 0)} intentos</small></div>
      <p>${esc(task.description)}</p>
      ${done ? `<p><b>Evidencia:</b> ${esc(task.evidence)}</p>` : ""}
      ${blocked ? `<p><b>Bloqueo:</b> ${esc(task.block_reason)}</p>` : ""}
      ${running && actionable ? `<label>Fuente, control o motivo comprobado
        <textarea class="form-control" rows="2" maxlength="3000" data-progress-proof="${esc(task.task_id)}"
        placeholder="Referencia verificable de la tarea terminada o motivo del bloqueo"></textarea></label>
        <div class="progress-task-actions">
          <button type="button" class="btn btn-outline-info" data-progress-action="complete" data-task-id="${esc(task.task_id)}">Terminar con evidencia</button>
          <button type="button" class="btn btn-outline-warning" data-progress-action="block" data-task-id="${esc(task.task_id)}">Bloquear y documentar</button>
        </div>` : ""}
      ${!done && !running && actionable ? `<button type="button" class="btn btn-outline-info" data-progress-action="start" data-task-id="${esc(task.task_id)}">Iniciar tarea</button>` : ""}
    </li>`;
  }
  function render() {
    if (!response) return;
    panel.hidden = false;
    const counts = response.inventory_counts || { main: 0, extra: 0, side: 0, reserve: 0 };
    const required = ["main", "extra", "side"], missing = required.filter(k => !counts[k]);
    if (!response.assigned) {
      headline.textContent = response.eligible ? "Listo para asignación" : "Inventario incompleto";
      content.innerHTML = `<p>El seguimiento solo se inicia cuando el usuario asigna expresamente este deck.
        No ejecuta análisis ni modifica listas de cartas.</p>
        <p><b>Main:</b> ${counts.main || 0} · <b>Extra:</b> ${counts.extra || 0} ·
        <b>Side:</b> ${counts.side || 0} · <b>Reserva (opcional):</b> ${counts.reserve || 0}</p>
        ${missing.length ? `<p role="alert">No se puede analizar: falta cargar ${esc(missing.join(", "))}.</p>` : ""}
        ${response.eligible ? `<label class="progress-consent"><input type="checkbox" data-progress-consent>
        Confirmo que asigno expresamente este deck al seguimiento A01–A10A.
        Esta autorización no inicia tareas ni invoca una IA.</label>
        <button type="button" class="btn btn-primary" data-progress-assign disabled>Crear seguimiento para este deck</button>` : ""}
        <p><small>Las 35 tareas de A01–A10A y las 3 de A10B opcional provienen del manifiesto compartido con GPT Prompts.</small></p>`;
      return;
    }
    const summary = response.summary || {}, ledger = response.ledger;
    headline.textContent = summary.complete + " / " + summary.total + " verificadas";
    const firstOpen = ledger.tasks.find(t => (t.mandatory || ledger.calculator_requested) &&
      t.status !== "COMPLETE")?.task_id;
    const stages = (response.manifest?.stages || []).filter(s => s.mandatory || ledger.calculator_requested);
    const tasks = new Map(ledger.tasks.map(t => [t.task_id, t]));
    const progress = summary.total ? Math.round(100 * summary.complete / summary.total) : 0;
    content.innerHTML = `<div class="progress-overview">
      <div><strong>${progress}%</strong> completado con evidencia</div>
      <div>Duración registrada: <b>${duration(summary.elapsed_seconds)}</b></div>
      <div>Tiempo restante: <b>${summary.eta_seconds == null ? "Sin datos suficientes" : duration(summary.eta_seconds)}</b></div>
      <div>Bloqueadas: <b>${summary.blocked || 0}</b></div>
      </div>
      <progress value="${summary.complete}" max="${summary.total}" aria-label="Tareas verificadas"></progress>
      ${response.inventory_changed ? '<p role="alert">El inventario del deck cambió; se bloquean modificaciones hasta conciliarlo.</p>' : ""}
      <div class="progress-stage-grid">${stages.map(stage => {
        const members = stage.tasks.map(t => tasks.get(t.task_id)).filter(Boolean);
        const complete = members.filter(t => t.status === "COMPLETE").length;
        const metrics = (summary.stages || []).find(x => x.stage_id === stage.stage_id);
        const elapsed = duration(metrics?.elapsed_seconds || 0);
        const remaining = metrics?.eta_seconds == null ? "ETA sin datos" : duration(metrics.eta_seconds);
        return `<details class="progress-stage" ${members.some(t => t.status === "RUNNING") ? "open" : ""}>
          <summary><b>${esc(stage.stage_id)}</b><span>${complete}/${members.length} terminadas</span></summary>
          <p class="progress-stage-timing">Tiempo registrado: ${elapsed} · Restante: ${remaining}</p>
          <ol>${members.map(t => row(t, response.inventory_changed ? "__LOCKED__" : firstOpen)).join("")}</ol>
        </details>`;
      }).join("")}</div>
      ${!ledger.calculator_requested ? `<details class="progress-optional">
        <summary>A10B — calculadora opcional, no solicitada</summary>
        <p>Activar A10B no ejecuta IA; habilita sus tres tareas técnicas de seguimiento.</p>
        <label class="progress-consent"><input type="checkbox" data-progress-a10b-consent> Solicito expresamente el seguimiento A10B para este deck.</label>
        <button type="button" class="btn btn-outline-info" data-progress-a10b disabled>Habilitar A10B</button>
        </details>` : ""}
      <p class="progress-warning">Marcar una tarea terminada requiere evidencia real. Las muestras sintéticas o los tests de CI no certifican el análisis de este deck.</p>`;
  }
  async function refresh(id) {
    if (!id || id < 1) { panel.hidden = true; response = null; return; }
    const current = ++sequence;
    activeId = id;
    try {
      const data = await request("status");
      if (current !== sequence || Number(picker.value) !== id) return;
      response = data; render();
    } catch (error) {
      if (current !== sequence) return;
      response = null;
      // A 403 is expected for viewers without editorial permissions.
      if (/permisos de edición/i.test(error.message)) { panel.hidden = true; return; }
      panel.hidden = false; headline.textContent = "No disponible";
      content.textContent = error.message;
    }
  }
  async function mutate(op, payload) {
    if (busy || !response) return;
    busy = true;
    try {
      const data = await request(op, payload);
      response = data; render();
    } catch (error) {
      content.insertAdjacentHTML("afterbegin", `<p class="progress-error" role="alert">${esc(error.message)}</p>`);
    } finally { busy = false; }
  }
  content.addEventListener("change", e => {
    const consent = e.target.matches("[data-progress-consent]") && content.querySelector("[data-progress-assign]");
    if (consent) consent.disabled = !e.target.checked;
    const optional = e.target.matches("[data-progress-a10b-consent]") && content.querySelector("[data-progress-a10b]");
    if (optional) optional.disabled = !e.target.checked;
  });
  content.addEventListener("click", e => {
    const assign = e.target.closest("[data-progress-assign]");
    if (assign && !assign.disabled) return mutate("assign", { explicit_user_assignment: true });
    const a10b = e.target.closest("[data-progress-a10b]");
    if (a10b && !a10b.disabled) return mutate("enable_calculator", { explicit_a10b_request: true });
    const button = e.target.closest("[data-progress-action]");
    if (!button || button.disabled) return;
    const task_id = button.dataset.taskId, action = button.dataset.progressAction;
    if (action === "start") return mutate("transition", { task_id, action: "start" });
    const value = content.querySelector('[data-progress-proof="' + task_id + '"]')?.value.trim() || "";
    if (!value) {
      const proof = content.querySelector('[data-progress-proof="' + task_id + '"]');
      if (proof) { proof.setCustomValidity("Escribe evidencia o causa del bloqueo."); proof.reportValidity(); }
      return;
    }
    return mutate("transition", { task_id, action,
      ...(action === "complete" ? { evidence: value } : { reason: value }) });
  });
  let observedId = 0;
  const checkDeck = () => {
    const next = Number(picker.value || 0);
    if (next !== observedId) { observedId = next; refresh(next); }
  };
  picker.addEventListener("change", checkDeck);
  // Some legacy navigation paths update a hidden selector without emitting change.
  // Polling reads only a DOM value; it makes NO server requests until the deck changes.
  root.setInterval(checkDeck, 900);
  checkDeck();
})(window);
