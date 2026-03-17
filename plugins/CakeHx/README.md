# CakeHx

htmx-inspirierte AJAX-Engine für CakePHP 5.x — partielle Seitenaktualisierungen über HTML-Attribute, nativ integriert in die CakePHP-Umgebung.

## Voraussetzungen

- PHP >= 8.1
- CakePHP >= 5.0

## Installation

### 1. Plugin-Verzeichnis einbinden

Das Plugin liegt unter `plugins/CakeHx`. In der `composer.json` der Hauptanwendung muss der Autoload-Pfad registriert werden:

```json
{
    "autoload": {
        "psr-4": {
            "CakeHx\\": "plugins/CakeHx/src/"
        }
    },
    "autoload-dev": {
        "psr-4": {
            "CakeHx\\Test\\": "plugins/CakeHx/tests/"
        }
    }
}
```

Danach Autoloader neu generieren:

```bash
composer dumpautoload
```

### 2. Plugin in der Anwendung laden

In `src/Application.php`:

```php
public function bootstrap(): void
{
    parent::bootstrap();
    $this->addPlugin('CakeHx');
}
```

Das Plugin registriert automatisch die `HxMiddleware`.

### 3. Helper und Component laden

**Im Controller** (z. B. `AppController`):

```php
public function initialize(): void
{
    parent::initialize();
    $this->loadComponent('CakeHx.Hx');
}
```

**Im Layout oder Template** den Helper laden (`AppView.php`):

```php
public function initialize(): void
{
    $this->addHelper('CakeHx.Hx');
}
```

### 4. JavaScript einbinden

Im Layout vor `</body>`:

```php
<?= $this->Hx->script() ?>
```

## Verwendung

### HTML-Attribute (data-hx-*)

CakeHx funktioniert wie htmx — über deklarative HTML-Attribute:

```html
<!-- GET-Request, Ergebnis in #results einfügen -->
<button data-hx-get="/search?q=cake" data-hx-target="#results">
    Suchen
</button>

<!-- POST-Request mit Bestätigung -->
<button data-hx-post="/items/delete/5"
        data-hx-confirm="Wirklich löschen?"
        data-hx-target="#item-5"
        data-hx-swap="outerHTML">
    Löschen
</button>

<!-- Ergebnis-Container -->
<div id="results">…wird ersetzt…</div>
```

### Verfügbare Attribute

| Attribut | Beschreibung |
|----------|-------------|
| `data-hx-get="/url"` | GET-Request auslösen |
| `data-hx-post="/url"` | POST-Request auslösen |
| `data-hx-put="/url"` | PUT-Request auslösen |
| `data-hx-patch="/url"` | PATCH-Request auslösen |
| `data-hx-delete="/url"` | DELETE-Request auslösen |
| `data-hx-trigger="event"` | Auslösendes Event (Standard: click/submit) |
| `data-hx-target="#sel"` | Ziel-Element per CSS-Selektor |
| `data-hx-swap="mode"` | Swap-Modus: `innerHTML`, `outerHTML`, `beforebegin`, `afterbegin`, `beforeend`, `afterend`, `none` |
| `data-hx-select="#sel"` | Nur ein Fragment der Response verwenden |
| `data-hx-push-url="true"` | URL in die Browser-History pushen |
| `data-hx-confirm="text"` | Bestätigungsdialog vor dem Request |
| `data-hx-indicator="#sel"` | Element bekommt `.hx-request` CSS-Klasse während des Ladens |
| `data-hx-vals='{"k":"v"}'` | Zusätzliche Werte als JSON mitsenden |
| `data-hx-headers='{"k":"v"}'` | Zusätzliche Request-Header |
| `data-hx-boost="true"` | Alle Links/Formulare im Container automatisch per AJAX laden |

### HxHelper — PHP-Methoden im Template

