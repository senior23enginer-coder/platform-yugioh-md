"""Build deck-scoped, lossless reading material from the approved delivery.

Originals and active deck records are inputs only. No DB/network operations.
Run with --check to verify generated artifacts are current.
"""
from pathlib import Path
from html.parser import HTMLParser
from urllib.parse import quote
import argparse
import base64
import hashlib
import html
import json
import re

ROOT = Path(__file__).resolve().parents[1]
SOURCE = ROOT / 'context/gpt/Station_X_Entrega_FINAL_11_Decks_Completa_Para_Codex'
ACTIVE = ROOT / 'assets/editorial/20261101-11-decks'
DEST = ROOT / 'assets/editorial/deck-library'
IDS = [665, 670, 650, 684, 687, 653, 656, 706, 704, 707, 705]
VOID = {'area', 'base', 'br', 'col', 'embed', 'hr', 'img', 'input', 'link', 'meta', 'param', 'source', 'track', 'wbr'}


class Node:
    def __init__(self, tag='', attrs=(), opening=''):
        self.tag, self.attrs, self.children, self.opening = tag, dict(attrs), [], opening

    def walk(self):
        yield self
        for child in self.children:
            if isinstance(child, Node):
                yield from child.walk()

    def text(self):
        return ''.join(child.text() if isinstance(child, Node) else child for child in self.children)


class Document(HTMLParser):
    def __init__(self, text):
        super().__init__(convert_charrefs=True)
        self.root = Node()
        self.stack = [self.root]
        self.feed(text)

    def handle_starttag(self, tag, attrs):
        node = Node(tag, attrs, self.get_starttag_text())
        self.stack[-1].children.append(node)
        if tag not in VOID:
            self.stack.append(node)

    def handle_startendtag(self, tag, attrs):
        self.handle_starttag(tag, attrs)
        if tag not in VOID:
            self.handle_endtag(tag)

    def handle_endtag(self, tag):
        for i in range(len(self.stack)-1, 0, -1):
            if self.stack[i].tag == tag:
                del self.stack[i:]
                return

    def handle_data(self, data):
        self.stack[-1].children.append(data)


def raw_svg(node):
    if node.opening.rstrip().endswith('/>'):
        return node.opening
    return node.opening + ''.join(raw_svg(c) if isinstance(c, Node) else html.escape(c) for c in node.children) + ('' if node.tag in VOID else '</'+node.tag+'>')


def safe_html(node, deck_id, images):
    if isinstance(node, str):
        return html.escape(node)
    if node.tag in {'script', 'style', 'nav', 'aside', 'button', 'form', 'input', 'iframe'}:
        return ''
    if node.tag == 'svg':
        return '<img class="learn-delivery-diagram" alt="Diagrama del documento original" loading="lazy" src="data:image/svg+xml;charset=utf-8,' + quote(raw_svg(node), safe='') + '">'
    if node.tag == 'img':
        src = node.attrs.get('src', '')
        if src.startswith('data:image/') and ';base64,' in src:
            payload = base64.b64decode(src.split(';base64,', 1)[1])
            digest = hashlib.sha256(payload).hexdigest()
            if digest not in images:
                ext = '.png' if payload.startswith(b'\x89PNG') else '.jpg'
                images[digest] = (ext, payload)
            src = './assets/editorial/deck-library/images/'+digest+images[digest][0]
        if not re.match(r'^(https://|\./assets/|data:image/)', src):
            return ''
        return '<img class="learn-source-image" loading="lazy" alt="'+html.escape(node.attrs.get('alt', 'Imagen original del deck'), quote=True)+'" src="'+html.escape(src, quote=True)+'">'
    children = ''.join(safe_html(c, deck_id, images) for c in node.children)
    allowed = {'section','article','div','p','span','strong','em','b','i','small','ul','ol','li','details','summary','h1','h2','h3','h4','h5','h6','table','thead','tbody','tr','td','th','br','hr','pre','code','a','blockquote','figure','figcaption'}
    if node.tag not in allowed:
        return children
    attrs = ''
    if node.attrs.get('id'):
        attrs += ' id="source-'+str(deck_id)+'-'+html.escape(node.attrs['id'], quote=True)+'"'
    if node.tag in {'td','th'}:
        for key in ['colspan','rowspan']:
            if node.attrs.get(key, '').isdigit():
                attrs += ' '+key+'="'+node.attrs[key]+'"'
    if node.tag == 'a':
        href = node.attrs.get('href', '')
        if href.startswith('https://'):
            attrs += ' href="'+html.escape(href, quote=True)+'" target="_blank" rel="noopener noreferrer"'
        elif href.startswith('#'):
            attrs += ' href="#source-'+str(deck_id)+'-'+html.escape(href[1:], quote=True)+'"'
    result = '<'+node.tag+attrs+'>'+children+('' if node.tag in VOID else '</'+node.tag+'>')
    return '<div class="learn-table-scroll">'+result+'</div>' if node.tag == 'table' else result


