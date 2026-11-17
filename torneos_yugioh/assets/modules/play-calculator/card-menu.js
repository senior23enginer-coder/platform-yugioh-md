(function (root) {
  "use strict";
  const E = root.YgoPlayEngine,
    esc = (v) =>
      String(v ?? "").replace(
        /[&<>"']/g,
        (c) =>
          ({
            "&": "&amp;",
            "<": "&lt;",
            ">": "&gt;",
            '"': "&quot;",
            "'": "&#39;",
          })[c],
      );
  const zone = (z) =>
    ({
      hand: "Mano",
      graveyard: "Cementerio",
      banished: "Destierro",
      deck: "Main Deck",
      extra: "Extra Deck",
      field: "Campo",
      emz1: "Zona Extra 1",
      emz2: "Zona Extra 2",
    })[z] ||
    (/^m[1-5]$/.test(z)
      ? "Monstruo " + z.slice(1)
      : /^s[1-5]$/.test(z)
        ? "Magia/Trampa " + z.slice(1)
        : z);
  function create(node, { getScenario, getFacts, onChange, signal }) {
    const dialog = document.createElement("dialog");
    dialog.className = "play-card-menu";
    dialog.setAttribute("aria-label", "Opciones de la carta");
    document.body.append(dialog);
    let uid = null,
      trigger = null;
    function render() {
      const s = getScenario(),
        t = s?.tokens.find((t) => t.uid === uid);
      if (!t) {
        dialog.close();
        return;
      }
      const c = E.definition(s, t.key, t.owner),
        facts = getFacts() || { facts: {}, bindings: {} },
        cid = facts.bindings[t.key],
        p = facts.facts[cid];
      const link = !!p?.species?.includes("Link"),
        monster = E.isMonster(t.zone),
        negated = E.negated(s, t);
      const effects = (
        root.YgoPlayPlanner?.capabilities(s, facts) || []
      ).filter((e) => e.cardKey === t.key);
      const effectRows = Array.from(
        { length: Math.max(2, effects.length) },
        (_, i) => {
          const effect = effects[i],
            id =
              effect?.id === "9861:control"
                ? `${t.uid}:control`
                : effect?.id || "",
            used = (s.effectUsage || []).some(
              (e) => e.uid === uid && e.turn === s.turn && e.slot === i + 1,
            );
          return `<label><input type="checkbox" data-card-effect-slot="${i + 1}" data-card-effect-id="${esc(id)}" ${used ? "checked" : ""}> Efecto ${i + 1}${effect ? " · " + esc(effect.label.replace(c.name + ": ", "")) : " · marcador manual"}</label>`;
        },
      ).join("");
      dialog.innerHTML = `<header><div><h3>${esc(c.name)}</h3><p>Copia #${uid} · ${esc(zone(t.zone))} · Turno ${s.turn}</p></div><button type="button" class="btn btn-outline-light" data-card-menu-close>Cerrar</button></header>
        <fieldset><legend>Posición</legend><div class="play-card-menu-actions">${[["attack", monster ? "Ataque" : "Boca arriba"], ...(monster && !link ? [["defense", "Defensa"]] : []), ...(!link ? [["set", monster ? "Defensa boca abajo" : "Boca abajo"]] : [])].map(([value, label]) => `<button type="button" class="btn btn-outline-info" data-card-menu-position="${value}" aria-pressed="${t.position === value}" ${t.key === "unknown" && value !== "set" ? "disabled" : ""}>${label}</button>`).join("")}</div></fieldset>
        <fieldset><legend>Mover carta</legend><label>Destino<select class="form-select" data-card-menu-zone>${E.zones(
          s,
        )
          .filter((z) => !z.startsWith("emz") || s.format === "tcg_advanced")
          .map(
            (z) =>
              `<option value="${z}" ${z === t.zone ? "selected" : ""}>${esc(zone(z))}</option>`,
          )
          .join(
            "",
          )}</select></label><button type="button" class="btn btn-outline-info" data-card-menu-move>Mover a esta zona</button></fieldset>
        <fieldset><legend>Negación</legend><label><input type="checkbox" data-card-negated ${negated ? "checked" : ""}> Efectos de esta carta negados</label><label>Duración<select class="form-select" data-card-negation-duration><option value="turn" ${t.negation?.duration !== "manual" ? "selected" : ""}>Hasta terminar el turno ${s.turn}</option><option value="manual" ${t.negation?.duration === "manual" ? "selected" : ""}>Hasta que lo quite manualmente</option></select></label><label>Carta o motivo<input class="form-control" maxlength="300" data-card-negation-reason value="${esc(t.negation?.reason || "")}" placeholder="Ej.: Infinite Impermanence"></label></fieldset>
        <fieldset><legend>Efectos usados en el turno ${s.turn}</legend>${effectRows}<p>Marca el efecto que ya utilizaste. Los marcadores manuales no añaden efectos nuevos al motor.</p></fieldset><p role="status" data-card-menu-status></p>`;
    }
    function change(next) {
      onChange(next);
      render();
    }
    function open(id, button) {
      uid = Number(id);
      trigger = button;
      render();
      if (!dialog.open) dialog.showModal();
    }
    node.addEventListener(
      "contextmenu",
      (e) => {
        const b = e.target.closest("[data-play-token]");
        if (!b) return;
        e.preventDefault();
        open(b.dataset.playToken, b);
      },
      { signal },
    );
    node.addEventListener(
      "click",
      (e) => {
        const b = e.target.closest("[data-play-token-menu]");
        if (b) open(b.dataset.playTokenMenu, b);
      },
      { signal },
    );
    dialog.addEventListener("click", (e) => {
      const b = e.target.closest("button");
      if (!b) return;
      try {
        const s = getScenario(),
          t = s.tokens.find((t) => t.uid === uid);
        if (b.hasAttribute("data-card-menu-close")) dialog.close();
        else if (b.dataset.cardMenuPosition)
          change(E.position(s, uid, b.dataset.cardMenuPosition));
        else if (b.hasAttribute("data-card-menu-move")) {
          const destination = dialog.querySelector(
            "[data-card-menu-zone]",
          ).value;
          change(
            E.place(s, {
              uid,
              key: t.key,
              owner: t.owner,
              controller: [
                "hand",
                "graveyard",
                "banished",
                "deck",
                "extra",
              ].includes(destination)
                ? t.owner
                : t.controller,
              zone: destination,
            }),
          );
        }
      } catch (err) {
        dialog.querySelector("[data-card-menu-status]").textContent =
          err.message;
      }
    });
    dialog.addEventListener("change", (e) => {
      const el = e.target;
      try {
        const s = getScenario();
        if (el.hasAttribute("data-card-effect-slot"))
          change(
            E.cardStatus(s, uid, {
              effect: {
                slot: Number(el.dataset.cardEffectSlot),
                id: el.dataset.cardEffectId,
                used: el.checked,
              },
            }),
          );
        else if (
          el.matches(
            "[data-card-negated],[data-card-negation-duration],[data-card-negation-reason]",
          )
        )
          change(
            E.cardStatus(s, uid, {
              negation: {
                active: dialog.querySelector("[data-card-negated]").checked,
                duration: dialog.querySelector("[data-card-negation-duration]")
                  .value,
                reason: dialog.querySelector("[data-card-negation-reason]")
                  .value,
              },
            }),
          );
      } catch (err) {
        dialog.querySelector("[data-card-menu-status]").textContent =
          err.message;
      }
    });
    dialog.addEventListener("keydown", (e) => {
      if (e.key === "Escape") e.stopPropagation();
    });
    dialog.addEventListener("close", () => {
      const button = node.querySelector(`[data-play-token-menu="${uid}"]`);
      (button || trigger)?.focus({ preventScroll: true });
    });
    signal.addEventListener(
      "abort",
      () => {
        dialog.close();
        dialog.remove();
      },
      { once: true },
    );
    return { open };
  }
  root.YgoPlayCardMenu = { create };
})(window);
