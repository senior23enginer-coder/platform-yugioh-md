"""Public entry points must reference real local modules after reorganizing them."""
from pathlib import Path
import re
import unittest
from urllib.parse import unquote, urlsplit

ROOT=Path(__file__).resolve().parents[1]


def composed_entry_source():
    """Inspect the actual presentation include order without initializing a database."""
    pattern = r"(?:<\?php\s+)?require\s+YGO_APP_ROOT\s*\.\s*'(/includes/presentation/[^']+)'\s*;(?:\s*\?>)?"
    def expand(relative):
        text = (ROOT / relative).read_text(encoding='utf-8-sig')
        return re.sub(pattern, lambda match: expand(match[1].lstrip('/')), text)
    return (expand('index.php') +
            (ROOT/'includes/bootstrap/dependencies.php').read_text(encoding='utf-8-sig'))


class AssetPathsTests(unittest.TestCase):
    def test_combo_creator_menu_deck_and_server_are_integrated(self):
        index=composed_entry_source()
        app=(ROOT/'assets/app.js').read_text(encoding='utf-8')
        self.assertIn('data-route="creador_combos"',index)
        self.assertIn("'/includes/decks/combo-creator-view.php'",index)
        self.assertIn('data-deck-category-open="created-combos"',index)
        self.assertIn('data-deck-created-combos',index)
        self.assertIn("'/includes/decks/combo-creator-http.php'",index)
        self.assertIn('window.YgoComboCreator?.mountViewer',app)
        self.assertIn("'created-combos': [document.querySelector('.deck-created-combos-panel')]",app)
    def test_entry_point_assets_exist(self):
        for entry in ['index.php','play-calculator.html']:
            text=composed_entry_source() if entry=='index.php' else (ROOT/entry).read_text(encoding='utf-8-sig')
            for value in re.findall(r'(?:src|href)=["\'](\./assets/[^"\']+)["\']',text):
                self.assertTrue((ROOT/unquote(urlsplit(value).path)).is_file(),(entry,value))

    def test_all_pwa_shell_resources_exist(self):
        text=(ROOT/'service-worker.js').read_text(encoding='utf-8-sig').split('const APP_SHELL = [',1)[1].split('];',1)[0]
        for value in re.findall(r"['\"](\./[^'\"]*)['\"]",text):
            self.assertTrue((ROOT/unquote(urlsplit(value).path)).exists(),value)

    def test_dependency_order_and_category_are_integrated(self):
        index=composed_entry_source()
        scripts=re.findall(r'<script\s+src="([^"]+)"',index)
        urls=[urlsplit(s).path for s in scripts]
        self.assertLess(urls.index('./assets/modules/play-calculator/engine.js'),urls.index('./assets/modules/play-calculator/ui.js'))
        self.assertLess(urls.index('./assets/modules/play-calculator/storage.js'),urls.index('./assets/modules/play-calculator/ui.js'))
        self.assertLess(urls.index('./assets/modules/play-calculator/ui.js'),urls.index('./assets/app.js'))
        for first, second in [('engine', 'rules'), ('rules', 'planner'), ('planner', 'ui'), ('study', 'ui')]:
            self.assertLess(urls.index(f'./assets/modules/play-calculator/{first}.js'), urls.index(f'./assets/modules/play-calculator/{second}.js'))
        self.assertIn('data-deck-category-open="play-calculator"',index)
        self.assertIn("'/includes/decks/play-calculator-view.php'",index)
        app=(ROOT/'assets/app.js').read_text(encoding='utf-8-sig')
        self.assertIn("window.YgoPlayCalculator?.mount",app)

    def test_standalone_page_has_no_external_dependencies(self):
        html=(ROOT/'play-calculator.html').read_text(encoding='utf-8-sig')
        for value in re.findall(r'(?:src|href)="([^"]+)"',html):
            self.assertTrue(value.startswith('./'),value)


if __name__=='__main__':
    unittest.main()
