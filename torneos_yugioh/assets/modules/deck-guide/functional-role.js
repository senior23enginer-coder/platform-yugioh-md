(function(root){
  'use strict';
  const fold=s=>String(s||'').normalize('NFD').replace(/\p{Diacritic}/gu,'').toLowerCase();
  const connectionHelp={
    'Acceso por documentar':'La carta conserva su función; todavía falta una vía de acceso acreditada con esta lista.',
    'Sinergia directa':'Tiene una relación concreta con otra carta de este deck. Abre la ficha para ver qué hace cada una.',
    'Apoyo indirecto o recurso':'Apoya otra carta mediante recursos, costes o continuidad; consulta la condición registrada.',
    'Sinergia condicionada':'La interacción necesita una condición, material o estado específico; no está disponible automáticamente.',
    'Conflicto o incompatibilidad':'Existe una restricción o interacción que puede impedir combinar estas cartas.',
    'Staple / respuesta independiente':'Cumple una función situacional sin necesitar pertenecer al motor del arquetipo.',
    'Conexión por documentar':'Tiene función individual descrita, pero falta una relación concreta con otra carta. No significa que sea inútil.',
    'Acceso no disponible con esta lista':'Faltan materiales registrados para el acceso descrito; no se ofrece como Invocación disponible.'
  };
  const roleNames={EXTENSION_DE_CAMPO:'Desarrollar monstruos en campo',REMOCION_O_AUTODESTRUCCION:'Retirar cartas o usar destrucciones',ALIMENTA_CEMENTERIO:'Preparar recursos en Cementerio',GESTION_DESTIERRO:'Gestionar cartas desterradas',ACCESO_DECK:'Buscar o acceder al Deck',ACCESO_FUSION:'Acceder a Fusión y sus materiales',ACCESO_XYZ:'Acceder a Xyz y sus materiales',FUNCION_SITUACIONAL:'Responder a una situación',INTERRUPCION:'Interrumpir acciones del rival',RECURSO_EQUIPO:'Aprovechar equipamientos',ACCESO_SINCRO:'Acceder a Sincronía y sus materiales'};
  function presentation(c,data){
    const relations=(data.causal_relations||[]).filter(r=>r.origen.cid===c.cid||r.destino.cid===c.cid);
    const labels=[];const add=s=>{if(!labels.includes(s))labels.push(s);};
    for(const r of relations){
      if(/CONFLICTO|NO_ACTIVA/.test(r.clase))add('Conflicto o incompatibilidad');
      else if(/CONDICION|RESTRICC|EXACTO|REQUISITO|NIVELES|COLUMNA|PLAN_DE_SINCRO|CON_NOMBRE|SI_ENVIO|CON_MATERIALES|ATRIBUTO/.test(r.clase))add('Sinergia condicionada');
      else if(/INDIRECTA|COSTE_MATERIAL|GESTION_DE_RECURSOS/.test(r.clase))add('Apoyo indirecto o recurso');
      else add('Sinergia directa');
    }
    const legacy=classify(c,data);
    const current=c.current_a06;
    if(current){
      labels.length=0;
      if(current.grado==='SINERGIA_DE_ENGINE_DOCUMENTADA')add('Sinergia directa');
      else if(current.grado==='ACCESO_EXTRA_NO_ACREDITADO')add('Acceso por documentar');
      else add('Sinergia condicionada');
    }
    const independent=legacy.labels.some(l=>/Staple|Respuesta situacional/.test(l))||/handtrap|staple/.test(fold(c.role));
    if(independent)add('Staple / respuesta independiente');
    if(!current&&c.a06_classification?.tipo==='BLOQUEADA_POR_MATERIALES_NO_REGISTRADOS')add('Acceso no disponible con esta lista');
    if(!labels.length)add('Conexión por documentar');
    const peers=[...new Set(relations.filter(r=>!/CONFLICTO|NO_ACTIVA/.test(r.clase)).map(r=>r.origen.cid===c.cid?r.destino.nombre:r.origen.nombre))];
    const summary=peers.length?`Se combina con ${peers.slice(0,2).join(' y ')}${peers.length>2?` y ${peers.length-2} cartas más`:''}.`:independent?'Su utilidad es responder al rival, sin depender de una combinación del motor.':connectionHelp[labels[0]];
    const roles=[...new Set((c.causal_profile?.funciones||[]).map(f=>roleNames[f.rol]).filter(Boolean))];
    const availability=c.slots.every(s=>s.section==='reserve'||s.section==='side')?'Fuera de la lista activa: requiere incorporarla desde Side o Reserva.':'';
    return {labels,roles,summary,availability,purpose:c.functional?.purpose||c.role,explanation:relations.length?relations.map(r=>r.interaccion).join('\n'):summary};
  }
  // Presentation of documented purpose is independent of motor links and inventory availability.
  function classify(card,data){
    if(card.documented_access)return {labels:['Acceso de Sincro documentado'],purpose:card.documented_access.purpose,explanation:card.documented_access.explanation,availability:'Conserva la carta y sus cantidades registradas.',extra:true,changed:true};
    const original=card.synergy?.labels||[],name=fold(card.name),role=String(card.role||'').trim();
    const outside=card.slots.every(s=>s.section==='reserve'||s.section==='side');
    const availability=outside?(card.slots.every(s=>s.section==='reserve')?'Reserva: fuera del duelo activo.':'Side: requiere incorporarla a la lista activa.'):'Lista activa';
    const extra=card.slots.some(s=>s.section==='extra')||/\[(?:monstruo )?(?:link|synchro|fusion|xyz|extra)/i.test(name);
    const pot=data.cards.find(c=>c.slots.some(s=>s.section==='main')&&/pot of prosperity|olla de la prosperidad|pot of extravagance|olla de la extravagancia/.test(fold(c.name)));
    const staple=/nibiru|droll|ghost belle|bella fantasma|ghost ogre|ogro fantasma|ash blossom|flor de ceniza|dimensional barrier|barrera dimensional/.test(name);
    const generic=staple||/dinowrestler pankratops|eradicator epidemic virus/.test(name);
    let labels=original.filter(s=>s!=='Sin sinergia demostrada'),purpose=role,explanation='';
    if(generic){
      labels=[staple?'Staple · respuesta situacional':'Respuesta situacional'];
      purpose=role||card.practical?.find(f=>f.title==='Qué hace')?.text;
      explanation='Su función es responder a una situación del rival; no necesita pertenecer al motor del arquetipo. '+availability;
    }else if(original.includes('Sin sinergia demostrada')){
      if(extra&&pot){
        labels=[outside?'Recurso potencial del Extra para Pot':'Recurso del Extra para Pot'];
        purpose=`Puede servir como carta del Extra para el coste de ${pot.name}.`;
        explanation=`Esta utilidad no requiere Invocarla. Pot la destierra boca abajo; no la descarta ni la envía al Cementerio. No activa por ello los efectos de Cementerio de las Fusiones Fossil. Conserva los objetivos que necesites para tus jugadas.`;
        if(outside)explanation+=' Actualmente está fuera del Extra activo; ese uso exige incorporarla antes a la lista. No se mueve automáticamente.';
      }else if(extra){
        labels=['Utilidad del Extra · acceso por documentar'];
        explanation='Tiene una función individual descrita; falta documentar cómo acceder a ella con esta lista. La ausencia de una ruta no demuestra que sea inútil. '+availability;
      }else if(role&&!/^(sin datos|sin funcion|por determinar)/.test(fold(role))){
        labels=['Función independiente'];
        explanation='Tiene un papel individual documentado; su vínculo con el motor se estudia por separado. '+availability;
      }else{labels=['Función por aclarar'];purpose='Falta documentar qué aporta esta carta al plan del deck.';}
    }
    return {labels,purpose,explanation,availability,extra,changed:generic||original.includes('Sin sinergia demostrada')};
  }
  function project(data){
    if(data.delivery_v21)return {...data,cards:data.cards.map(c=>{const p=presentation(c,data);return {...c,connection:p,functional:{...c.functional,explanation:p.explanation},synergy:{...c.synergy,labels:p.labels}};})};
    if(data.a01_a07_revision)return data;
    return {...data,cards:data.cards.map(c=>{
      const functional=classify(c,data);if(!functional.changed)return {...c,functional};
      const practical=(c.practical||[]).map(f=>f.title==='Cómo aprovecharla en este deck'?{...f,text:functional.purpose+' '+functional.explanation}:f.title==='Uso y consumo de recursos'&&functional.labels.some(s=>/para Pot/.test(s))?{...f,text:functional.explanation}:f);
      const operational={...c.operational,'Uso en este deck':functional.purpose+' '+functional.explanation};
      if(functional.labels.some(s=>/para Pot/.test(s)))operational['Coste y materiales']=functional.explanation;
      return {...c,functional,role:functional.purpose,practical,operational,synergy:{...c.synergy,labels:functional.labels,function:functional.purpose,resource:functional.explanation,reason:functional.purpose,relation:functional.explanation},extra_explanation:functional.extra?functional.explanation:c.extra_explanation};
    })};
  }
  root.YgoFunctionalRole={classify,project,presentation,connectionHelp};
  if(typeof module==='object'&&module.exports)module.exports=root.YgoFunctionalRole;
})(typeof window!=='undefined'?window:globalThis);
