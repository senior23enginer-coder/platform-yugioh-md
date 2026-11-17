(function () {
  "use strict";
  const S = window.YgoPlayStorage,
    select = document.querySelector("[data-offline-deck]"),
    status = document.querySelector("#offline-status");
  const catalogs = S.catalogs()
    .map((id) => S.deck(id))
    .filter((d) => d?.cards?.some((c) => c.section === "main"));
  for (const c of catalogs) {
    const option = document.createElement("option");
    option.value = String(c.id);
    option.textContent = c.name;
    select.append(option);
  }
  if (!catalogs.length) {
    select.disabled = true;
    status.textContent =
      "Aún no hay listas preparadas en este navegador. Visita la ficha de un deck con conexión una vez para guardarlas aquí.";
    return;
  }
  const data = {
    allDecks: catalogs.map((c) => ({
      id: c.id,
      name: c.name,
      format_codes: c.formats,
    })),
    cards: catalogs.flatMap((c) =>
      c.cards.map((r) => ({
        id: Number(r.key.split(":")[1]),
        deck_id: c.id,
        card_name: r.name,
        quantity: r.quantity,
        section: r.section,
        ygoprodeck_id: r.imageId,
        role: r.role,
      })),
    ),
  };
  select.value = String(
    catalogs.find(
      (c) => c.id === Number(new URL(location.href).searchParams.get("deck")),
    )?.id ||
      S.self() ||
      catalogs[0].id,
  );
  if (!select.value) select.value = String(catalogs[0].id);
  function mount() {
    const deck = data.allDecks.find((d) => d.id === Number(select.value));
    window.YgoPlayCalculator.mount(
      document.querySelector("[data-play-calculator]"),
      deck,
      data,
    );
    status.textContent = `${catalogs.length} listas locales disponibles. Las recomendaciones y la edición del tablero se calculan en este dispositivo.`;
    const url = new URL(location.href);
    url.searchParams.set("deck", select.value);
    history.replaceState(null, "", url);
  }
  select.addEventListener("change", mount);
  mount();
  if ("serviceWorker" in navigator && navigator.onLine)
    navigator.serviceWorker.register("./service-worker.js").catch(() => {
      status.textContent +=
        " No se pudo preparar la reapertura offline; conserva esta pestaña abierta.";
    });
})();