```php
<!-- Link mit AJAX-Verhalten -->
<?= $this->Hx->link('Mehr laden', '/posts/page/2', [
    'target' => '#post-list',
    'swap' => 'beforeend',
]) ?>

<!-- Button mit DELETE-Request -->
<?= $this->Hx->button('Entfernen', '/items/42', 'delete', [
    'confirm' => 'Sicher?',
    'target' => '#item-42',
    'swap' => 'outerHTML',
]) ?>

<!-- Attribute direkt auf ein beliebiges Element setzen -->
<div <?= $this->Hx->attrs([
    'get' => '/sidebar',
    'trigger' => 'load',
    'target' => 'this',
]) ?>>
    Lädt automatisch…
</div>

<!-- Shorthand für GET -->
<input type="search"
       <?= $this->Hx->get('/search', ['trigger' => 'keyup', 'target' => '#results']) ?>
       placeholder="Suchen…">

<!-- Shorthand für POST -->
<form <?= $this->Hx->post('/comments', ['swap' => 'beforeend', 'target' => '#comments']) ?>>
    <textarea name="body"></textarea>
    <button type="submit">Absenden</button>
</form>

<!-- Boost: alle Links im Container werden automatisch AJAX -->
<?= $this->Hx->boostStart() ?>
    <a href="/seite-1">Seite 1</a>
    <a href="/seite-2">Seite 2</a>
<?= $this->Hx->boostEnd() ?>

<!-- Ladeindikator -->
<?= $this->Hx->indicator('spinner', 'Wird geladen…') ?>
<button data-hx-get="/slow" data-hx-indicator="#spinner">Laden</button>
```

### HxComponent — Server-Seite im Controller

```php
class ArticlesController extends AppController
{
    public function initialize(): void
    {
        parent::initialize();
        $this->loadComponent('CakeHx.Hx');
    }

    public function index()
    {
        $articles = $this->Articles->find()->all();
        $this->set(compact('articles'));

        // Bei HX-Request: nur das Fragment rendern (ohne Layout)
        // Dies passiert automatisch (autoNoLayout = true),
        // aber man kann zusätzlich ein anderes Template wählen:
        if ($this->Hx->isHxRequest()) {
            $this->viewBuilder()->setTemplate('index_partial');
        }
    }

    public function delete(int $id)
    {
        $this->request->allowMethod(['delete']);
        $this->Articles->deleteOrFail($this->Articles->get($id));

        if ($this->Hx->isHxRequest()) {
            // Client-Event auslösen nach dem Swap
            $this->Hx->triggerClientEvent('articleDeleted');
            return $this->response->withStringBody('');
        }

        return $this->redirect(['action' => 'index']);
    }

    public function batchAction()
    {
        // ... Aktion durchführen ...

        // Ganze Seite neu laden lassen
        $this->Hx->refresh();
    }
}
```

### CSRF-Schutz

CakeHx liest das CSRF-Token automatisch aus dem CakePHP-Meta-Tag:

```php
<!-- Im Layout <head> -->
<?= $this->Html->meta('csrfToken', $this->request->getAttribute('csrfToken')) ?>
```

Das Token wird bei POST/PUT/PATCH/DELETE-Requests automatisch als `X-CSRF-Token` Header mitgesendet.

### CSS für Ladeindikatoren

```css
/* Indikator standardmäßig versteckt */
.hx-indicator {
    display: none;
}

/* Sichtbar während des Requests */
.hx-request .hx-indicator,
.hx-request.hx-indicator {
    display: inline-block;
}

/* Optional: Element selbst dimmen während des Ladens */
.hx-request {
    opacity: 0.65;
    transition: opacity 200ms;
}
```

### JavaScript-Events

CakeHx löst Custom-Events aus, auf die man reagieren kann:

```javascript
// Vor dem Request (cancelable)
document.addEventListener('cakehx:beforeRequest', function(e) {
    console.log('Request an:', e.detail.url);
    // e.preventDefault() um den Request abzubrechen
});

// Nach dem DOM-Swap
document.addEventListener('cakehx:afterSwap', function(e) {
    console.log('Neuer Inhalt in:', e.detail.target);
});

// Bei Fehlern
document.addEventListener('cakehx:error', function(e) {
    console.error('Fehler:', e.detail.error);
});
```

## Tests ausführen

```bash
cd plugins/CakeHx
composer install
./vendor/bin/phpunit
```

## Lizenz

MIT
