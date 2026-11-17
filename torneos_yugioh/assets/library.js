(function(){
  'use strict';
  const screen=document.querySelector('[data-screen="biblioteca"]'),payload=document.getElementById('library-data');if(!screen||!payload||!window.YgoLibrarySearch)return;
  const data=JSON.parse(payload.textContent),engine=window.YgoLibrarySearch,index=engine.createIndex(data.articles),byId=new Map(data.articles.map(a=>[a.id,a]));
  const form=screen.querySelector('[data-library-search]'),input=form.querySelector('input'),category=screen.querySelector('[data-library-category]'),results=screen.querySelector('[data-library-results]'),reader=screen.querySelector('[data-library-reader]'),status=screen.querySelector('[data-library-status]');
  const escape=s=>String(s??'').replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
  let active='',limit=25,hits=[],timer;
  function updateUrl(){const url=new URL(location.href);for(const [key,value] of [['library',active],['library_q',input.value.trim()],['library_category',category.value]]){if(value)url.searchParams.set(key,value);else url.searchParams.delete(key);}history.replaceState(null,'',url.pathname+url.search+url.hash);}
  function open(id,focus=false){
    const a=byId.get(id);if(!a){reader.innerHTML='<h2 id="library-article-title">Artículo no encontrado</h2><p>El contenido puede haber cambiado. Busca por el nombre o explora las categorías.</p>';active='';return;}
    active=id;
    const related=(a.related||[]).map(id=>byId.get(id)).filter(Boolean);
    reader.innerHTML=`<div class="library-reader-heading"><span class="tag">${escape(a.category)}</span><button type="button" class="btn btn-outline-info btn-sm" data-library-copy>Copiar enlace</button></div><h2 id="library-article-title" tabindex="-1">${escape(a.title)}</h2><p class="library-lead">${escape(a.summary)}</p>${a.status?`<p class="library-state">${escape(a.status)}</p>`:''}${(a.sections||[]).map(s=>`<section><h3>${escape(s.title)}</h3>${s.paragraphs.filter(Boolean).map(p=>`<p>${escape(p)}</p>`).join('')}</section>`).join('')}<footer><p class="note">Fuente: ${escape(a.source)}. ${['Eventos registrados','Decks y equipos','Reglas registradas'].includes(a.category)?'Datos de la consulta cargada.':'Guía revisada: '+escape(data.reviewed_on)+'.'}</p>${a.href?`<a class="btn btn-outline-info" href="${escape(a.href)}">${escape(a.link_label||'Consultar ficha')}</a>`:''}${related.length?'<h3>También puedes consultar</h3><div class="library-related">'+related.map(r=>`<button type="button" class="btn btn-outline-light btn-sm" data-library-open="${escape(r.id)}">${escape(r.title)}</button>`).join('')+'</div>':''}</footer>`;
    results.querySelectorAll('[data-library-open]').forEach(b=>b.setAttribute('aria-current',b.dataset.libraryOpen===id?'true':'false'));
    if(location.hash==='#biblioteca')updateUrl();
    if(focus){reader.querySelector('h2').focus({preventScroll:true});reader.scrollIntoView({behavior:'auto',block:'start'});}
  }
  function render(){
    hits=engine.search(index,input.value,category.value);const shown=hits.slice(0,limit);
    status.textContent=`${hits.length} ${hits.length===1?'artículo encontrado':'artículos encontrados'}${category.value?' en '+category.value:''}`;
    results.innerHTML=shown.length?shown.map(({article:a,reason})=>`<button type="button" class="library-result" data-library-open="${escape(a.id)}" aria-current="${a.id===active}"><span class="library-category">${escape(a.category)}</span><strong>${escape(a.title)}</strong><span>${escape(a.summary)}</span>${input.value.trim()?`<small>${escape(reason)}</small>`:''}</button>`).join('')+(hits.length>limit?`<button type="button" class="btn btn-outline-info" data-library-more>Ver más artículos (${hits.length-limit})</button>`:''):'<div class="panel"><h2>Sin coincidencias</h2><p>Prueba con menos palabras, otra categoría o un término como «liga», «suizo» o «Elo».</p><button type="button" class="btn btn-outline-info" data-library-reset>Ver toda la biblioteca</button></div>';
    if(hits.length&&!hits.some(r=>r.article.id===active))open(hits[0].article.id);else if(!hits.length){active='';reader.innerHTML='<h2 id="library-article-title">Busca un tema para empezar</h2><p>Los artículos se muestran aquí con su explicación, ejemplos y temas relacionados.</p>';}
    if(location.hash==='#biblioteca')updateUrl();
  }
  function search(text){input.value=text;limit=25;render();}
  form.addEventListener('submit',e=>{e.preventDefault();clearTimeout(timer);render();if(hits.length)open(hits[0].article.id,true);});
  input.addEventListener('input',()=>{clearTimeout(timer);timer=setTimeout(()=>{limit=25;render();},160);});
  category.addEventListener('change',()=>{limit=25;render();});
  screen.addEventListener('click',async e=>{const button=e.target.closest('button');if(!button)return;
    if(button.hasAttribute('data-library-open'))open(button.dataset.libraryOpen,true);
    if(button.hasAttribute('data-library-query')){category.value='';search(button.dataset.libraryQuery);}
    if(button.hasAttribute('data-library-clear')||button.hasAttribute('data-library-reset')){category.value='';search('');input.focus();}
    if(button.hasAttribute('data-library-more')){limit+=25;render();}
    if(button.hasAttribute('data-library-copy')){try{await navigator.clipboard.writeText(location.href);button.textContent='Enlace copiado';}catch{button.textContent='Copia la dirección del navegador';}}
  });
  function restore(){const params=new URLSearchParams(location.search);input.value=(params.get('library_q')||'').slice(0,250);category.value=params.get('library_category')||'';active=params.get('library')||'';render();if(active)open(active);else if(hits.length)open(hits[0].article.id);}
  window.addEventListener('popstate',()=>{if(location.hash==='#biblioteca')restore();});
  window.addEventListener('dml:routechange',e=>{if(e.detail.target==='biblioteca')restore();});
  document.addEventListener('keydown',e=>{if(!screen.hidden&&e.key==='/'&&!/INPUT|TEXTAREA|SELECT/.test(document.activeElement.tagName)&&!document.activeElement.isContentEditable){e.preventDefault();input.focus();}});
  restore();
})();
