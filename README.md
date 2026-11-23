# cv08 – Transakcie, migrácie a REST API

*(English version below)*

Teória, príklad krok za krokom a vzor sú na stránke predmetu: [Týždeň 9](https://beata.stehlikova.website.tuke.sk/it/09.html). Vzorová aplikácia beží na <https://sigma.tuke.sk/beata.stehlikova/it/ukazky/cv08/>.

Čo dnes urobíš: pridáš do formulára checkboxy pre pivot table a zapíšeš ich v transakcii spolu s record, vypíšeš ich v detaile, založíš `sql/migrations/` s migráciami 001 a 002 (index), vyskúšaš `EXPLAIN`, a pridáš REST API, ktoré vracia tie isté dáta ako JSON.

## Terminológia

main entity = hlavná tabuľka aplikácie; lookup table = číselník; pivot table = tabuľka väzby M:N; record = jeden riadok; transaction = skupina príkazov SQL, ktoré sa zapíšu všetky alebo žiadny; migration = SQL súbor s jednou zmenou štruktúry; index = pomocná štruktúra databázy na rýchle hľadanie podľa stĺpca; JSON = textový zápis dát pre programy; REST API = adresy, ktoré vracajú JSON; endpoint = jedna adresa API. Všade, kde je vo vzore `recipes` a `categories`, použi svoju main entity a tú svoju lookup table, ktorá je s ňou spojená cez pivot table.

## Úlohy

**Úloha 9.1: Repository cv08 z cv07.** Prijmi zadanie *cv08* ([link](https://classroom50.org/tuke-fberg-stehlikova/informacne-technologie-5k/assignments/cv08/accept?k=xzmai3oh)) a repository naklonuj do `~/web/it/cv08` podľa návodu [04 Odovzdávanie](https://beata.stehlikova.website.tuke.sk/navody/04-odovzdavanie.html). Potom v termináli v Kubeflow:

```bash
cd ~/web/it
cp cv07/config.php cv07/config.example.php cv07/.gitignore cv07/.htaccess cv07/composer.json cv07/composer.lock cv07/index.php cv08/
cp -r cv07/sql cv07/src cv07/views cv08/
cd cv08
mkdir -p sql/migrations
composer install
```

Upload Folder. Kontrola: `…/cv08/` funguje ako cv07. Commit a push: `cv08: štart`.

**Úloha 9.2: Migrácie 001 a 002.** Do `sql/migrations/001_users_role_user_id.sql` zapíš príkazy z minulého týždňa (`ALTER TABLE users ADD role …`, `ALTER TABLE <main entity> ADD user_id …`, `UPDATE …`, `ALTER TABLE … ADD FOREIGN KEY …`) s komentárom, že už prebehli. Do `sql/migrations/002_index_<main entity>_<stĺpec>.sql` zapíš `CREATE INDEX` na stĺpec, podľa ktorého hľadáš alebo triediš. Spusti 002 v Adminer → SQL command. Ten istý `INDEX` doplň do `CREATE TABLE` v `sql/schema.sql`. Kontrola: Adminer → tabuľka → **Indexes** ukazuje nový index.

**Úloha 9.3: EXPLAIN.** V Adminer spusti `EXPLAIN SELECT * FROM <main entity> WHERE <stĺpec> = '<hodnota>'` a `EXPLAIN SELECT * FROM <main entity> WHERE <stĺpec> LIKE '%<text>%'`. Do tohto `README.md` zapíš pre obidva dotazy `type`, `key`, `rows` a jednou vetou, prečo sa líšia.

**Úloha 9.4: Čítanie pivot table.** Do modelu main entity pridaj `get<Lookup>s(int $id)` (JOIN pivot table na lookup table) a `get<Lookup>Ids(int $id)` (`FETCH_COLUMN`). V `show()` pošli výsledok do template a v `show.twig` ho vypíš oddelený čiarkami. Kontrola: detail ukazuje názvy zo seed dát.

**Úloha 9.5: Checkboxy vo formulári.** Do `Request.php` pridaj `postArray()`. Do `form.twig` pridaj `<fieldset>` s checkboxmi `name="<lookup>[]"`, zaškrtnuté podľa poľa `checked`. V `create()`, `store()`, `edit()`, `update()` pošli do template všetky záznamy lookup table a `checked`. Do controllera pridaj `read<Lookup>Ids()`. Kontrola: pri úprave sú zaškrtnuté hodnoty zo seed dát.

**Úloha 9.6: Zápis pivot table v transakcii.** Do modelu pridaj `private function save<Lookup>s(int $id, array $ids)` (DELETE + INSERT v cykle). `insert()` a `update()` dostanú parameter `array $ids = []` a obidve obaľ `beginTransaction()` / `try` / `commit()` / `catch (Throwable $e)` / `rollBack()` / `throw $e`. V `store()` a `update()` odovzdaj pole id. Kontrola: zmeň zaškrtnutie, ulož; v Adminer má pivot table presne tie riadky.

**Úloha 9.7: REST API.** Do `Response.php` pridaj `json()`. Vytvor `src/Controller/ApiController.php` s `index()`, `show()` (aj s hodnotami z pivot table; neznáme id → `['error' => 'Not found']` a 404) a jednou metódou pre lookup table. Do `index.php` pridaj `use` a route `GET /api/<main entity>`, `GET /api/<main entity>/{id}`, `GET /api/<lookup>`. Kontrola: adresy vrátia JSON s diakritikou; neznáme id 404.

**Úloha 9.8: Vlastný endpoint.** Vymysli a naprogramuj jednu ďalšiu adresu API (napr. `/api/<main entity>?q=`, `/api/<lookup>/{id}/<main entity>`, `/api/stats`). Do tohto `README.md` zapíš adresu, príklad odpovede a na čo by ju program použil.

**Úloha 9.9: Odovzdanie a kontrola na sigma.** Commit a push. Na GitHub skontroluj `sql/migrations/`, `ApiController.php`, `Response::json()`, `Request::postArray()`, model s `beginTransaction`. `config.php` a `vendor/` tam nie sú. Upload Folder; na sigma over checkboxy, detail, `/api/…` a 404.

**Hodnotenie:** 1 bod. Commit a push najneskôr do nasledujúceho cvičenia.

---

# cv08 – Transactions, migrations and REST API

Theory, a step-by-step example and the sample are on the course page: [Week 9](https://beata.stehlikova.website.tuke.sk/it/09.html). The sample application runs at <https://sigma.tuke.sk/beata.stehlikova/it/ukazky/cv08/>.

What you do today: add checkboxes for the pivot table to the form and write them in a transaction together with the record, print them in the detail, create `sql/migrations/` with migrations 001 and 002 (index), try `EXPLAIN`, and add a REST API that returns the same data as JSON.

## Terminology

main entity = the application's main table; lookup table = a code list; pivot table = an M:N link table; record = one row; transaction = a group of SQL commands written all or none; migration = an SQL file with one structure change; index = an auxiliary database structure for fast lookup by a column; JSON = a text notation of data for programs; REST API = addresses that return JSON; endpoint = one API address. Wherever the sample has `recipes` and `categories`, use your main entity and the lookup table of yours connected to it through a pivot table.

## Tasks

**Task 9.1: Repository cv08 from cv07.** Accept the assignment *cv08* ([link](https://classroom50.org/tuke-fberg-stehlikova/informacne-technologie-5k/assignments/cv08/accept?k=xzmai3oh)) and clone the repository into `~/web/it/cv08` following the guide [04 Submitting](https://beata.stehlikova.website.tuke.sk/navody/04-odovzdavanie.html). Then in the terminal in Kubeflow:

```bash
cd ~/web/it
cp cv07/config.php cv07/config.example.php cv07/.gitignore cv07/.htaccess cv07/composer.json cv07/composer.lock cv07/index.php cv08/
cp -r cv07/sql cv07/src cv07/views cv08/
cd cv08
mkdir -p sql/migrations
composer install
```

Upload Folder. Check: `…/cv08/` works like cv07. Commit and push: `cv08: štart`.

**Task 9.2: Migrations 001 and 002.** Into `sql/migrations/001_users_role_user_id.sql` write last week's commands (`ALTER TABLE users ADD role …`, `ALTER TABLE <main entity> ADD user_id …`, `UPDATE …`, `ALTER TABLE … ADD FOREIGN KEY …`) with a comment that they have already run. Into `sql/migrations/002_index_<main entity>_<column>.sql` write `CREATE INDEX` on the column you search or sort by. Run 002 in Adminer → SQL command. Add the same `INDEX` into `CREATE TABLE` in `sql/schema.sql`. Check: Adminer → table → **Indexes** shows the new index.

**Task 9.3: EXPLAIN.** In Adminer run `EXPLAIN SELECT * FROM <main entity> WHERE <column> = '<value>'` and `EXPLAIN SELECT * FROM <main entity> WHERE <column> LIKE '%<text>%'`. Into this `README.md` write `type`, `key`, `rows` for both queries and one sentence on why they differ.

**Task 9.4: Reading the pivot table.** Add `get<Lookup>s(int $id)` (JOIN of the pivot table to the lookup table) and `get<Lookup>Ids(int $id)` (`FETCH_COLUMN`) to the main entity model. In `show()` pass the result to the template and print it comma-separated in `show.twig`. Check: the detail shows the names from the seed data.

**Task 9.5: Checkboxes in the form.** Add `postArray()` to `Request.php`. Add a `<fieldset>` with checkboxes `name="<lookup>[]"` to `form.twig`, ticked according to the array `checked`. In `create()`, `store()`, `edit()`, `update()` pass all lookup table records and `checked` to the template. Add `read<Lookup>Ids()` to the controller. Check: when editing, the values from the seed data are ticked.

**Task 9.6: Writing the pivot table in a transaction.** Add `private function save<Lookup>s(int $id, array $ids)` (DELETE + INSERT in a loop) to the model. `insert()` and `update()` get the parameter `array $ids = []`; wrap both in `beginTransaction()` / `try` / `commit()` / `catch (Throwable $e)` / `rollBack()` / `throw $e`. In `store()` and `update()` pass the array of ids. Check: change the ticks, save; in Adminer the pivot table has exactly those rows.

**Task 9.7: REST API.** Add `json()` to `Response.php`. Create `src/Controller/ApiController.php` with `index()`, `show()` (with the pivot table values; unknown id → `['error' => 'Not found']` and 404) and one method for a lookup table. Add the `use` and the routes `GET /api/<main entity>`, `GET /api/<main entity>/{id}`, `GET /api/<lookup>` to `index.php`. Check: the addresses return JSON with diacritics; an unknown id 404.

**Task 9.8: Your own endpoint.** Invent and program one more API address (e.g. `/api/<main entity>?q=`, `/api/<lookup>/{id}/<main entity>`, `/api/stats`). Into this `README.md` write the address, an example response and what a program would use it for.

**Task 9.9: Submission and check on sigma.** Commit and push. On GitHub check `sql/migrations/`, `ApiController.php`, `Response::json()`, `Request::postArray()`, the model with `beginTransaction`. `config.php` and `vendor/` are not there. Upload Folder; on sigma verify the checkboxes, the detail, `/api/…` and 404.

**Grading:** 1 point. Commit and push by the next lab at the latest.