def read(name):
    return (SOURCE/name).read_text(encoding='utf-8-sig')


def data(name):
    return json.loads(read(name))


def encoded(value):
    return (json.dumps(value, ensure_ascii=False, indent=2)+'\n').encode('utf-8')


def build():
    consolidated = data('Station_X_Analisis_11_Decks_Completos_36_Apartados.json')
    audit = data('Station_X_Auditoria_Profunda_11_Decks_2026-11-01.json')
    index = data('Station_X_Indice_Fuentes_y_Rutas_11_Decks_2026-11-01.json')
    guide = read('Station_X_Guia_11_Decks_390_Cartas_126_Combos_2026-11-01.md')
    guide_parts = list(re.finditer(r'^## (\d+)\. (.+)$', guide, re.M))
    assert len(guide_parts) == len(IDS) == len(consolidated['decks'])
    atlas_names = ['Station_X_Atlas_Visual_7_Decks_31Oct2026.html', 'Rush_Duel_Atlas_Visual_Skills_HERO_Quimicalizada.html', 'Rush_Duel_Mago_Oscuro_Gaia_Atlas_Skill.html', 'Rush_Duel_Royal_Rebels_Atlas_Skill_Hard_Rock.html']
    atlases = {name: Document(read(name)).root for name in atlas_names}
    images, outputs, coverage = {}, {}, {}
    for i, deck_id in enumerate(IDS):
        active = json.loads((ACTIVE/f'deck-{deck_id}.json').read_text(encoding='utf-8-sig'))
        # Demonstrate the individual active record retains the ENTIRE consolidated deck.
        assert active['original_record'] == consolidated['decks'][i], deck_id
        card_profiles = []
        source_profiles = next(t['data']['records'] for t in active['topics'] if str(t['id']) == '5')
        for card in active['cards']:
            profile = card['detail'] or next(p for p in source_profiles if p.get('nombre',p.get('card_name')) == card['name'])
            fields = profile.get('eighteen_obligatory_fields', profile.get('campos_18', {}))
            assert len(fields) == 18, (deck_id, card['name'])
            card_profiles.append({'name':card['name'],'section':card['section'],'profile':profile})
        start, end = guide_parts[i].start(), guide_parts[i+1].start() if i+1 < len(guide_parts) else len(guide)
        documents = [{'id':'guide', 'title':'Guía original de '+active['name'], 'kind':'markdown', 'text':guide[start:end], 'source':'Station_X_Guia_11_Decks_390_Cartas_126_Combos_2026-11-01.md', 'status':'current'}]
        if i < 7:
            atlas_name, predicate = atlas_names[0], lambda n: n.attrs.get('id') == f'deck{i+1}'
        elif deck_id in {706,704}:
            atlas_name, predicate = atlas_names[1], lambda n: n.attrs.get('id') == ('hero' if deck_id == 706 else 'alchemy')
        else:
            atlas_name, predicate = atlas_names[2 if deck_id == 707 else 3], lambda n: n.tag == 'main'
        nodes = [n for n in atlases[atlas_name].walk() if predicate(n)]
        assert len(nodes) == 1, (deck_id, atlas_name)
        documents.append({'id':'atlas', 'title':'Atlas original · solo '+active['name'], 'kind':'html', 'html':safe_html(nodes[0], deck_id, images), 'search_text':nodes[0].text(), 'source':atlas_name, 'status':'reference'})
        historical = []
        if deck_id == 705:
            historical.append({'title':'Registro analítico previo de Royal Rebel’s', 'data':data('Rush_Duel_Royal_Rebels_Registro_Analitico.json')})
            documents.append({'id':'royal-guide','title':'Estudio original de Royal Rebel’s · 36 temas', 'kind':'markdown','text':read('Rush_Duel_Royal_Rebels_Analisis_36_Apartados.md'),'source':'Rush_Duel_Royal_Rebels_Analisis_36_Apartados.md','status':'reference'})
        if deck_id == 707:
            historical.append({'title':'Registro de recepción de Mago Oscuro / Gaia', 'data':data('Rush_Duel_Mago_Oscuro_Gaia_Registro.json')})
        refs_key = {705:'official_royal_card_references',706:'official_hero_card_references',704:'official_chemical_card_references',707:'official_magician_card_references'}.get(deck_id)
        card_audit = [c for c in audit['all_cards'] if c['deck'] == index['decks'][i]['deck']]
        route_audit = [c for c in audit['all_routes'] if c['deck'] == index['decks'][i]['deck']]
        assert len(card_audit) == len(active['cards'])
        assert len(route_audit) == len(active['combos'])
        value = {'schema':'station-x-deck-library/1','deck_id':deck_id,'name':active['name'],
            'original_record':active['original_record'],
            'source_sha256':active['source_sha256'],'documents':documents,'card_checks':card_audit,'route_checks':route_audit,'card_profiles':card_profiles,
            'source_index':index['decks'][i], 'verification':audit['decks'][i],
            'official_card_texts':audit.get(refs_key,{}) if refs_key else {},'historical_records':historical,
            'coverage':{'chapters':sum(t['id']!='skill' for t in active['topics']),'skills':len(active['skills']),'cards':len(active['cards']),'routes':len(active['combos']),'hands':len(active['hands']),'original_record_preserved':True}}
        outputs[f'deck-{deck_id}.json'] = encoded(value)
        coverage[str(deck_id)] = value['coverage']
    general = {
        'title':'Criterios y notas generales de la entrega',
        'documents':[
            {'id':name,'title':title,'kind':'markdown','text':read(name),'source':name,'status':status}
            for name,title,status in [
                ('Station_X_Estado_Real_11_Decks_2026-11-01.md','Qué se comprobó y qué sigue pendiente','general'),
                ('LEEME_STATION_X_PARA_CODEX.md','Alcance y precedencia de los documentos','general'),
                ('PROMPT_MAESTRO_36_APARTADOS_ACTUALIZADO.txt','Criterios de elaboración · documento completo','general'),
                ('Station_X_Resumen_11_Decks_Completos.md','Resumen de la entrega · referencia histórica','historical')]],
        'consolidated_metadata':{k:v for k,v in consolidated.items() if k not in {'decks','rush_skills_mapping'}},
        'audit_metadata':{k:v for k,v in audit.items() if k not in {'decks','all_cards','all_routes'} and not k.startswith('official_')},
        'official_rule_references':audit['official_rule_references'],
        'index_metadata':{k:v for k,v in index.items() if k!='decks'},
        'historical_audit':data('Station_X_Auditoria_11_Decks.json')}
    outputs['general.json'] = encoded(general)
    files = []
    for p in sorted(SOURCE.rglob('*')):
        if p.is_file():
            files.append({'path':p.relative_to(SOURCE).as_posix(),'bytes':p.stat().st_size,'sha256':hashlib.sha256(p.read_bytes()).hexdigest()})
    manifest = {'schema':'station-x-deck-library/1','decks':coverage,'source_files':files,
        'totals':{k:sum(c[k] for c in coverage.values()) for k in ['chapters','cards','routes','hands']},
        'source_records_equal_consolidated':True,
        'scope':'Individual decks plus explicitly labelled general criteria and historical notes; no shared deck navigator',
        'artifacts':{name:hashlib.sha256(content).hexdigest() for name,content in outputs.items()}}
    for digest,(ext,payload) in images.items():
        outputs['images/'+digest+ext] = payload
    outputs['manifest.json'] = encoded(manifest)
    return outputs,manifest


if __name__ == '__main__':
    args = argparse.ArgumentParser()
    args.add_argument('--check', action='store_true')
    options = args.parse_args()
    outputs, manifest = build()
    for name, content in outputs.items():
        target = DEST/name
        if options.check:
            assert target.exists() and target.read_bytes() == content, f'Stale generated library: {name}'
        else:
            target.parent.mkdir(parents=True, exist_ok=True)
            target.write_bytes(content)
    print(json.dumps({'ok':True,'check':options.check,'decks':len(IDS),'source_files':len(manifest['source_files']),**manifest['totals']}))
