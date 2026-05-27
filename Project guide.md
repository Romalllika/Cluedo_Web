# CLUEDO WEB — Полное руководство по проекту

## Содержание

1. [Обзор и стек](#1-обзор-и-стек)
2. [Структура файлов](#2-структура-файлов)
3. [База данных](#3-база-данных)
4. [Конфигурация и окружение](#4-конфигурация-и-окружение)
5. [Архитектура серверной части](#5-архитектура-серверной-части)
6. [Система JSON-карт — главный принцип](#6-система-json-карт--главный-принцип)
7. [Формат JSON-карты — детально](#7-формат-json-карты--детально)
8. [Цепочка загрузки данных карты](#8-цепочка-загрузки-данных-карты)
9. [Игровые фазы и жизненный цикл партии](#9-игровые-фазы-и-жизненный-цикл-партии)
10. [API — все действия](#10-api--все-действия)
11. [Клиентская часть (game.js)](#11-клиентская-часть-gamejs)
12. [Инструменты разработчика (tools/)](#12-инструменты-разработчика-tools)
13. [AFK и таймеры](#13-afk-и-таймеры)
14. [Правило: никаких хардкодов и фолбеков на сервере](#14-правило-никаких-хардкодов-и-фолбеков-на-сервере)
15. [Как добавить новую карту](#15-как-добавить-новую-карту)
16. [Как работать с кодом — практические правила](#16-как-работать-с-кодом--практические-правила)
17. [Известные ограничения и что ещё не сделано](#17-известные-ограничения-и-что-ещё-не-сделано)

---

## 1. Обзор и стек

Браузерная многопользовательская игра Клуэдо. Игроки по очереди ходят по полю, делают предположения и обвинения, пытаясь вычислить убийцу, орудие и комнату.

**Стек:**
- PHP 8.2+ (процедурный стиль, без фреймворков)
- MySQL 8 / MariaDB (PDO)
- Vanilla JS (Canvas API для поля)
- Никаких npm, composer, сборщиков — всё работает напрямую

**Сервер:** Apache или Nginx + PHP-FPM. Локально: AMPPS (Mac), Debian-сервер с `webuser`.

---

## 2. Структура файлов

```
cluedo_web/
│
├── index.php              — логин/регистрация
├── lobby.php              — список игр, создание новой
├── lobby_state.php        — JSON-эндпоинт состояния лобби (polling)
├── game.php               — страница игры (HTML-оболочка, данные через api.php)
├── api.php                — единственный JSON API для всей игровой логики
├── create_game.php        — POST-обработчик создания игры
├── join_game.php          — GET-обработчик входа в лобби (выбор персонажа)
├── change_seat.php        — POST/GET смена персонажа в лобби
├── leave_lobby.php        — выход из лобби ожидания
├── logout.php             — выход из аккаунта
│
├── includes/
│   ├── config.php         — подключение к БД, вспомогательные функции (db(), json_out(), h())
│   ├── cards.php          — статический реестр карточек (эталон id/type/title/legacy_name)
│   ├── data.php           — обёртки suspects()/weapons()/rooms()/characters() — НЕ используются в игре
│   ├── maps.php           — вся логика загрузки и парсинга JSON-карт
│   ├── movement.php       — движение по полю, BFS, board_paths(), board_cells()
│   ├── game_lifecycle.php — управление игрой: next_turn(), finish_game(), surrender_player()
│   └── afk.php            — AFK-таймеры, автопропуск хода, автопоказ карты
│
├── maps/
│   ├── classic_mansion.json   — основная рабочая карта (17×17)
│   └── modern_villa.json      — тестовая карта (15×15, 4 игрока, 4 оружия, 5 комнат)
│
├── tools/                 — инструменты разработчика (требуют авторизации)
│   ├── validate_maps.php  — валидатор JSON-карт (CLI и браузер)
│   ├── map_preview.php    — визуальный просмотрщик карты
│   └── cards_preview.php  — просмотрщик игровых карточек карты
│
├── assets/
│   ├── game.js            — весь клиентский JS (1200+ строк)
│   └── style.css          — стили
│
├── sql/
│   └── cluedo_web.sql     — схема и тестовые данные БД
│
├── map_tutorial.txt       — подробный туториал по формату JSON-карт
├── README.md
└── ROADMAP.md             — план задач с отметками выполнения
```

---

## 3. База данных

База данных: `cluedo_web`. Кодировка: `utf8mb4_general_ci`.

### Таблица `users`

| Поле | Тип | Описание |
|---|---|---|
| `id` | int PK AI | |
| `username` | varchar(40) UNIQUE | |
| `role` | enum('player','moderator','admin') | |
| `password_hash` | varchar(255) | bcrypt |
| `wins`, `losses`, `games_played` | int | статистика |
| `created_at` | timestamp | |

### Таблица `games`

| Поле | Тип | Описание |
|---|---|---|
| `id` | int PK AI | |
| `title` | varchar(80) | название игры |
| `owner_id` | int FK→users | создатель |
| `status` | enum('waiting','active','finished') | |
| `max_players` | tinyint | 3–6 |
| `map_id` | varchar(50) | например `classic_mansion` |
| `current_turn_player_id` | int | чей ход |
| `phase` | enum('join','roll','move','suggest','disprove','accuse','ended') | текущая фаза |
| `phase_started_at` | datetime | для AFK-таймера |
| `dice_total` | tinyint | бросок кубиков |
| `solution_suspect` | varchar(50) | legacy-имя убийцы |
| `solution_suspect_card_id` | varchar(80) | card_id убийцы |
| `solution_weapon` | varchar(50) | legacy-имя оружия |
| `solution_weapon_card_id` | varchar(80) | card_id оружия |
| `solution_room` | varchar(50) | legacy-имя комнаты |
| `solution_room_card_id` | varchar(80) | card_id комнаты |
| `winner_user_id` | int | |
| `stats_applied` | tinyint(1) | флаг обновления статистики |
| `pending_suggester_id` | int | кто сделал предположение |
| `pending_disprover_id` | int | кто должен опровергнуть |
| `pending_suspect/weapon/room` | varchar(50) | legacy-имена в предположении |
| `pending_suspect/weapon/room_card_id` | varchar(80) | card_id в предположении |
| `shown_card_name` | varchar(50) | показанная карта (legacy) |
| `shown_card_id` | varchar(80) | показанная карта (card_id) |
| `shown_by_user_id` | int | кто показал |

### Таблица `game_players`

| Поле | Тип | Описание |
|---|---|---|
| `id` | int PK AI | |
| `game_id` | int FK→games CASCADE | |
| `user_id` | int FK→users | |
| `character_name` | varchar(50) | legacy-имя персонажа |
| `seat_no` | tinyint UNIQUE per game | номер места (0-based) |
| `turn_order` | tinyint | порядок ходов |
| `pos_x`, `pos_y` | tinyint | текущая позиция на поле |
| `is_eliminated` | tinyint(1) | выбыл из игры |
| `afk_misses` | int | количество AFK-пропусков |

### Таблица `game_character_positions`

Дублирует позиции персонажей для отдельного отображения на поле. Ключ: `(game_id, character_name)` уникален.

| Поле | Тип | Описание |
|---|---|---|
| `game_id` | int FK→games CASCADE | |
| `character_name` | varchar(80) | legacy-имя |
| `pos_x`, `pos_y` | int | |

### Таблица `player_cards`

Карточки, розданные игрокам в руку.

| Поле | Тип | Описание |
|---|---|---|
| `game_id` | int FK→games CASCADE | |
| `user_id` | int FK→users | |
| `card_type` | enum('suspect','weapon','room') | |
| `card_id` | varchar(80) | стабильный id карточки |
| `card_name` | varchar(50) | legacy-имя для совместимости |

### Таблица `game_logs`

Журнал событий партии. `user_id` может быть NULL (системные сообщения).

---

## 4. Конфигурация и окружение

Файл: `includes/config.php`

Конфигурация определяется по `PHP_OS_FAMILY`:
- Mac (`Darwin`) → `root` / `mysql`
- Сервер (Debian) → `webuser` / `12345`

Есть закомментированная поддержка `.env`. Чтобы переключить на env-переменные, раскомментировать соответствующие строки в `config.php`.

**Ключевые функции из config.php:**

```php
db()              // PDO-соединение (singleton)
current_user_id() // int|null из $_SESSION['user_id']
require_auth()    // redirect на index.php если не авторизован
json_out($data)   // header + json_encode + exit
h($s)             // htmlspecialchars
```

---

## 5. Архитектура серверной части

### Принцип организации

Код процедурный. Нет классов, нет DI, нет роутеров. Каждый PHP-файл — это либо страница (HTML), либо обработчик действия (redirect), либо API-эндпоинт (JSON).

### Цепочка includes

Типичный файл страницы подключает:

```php
require 'includes/config.php';    // db(), session, вспомогалки
require 'includes/cards.php';     // статические id карточек (только где нужно)
require 'includes/maps.php';      // вся работа с JSON-картами
require 'includes/movement.php';  // движение (только game/api)
require 'includes/game_lifecycle.php'; // управление партией (только api)
require 'includes/afk.php';       // AFK (только api)
```

**Важно:** `data.php` больше не используется в игровых файлах. Он остался для обратной совместимости, но ни один файл игровой логики его не подключает. Не добавляй `require 'includes/data.php'` в новые файлы.

### Какой файл за что отвечает

**`includes/maps.php`** — единственный источник данных карты. Читает JSON, возвращает:
- `load_map_config_by_id(string $mapId): array` — загружает JSON карты
- `load_map_config(int $gid): array` — то же по game_id
- `available_maps(): array` — список всех карт из `maps/`
- `mansion_rooms(int $gid): array` — геометрия комнат
- `map_cards(int $gid): array` — все карточки карты
- `map_suspect_cards(int $gid): array` — только suspects
- `map_weapon_cards(int $gid): array` — только weapons
- `map_room_cards(int $gid): array` — только rooms
- `characters_for_game(int $gid): array` — персонажи со стартами
- `character_starts_from_config(array $map): array` — стартовые позиции
- `map_card_by_id(int $gid, string $id): ?array` — карточка по id
- `map_legacy_card_name_to_id(int $gid, string $type, string $name): ?string` — legacy-имя → id

**`includes/movement.php`** — движение по полю:
- `board_paths(int $gid): array` — коридоры из JSON
- `board_path_keys(int $gid): array` — то же в виде `["x:y" => true]`
- `board_cells(int $gid): array` — полное состояние поля для JS
- `reachable_targets(int $sx, int $sy, int $points, int $gid): array` — достижимые клетки и комнаты
- `bfs_distances_from(int $sx, int $sy, int $gid): array` — BFS от точки
- `room_at(int $x, int $y, int $gid): ?string` — комната в точке
- `is_walk_cell(int $x, int $y, int $gid): bool` — является ли клетка коридором

**`includes/game_lifecycle.php`** — управление партией:
- `next_turn(int $gid)` — передать ход следующему
- `finish_game(int $gid, ?int $winnerId)` — завершить игру
- `surrender_player(int $gid, int $uid, string $reason)` — выбить игрока
- `log_msg(int $gid, ?int $uid, string $msg)` — запись в журнал
- `players(int $gid): array` — список игроков
- `game(int $gid)` — строка игры из БД

**`includes/afk.php`** — AFK-механика:
- Константы: `AFK_TURN_SECONDS = 180`, `AFK_DISPROVE_SECONDS = 120`, `AFK_MAX_MISSES = 2`
- `check_afk_timeout(int $gid)` — вызывается при каждом `state`-запросе

**`includes/cards.php`** — статический реестр карточек. Используется только как эталон id/type/title/legacy_name. Никакой игровой логики здесь нет.

---

## 6. Система JSON-карт — главный принцип

**Всё данные об игре берутся только из JSON-карты.** Никаких фолбеков на хардкодированные данные на сервере нет.

Если карта невалидна (нет `paths`, нет `cards`, нет `starts`) — игра сломается. Именно поэтому существует валидатор (`tools/validate_maps.php`), который должен пройти без ошибок перед использованием карты.

### Что хранится в JSON и только там

| Данные | Откуда берётся в коде |
|---|---|
| Список персонажей | `cards.suspects` → `map_suspect_cards()` |
| Цвет и имя персонажа | `cards.suspects[].color`, `cards.suspects[].title` |
| Стартовая позиция персонажа | `starts[card_id]` → `character_starts_from_config()` |
| Список оружий | `cards.weapons` → `map_weapon_cards()` |
| Список комнат-карточек | `cards.rooms` → `map_room_cards()` |
| Геометрия комнат на поле | `rooms.*` → `mansion_rooms()` |
| Коридоры | `paths` → `board_paths()` |
| Размер поля | `board.w`, `board.h` → `board_size()` |
| Секретные проходы | `rooms.*.secret` → `mansion_rooms()` |
| Визуальные темы комнат | `rooms.*.theme` → `mansion_rooms()` |

### Что в `cards.php` (статично, не карта)

`cards.php` — это эталонный реестр card_id для classic_mansion. Используется только в `tools/` для проверки, что id карточек валидны. В игровом коде карточки берутся только из JSON через `map_cards()`.

---

## 7. Формат JSON-карты — детально

Файл: `maps/{id}.json`. Имя файла должно совпадать с полем `id` внутри.

### Полная структура

```json
{
  "id": "classic_mansion",
  "title": "Классический особняк",
  "description": "Описание карты.",
  "variant": 0,

  "board": {
    "w": 17,
    "h": 17
  },

  "cards": {
    "suspects": [ ... ],
    "weapons":  [ ... ],
    "rooms":    [ ... ]
  },

  "starts": {
    "suspect_alex_gromov": [8, 9],
    "suspect_maria_scarlet": [7, 9]
  },

  "paths": [
    [5, 4], [6, 4], [7, 4]
  ],

  "rooms": {
    "Кухня": { ... },
    "Бальный зал": { ... }
  }
}
```

### `board`

```json
"board": { "w": 17, "h": 17 }
```

Размер поля. `w` — ширина (x), `h` — высота (y). Координаты начинаются с `[0, 0]` — верхний левый угол. X растёт вправо, Y — вниз.

### `cards.suspects` — персонаж

```json
{
  "id": "suspect_alex_gromov",
  "type": "suspect",
  "title": "Алекс Громов",
  "legacy_name": "Алекс Громов",
  "image": null,
  "color": "#d62828"
}
```

| Поле | Назначение |
|---|---|
| `id` | Стабильный идентификатор. Используется в БД, в `starts`, в логике игры. Только `[a-z0-9_]` |
| `type` | Всегда `"suspect"` |
| `title` | Отображаемое имя |
| `legacy_name` | Строковое имя для совместимости с БД (обычно = title) |
| `image` | Путь к изображению или `null` |
| `color` | HEX-цвет фишки, обязателен для suspects |

### `cards.weapons` — оружие

```json
{
  "id": "weapon_knife",
  "type": "weapon",
  "title": "Кинжал",
  "legacy_name": "Кинжал",
  "image": null
}
```

Аналогично suspects, но без `color`. `type` всегда `"weapon"`.

### `cards.rooms` — карточка комнаты

```json
{
  "id": "room_kitchen",
  "type": "room",
  "title": "Кухня",
  "legacy_name": "Кухня",
  "image": null,
  "room_key": "Кухня"
}
```

`room_key` — ключ соответствующей записи в блоке `rooms`. Связывает карточку с геометрией на поле.

### `starts` — стартовые позиции

```json
"starts": {
  "suspect_alex_gromov": [8, 9],
  "suspect_maria_scarlet": [7, 9]
}
```

Ключ — `id` из `cards.suspects`. Значение — `[x, y]`. Каждый персонаж должен иметь запись. Позиция должна быть в `paths`, не внутри комнаты, в пределах поля.

### `paths` — коридоры

```json
"paths": [
  [5, 4], [6, 4], [7, 4], [8, 4]
]
```

Массив точек `[x, y]`. Только эти клетки доступны для ходьбы. Точки не должны быть внутри комнат. Все paths должны быть связаны между собой (нет изолированных островов). Все стартовые позиции из `starts` должны входить в `paths`.

### `rooms` — геометрия комнат на поле

```json
"rooms": {
  "Кухня": {
    "card_id": "room_kitchen",
    "x1": 0,
    "y1": 0,
    "x2": 4,
    "y2": 4,
    "door": [4, 4],
    "secret": "Кабинет",
    "theme": "kitchen"
  }
}
```

| Поле | Описание |
|---|---|
| Ключ (`"Кухня"`) | Уникальное имя зоны на поле. Используется в движке как ключ. |
| `card_id` | ID карточки из `cards.rooms`. Связывает геометрию с игровой сущностью. |
| `x1, y1` | Верхний левый угол прямоугольника включительно |
| `x2, y2` | Нижний правый угол включительно |
| `door` | `[x, y]` — координата двери. Должна быть на границе комнаты. Рядом должен быть `paths`-сосед. |
| `secret` | Ключ комнаты назначения или `null`. Желательно взаимный. |
| `theme` | Строка для визуального стиля (`"kitchen"`, `"ballroom"` и т.д.) |

**Правила геометрии:**
- `x1 <= x2`, `y1 <= y2`
- Комната полностью в пределах поля (`x2 < w`, `y2 < h`)
- Комнаты не пересекаются
- Дверь внутри комнаты, на её границе
- Рядом с дверью есть хотя бы одна `paths`-клетка
- Эта клетка достижима от стартовой зоны

### Лимиты карточек (в валидаторе)

- `suspects`: максимум 12
- `weapons`: максимум 12
- `rooms`: максимум 24

Классическое Клуэдо: 6 suspects, 6 weapons, 9 rooms.

---

## 8. Цепочка загрузки данных карты

```
Игрок создаёт игру (create_game.php)
  → записывает map_id в games.map_id
  → characters_for_game($gid) → читает JSON → строит список персонажей
  → pos_x/pos_y берутся из $c['x']/$c['y'] (из JSON starts)

api.php action=state
  → load_map_config($gid)
    → map_id_for_game($gid) → SELECT map_id FROM games
    → normalize_map_id() → проверяет что карта существует в available_maps()
    → load_map_config_by_id() → file_get_contents('maps/{id}.json') + json_decode
  → mansion_rooms($gid) → из $map['rooms']
  → board_cells($gid) → movement.php → board_paths() из $map['paths']
  → map_suspect/weapon/room_cards($gid) → из $map['cards']
  → characters_for_game($gid) → suspects + starts

api.php action=start (раздача карт)
  → map_suspect_cards($gid) → из JSON
  → map_weapon_cards($gid) → из JSON
  → map_room_cards($gid) → из JSON
  → случайно выбрать solution, остальное раздать игрокам
```

### Кеширование

`load_map_config_by_id` кешируется в статическом массиве в рамках одного запроса. `available_maps()` тоже кешируется статически. `map_id_for_game` кешируется по `$gid`. Кеш живёт только в рамках одного HTTP-запроса.

---

## 9. Игровые фазы и жизненный цикл партии

### Фазы (`games.phase`)

```
join      — лобби ожидания игроков
roll      — активный игрок должен бросить кубики
move      — активный игрок должен сделать ход
suggest   — активный игрок может сделать предположение (если в комнате)
disprove  — другой игрок должен показать карту
accuse    — активный игрок может сделать обвинение или завершить ход
ended     — игра завершена
```

### Граф переходов

```
join → (start) → roll
roll → (roll) → move
     → (secretPassage) → suggest
move → (move на путь) → suggest (если оказался в комнате) или roll следующего
     → (move в комнату) → suggest
suggest → (suggest) → disprove
        → (пропустить suggest) → accuse
disprove → (showCard) → accuse
accuse → (accuse верно) → ended
       → (accuse неверно) → выбыть, roll следующего
       → (endTurn) → roll следующего
ended → конец
```

### Создание и старт игры

1. `create_game.php` (POST) — создаёт строку в `games`, добавляет создателя как первого игрока (seat 0, персонаж 0 из JSON)
2. `join_game.php` (GET, `?game_id=N&seat=N`) — добавляет нового игрока с выбранным местом
3. `change_seat.php` — смена персонажа в лобби
4. `api.php?action=start` — перемешивает карточки, выбирает решение, раздаёт руки, сбрасывает позиции, устанавливает фазу `roll`

### Раздача карт (`action=start`)

Все карточки берутся из JSON (`map_suspect/weapon/room_cards($gid)`). Одна случайная suspects, одна weapon, одна room откладываются как «решение» и сохраняются в `games.solution_*`. Остальные перемешиваются и равномерно раздаются игрокам в `player_cards`.

---

## 10. API — все действия

Файл: `api.php`. Все запросы — POST (или GET) с полем `action` и `game_id`.

### `action=state`

Возвращает полное состояние игры. Вызывается каждые 2.5 секунды клиентом. Также вызывает `check_afk_timeout()`.

Ответ содержит:
```json
{
  "game": { ...строка из games... },
  "players": [ ...игроки... ],
  "myCards": [ ...карточки текущего игрока... ],
  "board": { "width": 17, "height": 17, "rooms": {...}, "paths": [...], "starts": [...] },
  "suspects": ["Алекс Громов", ...],
  "weapons": [...],
  "roomNames": [...],
  "cardsMeta": [...],
  "suspectCards": [...],
  "weaponCards": [...],
  "roomCards": [...],
  "reachable": [...],
  "characters": [...],
  "positions": [...],
  "logs": [...],
  "solution": null | {...}
}
```

### `action=start`

Запускает игру. Только для `status=waiting` и `phase=join`. Только owner.

### `action=roll`

Бросает 2 кубика. Переводит в фазу `move`. Возвращает `d1`, `d2`, `total`.

### `action=secretPassage`

Использует секретный проход из текущей комнаты. Переводит в фазу `suggest`.

### `action=move`

Параметры: `x`, `y`. Перемещает персонажа. Если клетка — комната, переводит в `suggest`. Иначе — в `accuse` (можно endTurn).

### `action=suggest`

Параметры: `suspect_id` + `suspect` (legacy), `weapon_id` + `weapon` (legacy), `room` (текущая комната). Выбирает опровергающего, переводит в `disprove`.

### `action=showCard`

Параметры: `card_id` или `card_name`. Только для `pending_disprover_id`. Показывает карту suggester'у. Переводит в `accuse`.

### `action=accuse`

Параметры: `suspect_id` + `suspect`, `weapon_id` + `weapon`, `room_id` + `room`. Если совпадает с решением — победа. Если нет — игрок выбывает.

### `action=endTurn`

Завершает ход без обвинения. Переводит к следующему игроку, фаза `roll`.

### `action=surrender`

Игрок сдаётся (выбывает). Если остался один — он победитель.

---

## 11. Клиентская часть (game.js)

Файл: `assets/game.js` (~1200 строк).

### Опрос сервера

```javascript
refresh(); setInterval(refresh, 2500);
```

`refresh()` делает `api('state')` и вызывает `render()`. Нет WebSocket, нет long-polling. Простой polling каждые 2.5 секунды.

### Рендеринг

`render()` вызывает:
- `renderCanvas()` — рисует поле на `<canvas>` (комнаты, коридоры, двери, фишки, reachable-клетки)
- `renderPlayersAndSeats()` — список игроков
- `renderCards()` — карточки на руке игрока
- `renderLog()` — журнал событий
- `renderDisproveFlow()` — UI для опровержения
- `renderEndGameFlow()` — финальный экран

### Canvas

Клетка: `meta.cell = 40px`. Поле центрируется по canvas. Рисование идёт в функциях `drawCorridors()`, `drawRooms()`, `drawDoors()`, `drawPlayers()`. Кликабельные клетки сохраняются в `meta.clickable` и обрабатываются в `canvas.addEventListener('click', ...)`.

### Работа с карточками

Все карточки передаются с сервера как `state.suspectCards`, `state.weaponCards`, `state.roomCards`. Клиент использует:
- `cardId(card)` — `card.id || card.card_id`
- `cardLabel(card)` — `card.title || card.card_name || ...`
- `cardLegacyName(card)` — для legacy-полей в запросах
- `selectedCardPayload(select, legacyKey, idKey)` — собирает пару legacy+id из select для POST

### AFK-таймер на клиенте

`startAfkTimer(limit, age, phase)` — отображает обратный отсчёт. Синхронизируется с `phase_started_at` с сервера.

### Блокнот

Реализован в `renderNotes()`. Хранится в `localStorage`. Отображает историю показанных карт (`loadShownHistory()`, `addShownHistory()`).

---

## 12. Инструменты разработчика (tools/)

Все инструменты требуют авторизации (`require_auth()`). Не для публичного доступа.

### `tools/validate_maps.php`

**Запуск из CLI:**
```bash
php tools/validate_maps.php
```

**Запуск в браузере:** `/tools/validate_maps.php`

Проверяет все файлы из `maps/*.json`. Проверки:

1. Валидность JSON
2. `id` совпадает с именем файла
3. `title` не пустой
4. `board.w` и `board.h` положительные
5. Блок `cards` существует и содержит `suspects`, `weapons`, `rooms`
6. Каждая карточка имеет `id` (`[a-z0-9_]`), `type`, `title`, `legacy_name`
7. Нет дублей `card_id` внутри группы и между группами
8. Suspects имеют `color` в формате `#rrggbb`
9. Rooms-карточки имеют `room_key`
10. Лимиты карточек (12/12/24)
11. Блок `rooms` существует
12. Каждая `rooms.*` имеет `card_id`, `x1`, `y1`, `x2`, `y2`, `door`
13. `card_id` комнаты существует в `cards.rooms`
14. Геометрия комнаты корректна (x1≤x2, y1≤y2, в пределах поля)
15. Комнаты не пересекаются
16. Дверь на границе комнаты
17. Рядом с дверью есть `paths`-клетка
18. Блок `paths` существует и не пустой
19. Paths не выходят за поле, не внутри комнат
20. Все стартовые позиции включены в `paths`
21. Все `paths` связаны (нет изолированных островов)
22. Все двери достижимы от стартовой зоны
23. Блок `starts` существует
24. У каждого suspect есть стартовая позиция
25. Стартовые позиции в пределах поля, не внутри комнат
26. `secret` ведёт в существующую комнату
27. Предупреждение если `secret` не взаимный

**Подход к ошибкам:** ошибка (`ERROR`) блокирует использование карты, предупреждение (`WARN`) нет.

### `tools/map_preview.php`

Адрес: `/tools/map_preview.php?map=classic_mansion`

Отображает:
- Сетку поля с комнатами, коридорами, дверями, стартовыми позициями
- Секретные проходы линиями
- Подсветку ошибок валидатора на поле
- Список комнат с card_id
- Анализ расстояний от стартовой зоны до каждой двери

Использует `validate_map_file()` из `validate_maps.php` (который подключается через `require_once`).

### `tools/cards_preview.php`

Адрес: `/tools/cards_preview.php?map=classic_mansion`

Показывает все карточки карты: suspects, weapons, rooms. Для каждой выводит `id`, `type`, `legacy_name`, `image`, `color` (для suspects), `room_key` (для rooms), связь с геометрией поля.

Показывает источник данных: `JSON` (карта определила свои cards) или `Fallback` (карта без блока cards).

---

## 13. AFK и таймеры

Файл: `includes/afk.php`

### Константы

```php
AFK_TURN_SECONDS    = 180  // 3 минуты на ход
AFK_DISPROVE_SECONDS = 120 // 2 минуты на опровержение
AFK_MAX_MISSES      = 2    // после 2 пропусков — сдача
```

### Механика

`check_afk_timeout($gid)` вызывается при каждом `action=state`. Сравнивает `NOW()` с `phase_started_at` из БД.

**Фаза `disprove`:** если истёк `AFK_DISPROVE_SECONDS` — автоматически показывается первая подходящая карта (`auto_show_pending_disprove_card`). Если подходящих карт нет — игра переходит в `accuse` без показа.

**Фазы `roll`, `move`, `suggest`, `accuse`:** если истёк `AFK_TURN_SECONDS` — `afk_misses++`. При достижении `AFK_MAX_MISSES` — `surrender_player()`. Иначе — `next_turn()`.

---

## 14. Правило: никаких хардкодов и фолбеков на сервере

Это главное архитектурное правило после последнего рефакторинга.

### Что было и что стало

**Было:** код содержал хардкоженные данные в `data.php` (персонажи, оружия, комнаты) и использовал их как фолбеки когда карта не определяла что-то. Это создавало ситуацию когда неполная карта «работала» за счёт подстановки чужих данных.

**Стало:** всё или из JSON, или ошибка. Если в JSON нет `paths` — `board_paths()` вернёт пустой массив. Если нет `cards` — `map_cards_from_config()` вернёт `[]`. Если нет `starts` для персонажа — он получит позицию `[0, 0]`, что валидатор поймает как ошибку.

### Файлы которые больше не должны подключать data.php

Ни один игровой файл не должен делать `require 'includes/data.php'`. `data.php` существует только как исторический артефакт и может быть удалён после полного перехода.

### Что делать если не хватает данных

Не добавлять фолбеки в серверный код. Добавлять данные в JSON карты. Проверять через валидатор. Инструмент `tools/` для этого и создан.

---

## 15. Как добавить новую карту

1. Создать файл `maps/my_map.json`

2. Прописать минимальную структуру:
```json
{
  "id": "my_map",
  "title": "Моя карта",
  "description": "Описание.",
  "variant": 0,
  "board": { "w": 17, "h": 17 },
  "cards": {
    "suspects": [ /* минимум 3, максимум 12 */ ],
    "weapons":  [ /* минимум 3, максимум 12 */ ],
    "rooms":    [ /* минимум 3, максимум 24 */ ]
  },
  "starts": { /* card_id → [x, y] для каждого suspect */ },
  "paths":  [ /* [x, y], ... */ ],
  "rooms":  { /* "Имя зоны": { card_id, x1, y1, x2, y2, door, secret, theme } */ }
}
```

3. Проверить валидатором:
```bash
php tools/validate_maps.php
```

4. Открыть preview: `/tools/map_preview.php?map=my_map`

5. Открыть cards preview: `/tools/cards_preview.php?map=my_map`

6. Исправить все `[ERROR]`. Разобраться с `[WARN]`.

7. Только после прохождения валидатора — тестировать в реальной игре.

### Ключевые связи которые нужно соблюсти

```
cards.suspects[].id  ←→  starts{key}
cards.rooms[].id     ←→  rooms.*.card_id
cards.rooms[].room_key ←→ rooms{key}  (ключ объекта rooms)
```

### Нейминг card_id

Используй формат `{type}_{snake_case}`. Примеры: `suspect_alex_gromov`, `weapon_knife`, `room_kitchen`. Только `[a-z0-9_]`.

---

## 16. Как работать с кодом — практические правила

### Перед любым изменением

1. Прочитай затронутые файлы целиком — это процедурный код, функции не изолированы
2. Проверь что тебя не касается `data.php` (устаревший)
3. Проверь что не ломаешь API-ответ `action=state` — от него зависит весь JS

### При изменении `maps.php`

Любое изменение функций `map_cards_from_config`, `characters_for_game`, `mansion_rooms`, `board_size` затрагивает:
- `api.php` (action=state, action=start, action=suggest, action=accuse)
- `movement.php` (board_paths, reachable_targets)
- `create_game.php`, `join_game.php`, `change_seat.php`
- Все три tools/

### При изменении JSON-карты

Запускай `php tools/validate_maps.php` после каждого сохранения. Сервер кешует карту в рамках запроса, но между запросами кеша нет — изменения видны сразу.

### При изменении `api.php`

Проверяй что структура ответа `action=state` не изменилась — иначе JS сломается. Особо важные поля в ответе: `board.rooms`, `board.paths`, `suspectCards`, `weaponCards`, `roomCards`, `characters`, `positions`, `reachable`.

### При изменении `validate_maps.php`

Функция `validate_map_file(string $file, string $mapsDir, array $cardLimits): array` подключается и вызывается из `tools/map_preview.php`. Сигнатура должна оставаться трёхаргументной.

### Структура ответа `validate_map_file`

```php
[
    'title'    => string,
    'errors'   => string[],
    'warnings' => string[],
    'stats'    => [
        'board'              => '17x17',
        'rooms'              => int,
        'paths'              => int,
        'doors'              => int,
        'secrets'            => int,
        'starts'             => int,
        'has_explicit_paths' => bool,
        'cards_suspects'     => int,
        'cards_weapons'      => int,
        'cards_rooms'        => int,
    ],
]
```

### Именование

- Серверные функции: `snake_case`
- JS-функции: `camelCase`
- card_id: `type_snake_case` (например `suspect_alex_gromov`)
- PHP-файлы страниц: `snake_case.php`

---

## 17. Известные ограничения и что ещё не сделано

По ROADMAP.md — проект в состоянии alpha, идёт к beta. Ключевые невыполненные пункты:

**Критические для beta:**
- Ручное тестирование всех фаз игры (5.5)
- Безопасность: проверка `.env`, SQL-инъекции, авторизация API (раздел 13)
- Ограничение публичного доступа к `tools/` (3.17)

**Игровая механика:**
- Блокнот игрока нужно доработать (раздел 9)
- Логи неполные — не все события логируются (раздел 10)
- AFK не тестировался на реальных игроках (раздел 11)

**Карты:**
- `modern_villa.json` — тестовая, не проверена в игре
- Нет критериев допуска карты в публичный список (16.1)
- Нет балансировки secret-проходов (4.9)

**Технический долг:**
- `data.php` и `characters()` можно удалить после финального аудита
- `legacy_name` в БД можно будет убрать после полного перехода на card_id
- Fallback по имени персонажа в `starts` (старый формат) помечен как временный
- Поле `map_tutorial.txt` раздел 20 (Fallback) устарел — фолбеки на сервере убраны

**UX:**
- Адаптивность под мобильные (8.10, 8.11)
- Онбординг и правила для новых игроков (8.12)
- Улучшение UI кубиков, ходов, модальных окон (раздел 8)